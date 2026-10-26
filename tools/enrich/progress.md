# Venue enrichment progress

Updated 2026-10-26 01:43. Rebuilt by `python3 tools/enrich/enrich.py progress` and after every venue.

Cities are worked in the order of the latest keyword report; a city is finished before the next starts. A *hit* is a venue with at least one **stated** fact for that field (implied facts are counted separately and go to review flagged).

| City | Venues done | Facts (stated) | Venues with a stated fact | With a table fact | New venues found | Disk |
|---|--:|--:|--:|--:|--:|--:|
| new-york (in progress) | 146/147 | 250 (66) | 37 (25%) | 6 (4%) | 142 | 445 KB |
| sioux-falls (done) | 26/26 | 157 (98) | 24 (92%) | 2 (8%) | 16 | 268 KB |

## new-york

Discovery: OpenStreetMap 145 pool-tagged places and 1839 other bars; Google Places skipped (GOOGLE_PLACES_API_KEY not set); 0 league page(s); 3 matched our listings, 142 new.

Pages read 184, skipped by robots.txt or login 20, errors 17.

| Field | Venues with stated fact | Hit rate | Implied only |
|---|--:|--:|--:|
| Table count | 5 | 3% | 0 |
| Table sizes | 0 | 0% | 147 |
| Table brands | 2 | 1% | 0 |
| Pricing | 8 | 5% | 0 |
| League nights | 3 | 2% | 2 |
| Age policy | 6 | 4% | 4 |
| Food | 32 | 22% | 2 |
| Full bar | 7 | 5% | 27 |
| Closed notice | 0 | 0% | 0 |

| Venue | Status | Stated facts | Implied | Sources read |
|---|---|--:|--:|--:|
| Amsterdam Billiards | done | 8 | 2 | 4 |
| Anytime Bar & Billiards | done | 3 | 1 | 4 |
| Society Billiards + Bar | done | 3 | 3 | 2 |
| Space Billiard Pool Hall & Sports Bar | done | 3 | 1 | 4 |
| Arena Billiards and Cafe | done – no website | 0 | 1 | 0 |
| Bar Set Lounge & Billiards | done – no website | 0 | 1 | 0 |
| Billiard Factory Lofts | done – no website | 0 | 1 | 0 |
| BQE Billiards and Bar | done – no website | 0 | 1 | 0 |
| Brooklyn Billiards Bushwick | done – no website | 0 | 1 | 0 |
| City Snooker Pool House | done – no website | 0 | 1 | 0 |
| Corner Pocket | done – no website | 0 | 1 | 0 |
| El Coqui Billiards Lounge | done – no website | 0 | 1 | 0 |
| Joe Broadway's Billiards & Sports Pub | done – no website | 0 | 1 | 0 |
| Olympia Billiards Snooker Bar | done – no website | 0 | 1 | 0 |
| Sharks | done – no website | 0 | 1 | 0 |
| Sports Bar and Billiards | done – no website | 0 | 1 | 0 |
| Studio6 Gallery | done – no website | 0 | 1 | 0 |
| The Billiard Company | done – no website | 0 | 1 | 0 |
| The Drift | done – no website | 0 | 1 | 0 |
| The Trim Lounge | done – no website | 0 | 1 | 0 |
| Three Star Billiards | done – no website | 0 | 1 | 0 |
| Uno Cafe and Billiards | done – no website | 0 | 1 | 0 |
| V/S Sports Gaming | done – no website | 0 | 1 | 0 |
| 169 Bar | done | 1 | 2 | 4 |
| Abe's Pagoda Bar | done | 1 | 2 | 5 |
| Alibi | done – no website | 0 | 1 | 0 |
| Alligator Lounge | done | 1 | 1 | 2 |
| Aloft | done | 0 | 1 | 0 |
| Aloft Hotel | done | 0 | 1 | 0 |
| Aloft Manhattan Downtown | done | 0 | 1 | 0 |
| Aloft New York LaGuardia Airport | done | 0 | 1 | 0 |
| Aunt Ginny's | done | 0 | 1 | 1 |
| Bar & Play Pool | done – no website | 0 | 1 | 0 |
| Barfly | done | 1 | 2 | 6 |
| Bleecker Street Bar | done | 1 | 2 | 4 |
| Blue Ruin Bar | done – no website | 0 | 1 | 0 |
| Boat | done | 0 | 1 | 0 |
| Bonus Room | done | 0 | 1 | 1 |
| Bootleg Bar | done | 0 | 1 | 0 |
| Boxers | done – no website | 0 | 1 | 0 |
| Brooklyn Safehouse | done | 1 | 2 | 1 |
| Brooklyn Social | done | 1 | 2 | 1 |
| Bushwick Ice House | done – no website | 0 | 1 | 0 |
| Buttermilk Bar | done | 0 | 1 | 0 |
| Carlow East | done | 0 | 1 | 0 |
| Carmelo's | done – no website | 0 | 1 | 0 |
| Carousel | done – no website | 0 | 1 | 0 |
| Clara's | done | 0 | 1 | 0 |
| Connie O's Pub | done – no website | 0 | 1 | 0 |
| Cozy Corner | done – no website | 0 | 1 | 0 |
| Daddy's Bar | done – no website | 0 | 1 | 0 |
| Doc Holidays | done – no website | 0 | 1 | 0 |
| Double Down Saloon | done – no website | 0 | 1 | 0 |
| Dram Shop | done | 0 | 1 | 0 |
| Finn's Corner | done | 0 | 2 | 3 |
| Frog Wine Bar | done – no website | 0 | 1 | 0 |
| Fáílte | done | 0 | 1 | 0 |
| George & Jacks Tap Room | done – no website | 0 | 1 | 0 |
| Ginger's Bar | done | 1 | 2 | 5 |
| Ha-Weeda Tavern | done | 0 | 1 | 0 |
| Halyards | done | 1 | 2 | 4 |
| Haven Lounge | done | 0 | 1 | 0 |
| Heavy Woods | done | 1 | 2 | 2 |
| Horses and Divorces | done – no website | 0 | 1 | 0 |
| Industry | done – no website | 0 | 1 | 0 |
| Joe's Bar & Grill | done – no website | 0 | 1 | 0 |
| Kingston Hall | done | 1 | 2 | 1 |
| Lone Wolf | done – no website | 0 | 1 | 0 |
| Lou's Athletic Club | done – no website | 0 | 1 | 0 |
| Lucy's Bar | done – no website | 0 | 1 | 0 |
| MacDougal Street Ale House | done | 0 | 1 | 0 |
| Mai Tai's Bar and Kitchen | done – no website | 0 | 1 | 0 |
| Marian's Brooklyn | done – no website | 0 | 1 | 0 |
| Merrion Square Pub | done – no website | 0 | 1 | 0 |
| Merv's | done | 0 | 1 | 0 |
| Metropolitan Bar | done – no website | 0 | 1 | 0 |
| Midway | done – no website | 0 | 1 | 0 |
| Mona's | done | 0 | 1 | 0 |
| Montero's Bar | done | 0 | 1 | 0 |
| Nightshade | done – no website | 0 | 1 | 0 |
| Nowhere | done | 1 | 1 | 2 |
| Ocean's 8 | done | 2 | 3 | 3 |
| One and One | done | 0 | 1 | 0 |
| Ontario Bar | done – no website | 0 | 1 | 0 |
| Paddy Maguire's | done | 0 | 1 | 1 |
| Paddy Reilly's | done | 0 | 1 | 2 |
| Peter Dillon's | done – no website | 0 | 1 | 0 |
| Phil Hughes Bar | done | 0 | 1 | 0 |
| Phoenix Bar | done | 0 | 2 | 3 |
| Pioneers Bar NYC | done | 2 | 3 | 4 |
| Propaganda | done – no website | 0 | 1 | 0 |
| PUBLIC, an Ian Schrager hotel | done | 0 | 1 | 1 |
| Q4 hotel | done – no website | 0 | 1 | 0 |
| Ray's | done | 2 | 1 | 9 |
| Redd's Tavern | done | 0 | 1 | 0 |
| Reif's Tavern | done | 0 | 2 | 1 |
| Roxy Hotel | done | 1 | 2 | 9 |
| Ryan's Daughter | done | 1 | 2 | 6 |
| Secret Pour | done | 0 | 3 | 4 |
| Seneca Bar | done – no website | 0 | 1 | 0 |
| Starr Bar | done | 2 | 1 | 5 |
| Status Q | done – no website | 0 | 1 | 0 |
| Talon Bar | done | 1 | 2 | 3 |
| The Anchored Inn | done | 1 | 2 | 4 |
| The Boiler Room | done | 0 | 1 | 0 |
| The Broadway | done | 1 | 2 | 9 |
| The Brooklyn Inn | done – no website | 0 | 1 | 0 |
| The Emerson | done – no website | 0 | 1 | 0 |
| The Irish American | done – no website | 0 | 1 | 0 |
| The Johnson's | done – no website | 0 | 1 | 0 |
| The Less Dead | done | 0 | 1 | 0 |
| The Levee | done – no website | 0 | 1 | 0 |
| The Mallard Drake | done – no website | 0 | 1 | 0 |
| The Richardson | done – no website | 0 | 1 | 0 |
| The Spotted Dog | done – no website | 0 | 1 | 0 |
| The Spotted Owl | done – no website | 0 | 1 | 0 |
| The Whiskey | done | 1 | 2 | 2 |
| The Windjammer | done | 0 | 1 | 0 |
| Toad Hall | done | 0 | 1 | 0 |
| Triona's | done – no website | 0 | 1 | 0 |
| Tru by Hilton | done | 0 | 1 | 0 |
| Uncle Roger's | done – no website | 0 | 1 | 0 |
| Walsh's Pub | done – no website | 0 | 1 | 0 |
| Welcome to the Johnsons | done – no website | 0 | 1 | 0 |
| ABC Billiards & Cafe | done – no website | 0 | 1 | 0 |
| Carom Café Billiards | done – no website | 0 | 1 | 0 |
| Cue Bar | done | 5 | 1 | 5 |
| New City Billiards & Cafe | done – no website | 0 | 1 | 0 |
| Queens Brewery | done | 1 | 3 | 5 |
| Set Billiards & Bowling | done – no website | 0 | 1 | 0 |
| Social Cues | done | 3 | 1 | 4 |
| Cellar Dog | done | 2 | 2 | 4 |
| 3 Dollar Bill | done | 1 | 2 | 3 |
| Alma | done | 2 | 1 | 4 |
| Ange Noir Cafe | done | 0 | 1 | 0 |
| Backyard Bar & Billards | done | 0 | 1 | 0 |
| Cobra Club | done | 1 | 2 | 3 |
| Fort Hamilton Bowling Center | done | 2 | 1 | 9 |
| Frames Bowling Lounge NYC | done | 2 | 2 | 7 |
| Parkside Lounge | done | 0 | 1 | 0 |
| Salmagundi Club | done – no website | 0 | 1 | 0 |
| Sour Mouse | done | 3 | 3 | 8 |
| St. John’s Recreation Center | done | 0 | 1 | 0 |
| Stonewall Inn | done | 1 | 2 | 9 |
| Sugar Mouse | retry – site rate-limited us; retried next run | 0 | 1 | 0 |
| Superfine | done | 0 | 1 | 0 |
| The Flower Shop | done | 0 | 1 | 1 |

Run log:

- 2026-10-26 00:52 run started
- 2026-10-26 00:55 enrichment finished: 5 venues
- 2026-10-26 00:55 run started
- 2026-10-26 01:06 redo: enriching every venue again
- 2026-10-26 01:06 run started
- 2026-10-26 01:32 enrichment finished: 147 venues
- 2026-10-26 01:33 redo: 3-dollar-bill-new-york-ny,abc-billiards-and-cafe-new-york-ny,alma-new-york-ny,ange-noir-cafe-new-york-ny,backyard-bar-and-billards-new-york-ny,carom-caf-billiards-new-york-ny,cellar-dog-new-york-ny,cobra-club-new-york-ny,cue-bar-new-york-ny,fort-hamilton-bowling-center-new-york-ny,frames-bowling-lounge-nyc-new-york-ny,new-city-billiards-and-cafe-new-york-ny,parkside-lounge-new-york-ny,queens-brewery-new-york-ny,salmagundi-club-new-york-ny,set-billiards-and-bowling-new-york-ny,social-cues-new-york-ny,sour-mouse-new-york-ny,st-johns-recreation-center-new-york-ny,stonewall-inn-new-york-ny,sugar-mouse-new-york-ny,superfine-new-york-ny,the-flower-shop-new-york-ny
- 2026-10-26 01:33 run started
- 2026-10-26 01:40 paused: www.sugarmousenyc.com returned 429 4 times
- 2026-10-26 01:40 run resumed after pause
- 2026-10-26 01:42 enrichment finished: 147 venues; some steps still to do (OpenStreetMap or rate-limited sites), run again

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
