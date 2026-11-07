// Hours, places and table facts for the Tonight panel and search. Weekly hours, not a status, so it caches well.
import { clientVenue, venues } from '../../lib/data.ts';

export function GET() {
  return new Response(JSON.stringify(venues().filter((v) => v.status !== 'permanently-closed').map(clientVenue)), {
    headers: { 'Content-Type': 'application/json' },
  });
}
