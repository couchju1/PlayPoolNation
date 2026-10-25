"""Match discovered venues against existing listings by name, address and phone.

Same idea as the plugin's Helpers\\Dedupe (whose name noise words this copies), with the
street address added because discovery sources rarely carry a website.
"""

from __future__ import annotations

import math
import re
from difflib import SequenceMatcher

NOISE = {"the", "and", "a", "of", "bar", "grill", "lounge", "club", "pub", "inc", "llc", "co", "sports", "billiards", "billiard", "tavern", "saloon"}
STREET_WORDS = {
    "avenue": "ave", "av": "ave", "street": "st", "boulevard": "blvd", "road": "rd", "drive": "dr", "lane": "ln",
    "place": "pl", "court": "ct", "circle": "cir", "parkway": "pkwy", "highway": "hwy", "north": "n", "south": "s",
    "east": "e", "west": "w", "suite": "ste", "#": "ste",
}


def normalize_name(name: str) -> str:
    name = name.lower().replace("&", " and ").replace("+", " and ")
    name = re.sub(r"['’`]", "", name)
    name = re.sub(r"[^a-z0-9]+", " ", name)
    return " ".join(w for w in name.split() if w not in NOISE)


def normalize_phone(phone: str) -> str:
    digits = re.sub(r"\D+", "", phone or "")
    return digits[-10:] if len(digits) >= 10 else ""


def normalize_street(street: str) -> str:
    s = re.sub(r"[^a-z0-9# ]+", " ", (street or "").lower())
    words = [STREET_WORDS.get(w, w) for w in s.split()]
    # Drop suite/unit numbers: they differ between sources for the same door.
    if "ste" in words:
        words = words[: words.index("ste")]
    return " ".join(words)


def house_number(street: str) -> str:
    m = re.match(r"\s*(\d+)", street or "")
    return m.group(1) if m else ""


def distance_m(lat1, lng1, lat2, lng2) -> float:
    r = 6371000.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp, dl = p2 - p1, math.radians(lng2 - lng1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(min(1.0, math.sqrt(a)))


def name_similarity(a: str, b: str) -> float:
    a, b = normalize_name(a), normalize_name(b)
    if not a or not b:
        return 0.0
    if a == b:
        return 1.0
    contains = 0.15 if (a in b or b in a) else 0.0
    return min(1.0, SequenceMatcher(None, a, b).ratio() + contains)


def compare(a: dict, b: dict) -> dict:
    """Score two venue records (name, street, phone, lat, lng). match >= 75, possible >= 45."""
    reasons = []
    score = 0.0
    sim = name_similarity(a.get("name", ""), b.get("name", ""))
    score += sim * 40
    if sim >= 0.85:
        reasons.append("similar name")
    pa = normalize_phone(a.get("phone", ""))
    if pa and pa == normalize_phone(b.get("phone", "")):
        score += 35
        reasons.append("same phone")
    sa, sb = normalize_street(a.get("street", "")), normalize_street(b.get("street", ""))
    if sa and sb:
        if sa == sb:
            score += 35
            reasons.append("same address")
        elif house_number(sa) and house_number(sa) == house_number(sb) and SequenceMatcher(None, sa, sb).ratio() > 0.7:
            score += 25
            reasons.append("similar address")
        elif house_number(sa) and house_number(sb) and house_number(sa) != house_number(sb):
            score -= 15
    if all(isinstance(x.get(k), (int, float)) for x in (a, b) for k in ("lat", "lng")):
        d = distance_m(a["lat"], a["lng"], b["lat"], b["lng"])
        if d <= 60:
            score += 30
            reasons.append("within 60 m")
        elif d <= 250:
            score += 15
            reasons.append("within 250 m")
        elif d > 5000:
            score -= 60
            reasons.append("more than 5 km apart")
    score = int(max(0, min(100, round(score))))
    cls = "match" if score >= 75 else ("possible" if score >= 45 else "distinct")
    return {"score": score, "class": cls, "reasons": reasons}


def best_match(candidate: dict, listings: list[dict]) -> dict:
    best = {"score": 0, "class": "distinct", "reasons": [], "slug": ""}
    for listing in listings:
        cmp = compare(candidate, listing)
        if cmp["score"] > best["score"]:
            best = dict(cmp, slug=listing["slug"])
    return best
