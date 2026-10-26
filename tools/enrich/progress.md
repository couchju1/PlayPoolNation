# Venue enrichment progress

Updated 2026-10-26 01:07. Rebuilt by `python3 tools/enrich/enrich.py progress` and after every venue.

Cities are worked in the order of the latest keyword report; a city is finished before the next starts. A *hit* is a venue with at least one **stated** fact for that field (implied facts are counted separately and go to review flagged).

| City | Venues done | Facts (stated) | Venues with a stated fact | With a table fact | New venues found | Disk |
|---|--:|--:|--:|--:|--:|--:|
| new-york (in progress) | 1/147 | 10 (8) | 1 (100%) | 1 (100%) | 142 | 974 KB |
| sioux-falls (done) | 26/26 | 157 (98) | 24 (92%) | 2 (8%) | 16 | 268 KB |

## new-york

Discovery: OpenStreetMap 145 pool-tagged places and 1839 other bars; Google Places skipped (GOOGLE_PLACES_API_KEY not set); 0 league page(s); 3 matched our listings, 142 new.

Pages read 4, skipped by robots.txt or login 0, errors 0.

| Field | Venues with stated fact | Hit rate | Implied only |
|---|--:|--:|--:|
| Table count | 1 | 100% | 0 |
| Table sizes | 0 | 0% | 1 |
| Table brands | 1 | 100% | 0 |
| Pricing | 1 | 100% | 0 |
| League nights | 1 | 100% | 0 |
| Age policy | 0 | 0% | 0 |
| Food | 1 | 100% | 0 |
| Full bar | 0 | 0% | 0 |
| Closed notice | 0 | 0% | 0 |

| Venue | Status | Stated facts | Implied | Sources read |
|---|---|--:|--:|--:|
| Amsterdam Billiards | done | 8 | 2 | 4 |

Run log:

- 2026-10-26 00:52 run started
- 2026-10-26 00:55 enrichment finished: 5 venues
- 2026-10-26 00:55 run started
- 2026-10-26 01:06 redo: enriching every venue again
- 2026-10-26 01:06 run started

## sioux-falls

Discovery: OpenStreetMap 0 pool-tagged places and 21 other bars; Google Places skipped (GOOGLE_PLACES_API_KEY not set); 2 league page(s); 0 matched our listings, 16 new.

Pages read 41, skipped by robots.txt or login 0, errors 0.

| Field | Venues with stated fact | Hit rate | Implied only |
|---|--:|--:|--:|
| Table count | 2 | 8% | 0 |
| Table sizes | 0 | 0% | 25 |
| Table brands | 0 | 0% | 0 |
| Pricing | 1 | 4% | 0 |
| League nights | 22 | 85% | 1 |
| Age policy | 0 | 0% | 0 |
| Food | 6 | 23% | 0 |
| Full bar | 1 | 4% | 7 |
| Closed notice | 0 | 0% | 0 |

| Venue | Status | Stated facts | Implied | Sources read |
|---|---|--:|--:|--:|
| Bigs Bar | done | 15 | 3 | 5 |
| JJ’s Billiards & Darts | done – no website | 10 | 4 | 0 |
| Rack City Billiards | done | 9 | 4 | 2 |
| Gibs Sports Bar | done | 1 | 1 | 2 |
| Lucky’s | done | 4 | 4 | 5 |
| Lucky’s on Louise | done | 1 | 2 | 5 |
| Nickel Spot | done | 7 | 10 | 9 |
| Thirsty Duck | done | 4 | 3 | 8 |
| Tommy Jack’s Pub | done | 0 | 3 | 3 |
| Upper Cut Bar & Grill | done | 0 | 2 | 2 |
| 18th | done – no website | 1 | 1 | 0 |
| Axes & Ales | done – no website | 4 | 1 | 0 |
| Bb's | done – no website | 3 | 2 | 0 |
| Billy Frogs | done – no website | 2 | 1 | 0 |
| Chasers | done – no website | 4 | 1 | 0 |
| Crow Bar | done – no website | 4 | 3 | 0 |
| Double D | done – no website | 5 | 2 | 0 |
| Garage | done – no website | 1 | 2 | 0 |
| Liebrary | done – no website | 3 | 1 | 0 |
| Luckys South | done – no website | 2 | 1 | 0 |
| Pave | done – no website | 2 | 1 | 0 |
| Phoenix | done – no website | 3 | 1 | 0 |
| Red Eye | done – no website | 5 | 3 | 0 |
| The Den | done – no website | 2 | 1 | 0 |
| The Gulch | done – no website | 2 | 1 | 0 |
| Wileys | done – no website | 4 | 1 | 0 |

Run log:

- 2026-10-25 23:43 run started
- 2026-10-25 23:54 enrichment finished: 42 venues; OpenStreetMap discovery still to do, run again
- 2026-10-25 23:55 redo: enriching every venue again
- 2026-10-25 23:55 run started
- 2026-10-26 00:02 resumed: 1 venues already done, skipping them
- 2026-10-26 00:05 enrichment finished: 25 venues
- 2026-10-26 00:06 redo: enriching every venue again
- 2026-10-26 00:06 run started
- 2026-10-26 00:13 enrichment finished: 25 venues
- 2026-10-26 00:14 redo: enriching every venue again
- 2026-10-26 00:14 run started
- 2026-10-26 00:26 enrichment finished: 26 venues; OpenStreetMap discovery still to do, run again
- 2026-10-26 00:26 redo: enriching every venue again
- 2026-10-26 00:26 run started
- 2026-10-26 00:33 enrichment finished: 26 venues
