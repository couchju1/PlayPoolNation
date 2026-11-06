<?php
// Run on the old WordPress site: wp eval-file scripts/wordpress-export.php > export.json
// (prints the JSON instead of writing a file when run with wp eval-file).
global $wpdb;
$ids = array_map('intval', get_posts(['post_type'=>'job_listing','post_status'=>'publish','fields'=>'ids','posts_per_page'=>-1,'no_found_rows'=>true,'meta_key'=>'_case27_listing_type','meta_value'=>'place','orderby'=>'ID','order'=>'ASC']));
\PlayPoolNation\Core\Venue::prime($ids);
$tax = array_merge(['table-size','table-brand','pool-pricing','league-org','pool-play','game-type'], [\PlayPoolNation\Core\Pool_Schema::TAX_AMENITY, \PlayPoolNation\Core\Pool_Schema::TAX_VENUE_TYPE]);
$rows = [];
foreach ($ids as $id) {
  $v = \PlayPoolNation\Core\Venue::get($id);
  $loc = $v->location();
  $terms = [];
  foreach ($tax as $t) { $terms[$t] = $v->term_slugs($t); }
  $counts = [];
  foreach (\PlayPoolNation\Core\Pool_Schema::COUNT_FIELDS as $k => $d) { $counts[$k] = $v->count($k); }
  $text = [];
  foreach (array_merge(array_keys(\PlayPoolNation\Core\Pool_Schema::TEXT_FIELDS), array_keys(\PlayPoolNation\Core\Pool_Schema::PRICE_FIELDS)) as $k) { $text[$k] = $v->field($k); }
  $regions = array_map(static fn($t)=>['slug'=>$t->slug,'name'=>$t->name,'parent'=>$t->parent,'id'=>$t->term_id], $v->terms('region'));
  $rows[] = [
    'id'=>$id, 'slug'=>$v->post->post_name, 'url'=>$v->url(), 'name'=>$v->name(),
    'content'=>$v->post->post_content, 'tagline'=>$v->meta('_job_tagline'),
    'created'=>$v->post->post_date_gmt, 'modified'=>$v->post->post_modified_gmt,
    'address'=>$v->address(), 'address_parts'=>$v->address_parts(),
    'lat'=>$loc ? (float)$loc->lat : null, 'lng'=>$loc ? (float)$loc->lng : null,
    'phone'=>$v->phone(), 'website'=>$v->website(), 'email'=>$v->meta('_job_email'),
    'facebook'=>$v->meta('_facebook') ?: $v->meta('_job_facebook'),
    'hours'=>$v->hours_data(),
    'terms'=>$terms, 'known_no'=>$v->known_no(), 'counts'=>$counts, 'fields'=>$text,
    'status'=>$v->status(), 'regions'=>$regions,
    'field_sources'=>\PlayPoolNation\Core\Provenance::field_sources($id),
    'ext_ids'=>\PlayPoolNation\Core\Provenance::external_ids($id),
    'source'=>$v->meta('_ppn_source'), 'source_url'=>$v->meta('_ppn_source_url'),
    'last_checked'=>$v->meta('_ppn_last_checked'), 'verified'=>$v->meta('_ppn_verified'),
    'verification'=>$v->meta('_ppn_verification_status'),
    'claimed'=>$v->is_claimed(), 'photo'=>$v->photo('large'), 'rating'=>$v->rating(),
    'indexable'=>\PlayPoolNation\Core\Venue::is_indexable($id), 'index_gaps'=>\PlayPoolNation\Core\Venue::index_gaps($id),
    'featured'=>(int)$v->meta('_featured'),
  ];
}
$queue = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ppn_enrich_queue", ARRAY_A);
$clicks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ppn_events", ARRAY_A);
$data = ['exported_at'=>gmdate('c'),'venues'=>$rows,'metros'=>get_option('ppn_metros'),'enrich_queue'=>$queue,'click_events'=>$clicks];
echo wp_json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
