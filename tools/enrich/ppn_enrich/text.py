"""HTML to plain text lines and links, without third-party parsers."""

from __future__ import annotations

import html
import re
import urllib.parse
from html.parser import HTMLParser

SKIP = {"script", "style", "noscript", "svg", "template", "iframe", "canvas", "head"}
BLOCK = {
    "p", "div", "section", "article", "li", "ul", "ol", "tr", "td", "th", "table", "br", "h1", "h2", "h3", "h4",
    "h5", "h6", "header", "footer", "nav", "main", "aside", "blockquote", "dd", "dt", "figcaption", "option", "span-block",
}


class _Parser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.parts: list[str] = []
        self.links: list[tuple[str, str]] = []
        self.skip = 0
        self._href: str | None = None
        self._anchor: list[str] = []
        self.title = ""
        self._in_title = False
        self.meta: dict[str, str] = {}

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "title":
            self._in_title = True
        if tag == "meta" and a.get("content"):
            key = (a.get("property") or a.get("name") or "").lower()
            if key in ("og:description", "description", "og:title"):
                self.meta[key] = a["content"]
        if tag in SKIP:
            self.skip += 1
            return
        if tag in BLOCK:
            self.parts.append("\n")
        if tag == "a" and a.get("href"):
            self._href = a["href"]
            self._anchor = []
        if tag == "img" and a.get("alt") and not self.skip:
            self.parts.append(" " + a["alt"] + " ")

    def handle_endtag(self, tag):
        if tag == "title":
            self._in_title = False
        if tag in SKIP:
            self.skip = max(0, self.skip - 1)
            return
        if tag in BLOCK:
            self.parts.append("\n")
        if tag == "a" and self._href is not None:
            self.links.append((self._href, " ".join("".join(self._anchor).split())))
            self._href = None

    def handle_data(self, data):
        if self._in_title:
            self.title += data
        if self.skip:
            return
        self.parts.append(data)
        if self._href is not None:
            self._anchor.append(data)


def parse_html(doc: str, base_url: str) -> dict:
    p = _Parser()
    try:
        p.feed(doc)
        p.close()
    except Exception:  # Broken markup: keep what was parsed.
        pass
    text = "".join(p.parts)
    lines = [" ".join(line.split()) for line in text.split("\n")]
    lines = [line for line in lines if line]
    links = []
    for href, anchor in p.links:
        href = html.unescape(href.strip())
        if href.startswith(("mailto:", "tel:", "javascript:", "#")):
            continue
        links.append((urllib.parse.urljoin(base_url, href).split("#")[0], anchor))
    return {"title": " ".join(p.title.split()), "lines": lines, "links": links, "meta": p.meta}


def plain_lines(text: str) -> list[str]:
    return [" ".join(line.split()) for line in text.splitlines() if line.strip()]


_SENT = re.compile(r"(?<=[.!?])\s+(?=[A-Z0-9\"“])")


def sentences(lines: list[str]) -> list[str]:
    """Lines split into sentences; short lines (menus, headings) stay whole."""
    out = []
    for line in lines:
        if len(line) > 600:
            line = line[:600]
        out.extend(s.strip() for s in _SENT.split(line) if s.strip())
    return out


def snippet(sentence: str, start: int, end: int, max_words: int = 17) -> str:
    """Up to max_words words of the sentence around [start, end)."""
    words = list(re.finditer(r"\S+", sentence))
    if len(words) <= max_words:
        return sentence.strip()
    first = next((i for i, w in enumerate(words) if w.end() > start), 0)
    last = next((i for i, w in enumerate(words) if w.start() >= end), len(words)) - 1
    span = max(0, last - first + 1)
    if span >= max_words:
        lo, hi = first, first + max_words
    else:
        pad = (max_words - span) // 2
        lo = max(0, first - pad)
        hi = min(len(words), lo + max_words)
        lo = max(0, hi - max_words)
    text = sentence[words[lo].start(): words[hi - 1].end()]
    return ("… " if lo > 0 else "") + text + (" …" if hi < len(words) else "")
