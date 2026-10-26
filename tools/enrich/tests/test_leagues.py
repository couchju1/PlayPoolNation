import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from ppn_enrich.enrich import default_size  # noqa: E402
from ppn_enrich.leagues import host_for, league_facts, league_weekday, standings, team_hosts  # noqa: E402

PDF = """                                                    Team standings
  SIOUX FALLS OPEN A-2
  Rank               Team Name                   Wins
      1 BIGS IV                                   60    25    15.0  10.0   100   68   838    838
      2 NICKEL SPOT I                             60    25    15.0  10.0   100   65   831    831
      3 DOUBLE D SALOON TRAVELERS                 60    25    15.0  10.0   100   54   792    764
      4 NICKEL SPOT CRAZY TRAIN                   60    25    15.0  10.0   100   57   770    762
      8 LUCKYS SOUTH PEN15                        44    25    11.0  14.0   100   37   750    638
      9 CHASERS CUE SLINGERS                      40    25    10.0  15.0   100   42   735    703
     10 CHASERS 2                                 24    25     6.0  19.0   100   35   794    642
                                                         Team players
    BIGS IV
       3 RANDY BLOCK                    9.60        12       9     3       96     15.08     6
"""

LISTINGS = [
    {"slug": "bigs-bar", "name": "Bigs Bar"},
    {"slug": "nickel-spot", "name": "Nickel Spot"},
    {"slug": "luckys", "name": "Lucky’s"},
    {"slug": "luckys-on-louise", "name": "Lucky’s on Louise"},
]


def league(teams=None, weekday="monday", latest="2026-10-19"):
    return {"name": "Sioux Falls A-2", "url": "https://musicservice.com/pool-league/sioux-falls-a-2/", "stats_url": "https://x/s.pdf", "weekday": weekday, "latest": latest, "teams": teams if teams is not None else standings(PDF)}


class MusicService(unittest.TestCase):
    def test_standings(self):
        teams = [t for t, _ in standings(PDF)]
        self.assertEqual(teams[:3], ["BIGS IV", "NICKEL SPOT I", "DOUBLE D SALOON TRAVELERS"])
        self.assertNotIn("RANDY BLOCK", teams)

    def test_weekday_from_result_dates(self):
        self.assertEqual(league_weekday(["10-19-2026", "10-12-2026", "10-05-2026"]), ("monday", "2026-10-19"))

    def test_host_matching(self):
        self.assertEqual(host_for("BIGS MAKE IT WORK", LISTINGS)[0]["slug"], "bigs-bar")
        self.assertEqual(host_for("NICKEL SPOT CRAZY TRAIN", LISTINGS), (LISTINGS[1], True))
        listing, clear = host_for("LUCKYS SOUTH PEN15", LISTINGS)
        self.assertEqual((listing["slug"], clear), ("luckys", False))
        self.assertEqual(host_for("LUCKYS ON LOUISE 8 BALLERS", LISTINGS)[0]["slug"], "luckys-on-louise")
        self.assertIsNone(host_for("CROW BAR CHAIN STROKERS", LISTINGS)[0])

    def test_league_facts(self):
        facts = league_facts([league()], LISTINGS, "Music Service", "2026-10-25")
        bigs = facts["bigs-bar"][0]
        self.assertEqual(bigs["value"], {"league": "Sioux Falls A-2 (Music Service)", "weekday": "monday"})
        self.assertEqual(bigs["confidence"], "stated")
        self.assertEqual(len(facts["nickel-spot"]), 1)  # two teams, one league night
        self.assertEqual(facts["luckys"][0]["confidence"], "implied")

    def test_off_season_league_is_implied(self):
        facts = league_facts([league(latest="2026-07-01")], LISTINGS, "Music Service", "2026-10-25")
        self.assertEqual(facts["bigs-bar"][0]["confidence"], "implied")

    def test_team_hosts(self):
        hosts = {h["name"]: h for h in team_hosts([league()], LISTINGS)}
        self.assertEqual(sorted(hosts), ["Chasers"])  # one-team names are left out
        self.assertTrue(hosts["Chasers"]["guessed"])

    def test_team_hosts_merge_spellings(self):
        teams = [("LUCKYS SOUTH MIS-CUES", ""), ("LUCK SOUT RAK TO THE FUTUR", ""), ("LUCKY SOUTH POCKET ROCKET", ""), ("LUCKY STRIKE", ""),
                 ("RED EYE HAVOC", ""), ("RED EYE CUE FORCE", ""), ("RED ROOSTER A", ""), ("RED ROOSTER B", ""), ("18TH CUE THE CHAOS", ""), ("18TH AMENDMNT RACK N ROLL", ""),
                 ("THE DEN DAD BODS", ""), ("THE DEN HAPPY HOOKERS", ""), ("DOUBLE D SALOON I", ""), ("DOUBLE D SALOON RACK EM", ""), ("DOUBLE D RACKSTARS", "")]
        hosts = {h["name"]: h for h in team_hosts([league(teams=teams)], LISTINGS)}
        self.assertEqual(sorted(hosts), ["18th", "Double D", "Luckys South", "Red Eye", "Red Rooster", "The Den"])
        self.assertEqual(len(hosts["Luckys South"]["teams"]), 3)

    def test_short_name_flag(self):
        facts = league_facts([league(teams=[("NICKEL GREASY BASTARDS", "1 NICKEL GREASY BASTARDS 60")])], LISTINGS, "Music Service", "2026-10-25")
        self.assertIn("team 'NICKEL GREASY BASTARDS' matched on its first word only", facts["nickel-spot"][0]["flags"])

    def test_short_team_name_matches_listing(self):
        listing, clear = host_for("NICKEL GREASY BASTARDS", LISTINGS)
        self.assertEqual((listing["slug"], clear), ("nickel-spot", False))


class Hotels(unittest.TestCase):
    def test_hotels_flagged_bars_not(self):
        from ppn_enrich.discover import mark_hotel
        self.assertEqual(mark_hotel({"name": "Aloft Manhattan Downtown", "venue_type": "pool-halls"})["venue_type"], "other-venue")
        self.assertEqual(mark_hotel({"name": "Stonewall Inn", "venue_type": "bars-with-pool-tables"})["venue_type"], "bars-with-pool-tables")
        self.assertEqual(mark_hotel({"name": "Q4", "venue_type": "pool-halls"}, {"tourism": "hotel"})["venue_type"], "other-venue")


class DefaultSize(unittest.TestCase):
    def test_seven_foot_default_uses_the_count(self):
        count = {"field": "table_count", "value": 12, "confidence": "stated", "source_url": "https://bigsbar.com/", "quote_snippet": "12 pool tables"}
        f = default_size({"url": "https://playpoolnation.com/place/x/", "current": {}}, [count], "2026-10-25")[0]
        self.assertEqual((f["value"], f["confidence"], f["source_url"]), ({"size": "7ft", "count": 12}, "implied", "https://bigsbar.com/"))

    def test_no_default_when_size_known(self):
        self.assertEqual(default_size({"url": "u", "current": {"9' tables": True}}, [], "2026-10-25"), [])
        self.assertEqual(default_size({"url": "u"}, [{"field": "table_sizes", "value": {"size": "9ft", "count": 2}}], "2026-10-25"), [])

    def test_default_without_count(self):
        f = default_size({"url": "https://playpoolnation.com/place/x/"}, [], "2026-10-25")[0]
        self.assertEqual(f["value"], {"size": "7ft", "count": None})


if __name__ == "__main__":
    unittest.main()
