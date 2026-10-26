#!/usr/bin/env python3
"""Venue discovery and table-data enrichment for PlayPoolNation.

    python3 tools/enrich/enrich.py run sioux-falls       # listings, discovery, enrichment
    python3 tools/enrich/enrich.py run --next            # next unfinished city in keyword-report order
    python3 tools/enrich/enrich.py recheck sioux-falls   # monthly: re-read sources, queue changes and closures
    python3 tools/enrich/enrich.py progress              # rebuild progress.md
    python3 tools/enrich/enrich.py verify sioux-falls    # every fact's source URL still answers

Output goes to tools/enrich/out/<city>/. Load it for review on the server with
`wp ppn enrich import <city>`. Nothing here writes to the site.
"""

from __future__ import annotations

import argparse
import datetime as dt
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from ppn_enrich import discover, progress, store  # noqa: E402
from ppn_enrich.cities import city as load_city, priority  # noqa: E402
from ppn_enrich.enrich import changes, enrich_venue, venues_for  # noqa: E402
from ppn_enrich.http import Fetcher, UsageLimit  # noqa: E402
from ppn_enrich.listings import from_export, from_site  # noqa: E402

EXIT_PAUSED = 75  # EX_TEMPFAIL: run the same command again later to resume.


def get_listings(city: dict, fetcher: Fetcher, export: str | None, refresh: bool) -> list[dict]:
    if export:
        rows = from_export(export)
    else:
        rows = store.read_json(city["slug"], "listings.json", None)
        if rows is not None and not refresh:
            return rows
        print(f"Reading our listings from {city['listings_page']}…")
        rows = from_site(fetcher, city["listings_page"])
    store.write_json(city["slug"], "listings.json", rows)
    print(f"  {len(rows)} listings")
    return rows


def do_enrich(city: dict, listings: list[dict], fetcher: Fetcher, *, recheck: bool = False, limit: int = 0) -> None:
    slug = city["slug"]
    state = store.load_state(slug)
    key = "recheck" if recheck else "venues"
    if recheck:
        run_id = dt.date.today().strftime("%Y-%m")
        if state.get("recheck", {}).get("_run") != run_id:
            state["recheck"] = {"_run": run_id}
            store.save_state(slug, state)
        progress.log(slug, f"re-check {run_id} started")
        state = store.load_state(slug)
    league = store.read_json(slug, "league-facts.json", {})
    venues = venues_for(city, listings)
    state["total_venues"] = len(venues)
    store.save_state(slug, state)
    crash_after = int(os.environ.get("PPN_ENRICH_CRASH_AFTER", "0") or 0)
    done_now = 0
    for v in venues:
        state = store.load_state(slug)
        bucket = state.setdefault(key, {})
        if bucket.get(v["slug"], {}).get("status") == "done":
            continue
        print(f"[{v['_kind']}] {v['name']}")
        old = store.read_json(slug, v["slug"] + ".json", None)
        record = enrich_venue(v, city, fetcher, league.get(v["slug"], []))
        if v.get("_filled_from_osm"):
            record["listing"]["filled_from_openstreetmap"] = v["_filled_from_osm"]
        if recheck:
            record["previous_checked_at"] = (old or {}).get("checked_at")
            record["changes"] = changes(old, record)
        store.write_json(slug, v["slug"] + ".json", record)
        stated = sum(1 for f in record["facts"] if f["confidence"] == "stated")
        note = "" if record["listing"].get("website") or record["listing"].get("facebook") else "no website"
        state = store.load_state(slug)
        limited = any(c["result"] == "rate_limited" for c in record["sources_checked"])
        if limited and state.get(key, {}).get(v["slug"], {}).get("status") == "retry":
            limited = False  # Retried once already: keep what we read, note the rest.
            note = "some pages rate-limited twice; not read"
        state.setdefault(key, {})[v["slug"]] = {"status": "retry" if limited else "done", "name": v["name"], "kind": v["_kind"], "at": record["checked_at"], "stated": stated, "note": "site rate-limited us; retried next run" if limited else note}
        if recheck:
            state.setdefault("venues", {}).setdefault(v["slug"], {}).update(status="done", name=v["name"], kind=v["_kind"], at=record["checked_at"], stated=stated, note=note)
        store.save_state(slug, state)
        progress.write()
        print(f"  {len(record['facts'])} facts ({stated} stated); {sum(1 for c in record['sources_checked'] if c['result'] == 'read')} pages read")
        done_now += 1
        if crash_after and done_now >= crash_after:
            print(f"Simulated crash after {done_now} venue(s) (PPN_ENRICH_CRASH_AFTER).", flush=True)
            progress.log(slug, f"simulated crash after {done_now} venues (PPN_ENRICH_CRASH_AFTER)")
            progress.write()
            os._exit(70)
        if limit and done_now >= limit:
            print(f"Stopped after --limit {limit}; run again to continue.")
            return
    removed = store.prune(slug, {v["slug"] for v in venues})
    state = store.load_state(slug)
    for gone in removed:
        state.get("venues", {}).pop(gone, None)
    if removed:
        print(f"Removed {len(removed)} venue file(s) no longer in the list: {', '.join(removed)}")
    pending = state.get("discovery", {}).get("osm_pending") or any(v.get("status") == "retry" for v in state.get(key, {}).values())
    if recheck:
        state["recheck"]["_finished"] = dt.date.today().isoformat()
    elif not pending:
        state["finished_at"] = dt.date.today().isoformat()
    else:
        state.pop("finished_at", None)
    state.pop("paused", None)
    store.save_state(slug, state)
    progress.log(slug, ("re-check" if recheck else "enrichment") + f" finished: {len(venues)} venues" + ("; some steps still to do (OpenStreetMap or rate-limited sites), run again" if pending else ""))
    progress.write()


def run_city(slug: str, args) -> None:
    city = load_city(slug)
    fetcher = Fetcher(delay=args.delay)
    purged = store.purge_places(slug)
    if purged:
        print(f"Removed expired Google Places details from {purged} candidate(s).")
    state = store.load_state(slug)
    if args.only and args.cmd == "run":
        for one in args.only.split(","):
            state.get("venues", {}).pop(one.strip(), None)
        store.save_state(slug, state)
        progress.log(slug, f"redo: {args.only}")
        state = store.load_state(slug)
    if args.redo and args.cmd == "run":
        state["venues"] = {}
        state.pop("finished_at", None)
        store.save_state(slug, state)
        progress.log(slug, "redo: enriching every venue again")
        state = store.load_state(slug)
    done = sum(1 for v in state.get("venues", {}).values() if v.get("status") == "done")
    label = "re-check" if args.cmd == "recheck" else "run"
    if args.cmd == "run" and done and not state.get("finished_at"):
        progress.log(slug, f"resumed{' after pause' if state.get('paused') else ''}: {done} venues already done, skipping them")
    elif state.get("paused"):
        progress.log(slug, f"{label} resumed after pause")
    else:
        progress.log(slug, f"{label} started")
    state = store.load_state(slug)
    state.pop("paused", None)
    store.save_state(slug, state)
    try:
        listings = get_listings(city, fetcher, args.listings, args.refresh_listings)
        if not store.load_state(slug).get("discovery", {}).get("done") or args.rediscover:
            if args.rediscover:
                s = store.load_state(slug)
                s["discovery"] = {}
                store.save_state(slug, s)
                store.write_json(slug, "_candidates.json", [])
                store.write_json(slug, "league-facts.json", {})
            print("Discovery…")
            res = discover.run(city, listings, fetcher)
            s = store.load_state(slug)
            s.pop("paused", None)
            store.save_state(slug, s)
            print(f"  {res['candidates']} candidates, {res['matched']} already listed, {res['new']} new → out/{slug}/new-venues.json")
        do_enrich(city, listings, fetcher, recheck=args.cmd == "recheck", limit=args.limit)
    except UsageLimit as e:
        s = store.load_state(slug)
        s["paused"] = str(e)
        store.save_state(slug, s)
        progress.log(slug, f"paused: {e}")
        progress.write()
        print(f"Paused: {e}. Run the same command later to resume.")
        sys.exit(EXIT_PAUSED)
    print(f"Requests made: {fetcher.requests}. out/{slug} is {store.dir_size(slug) // 1024} KB.")


def verify(slug: str, args) -> None:
    import json

    fetcher = Fetcher(delay=args.delay)
    urls = {}
    for path in store.venue_files(slug):
        for f in json.load(open(path, encoding="utf-8"))["facts"]:
            urls.setdefault(f["source_url"], 0)
            urls[f["source_url"]] += 1
    bad = 0
    for url, n in sorted(urls.items()):
        r = fetcher.get(url)
        print(f"{'OK ' if r.ok else 'BAD'} {r.status or '-'} {url} ({n} facts){'' if r.ok else ' ' + r.error}")
        bad += not r.ok
    print(f"{len(urls) - bad}/{len(urls)} source URLs answer.")
    sys.exit(1 if bad else 0)


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("cmd", choices=["run", "recheck", "progress", "verify", "cities"])
    p.add_argument("city", nargs="?", help="city slug, e.g. sioux-falls")
    p.add_argument("--next", action="store_true", help="run: the next unfinished city in keyword-report order")
    p.add_argument("--listings", help="JSON from `wp ppn enrich export <city>` instead of reading the public site")
    p.add_argument("--refresh-listings", action="store_true")
    p.add_argument("--rediscover", action="store_true", help="run Step A again")
    p.add_argument("--redo", action="store_true", help="run: enrich every venue again (e.g. after improving the extraction rules)")
    p.add_argument("--limit", type=int, default=0, help="stop after this many venues")
    p.add_argument("--only", default="", help="run: enrich just these venue slugs again (comma-separated)")
    p.add_argument("--delay", type=float, default=4.0, help="seconds between requests to one domain (default 4)")
    args = p.parse_args()

    if args.cmd == "progress":
        print(progress.write())
        return
    if args.cmd == "cities":
        for i, slug in enumerate(priority(), 1):
            st = store.read_json(slug, "_state.json", {}) if os.path.isdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), "out", slug)) else {}
            print(f"{i:3}. {slug:24} {'done ' + st['finished_at'] if st.get('finished_at') else ('started' if st else '')}")
        return
    if args.next and args.cmd == "run":
        for slug in priority():
            st = store.read_json(slug, "_state.json", {}) if os.path.isdir(os.path.join(os.path.dirname(os.path.abspath(__file__)), "out", slug)) else {}
            if not st.get("finished_at"):
                args.city = slug
                break
    if not args.city:
        p.error("give a city slug (or --next)")
    if args.cmd == "verify":
        verify(args.city, args)
        return
    run_city(args.city, args)


if __name__ == "__main__":
    main()
