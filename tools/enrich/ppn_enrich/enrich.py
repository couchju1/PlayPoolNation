"""Step B: read each venue's own sources and keep only the facts.

Order of sources: the venue's website (home page, then its about, games, menu,
events pages and linked PDFs), the venue's Facebook page when it can be read
without logging in, and league operators' pages (facts gathered in Step A).
Pages and PDFs are never stored; each venue ends as one small JSON file.
"""

from __future__ import annotations

import datetime as dt
import re
import urllib.parse

from . import store
from .extract import FIELDS, extract, merge
from .http import Fetcher, UsageLimit
from .listings import host, is_pool_hall
from .text import parse_html, plain_lines

MAX_PAGES = 9
PAGE_HINT = re.compile(r"about|games?|pool|billiard|tables?|menu|food|drink|events?|calendar|leagues?|specials?|happy|entertain|amenit|faq|info|hours|rates|pricing|activities|bar\b", re.I)
SKIP_PATH = re.compile(r"/(?:cart|checkout|account|login|wp-admin|wp-login|feed|tag|category|author|privacy|terms|cdn-cgi|careers|jobs|gift|shop|store|product)s?\b|\.(?:jpe?g|png|gif|webp|svg|mp4|mov|zip|docx?|xlsx?)$", re.I)
PDF_HINT = re.compile(r"menu|special|event|league|calendar|price|rate|drink|food|tournament", re.I)
LOGIN_WALL = re.compile(r"log in to (?:facebook|continue)|you must log in|log into facebook|see more on facebook", re.I)


def _same_site(a: str, b: str) -> bool:
    ha, hb = host(a), host(b)
    return bool(ha) and (ha == hb or ha.endswith("." + hb) or hb.endswith("." + ha))


def _pick_links(links: list[tuple[str, str]], home: str) -> list[str]:
    pages, pdfs = [], []
    for url, anchor in links:
        if not url.startswith("http") or not _same_site(url, home):
            continue
        path = urllib.parse.urlsplit(url).path
        if url.lower().split("?")[0].endswith(".pdf"):
            if PDF_HINT.search(url + " " + anchor):
                pdfs.append(url)
            continue
        if SKIP_PATH.search(path) or path in ("", "/"):
            continue
        if PAGE_HINT.search(path + " " + anchor):
            pages.append(url.split("?")[0].rstrip("/") + "/" if not re.search(r"\.\w{2,4}$", path) else url.split("?")[0])
    ordered = []
    for u in pages + pdfs:
        if u not in ordered and u.rstrip("/") != home.rstrip("/"):
            ordered.append(u)
    return ordered


def _read(fetcher: Fetcher, url: str, checked: list) -> tuple[list[str], list[tuple[str, str]], str] | None:
    try:
        r = fetcher.get(url)
    except UsageLimit as e:
        # One venue's site asking us to slow down: leave this venue for the next run.
        checked.append({"url": url, "result": "rate_limited", "note": str(e)})
        return None
    if not r.ok:
        checked.append({"url": url, "result": "skipped" if "robots" in r.error else "error", "note": r.error})
        return None
    if r.content_type == "application/pdf":
        lines = plain_lines(r.text)
        checked.append({"url": r.url, "result": "read", "note": "pdf" if lines else "pdf without text"})
        return lines, [], r.url
    doc = parse_html(r.text, r.url)
    lines = doc["lines"]
    for k in ("description", "og:description"):
        if doc["meta"].get(k):
            lines.append(doc["meta"][k])
    checked.append({"url": r.url, "result": "read", "note": ""})
    return lines, doc["links"], r.url


def enrich_venue(venue: dict, city: dict, fetcher: Fetcher, league_facts: list[dict]) -> dict:
    checked_at = dt.date.today().isoformat()
    checked: list[dict] = []
    candidates: list[dict] = []
    pool_venue = is_pool_hall(venue) or venue.get("venue_type") == "pool-halls"
    website = venue.get("website", "")
    if website and not website.startswith("http"):
        website = "http://" + website

    site_pages = []
    if website:
        first = _read(fetcher, website, checked)
        if first:
            lines, links, final = first
            site_pages.append(final)
            candidates += extract(lines, final, checked_at, pool_venue=pool_venue)
            for url in _pick_links(links, final)[: MAX_PAGES - 1]:
                got = _read(fetcher, url, checked)
                if got:
                    site_pages.append(got[2])
                    candidates += extract(got[0], got[2], checked_at, pool_venue=pool_venue)

    fb = venue.get("facebook", "")
    if fb:
        got = _read(fetcher, fb, checked)
        if got:
            lines = got[0]
            if LOGIN_WALL.search(" ".join(lines[:80])):
                checked[-1].update(result="skipped", note="needs login")
            else:
                candidates += extract(lines, got[2], checked_at, pool_venue=pool_venue)

    candidates += [dict(f) for f in league_facts]

    def rank(url: str) -> int:
        if any(_same_site(url, p) for p in site_pages):
            return 0
        if "facebook.com" in url:
            return 1
        return 2

    facts = merge(candidates, rank)
    facts += default_size(venue, facts, checked_at)
    shared = venue.get("_shared_with", [])
    for f in facts:
        if f.get("source_type") == "default":
            continue
        f["source_type"] = ("official_website", "facebook", "league_operator")[rank(f["source_url"])]
        if shared and f["source_type"] == "official_website":
            # One site for several locations: the page may be about another one.
            f["flags"].append("website shared with " + ", ".join(shared))
    found = {f["field"] for f in facts if f.get("source_type") != "default"}
    return {
        "schema": 1,
        "slug": venue["slug"],
        "city": city["slug"],
        "kind": venue.get("_kind", "listing"),
        "listing": {k: venue.get(k) for k in ("id", "url", "name", "street", "city", "state", "zip", "phone", "website", "facebook", "type", "venue_type", "lat", "lng") if venue.get(k) not in (None, "")},
        "checked_at": checked_at,
        "sources_checked": checked,
        "facts": facts,
        "missing": [f for f in FIELDS if f not in found and f != "venue_status"],
    }


DEFAULT_SIZE = "7ft"
SIZE_KEYS = re.compile(r"^(7|8|9)' tables$|^snooker tables$", re.I)


def has_known_size(venue: dict) -> bool:
    """Whether our listing already says which table sizes the venue has."""
    current = venue.get("current") or {}
    return bool(current.get("sizes")) or any(SIZE_KEYS.match(k) and v for k, v in current.items())


def default_size(venue: dict, facts: list[dict], checked_at: str) -> list[dict]:
    """A place listed with pool tables but no stated size gets 7 ft tables, flagged for review.

    The count is the stated table count when there is one. The fact points at the
    page with the table count, else at our own listing (which lists the tables).
    """
    if any(f["field"] == "table_sizes" for f in facts) or has_known_size(venue):
        return []
    count = next((f for f in facts if f["field"] == "table_count" and f["confidence"] == "stated"), None)
    current_count = (venue.get("current") or {}).get("Pool tables") or (venue.get("current") or {}).get("tables")
    sources = venue.get("sources") or [{}]
    url = count["source_url"] if count else (venue.get("url") or venue.get("website") or sources[0].get("url") or "")
    if not url:
        return []
    return [{
        "field": "table_sizes",
        "key": "table_sizes:" + DEFAULT_SIZE,
        "value": {"size": DEFAULT_SIZE, "count": count["value"] if count else (current_count if isinstance(current_count, int) else None)},
        "source_url": url,
        "quote_snippet": count["quote_snippet"] if count else "Listed as a place to play pool; no table size stated anywhere.",
        "checked_at": checked_at,
        "confidence": "implied",
        "flags": ["size not stated, 7 ft by default"],
        "source_type": "default",
        "seen_on": [url],
    }]


def changes(old: dict | None, new: dict) -> list[dict]:
    """What a re-check found that differs from the last file: new, changed and gone facts."""
    if not old:
        return []
    before = {f["key"]: f for f in old.get("facts", [])}
    after = {f["key"]: f for f in new.get("facts", [])}
    out = []
    for key, f in after.items():
        if key not in before:
            out.append({"key": key, "change": "new", "value": f["value"]})
        elif before[key]["value"] != f["value"]:
            out.append({"key": key, "change": "changed", "from": before[key]["value"], "value": f["value"]})
    read_any = any(c["result"] == "read" for c in new.get("sources_checked", []))
    for key, f in before.items():
        # Only call a fact gone when its own page was read this time and no longer says it.
        if key not in after and read_any and any(c["url"] == f["source_url"] and c["result"] == "read" for c in new["sources_checked"]):
            out.append({"key": key, "change": "gone", "from": f["value"]})
    return out


def venues_for(city: dict, listings: list[dict]) -> list[dict]:
    """Our listings plus the new candidates from Step A: pool halls first, then bars."""
    links = store.read_json(city["slug"], "osm-listing-links.json", {})
    out = []
    for listing in listings:
        v = dict(listing, _kind="listing")
        fill = links.get(listing["slug"], {})
        for k in ("website", "facebook", "phone"):
            if not v.get(k) and fill.get(k):
                v[k] = fill[k]
                v.setdefault("_filled_from_osm", []).append(k)
        out.append(v)
    for c in store.read_json(city["slug"], "new-venues.json", []):
        if c.get("places_expired"):
            continue
        out.append(dict(c, _kind="new"))
    by_host: dict[str, list[str]] = {}
    for v in out:
        if v.get("website"):
            by_host.setdefault(host(v["website"]), []).append(v["name"])
    for v in out:
        names = by_host.get(host(v.get("website", "")), []) if v.get("website") else []
        if len(names) > 1:
            v["_shared_with"] = [n for n in names if n != v["name"]]
    out.sort(key=lambda v: (not (is_pool_hall(v) or v.get("venue_type") == "pool-halls"), v["_kind"] != "listing", v["name"].lower()))
    return out
