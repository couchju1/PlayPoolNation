/**
 * The venue record, one JSON file per venue in data/venues/. Every pool fact
 * carries its value, source URL, checked date and confidence. A fact that isn't
 * in the file is unknown, and unknown stays blank on the site.
 */

export type Confidence = 'stated' | 'implied';

export type SourceType =
  | 'admin'
  | 'owner'
  | 'official_website'
  | 'facebook'
  | 'league_operator'
  | 'community'
  | 'web_research'
  | 'google_places'
  | 'openstreetmap'
  | 'default';

export type SizeKey = '7ft' | '8ft' | '9ft' | 'snooker' | 'carom';

export interface Fact {
  field:
    | 'table_count'
    | 'table_sizes'
    | 'table_brands'
    | 'table_models'
    | 'pricing_model'
    | 'pricing_notes'
    | 'league_nights'
    | 'age_policy'
    | 'food'
    | 'full_bar'
    | 'games'
    | 'venue_status';
  key: string;
  value: any;
  source_url: string;
  source_type: SourceType;
  checked_at: string;
  confidence: Confidence;
  flags?: string[];
  approved_at: string;
  approved_by: string;
  evidence_url?: string;
}

export interface VenueRecord {
  schema: 1;
  slug: string;
  wp_id?: number;
  name: string;
  type: string;
  status: 'open' | 'temporarily-closed' | 'permanently-closed';
  address: { street: string; city: string; state: string; postal_code: string; country: string };
  geo: { lat: number; lng: number } | null;
  region: { state: string; state_name: string; city: string; city_name: string };
  phone: string;
  website: string;
  hours: { tz: string; ranges: [number, number][] } | null;
  listing_source: { source: string; url: string; checked_at: string; place_id?: string; osm_id?: string };
  claimed: boolean;
  verification: string;
  photo: string | null;
  created_at: string;
  updated_at: string;
  facts: Fact[];
}

export interface League {
  day: number | null; // 0 = Monday
  time: string;
  orgs: string[];
  name: string;
}

/** What pages are built from: the record plus facts read into plain values. */
export interface Venue extends VenueRecord {
  url: string;
  anchor: string;
  cityKey: string; // "south-dakota/sioux-falls"
  tables: number | null;
  sizeCounts: Partial<Record<SizeKey, number>>;
  sizes: SizeKey[];
  brands: string[];
  models: string;
  pricing: string[];
  hourlyRate: string;
  gamePrice: string;
  pricingNotes: string;
  food: boolean;
  fullBar: boolean;
  games: string[];
  agepolicy: string;
  leagues: League[];
  tableFacts: number;
  indexable: boolean;
  indexGaps: string[];
  lastChecked: string;
}
