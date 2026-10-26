"""Polite HTTP: robots.txt, one request every few seconds per domain, a named User-Agent.

Nothing fetched is written to disk except a PDF, which lives in a temporary file
only while pdftotext reads it and is deleted straight after.
"""

from __future__ import annotations

import gzip
import os
import random
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser
import zlib
from dataclasses import dataclass, field

USER_AGENT = "PlayPoolNationBot/1.0 (+https://playpoolnation.com/; info@playpoolnation.com)"
# The token robots.txt rules are matched against.
ROBOTS_TOKEN = "PlayPoolNationBot"

MAX_HTML_BYTES = 3_000_000
MAX_PDF_BYTES = 12_000_000


class UsageLimit(Exception):
    """A service asked us to slow down (429/503) and kept asking. Stop and resume later."""


@dataclass
class Response:
    url: str
    status: int
    content_type: str
    text: str = ""
    error: str = ""

    @property
    def ok(self) -> bool:
        return 200 <= self.status < 300 and not self.error


@dataclass
class Fetcher:
    delay: float = 4.0  # seconds between requests to the same host
    timeout: float = 30.0
    retries: int = 3
    _last: dict = field(default_factory=dict)
    _robots: dict = field(default_factory=dict)
    requests: int = 0

    # ---------------------------------------------------------------- robots

    def allowed(self, url: str) -> tuple[bool, str]:
        parts = urllib.parse.urlsplit(url)
        if parts.scheme not in ("http", "https"):
            return False, "not http"
        base = f"{parts.scheme}://{parts.netloc}"
        if base not in self._robots:
            self._robots[base] = self._load_robots(base)
        rp = self._robots[base]
        if rp is None:
            return False, "robots.txt unreachable"
        if rp is True:
            return True, ""
        ok = rp.can_fetch(ROBOTS_TOKEN, url)
        return ok, "" if ok else "robots.txt disallows"

    def _load_robots(self, base: str):
        """RFC 9309: a 4xx robots.txt means no rules; an unreachable one means stay out."""
        status, body, _ = self._raw(base + "/robots.txt", max_bytes=500_000)
        if status == 0 or status >= 500:
            return None
        if status >= 400:
            return True
        rp = urllib.robotparser.RobotFileParser()
        rp.parse(body.decode("utf-8", "replace").splitlines())
        return rp

    # ----------------------------------------------------------------- fetch

    def get(self, url: str, *, check_robots: bool = True, accept_pdf: bool = True) -> Response:
        if check_robots:
            ok, why = self.allowed(url)
            if not ok:
                return Response(url, 0, "", error=why)
        status, body, info = self._raw(url, max_bytes=MAX_PDF_BYTES if accept_pdf else MAX_HTML_BYTES)
        ctype = (info.get("content-type") or "").lower() if info else ""
        final = info.get("_final_url", url) if info else url
        if status == 0:
            return Response(url, 0, "", error=info.get("_error", "network error") if info else "network error")
        if not (200 <= status < 300):
            return Response(final, status, ctype, error=f"HTTP {status}")
        if "pdf" in ctype or final.lower().split("?")[0].endswith(".pdf"):
            if not accept_pdf:
                return Response(final, status, ctype, error="pdf skipped")
            return Response(final, status, "application/pdf", text=pdf_text(body))
        if not any(t in ctype for t in ("html", "text/plain", "xml", "json")) and ctype:
            return Response(final, status, ctype, error="unsupported type " + ctype)
        charset = "utf-8"
        if "charset=" in ctype:
            charset = ctype.split("charset=")[-1].split(";")[0].strip() or "utf-8"
        try:
            text = body.decode(charset, "replace")
        except LookupError:
            text = body.decode("utf-8", "replace")
        return Response(final, status, ctype, text=text)

    def post(self, url: str, data: dict, *, headers: dict | None = None, retries: int | None = None) -> Response:
        """POST without a robots check: only used for APIs (Overpass, Places) meant to be called."""
        body = urllib.parse.urlencode(data).encode() if not isinstance(data, bytes) else data
        headers = {"Accept": "application/json, */*;q=0.5", "Accept-Encoding": "identity", **(headers or {})}
        status, raw, info = self._raw(url, data=body, headers=headers, max_bytes=50_000_000, retries=retries)
        ctype = (info.get("content-type") or "") if info else ""
        if not (200 <= status < 300):
            return Response(url, status, ctype, error=(info or {}).get("_error", f"HTTP {status}"))
        return Response(url, status, ctype, text=raw.decode("utf-8", "replace"))

    def _wait(self, host: str) -> None:
        last = self._last.get(host)
        if last is not None:
            gap = self.delay - (time.monotonic() - last)
            if gap > 0:
                time.sleep(gap + random.uniform(0, 0.5))
        self._last[host] = time.monotonic()

    def _raw(self, url: str, *, data: bytes | None = None, headers: dict | None = None, max_bytes: int = MAX_HTML_BYTES, retries: int | None = None):
        host = urllib.parse.urlsplit(url).netloc.lower()
        hdrs = {"User-Agent": USER_AGENT, "Accept": "text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.5", "Accept-Encoding": "gzip, deflate", "From": "info@playpoolnation.com"}
        hdrs.update(headers or {})
        backoff = 5.0
        limited = 0
        retries = self.retries if retries is None else retries
        for attempt in range(retries + 1):
            self._wait(host)
            self.requests += 1
            req = urllib.request.Request(url, data=data, headers=hdrs, method="POST" if data is not None else "GET")
            try:
                with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                    info = {k.lower(): v for k, v in resp.headers.items()}
                    info["_final_url"] = resp.geturl()
                    length = int(info.get("content-length") or 0)
                    if length > max_bytes:
                        return 0, b"", {"_error": f"too large ({length} bytes)"}
                    body = resp.read(max_bytes + 1)
                    if len(body) > max_bytes:
                        return 0, b"", {"_error": "too large"}
                    enc = info.get("content-encoding", "")
                    if enc == "gzip":
                        body = gzip.decompress(body)
                    elif enc == "deflate":
                        body = zlib.decompress(body)
                    return resp.status, body, info
            except urllib.error.HTTPError as e:
                if e.code in (429, 503):
                    limited += 1
                    if limited > retries:
                        raise UsageLimit(f"{host} returned {e.code} {limited} times")
                    wait = e.headers.get("Retry-After")
                    time.sleep(min(float(wait) if wait and wait.isdigit() else backoff, 300))
                    backoff *= 2
                    continue
                if e.code in (502, 504) and attempt < retries:
                    time.sleep(backoff)
                    backoff *= 2
                    continue
                return e.code, b"", {k.lower(): v for k, v in (e.headers or {}).items()}
            except (urllib.error.URLError, TimeoutError, ConnectionError, OSError) as e:
                if attempt < retries:
                    time.sleep(backoff)
                    backoff *= 2
                    continue
                return 0, b"", {"_error": f"network error: {getattr(e, 'reason', e)}"}
        return 0, b"", {"_error": "gave up"}


def pdf_text(body: bytes) -> str:
    """Text of a PDF via pdftotext. The file exists only for the length of this call."""
    fd, path = tempfile.mkstemp(suffix=".pdf", prefix="ppn-enrich-")
    try:
        with os.fdopen(fd, "wb") as f:
            f.write(body)
        out = subprocess.run(["pdftotext", "-layout", "-l", "20", path, "-"], capture_output=True, timeout=60)
        return out.stdout.decode("utf-8", "replace")
    except (OSError, subprocess.SubprocessError):
        return ""
    finally:
        try:
            os.unlink(path)
        except OSError:
            pass
