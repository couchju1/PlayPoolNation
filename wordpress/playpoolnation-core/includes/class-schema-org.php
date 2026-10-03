<?php
/**
 * schema.org JSON-LD for venues and tournaments.
 *
 * Replaces My Listing's template-driven LocalBusiness markup (which emitted HTML and
 * empty placeholders). Every property comes from stored data; nothing is invented.
 * Breadcrumbs and WebPage markup stay with the SEO plugin (ThinkRank).
 *
 * @package PlayPoolNation\Core
 */

namespace PlayPoolNation\Core;

defined( 'ABSPATH' ) || exit;

final class Schema_Org {

	private const DAY_URIS = [
		'Monday' => 'https://schema.org/Monday', 'Tuesday' => 'https://schema.org/Tuesday', 'Wednesday' => 'https://schema.org/Wednesday',
		'Thursday' => 'https://schema.org/Thursday', 'Friday' => 'https://schema.org/Friday', 'Saturday' => 'https://schema.org/Saturday',
		'Sunday' => 'https://schema.org/Sunday',
	];

	public static function boot(): void {
		add_action( 'wp_head', [ __CLASS__, 'output' ], 30 );
	}

	public static function output(): void {
		if ( ! is_singular( Pool_Schema::POST_TYPE ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		$type = Venue::listing_type( $id );
		$data = null;
		if ( Pool_Schema::VENUE_TYPE === $type ) {
			$data = self::venue( $id );
		} elseif ( Pool_Schema::TOURNAMENT_TYPE === $type ) {
			$data = self::tournament( $id );
		} elseif ( Pool_Schema::INSTRUCTOR_TYPE === $type ) {
			$data = self::instructor( $id );
		}
		if ( $data ) {
			echo "<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
		}
	}

	public static function venue_type( Venue $v ): string {
		$slugs = $v->term_slugs( Pool_Schema::TAX_VENUE_TYPE );
		if ( array_intersect( $slugs, [ 'bars-with-pool-tables', 'sports-bar' ] ) && ! in_array( 'pool-halls', $slugs, true ) ) {
			return 'BarOrPub';
		}
		if ( in_array( 'bowling-center', $slugs, true ) ) {
			return 'BowlingAlley';
		}
		return 'SportsActivityLocation';
	}

	public static function postal_address( Venue $v ): ?array {
		$a = $v->address_parts();
		if ( '' === $a['street'] && '' === $a['city'] ) {
			return null;
		}
		return array_filter( [
			'@type'           => 'PostalAddress',
			'streetAddress'   => $a['street'],
			'addressLocality' => $a['city'],
			'addressRegion'   => $a['state'],
			'postalCode'      => $a['postal_code'],
			'addressCountry'  => $a['country'] ?: ( $a['state'] ? 'US' : '' ),
		] );
	}

	public static function venue( int $id ): ?array {
		$v = Venue::get( $id );
		if ( ! $v || 'publish' !== $v->post->post_status ) {
			return null;
		}
		$data = [
			'@context' => 'https://schema.org',
			'@type'    => self::venue_type( $v ),
			'@id'      => $v->url() . '#venue',
			'name'     => $v->name(),
			'url'      => $v->url(),
		];
		$address = self::postal_address( $v );
		if ( $address ) {
			$data['address'] = $address;
		}
		$loc = $v->location();
		if ( $loc && $loc->lat && $loc->lng ) {
			$data['geo'] = [ '@type' => 'GeoCoordinates', 'latitude' => (float) $loc->lat, 'longitude' => (float) $loc->lng ];
		}
		if ( $v->phone() ) {
			$data['telephone'] = $v->phone();
		}
		if ( $v->website() ) {
			$data['sameAs'] = [ $v->website() ];
		}
		$photo = $v->photo( 'large' );
		if ( $photo ) {
			$data['image'] = $photo;
		}
		$hours = self::opening_hours( $id );
		if ( $hours ) {
			$data['openingHoursSpecification'] = $hours;
		}
		$rating = $v->rating();
		if ( $rating ) {
			$data['aggregateRating'] = [ '@type' => 'AggregateRating', 'ratingValue' => $rating['value'], 'bestRating' => $rating['best'], 'reviewCount' => $rating['count'] ];
		}

		$features = [];
		$total = $v->total_tables();
		if ( $total ) {
			$features[] = [ '@type' => 'LocationFeatureSpecification', 'name' => 'Pool tables', 'value' => $total ];
		}
		foreach ( $v->term_names( 'table-size' ) as $name ) {
			$features[] = [ '@type' => 'LocationFeatureSpecification', 'name' => $name, 'value' => true ];
		}
		foreach ( $v->term_names( 'table-brand' ) as $name ) {
			$features[] = [ '@type' => 'LocationFeatureSpecification', 'name' => $name . ' tables', 'value' => true ];
		}
		foreach ( $v->term_names( Pool_Schema::TAX_AMENITY ) as $name ) {
			$features[] = [ '@type' => 'LocationFeatureSpecification', 'name' => $name, 'value' => true ];
		}
		if ( $features ) {
			$data['amenityFeature'] = $features;
		}
		if ( 'permanently-closed' === $v->status() ) {
			// No standard property; avoid advertising hours for a closed venue.
			unset( $data['openingHoursSpecification'] );
		}
		return $data;
	}

	/** openingHoursSpecification from My Listing's stored schedule. */
	public static function opening_hours( int $id ): array {
		$schedule = get_post_meta( $id, '_work_hours', true );
		if ( ! is_array( $schedule ) ) {
			return [];
		}
		$out = [];
		foreach ( self::DAY_URIS as $day => $uri ) {
			$data = $schedule[ $day ] ?? null;
			if ( ! is_array( $data ) || empty( $data['status'] ) ) {
				continue;
			}
			if ( 'open-all-day' === $data['status'] ) {
				$out[] = [ '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $uri, 'opens' => '00:00', 'closes' => '23:59' ];
				continue;
			}
			if ( 'enter-hours' !== $data['status'] ) {
				continue;
			}
			foreach ( $data as $key => $slot ) {
				if ( ! is_numeric( $key ) || ! is_array( $slot ) || empty( $slot['from'] ) || empty( $slot['to'] ) ) {
					continue;
				}
				$out[] = [ '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $uri, 'opens' => $slot['from'], 'closes' => $slot['to'] ];
			}
		}
		return $out;
	}

	public static function tournament( int $id ): ?array {
		$t = Venue::get( $id );
		if ( ! $t || 'publish' !== $t->post->post_status ) {
			return null;
		}
		$next = Play::upcoming_from_ids( [ $id ], 1 );
		$venue = Venue::get( (int) get_post_meta( $id, '_ppn_venue_id', true ) );
		if ( ! $next || ! $venue ) {
			return null; // Event markup requires a date and a location.
		}
		$status = $t->field( 'tournament-status' );
		$data = [
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => $t->name(),
			'url'                 => $t->url(),
			'startDate'           => $next[0]['when']->format( 'c' ),
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'eventStatus'         => 'cancelled' === $status ? 'https://schema.org/EventCancelled' : ( 'postponed' === $status ? 'https://schema.org/EventPostponed' : 'https://schema.org/EventScheduled' ),
			'location'            => array_filter( [
				'@type'   => 'Place',
				'name'    => $venue->name(),
				'url'     => $venue->url(),
				'address' => self::postal_address( $venue ),
			] ),
		];
		$description = wp_strip_all_tags( (string) $t->post->post_content );
		if ( '' !== trim( $description ) ) {
			$data['description'] = wp_trim_words( $description, 50, '...' );
		}
		$fee = $t->field( 'entry-fee' );
		if ( '' !== $fee && is_numeric( $fee ) ) {
			$data['offers'] = [ '@type' => 'Offer', 'price' => (float) $fee, 'priceCurrency' => 'USD', 'url' => $t->website() ?: $t->url() ];
		}
		return $data;
	}

	/** Person markup for an instructor profile. Only verified credentials are stated as credentials. */
	public static function instructor( int $id ): ?array {
		$i = Venue::get( $id );
		if ( ! $i || 'publish' !== $i->post->post_status ) {
			return null;
		}
		$a = $i->address_parts();
		$data = [
			'@context'  => 'https://schema.org',
			'@type'     => 'Person',
			'name'      => $i->name(),
			'url'       => $i->url(),
			'jobTitle'  => 'Pool instructor',
			'knowsAbout' => array_values( array_merge( [ 'Pool (cue sports)' ], $i->term_names( 'lesson-focus' ), $i->term_names( 'game-type' ) ) ),
		];
		if ( '' !== $a['city'] && '' !== $a['state'] ) {
			$data['address'] = [ '@type' => 'PostalAddress', 'addressLocality' => $a['city'], 'addressRegion' => $a['state'], 'addressCountry' => 'US' ];
		}
		$photo = '';
		$logo = get_post_meta( $id, '_job_logo', true );
		$first = is_array( $logo ) ? reset( $logo ) : $logo;
		if ( is_numeric( $first ) ) {
			$photo = (string) wp_get_attachment_image_url( (int) $first, 'medium' );
		} elseif ( is_string( $first ) && filter_var( $first, FILTER_VALIDATE_URL ) ) {
			$photo = $first;
		}
		if ( $photo ) {
			$data['image'] = $photo;
		}
		if ( $i->website() ) {
			$data['sameAs'] = [ $i->website() ];
		}
		$creds = [];
		foreach ( Instructors::credentials( $id ) as $c ) {
			if ( '' !== $c['verified'] ) {
				$creds[] = [ '@type' => 'EducationalOccupationalCredential', 'name' => $c['name'] ];
			}
		}
		if ( $creds ) {
			$data['hasCredential'] = $creds;
		}
		return $data;
	}
}
