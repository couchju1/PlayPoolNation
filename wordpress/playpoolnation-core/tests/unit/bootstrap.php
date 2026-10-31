<?php
// Unit tests cover the pure helpers only; they load without WordPress.
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../../vendor/autoload.php';
foreach ( [ 'class-about-text', 'class-address', 'class-dedupe', 'class-format', 'class-og-card', 'class-tri-state', 'class-table-room', 'class-html-fix', 'class-event-data', 'class-keywords', 'class-keyword-report', 'class-city-text', 'class-index-rule', 'class-enrich-fact' ] as $f ) {
	require __DIR__ . '/../../includes/helpers/' . $f . '.php';
}
