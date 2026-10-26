"""Our existing listings for a city.

Preferred source: `wp ppn enrich export <city> > listings-export.json` on the server,
passed with --listings. Without it, the public city page and each venue page's
JSON-LD are read (our own site, through the same polite fetcher).
"""

from __future__ import annotations

import json
import re
import urllib.parse

from .http import Fetcher

SOCIAL = ("facebook.com", "fb.com", "instagram.com", "twitter.com", "x.com", "tiktok.com", "yelp.com", "linktr.ee", "untappd.com", "google.com", "youtube.com")
POOL_HALL_TYPES = {"pool-halls", "billiards-lounge", "pool hall", "billiards lounge"}


def host(url: str) -> str:
    h = urllib.parse.urlsplit(url if "//" in url else "http://" + url).netloc.lower()
    return h[4:] if h.startswith("www.") else h


def split_links(urls: list[str]) -> tuple[str, str]:
    website = facebook = ""
    for u in urls:
        h = host(u)
        if not h:
            continue
        if h.endswith(("facebook.com", "fb.com")):
            facebook = facebook or u
        elif not any(h == s or h.endswith("." + s) for s in SOCIAL):
            website = website or u
    return website, facebook


def from_export(path: str) -> list[dict]:
    with open(path, encoding="utf-8") as f:
        rows = json.load(f)
    out = []
    for r in rows:
        website, facebook = split_links([r.get("website", ""), *r.get("links", [])])
        out.append({
            "id": r.get("id"),
            "slug": r["slug"],
            "url": r["url"],
            "name": r["name"],
            "type": r.get("type", ""),
            "street": r.get("street", ""),
            "city": r.get("city", ""),
            "state": r.get("state", ""),
            "zip": r.get("zip", ""),
            "phone": r.get("phone", ""),
            "lat": r.get("lat"),
            "lng": r.get("lng"),
            "website": website,
            "facebook": facebook,
            "current": r.get("current", {}),
        })
    return out


def _jsonld(html: str) -> list:
    out = []
    for block in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', html, re.S):
        try:
            data = json.loads(block)
        except ValueError:
            continue
        items = data.get("@graph", [data]) if isinstance(data, dict) else data
        out.extend(i for i in items if isinstance(i, dict))
    return out


def from_site(fetcher: Fetcher, city_page: str) -> list[dict]:
    page = fetcher.get(city_page, accept_pdf=False)
    if not page.ok:
        raise SystemExit(f"Could not read {city_page}: {page.error}")
    urls = []
    for item in _jsonld(page.text):
        if item.get("@type") == "ItemList":
            urls = [e["url"] for e in item.get("itemListElement", []) if e.get("url")]
    out = []
    for url in urls:
        r = fetcher.get(url, accept_pdf=False)
        if not r.ok:
            continue
        place = next((i for i in _jsonld(r.text) if i.get("@type") in ("SportsActivityLocation", "BarOrPub", "BowlingAlley", "LocalBusiness")), None)
        if not place:
            continue
        crumbs = next((i for i in _jsonld(r.text) if i.get("@type") == "BreadcrumbList"), {})
        names = [e.get("name", "") for e in crumbs.get("itemListElement", [])]
        vtype = names[1] if len(names) > 2 else ""
        addr = place.get("address", {})
        geo = place.get("geo", {})
        same = place.get("sameAs", [])
        website, facebook = split_links(same if isinstance(same, list) else [same])
        current = {}
        for feat in place.get("amenityFeature", []):
            current[feat.get("name", "")] = feat.get("value")
        id_match = re.search(r"[?&]p=(\d+)|postid-(\d+)", r.text)
        out.append({
            "id": int(next(g for g in id_match.groups() if g)) if id_match else None,
            "slug": url.rstrip("/").split("/")[-1],
            "url": url,
            "name": place.get("name", ""),
            "type": vtype,
            "street": addr.get("streetAddress", ""),
            "city": addr.get("addressLocality", ""),
            "state": addr.get("addressRegion", ""),
            "zip": addr.get("postalCode", ""),
            "phone": place.get("telephone", ""),
            "lat": geo.get("latitude"),
            "lng": geo.get("longitude"),
            "website": website,
            "facebook": facebook,
            "current": current,
        })
    return out


def is_pool_hall(listing: dict) -> bool:
    return listing.get("type", "").lower() in POOL_HALL_TYPES or bool(re.search(r"billiard|pool hall|cue", listing.get("name", ""), re.I))
