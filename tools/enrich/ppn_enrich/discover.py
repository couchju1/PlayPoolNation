"""Step A: find venues we are missing in a city.

Sources: OpenStreetMap through Overpass (one query per city), Google Places Text
Search only when GOOGLE_PLACES_API_KEY is set, and league operators' public
host-location pages listed in cities.json. Candidates are matched against our
listings; new ones are written to out/<city>/new-venues.json for review. No
listing is ever created from here.
"""

from __future__ import annotations

import datetime as dt
import json
import os
import re

from . import store
from .extract import extract
from .http import Fetcher, UsageLimit
from .listings import split_links
from .leagues import league_facts, music_service, team_hosts
from .match import best_match, name_similarity, normalize_name
from .paths import ROOT, REPO
from .text import parse_html, plain_lines

# Public Overpass instances, tried in this order.
OVERPASS = [
    "https://overpass-api.de/api/interpreter",
    "https://overpass.private.coffee/api/interpreter",
    "https://maps.mail.ru/osm/tools/overpass/api/interpreter",
]
PLACES = "https://places.googleapis.com/v1/places:searchText"
# Google Maps Platform terms: only the place ID may be kept indefinitely; other content at most 30 days.
PLACES_KEEP_DAYS = 30

STREET = re.compile(
    r"\b\d{1,5}\s+(?:[NSEW]\.?\s+)?(?:[A-Z0-9][\w\.']*\s+){0,4}?(?:Ave(?:nue)?|St(?:reet)?|Blvd|Boulevard|Rd|Road|Dr(?:ive)?|Ln|Lane|Way|Pkwy|Parkway|Hwy|Highway|Pl(?:ace)?|Ct|Court|Cir(?:cle)?|Trl|Trail)\b\.?(?:\s+(?:N|S|E|W|NE|NW|SE|SW)\b)?",
)


HOTEL = re.compile(r"\bhotels?\b|\bmotel\b|\bhostel\b|\baloft\b|\bhilton\b|marriott|\bhyatt\b|\bsheraton\b|\bresort\b", re.I)


def mark_hotel(c: dict, tags: dict | None = None) -> dict:
    """A hotel with a pool table is not a place people go to play: type Other, flagged."""
    tags = tags or {}
    if tags.get("tourism") in ("hotel", "motel", "hostel", "guest_house") or HOTEL.search(c.get("name", "") + " " + c.get("website", "")):
        c["venue_type"] = "other-venue"
        c["flags"] = sorted(set(c.get("flags", []) + ["hotel: a pool table for guests, probably not a place to play"]))
    return c


def env(key: str) -> str:
    if os.environ.get(key):
        return os.environ[key]
    for path in (os.path.join(ROOT, ".env"), os.path.join(REPO, ".env")):
        if os.path.exists(path):
            for line in open(path, encoding="utf-8"):
                k, _, v = line.strip().partition("=")
                if k.strip() == key and v.strip():
                    return v.strip().strip('"').strip("'")
    return ""


def overpass_query(city: dict) -> str:
    # Set osm_admin_level when the name alone is ambiguous ("New York" is the state at 4, the city at 5).
    level = f'["admin_level"="{city["osm_admin_level"]}"]' if city.get("osm_admin_level") else ""
    return f"""[out:json][timeout:120];
area["ISO3166-2"="US-{city['state']}"]["admin_level"="4"]->.st;
area["name"="{city['osm_name']}"]["boundary"="administrative"]{level}(area.st)->.a;
(
  nwr["sport"~"billiards|pool|snooker",i](area.a);
  nwr["billiards"~"^(yes|[0-9]+)$"](area.a);
  nwr["name"~"billiard|pool hall",i](area.a);
  nwr["amenity"~"^(bar|pub)$"]["description"~"pool table|billiard",i](area.a);
);
out center tags;
(
  nwr["amenity"~"^(bar|pub|nightclub)$"](area.a);
);
out center tags;"""


def osm_candidates(fetcher: Fetcher, city: dict) -> tuple[list[dict], list[dict]]:
    """(pool venues, all other bars). Bars without a pool tag only help fill in our own listings."""
    timeout, fetcher.timeout = fetcher.timeout, 120.0  # The query itself may take a minute or two.
    errors = []
    try:
        for url in OVERPASS:
            r = fetcher.post(url, {"data": overpass_query(city)}, retries=1)
            if r.ok:
                break
            errors.append(f"{url.split('/')[2]}: {'rate limit' if r.status == 429 else r.error or r.status}")
        else:
            # Busy or unreachable everywhere: the next run tries again.
            raise UsageLimit("Overpass unavailable (" + "; ".join(errors) + ")")
    finally:
        fetcher.timeout = timeout
    elements = json.loads(r.text).get("elements", [])
    pool, bars, seen = [], [], set()
    for e in elements:
        key = f"{e['type']}/{e['id']}"
        if key in seen:
            continue
        seen.add(key)
        t = e.get("tags", {})
        if not t.get("name"):
            continue
        hint = bool(re.search(r"billiard|pool|snooker", t.get("sport", ""), re.I) or t.get("billiards") not in (None, "no")
                    or re.search(r"billiard|pool hall", t.get("name", ""), re.I) or re.search(r"pool table|billiard", t.get("description", ""), re.I))
        lat = e.get("lat", e.get("center", {}).get("lat"))
        lng = e.get("lon", e.get("center", {}).get("lon"))
        website, facebook = split_links([t.get("website", ""), t.get("contact:website", ""), t.get("contact:facebook", "")])
        street = " ".join(x for x in (t.get("addr:housenumber", ""), t.get("addr:street", "")) if x)
        c = {
            "name": t["name"],
            "street": street,
            "city": t.get("addr:city", city["name"]),
            "state": t.get("addr:state", city["state"]),
            "zip": t.get("addr:postcode", ""),
            "lat": lat,
            "lng": lng,
            "phone": t.get("phone", t.get("contact:phone", "")),
            "website": website,
            "facebook": facebook,
            "venue_type": "pool-halls" if hint and t.get("amenity") not in ("bar", "pub", "nightclub", "restaurant") else "bars-with-pool-tables",
            "sources": [{
                "source": "openstreetmap",
                "id": key,
                "url": f"https://www.openstreetmap.org/{key}",
                "evidence": ", ".join(f"{k}={t[k]}" for k in ("sport", "billiards", "leisure", "amenity") if t.get(k)),
            }],
        }
        (pool if hint else bars).append(mark_hotel(c, t))
    return pool, bars


def places_candidates(fetcher: Fetcher, city: dict, key: str) -> list[dict]:
    out = []
    until = (dt.date.today() + dt.timedelta(days=PLACES_KEEP_DAYS)).isoformat()
    for q in (f"pool hall in {city['name']}, {city['state']}", f"billiards in {city['name']}, {city['state']}"):
        body = json.dumps({"textQuery": q, "pageSize": 20}).encode()
        r = fetcher.post(PLACES, body, headers={
            "Content-Type": "application/json",
            "X-Goog-Api-Key": key,
            "X-Goog-FieldMask": "places.id,places.displayName,places.formattedAddress,places.location,places.nationalPhoneNumber,places.websiteUri,places.businessStatus",
        })
        if r.status == 429:
            raise UsageLimit("Places quota")
        if not r.ok:
            print(f"  Places search failed: {r.error}")
            continue
        for p in json.loads(r.text).get("places", []):
            if city["name"].lower() not in p.get("formattedAddress", "").lower():
                continue
            out.append({
                "name": p.get("displayName", {}).get("text", ""),
                "street": p.get("formattedAddress", "").split(",")[0],
                "city": city["name"],
                "state": city["state"],
                "lat": p.get("location", {}).get("latitude"),
                "lng": p.get("location", {}).get("longitude"),
                "phone": p.get("nationalPhoneNumber", ""),
                "website": p.get("websiteUri", ""),
                "facebook": "",
                "venue_type": "",
                "places_closed": p.get("businessStatus") == "CLOSED_PERMANENTLY",
                # Everything but the place ID is removed after this date (store.purge_places).
                "places_expires": until,
                "sources": [{"source": "google_places", "id": p.get("id", ""), "url": "", "evidence": q}],
            })
    return out


def league_page(fetcher: Fetcher, city: dict, page: dict, listings: list[dict], checked_at: str) -> tuple[list[dict], dict]:
    """(host venues named with a street address, league facts per listing slug)."""
    r = fetcher.get(page["url"])
    if not r.ok:
        print(f"  {page['url']}: {r.error}")
        return [], {}
    lines = plain_lines(r.text) if r.content_type == "application/pdf" else parse_html(r.text, r.url)["lines"]
    venues = []
    for i, line in enumerate(lines):
        m = STREET.search(line)
        if not m:
            continue
        name = line[: m.start()].strip(" ,-–—@|:·")
        name = re.sub(r"^(?:at|@|location:?|venue:?)\s+", "", name, flags=re.I)
        if len(name) < 3 and i > 0 and len(lines[i - 1]) < 60:
            name = lines[i - 1]
        if not (3 <= len(name) <= 60):
            continue
        venues.append({
            "name": name,
            "street": m.group(0).strip(),
            "city": city["name"],
            "state": city["state"],
            "lat": None,
            "lng": None,
            "phone": "",
            "website": "",
            "facebook": "",
            "venue_type": "",
            "sources": [{"source": "league", "id": page["url"], "url": page["url"], "evidence": page.get("operator", "")}],
        })
    # League nights at venues we list: lines that name the venue, read with the next two lines.
    facts: dict[str, list[dict]] = {}
    for listing in listings:
        norm = normalize_name(listing["name"])
        if not norm:
            continue
        for i, line in enumerate(lines):
            if norm not in normalize_name(line):
                continue
            window = " ".join(lines[i: i + 3])
            found = [f for f in extract([window], r.url, checked_at, pool_venue=True, own_site=False) if f["field"] == "league_nights"]
            for f in found:
                if f["value"]["league"] == "Pool league":
                    f["value"]["league"] = page.get("league") or page.get("operator") or "Pool league"
                    f["key"] = "league_nights:" + re.sub(r"[^a-z0-9]+", "-", f["value"]["league"].lower()) + ":" + (f["value"]["weekday"] or "")
                    f["flags"] = [x for x in f["flags"] if x != "league name not given"]
            facts.setdefault(listing["slug"], []).extend(found)
    return venues, facts


def music_service_page(fetcher: Fetcher, city: dict, page: dict, listings: list[dict], checked_at: str) -> tuple[list[dict], dict]:
    """League nights at our venues, and bars named by teams that we don't list."""
    operator = page.get("operator", "Music Service")
    leagues = music_service(fetcher, page, city)
    facts = league_facts(leagues, listings, operator, checked_at)
    osm_bars = store.read_json(city["slug"], "osm-bars.json", [])
    venues = []
    for host in team_hosts(leagues, listings):
        flags = ["name taken from league team names" + (" (one word; the bar's full name may be longer)" if host["guessed"] else "")]
        c = {
            "name": host["name"],
            "street": "", "city": city["name"], "state": city["state"], "zip": "",
            "lat": None, "lng": None, "phone": "", "website": "", "facebook": "",
            "venue_type": "bars-with-pool-tables",
            "flags": flags,
            "sources": [{
                "source": "league",
                "id": host["league_urls"][0],
                "url": host["league_urls"][0],
                "evidence": f"{operator} teams: " + ", ".join(host["teams"][:6]) + f" ({', '.join(host['leagues'][:4])})",
            }],
        }
        osm = max(osm_bars, key=lambda b: name_similarity(b["name"], c["name"]), default=None)
        if osm and name_similarity(osm["name"], c["name"]) >= 0.85:
            for k in ("street", "zip", "lat", "lng", "phone", "website", "facebook"):
                c[k] = c[k] or osm.get(k)
            c["sources"] += osm["sources"]
        else:
            c["flags"].append("no address found; add it when approving")
        venues.append(c)
        # Its league nights, for review once the venue is approved.
        slug = store.venue_slug(c["name"], city)
        pseudo = [{"slug": slug, "name": c["name"]}]
        for s, fs in league_facts([dict(lg, teams=[t for t in lg["teams"] if t[0] in host["teams"]]) for lg in leagues], pseudo, operator, checked_at).items():
            have = {f["key"] for f in facts.setdefault(s, [])}
            facts[s].extend(f for f in fs if f["key"] not in have)
    return venues, facts


def run(city: dict, listings: list[dict], fetcher: Fetcher) -> dict:
    state = store.load_state(city["slug"])
    disc = state.setdefault("discovery", {})
    checked_at = dt.date.today().isoformat()
    candidates = list(store.read_json(city["slug"], "_candidates.json", []))

    if not disc.get("osm"):
        print("  OpenStreetMap (Overpass)…")
        try:
            pool, bars = osm_candidates(fetcher, city)
        except UsageLimit as e:
            # Don't hold up the rest of the city; the next run retries this step only.
            print(f"  {e}; continuing without it.")
            disc["osm_pending"] = str(e)[:300]
            store.save_state(city["slug"], state)
            pool = None
        else:
            if not pool and not bars:
                # Not one bar in a city means the boundary name didn't match, not an empty city.
                msg = f"no OpenStreetMap boundary named '{city['osm_name']}' in {city['state']} (set osm_name / osm_admin_level in cities.json)"
                print(f"  {msg}; continuing without it.")
                disc["osm_pending"] = msg
                store.save_state(city["slug"], state)
                pool = None
    if not disc.get("osm") and pool is not None:
        disc.pop("osm_pending", None)
        candidates += pool
        # Plain bars only fill in missing websites/phones on listings we already have.
        fills = {}
        for b in bars:
            m = best_match(b, listings)
            if m["class"] == "match":
                fills[m["slug"]] = {"website": b["website"], "facebook": b["facebook"], "phone": b["phone"], "osm": b["sources"][0]["id"]}
        store.write_json(city["slug"], "_candidates.json", candidates)
        store.write_json(city["slug"], "osm-listing-links.json", fills)
        # Names and addresses of every bar OSM knows here (ODbL), to place bars named by league teams.
        store.write_json(city["slug"], "osm-bars.json", [{k: b[k] for k in ("name", "street", "zip", "lat", "lng", "phone", "website", "facebook", "sources")} for b in bars])
        disc["osm"] = {"at": checked_at, "pool_tagged": len(pool), "bars": len(bars)}
        store.save_state(city["slug"], state)

    key = env("GOOGLE_PLACES_API_KEY")
    if key and not disc.get("places"):
        print("  Google Places Text Search…")
        candidates += places_candidates(fetcher, city, key)
        store.write_json(city["slug"], "_candidates.json", candidates)
        disc["places"] = {"at": checked_at}
        store.save_state(city["slug"], state)
    elif not key:
        disc["places"] = {"skipped": "GOOGLE_PLACES_API_KEY not set"}

    league_facts = store.read_json(city["slug"], "league-facts.json", {})
    for page in city.get("league_pages", []):
        if page["url"] in disc.get("league_pages", {}):
            continue
        print(f"  League page {page['url']}…")
        if page.get("parser") == "musicservice":
            venues, facts = music_service_page(fetcher, city, page, listings, checked_at)
        else:
            venues, facts = league_page(fetcher, city, page, listings, checked_at)
        candidates += venues
        for slug, fs in facts.items():
            have = {f["key"] for f in league_facts.setdefault(slug, [])}
            league_facts[slug].extend(f for f in fs if f["key"] not in have)
        store.write_json(city["slug"], "_candidates.json", candidates)
        store.write_json(city["slug"], "league-facts.json", league_facts)
        disc.setdefault("league_pages", {})[page["url"]] = {"at": checked_at, "venues": len(venues), "listings_with_league_facts": len(facts)}
        store.save_state(city["slug"], state)

    # Merge duplicates between sources, then compare with our listings.
    merged: list[dict] = []
    for c in candidates:
        same = next((m for m in merged if best_match(c, [dict(m, slug="x")])["class"] == "match"), None)
        if same:
            same["sources"] += c["sources"]
            for k in ("street", "zip", "phone", "website", "facebook", "lat", "lng", "venue_type"):
                if not same.get(k) and c.get(k):
                    same[k] = c[k]
            same["flags"] = sorted(set(same.get("flags", []) + c.get("flags", [])))
        else:
            merged.append(dict(c))
    new, matched = [], []
    for c in merged:
        m = best_match(c, listings)
        c["match"] = m
        c["slug"] = store.venue_slug(c["name"], city)
        c["discovered_at"] = checked_at
        (matched if m["class"] == "match" else new).append(c)
    new.sort(key=lambda c: (c.get("venue_type") != "pool-halls", c["name"].lower()))
    store.write_json(city["slug"], "new-venues.json", new)
    if disc.get("osm"):
        disc["done"] = checked_at
    disc["new"] = len(new)
    disc["matched_existing"] = len(matched)
    store.save_state(city["slug"], state)
    return {"new": len(new), "matched": len(matched), "candidates": len(candidates)}
