/**
 * Open/closed status from a venue's weekly hours, worked out in the browser with
 * the venue's own time zone, so cached static pages stay right all week.
 *
 * Hours are ranges of minutes from Monday 00:00 local time ([840, 1560] is
 * Monday 2 PM to Tuesday 2 AM), the format the old site stored. Ported from
 * the plugin's ppnOpenStatus; tests/fixtures/open-status.json is the shared
 * fixture set the PHP version was tested against.
 */
export const WEEK = 10080;
export const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const CLOSED_STATUSES = { 'temporarily-closed': 'Temporarily closed', 'permanently-closed': 'Permanently closed' };

/** Minutes since midnight to "1 AM", "11:30 PM", "Midnight", "Noon". */
export function clock(minutes) {
  minutes = ((minutes % 1440) + 1440) % 1440;
  if (minutes === 0) return 'Midnight';
  if (minutes === 720) return 'Noon';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  const h12 = h % 12 || 12;
  return h12 + (m ? ':' + (m < 10 ? '0' : '') + m : '') + ' ' + (h >= 12 ? 'PM' : 'AM');
}

/** Minutes since Monday 00:00 in the venue's time zone, never the visitor's. */
export function weekMinute(tz, now) {
  const parts = {};
  new Intl.DateTimeFormat('en-US', { timeZone: tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
    .formatToParts(now)
    .forEach((p) => { parts[p.type] = p.value; });
  return DAYS.indexOf(parts.weekday) * 1440 + (parseInt(parts.hour, 10) % 24) * 60 + parseInt(parts.minute, 10);
}

function normalize(ranges) {
  const norm = [];
  for (const [s, e] of ranges) {
    if (e <= s) continue;
    if (e - s >= WEEK) return null;
    norm.push([s, e]);
  }
  return norm.sort((a, b) => a[0] - b[0]);
}

/**
 * @param {{tz:string, r:number[][], s?:string}} data
 * @param {Date} now
 * @returns {{state:'open'|'closed'|'unknown', label:string}}
 */
export function openStatus(data, now) {
  if (CLOSED_STATUSES[data.s]) return { state: 'closed', label: CLOSED_STATUSES[data.s] };
  const ranges = data.r || [];
  if (!ranges.length) return { state: 'unknown', label: '' };
  const minute = weekMinute(data.tz, now);
  const norm = normalize(ranges);
  if (norm === null) return { state: 'open', label: 'Open 24 hours' };
  if (!norm.length) return { state: 'unknown', label: '' };
  const total = norm.reduce((sum, r) => sum + r[1] - r[0], 0);
  if (total >= WEEK - 1) return { state: 'open', label: 'Open 24 hours' };

  for (const [s, e] of norm) {
    for (const shift of [0, WEEK, -WEEK]) {
      if (minute >= s + shift && minute < e + shift) {
        let close = e;
        // A range ending exactly where another starts carries on into it.
        for (const [s2, e2] of norm) {
          if (s2 === close % WEEK && e2 > s2) close += e2 - s2;
        }
        return { state: 'open', label: 'Open until ' + clock(close % 1440) };
      }
    }
  }

  let best = null;
  for (const [s] of norm) {
    const delta = (s - minute + WEEK) % WEEK;
    if (best === null || delta < best[0]) best = [delta, s];
  }
  const openDay = Math.floor((best[1] % WEEK) / 1440);
  const time = clock(best[1] % 1440);
  const today = Math.floor(minute / 1440);
  return { state: 'closed', label: openDay === today && best[0] < 1440 ? 'Opens ' + time : 'Opens ' + DAYS[openDay] + ' ' + time };
}

/** "Open hours today: 2 PM - 2 AM" for the venue's current day. */
export function todaysHours(data, now) {
  const today = Math.floor(weekMinute(data.tz, now) / 1440);
  const spans = (data.r || []).filter((r) => Math.floor(r[0] / 1440) === today && r[1] > r[0]);
  if (!spans.length) return (data.r || []).length ? 'Closed today' : '';
  return 'Open hours today: ' + spans.map((r) => (r[1] - r[0] >= 1440 ? 'Open 24h' : clock(r[0]) + ' - ' + clock(r[1]))).join(', ');
}

/** Weekday in the venue's time zone, 0 = Monday. */
export function venueWeekday(tz, now) {
  return Math.floor(weekMinute(tz, now) / 1440);
}

/** Miles between two points (haversine). */
export function miles(lat1, lng1, lat2, lng2) {
  const rad = Math.PI / 180;
  const dLat = (lat2 - lat1) * rad;
  const dLng = (lng2 - lng1) * rad;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) ** 2;
  return 3958.8 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}
