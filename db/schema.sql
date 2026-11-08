-- PlayPoolNation on Supabase (Postgres). Run once in the SQL editor, then
-- `npm run db:seed-sql` and run db/seed.sql to load data/venues/.
--
-- Phase 1 (now): data/venues/*.json in git is the source of truth and the site
-- builds from it; the functions write click_events and submissions here.
-- Phase 2 (owner accounts): venues and facts move here; `npm run db:pull`
-- writes them back to data/venues before each build, so pages stay static.
--
-- Every fact carries a value, source URL, checked date and confidence.
-- Row-level security is on for every table. Browsers only ever read published
-- venues and facts; all writes go through the service key in Pages Functions.

create extension if not exists pgcrypto;

create table if not exists venues (
  slug            text primary key check (slug ~ '^[a-z0-9-]+$'),
  wp_id           integer unique,
  name            text not null,
  type            text not null default 'pool-halls',
  status          text not null default 'open' check (status in ('open', 'temporarily-closed', 'permanently-closed')),
  street          text not null default '',
  city            text not null default '',
  state           text not null default '',
  postal_code     text not null default '',
  country         text not null default 'US',
  lat             double precision,
  lng             double precision,
  region_state    text not null default '',
  region_state_name text not null default '',
  region_city     text not null default '',
  region_city_name text not null default '',
  phone           text not null default '',
  website         text not null default '',
  hours_tz        text,
  hours_ranges    jsonb,              -- [[start, end], ...] minutes from Monday 00:00 local
  listing_source  jsonb not null default '{}'::jsonb,  -- {source, url, checked_at, place_id, osm_id}
  claimed         boolean not null default false,
  verification    text not null default 'unverified',
  photo           text,
  published       boolean not null default true,
  created_at      date not null default current_date,
  updated_at      date not null default current_date
);
create index if not exists venues_city on venues (region_state, region_city);

-- Approved, published facts. `key` is unique per venue ("table_sizes:9ft").
create table if not exists facts (
  id           bigint generated always as identity primary key,
  venue_slug   text not null references venues (slug) on update cascade on delete cascade,
  field        text not null,
  key          text not null,
  value        jsonb not null,
  source_url   text not null default '',
  source_type  text not null,
  checked_at   date,
  confidence   text not null check (confidence in ('stated', 'implied')),
  flags        text[] not null default '{}',
  evidence_url text,
  approved_at  date not null default current_date,
  approved_by  text not null,
  unique (venue_slug, key)
);

-- Facts found by tools/enrich waiting for a person. The quote is for the
-- reviewer only and is never published.
create table if not exists fact_queue (
  id            bigint generated always as identity primary key,
  venue_slug    text not null,
  city          text not null,
  field         text not null,
  key           text not null,
  value         jsonb not null,
  source_url    text not null,
  source_type   text not null,
  quote_snippet text,
  checked_at    date,
  confidence    text not null,
  flags         text[] not null default '{}',
  status        text not null default 'pending' check (status in ('pending', 'approved', 'rejected')),
  decided_by    text,
  decided_at    timestamptz,
  note          text,
  created_at    timestamptz not null default now(),
  unique (venue_slug, key, value)
);

-- Add a venue, claim, suggest an edit. Reviewed by a person.
create table if not exists submissions (
  id          uuid primary key default gen_random_uuid(),
  kind        text not null check (kind in ('add', 'claim', 'edit')),
  venue_slug  text,
  payload     jsonb not null,
  email       text,
  status      text not null default 'new' check (status in ('new', 'done', 'declined', 'spam')),
  created_at  timestamptz not null default now()
);

-- First-party lead counts: what owners will be shown after 90 days. No IPs,
-- cookies, user agents or user IDs.
create table if not exists click_events (
  id          bigint generated always as identity primary key,
  venue_slug  text not null,
  event       text not null check (event in ('call', 'directions', 'website', 'claim', 'suggest_edit', 'view')),
  placement   text not null,
  path        text not null,
  city_key    text,
  device      text,
  source      text not null,
  hour_utc    timestamptz not null,
  created_at  timestamptz not null default now()
);
create index if not exists click_events_venue on click_events (venue_slug, hour_utc);

-- Owners who claimed a venue (Supabase Auth users), approved by an admin.
create table if not exists claims (
  id          uuid primary key default gen_random_uuid(),
  venue_slug  text not null references venues (slug) on update cascade on delete cascade,
  user_id     uuid not null references auth.users (id) on delete cascade,
  status      text not null default 'pending' check (status in ('pending', 'approved', 'rejected')),
  created_at  timestamptz not null default now(),
  unique (venue_slug, user_id)
);

-- "We sent you X calls": monthly totals per venue.
create or replace view venue_leads_monthly with (security_invoker = true) as
select venue_slug,
       date_trunc('month', hour_utc)::date as month,
       count(*) filter (where event = 'call')       as calls,
       count(*) filter (where event = 'directions') as directions,
       count(*) filter (where event = 'website')    as website,
       count(*) filter (where event = 'view')       as views
from click_events
group by 1, 2;

alter table venues        enable row level security;
alter table facts         enable row level security;
alter table fact_queue    enable row level security;
alter table submissions   enable row level security;
alter table click_events  enable row level security;
alter table claims        enable row level security;

-- Published data is public.
drop policy if exists venues_read on venues;
create policy venues_read on venues for select using (published);
drop policy if exists facts_read on facts;
create policy facts_read on facts for select using (true);

-- An approved owner can see their own venue's clicks and their own claims.
drop policy if exists clicks_owner on click_events;
create policy clicks_owner on click_events for select using (
  exists (select 1 from claims c where c.venue_slug = click_events.venue_slug and c.user_id = auth.uid() and c.status = 'approved')
);
drop policy if exists claims_own on claims;
create policy claims_own on claims for select using (user_id = auth.uid());

-- fact_queue and submissions: no policies, so only the service key reaches them.

-- The Wall: check-ins and updates players post on venue pages. No accounts, no IPs:
-- a display name, the post, and counts. Three reports hide a post; set status to
-- 'hidden' by hand to take one down. Only the service key (the PHP endpoints) reaches it.
create table if not exists posts (
  id          bigint generated always as identity primary key,
  venue_slug  text not null,
  city_key    text,
  kind        text not null check (kind in ('checkin', 'tables_open', 'wait', 'league', 'tournament', 'note')),
  name        text not null check (char_length(name) between 1 and 30),
  body        text not null default '' check (char_length(body) <= 280),
  cheers      integer not null default 0,
  reports     integer not null default 0,
  status      text not null default 'live' check (status in ('live', 'hidden')),
  created_at  timestamptz not null default now()
);
create index if not exists posts_venue on posts (venue_slug, created_at desc);
create index if not exists posts_city on posts (city_key, created_at desc);
create index if not exists posts_recent on posts (created_at desc);
alter table posts enable row level security;

-- Cheer or report a post in one statement (no read-then-write race).
create or replace function post_react(p_id bigint, p_action text) returns void
language sql as $$
  update posts set
    cheers  = cheers  + (case when p_action = 'cheer'  then 1 else 0 end),
    reports = reports + (case when p_action = 'report' then 1 else 0 end),
    status  = case when p_action = 'report' and reports + 1 >= 3 then 'hidden' else status end
  where id = p_id and status = 'live';
$$;
