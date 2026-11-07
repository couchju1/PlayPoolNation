#!/usr/bin/env node
/**
 * The review queue: a person approves every fact before it goes live.
 *
 *   npm run review            then open http://localhost:5178
 *
 * Reads what tools/enrich found (tools/enrich/out/<city>/), shows each fact
 * next to what the site has now, with its source link and the quote it was
 * read from, and writes approved facts into data/venues/<slug>.json. Rejections
 * and approvals are logged in data/review/decisions.jsonl, so a rejected fact
 * doesn't come back unless its value changes. Commit the changed files and the
 * next deploy publishes them; git history is the audit trail.
 *
 * Keys: j/k move, a approve, r reject, e edit the value, n/p next/previous venue.
 * Runs on your machine only (binds to 127.0.0.1). No dependencies.
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const OUT = path.join(ROOT, 'tools/enrich/out');
const VENUES = path.join(ROOT, 'data/venues');
const LOG = path.join(ROOT, 'data/review/decisions.jsonl');
const PORT = Number(process.env.PORT || 5178);
const today = () => new Date().toISOString().slice(0, 10);
const reviewer = (() => {
  try {
    return execSync('git config user.name', { encoding: 'utf8' }).trim() || process.env.USER || 'reviewer';
  } catch {
    return process.env.USER || 'reviewer';
  }
})();

const STATES = { AL: 'alabama', AK: 'alaska', AZ: 'arizona', AR: 'arkansas', CA: 'california', CO: 'colorado', CT: 'connecticut', DE: 'delaware', DC: 'washington-d-c', FL: 'florida', GA: 'georgia', HI: 'hawaii', ID: 'idaho', IL: 'illinois', IN: 'indiana', IA: 'iowa', KS: 'kansas', KY: 'kentucky', LA: 'louisiana', ME: 'maine', MD: 'maryland', MA: 'massachusetts', MI: 'michigan', MN: 'minnesota', MS: 'mississippi', MO: 'missouri', MT: 'montana', NE: 'nebraska', NV: 'nevada', NH: 'new-hampshire', NJ: 'new-jersey', NM: 'new-mexico', NY: 'new-york', NC: 'north-carolina', ND: 'north-dakota', OH: 'ohio', OK: 'oklahoma', OR: 'oregon', PA: 'pennsylvania', RI: 'rhode-island', SC: 'south-carolina', SD: 'south-dakota', TN: 'tennessee', TX: 'texas', UT: 'utah', VT: 'vermont', VA: 'virginia', WA: 'washington', WV: 'west-virginia', WI: 'wisconsin', WY: 'wyoming' };
const titleCase = (s) => s.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const slugify = (s) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[&+]/g, ' and ').replace(/['’`"]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const readJson = (p, fallback) => {
  try {
    return JSON.parse(fs.readFileSync(p, 'utf8'));
  } catch {
    return fallback;
  }
};
const writeJson = (p, data) => {
  fs.mkdirSync(path.dirname(p), { recursive: true });
  const tmp = p + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(data, null, 2) + '\n');
  fs.renameSync(tmp, p);
};
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

function decisions() {
  if (!fs.existsSync(LOG)) return [];
  return fs.readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map((l) => JSON.parse(l));
}
function logDecision(d) {
  fs.mkdirSync(path.dirname(LOG), { recursive: true });
  fs.appendFileSync(LOG, JSON.stringify({ ...d, at: new Date().toISOString(), by: reviewer }) + '\n');
}

function cities() {
  if (!fs.existsSync(OUT)) return [];
  return fs.readdirSync(OUT).filter((d) => fs.existsSync(path.join(OUT, d, '_state.json')));
}

/** Everything still waiting for a decision in one city. */
function queue(city) {
  const dir = path.join(OUT, city);
  const log = decisions();
  const rejected = (slug, key, value) => log.some((d) => d.action === 'reject' && d.slug === slug && d.key === key && same(d.value, value));
  const venues = [];
  // New venues found by discovery, not on the site yet.
  for (const c of readJson(path.join(dir, 'new-venues.json'), [])) {
    if (fs.existsSync(path.join(VENUES, `${c.slug}.json`)) || rejected(c.slug, '_venue', c.name)) continue;
    venues.push({ slug: c.slug, name: c.name, kind: 'new', candidate: c, items: [] });
  }
  for (const f of fs.readdirSync(dir).filter((x) => x.endsWith('.json') && !x.startsWith('_') && !['listings.json', 'new-venues.json', 'league-facts.json', 'osm-listing-links.json', 'osm-bars.json'].includes(x))) {
    const found = readJson(path.join(dir, f), null);
    if (!found?.facts) continue;
    const record = readJson(path.join(VENUES, `${found.slug}.json`), null);
    if (!record) continue; // facts for a new venue wait until the venue itself is approved
    const items = [];
    for (const fact of found.facts) {
      const current = record.facts.filter((x) => x.key === fact.key || (x.field === fact.field && ['table_count', 'food', 'full_bar', 'age_policy', 'venue_status'].includes(fact.field)));
      if (current.some((x) => same(x.value, fact.value) && x.source_type !== 'default')) continue;
      if (rejected(found.slug, fact.key, fact.value)) continue;
      items.push({ ...fact, current: current.map((x) => ({ value: x.value, source_url: x.source_url, source_type: x.source_type })) });
    }
    if (items.length) venues.push({ slug: found.slug, name: record.name, kind: 'listing', items });
  }
  return venues;
}

function approveFact(slug, fact, editedValue) {
  const file = path.join(VENUES, `${slug}.json`);
  const record = readJson(file, null);
  if (!record) throw new Error('no such venue');
  const value = editedValue === undefined ? fact.value : editedValue;
  const single = ['table_count', 'food', 'full_bar', 'age_policy', 'venue_status'].includes(fact.field);
  record.facts = record.facts.filter((x) => x.key !== fact.key && !(single && x.field === fact.field));
  record.facts.push({
    field: fact.field,
    key: fact.key,
    value,
    source_url: fact.source_url,
    source_type: fact.source_type || 'web_research',
    checked_at: fact.checked_at || today(),
    confidence: fact.confidence,
    flags: [...(fact.flags || []), ...(editedValue !== undefined ? ['edited by reviewer'] : [])],
    ...(fact.evidence_url ? { evidence_url: fact.evidence_url } : {}),
    approved_at: today(),
    approved_by: reviewer,
  });
  if (fact.field === 'venue_status') record.status = value;
  record.updated_at = today();
  writeJson(file, record);
  logDecision({ action: 'approve', slug, key: fact.key, value });
}

function approveVenue(c, edits) {
  const v = { ...c, ...edits };
  if (!v.street || !v.city || !v.state) throw new Error('a new venue needs a street, city and state');
  const state = STATES[v.state.toUpperCase()];
  if (!state) throw new Error('state should be a two-letter code');
  const slug = c.slug;
  writeJson(path.join(VENUES, `${slug}.json`), {
    schema: 1,
    slug,
    name: v.name,
    type: v.venue_type || 'bars-with-pool-tables',
    status: 'open',
    address: { street: v.street, city: v.city, state: v.state.toUpperCase(), postal_code: v.zip || '', country: 'US' },
    geo: v.lat && v.lng ? { lat: Number(v.lat), lng: Number(v.lng) } : null,
    region: { state, state_name: titleCase(state), city: slugify(v.city), city_name: v.city },
    phone: v.phone || '',
    website: v.website || '',
    hours: null,
    listing_source: { source: (c.sources?.[0]?.source === 'osm' ? 'openstreetmap' : c.sources?.[0]?.source) || 'web_research', url: c.sources?.[0]?.url || '', checked_at: c.discovered_at || today(), osm_id: c.osm_id || '' },
    claimed: false,
    verification: 'unverified',
    photo: null,
    created_at: today(),
    updated_at: today(),
    facts: [],
  });
  logDecision({ action: 'approve', slug, key: '_venue', value: v.name });
}

const PAGE = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Review queue</title>
<style>
body{margin:0;font:15px/1.5 system-ui,sans-serif;color:#14201b;background:#f2f4f1}header{background:#0e3b2d;color:#fff;padding:10px 16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
header select{font:inherit;padding:4px}main{display:grid;grid-template-columns:260px 1fr;min-height:calc(100vh - 48px)}nav{background:#fff;border-right:1px solid #d9e0db;overflow:auto}
nav button{display:block;width:100%;text-align:left;border:0;border-bottom:1px solid #eef1ee;background:none;padding:8px 12px;font:inherit;cursor:pointer}nav button[aria-current=true]{background:#e8efe9;font-weight:700}
nav small{color:#5b6b64}section{padding:16px 20px;overflow:auto}.item{background:#fff;border:1px solid #d9e0db;border-radius:8px;padding:12px 14px;margin:0 0 10px}.item.sel{outline:3px solid #2f6fe4}
.item.done{opacity:.45}.row{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}button.b{border:1px solid #2f6fe4;background:#2f6fe4;color:#fff;border-radius:6px;padding:4px 12px;font:inherit;cursor:pointer}button.g{background:#fff;color:#2f6fe4}
code{background:#f2f4f1;padding:1px 4px;border-radius:4px}.flag{display:inline-block;background:#f4ead8;color:#8a6424;border-radius:4px;padding:0 6px;margin:2px 4px 0 0;font-size:13px}.q{color:#5b6b64;font-style:italic}
.k{color:#5b6b64;font-size:13px}input{font:inherit;padding:4px 6px;border:1px solid #d9e0db;border-radius:4px}.msg{margin-left:auto}
</style></head><body>
<header><b>Review queue</b><select id="city"></select><span class="k">j/k move · a approve · r reject · e edit · n/p venue</span><span class="msg" id="msg"></span></header>
<main><nav id="venues"></nav><section id="items"><p>Pick a city.</p></section></main>
<script>
const $=s=>document.querySelector(s);let Q=[],vi=0,ii=0;
const fmt=v=>typeof v==='object'&&v?Object.entries(v).filter(([,x])=>x!==null&&x!=='').map(([k,x])=>k+': '+x).join(', '):String(v);
async function load(){const c=$('#city').value;Q=await (await fetch('/api/queue?city='+c)).json();vi=Math.min(vi,Math.max(0,Q.length-1));ii=0;draw();}
function draw(){
 $('#venues').innerHTML=Q.map((v,i)=>'<button aria-current='+(i===vi)+' data-i='+i+'>'+esc(v.name)+'<br><small>'+(v.kind==='new'?'new venue':v.items.length+' to review')+'</small></button>').join('')||'<p style="padding:12px">Nothing waiting.</p>';
 document.querySelectorAll('nav button').forEach(b=>b.onclick=()=>{vi=+b.dataset.i;ii=0;draw();});
 const v=Q[vi];if(!v){$('#items').innerHTML='<p>Nothing waiting in this city.</p>';return;}
 if(v.kind==='new'){const c=v.candidate;$('#items').innerHTML='<h2>'+esc(c.name)+' <small class=k>new venue</small></h2>'+(c.flags||[]).map(f=>'<span class=flag>'+esc(f)+'</span>').join('')+
  '<p>'+(c.sources||[]).map(s=>'<a target=_blank href="'+esc(s.url)+'">'+esc(s.source)+'</a> '+esc(s.evidence||'')).join('<br>')+'</p>'+
  ['name','street','city','state','zip','phone','website','venue_type'].map(k=>'<p><label>'+k+' <input size=40 data-k='+k+' value="'+esc(c[k]||'')+'"></label></p>').join('')+
  '<div class=row><button class=b id=va>Approve venue</button><button class="b g" id=vr>Reject</button></div>';
  $('#va').onclick=()=>post({city:$('#city').value,slug:v.slug,kind:'venue',action:'approve',edits:Object.fromEntries([...document.querySelectorAll('[data-k]')].map(i=>[i.dataset.k,i.value]))});
  $('#vr').onclick=()=>post({city:$('#city').value,slug:v.slug,kind:'venue',action:'reject'});return;}
 $('#items').innerHTML='<h2>'+esc(v.name)+'</h2>'+v.items.map((f,i)=>'<div class="item'+(i===ii?' sel':'')+'" data-i='+i+'><b>'+esc(f.field)+'</b> <code>'+esc(fmt(f.value))+'</code> <span class=k>'+esc(f.confidence)+'</span><br>'+
  (f.current.length?'<span class=k>On the site now: '+f.current.map(c=>esc(fmt(c.value))+' ('+esc(c.source_type)+')').join('; ')+'</span><br>':'<span class=k>On the site now: unknown</span><br>')+
  (f.flags||[]).map(x=>'<span class=flag>'+esc(x)+'</span>').join('')+(f.quote_snippet?'<p class=q>“'+esc(f.quote_snippet)+'”</p>':'')+
  '<span class=k>Source: <a target=_blank href="'+esc(f.source_url)+'">'+esc(f.source_url)+'</a>'+(f.evidence_url?' · <a target=_blank href="'+esc(f.evidence_url)+'">evidence</a>':'')+' · checked '+esc(f.checked_at||'')+'</span>'+
  '<div class=row><button class=b data-a=approve>Approve</button><button class="b g" data-a=reject>Reject</button><button class="b g" data-a=edit>Edit</button></div></div>').join('');
 document.querySelectorAll('.item').forEach(el=>el.querySelectorAll('button').forEach(b=>b.onclick=()=>{ii=+el.dataset.i;act(b.dataset.a);}));
 document.querySelector('.item.sel')?.scrollIntoView({block:'nearest'});
}
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
async function act(a){const v=Q[vi],f=v?.items?.[ii];if(!f)return;let value;
 if(a==='edit'){const t=prompt('Value as JSON',JSON.stringify(f.value));if(t===null)return;try{value=JSON.parse(t);}catch{alert('Not valid JSON');return;}a='approve';}
 await post({city:$('#city').value,slug:v.slug,kind:'fact',action:a,fact:f,value});}
async function post(body){const r=await fetch('/api/decide',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(body)});const j=await r.json();$('#msg').textContent=j.ok?'Saved.':j.error;await load();}
document.addEventListener('keydown',e=>{if(e.target.tagName==='INPUT')return;const v=Q[vi];
 if(e.key==='j'&&v?.items){ii=Math.min(ii+1,v.items.length-1);draw();}if(e.key==='k'){ii=Math.max(ii-1,0);draw();}
 if(e.key==='n'){vi=Math.min(vi+1,Q.length-1);ii=0;draw();}if(e.key==='p'){vi=Math.max(vi-1,0);ii=0;draw();}
 if(e.key==='a')act('approve');if(e.key==='r')act('reject');if(e.key==='e')act('edit');});
fetch('/api/cities').then(r=>r.json()).then(c=>{$('#city').innerHTML=c.map(x=>'<option>'+esc(x)+'</option>').join('');$('#city').onchange=load;if(c.length)load();});
</script></body></html>`;

const send = (res, status, body, type = 'application/json') => {
  res.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-store' });
  res.end(type === 'application/json' ? JSON.stringify(body) : body);
};

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  try {
    if (req.method === 'GET' && url.pathname === '/') return send(res, 200, PAGE, 'text/html; charset=utf-8');
    if (req.method === 'GET' && url.pathname === '/api/cities') return send(res, 200, cities());
    if (req.method === 'GET' && url.pathname === '/api/queue') {
      const city = url.searchParams.get('city') || '';
      if (!cities().includes(city)) return send(res, 404, { error: 'no such city' });
      return send(res, 200, queue(city));
    }
    if (req.method === 'POST' && url.pathname === '/api/decide') {
      let raw = '';
      for await (const chunk of req) raw += chunk;
      const b = JSON.parse(raw);
      if (!cities().includes(b.city) || !/^[a-z0-9-]+$/.test(b.slug)) return send(res, 400, { error: 'bad request' });
      if (b.kind === 'venue') {
        const c = readJson(path.join(OUT, b.city, 'new-venues.json'), []).find((x) => x.slug === b.slug);
        if (!c) return send(res, 404, { error: 'no such candidate' });
        if (b.action === 'approve') approveVenue(c, b.edits || {});
        else logDecision({ action: 'reject', slug: b.slug, key: '_venue', value: c.name });
        return send(res, 200, { ok: true });
      }
      // Only facts the tool actually found can be approved, never one made up in the browser.
      const found = readJson(path.join(OUT, b.city, `${b.slug}.json`), null);
      const fact = found?.facts?.find((f) => f.key === b.fact?.key && same(f.value, b.fact?.value));
      if (!fact) return send(res, 404, { error: 'that fact is not in the enrich output' });
      if (b.action === 'approve') approveFact(b.slug, fact, b.value);
      else logDecision({ action: 'reject', slug: b.slug, key: fact.key, value: fact.value });
      return send(res, 200, { ok: true });
    }
    send(res, 404, { error: 'not found' });
  } catch (e) {
    send(res, 400, { error: e.message });
  }
});
server.listen(PORT, '127.0.0.1', () => console.log(`Review queue on http://localhost:${PORT} (reviewer: ${reviewer}). Ctrl-C to stop.`));
