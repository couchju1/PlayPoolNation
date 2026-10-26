"""League operators whose public pages need their own reader.

Music Service (musicservice.com) runs the pool leagues in eastern South Dakota.
Its league index links one page per league. A league page lists the dates of
its weekly results (the weekday they fall on is the league night) and links the
latest weekly statistics PDF, whose "Team standings" table names every team.
Teams carry their host bar's name ("BIGS IV", "NICKEL SPOT CRAZY TRAIN"), so a
team name that starts with one of our venues' names puts a league night there,
and the bar names left over are venues we may be missing.
"""

from __future__ import annotations

import datetime as dt
import re
from collections import Counter

from .http import Fetcher
from .match import normalize_name
from .text import parse_html, plain_lines, snippet

# Words that end a venue's distinctive name ("Bigs Bar" is "BIGS" on a team sheet).
GENERIC = {"bar", "pub", "grill", "sports", "billiards", "billiard", "darts", "and", "the", "saloon", "tavern", "lounge", "club", "inc", "llc", "co", "cafe", "bar and grill"}
# A word right after the venue's name that suggests a different location of a chain.
OTHER_LOCATION = {"south", "north", "east", "west", "downtown", "dt", "on", "at", "ii", "2", "too", "express", "southside", "northside", "eastside", "westside"}
DATE = re.compile(r"\b(\d{2})-(\d{2})-(\d{4})\b")
STALE_DAYS = 60


def venue_key(name: str) -> list[str]:
    words = normalize_name_keep(name)
    while len(words) > 1 and words[-1] in GENERIC:
        words.pop()
    return words


def normalize_name_keep(name: str) -> list[str]:
    s = re.sub(r"['’`]", "", name.lower().replace("&", " and "))
    return [w for w in re.sub(r"[^a-z0-9]+", " ", s).split() if w not in ("the",)]


def standings(text: str) -> list[tuple[str, str]]:
    """(team name, the standings line) from a weekly statistics PDF's text."""
    out = []
    block = text.split("Team standings", 1)[-1].split("Team players", 1)[0]
    for line in block.splitlines():
        m = re.match(r"\s*\d{1,2}\s+(.+?)\s{2,}\d", line)
        if m and not re.search(r"team name|rank", m.group(1), re.I):
            out.append((" ".join(m.group(1).split()), " ".join(line.split())))
    return out


def league_weekday(lines: list[str]) -> tuple[str | None, str | None]:
    """The weekday most result dates fall on, and the latest date."""
    dates = []
    for line in lines:
        for m in DATE.finditer(line):
            try:
                dates.append(dt.date(int(m.group(3)), int(m.group(1)), int(m.group(2))))
            except ValueError:
                pass
    if not dates:
        return None, None
    day = Counter(d.weekday() for d in dates).most_common(1)[0][0]
    return ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"][day], max(dates).isoformat()


def music_service(fetcher: Fetcher, page: dict, city: dict) -> list[dict]:
    """Every league whose name contains the city's name: name, page, weekday, latest week, teams."""
    r = fetcher.get(page["url"], accept_pdf=False)
    if not r.ok:
        print(f"  {page['url']}: {r.error}")
        return []
    want = page.get("league_filter") or city["name"]
    seen, leagues = set(), []
    for url, anchor in parse_html(r.text, r.url)["links"]:
        if "/league/" not in url or want.lower() not in anchor.lower() or url in seen:
            continue
        seen.add(url)
        lp = fetcher.get(url, accept_pdf=False)
        if not lp.ok:
            print(f"  {url}: {lp.error}")
            continue
        doc = parse_html(lp.text, lp.url)
        weekday, latest = league_weekday(doc["lines"])
        stats = re.findall(r'href="([^"]+/weeklystatistics/[^"]+\.pdf)"', lp.text)
        teams = []
        if stats:
            pdf = fetcher.get(stats[0])
            if pdf.ok:
                teams = standings(pdf.text)
        print(f"    {anchor}: {weekday or 'night unknown'}, {len(teams)} teams")
        leagues.append({"name": anchor, "url": lp.url, "stats_url": stats[0] if stats else "", "weekday": weekday, "latest": latest, "teams": teams})
    return leagues


def host_for(team: str, listings: list[dict]) -> tuple[dict | None, bool]:
    """(listing whose name the team name starts with, whether that is unambiguous).

    Team sheets shorten names ("NICKEL GREASY BASTARDS" for Nickel Spot), so a team
    whose first word is the first word of exactly one listing's name also matches,
    as not clear-cut.
    """
    words = normalize_name_keep(team)
    best, best_len = None, 0
    for listing in listings:
        key = venue_key(listing["name"])
        if key and words[: len(key)] == key and len(key) > best_len:
            best, best_len = listing, len(key)
    if not best:
        if not words or len(words[0]) < 5:
            return None, False
        same_first = [l for l in listings if venue_key(l["name"])[:1] == words[:1]]
        return (same_first[0], False) if len(same_first) == 1 else (None, False)
    rest = words[best_len:]
    # "LUCKYS SOUTH ..." starts with "Lucky's" but may be another Lucky's.
    clear = not (rest and rest[0] in OTHER_LOCATION)
    return best, clear


def league_facts(leagues: list[dict], listings: list[dict], operator: str, checked_at: str) -> dict[str, list[dict]]:
    facts: dict[str, list[dict]] = {}
    today = dt.date.fromisoformat(checked_at)
    for lg in leagues:
        stale = bool(lg["latest"]) and (today - dt.date.fromisoformat(lg["latest"])).days > STALE_DAYS
        for team, line in lg["teams"]:
            listing, clear = host_for(team, listings)
            if not listing:
                continue
            flags = ["from the league's team names"]
            key = venue_key(listing["name"])
            if normalize_name_keep(team)[: len(key)] != key:
                flags.append(f"team '{team}' matched on its first word only")
            elif not clear:
                flags.append(f"team '{team}' may be another location")
            if stale:
                flags.append(f"no results since {lg['latest']} (off-season?)")
            if not lg["weekday"]:
                flags.append("no weekday given")
            name = f"{lg['name']} ({operator})"
            key = "league_nights:" + re.sub(r"[^a-z0-9]+", "-", name.lower()).strip("-") + ":" + (lg["weekday"] or "")
            fs = facts.setdefault(listing["slug"], [])
            if any(f["key"] == key for f in fs):
                continue
            quote = f"{line} · results {lg['latest'] or ''}"
            fs.append({
                "field": "league_nights",
                "key": key,
                "value": {"league": name, "weekday": lg["weekday"]},
                "source_url": lg["url"],
                "evidence_url": lg["stats_url"],
                "quote_snippet": snippet(quote, 0, len(team)),
                "checked_at": checked_at,
                "confidence": "stated" if clear and lg["weekday"] and not stale else "implied",
                "flags": flags,
            })
    return facts


def _tokens(team: str) -> list[str]:
    return re.sub(r"[^A-Za-z0-9'&]+", " ", team.replace("’", "'")).split()


def _key(tok: str) -> str:
    return re.sub(r"[^a-z0-9]", "", tok.lower().replace("&", "and"))


def _bare(toks: list[str]) -> list[str]:
    return toks[1:] if len(toks) > 1 and _key(toks[0]) == "the" else toks


def _compatible(a: list[str], b: list[str]) -> bool:
    """Same first word, give or take shortening ("LUCK SOUT" ~ "LUCKYS SOUTH"), ignoring a leading "THE"."""
    a, b = _bare(a), _bare(b)
    if not a or not b or min(len(_key(a[0])), len(_key(b[0]))) < 3:
        return False
    x, y = _key(a[0]), _key(b[0])
    return x.startswith(y) or y.startswith(x)


def team_hosts(leagues: list[dict], listings: list[dict]) -> list[dict]:
    """Bars named by teams that match none of our listings: the words their teams share.

    Teams of one bar start alike ("CROW BAR 8-BALLERS", "CROW BAR CHAIN STROKERS" →
    "Crow Bar"), give or take a shortened word ("LUCK SOUT …" with "LUCKYS SOUTH …").
    A name seen on one team only is left out: in practice it is the team's own name
    ("50 SHADES OF PINK"), not a bar's.
    """
    teams = []
    for lg in leagues:
        for team, _line in lg["teams"]:
            listing, clear = host_for(team, listings)
            if not listing or (not clear and venue_key(listing["name"]) == normalize_name_keep(team)[: len(venue_key(listing["name"]))]):
                # Unmatched, or "LUCKYS SOUTH" after a "Lucky's" we list: may be a bar we're missing.
                teams.append((team, lg))
    groups: list[list[tuple[str, dict]]] = []
    for team, lg in teams:
        for g in groups:
            if _compatible(_tokens(team), _tokens(g[0][0])):
                g.append((team, lg))
                break
        else:
            groups.append([(team, lg)])
    # Within a first-word group, teams that also share their second word are one bar
    # ("LUCKY SOUTH …", "LUCKYS SOUTH …"); a lone "LUCKY STRIKE" is a team, not a bar.
    split: list[list[tuple[str, dict]]] = []
    for g in groups:
        subs: list[list[tuple[str, dict]]] = []
        for team, lg in g:
            second = _bare(_tokens(team))[1:2]
            for sub in subs:
                other = _bare(_tokens(sub[0][0]))[1:2]
                if second and other and (_key(second[0]).startswith(_key(other[0])) or _key(other[0]).startswith(_key(second[0]))) and min(len(_key(second[0])), len(_key(other[0]))) >= 1:
                    sub.append((team, lg))
                    break
            else:
                subs.append([(team, lg)])
        multi = [sub for sub in subs if len({t for t, _ in sub}) >= 2]
        if multi and len(multi) == 1 and sum(len({t for t, _ in sub}) for sub in subs if sub not in multi) <= 1 and len(_bare(_tokens(multi[0][0][0]))) > 1:
            split.append(multi[0])  # One bar plus at most one stray team.
        elif len(multi) > 1:
            split.extend(multi)  # Several bars share a first word ("RED EYE", "RED ROOSTER").
        else:
            split.append(g)  # Teams agree on the first word only ("CHASERS …").
    groups = split
    out = []
    for members in groups:
        distinct = sorted({t for t, _ in members})
        if len(distinct) < 2:
            continue
        the = all(_key(_tokens(t)[0]) == "the" for t in distinct)
        toks = [_bare(_tokens(t)) for t in distinct]

        def same(i: int) -> bool:
            ks = [_key(t[i]) for t in toks]
            return all(k.startswith(min(ks, key=len)) for k in ks) and len(min(ks, key=len)) >= 1

        # The shared words, spelled as in the team whose spelling of them is longest.
        n = 1
        while all(len(t) > n for t in toks) and same(n):
            n += 1
        words = (["THE"] if the else []) + [max((t[i] for t in toks), key=len) for i in range(n)]
        name = " ".join(w.lower() if w[:1].isdigit() else w[:1].upper() + w[1:].lower() for w in words)
        out.append({
            "name": name,
            "teams": distinct,
            "leagues": sorted({lg["name"] for _, lg in members}),
            "league_urls": sorted({lg["url"] for _, lg in members}),
            "guessed": n == 1,
        })
    return out
