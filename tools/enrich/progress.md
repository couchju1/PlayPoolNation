# Venue enrichment progress

Updated 2026-10-25 23:29. Rebuilt by `python3 tools/enrich/enrich.py progress` and after every venue.

Cities are worked in the order of the latest keyword report; a city is finished before the next starts. A *hit* is a venue with at least one **stated** fact for that field (implied facts are counted separately and go to review flagged).

| City | Venues done | Facts (stated) | Venues with a stated fact | With a table fact | New venues found | Disk |
|---|--:|--:|--:|--:|--:|--:|
| sioux-falls (done) | 10/10 | 23 (12) | 7 (70%) | 2 (20%) | 0 | 36 KB |

## sioux-falls

Discovery: OpenStreetMap 0 pool-tagged places and 21 other bars; Google Places skipped (GOOGLE_PLACES_API_KEY not set); 1 league page(s); 0 matched our listings, 0 new.

Pages read 41, skipped by robots.txt or login 0, errors 0.

| Field | Venues with stated fact | Hit rate | Implied only |
|---|--:|--:|--:|
| Table count | 2 | 20% | 0 |
| Table sizes | 0 | 0% | 0 |
| Table brands | 0 | 0% | 0 |
| Pricing | 1 | 10% | 0 |
| League nights | 1 | 10% | 3 |
| Age policy | 0 | 0% | 0 |
| Food | 6 | 60% | 0 |
| Full bar | 1 | 10% | 7 |
| Closed notice | 0 | 0% | 0 |

| Venue | Status | Stated facts | Implied | Sources read |
|---|---|--:|--:|--:|
| Bigs Bar | done | 6 | 1 | 5 |
| JJ’s Billiards & Darts | done – no website | 0 | 0 | 0 |
| Rack City Billiards | done | 1 | 1 | 2 |
| Gibs Sports Bar | done | 1 | 0 | 2 |
| Lucky’s | done | 1 | 1 | 5 |
| Lucky’s on Louise | done | 1 | 1 | 5 |
| Nickel Spot | done | 1 | 2 | 9 |
| Thirsty Duck | done | 1 | 2 | 8 |
| Tommy Jack’s Pub | done | 0 | 2 | 3 |
| Upper Cut Bar & Grill | done | 0 | 1 | 2 |

Run log:

- 2026-10-25 23:10 resumed after pause
- 2026-10-25 23:11 resumed after pause
- 2026-10-25 23:12 resumed after pause
- 2026-10-25 23:15 enrichment finished: 10 venues
- 2026-10-25 23:16 redo: enriching every venue again
- 2026-10-25 23:16 run started
- 2026-10-25 23:20 enrichment finished: 10 venues
- 2026-10-25 23:21 run started
- 2026-10-25 23:21 re-check 2026-10 started
- 2026-10-25 23:25 re-check finished: 10 venues
- 2026-10-25 23:26 redo: enriching every venue again
- 2026-10-25 23:26 run started
- 2026-10-25 23:26 simulated crash after 3 venues (PPN_ENRICH_CRASH_AFTER)
- 2026-10-25 23:26 resumed: 3 venues already done, skipping them
- 2026-10-25 23:29 enrichment finished: 10 venues
