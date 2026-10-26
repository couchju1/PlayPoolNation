"""Rule-based fact extraction from a venue's own words.

Only exact statements count. "Lots of tables" gives no table_count; a number
qualified with "over", "up to" or "+" is kept as `implied` so it goes to review
flagged, never straight through. Each fact keeps the sentence it came from,
cut to under 20 words, for the reviewer.
"""

from __future__ import annotations

import re
import urllib.parse

from .text import sentences, snippet

FIELDS = ["table_count", "table_sizes", "table_brands", "pricing_model", "league_nights", "age_policy", "food", "full_bar", "venue_status"]

NUMBER_WORDS = {
    "one": 1, "two": 2, "three": 3, "four": 4, "five": 5, "six": 6, "seven": 7, "eight": 8, "nine": 9, "ten": 10,
    "eleven": 11, "twelve": 12, "thirteen": 13, "fourteen": 14, "fifteen": 15, "sixteen": 16, "seventeen": 17,
    "eighteen": 18, "nineteen": 19, "twenty": 20, "thirty": 30, "forty": 40, "fifty": 50, "a single": 1, "single": 1,
}
_UNITS = "one|two|three|four|five|six|seven|eight|nine"
NUM = r"(\d{1,2}|(?:twenty|thirty|forty)[- ](?:%s)|%s)" % (_UNITS, "|".join(sorted(NUMBER_WORDS, key=len, reverse=True)))

WEEKDAYS = ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]
_DAY_RX = re.compile(r"\b(mon|tues?|wed(?:nes)?|weds|thu(?:rs?)?|fri|sat(?:ur)?|sun)(?:day)?s?\b\.?", re.I)
_DAY_MAP = {"mon": 0, "tue": 1, "tues": 1, "wed": 2, "wednes": 2, "weds": 2, "thu": 3, "thur": 3, "thurs": 3, "fri": 4, "sat": 5, "satur": 5, "sun": 6}

# Words that put a sentence about pool tables (not swimming pools, not bowling).
POOL_CONTEXT = re.compile(r"\b(pool tables?|billiards?|billard|8[- ]ball|9[- ]ball|10[- ]ball|eight[- ]ball|nine[- ]ball|cue|snooker|pool hall|pool league|play pool|shoot pool|free pool|pool night|pool tournament|pool (?:is|rates?|prices?|time)|table (?:time|rates?|rental)|apa|vnea|bca|usapl)\b", re.I)
SWIMMING = re.compile(r"\b(swimming|swim|pool ?side|pool party|pool deck|hot tub|lifeguard|aquatic|splash)\b", re.I)
SALES = re.compile(r"\b(for sale|we sell|sales|installation|install|moving|movers|refelt|recover|re-?cloth|repair|delivery|showroom|financing|warranty|buy (?:a|your)|shop now|add to cart|price list|msrp)\b", re.I)
OTHER_GAMES = re.compile(r"\b(bowling|bowl|lanes?|darts?|golf|cornhole|volleyball|softball|trivia|bingo|poker|karaoke|keno)\b", re.I)

BRANDS = {
    "Diamond": r"diamond(?:\s+(?:pro[- ]am|smart\s*table|professional|bar\s*box))?",
    "Valley": r"valley(?:\s+(?:panther|zd-?\d+|cougar|dynamo|tiger))?",
    "Brunswick": r"brunswick(?:\s+gold\s*crown)?|gold\s*crown(?:\s+[ivx]+)?",
    "Rasson": r"rasson",
    "Predator": r"predator(?:\s+apex)?",
    "Olhausen": r"olhausen",
    "Gandy": r"gandy",
    "Dynamic": r"dynamic(?:\s+(?:billard|premium|competition))",
    "CL Bailey": r"c\.?\s?l\.?\s+bailey",
    "Connelly": r"connelly",
    "Golden West": r"golden\s+west",
    "Presidential": r"presidential",
    "Imperial": r"imperial",
    "Kasson": r"kasson",
    "Great American": r"great\s+american",
    "Legacy": r"legacy",
    "AMF": r"amf",
    "Plank & Hide": r"plank\s*(?:&|and)\s*hide",
}
_BRAND_ALT = "|".join(f"(?P<b{i}>{rx})" for i, rx in enumerate(BRANDS.values()))
_BRAND_NAMES = list(BRANDS)
# A brand counts only right next to the word table(s), or as "tables by/from <brand>".
BRAND_BEFORE = re.compile(r"\b(?:%s)\b(?:\s+[\w'’\-]+){0,3}?\s+(?:pool\s+|billiards?\s+|bar[- ]?box\s+)?tables?\b" % _BRAND_ALT, re.I)
BRAND_AFTER = re.compile(r"\btables?\s+(?:by|from|made by|built by)\s+(?:%s)\b" % _BRAND_ALT, re.I)
BRAND_STANDALONE = re.compile(r"\b(gold\s*crown|diamond\s+pro[- ]am|diamond\s+smart\s*table|valley\s+panther|rasson\s+ox|predator\s+apex)\b", re.I)
NON_TABLE_BRAND_USE = re.compile(r"\b(diamond|valley)\s+(club|lounge|bar|room|casino|mall|ballroom|room|hotel|inn|church|road|rd|st|street|ave|drive|view|bank)\b", re.I)

COUNT = re.compile(
    r"(?P<q>\b(?:over|more than|up to|nearly|almost|about|around|approximately|roughly|at least)\s+)?\b(?P<n>%s)\s*(?P<plus>\+)?\s+"
    r"(?P<mods>(?:[\w'’\-]+\s+){0,3}?)(?P<noun>pool\s+tables?|billiards?\s+tables?|tables?\s+of\s+pool|coin[- ]op(?:erated)?\s+(?:pool\s+)?tables?)\b" % NUM,
    re.I,
)
COUNT_LABEL = re.compile(r"\b(?:number of|#\s*of|total)?\s*(?:pool|billiards?)\s+tables?\s*[:\-–]\s*(?P<n>\d{1,2})\b", re.I)
SIZE_IN_MODS = re.compile(r"\b(7|8|9|10|12)\s*(?:-|\s)?(?:ft\.?|foot|feet|'|’|′)|\b(seven|eight|nine)[- ]f(?:oo)?t\b|\bbar[- ]?(?:size|box)\b|\bsnooker\b|\bcarom\b", re.I)
SIZE_COUNT = re.compile(
    r"\b(?P<n>%s)\s+(?:[\w\-]+\s+){0,2}?(?P<size>(?:7|8|9|10|12)\s*(?:-|\s)?(?:ft\.?|foot|feet|'|’|′)|(?:seven|eight|nine)[- ]f(?:oo)?t)\s*"
    r"(?:[\w\-]+\s+){0,2}?(?:pool\s+|billiards?\s+)?tables?\b" % NUM,
    re.I,
)
SIZE_ONLY = re.compile(
    r"\b(?P<size>(?:7|8|9|10|12)\s*(?:-|\s)?(?:ft\.?|foot|feet|'|’|′)|(?:seven|eight|nine)[- ]f(?:oo)?t)\s*(?:[\w\-]+\s+){0,2}?(?:pool\s+|billiards?\s+)?tables?\b",
    re.I,
)
SNOOKER = re.compile(r"\b(?:(?P<n>%s)\s+)?(?:full[- ]size\s+)?snooker\s+tables?\b" % NUM, re.I)
BARBOX = re.compile(r"\bbar[- ]?(?:size|box)\s+(?:pool\s+)?tables?\b", re.I)

HOURLY = re.compile(r"\$\s?(?P<rate>\d{1,3}(?:\.\d{2})?)\s*(?:/|per|an|a|each)\s*(?:hour|hr)\b|\b(?:by the hour|hourly (?:rate|table|pool)|per hour)\b", re.I)
PER_GAME = re.compile(r"(?:\$\s?(?P<rate>\d{1,2}(?:\.\d{2})?)|(?P<cents>\d{2})\s?(?:¢|cents))\s*(?:/|per|a)\s*(?:game|rack)\b", re.I)
COIN_OP = re.compile(r"\bcoin[- ]?op(?:erated)?\b|\bquarters?\s+(?:a|per)\s+(?:game|rack)\b", re.I)
NOT_POOL_COIN = re.compile(r"\b(foosball|air\s*hockey|arcade|jukebox|video\s+games?|darts?|pinball|skee)\b", re.I)
PER_PERSON = re.compile(r"\b(per\s+(?:person|player|guest|head)|each|additional\s+(?:guests?|players?|people)|extra\s+(?:guests?|players?))\b|\+\s*\$", re.I)
FREE_POOL = re.compile(r"\bfree\s+(?:pool|billiards)\b", re.I)

LEAGUE_ORGS = {
    "APA": r"\bapa\b|american\s+poolplayers?\s+association",
    "VNEA": r"\bvnea\b|valley\s+national\s+(?:8|eight)[- ]ball",
    "BCA": r"\bbca\b|billiard\s+congress",
    "TAP": r"\btap\s+(?:pool\s+)?league",
    "USAPL": r"\busapl\b|usa\s+pool\s+league",
    "ACS": r"\bacs\b(?=.*\bleague)",
}
GENERIC_LEAGUE = re.compile(r"\b(?:(?:8|9|10|eight|nine)[- ]ball|pool|billiards?)\s+leagues?\b|\bleague\s+(?:night|play)\b|\bleagues?\b", re.I)

AGE = [
    ("21-plus", re.compile(r"\b21\s*(?:\+|and\s+(?:over|up|older)|or\s+(?:over|older))|\bmust\s+be\s+21\b|\b21\s+years\s+(?:of\s+age|old)\b|\bno\s+one\s+under\s+21\b", re.I), "stated"),
    ("18-plus", re.compile(r"\b18\s*(?:\+|and\s+(?:over|up|older)|or\s+(?:over|older))|\bmust\s+be\s+18\b|\b18\s+years\s+(?:of\s+age|old)\b", re.I), "stated"),
    ("all-ages", re.compile(r"\ball\s+ages\b|\bminors\s+(?:are\s+)?welcome\b|\bkids\s+(?:are\s+)?welcome\b|\bchildren\s+(?:are\s+)?welcome\b", re.I), "stated"),
    ("all-ages", re.compile(r"\bfamily[- ]friendly\b|\bkids'?\s+menu\b|\bchildren'?s\s+menu\b", re.I), "implied"),
]
AGE_NOISE = re.compile(r"\b(events?|shows?|concerts?|live|tour|presents?|doors|dj|band|night only|tournament|party|tickets?|id required for alcohol|to drink|to purchase alcohol|alcohol)\b|\bw/", re.I)
# Pages listing shows: an "All ages" or "21+" there is about one show, not the venue.
EVENT_PAGE = re.compile(r"event|calendar|show|concert|ticket|music", re.I)

FOOD_YES = re.compile(r"\b(full\s+menu|food\s+menu|our\s+menu|kitchen\s+(?:is\s+)?open|kitchen\s+hours|appetizers|burgers?|pizzas?|wings|sandwiches|tacos|entr[eé]es|grill\s+menu|lunch\s+menu|dinner\s+menu|bar\s+food|pub\s+food|food\s+(?:and|&)\s+drinks?|serving\s+food)\b", re.I)
FOOD_IMPLIED = re.compile(r"\b(snacks?|frozen\s+pizza|popcorn|chips)\b", re.I)
FOOD_NO = re.compile(r"\b(no\s+food|no\s+kitchen|we\s+do\s+not\s+serve\s+food|don'?t\s+serve\s+food|bring\s+your\s+own\s+food)\b", re.I)
BAR_YES = re.compile(r"\bfull[- ](?:service\s+)?bar\b|\bfull\s+liquor\b", re.I)
BAR_IMPLIED = re.compile(r"\b(cocktails?|mixed\s+drinks|liquor|spirits|whiske?y|vodka|tequila|margaritas?|martinis?|bloody\s+marys?)\b", re.I)
NOT_DRINKS = re.compile(r"\b(cocktail\s+sauce|shrimp\s+cocktail|fruit\s+cocktail|cocktail\s+(?:napkins?|tables?|attire|dress))\b", re.I)
BAR_NO = re.compile(r"\bbeer\s+(?:and|&)\s+wine\s+only\b|\bno\s+(?:hard\s+)?liquor\b|\balcohol[- ]free\b|\bno\s+alcohol\b|\bdry\s+venue\b", re.I)
CLOSED = re.compile(r"\b(permanently\s+closed|closed\s+permanently|closed\s+for\s+good|(?:have|has)\s+closed\s+(?:our|its|the)\s+doors|(?:we\s+are|we're)\s+closing\s+(?:our\s+doors|permanently)|final\s+day\s+of\s+business|out\s+of\s+business)\b", re.I)
TEMP_CLOSED = re.compile(r"\btemporarily\s+closed\b|\bclosed\s+(?:until\s+further\s+notice|for\s+renovations?)\b", re.I)


def number(s: str) -> int | None:
    s = s.lower().strip()
    if s.isdigit():
        return int(s)
    if s in NUMBER_WORDS:
        return NUMBER_WORDS[s]
    m = re.match(r"(twenty|thirty|forty)[- ](\w+)", s)
    if m and m.group(2) in NUMBER_WORDS:
        return NUMBER_WORDS[m.group(1)] + NUMBER_WORDS[m.group(2)]
    return None


def size_key(s: str) -> str:
    s = s.lower()
    for word, n in (("seven", "7"), ("eight", "8"), ("nine", "9")):
        s = s.replace(word, n)
    m = re.match(r"(\d+)", s)
    return f"{m.group(1)}ft" if m else ""


def weekdays(s: str) -> list[str]:
    out = []
    for m in _DAY_RX.finditer(s):
        stem = m.group(1).lower()
        # "Sun" and "Sat" alone are too often other words; require the full day name for them.
        full = m.group(0).lower().rstrip(".")
        if stem in ("sun", "sat", "mon", "wed", "fri") and not full.startswith(("sunday", "saturday", "monday", "wednesday", "friday")):
            continue
        day = WEEKDAYS[_DAY_MAP.get(stem, _DAY_MAP.get(stem[:4], _DAY_MAP.get(stem[:3], 0)))]
        if day not in out:
            out.append(day)
    return out


def _fact(field, key, value, sentence, m_start, m_end, url, checked_at, confidence, flags=None):
    return {
        "field": field,
        "key": key,
        "value": value,
        "source_url": url,
        "quote_snippet": snippet(sentence, m_start, m_end),
        "checked_at": checked_at,
        "confidence": confidence,
        "flags": list(flags or []),
    }


def extract(lines: list[str], url: str, checked_at: str, *, pool_venue: bool = False, own_site: bool = True) -> list[dict]:
    """Facts stated in one page's text lines.

    `pool_venue` relaxes the pool-context check for a pool hall's own site, where
    "18 tables" can only mean pool tables. `own_site` is false for league
    and other third-party pages: then only league nights and closures are read.
    """
    facts: list[dict] = []
    for s in sentences(lines):
        if len(s) < 4:
            continue
        pool = bool(POOL_CONTEXT.search(s)) and not SWIMMING.search(s)
        sales = bool(SALES.search(s))
        if own_site and not sales:
            facts += _tables(s, url, checked_at, pool or pool_venue)
            facts += _pricing(s, url, checked_at, pool or pool_venue)
            facts += _amenities(s, url, checked_at)
        facts += _leagues(s, url, checked_at, pool or pool_venue)
        facts += _status(s, url, checked_at)
    return facts


def _tables(s, url, at, pool):
    out = []
    for m in COUNT.finditer(s):
        if "pool" not in m.group("noun").lower() and "billiard" not in m.group("noun").lower() and not pool:
            continue
        n = number(m.group("n"))
        if not n or n > 80:
            continue
        mods = m.group("mods") or ""
        if SIZE_IN_MODS.search(mods):
            continue  # "two 9-foot pool tables" is a size count, handled below.
        vague = bool(m.group("q") or m.group("plus"))
        out.append(_fact("table_count", "table_count", n, s, m.start(), m.end(), url, at, "implied" if vague else "stated", ["approximate"] if vague else []))
    for m in COUNT_LABEL.finditer(s):
        out.append(_fact("table_count", "table_count", int(m.group("n")), s, m.start(), m.end(), url, at, "stated"))
    if not (pool or re.search(r"\btables?\b", s, re.I)):
        return out
    seen = set()
    for m in SIZE_COUNT.finditer(s):
        if not pool:
            continue
        n, size = number(m.group("n")), size_key(m.group("size"))
        if size in ("7ft", "8ft", "9ft", "10ft", "12ft") and n and n <= 60:
            seen.add(size)
            out.append(_fact("table_sizes", "table_sizes:" + size, {"size": size, "count": n}, s, m.start(), m.end(), url, at, "stated"))
    for m in SIZE_ONLY.finditer(s):
        size = size_key(m.group("size"))
        if size in seen or size not in ("7ft", "8ft", "9ft", "10ft", "12ft") or not pool:
            continue
        seen.add(size)
        out.append(_fact("table_sizes", "table_sizes:" + size, {"size": size, "count": None}, s, m.start(), m.end(), url, at, "stated"))
    for m in SNOOKER.finditer(s):
        n = number(m.group("n")) if m.group("n") else None
        out.append(_fact("table_sizes", "table_sizes:snooker", {"size": "snooker", "count": n}, s, m.start(), m.end(), url, at, "stated"))
    for m in BARBOX.finditer(s):
        if "7ft" not in seen and pool:
            out.append(_fact("table_sizes", "table_sizes:7ft", {"size": "7ft", "count": None}, s, m.start(), m.end(), url, at, "implied", ["bar-size means 7 ft on most tables"]))
    if NON_TABLE_BRAND_USE.search(s):
        s_brands = NON_TABLE_BRAND_USE.sub(" ", s)
    else:
        s_brands = s
    found = {}
    for rx in (BRAND_BEFORE, BRAND_AFTER):
        for m in rx.finditer(s_brands):
            if not pool:
                continue
            idx = next(int(k[1:]) for k, v in m.groupdict().items() if v)
            found.setdefault(_BRAND_NAMES[idx], (m.start(), m.end()))
    for m in BRAND_STANDALONE.finditer(s_brands):
        name = m.group(1).lower()
        brand = "Brunswick" if "crown" in name else name.split()[0].title()
        found.setdefault(brand, (m.start(), m.end()))
    for brand, (a, b) in found.items():
        out.append(_fact("table_brands", "table_brands:" + brand.lower().replace(" ", "-"), brand, s, a, b, url, at, "stated"))
    return out


def _pricing(s, url, at, pool):
    out = []
    other = bool(OTHER_GAMES.search(s))
    if pool and not other:
        for m in HOURLY.finditer(s):
            rate = float(m.group("rate")) if m.group("rate") else None
            if rate is not None and not (1 <= rate <= 60):
                continue
            flags = []
            if rate is not None and PER_PERSON.search(s[max(0, m.start() - 40): m.end() + 25]):
                rate, flags = None, ["rate is per person or an add-on, not per table"]
            val = {"model": "hourly", "rate": rate}
            out.append(_fact("pricing_model", "pricing_model:hourly", val, s, m.start(), m.end(), url, at, "implied" if flags else "stated", flags))
            break
        for m in PER_GAME.finditer(s):
            rate = float(m.group("rate")) if m.group("rate") else (int(m.group("cents")) / 100 if m.group("cents") else None)
            if rate is None or not (0.25 <= rate <= 10):
                continue
            out.append(_fact("pricing_model", "pricing_model:per-game", {"model": "per-game", "rate": rate}, s, m.start(), m.end(), url, at, "stated"))
            break
        for m in COIN_OP.finditer(s):
            if NOT_POOL_COIN.search(s[m.start(): m.end() + 40]) and not re.search(r"pool|billiard", s[m.start(): m.end() + 40], re.I):
                continue  # "coin-operated foosball table"
            out.append(_fact("pricing_model", "pricing_model:coin-op", {"model": "coin-op"}, s, m.start(), m.end(), url, at, "stated"))
            break
    for m in FREE_POOL.finditer(s):
        days = weekdays(s)
        flags = [] if days else ["no weekday given"]
        if re.search(r"\b(every\s*day|daily|all\s+day\s+every\s+day|always)\b", s, re.I):
            days, flags = WEEKDAYS[:], []
        out.append(_fact("pricing_model", "pricing_model:free-pool", {"model": "free-pool", "days": days}, s, m.start(), m.end(), url, at, "stated", flags))
        break
    return out


def _leagues(s, url, at, pool):
    out = []
    orgs = []
    spans = {}
    for name, rx in LEAGUE_ORGS.items():
        m = re.search(rx, s, re.I)
        if m:
            orgs.append(name)
            spans[name] = (m.start(), m.end())
    generic = GENERIC_LEAGUE.search(s)
    if not orgs and not generic:
        return out
    # A bare "pool" counts here ("pool and dart leagues"), unless it is a swimming pool.
    pool = pool or (bool(re.search(r"\bpool\b", s, re.I)) and not SWIMMING.search(s))
    darts_only = re.search(r"\bdarts?\b|\bcornhole\b|\bbowling\b|\bvolleyball\b|\bsoftball\b|\btrivia\b|\bbags\b", s, re.I) and not pool
    if darts_only:
        return out
    if not orgs:
        if not pool:
            return out
        orgs = ["Pool league"]
        spans["Pool league"] = (generic.start(), generic.end())
    days = weekdays(s)
    if not days:
        for org in orgs:
            a, b = spans[org]
            out.append(_fact("league_nights", f"league_nights:{org.lower().replace(' ', '-')}:", {"league": org, "weekday": None}, s, a, b, url, at, "implied", ["no weekday given"]))
        return out
    ambiguous = len(orgs) > 1 and len(days) > 1
    for org in orgs:
        a, b = spans[org]
        for day in days:
            flags = ["several leagues and days in one sentence"] if ambiguous else []
            if org == "Pool league":
                flags.append("league name not given")
            out.append(_fact("league_nights", f"league_nights:{org.lower().replace(' ', '-')}:{day}", {"league": org, "weekday": day}, s, a, b, url, at, "implied" if ambiguous else "stated", flags))
    return out


def _amenities(s, url, at):
    out = []
    if not AGE_NOISE.search(s) and not EVENT_PAGE.search(urllib.parse.urlsplit(url).path):
        for value, rx, conf in AGE:
            m = rx.search(s)
            if m:
                out.append(_fact("age_policy", "age_policy", value, s, m.start(), m.end(), url, at, conf))
                break
    for rx, value, conf in ((FOOD_NO, False, "stated"), (FOOD_YES, True, "stated"), (FOOD_IMPLIED, True, "implied")):
        m = rx.search(s)
        if m:
            out.append(_fact("food", "food", value, s, m.start(), m.end(), url, at, conf))
            break
    drinks = NOT_DRINKS.sub(lambda m: " " * len(m.group(0)), s)
    for rx, value, conf in ((BAR_NO, False, "stated"), (BAR_YES, True, "stated"), (BAR_IMPLIED, True, "implied")):
        m = rx.search(drinks)
        if m:
            out.append(_fact("full_bar", "full_bar", value, s, m.start(), m.end(), url, at, conf))
            break
    return out


def _status(s, url, at):
    m = CLOSED.search(s)
    if m:
        return [_fact("venue_status", "venue_status", "permanently-closed", s, m.start(), m.end(), url, at, "stated")]
    m = TEMP_CLOSED.search(s)
    if m:
        return [_fact("venue_status", "venue_status", "temporarily-closed", s, m.start(), m.end(), url, at, "implied", ["check whether it has reopened"])]
    return []


# ------------------------------------------------------------------ merging

CONF_RANK = {"stated": 2, "implied": 1}


def merge(candidates: list[dict], source_rank) -> list[dict]:
    """One fact per key: stated beats implied, then the better source, then the first page read.

    A stated value that disagrees with the chosen one is kept under `conflicts`
    and flagged, so the reviewer sees both. Food, bar and age facts are
    one-per-venue, and yes on any page beats an implied yes elsewhere.
    """
    by_key: dict[str, list[dict]] = {}
    for c in candidates:
        by_key.setdefault(c["key"], []).append(c)
    out = []
    for key, group in by_key.items():
        group.sort(key=lambda c: (-CONF_RANK[c["confidence"]], source_rank(c["source_url"])))
        best = dict(group[0])
        best["flags"] = list(best["flags"])
        conflicts = []
        for c in group[1:]:
            if c["confidence"] == "stated" and _norm(c["value"]) != _norm(best["value"]):
                if not any(_norm(x["value"]) == _norm(c["value"]) for x in conflicts):
                    conflicts.append({"value": c["value"], "source_url": c["source_url"], "quote_snippet": c["quote_snippet"]})
        if conflicts:
            best["conflicts"] = conflicts
            best["flags"].append("sources disagree")
        best["seen_on"] = sorted({c["source_url"] for c in group if _norm(c["value"]) == _norm(best["value"])})
        out.append(best)

    # A league with a weekday makes the same league's weekday-less mention redundant.
    with_day = {f["value"]["league"] for f in out if f["field"] == "league_nights" and f["value"].get("weekday")}
    out = [f for f in out if not (f["field"] == "league_nights" and not f["value"].get("weekday") and f["value"]["league"] in with_day)]
    if any(f["field"] == "league_nights" and f["value"]["league"] != "Pool league" for f in out):
        named_days = {f["value"].get("weekday") for f in out if f["field"] == "league_nights" and f["value"]["league"] != "Pool league"}
        out = [f for f in out if not (f["field"] == "league_nights" and f["value"]["league"] == "Pool league" and f["value"].get("weekday") in named_days | {None})]

    sizes = {f["value"]["size"]: f["value"]["count"] for f in out if f["field"] == "table_sizes" and f["confidence"] == "stated"}
    total = next((f for f in out if f["field"] == "table_count"), None)
    known = [c for c in sizes.values() if c]
    if total and known and sum(known) > total["value"]:
        total["flags"].append(f"size counts add up to {sum(known)}")
    order = {f: i for i, f in enumerate(FIELDS)}
    out.sort(key=lambda f: (order.get(f["field"], 99), f["key"]))
    return out


def _norm(v):
    if isinstance(v, dict):
        return tuple(sorted((k, tuple(x) if isinstance(x, list) else x) for k, x in v.items()))
    return v
