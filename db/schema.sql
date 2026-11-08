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

-- Accounts and reviews. People sign up with Supabase Auth (email and password, email
-- confirmed). A profile holds the public display name; emails stay in auth.users and are
-- never readable through the public API. A review can share its writer's email with that
-- venue only when they tick the box on that review (share_email, off by default).
create table if not exists profiles (
  id            uuid primary key references auth.users (id) on delete cascade,
  display_name  text not null check (char_length(display_name) between 1 and 40),
  created_at    timestamptz not null default now()
);

create table if not exists reviews (
  id           bigint generated always as identity primary key,
  venue_slug   text not null references venues (slug) on update cascade on delete cascade,
  user_id      uuid not null references auth.users (id) on delete cascade,
  rating       smallint not null check (rating between 1 and 5),
  body         text not null default '' check (char_length(body) <= 2000),
  share_email  boolean not null default false,
  status       text not null default 'live' check (status in ('live', 'hidden')),
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now(),
  unique (venue_slug, user_id)
);
create index if not exists reviews_venue on reviews (venue_slug, created_at desc);
create index if not exists reviews_user on reviews (user_id);

alter table profiles enable row level security;
alter table reviews  enable row level security;

-- A profile for every new account, named from the sign-up form.
create or replace function public.handle_new_user() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  insert into public.profiles (id, display_name)
  values (new.id, left(coalesce(nullif(trim(new.raw_user_meta_data->>'display_name'), ''), split_part(new.email, '@', 1)), 40))
  on conflict (id) do nothing;
  return new;
end;
$$;
drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created after insert on auth.users for each row execute function public.handle_new_user();

-- Names and live reviews are public; you can change only your own.
drop policy if exists profiles_read on profiles;
create policy profiles_read on profiles for select using (true);
drop policy if exists profiles_update_own on profiles;
create policy profiles_update_own on profiles for update using (id = auth.uid()) with check (id = auth.uid());
drop policy if exists reviews_read on reviews;
create policy reviews_read on reviews for select using (status = 'live' or user_id = auth.uid());
drop policy if exists reviews_insert_own on reviews;
create policy reviews_insert_own on reviews for insert with check (user_id = auth.uid());
drop policy if exists reviews_update_own on reviews;
create policy reviews_update_own on reviews for update using (user_id = auth.uid()) with check (user_id = auth.uid());
drop policy if exists reviews_delete_own on reviews;
create policy reviews_delete_own on reviews for delete using (user_id = auth.uid());

-- Column rights: visitors never see share_email, and nobody can set status or dates
-- through the API (hiding a review is done by hand or with the service key).
revoke all on profiles, reviews from anon, authenticated;
grant select on profiles to anon, authenticated;
grant update (display_name) on profiles to authenticated;
grant select (id, venue_slug, user_id, rating, body, created_at, updated_at) on reviews to anon;
grant select on reviews to authenticated;
grant insert (venue_slug, user_id, rating, body, share_email) on reviews to authenticated;
grant update (rating, body, share_email, updated_at) on reviews to authenticated;
grant delete on reviews to authenticated;

-- Emails reviewers agreed to share, by venue. In a schema the API doesn't expose, so only
-- the dashboard (SQL editor / table view) reads it: select * from private.review_email_leads;
create schema if not exists private;
revoke all on schema private from anon, authenticated;
create or replace view private.review_email_leads as
select r.venue_slug, v.name as venue_name, u.email, p.display_name, r.rating, r.body, r.created_at
from public.reviews r
join auth.users u on u.id = r.user_id
left join public.profiles p on p.id = r.user_id
left join public.venues v on v.slug = r.venue_slug
where r.share_email and r.status = 'live';

-- An approved owner (see claims) can pull the shared emails for their own venue.
create or replace function public.venue_review_leads(p_slug text)
returns table (email text, display_name text, rating smallint, created_at timestamptz)
language sql security definer set search_path = public as $$
  select u.email::text, p.display_name, r.rating, r.created_at
  from reviews r join auth.users u on u.id = r.user_id left join profiles p on p.id = r.user_id
  where r.venue_slug = p_slug and r.share_email and r.status = 'live'
    and exists (select 1 from claims c where c.venue_slug = p_slug and c.user_id = auth.uid() and c.status = 'approved');
$$;
revoke all on function public.venue_review_leads(text) from public, anon;
grant execute on function public.venue_review_leads(text) to authenticated;

-- "Delete my account" on /account/: removes the login, profile and reviews (they cascade).
create or replace function public.delete_my_account() returns void
language sql security definer set search_path = public as $$
  delete from auth.users where id = auth.uid();
$$;
revoke all on function public.delete_my_account() from public, anon;
grant execute on function public.delete_my_account() to authenticated;

-- Email the owner when someone confirms a new account: the trigger sends only the user id to
-- /api/notify-signup, which checks it with Supabase before emailing (public/api/notify-signup.php).
-- pg_net makes the web request after the transaction commits, so sign-ups never wait on it.
create extension if not exists pg_net with schema extensions;

create or replace function public.notify_signup() returns trigger
language plpgsql security definer set search_path = public, extensions as $$
begin
  if new.email_confirmed_at is not null and (tg_op = 'INSERT' or old.email_confirmed_at is null) then
    perform net.http_post(
      url := 'https://playpoolnation.com/api/notify-signup/',
      body := jsonb_build_object('id', new.id),
      headers := '{"Content-Type": "application/json"}'::jsonb
    );
  end if;
  return new;
end;
$$;
drop trigger if exists on_auth_user_confirmed on auth.users;
create trigger on_auth_user_confirmed after insert or update of email_confirmed_at on auth.users
  for each row execute function public.notify_signup();

-- Following bars, and the events bars post: the player page (/me/) shows a news feed of
-- events (plus Wall posts and reviews) from the venues you follow.
-- Admins can post events for any venue; an owner with an approved claim, for their own.
create table if not exists admins (
  user_id     uuid primary key references auth.users (id) on delete cascade,
  created_at  timestamptz not null default now()
);
alter table admins enable row level security;
revoke all on admins from anon, authenticated;

create table if not exists follows (
  user_id     uuid not null references auth.users (id) on delete cascade,
  venue_slug  text not null references venues (slug) on update cascade on delete cascade,
  created_at  timestamptz not null default now(),
  primary key (user_id, venue_slug)
);
create index if not exists follows_venue on follows (venue_slug);
alter table follows enable row level security;
drop policy if exists follows_own on follows;
create policy follows_own on follows for all using (user_id = auth.uid()) with check (user_id = auth.uid());
revoke all on follows from anon, authenticated;
grant select, insert, delete on follows to authenticated;

create table if not exists events (
  id          bigint generated always as identity primary key,
  venue_slug  text not null references venues (slug) on update cascade on delete cascade,
  kind        text not null default 'other' check (kind in ('tournament', 'league', 'special', 'music', 'other')),
  title       text not null check (char_length(title) between 3 and 120),
  body        text not null default '' check (char_length(body) <= 2000),
  starts_at   timestamptz not null,
  ends_at     timestamptz check (ends_at is null or ends_at > starts_at),
  status      text not null default 'live' check (status in ('live', 'cancelled', 'hidden')),
  created_by  uuid references auth.users (id) on delete set null,
  created_at  timestamptz not null default now()
);
create index if not exists events_venue on events (venue_slug, starts_at);
create index if not exists events_start on events (starts_at);
alter table events enable row level security;

-- Can the signed-in person manage this venue's events?
create or replace function public.can_manage_venue(p_slug text) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from admins where user_id = auth.uid())
      or exists (select 1 from claims where venue_slug = p_slug and user_id = auth.uid() and status = 'approved');
$$;
revoke all on function public.can_manage_venue(text) from public;
grant execute on function public.can_manage_venue(text) to anon, authenticated;

drop policy if exists events_read on events;
create policy events_read on events for select using (status in ('live', 'cancelled') or can_manage_venue(venue_slug));
drop policy if exists events_insert on events;
create policy events_insert on events for insert with check (can_manage_venue(venue_slug) and created_by = auth.uid());
drop policy if exists events_update on events;
create policy events_update on events for update using (can_manage_venue(venue_slug)) with check (can_manage_venue(venue_slug));
drop policy if exists events_delete on events;
create policy events_delete on events for delete using (can_manage_venue(venue_slug));
revoke all on events from anon, authenticated;
grant select on events to anon, authenticated;
grant insert (venue_slug, kind, title, body, starts_at, ends_at, created_by) on events to authenticated;
grant update (kind, title, body, starts_at, ends_at, status) on events to authenticated;
grant delete on events to authenticated;

-- Followers per venue, without exposing who follows.
create or replace function public.follower_count(p_slug text) returns integer
language sql stable security definer set search_path = public as $$
  select count(*)::int from follows where venue_slug = p_slug;
$$;
revoke all on function public.follower_count(text) from public;
grant execute on function public.follower_count(text) to anon, authenticated;

-- Photos. Bars (an admin, or the owner with an approved claim) add photos to their venue
-- page in the public bucket venue-photos. Players can attach a photo to a Wall post: it's
-- uploaded by /api/post into the private bucket wall-pending and stays hidden until an
-- admin approves it on /admin/, which moves it to the public bucket wall-photos.
create or replace function public.is_admin() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from admins where user_id = auth.uid());
$$;
revoke all on function public.is_admin() from public;
grant execute on function public.is_admin() to anon, authenticated;

create table if not exists venue_photos (
  id          bigint generated always as identity primary key,
  venue_slug  text not null references venues (slug) on update cascade on delete cascade,
  path        text not null unique,
  caption     text not null default '' check (char_length(caption) <= 140),
  created_by  uuid references auth.users (id) on delete set null,
  created_at  timestamptz not null default now()
);
create index if not exists venue_photos_venue on venue_photos (venue_slug, created_at desc);
alter table venue_photos enable row level security;
drop policy if exists venue_photos_read on venue_photos;
create policy venue_photos_read on venue_photos for select using (true);
drop policy if exists venue_photos_insert on venue_photos;
create policy venue_photos_insert on venue_photos for insert with check (can_manage_venue(venue_slug) and created_by = auth.uid());
drop policy if exists venue_photos_delete on venue_photos;
create policy venue_photos_delete on venue_photos for delete using (can_manage_venue(venue_slug));
revoke all on venue_photos from anon, authenticated;
grant select on venue_photos to anon, authenticated;
grant insert (venue_slug, path, caption, created_by) on venue_photos to authenticated;
grant delete on venue_photos to authenticated;

-- A Wall post can carry one photo, hidden until approved.
alter table posts add column if not exists photo_path text;
alter table posts add column if not exists photo_status text check (photo_status in ('pending', 'approved', 'rejected'));
create index if not exists posts_photo_pending on posts (created_at) where photo_status = 'pending';
-- Admins review photos from the browser; everyone else still reaches posts only through PHP.
drop policy if exists posts_admin_read on posts;
create policy posts_admin_read on posts for select using (is_admin());
drop policy if exists posts_admin_update on posts;
create policy posts_admin_update on posts for update using (is_admin()) with check (is_admin());
grant select on posts to authenticated;
grant update (photo_status, photo_path) on posts to authenticated;

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types) values
  ('venue-photos', 'venue-photos', true, 6291456, array['image/jpeg', 'image/png', 'image/webp']),
  ('wall-photos', 'wall-photos', true, 6291456, array['image/jpeg']),
  ('wall-pending', 'wall-pending', false, 6291456, array['image/jpeg'])
on conflict (id) do nothing;

-- venue-photos/<venue slug>/<file>: the venue's managers add and remove their own.
drop policy if exists venue_photos_upload on storage.objects;
create policy venue_photos_upload on storage.objects for insert to authenticated
  with check (bucket_id = 'venue-photos' and public.can_manage_venue((storage.foldername(name))[1]));
drop policy if exists venue_photos_remove on storage.objects;
create policy venue_photos_remove on storage.objects for delete to authenticated
  using (bucket_id = 'venue-photos' and public.can_manage_venue((storage.foldername(name))[1]));
-- wall-pending (private) and wall-photos (public): only admins move and remove files.
drop policy if exists wall_pending_admin on storage.objects;
create policy wall_pending_admin on storage.objects for all to authenticated
  using (bucket_id = 'wall-pending' and public.is_admin()) with check (bucket_id = 'wall-pending' and public.is_admin());
drop policy if exists wall_photos_admin on storage.objects;
create policy wall_photos_admin on storage.objects for all to authenticated
  using (bucket_id = 'wall-photos' and public.is_admin()) with check (bucket_id = 'wall-photos' and public.is_admin());

-- Bar dashboard (/dashboard/): leads, followers, ratings and email leads for a venue, for its
-- approved owner and for admins. Admins also see claim requests and make owners on /admin/.
drop policy if exists clicks_admin on click_events;
create policy clicks_admin on click_events for select using (is_admin());
grant select on click_events to authenticated;

-- Claim requests sent from /list-your-venue/ (kind 'claim') are readable by admins.
drop policy if exists submissions_admin on submissions;
create policy submissions_admin on submissions for select using (is_admin());
grant select on submissions to authenticated;

-- The venues you own (approved claims).
create or replace function public.my_venues() returns setof text
language sql stable security definer set search_path = public as $$
  select venue_slug from claims where user_id = auth.uid() and status = 'approved' order by created_at;
$$;
revoke all on function public.my_venues() from public;
grant execute on function public.my_venues() to authenticated;

-- Email leads for a venue: its owner, or an admin.
create or replace function public.venue_review_leads(p_slug text)
returns table (email text, display_name text, rating smallint, created_at timestamptz)
language sql security definer set search_path = public as $$
  select u.email::text, p.display_name, r.rating, r.created_at
  from reviews r join auth.users u on u.id = r.user_id left join profiles p on p.id = r.user_id
  where r.venue_slug = p_slug and r.share_email and r.status = 'live' and can_manage_venue(p_slug)
  order by r.created_at desc;
$$;
revoke all on function public.venue_review_leads(text) from public, anon;
grant execute on function public.venue_review_leads(text) to authenticated;

-- Admins: make an account the owner of a venue (by the email they signed up with), remove
-- an owner, and list owners. Each refuses anyone who isn't an admin.
create or replace function public.admin_set_owner(p_email text, p_slug text) returns text
language plpgsql security definer set search_path = public as $$
declare uid uuid;
begin
  if not is_admin() then raise exception 'admins only'; end if;
  select id into uid from auth.users where lower(email) = lower(trim(p_email));
  if uid is null then return 'no-account'; end if;
  if not exists (select 1 from venues where slug = p_slug) then return 'no-venue'; end if;
  insert into claims (venue_slug, user_id, status) values (p_slug, uid, 'approved')
  on conflict (venue_slug, user_id) do update set status = 'approved';
  return 'ok';
end;
$$;
create or replace function public.admin_remove_owner(p_email text, p_slug text) returns void
language plpgsql security definer set search_path = public as $$
begin
  if not is_admin() then raise exception 'admins only'; end if;
  delete from claims c using auth.users u where c.user_id = u.id and lower(u.email) = lower(p_email) and c.venue_slug = p_slug;
end;
$$;
create or replace function public.admin_owners()
returns table (venue_slug text, email text, display_name text, created_at timestamptz)
language plpgsql stable security definer set search_path = public as $$
begin
  if not is_admin() then raise exception 'admins only'; end if;
  return query select c.venue_slug, u.email::text, p.display_name, c.created_at
    from claims c join auth.users u on u.id = c.user_id left join profiles p on p.id = c.user_id
    where c.status = 'approved' order by c.created_at desc;
end;
$$;
revoke all on function public.admin_set_owner(text, text), public.admin_remove_owner(text, text), public.admin_owners() from public, anon;
grant execute on function public.admin_set_owner(text, text), public.admin_remove_owner(text, text), public.admin_owners() to authenticated;
