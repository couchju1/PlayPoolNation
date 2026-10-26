import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from ppn_enrich.extract import extract, merge, weekdays  # noqa: E402
from ppn_enrich.text import snippet  # noqa: E402

URL = "https://example.com/"
AT = "2026-10-25"


def facts(*lines, **kw):
    return merge(extract(list(lines), URL, AT, **kw), lambda u: 0)


def by_field(fs, field):
    return [f for f in fs if f["field"] == field]


class TableCounts(unittest.TestCase):
    def test_stated_count(self):
        f = by_field(facts("We have 18 pool tables and a full bar."), "table_count")
        self.assertEqual([(x["value"], x["confidence"]) for x in f], [(18, "stated")])

    def test_word_count(self):
        f = by_field(facts("Come shoot on our twelve Diamond pool tables."), "table_count")
        self.assertEqual(f[0]["value"], 12)

    def test_vague_is_not_a_count(self):
        self.assertEqual(by_field(facts("We have lots of pool tables!"), "table_count"), [])
        self.assertEqual(by_field(facts("Plenty of tables to go around."), "table_count"), [])

    def test_approximate_is_implied(self):
        f = by_field(facts("Over 20 pool tables in one room."), "table_count")
        self.assertEqual((f[0]["value"], f[0]["confidence"]), (20, "implied"))

    def test_size_count_is_not_total(self):
        fs = facts("We have two 9-foot pool tables.")
        self.assertEqual(by_field(fs, "table_count"), [])
        self.assertEqual(by_field(fs, "table_sizes")[0]["value"], {"size": "9ft", "count": 2})

    def test_size_without_count(self):
        fs = facts("All of our 7 ft pool tables are Valley tables.")
        self.assertEqual(by_field(fs, "table_sizes")[0]["value"], {"size": "7ft", "count": None})
        self.assertEqual(by_field(fs, "table_brands")[0]["value"], "Valley")

    def test_dining_tables_ignored(self):
        self.assertEqual(facts("We have 20 tables in our dining room for families."), [])

    def test_pool_hall_relaxes_context(self):
        f = by_field(facts("16 tables of pool, open late.", pool_venue=True), "table_count")
        self.assertEqual(f[0]["value"], 16)

    def test_sales_pages_ignored(self):
        self.assertEqual(by_field(facts("We sell 8 ft Brunswick pool tables with free delivery."), "table_brands"), [])

    def test_swimming_pool_ignored(self):
        self.assertEqual(facts("Book a pool party for 20 people at the swimming pool, $15 per hour."), [])


class Brands(unittest.TestCase):
    def test_gold_crown(self):
        self.assertEqual(by_field(facts("Play on Brunswick Gold Crown pool tables."), "table_brands")[0]["value"], "Brunswick")

    def test_diamond_club_not_brand(self):
        self.assertEqual(by_field(facts("The Diamond Club has 4 pool tables."), "table_brands"), [])


class Pricing(unittest.TestCase):
    def test_hourly(self):
        f = by_field(facts("Pool is $12 per hour per table."), "pricing_model")
        self.assertEqual(f[0]["value"], {"model": "hourly", "rate": 12.0})

    def test_free_pool_days(self):
        f = by_field(facts("Free pool on Sundays and Mondays!"), "pricing_model")
        self.assertEqual(f[0]["value"], {"model": "free-pool", "days": ["sunday", "monday"]})
        self.assertEqual(f[0]["confidence"], "stated")

    def test_bowling_price_ignored(self):
        self.assertEqual(by_field(facts("Bowling is $5 per game, pool tables too."), "pricing_model"), [])

    def test_coin_op(self):
        f = by_field(facts("Coin-op pool tables, 75 cents a game."), "pricing_model")
        self.assertEqual({x["value"]["model"] for x in f}, {"coin-op", "per-game"})


class Leagues(unittest.TestCase):
    def test_apa_tuesday(self):
        f = by_field(facts("APA league plays here every Tuesday at 7pm."), "league_nights")
        self.assertEqual([x["value"] for x in f], [{"league": "APA", "weekday": "tuesday"}])

    def test_dart_league_ignored(self):
        self.assertEqual(by_field(facts("Dart league on Wednesdays."), "league_nights"), [])

    def test_league_without_day_is_implied(self):
        f = by_field(facts("We host VNEA pool leagues."), "league_nights")
        self.assertEqual(f[0]["confidence"], "implied")

    def test_weekday_abbrev(self):
        self.assertEqual(weekdays("Tues & Thurs nights"), ["tuesday", "thursday"])
        self.assertEqual(weekdays("Sun is shining"), [])


class Amenities(unittest.TestCase):
    def test_age(self):
        self.assertEqual(by_field(facts("Must be 21 or older to enter."), "age_policy")[0]["value"], "21-plus")

    def test_full_bar(self):
        f = by_field(facts("Full bar and great burgers."), "full_bar")
        self.assertEqual((f[0]["value"], f[0]["confidence"]), (True, "stated"))
        self.assertTrue(by_field(facts("Full bar and great burgers."), "food")[0]["value"])

    def test_closed(self):
        self.assertEqual(by_field(facts("We are permanently closed. Thank you!"), "venue_status")[0]["value"], "permanently-closed")


class FalsePositives(unittest.TestCase):
    def test_cocktail_sauce_is_not_a_bar(self):
        self.assertEqual(by_field(facts("Crispy shrimp served with Texas toast and cocktail sauce."), "full_bar"), [])

    def test_show_age_is_not_venue_age(self):
        self.assertEqual(by_field(facts("BROTHER ALI Live at BIGS w/DJ Abilities - All Ages"), "age_policy"), [])
        self.assertEqual(by_field(merge(extract(["Must be 21 or older."], "https://x.com/current-events", AT), lambda u: 0), "age_policy"), [])

    def test_age_question_and_part_time(self):
        q = by_field(facts("Is the venue 21+?"), "age_policy")[0]
        self.assertEqual((q["confidence"], q["flags"]), ("implied", ["from a question (age gate or FAQ)"]))
        t = by_field(facts("Only 21+ Thursday - Saturday after 8PM"), "age_policy")[0]
        self.assertEqual((t["value"], t["confidence"]), ("21-plus", "implied"))
        self.assertEqual(by_field(facts("We are a 21+ venue, 24/7."), "age_policy")[0]["confidence"], "stated")

    def test_coin_op_foosball(self):
        self.assertEqual(by_field(facts("We have 25 pool tables and one coin-operated foosball table near the bar!"), "pricing_model"), [])

    def test_per_person_surcharge(self):
        f = by_field(facts("Pool table rental: additional guests +$5/hour each."), "pricing_model")[0]
        self.assertEqual((f["value"]["rate"], f["confidence"]), (None, "implied"))

    def test_pool_and_dart_leagues(self):
        f = by_field(facts("Downtown bar, pool and dart leagues, wine and spirits."), "league_nights")
        self.assertEqual((f[0]["value"], f[0]["confidence"]), ({"league": "Pool league", "weekday": None}, "implied"))

    def test_in_house_tournament_is_not_a_league(self):
        self.assertEqual(by_field(facts("Join us every Monday night for an in house pool tournament."), "league_nights"), [])


class Snippets(unittest.TestCase):
    def test_under_20_words(self):
        s = " ".join(f"word{i}" for i in range(60)) + " we have 18 pool tables " + " ".join(f"w{i}" for i in range(60))
        f = facts(s)[0]
        words = [w for w in f["quote_snippet"].split() if w != "…"]
        self.assertLess(len(words), 20)
        self.assertIn("18 pool tables", f["quote_snippet"])

    def test_short_kept(self):
        self.assertEqual(snippet("We have 18 pool tables.", 8, 22), "We have 18 pool tables.")


class Merge(unittest.TestCase):
    def test_conflict_flagged(self):
        fs = merge(extract(["We have 18 pool tables."], "https://a/", AT) + extract(["We have 16 pool tables."], "https://b/", AT), lambda u: 0 if "a/" in u else 1)
        f = by_field(fs, "table_count")[0]
        self.assertEqual(f["value"], 18)
        self.assertIn("sources disagree", f["flags"])


if __name__ == "__main__":
    unittest.main()
