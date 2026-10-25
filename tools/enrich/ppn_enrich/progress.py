"""tools/enrich/progress.md: venues done, facts found per field and the hit rate, per city."""

from __future__ import annotations

import datetime as dt
import glob
import json
import os

from . import store
from .cities import priority
from .extract import FIELDS
from .paths import OUT, PROGRESS

LABELS = {
    "table_count": "Table count", "table_sizes": "Table sizes", "table_brands": "Table brands", "pricing_model": "Pricing",
    "league_nights": "League nights", "age_policy": "Age policy", "food": "Food", "full_bar": "Full bar", "venue_status": "Closed notice",
}


def log(city: str, message: str) -> None:
    state = store.load_state(city)
    state.setdefault("log", []).append(f"{dt.datetime.now().strftime('%Y-%m-%d %H:%M')} {message}")
    state["log"] = state["log"][-40:]
    store.save_state(city, state)


def city_stats(slug: str) -> dict | None:
    folder = os.path.join(OUT, slug)
    if not os.path.isdir(folder):
        return None
    state = store.load_state(slug)
    files = [f for f in glob.glob(os.path.join(folder, "*.json")) if not os.path.basename(f).startswith("_") and os.path.basename(f) not in ("new-venues.json", "listings.json", "league-facts.json", "osm-listing-links.json")]
    records = [json.load(open(f, encoding="utf-8")) for f in sorted(files)]
    per_field = {f: {"stated": 0, "implied": 0} for f in FIELDS}
    facts_total = stated_total = 0
    with_any = with_table = 0
    for r in records:
        fields_stated, fields_any = set(), set()
        for fact in r["facts"]:
            facts_total += 1
            fields_any.add(fact["field"])
            if fact["confidence"] == "stated":
                stated_total += 1
                fields_stated.add(fact["field"])
        for f in FIELDS:
            if f in fields_stated:
                per_field[f]["stated"] += 1
            elif f in fields_any:
                per_field[f]["implied"] += 1
        with_any += bool(fields_stated)
        with_table += bool(fields_stated & {"table_count", "table_sizes", "table_brands"})
    sources = [c for r in records for c in r.get("sources_checked", [])]
    return {
        "slug": slug,
        "state": state,
        "records": records,
        "total": state.get("total_venues", len(records)),
        "done": sum(1 for v in state.get("venues", {}).values() if v.get("status") == "done"),
        "per_field": per_field,
        "facts": facts_total,
        "stated": stated_total,
        "with_any": with_any,
        "with_table": with_table,
        "pages_read": sum(1 for c in sources if c["result"] == "read"),
        "pages_skipped": sum(1 for c in sources if c["result"] == "skipped"),
        "pages_error": sum(1 for c in sources if c["result"] == "error"),
        "new_venues": len(store.read_json(slug, "new-venues.json", [])),
        "bytes": store.dir_size(slug),
    }


def pct(a: int, b: int) -> str:
    return f"{round(100 * a / b)}%" if b else "–"


def write() -> str:
    lines = ["# Venue enrichment progress", "", f"Updated {dt.datetime.now().strftime('%Y-%m-%d %H:%M')}. Rebuilt by `python3 tools/enrich/enrich.py progress` and after every venue.", ""]
    lines += ["Cities are worked in the order of the latest keyword report; a city is finished before the next starts. "
              "A *hit* is a venue with at least one **stated** fact for that field (implied facts are counted separately and go to review flagged).", ""]
    order = priority()
    rows = [s for s in (city_stats(c) for c in order + sorted(set(os.listdir(OUT)) - set(order)) if os.path.isdir(os.path.join(OUT, c))) if s]
    lines += ["| City | Venues done | Facts (stated) | Venues with a stated fact | With a table fact | New venues found | Disk |", "|---|--:|--:|--:|--:|--:|--:|"]
    for s in rows:
        status = "done" if s["state"].get("finished_at") else ("paused" if s["state"].get("paused") else "in progress")
        lines.append(f"| {s['slug']} ({status}) | {s['done']}/{s['total']} | {s['facts']} ({s['stated']}) | {s['with_any']} ({pct(s['with_any'], s['done'])}) | {s['with_table']} ({pct(s['with_table'], s['done'])}) | {s['new_venues']} | {s['bytes'] // 1024} KB |")
    for s in rows:
        lines += ["", f"## {s['slug']}", ""]
        d = s["state"].get("discovery", {})
        if d:
            osm = d.get("osm", {})
            lines.append(f"Discovery: OpenStreetMap {osm.get('pool_tagged', 0)} pool-tagged places and {osm.get('bars', 0)} other bars; "
                         f"Google Places {'skipped (' + d['places']['skipped'] + ')' if d.get('places', {}).get('skipped') else 'used'}; "
                         f"{len(d.get('league_pages', {}))} league page(s); {d.get('matched_existing', 0)} matched our listings, {d.get('new', 0)} new.")
            lines.append("")
        lines += [f"Pages read {s['pages_read']}, skipped by robots.txt or login {s['pages_skipped']}, errors {s['pages_error']}.", ""]
        lines += ["| Field | Venues with stated fact | Hit rate | Implied only |", "|---|--:|--:|--:|"]
        for f in FIELDS:
            pf = s["per_field"][f]
            lines.append(f"| {LABELS[f]} | {pf['stated']} | {pct(pf['stated'], s['done'])} | {pf['implied']} |")
        lines += ["", "| Venue | Status | Stated facts | Implied | Sources read |", "|---|---|--:|--:|--:|"]
        venues = s["state"].get("venues", {})
        recs = {r["slug"]: r for r in s["records"]}
        for slug, v in venues.items():
            r = recs.get(slug)
            st = sum(1 for f in r["facts"] if f["confidence"] == "stated") if r else 0
            im = sum(1 for f in r["facts"] if f["confidence"] == "implied") if r else 0
            rd = sum(1 for c in r["sources_checked"] if c["result"] == "read") if r else 0
            lines.append(f"| {v.get('name', slug)} | {v.get('status', '')}{' – ' + v['note'] if v.get('note') else ''} | {st} | {im} | {rd} |")
        if s["state"].get("log"):
            lines += ["", "Run log:", ""] + [f"- {x}" for x in s["state"]["log"][-15:]]
    with open(PROGRESS, "w", encoding="utf-8") as f:
        f.write("\n".join(lines) + "\n")
    return PROGRESS
