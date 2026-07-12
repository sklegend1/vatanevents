<?php
/**
 * Plugin Name: Vatan Iran Events Reseed
 * Description: One-shot seeder that creates realistic Iranian concert/theater
 *              events with real venues, coordinates, round-table seat maps,
 *              and Pexels images. Visit /wp-admin/?vatan_seed_iran=1 as admin
 *              to run. Delete once demo data is in place.
 */

defined( 'ABSPATH' ) || exit;

/* =============================================================================
 *  Entry point
 * ===========================================================================*/

add_action( 'admin_init', function () {
	if ( empty( $_GET['vatan_seed_iran'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	$log = array();
	vatan_irseed_wipe_events( $log );
	$cities = vatan_irseed_build_city_tree( $log );
	$cats   = vatan_irseed_ensure_categories( $log );
	vatan_irseed_create_events( $cities, $cats, $log );

	wp_die(
		'<h1>Vatan Iran events reseed report</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( implode( "\n", $log ) )
		. '</pre><p><a href="' . esc_url( admin_url( 'edit.php?post_type=event' ) ) . '">→ View events</a></p>',
		'Vatan Iran events seeder',
		array( 'response' => 200 )
	);
} );

/* =============================================================================
 *  Step 1 — wipe existing events + linked WC products
 * ===========================================================================*/

function vatan_irseed_wipe_events( array &$log ): void {
	$events = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$products = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_vatan_event_id',
		'fields'         => 'ids',
	) );

	foreach ( $products as $pid ) {
		wp_delete_post( $pid, true );
	}
	foreach ( $events as $eid ) {
		wp_delete_post( $eid, true );
	}

	$log[] = '[wipe ] deleted ' . count( $events ) . ' event(s) + ' . count( $products ) . ' ticket product(s).';
}

/* =============================================================================
 *  Step 2 — city tree (Country → City) with flag emojis
 * ===========================================================================*/

function vatan_irseed_build_city_tree( array &$log ): array {
	$tree = array(
		'iran' => array( 'name' => 'Iran', 'flag' => '🇮🇷', 'cities' => array(
			'tehran'       => 'Tehran',
			'isfahan'      => 'Isfahan',
			'shiraz'       => 'Shiraz',
			'mashhad'      => 'Mashhad',
			'tabriz'       => 'Tabriz',
		) ),
	);

	$resolved = array();

	foreach ( $tree as $country_slug => $info ) {
		$country = term_exists( $country_slug, 'event_city' );
		if ( ! $country ) {
			$country = wp_insert_term( $info['name'], 'event_city', array( 'slug' => $country_slug ) );
		}
		if ( is_wp_error( $country ) ) {
			$log[] = '[skip ] country ' . $country_slug . ': ' . $country->get_error_message();
			continue;
		}
		$country_id = (int) ( is_array( $country ) ? $country['term_id'] : $country );
		update_term_meta( $country_id, 'vatan_country_flag', $info['flag'] );

		foreach ( $info['cities'] as $city_slug => $city_name ) {
			$city = term_exists( $city_slug, 'event_city' );
			if ( ! $city ) {
				$city = wp_insert_term( $city_name, 'event_city', array(
					'slug'   => $city_slug,
					'parent' => $country_id,
				) );
			}
			if ( is_wp_error( $city ) ) {
				$log[] = '[skip ] city ' . $city_slug . ': ' . $city->get_error_message();
				continue;
			}
			$resolved[ $city_slug ] = (int) ( is_array( $city ) ? $city['term_id'] : $city );
		}
	}

	$log[] = '[cities] resolved ' . count( $resolved ) . ' city term(s).';
	return $resolved;
}

/* =============================================================================
 *  Step 3 — ensure event_category terms exist
 * ===========================================================================*/

function vatan_irseed_ensure_categories( array &$log ): array {
	$wanted = array(
		'concert'           => 'Concert',
		'traditional-music' => 'Traditional music',
		'theater'           => 'Theater',
		'standup'           => 'Standup comedy',
		'classical'         => 'Classical / Symphony',
		'festival'          => 'Festival',
	);

	$resolved = array();
	foreach ( $wanted as $slug => $name ) {
		$t = term_exists( $slug, 'event_category' );
		if ( ! $t ) {
			$t = wp_insert_term( $name, 'event_category', array( 'slug' => $slug ) );
		}
		if ( is_wp_error( $t ) ) {
			$log[] = '[skip ] category ' . $slug . ': ' . $t->get_error_message();
			continue;
		}
		$resolved[ $slug ] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
	}

	$log[] = '[cats ] resolved ' . count( $resolved ) . ' category term(s).';
	return $resolved;
}

/* =============================================================================
 *  Helper — look up an attachment ID from the media seeder by slug
 * ===========================================================================*/

function vatan_irseed_image( string $slug ): int {
	$state = (array) get_option( 'vatan_media_seeded', array() );
	if ( ! empty( $state['attachments'][ 'event:' . $slug ] ) ) {
		return (int) $state['attachments'][ 'event:' . $slug ];
	}
	$att = get_page_by_path( $slug, OBJECT, 'attachment' );
	return $att ? (int) $att->ID : 0;
}

/* =============================================================================
 *  Step 4 — the event catalogue (Iranian venues)
 * ===========================================================================*/

function vatan_irseed_catalogue(): array {
	$today = current_time( 'Y-m-d' );

	return array(
		/* ── 1. Ebi — Royal Hall, Tehran ─────────────────────────────── */
		array(
			'title'      => 'کنسرت ابی — تالار وحدت',
			'excerpt'    => 'اجرای زنده‌ی ابی در تالار وحدت تهران با ارکستر بزرگ.',
			'content'    => '<p>ابی، خواننده‌ی محبوب پاپ ایرانی، پس از سال‌ها در تالار وحدت تهران روی صحنه می‌رود. این کنسرت شامل آهنگ‌های کلاسیک و قطعات جدید خواهد بود.</p><p>تالار وحدت با ظرفیت بالا و صدای عالی، میزبان یک شب فراموش‌نشدنی است.</p>',
			'date'       => '2026-09-15',
			'time_start' => '20:00',
			'time_end'   => '23:00',
			'duration'   => 180,
			'age_limit'  => 12,
			'venue'      => 'تالار وحدت، خیابان ولیعصر، تهران',
			'lat'        => 35.7128,
			'lng'        => 51.3988,
			'map'        => 'https://maps.google.com/?q=Vahdat+Hall+Tehran',
			'city'       => 'tehran',
			'cats'       => array( 'concert' ),
			'image'      => 'event-singer-stage',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 450, 'color' => '#F59E0B', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' => 280, 'color' => '#7C3AED', 'capacity' => 72 ),
				array( 'name' => 'Economy',  'price' => 150, 'color' => '#3B82F6', 'capacity' => 72 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 12, 14, array(
				array( 'rows' => array( 1, 2 ),                'type' => 'VIP',      'price' => 450, 'color' => '#F59E0B' ),
				array( 'rows' => array( 3, 4, 5, 6 ),          'type' => 'Standard', 'price' => 280, 'color' => '#7C3AED' ),
				array( 'rows' => array( 7, 8, 9, 10, 11, 12 ), 'type' => 'Economy',  'price' => 150, 'color' => '#3B82F6' ),
			), array( '1-7', '1-8', '6-7', '6-8' ) ),
		),

		/* ── 2. Googoosh — Milad Tower, Tehran (round tables) ────────── */
		array(
			'title'      => 'کنسرت گوگوش — برج میلاد',
			'excerpt'    => 'گوگوش در برج میلاد تهران با اجرایی متفاوت.',
			'content'    => '<p>گوگوش، بانوی موسیقی پاپ ایرانی، در برج میلاد تهران روی صحنه می‌رود. اجرایی متفاوت با گروه نوازندگان بین‌المللی.</p><p>سالن چیدمان میز گرد با ظرفیت بالا.</p>',
			'date'       => '2026-10-05',
			'time_start' => '19:30',
			'time_end'   => '22:30',
			'duration'   => 180,
			'age_limit'  => 0,
			'venue'      => 'برج میلاد، بلوار شیخ فضل‌الله نوری، تهران',
			'lat'        => 35.7448,
			'lng'        => 51.4209,
			'map'        => 'https://maps.google.com/?q=Milad+Tower+Tehran',
			'city'       => 'tehran',
			'cats'       => array( 'concert' ),
			'image'      => 'event-dj-deck',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 520, 'color' => '#F59E0B', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' => 320, 'color' => '#7C3AED', 'capacity' => 48 ),
				array( 'name' => 'Economy',  'price' => 180, 'color' => '#3B82F6', 'capacity' => 48 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 8, 6, 32 ),
		),

		/* ── 3. Homayoun Shajarian — Isfahan Music Hall (grid) ──────── */
		array(
			'title'      => 'شب موسیقی سنتی — همایون شجریان',
			'excerpt'    => 'همایون شجریان در تالار موسیقی اصفهان.',
			'content'    => '<p>همایون شجریان با گروه نوازندگان موسیقی سنتی در تالار موسیقی اصفهان اجرا می‌کند.</p><p>قطعاتی از حافظ، سعدی و مولوی همراه با تنبک، تار و کمانچه.</p>',
			'date'       => '2026-09-20',
			'time_start' => '19:00',
			'time_end'   => '21:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'تالار موسیقی اصفهان، خیابان چهارباغ، اصفهان',
			'lat'        => 32.6546,
			'lng'        => 51.6680,
			'map'        => 'https://maps.google.com/?q=Isfahan+Music+Hall',
			'city'       => 'isfahan',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-classical-hall',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 380, 'color' => '#F59E0B', 'capacity' => 18 ),
				array( 'name' => 'Standard', 'price' => 220, 'color' => '#7C3AED', 'capacity' => 54 ),
				array( 'name' => 'Economy',  'price' => 120, 'color' => '#3B82F6', 'capacity' => 48 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 10, 12, array(
				array( 'rows' => array( 1, 2 ),                'type' => 'VIP',      'price' => 380, 'color' => '#F59E0B' ),
				array( 'rows' => array( 3, 4, 5, 6, 7 ),       'type' => 'Standard', 'price' => 220, 'color' => '#7C3AED' ),
				array( 'rows' => array( 8, 9, 10 ),             'type' => 'Economy',  'price' => 120, 'color' => '#3B82F6' ),
			), array( '1-6', '1-7', '5-6', '5-7' ) ),
		),

		/* ── 4. Mehdi Yarrahi — Parsian Hotel, Isfahan (round tables) ── */
		array(
			'title'      => 'کنسرت مهدی یarraهی — هتل پارسیان',
			'excerpt'    => 'مهدی یarraهی در هتل پارسیان اصفهان.',
			'content'    => '<p>مهدی یarraهی، خواننده‌ی محبوب پاپ راک، در سالن هتل پارسیان اصفهان اجرا می‌کند.</p><p>آهنگ‌های محبوب و قطعات جدید از آلبوم آخر.</p>',
			'date'       => '2026-10-10',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 15,
			'venue'      => 'هتل پارسیان، خیابان سی تیر، اصفهان',
			'lat'        => 32.6580,
			'lng'        => 51.6720,
			'map'        => 'https://maps.google.com/?q=Parsian+Hotel+Isfahan',
			'city'       => 'isfahan',
			'cats'       => array( 'concert' ),
			'image'      => 'event-band-live',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 350, 'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 200, 'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 100, 'color' => '#3B82F6', 'capacity' => 36 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 6, 16 ),
		),

		/* ── 5. Concert Hall Shiraz (grid) ──────────────────────────── */
		array(
			'title'      => 'شب آواز ایرانی — شیراز',
			'excerpt'    => 'شبی از آواز ایرانی در تالار حافظ شیراز.',
			'content'    => '<p>تالار حافظ شیراز میزبان شبی از آواز و موسیقی ایرانی با هنرمندان بنام است.</p><p>اجراهای زنده با سازهای سنتی ایرانی.</p>',
			'date'       => '2026-09-25',
			'time_start' => '19:30',
			'time_end'   => '22:00',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'تالار حافظ، خیابان زند، شیراز',
			'lat'        => 29.6100,
			'lng'        => 52.5300,
			'map'        => 'https://maps.google.com/?q=Hafez+Hall+Shiraz',
			'city'       => 'shiraz',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-piano-keys',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 300, 'color' => '#F59E0B', 'capacity' => 18 ),
				array( 'name' => 'Standard', 'price' => 180, 'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 90,  'color' => '#3B82F6', 'capacity' => 42 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 9, 10, array(
				array( 'rows' => array( 1, 2 ),                'type' => 'VIP',      'price' => 300, 'color' => '#F59E0B' ),
				array( 'rows' => array( 3, 4, 5 ),             'type' => 'Standard', 'price' => 180, 'color' => '#7C3AED' ),
				array( 'rows' => array( 6, 7, 8, 9 ),          'type' => 'Economy',  'price' => 90,  'color' => '#3B82F6' ),
			), array( '1-5', '1-6', '5-5', '5-6' ) ),
		),

		/* ── 6. Pishro — Mashhad Arena (round tables) ──────────────── */
		array(
			'title'      => 'کنسرت پیشرو — مشهد',
			'excerpt'    => 'گروه پیشرو در سالن پارک شهر مشهد.',
			'content'    => '<p>گروه پیشرو با اجرایی پرانرژی در سالن پارک شهر مشهد روی صحنه می‌رود.</p><p>آهنگ‌های راک و هیپ‌هاپ با سیستم صوتی حرفه‌ای.</p>',
			'date'       => '2026-10-18',
			'time_start' => '21:00',
			'time_end'   => '23:30',
			'duration'   => 150,
			'age_limit'  => 18,
			'venue'      => 'سالن پارک شهر، بلوار ویلا، مشهد',
			'lat'        => 36.3167,
			'lng'        => 59.5700,
			'map'        => 'https://maps.google.com/?q=Mashhad+Park+Hall',
			'city'       => 'mashhad',
			'cats'       => array( 'concert' ),
			'image'      => 'event-festival-fans',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 280, 'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 160, 'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 80,  'color' => '#3B82F6', 'capacity' => 48 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 6, 16 ),
		),

		/* ── 7. Aref — Tabriz Theater (grid) ────────────────────────── */
		array(
			'title'      => 'نمایش موزیکال — عارف در تبریز',
			'excerpt'    => 'نمایش موزیکال در تئاتر تبریز.',
			'content'    => '<p>تئاتر تبریز میزبان یک نمایش موزیکال با بازیگران بنام تئاتر ایران است.</p><p>ترکیبی از موسیقی زنده و بازیگری حرفه‌ای.</p>',
			'date'       => '2026-10-01',
			'time_start' => '18:00',
			'time_end'   => '20:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'تئاتر تبریز، خیابان امام، تبریز',
			'lat'        => 38.0800,
			'lng'        => 46.2900,
			'map'        => 'https://maps.google.com/?q=Tabriz+Theater',
			'city'       => 'tabriz',
			'cats'       => array( 'theater' ),
			'image'      => 'event-theater-curtain',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 250, 'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 150, 'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 80,  'color' => '#3B82F6', 'capacity' => 42 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 8, 10, array(
				array( 'rows' => array( 1 ),                    'type' => 'VIP',      'price' => 250, 'color' => '#F59E0B' ),
				array( 'rows' => array( 2, 3, 4 ),              'type' => 'Standard', 'price' => 150, 'color' => '#7C3AED' ),
				array( 'rows' => array( 5, 6, 7, 8 ),           'type' => 'Economy',  'price' => 80,  'color' => '#3B82F6' ),
			), array( '2-5', '2-6', '5-5', '5-6' ) ),
		),

		/* ── 8. Standup at City Theater Tehran (round tables) ──────── */
		array(
			'title'      => 'شب استندآپ کمدی — تئاتر شهر تهران',
			'excerpt'    => 'شبی پر از خنده با کمدین‌های محبوب.',
			'content'    => '<p>تئاتر شهر تهران میزبان شبی استندآپ کمدی با حضور کمدین‌های محبوب ایرانی است.</p><p>اجراهای ۱۵ دقیقه‌ای با موضوعات متنوع.</p>',
			'date'       => '2026-09-30',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 16,
			'venue'      => 'تئاتر شهر، خیابان وسط‌الشریعه، تهران',
			'lat'        => 35.6892,
			'lng'        => 51.3890,
			'map'        => 'https://maps.google.com/?q=City+Theater+Tehran',
			'city'       => 'tehran',
			'cats'       => array( 'standup' ),
			'image'      => 'event-guitar-live',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 200, 'color' => '#F59E0B', 'capacity' => 8 ),
				array( 'name' => 'Standard', 'price' => 120, 'color' => '#7C3AED', 'capacity' => 32 ),
				array( 'name' => 'Economy',  'price' => 60,  'color' => '#3B82F6', 'capacity' => 32 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 5, 6, 20 ),
		),

		/* ── 9. Festival — Fajr International Music Festival (grid) ──── */
		array(
			'title'      => 'جشنواره موسیقی فجر — شب پایانی',
			'excerpt'    => 'شب پایانی جشنواره بین‌المللی موسیقی فجر در تالار وحدت.',
			'content'    => '<p>شب پایانی جشنواره بین‌المللی موسیقی فجر با اجرای بهترین‌های موسیقی ایران و جهان.</p><p>اجراهای ویژه با هنرمندان بین‌المللی.</p>',
			'date'       => '2026-11-01',
			'time_start' => '19:00',
			'time_end'   => '23:00',
			'duration'   => 240,
			'age_limit'  => 0,
			'venue'      => 'تالار وحدت، خیابان ولیعصر، تهران',
			'lat'        => 35.7128,
			'lng'        => 51.3988,
			'map'        => 'https://maps.google.com/?q=Vahdat+Hall+Tehran',
			'city'       => 'tehran',
			'cats'       => array( 'festival' ),
			'image'      => 'event-hero-concert-crowd',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 600, 'color' => '#F59E0B', 'capacity' => 30 ),
				array( 'name' => 'Standard', 'price' => 350, 'color' => '#7C3AED', 'capacity' => 90 ),
				array( 'name' => 'Economy',  'price' => 200, 'color' => '#3B82F6', 'capacity' => 80 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 12, 14, array(
				array( 'rows' => array( 1, 2, 3 ),              'type' => 'VIP',      'price' => 600, 'color' => '#F59E0B' ),
				array( 'rows' => array( 4, 5, 6, 7, 8 ),        'type' => 'Standard', 'price' => 350, 'color' => '#7C3AED' ),
				array( 'rows' => array( 9, 10, 11, 12 ),         'type' => 'Economy',  'price' => 200, 'color' => '#3B82F6' ),
			), array( '1-7', '1-8', '5-7', '5-8', '9-7', '9-8' ) ),
		),

		/* ── 10. Mashhad Traditional Music (round tables) ────────────── */
		array(
			'title'      => 'شب موسیقی مقامی — مشهد',
			'excerpt'    => 'موسیقی مقامی خراسان در تالار فرهنگ مشهد.',
			'content'    => '<p>تالار فرهنگ مشهد میزبان شبی از موسیقی مقامی خراسان با هنرمندان محلی است.</p><p>اجراهای زنده با سازهای سنتی خراسانی.</p>',
			'date'       => '2026-10-15',
			'time_start' => '19:00',
			'time_end'   => '21:00',
			'duration'   => 120,
			'age_limit'  => 0,
			'venue'      => 'تالار فرهنگ، بلوار وحید، مشهد',
			'lat'        => 36.3000,
			'lng'        => 59.5500,
			'map'        => 'https://maps.google.com/?q=Farhang+Hall+Mashhad',
			'city'       => 'mashhad',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-microphone',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 200, 'color' => '#F59E0B', 'capacity' => 8 ),
				array( 'name' => 'Standard', 'price' => 120, 'color' => '#7C3AED', 'capacity' => 32 ),
				array( 'name' => 'Economy',  'price' => 60,  'color' => '#3B82F6', 'capacity' => 40 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 3, 8, 12 ),
		),

		/* ── 2. Googoosh — Milad Tower, Tehran ────────────────────────── */
		array(
			'title'      => 'کنسرت گوگوش — برج میلاد',
			'excerpt'    => 'گوگوش در برج میلاد تهران با اجرایی متفاوت.',
			'content'    => '<p>گوگوش، بانوی موسیقی پاپ ایرانی، در برج میلاد تهران روی صحنه می‌رود. اجرایی متفاوت با گروه نوازندگان بین‌المللی.</p><p>سالن چیدمان میز گرد با ظرفیت بالا.</p>',
			'date'       => '2026-10-05',
			'time_start' => '19:30',
			'time_end'   => '22:30',
			'duration'   => 180,
			'age_limit'  => 0,
			'venue'      => 'برج میلاد، بلوار شیخ فضل‌الله نوری، تهران',
			'lat'        => 35.7448,
			'lng'        => 51.4209,
			'map'        => 'https://maps.google.com/?q=Milad+Tower+Tehran',
			'city'       => 'tehran',
			'cats'       => array( 'concert' ),
			'image'      => 'event-dj-deck',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 520, 'color' => '#F59E0B', 'capacity' => 16 ),
				array( 'name' => 'Standard', 'price' => 320, 'color' => '#7C3AED', 'capacity' => 64 ),
				array( 'name' => 'Economy',  'price' => 180, 'color' => '#3B82F6', 'capacity' => 80 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 6, 8, 28 ),
		),

		/* ── 3. Homayoun Shajarian — Isfahan Music Hall ────────────────── */
		array(
			'title'      => 'شب موسیقی سنتی — همایون شجریان',
			'excerpt'    => 'همایون شجریان در تالار موسیقی اصفهان.',
			'content'    => '<p>همایون شجریان با گروه نوازندگان موسیقی سنتی در تالار موسیقی اصفهان اجرا می‌کند.</p><p>قطعاتی از حافظ، سعدی و مولوی همراه با تنبک، تار و کمانچه.</p>',
			'date'       => '2026-09-20',
			'time_start' => '19:00',
			'time_end'   => '21:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'تالار موسیقی اصفهان، خیابان چهارباغ، اصفهان',
			'lat'        => 32.6546,
			'lng'        => 51.6680,
			'map'        => 'https://maps.google.com/?q=Isfahan+Music+Hall',
			'city'       => 'isfahan',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-classical-hall',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 380, 'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 220, 'color' => '#7C3AED', 'capacity' => 48 ),
				array( 'name' => 'Economy',  'price' => 120, 'color' => '#3B82F6', 'capacity' => 60 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 5, 6, 20 ),
		),

		/* ── 4. Mehdi Yarrahi — Parsian Hotel, Isfahan ────────────────── */
		array(
			'title'      => 'کنسرت مهدی یarraهی — هتل پارسیان',
			'excerpt'    => 'مهدی یarraهی در هتل پارسیان اصفهان.',
			'content'    => '<p>مهدی یarraهی، خواننده‌ی محبوب پاپ راک، در سالن هتل پارسیان اصفهان اجرا می‌کند.</p><p>آهنگ‌های محبوب و قطعات جدید از آلبوم آخر.</p>',
			'date'       => '2026-10-10',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 15,
			'venue'      => 'هتل پارسیان، خیابان سی تیر، اصفهان',
			'lat'        => 32.6580,
			'lng'        => 51.6720,
			'map'        => 'https://maps.google.com/?q=Parsian+Hotel+Isfahan',
			'city'       => 'isfahan',
			'cats'       => array( 'concert' ),
			'image'      => 'event-band-live',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 350, 'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 200, 'color' => '#7C3AED', 'capacity' => 48 ),
				array( 'name' => 'Economy',  'price' => 100, 'color' => '#3B82F6', 'capacity' => 48 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 8, 16 ),
		),

		/* ── 5. Concert Hall Shiraz — Shajarian ──────────────────────── */
		array(
			'title'      => 'شب آواز ایرانی — شیراز',
			'excerpt'    => 'شبی از آواز ایرانی در تالار حافظ شیراز.',
			'content'    => '<p>تالار حافظ شیراز میزبان شبی از آواز و موسیقی ایرانی با هنرمندان بنام است.</p><p>اجراهای زنده با سازهای سنتی ایرانی.</p>',
			'date'       => '2026-09-25',
			'time_start' => '19:30',
			'time_end'   => '22:00',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'تالار حافظ، خیابان زند، شیراز',
			'lat'        => 29.6100,
			'lng'        => 52.5300,
			'map'        => 'https://maps.google.com/?q=Hafez+Hall+Shiraz',
			'city'       => 'shiraz',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-piano-keys',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 300, 'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 180, 'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 90,  'color' => '#3B82F6', 'capacity' => 48 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 3, 8, 18 ),
		),

		/* ── 6. Pishro — Mashhad Arena ──────────────────────────────── */
		array(
			'title'      => 'کنسرت پیشرو — مشهد',
			'excerpt'    => 'گروه پیشرو در سالن پارک شهر مشهد.',
			'content'    => '<p>گروه پیشرو با اجرایی پرانرژی در سالن پارک شهر مشهد روی صحنه می‌رود.</p><p>آهنگ‌های راک و هیپ‌هاپ با سیستم صوتی حرفه‌ای.</p>',
			'date'       => '2026-10-18',
			'time_start' => '21:00',
			'time_end'   => '23:30',
			'duration'   => 150,
			'age_limit'  => 18,
			'venue'      => 'سالن پارک شهر، بلوار ویلا، مشهد',
			'lat'        => 36.3167,
			'lng'        => 59.5700,
			'map'        => 'https://maps.google.com/?q=Mashhad+Park+Hall',
			'city'       => 'mashhad',
			'cats'       => array( 'concert' ),
			'image'      => 'event-festival-fans',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 280, 'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 160, 'color' => '#7C3AED', 'capacity' => 48 ),
				array( 'name' => 'Economy',  'price' => 80,  'color' => '#3B82F6', 'capacity' => 60 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 8, 16 ),
		),

		/* ── 7. Aref — Tabriz Theater ────────────────────────────────── */
		array(
			'title'      => 'نمایش موزیکال — عارف در تبریز',
			'excerpt'    => 'نمایش موزیکال در تئاتر تبریز.',
			'content'    => '<p>تئاتر تبریز میزبان یک نمایش موزیکال با بازیگران بنام تئاتر ایران است.</p><p>ترکیبی از موسیقی زنده و بازیگری حرفه‌ای.</p>',
			'date'       => '2026-10-01',
			'time_start' => '18:00',
			'time_end'   => '20:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'تئاتر تبریز، خیابان امام، تبریز',
			'lat'        => 38.0800,
			'lng'        => 46.2900,
			'map'        => 'https://maps.google.com/?q=Tabriz+Theater',
			'city'       => 'tabriz',
			'cats'       => array( 'theater' ),
			'image'      => 'event-theater-curtain',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 250, 'color' => '#F59E0B', 'capacity' => 10 ),
				array( 'name' => 'Standard', 'price' => 150, 'color' => '#7C3AED', 'capacity' => 40 ),
				array( 'name' => 'Economy',  'price' => 80,  'color' => '#3B82F6', 'capacity' => 50 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 6, 16 ),
		),

		/* ── 8. Standup at City Theater Tehran ──────────────────────── */
		array(
			'title'      => 'شب استندآپ کمدی — تئاتر شهر تهران',
			'excerpt'    => 'شبی پر از خنده با کمدین‌های محبوب.',
			'content'    => '<p>تئاتر شهر تهران میزبان شبی استندآپ کمدی با حضور کمدین‌های محبوب ایرانی است.</p><p>اجراهای ۱۵ دقیقه‌ای با موضوعات متنوع.</p>',
			'date'       => '2026-09-30',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 16,
			'venue'      => 'تئاتر شهر، خیابان وسط‌الشریعه، تهران',
			'lat'        => 35.6892,
			'lng'        => 51.3890,
			'map'        => 'https://maps.google.com/?q=City+Theater+Tehran',
			'city'       => 'tehran',
			'cats'       => array( 'standup' ),
			'image'      => 'event-guitar-live',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 200, 'color' => '#F59E0B', 'capacity' => 10 ),
				array( 'name' => 'Standard', 'price' => 120, 'color' => '#7C3AED', 'capacity' => 40 ),
				array( 'name' => 'Economy',  'price' => 60,  'color' => '#3B82F6', 'capacity' => 50 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 6, 16 ),
		),

		/* ── 9. Festival — Fajr International Music Festival ──────────── */
		array(
			'title'      => 'جشنواره موسیقی فجر — شب پایانی',
			'excerpt'    => 'شب پایانی جشنواره بین‌المللی موسیقی فجر در تالار وحدت.',
			'content'    => '<p>شب پایانی جشنواره بین‌المللی موسیقی فجر با اجرای بهترین‌های موسیقی ایران و جهان.</p><p>اجراهای ویژه با هنرمندان بین‌المللی.</p>',
			'date'       => '2026-11-01',
			'time_start' => '19:00',
			'time_end'   => '23:00',
			'duration'   => 240,
			'age_limit'  => 0,
			'venue'      => 'تالار وحدت، خیابان ولیعصر، تهران',
			'lat'        => 35.7128,
			'lng'        => 51.3988,
			'map'        => 'https://maps.google.com/?q=Vahdat+Hall+Tehran',
			'city'       => 'tehran',
			'cats'       => array( 'festival' ),
			'image'      => 'event-hero-concert-crowd',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 600, 'color' => '#F59E0B', 'capacity' => 20 ),
				array( 'name' => 'Standard', 'price' => 350, 'color' => '#7C3AED', 'capacity' => 80 ),
				array( 'name' => 'Economy',  'price' => 200, 'color' => '#3B82F6', 'capacity' => 100 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 10, 6, 36 ),
		),

		/* ── 10. Mashhad Traditional Music ────────────────────────────── */
		array(
			'title'      => 'شب موسیقی مقامی — مشهد',
			'excerpt'    => 'موسیقی مقامی خراسان در تالار فرهنگ مشهد.',
			'content'    => '<p>تالار فرهنگ مشهد میزبان شبی از موسیقی مقامی خراسان با هنرمندان محلی است.</p><p>اجراهای زنده با سازهای سنتی خراسانی.</p>',
			'date'       => '2026-10-15',
			'time_start' => '19:00',
			'time_end'   => '21:00',
			'duration'   => 120,
			'age_limit'  => 0,
			'venue'      => 'تالار فرهنگ، بلوار وحید، مشهد',
			'lat'        => 36.3000,
			'lng'        => 59.5500,
			'map'        => 'https://maps.google.com/?q=Farhang+Hall+Mashhad',
			'city'       => 'mashhad',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-microphone',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 200, 'color' => '#F59E0B', 'capacity' => 8 ),
				array( 'name' => 'Standard', 'price' => 120, 'color' => '#7C3AED', 'capacity' => 32 ),
				array( 'name' => 'Economy',  'price' => 60,  'color' => '#3B82F6', 'capacity' => 40 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 3, 8, 12 ),
		),
	);
}

/* =============================================================================
 *  Seat map builder — grid seats (theater-style rows/cols)
 * ===========================================================================*/

function vatan_irseed_grid_map( int $rows, int $cols, array $sections, array $hallways = array() ): array {
	$reserved = array();

	// Reserve some random seats across sections
	foreach ( $sections as $sec ) {
		if ( empty( $sec['rows'] ) ) continue;
		$row = $sec['rows'][0];
		$reserved[] = $row . '-' . 1;
		$reserved[] = $row . '-' . $cols;
	}

	return array(
		'rows'     => $rows,
		'cols'     => $cols,
		'sections' => $sections,
		'reserved' => $reserved,
		'hallways' => $hallways,
	);
}

/* =============================================================================
 *  Seat map builder — round tables
 * ===========================================================================*/

function vatan_irseed_round_table_map( int $num_tables, int $seats_per_table, int $reserved_count ): array {
	$tables = array();
	$reserved = array();

	for ( $i = 1; $i <= $num_tables; $i++ ) {
		$table_id = 'T' . $i;
		$label    = 'T' . $i;
		$price    = $i <= 2 ? 200 : ( $i <= 4 ? 120 : 70 );
		$color    = $i <= 2 ? '#F59E0B' : ( $i <= 4 ? '#7C3AED' : '#3B82F6' );
		$type     = $i <= 2 ? 'VIP Table' : ( $i <= 4 ? 'Standard Table' : 'Economy Table' );

		$tables[] = array(
			'id'     => $table_id,
			'seats'  => $seats_per_table,
			'label'  => $label,
			'type'   => $type,
			'price'  => $price,
			'color'  => $color,
			'row'    => $i,
		);

		// Mark some seats as reserved
		for ( $s = 1; $s <= min( 2, $seats_per_table ); $s++ ) {
			$reserved[] = $table_id . '-' . $s;
		}
	}

	return array(
		'tables'   => $tables,
		'reserved' => array_slice( $reserved, 0, $reserved_count ),
	);
}

/* =============================================================================
 *  Step 5 — create events
 * ===========================================================================*/

function vatan_irseed_create_events( array $cities, array $cats, array &$log ): void {
	$catalogue = vatan_irseed_catalogue();
	$created   = 0;

	foreach ( $catalogue as $row ) {
		// Resolve city term
		$city_id = isset( $cities[ $row['city'] ] ) ? $cities[ $row['city'] ] : 0;

		// Create the event post
		$event_id = wp_insert_post( array(
			'post_type'    => 'event',
			'post_status'  => 'publish',
			'post_title'   => $row['title'],
			'post_excerpt' => $row['excerpt'],
			'post_content' => $row['content'],
		) );

		if ( is_wp_error( $event_id ) ) {
			$log[] = '[skip ] ' . $row['title'] . ': ' . $event_id->get_error_message();
			continue;
		}

		// Set ACF fields
		update_field( 'event_date',          $row['date'],      $event_id );
		update_field( 'event_time_start',    $row['time_start'], $event_id );
		update_field( 'event_time_end',      $row['time_end'],   $event_id );
		update_field( 'event_duration',      $row['duration'],   $event_id );
		update_field( 'event_age_limit',     $row['age_limit'],  $event_id );
		update_field( 'event_venue',         $row['venue'],      $event_id );
		update_field( 'event_venue_lat',     $row['lat'],        $event_id );
		update_field( 'event_venue_lng',     $row['lng'],        $event_id );
		update_field( 'event_venue_map_link', $row['map'],       $event_id );
		update_field( 'event_status',        'upcoming',         $event_id );
		update_field( 'event_is_featured',   $row['featured'],   $event_id );
		update_field( 'seat_map_enabled',    true,               $event_id );
		update_field( 'seat_map_config',     wp_json_encode( $row['seat_map'], JSON_UNESCAPED_UNICODE ), $event_id );

		// Set taxonomies
		if ( $city_id ) {
			wp_set_object_terms( $event_id, $city_id, 'event_city' );
		}
		if ( ! empty( $row['cats'] ) ) {
			$cat_ids = array();
			foreach ( $row['cats'] as $cat_slug ) {
				if ( isset( $cats[ $cat_slug ] ) ) {
					$cat_ids[] = $cats[ $cat_slug ];
				}
			}
			if ( $cat_ids ) {
				wp_set_object_terms( $event_id, $cat_ids, 'event_category' );
			}
		}

		// Set featured image
		$img_id = vatan_irseed_image( $row['image'] );
		if ( $img_id ) {
			set_post_thumbnail( $event_id, $img_id );
		}

		// Create linked WooCommerce ticket product
		vatan_irseed_create_ticket_products( $event_id, $row['tickets'] );

		$created++;
		$log[] = '[event] #' . $event_id . ' — ' . $row['title'];
	}

	$log[] = '[done ] Created ' . $created . ' event(s).';
}

/* =============================================================================
 *  Helper — create WC ticket products linked to an event
 * ===========================================================================*/

function vatan_irseed_create_ticket_products( int $event_id, array $tickets ): void {
	foreach ( $tickets as $ticket ) {
		$product = new WC_Product_Simple();
		$product->set_name( get_the_title( $event_id ) . ' — ' . $ticket['name'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_regular_price( $ticket['price'] );
		$product->set_sold_individually( false );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $ticket['capacity'] );
		$product->set_stock_status( 'instock' );
		$product->set_virtual( false );
		$product->set_sold_individually( false );
		$product->save();

		// Link product to event
		update_post_meta( $product->get_id(), '_vatan_event_id', $event_id );
		update_post_meta( $product->get_id(), '_vatan_ticket_type', $ticket['name'] );
		update_post_meta( $product->get_id(), '_vatan_ticket_color', $ticket['color'] );

		// Update event's ticket_types repeater
		$existing = get_field( 'ticket_types', $event_id );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$existing[] = array(
			'ticket_name'    => $ticket['name'],
			'ticket_price'   => $ticket['price'],
			'ticket_color'   => $ticket['color'],
			'ticket_capacity' => $ticket['capacity'],
			'ticket_sold'    => 0,
		);
		update_field( 'ticket_types', $existing, $event_id );
	}
}
