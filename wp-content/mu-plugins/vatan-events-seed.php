<?php
/**
 * Plugin Name: Vatan Events Reseed
 * Description: One-shot seeder that switches the store currency to GBP, wipes
 *              every existing event + linked WooCommerce ticket product, then
 *              creates a curated set of realistic European-tour events with
 *              full seat plans. Visit /wp-admin/?vatan_seed_events=1 as admin
 *              to run. Delete this file once the demo data is in place.
 */

defined( 'ABSPATH' ) || exit;

/* =============================================================================
 *  Entry point
 * ===========================================================================*/

add_action( 'admin_init', function () {
	if ( empty( $_GET['vatan_seed_events'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	$log = array();
	vatan_eseed_switch_currency( $log );
	vatan_eseed_wipe_events( $log );
	$cities = vatan_eseed_build_city_tree( $log );
	$cats   = vatan_eseed_ensure_categories( $log );
	vatan_eseed_create_events( $cities, $cats, $log );

	wp_die(
		'<h1>Vatan events reseed report</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( implode( "\n", $log ) )
		. '</pre><p><a href="' . esc_url( admin_url( 'edit.php?post_type=event' ) ) . '">→ View events</a></p>',
		'Vatan events seeder',
		array( 'response' => 200 )
	);
} );

/* =============================================================================
 *  Step 1 — switch WooCommerce currency to GBP
 * ===========================================================================*/

function vatan_eseed_switch_currency( array &$log ): void {
	update_option( 'woocommerce_currency', 'GBP' );
	update_option( 'woocommerce_currency_pos', 'left' );
	update_option( 'woocommerce_price_thousand_sep', ',' );
	update_option( 'woocommerce_price_decimal_sep', '.' );
	update_option( 'woocommerce_price_num_decimals', '2' );

	$log[] = '[currency] WooCommerce currency switched to GBP (£, left, 2 decimals).';
}

/* =============================================================================
 *  Step 2 — wipe existing events + linked WC products
 * ===========================================================================*/

function vatan_eseed_wipe_events( array &$log ): void {
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
 *  Step 3 — city tree (Country → City) with flag emojis
 * ===========================================================================*/

function vatan_eseed_build_city_tree( array &$log ): array {
	$tree = array(
		'germany'        => array( 'name' => 'Germany',        'flag' => '🇩🇪', 'cities' => array(
			'berlin'    => 'Berlin',
			'frankfurt' => 'Frankfurt',
			'hamburg'   => 'Hamburg',
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
 *  Step 4 — ensure event_category terms exist
 * ===========================================================================*/

function vatan_eseed_ensure_categories( array &$log ): array {
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

function vatan_eseed_image( string $slug ): int {
	$state = (array) get_option( 'vatan_media_seeded', array() );
	if ( ! empty( $state['attachments'][ 'event:' . $slug ] ) ) {
		return (int) $state['attachments'][ 'event:' . $slug ];
	}
	// Fallback: look it up by post_name (file slug).
	$att = get_page_by_path( $slug, OBJECT, 'attachment' );
	return $att ? (int) $att->ID : 0;
}

/* =============================================================================
 *  Step 5 — the event catalogue
 * ===========================================================================*/

function vatan_eseed_catalogue(): array {
	$today = current_time( 'Y-m-d' );

	return array(

		/* -- 1. Ebi — Royal Albert Hall, London ------------------------- */
		array(
			'title'      => 'کنسرت ابی — لندن',
			'excerpt'    => 'اجرای زنده‌ی ابی همراه با ارکستر بزرگ در رویال آلبرت هال، لندن.',
			'content'    => '<p>ابی، اسطوره پاپ ایرانی، پس از سال‌ها به لندن باز می‌گردد و در سالن افسانه‌ای رویال آلبرت هال، یک شب فراموش‌نشدنی را همراه با ارکستر بزرگ خود اجرا می‌کند.</p><p>برنامه شامل آهنگ‌های کلاسیک «خالی»، «شب نیلوفری» و قطعات جدید آلبوم اخیر است. سالن چیدمان نشسته دارد با بخش‌های ویژه و اقتصادی.</p>',
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
				array( 'name' => 'VIP',      'price' => 120, 'color' => '#FF2D78', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' =>  75, 'color' => '#7C3AED', 'capacity' => 60 ),
				array( 'name' => 'Economy',  'price' =>  45, 'color' => '#3B82F6', 'capacity' => 60 ),
			),
			'seat_map'   => array(
				'rows'     => 12,
				'cols'     => 12,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),                'type' => 'VIP',      'price' => 120, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6, 7 ),       'type' => 'Standard', 'price' =>  75, 'color' => '#7C3AED' ),
					array( 'rows' => array( 8, 9, 10, 11, 12 ),    'type' => 'Economy',  'price' =>  45, 'color' => '#3B82F6' ),
				),
				'reserved' => array( '1-1', '1-12', '2-6', '2-7' ),
				'hallways' => array( '1-6', '1-7', '7-6', '7-7' ),
			),
		),

		/* -- 2. Googoosh — Tempodrom, Berlin ---------------------------- */
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
			'image'      => 'event-microphone',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 95, 'color' => '#FF2D78', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' => 55, 'color' => '#7C3AED', 'capacity' => 72 ),
				array( 'name' => 'Economy',  'price' => 35, 'color' => '#3B82F6', 'capacity' => 72 ),
			),
			'seat_map'   => array(
				'rows'     => 14,
				'cols'     => 12,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),                          'type' => 'VIP',      'price' => 95, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6, 7, 8 ),              'type' => 'Standard', 'price' => 55, 'color' => '#7C3AED' ),
					array( 'rows' => array( 9, 10, 11, 12, 13, 14 ),         'type' => 'Economy',  'price' => 35, 'color' => '#3B82F6' ),
				),
				'reserved' => array( '1-6', '1-7' ),
				'hallways' => array( '8-6', '8-7' ),
			),
		),

		/* -- 3. Homayoun Shajarian — Alte Oper, Frankfurt --------------- */
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
				array( 'name' => 'VIP',      'price' => 100, 'color' => '#FF2D78', 'capacity' => 22 ),
				array( 'name' => 'Standard', 'price' =>  65, 'color' => '#7C3AED', 'capacity' => 44 ),
				array( 'name' => 'Economy',  'price' =>  40, 'color' => '#3B82F6', 'capacity' => 33 ),
			),
			'seat_map'   => array(
				'rows'     => 9,
				'cols'     => 11,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),       'type' => 'VIP',      'price' => 100, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6 ), 'type' => 'Standard', 'price' =>  65, 'color' => '#7C3AED' ),
					array( 'rows' => array( 7, 8, 9 ),    'type' => 'Economy',  'price' =>  40, 'color' => '#3B82F6' ),
				),
				'reserved' => array( '1-1', '1-11', '9-1', '9-11' ),
				'hallways' => array( '4-6', '5-6', '6-6' ),
			),
		),

		/* -- 4. Mohsen Yeganeh — Mehr Theater, Hamburg ------------------ */
		array(
			'title'      => 'کنسرت محسن یگانه — هامبورگ',
			'excerpt'    => 'اجرای زنده‌ی آهنگ‌های آلبوم جدید همراه با هیتس قدیمی.',
			'content'    => '<p>محسن یگانه با یک شو کاملاً متفاوت، سیستم نور و صدای جدید، و گروه نوازندگان حرفه‌ای روی صحنه می‌رود.</p><p>طرفداران می‌توانند آهنگ‌های محبوب «بهت قول می‌دم» و «حباب» را همراه با قطعات تازه‌ی آلبوم اخیر بشنوند.</p>',
			'date'       => '2026-11-08',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'Mehr! Theater am Großmarkt, Banksstraße 28, 20097 Hamburg',
			'lat'        => 53.5408,
			'lng'        => 10.0210,
			'map'        => 'https://maps.google.com/?q=Mehr+Theater+Hamburg',
			'city'       => 'hamburg',
			'cats'       => array( 'concert' ),
			'image'      => 'event-guitar-live',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 85, 'color' => '#FF2D78', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' => 50, 'color' => '#7C3AED', 'capacity' => 60 ),
				array( 'name' => 'Economy',  'price' => 30, 'color' => '#3B82F6', 'capacity' => 60 ),
			),
			'seat_map'   => array(
				'rows'     => 12,
				'cols'     => 12,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),                  'type' => 'VIP',      'price' => 85, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6, 7 ),         'type' => 'Standard', 'price' => 50, 'color' => '#7C3AED' ),
					array( 'rows' => array( 8, 9, 10, 11, 12 ),      'type' => 'Economy',  'price' => 30, 'color' => '#3B82F6' ),
				),
				'reserved' => array( '1-6', '1-7' ),
				'hallways' => array( '7-6', '7-7' ),
			),
		),

		/* -- 5. Dariush — Avicii Arena, Stockholm ----------------------- */
		array(
			'title'      => 'کنسرت داریوش — استکهلم',
			'excerpt'    => 'صدای ماندگار داریوش اقبالی روی صحنه‌ی Avicii Arena استکهلم.',
			'content'    => '<p>داریوش اقبالی، یکی از محبوب‌ترین خوانندگان نسل طلایی، شبی به یاد ماندنی را در پایتخت سوئد اجرا می‌کند.</p><p>برنامه شامل قطعات «بوی عیدی»، «جنگل» و آهنگ‌های کلاسیک پاپ فارسی است.</p>',
			'date'       => '2026-12-04',
			'time_start' => '19:30',
			'time_end'   => '22:30',
			'duration'   => 180,
			'age_limit'  => 0,
			'venue'      => 'Avicii Arena, Globentorget 2, 121 77 Johanneshov, Stockholm',
			'lat'        => 59.2933,
			'lng'        => 18.0834,
			'map'        => 'https://maps.google.com/?q=Avicii+Arena+Stockholm',
			'city'       => 'stockholm',
			'cats'       => array( 'concert' ),
			'image'      => 'event-band-live',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 100, 'color' => '#FF2D78', 'capacity' => 28 ),
				array( 'name' => 'Standard', 'price' =>  60, 'color' => '#7C3AED', 'capacity' => 84 ),
				array( 'name' => 'Economy',  'price' =>  40, 'color' => '#3B82F6', 'capacity' => 84 ),
			),
			'seat_map'   => array(
				'rows'     => 14,
				'cols'     => 14,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),                                    'type' => 'VIP',      'price' => 100, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6, 7, 8 ),                        'type' => 'Standard', 'price' =>  60, 'color' => '#7C3AED' ),
					array( 'rows' => array( 9, 10, 11, 12, 13, 14 ),                   'type' => 'Economy',  'price' =>  40, 'color' => '#3B82F6' ),
				),
				'reserved' => array( '1-1', '1-14', '14-1', '14-14' ),
				'hallways' => array( '1-7', '1-8', '8-7', '8-8' ),
			),
		),

		/* -- 6. Sirvan Khosravi — AFAS Live, Amsterdam ------------------ */
		array(
			'title'      => 'کنسرت سیروان خسروی — آمستردام',
			'excerpt'    => 'سیروان خسروی با آهنگ‌های پر طرفدار، شبی پرانرژی در آمستردام.',
			'content'    => '<p>سیروان خسروی با اجرای آهنگ‌های محبوبش «انتظار» و «همخواب» در یکی از معروف‌ترین سالن‌های هلند می‌خواند.</p><p>پلتفرم نشسته، نور و صدای حرفه‌ای، و یک شب پرانرژی.</p>',
			'date'       => '2026-10-30',
			'time_start' => '21:00',
			'time_end'   => '23:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'AFAS Live, ArenA Boulevard 590, 1101 DS Amsterdam',
			'lat'        => 52.3132,
			'lng'        =>  4.9434,
			'map'        => 'https://maps.google.com/?q=AFAS+Live+Amsterdam',
			'city'       => 'amsterdam',
			'cats'       => array( 'concert' ),
			'image'      => 'event-dj-deck',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'Premium', 'price' => 65, 'color' => '#FF2D78', 'capacity' => 30 ),
				array( 'name' => 'Standard','price' => 40, 'color' => '#7C3AED', 'capacity' => 60 ),
				array( 'name' => 'Economy', 'price' => 25, 'color' => '#3B82F6', 'capacity' => 50 ),
			),
			'seat_map'   => array(
				'rows'     => 10,
				'cols'     => 14,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),                       'type' => 'Premium',  'price' => 65, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6 ),                 'type' => 'Standard', 'price' => 40, 'color' => '#7C3AED' ),
					array( 'rows' => array( 7, 8, 9, 10 ),                'type' => 'Economy',  'price' => 25, 'color' => '#3B82F6' ),
				),
				'reserved' => array(),
				'hallways' => array( '6-7', '6-8' ),
			),
		),

		/* -- 7. Persian Standup Night — O2 Apollo, Manchester ----------- */
		array(
			'title'      => 'شب طنز ایرانی — منچستر',
			'excerpt'    => 'پنج کمدین معروف، یک شب پر از خنده در سبک کاباره با میزهای ویژه.',
			'content'    => '<p>پنج کمدین استندآپ نسل جدید روی صحنه می‌روند: شبی پر از خنده برای کل خانواده.</p>',
			'date'       => '2026-11-22',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 16,
			'venue'      => 'O2 Apollo Manchester, Stockport Rd, Manchester M12 6AP',
			'lat'        => 53.4685,
			'lng'        => -2.2104,
			'map'        => 'https://maps.google.com/?q=O2+Apollo+Manchester',
			'city'       => 'manchester',
			'cats'       => array( 'standup' ),
			'image'      => 'event-festival-fans',
			'featured'   => true,
			'tickets'    => array(
				array( 'name' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'capacity' => 48 ),
				array( 'name' => 'General',   'price' => 20, 'color' => '#3B82F6', 'capacity' => 60 ),
			),
			'seat_map'   => array(
				'rows'     => 5,
				'cols'     => 12,
				'sections' => array(
					array( 'rows' => array( 1, 2, 3, 4, 5 ), 'type' => 'General', 'price' => 20, 'color' => '#3B82F6' ),
				),
				'reserved' => array(),
				'hallways' => array( '3-6', '3-7' ),
				'tables'   => array(
					array( 'id' => 'T1', 'seats' => 6, 'label' => 'VIP 1', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 1 ),
					array( 'id' => 'T2', 'seats' => 6, 'label' => 'VIP 2', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 1 ),
					array( 'id' => 'T3', 'seats' => 6, 'label' => 'VIP 3', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 1 ),
					array( 'id' => 'T4', 'seats' => 6, 'label' => 'VIP 4', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 1 ),
					array( 'id' => 'T5', 'seats' => 6, 'label' => 'VIP 5', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 2 ),
					array( 'id' => 'T6', 'seats' => 6, 'label' => 'VIP 6', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 2 ),
					array( 'id' => 'T7', 'seats' => 6, 'label' => 'VIP 7', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 2 ),
					array( 'id' => 'T8', 'seats' => 6, 'label' => 'VIP 8', 'type' => 'VIP Table', 'price' => 45, 'color' => '#FF2D78', 'row' => 2 ),
				),
			),
		),

		/* -- 8. Niavaran Symphony — Salle Pleyel, Paris ----------------- */
		array(
			'title'      => 'ارکستر سمفونی نیاوران — پاریس',
			'excerpt'    => 'یک شب از موسیقی ارکسترال با قطعاتی از چایکوفسکی، بتهوون و یک قطعه ایرانی-معاصر.',
			'content'    => '<p>ارکستر سمفونی نیاوران پس از تور موفق اروپایی، در سال پلیل پاریس روی صحنه می‌رود.</p>',
			'date'       => '2027-01-18',
			'time_start' => '19:30',
			'time_end'   => '21:30',
			'duration'   => 120,
			'age_limit'  => 0,
			'venue'      => 'Salle Pleyel, 252 Rue du Faubourg Saint-Honoré, 75008 Paris',
			'lat'        => 48.8775,
			'lng'        =>  2.3017,
			'map'        => 'https://maps.google.com/?q=Salle+Pleyel+Paris',
			'city'       => 'paris',
			'cats'       => array( 'classical' ),
			'image'      => 'event-piano-keys',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 90, 'color' => '#FF2D78', 'capacity' => 22 ),
				array( 'name' => 'Standard', 'price' => 55, 'color' => '#7C3AED', 'capacity' => 55 ),
				array( 'name' => 'Economy',  'price' => 30, 'color' => '#3B82F6', 'capacity' => 33 ),
			),
			'seat_map'   => array(
				'rows'     => 10,
				'cols'     => 11,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),          'type' => 'VIP',      'price' => 90, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6, 7 ), 'type' => 'Standard', 'price' => 55, 'color' => '#7C3AED' ),
					array( 'rows' => array( 8, 9, 10 ),      'type' => 'Economy',  'price' => 30, 'color' => '#3B82F6' ),
				),
				'reserved' => array( '1-6', '2-6' ),
				'hallways' => array( '5-6' ),
			),
		),

		/* -- 9. Yas — Palladium, Cologne -------------------------------- */
		array(
			'title'      => 'کنسرت یاس — کلن',
			'excerpt'    => 'یاس، چهره برجسته رپ فارسی، در شهر کلن.',
			'content'    => '<p>یاس با اجرای آهنگ‌های اجتماعی و حماسی خود، شب پرانرژی را در پالادیوم کلن رقم می‌زند.</p>',
			'date'       => '2026-10-12',
			'time_start' => '20:30',
			'time_end'   => '23:00',
			'duration'   => 150,
			'age_limit'  => 14,
			'venue'      => 'Palladium, Schanzenstraße 28, 51063 Köln',
			'lat'        => 50.9456,
			'lng'        =>  6.9712,
			'map'        => 'https://maps.google.com/?q=Palladium+K%C3%B6ln',
			'city'       => 'cologne',
			'cats'       => array( 'concert' ),
			'image'      => 'event-festival-fans',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 55, 'color' => '#FF2D78', 'capacity' => 32 ),
				array( 'name' => 'Standard', 'price' => 35, 'color' => '#7C3AED', 'capacity' => 48 ),
				array( 'name' => 'Economy',  'price' => 25, 'color' => '#3B82F6', 'capacity' => 48 ),
			),
			'seat_map'   => array(
				'rows'     => 8,
				'cols'     => 16,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),     'type' => 'VIP',      'price' => 55, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5 ), 'type' => 'Standard', 'price' => 35, 'color' => '#7C3AED' ),
					array( 'rows' => array( 6, 7, 8 ), 'type' => 'Economy',  'price' => 25, 'color' => '#3B82F6' ),
				),
				'reserved' => array(),
				'hallways' => array( '5-8', '5-9' ),
			),
		),

		/* -- 10. Hamid Hiraad — Gasometer, Vienna ----------------------- */
		array(
			'title'      => 'کنسرت حمید هیراد — وین',
			'excerpt'    => 'حمید هیراد با صدای پر احساس خود، شبی متفاوت در گازومتر وین.',
			'content'    => '<p>حمید هیراد با اجرای آهنگ‌های «جان جان»، «نگاهم کن»، و قطعات جدید آلبوم اخیر در گازومتر وین روی صحنه می‌رود.</p>',
			'date'       => '2026-12-15',
			'time_start' => '20:00',
			'time_end'   => '22:30',
			'duration'   => 150,
			'age_limit'  => 0,
			'venue'      => 'Gasometer, Guglgasse 8, 1110 Wien',
			'lat'        => 48.1865,
			'lng'        => 16.4188,
			'map'        => 'https://maps.google.com/?q=Gasometer+Wien',
			'city'       => 'vienna',
			'cats'       => array( 'concert' ),
			'image'      => 'event-drums-close',
			'featured'   => false,
			'tickets'    => array(
				array( 'name' => 'VIP',      'price' => 75, 'color' => '#FF2D78', 'capacity' => 24 ),
				array( 'name' => 'Standard', 'price' => 45, 'color' => '#7C3AED', 'capacity' => 60 ),
				array( 'name' => 'Economy',  'price' => 30, 'color' => '#3B82F6', 'capacity' => 36 ),
			),
			'seat_map'   => array(
				'rows'     => 10,
				'cols'     => 12,
				'sections' => array(
					array( 'rows' => array( 1, 2 ),                  'type' => 'VIP',      'price' => 75, 'color' => '#FF2D78' ),
					array( 'rows' => array( 3, 4, 5, 6, 7 ),         'type' => 'Standard', 'price' => 45, 'color' => '#7C3AED' ),
					array( 'rows' => array( 8, 9, 10 ),              'type' => 'Economy',  'price' => 30, 'color' => '#3B82F6' ),
				),
				'reserved' => array( '1-1', '1-12' ),
				'hallways' => array( '5-6', '5-7' ),
			),
		),

	);
}

/* =============================================================================
 *  Step 6 — create each event + sync its ticket products
 * ===========================================================================*/

function vatan_eseed_create_events( array $cities, array $cats, array &$log ): void {
	$created = 0;

	foreach ( vatan_eseed_catalogue() as $i => $row ) {
		$post_id = wp_insert_post( array(
			'post_type'    => 'event',
			'post_status'  => 'publish',
			'post_title'   => $row['title'],
			'post_content' => $row['content'],
			'post_excerpt' => $row['excerpt'],
		), true );

		if ( is_wp_error( $post_id ) ) {
			$log[] = '[fail ] event ' . ( $i + 1 ) . ': ' . $post_id->get_error_message();
			continue;
		}

		// ACF fields ------------------------------------------------------
		update_field( 'event_date',           $row['date'],       $post_id );
		update_field( 'event_time_start',     $row['time_start'], $post_id );
		update_field( 'event_time_end',       $row['time_end'],   $post_id );
		update_field( 'event_duration',       $row['duration'],   $post_id );
		update_field( 'event_age_limit',      $row['age_limit'],  $post_id );
		update_field( 'event_venue',          $row['venue'],      $post_id );
		update_field( 'event_venue_map_link', $row['map'],        $post_id );
		update_field( 'event_venue_lat',      $row['lat'],        $post_id );
		update_field( 'event_venue_lng',      $row['lng'],        $post_id );
		update_field( 'event_status',         'on_sale',          $post_id );
		update_field( 'event_is_featured',    ! empty( $row['featured'] ), $post_id );

		// Ticket types repeater ------------------------------------------
		$tickets_for_acf = array();
		foreach ( $row['tickets'] as $t ) {
			$tickets_for_acf[] = array(
				'ticket_name'     => $t['name'],
				'ticket_price'    => $t['price'],
				'ticket_color'    => $t['color'],
				'ticket_capacity' => $t['capacity'],
				'ticket_sold'     => 0,
			);
		}
		update_field( 'ticket_types', $tickets_for_acf, $post_id );

		// Seat map -------------------------------------------------------
		update_field( 'seat_map_enabled', 1,                      $post_id );
		update_field( 'seat_map_rows',    $row['seat_map']['rows'], $post_id );
		update_field( 'seat_map_cols',    $row['seat_map']['cols'], $post_id );
		update_field( 'seat_map_config',  wp_json_encode( $row['seat_map'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), $post_id );

		// Taxonomies -----------------------------------------------------
		if ( ! empty( $cities[ $row['city'] ] ) ) {
			wp_set_post_terms( $post_id, array( (int) $cities[ $row['city'] ] ), 'event_city', false );
		}
		$cat_ids = array();
		foreach ( $row['cats'] as $slug ) {
			if ( ! empty( $cats[ $slug ] ) ) {
				$cat_ids[] = (int) $cats[ $slug ];
			}
		}
		if ( $cat_ids ) {
			wp_set_post_terms( $post_id, $cat_ids, 'event_category', false );
		}

		// Featured image -------------------------------------------------
		$att_id = vatan_eseed_image( $row['image'] );
		if ( $att_id ) {
			set_post_thumbnail( $post_id, $att_id );
		}

		// Trigger WooCommerce ticket product sync ------------------------
		if ( function_exists( 'vatan_sync_event_ticket_products' ) ) {
			vatan_sync_event_ticket_products( $post_id );
		} else {
			do_action( 'save_post_event', $post_id, get_post( $post_id ), true );
		}

		$created++;
		$log[] = '[ok   ] #' . $post_id . ' — ' . $row['title'] . ' (image=' . $att_id . ')';
	}

	$log[] = '[done ] ' . $created . ' event(s) created.';
}

