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
		'germany'        => array( 'name' => 'Germany',        'flag' => '🇩🇪', 'cities' => array(
			'berlin'    => 'Berlin',
			'frankfurt' => 'Frankfurt',
			'cologne'   => 'Cologne',
		) ),
		'united-kingdom' => array( 'name' => 'United Kingdom', 'flag' => '🇬🇧', 'cities' => array(
			'london'     => 'London',
			'manchester' => 'Manchester',
		) ),
		'sweden'         => array( 'name' => 'Sweden',         'flag' => '🇸🇪', 'cities' => array(
			'stockholm' => 'Stockholm',
		) ),
		'netherlands'    => array( 'name' => 'Netherlands',    'flag' => '🇳🇱', 'cities' => array(
			'amsterdam' => 'Amsterdam',
		) ),
		'france'         => array( 'name' => 'France',         'flag' => '🇫🇷', 'cities' => array(
			'paris' => 'Paris',
		) ),
		'austria'        => array( 'name' => 'Austria',        'flag' => '🇦🇹', 'cities' => array(
			'vienna' => 'Vienna',
		) ),
		'belgium'         => array( 'name' => 'Belgium',         'flag' => '🇧🇪', 'cities' => array(
			'brussels' => 'Brussels',
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

		/* ── 1. Ebi — Royal Albert Hall, London (grid) ──────────────── */
		array(
			'title'      => 'کنسرت ابی — لندن',
			'excerpt'    => 'اجرای زنده‌ی ابی همراه با ارکستر بزرگ در رویال آلبرت هال، لندن.',
			'content'    => '<p>ابی، اسطوره پاپ ایرانی، پس از سال‌ها به لندن باز می‌گردد و در سالن افسانه‌ای رویال آلبرت هال، یک شب فراموش‌نشدنی را همراه با ارکستر بزرگ خود اجرا می‌کند.</p><p>برنامه شامل آهنگ‌های کلاسیک «خالی»، «شب نیلوفری» و قطعات جدید آلبوم اخیر است.</p>',
			'date'       => '2026-11-15',
			'time_start' => '20:00',
			'time_end'   => '23:00',
			'duration'   => 180,
			'age_limit'  => 12,
			'venue'      => 'Royal Albert Hall, Kensington Gore, London SW7 2AP',
			'lat'        => 51.5009,
			'lng'        => -0.1773,
			'map'        => 'https://maps.google.com/?q=Royal+Albert+Hall+London',
			'city'       => 'london',
			'cats'       => array( 'concert' ),
			'image'      => 'event-singer-stage',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 120, 'color' => '#F59E0B', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' => 75,  'color' => '#7C3AED', 'capacity' => 60 ),
				array( 'name' => 'Economy',  'price' => 45,  'color' => '#3B82F6', 'capacity' => 60 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 12, 12, array(
				array( 'rows' => array( 1, 2 ),                'type' => 'VIP',      'price' => 120, 'color' => '#F59E0B' ),
				array( 'rows' => array( 3, 4, 5, 6, 7 ),       'type' => 'Standard', 'price' => 75,  'color' => '#7C3AED' ),
				array( 'rows' => array( 8, 9, 10, 11, 12 ),    'type' => 'Economy',  'price' => 45,  'color' => '#3B82F6' ),
			), array( '1-6', '1-7', '7-6', '7-7' ) ),
		),

		/* ── 2. Googoosh — Tempodrom, Berlin (round tables) ─────────── */
		array(
			'title'      => 'کنسرت گوگوش — برلین',
			'excerpt'    => 'بانوی موسیقی پاپ ایرانی، گوگوش، روی صحنه‌ی تمپودروم برلین.',
			'content'    => '<p>گوگوش با اجرای آهنگ‌های مشهور خود از دهه‌های ۷۰ تا امروز، در یکی از معروف‌ترین سالن‌های آلمان روی صحنه می‌رود.</p><p>گروه نوازندگان حرفه‌ای و سیستم نور و صدای پیشرفته، تجربه‌ای ویژه برای علاقه‌مندان رقم می‌زنند.</p>',
			'date'       => '2026-10-22',
			'time_start' => '19:30',
			'time_end'   => '22:30',
			'duration'   => 180,
			'age_limit'  => 0,
			'venue'      => 'Tempodrom, Möckernstraße 10, 10963 Berlin',
			'lat'        => 52.5040,
			'lng'        => 13.3796,
			'map'        => 'https://maps.google.com/?q=Tempodrom+Berlin',
			'city'       => 'berlin',
			'cats'       => array( 'concert' ),
			'image'      => 'event-dj-deck',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 95,  'color' => '#F59E0B', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' => 55,  'color' => '#7C3AED', 'capacity' => 72 ),
				array( 'name' => 'Economy',  'price' => 35,  'color' => '#3B82F6', 'capacity' => 72 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 8, 6, 32 ),
		),

		/* ── 3. Homayoun Shajarian — Alte Oper, Frankfurt (grid) ─────── */
		array(
			'title'      => 'شب موسیقی سنتی — همایون شجریان، فرانکفورت',
			'excerpt'    => 'یک شب از موسیقی اصیل ایرانی با همایون شجریان در آلته اوپر فرانکفورت.',
			'content'    => '<p>همایون شجریان به همراه گروه نوازندگان موسیقی سنتی، شبی از غزل‌خوانی و آواز ایرانی را در سالن کلاسیک آلته اوپر فرانکفورت اجرا می‌کند.</p><p>قطعاتی از حافظ، سعدی و مولوی همراه با تنبک، تار و کمانچه.</p>',
			'date'       => '2026-09-28',
			'time_start' => '19:00',
			'time_end'   => '21:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'Alte Oper, Opernplatz 1, 60313 Frankfurt am Main',
			'lat'        => 50.1156,
			'lng'        =>  8.6724,
			'map'        => 'https://maps.google.com/?q=Alte+Oper+Frankfurt',
			'city'       => 'frankfurt',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-classical-hall',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 100, 'color' => '#F59E0B', 'capacity' => 22 ),
				array( 'name' => 'Standard', 'price' => 65,  'color' => '#7C3AED', 'capacity' => 44 ),
				array( 'name' => 'Economy',  'price' => 40,  'color' => '#3B82F6', 'capacity' => 33 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 9, 10, array(
				array( 'rows' => array( 1, 2 ),                'type' => 'VIP',      'price' => 100, 'color' => '#F59E0B' ),
				array( 'rows' => array( 3, 4, 5, 6, 7, 8, 9 ), 'type' => 'Standard', 'price' => 65,  'color' => '#7C3AED' ),
			), array( '1-5', '1-6', '5-5', '5-6' ) ),
		),

		/* ── 4. Mehdi Yarrahi — Ziggo Dome, Amsterdam (round tables) ── */
		array(
			'title'      => 'کنسرت مهدی یarraهی — آمستردام',
			'excerpt'    => 'مهدی یarraهی در زیگو دوم آمستردام.',
			'content'    => '<p>مهدی یarraهی، خواننده‌ی محبوب پاپ راک، در زیگو دوم آمستردام اجرا می‌کند.</p><p>آهنگ‌های محبوب و قطعات جدید از آلبوم آخر.</p>',
			'date'       => '2026-10-10',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 15,
			'venue'      => 'Ziggo Dome, De Dongel 9, 1101 BH Amsterdam',
			'lat'        => 52.3125,
			'lng'        => 4.9375,
			'map'        => 'https://maps.google.com/?q=Ziggo+Dome+Amsterdam',
			'city'       => 'amsterdam',
			'cats'       => array( 'concert' ),
			'image'      => 'event-band-live',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 85,  'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 50,  'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 30,  'color' => '#3B82F6', 'capacity' => 36 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 6, 16 ),
		),

		/* ── 5. Shajarian Jr — Bridgewater Hall, Manchester (grid) ──── */
		array(
			'title'      => 'شب آواز ایرانی — منچستر',
			'excerpt'    => 'شبی از آواز ایرانی در بریج‌واتر هال منچستر.',
			'content'    => '<p>بریج‌واتر هال منچستر میزبان شبی از آواز و موسیقی ایرانی با هنرمندان بنام است.</p><p>اجراهای زنده با سازهای سنتی ایرانی.</p>',
			'date'       => '2026-09-25',
			'time_start' => '19:30',
			'time_end'   => '22:00',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'Bridgewater Hall, Manchester M2 3DE',
			'lat'        => 53.4758,
			'lng'        => -2.2487,
			'map'        => 'https://maps.google.com/?q=Bridgewater+Hall+Manchester',
			'city'       => 'manchester',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-piano-keys',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 75,  'color' => '#F59E0B', 'capacity' => 18 ),
				array( 'name' => 'Standard', 'price' => 45,  'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 25,  'color' => '#3B82F6', 'capacity' => 42 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 9, 10, array(
				array( 'rows' => array( 1, 2 ),                'type' => 'VIP',      'price' => 75,  'color' => '#F59E0B' ),
				array( 'rows' => array( 3, 4, 5 ),             'type' => 'Standard', 'price' => 45,  'color' => '#7C3AED' ),
				array( 'rows' => array( 6, 7, 8, 9 ),          'type' => 'Economy',  'price' => 25,  'color' => '#3B82F6' ),
			), array( '1-5', '1-6', '5-5', '5-6' ) ),
		),

		/* ── 6. Pishro — Avicii Arena, Stockholm (round tables) ────── */
		array(
			'title'      => 'کنسرت پیشرو — استکهلم',
			'excerpt'    => 'گروه پیشرو در آویچی آرنا استکهلم.',
			'content'    => '<p>گروه پیشرو با اجرایی پرانرژی در آویچی آرنا استکهلم روی صحنه می‌رود.</p><p>آهنگ‌های راک و هیپ‌هاپ با سیستم صوتی حرفه‌ای.</p>',
			'date'       => '2026-10-18',
			'time_start' => '21:00',
			'time_end'   => '23:30',
			'duration'   => 150,
			'age_limit'  => 18,
			'venue'      => 'Avicii Arena, Globentorget 2, 121 77 Johanneshov',
			'lat'        => 59.2936,
			'lng'        => 18.0830,
			'map'        => 'https://maps.google.com/?q=Avicii+Arena+Stockholm',
			'city'       => 'stockholm',
			'cats'       => array( 'concert' ),
			'image'      => 'event-festival-fans',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 90,  'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 55,  'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 30,  'color' => '#3B82F6', 'capacity' => 48 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 4, 6, 16 ),
		),

		/* ── 7. Aref — Theater an der Wien, Vienna (grid) ───────────── */
		array(
			'title'      => 'نمایش موزیکال ایرانی — وین',
			'excerpt'    => 'نمایش موزیکال در تئاتر آندر وین.',
			'content'    => '<p>تئاتر آندر وین میزبان یک نمایش موزیکال با بازیگران بنام تئاتر ایران است.</p><p>ترکیبی از موسیقی زنده و بازیگری حرفه‌ای.</p>',
			'date'       => '2026-10-01',
			'time_start' => '18:00',
			'time_end'   => '20:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'Theater an der Wien, Operngasse 2, 1010 Wien',
			'lat'        => 48.2036,
			'lng'        => 16.3698,
			'map'        => 'https://maps.google.com/?q=Theater+an+der+Wien+Vienna',
			'city'       => 'vienna',
			'cats'       => array( 'theater' ),
			'image'      => 'event-theater-curtain',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 80,  'color' => '#F59E0B', 'capacity' => 12 ),
				array( 'name' => 'Standard', 'price' => 50,  'color' => '#7C3AED', 'capacity' => 36 ),
				array( 'name' => 'Economy',  'price' => 25,  'color' => '#3B82F6', 'capacity' => 42 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 8, 10, array(
				array( 'rows' => array( 1 ),                    'type' => 'VIP',      'price' => 80,  'color' => '#F59E0B' ),
				array( 'rows' => array( 2, 3, 4 ),              'type' => 'Standard', 'price' => 50,  'color' => '#7C3AED' ),
				array( 'rows' => array( 5, 6, 7, 8 ),           'type' => 'Economy',  'price' => 25,  'color' => '#3B82F6' ),
			), array( '2-5', '2-6', '5-5', '5-6' ) ),
		),

		/* ── 8. Standup night — AFAS Live, Amsterdam (round tables) ── */
		array(
			'title'      => 'شب استندآپ ایرانی — آمستردام',
			'excerpt'    => 'شبی پر از خنده با کمدین‌های محبوب ایرانی.',
			'content'    => '<p>ایونت‌هال آمستردام میزبان شبی استندآپ کمدی با حضور کمدین‌های محبوب ایرانی است.</p><p>اجراهای ۱۵ دقیقه‌ای با موضوعات متنوع.</p>',
			'date'       => '2026-09-30',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 16,
			'venue'      => 'AFAS Live, de Passage 10, 1101 AX Amsterdam',
			'lat'        => 52.3130,
			'lng'        => 4.9370,
			'map'        => 'https://maps.google.com/?q=AFAS+Live+Amsterdam',
			'city'       => 'amsterdam',
			'cats'       => array( 'standup' ),
			'image'      => 'event-guitar-live',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 55,  'color' => '#F59E0B', 'capacity' => 8 ),
				array( 'name' => 'Standard', 'price' => 35,  'color' => '#7C3AED', 'capacity' => 32 ),
				array( 'name' => 'Economy',  'price' => 20,  'color' => '#3B82F6', 'capacity' => 32 ),
			),
			'seat_map'   => vatan_irseed_round_table_map( 5, 6, 20 ),
		),

		/* ── 9. Fajr Festival — Philharmonie, Cologne (grid) ─────────── */
		array(
			'title'      => 'جشنواره موسیقی فجر — کلن',
			'excerpt'    => 'شب ویژه جشنواره موسیقی فجر در فیلارمونی کلن.',
			'content'    => '<p>فیلارمونی کلن میزبان شب ویژه جشنواره موسیقی فجر با اجرای بهترین‌های موسیقی ایرانی است.</p><p>اجراهای ویژه با هنرمندان بین‌المللی.</p>',
			'date'       => '2026-11-01',
			'time_start' => '19:00',
			'time_end'   => '23:00',
			'duration'   => 240,
			'age_limit'  => 0,
			'venue'      => 'Kölner Philharmonie, Bismarckstraße 37, 50672 Köln',
			'lat'        => 50.9413,
			'lng'        => 6.9583,
			'map'        => 'https://maps.google.com/?q=Koelner+Philharmonie',
			'city'       => 'cologne',
			'cats'       => array( 'festival' ),
			'image'      => 'event-hero-concert-crowd',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 110, 'color' => '#F59E0B', 'capacity' => 20 ),
				array( 'name' => 'Standard', 'price' => 70,  'color' => '#7C3AED', 'capacity' => 80 ),
				array( 'name' => 'Economy',  'price' => 40,  'color' => '#3B82F6', 'capacity' => 80 ),
			),
			'seat_map'   => vatan_irseed_grid_map( 12, 14, array(
				array( 'rows' => array( 1, 2, 3 ),              'type' => 'VIP',      'price' => 110, 'color' => '#F59E0B' ),
				array( 'rows' => array( 4, 5, 6, 7, 8 ),        'type' => 'Standard', 'price' => 70,  'color' => '#7C3AED' ),
				array( 'rows' => array( 9, 10, 11, 12 ),         'type' => 'Economy',  'price' => 40,  'color' => '#3B82F6' ),
			), array( '1-7', '1-8', '5-7', '5-8', '9-7', '9-8' ) ),
		),

		/* ── 10. Traditional music night — Cercle Royal Gaulois, Brussels ── */
		array(
			'title'      => 'شب موسیقی سنتی ایرانی — بروکسل',
			'excerpt'    => 'شبی از موسیقی اصیل ایرانی در سیرکل رویال گلوآ بروکسل.',
			'content'    => '<p>سیرکل رویال گلوآ بروکسل میزبان شبی از موسیقی اصیل ایرانی با هنرمندان بنام است.</p><p>اجراهای زنده با سازهای سنتی ایرانی.</p>',
			'date'       => '2026-10-15',
			'time_start' => '19:00',
			'time_end'   => '21:00',
			'duration'   => 120,
			'age_limit'  => 0,
			'venue'      => 'Cercle Royal Gaulois, Rue de la Régence 3, 1000 Bruxelles',
			'lat'        => 50.8410,
			'lng'        => 4.3556,
			'map'        => 'https://maps.google.com/?q=Cercle+Royal+Gaulois+Brussels',
			'city'       => 'brussels',
			'cats'       => array( 'traditional-music' ),
			'image'      => 'event-microphone',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 65,  'color' => '#F59E0B', 'capacity' => 8 ),
				array( 'name' => 'Standard', 'price' => 40,  'color' => '#7C3AED', 'capacity' => 32 ),
				array( 'name' => 'Economy',  'price' => 20,  'color' => '#3B82F6', 'capacity' => 40 ),
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
		// Set rows/cols from seat map data (0 for round-table-only maps).
		$sm_rows = isset( $row['seat_map']['rows'] ) ? (int) $row['seat_map']['rows'] : 0;
		$sm_cols = isset( $row['seat_map']['cols'] ) ? (int) $row['seat_map']['cols'] : 0;
		update_field( 'seat_map_rows', $sm_rows, $event_id );
		update_field( 'seat_map_cols', $sm_cols, $event_id );

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
