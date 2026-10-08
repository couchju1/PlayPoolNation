<?php
// Unit tests cover the pure helpers only; they load without WordPress.
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../../vendor/autoload.php';
foreach ( [ 'class-address', 'class-dedupe', 'class-format', 'class-og-card', 'class-tri-state' ] as $f ) {
	require __DIR__ . '/../../includes/helpers/' . $f . '.php';
}
