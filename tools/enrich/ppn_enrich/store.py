"""Small JSON files under out/<city>/, written atomically so a crash never leaves half a file."""

from __future__ import annotations

import datetime as dt
import json
import os
import re
import tempfile

from .paths import city_dir


def _path(city: str, name: str) -> str:
    return os.path.join(city_dir(city), name)


def read_json(city: str, name: str, default):
    path = _path(city, name)
    if not os.path.exists(path):
        return default
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def write_json(city: str, name: str, data) -> str:
    path = _path(city, name)
    fd, tmp = tempfile.mkstemp(dir=os.path.dirname(path), prefix=".tmp-", suffix=".json")
    with os.fdopen(fd, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False, sort_keys=False)
        f.write("\n")
        f.flush()
        os.fsync(f.fileno())
    os.chmod(tmp, 0o644)
    os.replace(tmp, path)
    return path


def load_state(city: str) -> dict:
    return read_json(city, "_state.json", {"venues": {}, "discovery": {}})


def save_state(city: str, state: dict) -> None:
    state["updated_at"] = dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds")
    write_json(city, "_state.json", state)


def venue_slug(name: str, city: dict) -> str:
    base = re.sub(r"['’`]", "", f"{name} {city['name']} {city['state']}".lower().replace("&", " and "))
    return re.sub(r"[^a-z0-9]+", "-", base).strip("-")


def purge_places(city: str) -> int:
    """Drop Google Places content older than the terms allow; the place ID stays."""
    today = dt.date.today().isoformat()
    rows = read_json(city, "new-venues.json", [])
    purged = 0
    for r in rows:
        if r.get("places_expires") and r["places_expires"] < today and all(s["source"] == "google_places" for s in r["sources"]):
            keep = {k: r[k] for k in ("slug", "sources", "match", "discovered_at") if k in r}
            r.clear()
            r.update(keep, name="(Google Places result, details expired)", places_expired=True)
            purged += 1
    if purged:
        write_json(city, "new-venues.json", rows)
    return purged


def dir_size(city: str) -> int:
    total = 0
    for root, _, files in os.walk(city_dir(city)):
        total += sum(os.path.getsize(os.path.join(root, f)) for f in files)
    return total


# Files in out/<city>/ that are not one venue's facts.
NOT_VENUES = {"new-venues.json", "listings.json", "league-facts.json", "osm-listing-links.json", "osm-bars.json"}


def venue_files(city: str) -> list[str]:
    """Paths of the venue files this city's state knows about."""
    state = load_state(city)
    return [os.path.join(city_dir(city), slug + ".json") for slug in state.get("venues", {}) if os.path.exists(os.path.join(city_dir(city), slug + ".json"))]


def prune(city: str, keep: set[str]) -> list[str]:
    """Remove venue files of venues no longer in the city's list (e.g. after rediscovery)."""
    removed = []
    for name in os.listdir(city_dir(city)):
        slug = name[:-5]
        if name.endswith(".json") and not name.startswith((".", "_")) and name not in NOT_VENUES and slug not in keep:
            os.unlink(os.path.join(city_dir(city), name))
            removed.append(slug)
    return removed
