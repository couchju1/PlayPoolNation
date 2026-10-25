"""Which cities to work on, in what order, and what we know about each."""

from __future__ import annotations

import glob
import json
import os
import re

from .paths import ROOT, REPO

STATES = {
    "AL": "Alabama", "AK": "Alaska", "AZ": "Arizona", "AR": "Arkansas", "CA": "California", "CO": "Colorado", "CT": "Connecticut",
    "DE": "Delaware", "DC": "District of Columbia", "FL": "Florida", "GA": "Georgia", "HI": "Hawaii", "ID": "Idaho", "IL": "Illinois",
    "IN": "Indiana", "IA": "Iowa", "KS": "Kansas", "KY": "Kentucky", "LA": "Louisiana", "ME": "Maine", "MD": "Maryland",
    "MA": "Massachusetts", "MI": "Michigan", "MN": "Minnesota", "MS": "Mississippi", "MO": "Missouri", "MT": "Montana",
    "NE": "Nebraska", "NV": "Nevada", "NH": "New Hampshire", "NJ": "New Jersey", "NM": "New Mexico", "NY": "New York",
    "NC": "North Carolina", "ND": "North Dakota", "OH": "Ohio", "OK": "Oklahoma", "OR": "Oregon", "PA": "Pennsylvania",
    "RI": "Rhode Island", "SC": "South Carolina", "SD": "South Dakota", "TN": "Tennessee", "TX": "Texas", "UT": "Utah",
    "VT": "Vermont", "VA": "Virginia", "WA": "Washington", "WV": "West Virginia", "WI": "Wisconsin", "WY": "Wyoming",
}

SITE = "https://playpoolnation.com"


def slugify(s: str) -> str:
    s = re.sub(r"['’`]", "", s.lower().replace("&", " and "))
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")


def latest_report() -> str | None:
    files = sorted(glob.glob(os.path.join(REPO, "data", "reports", "keyword-report-*.md")))
    return files[-1] if files else None


def report_cities(path: str) -> list[dict]:
    """Rows of the report's "Cities by search volume" table, in its order."""
    out = []
    in_table = False
    for line in open(path, encoding="utf-8"):
        if line.startswith("## "):
            in_table = line.strip() == "## Cities by search volume"
            continue
        if not in_table or not line.startswith("|"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if not cells or not cells[0].isdigit() or len(cells) < 8:
            continue
        m = re.match(r"(.+),\s*([A-Z]{2})$", cells[1])
        if not m:
            continue
        name, state = m.group(1), m.group(2)
        out.append({
            "rank": int(cells[0]),
            "name": name,
            "state": state,
            "slug": slugify(name),
            "searches": int(cells[2].replace(",", "") or 0),
            "page": (SITE + cells[7]) if cells[7].startswith("/") else "",
        })
    return out


def load_config() -> dict:
    path = os.path.join(ROOT, "cities.json")
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def city(slug: str) -> dict:
    """City settings: cities.json first, then the keyword report row, then defaults."""
    cfg = load_config().get(slug)
    row = None
    report = latest_report()
    if report:
        row = next((r for r in report_cities(report) if r["slug"] == slug), None)
    if not cfg and not row:
        raise SystemExit(f"Unknown city '{slug}'. Add it to tools/enrich/cities.json or the keyword report.")
    out = {"slug": slug, "league_pages": [], "osm_name": None, "listings_page": ""}
    if row:
        out.update(name=row["name"], state=row["state"], listings_page=row["page"])
    out.update(cfg or {})
    out["osm_name"] = out.get("osm_name") or out["name"]
    out["state_name"] = STATES.get(out["state"], out["state"])
    if not out.get("listings_page"):
        out["listings_page"] = f"{SITE}/places/{slugify(out['state_name'])}/{slug}/"
    return out


def priority() -> list[str]:
    """City slugs in the order of the latest keyword report, then any extra cities in cities.json."""
    report = latest_report()
    order = [r["slug"] for r in report_cities(report)] if report else []
    for slug in load_config():
        if slug not in order and not slug.startswith("_"):
            order.append(slug)
    return order
