<?php
/**
 * Vatan Event — theme bootstrap.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

define( 'VATAN_EVENT_VERSION', '1.0.2' );
define( 'VATAN_EVENT_DIR', get_template_directory() );
define( 'VATAN_EVENT_URI', get_template_directory_uri() );

require_once VATAN_EVENT_DIR . '/inc/helpers.php';
require_once VATAN_EVENT_DIR . '/inc/i18n.php'; // Polylang integration — loaded early so post-types/taxonomies are tagged translatable as soon as they're registered.
require_once VATAN_EVENT_DIR . '/inc/custom-post-types.php';
require_once VATAN_EVENT_DIR . '/inc/acf-fields.php';
require_once VATAN_EVENT_DIR . '/inc/music/post-types.php'; // track / album / artist + music_genre. Loaded after the event CPTs so its Polylang filter chains cleanly with the one in inc/i18n.php.
require_once VATAN_EVENT_DIR . '/inc/music/acf-fields.php';
require_once VATAN_EVENT_DIR . '/inc/music/rest-api.php'; // vatan/v1/music/* — public read-only catalog endpoints. Same namespace as the events REST module; both register routes via separate `rest_api_init` hooks.
require_once VATAN_EVENT_DIR . '/inc/music/player.php';   // Render gate + mini-bar bootstrap + asset enqueue. Also defines vatan_is_app_request() (moved here from the deleted vatan-app-radio.php mu-plugin).
require_once VATAN_EVENT_DIR . '/inc/music/admin.php';    // Frontend dashboard: list views + POST handlers (delete / toggle-featured / untrash). Phase 1.
require_once VATAN_EVENT_DIR . '/inc/seat-holds.php'; // Temporary cart-side seat reservations — bridges Add-to-cart → Order with a race-safe UNIQUE table.
require_once VATAN_EVENT_DIR . '/inc/woocommerce.php';
require_once VATAN_EVENT_DIR . '/inc/rest-api.php';
require_once VATAN_EVENT_DIR . '/inc/ajax-handlers.php';
require_once VATAN_EVENT_DIR . '/inc/newsletter.php';
require_once VATAN_EVENT_DIR . '/inc/view-counter.php';
require_once VATAN_EVENT_DIR . '/inc/create-event.php';
require_once VATAN_EVENT_DIR . '/inc/my-events.php';
require_once VATAN_EVENT_DIR . '/inc/earnings.php';
require_once VATAN_EVENT_DIR . '/inc/page-builder.php'; // Component registry + storage + front-end renderer. Loaded everywhere — the admin UI piece lives under /inc/admin/.
require_once VATAN_EVENT_DIR . '/inc/admin-panel.php'; // Self-gates: admin_* hooks only fire in admin; vatan_get_setting() needs to be available on front-end too.
require_once VATAN_EVENT_DIR . '/inc/checkin.php';     // REST /vatan/v1/checkin + the Vatan Event → Door Scanner admin page.
require_once VATAN_EVENT_DIR . '/inc/auth.php';        // Branded /login/ + /signup/ pages, replaces wp-login.php on the front-end.
require_once VATAN_EVENT_DIR . '/inc/admin-dashboard.php'; // Frontend /admin/ dashboard + wp-admin lockout for non-allow-listed users.

function vatan_setup() {
	load_theme_textdomain( 'vatan-event', VATAN_EVENT_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );

	// Custom image sizes for optimized delivery.
	add_image_size( 'vatan-hero', 1920, 900, true );      // Hero slides — crop to 1920×900.
	add_image_size( 'vatan-card', 600, 400, true );       // Event cards — crop to 600×400.
	add_image_size( 'vatan-thumb', 300, 300, true );      // Thumbnails — crop to 300×300.
	add_image_size( 'vatan-album', 400, 400, true );      // Music album art.
	add_image_size( 'vatan-artist', 300, 300, true );     // Artist photos.

	register_nav_menus( array(
		'primary-fa' => __( 'Primary Menu — Persian (RTL)', 'vatan-event' ),
		'primary-en' => __( 'Primary Menu — English (LTR)', 'vatan-event' ),
		'footer'     => __( 'Footer Menu', 'vatan-event' ),
	) );
}
add_action( 'after_setup_theme', 'vatan_setup' );

/**
 * Disable Heartbeat API on frontend pages.
 * Heartbeat serves no purpose on the public site and wastes AJAX requests
 * for logged-in users. Only needed in wp-admin.
 */
function vatan_disable_frontend_heartbeat( $settings ) {
	if ( ! is_admin() ) {
		$settings['interval'] = 0; // Disable entirely.
	}
	return $settings;
}
add_filter( 'heartbeat_settings', 'vatan_disable_frontend_heartbeat' );

/**
 * Add theme-state body classes:
 *   `has-hero`           — page renders a hero unit (controls header transparency).
 *   `vatan-theme--dark`  — default dark color scheme.
 *   `vatan-theme--light` — light color scheme (admin opt-in via Theme Settings).
 */
function vatan_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'has-hero';
	}
	if ( is_singular( 'event' ) && has_post_thumbnail() ) {
		$classes[] = 'has-hero';
	}

	$scheme = function_exists( 'vatan_get_setting' )
		? (string) vatan_get_setting( 'color_scheme', 'dark' )
		: 'dark';
	if ( ! in_array( $scheme, array( 'dark', 'light' ), true ) ) {
		$scheme = 'dark';
	}
	$classes[] = 'vatan-theme--' . $scheme;

	return $classes;
}
add_filter( 'body_class', 'vatan_body_classes' );

function vatan_enqueue_assets() {
	// Vazirmatn + Inter are pulled from Google Fonts via @import at the top of main.css,
	// so the font is loaded from a single source. To switch back to <link>-based loading
	// (slightly faster — parallel fetch instead of chained), re-enqueue 'vatan-fonts' here
	// and remove the @import in main.css.

	wp_enqueue_style( 'vatan-main', VATAN_EVENT_URI . '/assets/css/main.css', array(), VATAN_EVENT_VERSION );
	wp_enqueue_style( 'vatan-components', VATAN_EVENT_URI . '/assets/css/components.css', array( 'vatan-main' ), VATAN_EVENT_VERSION );

	if ( is_rtl() ) {
		wp_enqueue_style( 'vatan-rtl', VATAN_EVENT_URI . '/assets/css/rtl.css', array( 'vatan-main' ), VATAN_EVENT_VERSION );
	}

	wp_enqueue_script( 'vatan-main', VATAN_EVENT_URI . '/assets/js/main.js', array(), VATAN_EVENT_VERSION, true );
	wp_enqueue_script( 'vatan-search', VATAN_EVENT_URI . '/assets/js/search.js', array( 'vatan-main' ), VATAN_EVENT_VERSION, true );
	wp_enqueue_script( 'vatan-animations', VATAN_EVENT_URI . '/assets/js/animations.js', array(), VATAN_EVENT_VERSION, true );

	if ( is_singular( 'event' ) ) {
		wp_enqueue_script( 'vatan-seat-map', VATAN_EVENT_URI . '/assets/js/seat-map.js', array( 'vatan-main' ), VATAN_EVENT_VERSION, true );
	}

	// Create-event form lives on its own page; load the form JS only there.
	if ( is_page( 'create-event' ) ) {
		wp_enqueue_script( 'vatan-create-event', VATAN_EVENT_URI . '/assets/js/create-event.js', array( 'vatan-main' ), VATAN_EVENT_VERSION, true );

		// Seat planner — borrows the admin seat-editor module + its CSS so
		// the planner inside the form looks and behaves the same as the
		// wp-admin Seat Manager. `seat-planner-create.js` bridges the form's
		// live ticket inputs into the editor's tier list.
		wp_enqueue_style(
			'vatan-admin',
			VATAN_EVENT_URI . '/assets/admin/css/admin.css',
			array( 'vatan-main' ),
			VATAN_EVENT_VERSION
		);
		wp_enqueue_script(
			'vatan-seat-editor',
			VATAN_EVENT_URI . '/assets/admin/js/seat-editor.js',
			array(),
			VATAN_EVENT_VERSION,
			true
		);
		wp_enqueue_script(
			'vatan-seat-planner-create',
			VATAN_EVENT_URI . '/assets/js/seat-planner-create.js',
			array( 'vatan-seat-editor' ),
			VATAN_EVENT_VERSION,
			true
		);
	}

	wp_localize_script( 'vatan-main', 'vatanData', array(
		'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
		'restUrl'            => esc_url_raw( rest_url( 'vatan/v1/' ) ),
		'nonce'              => wp_create_nonce( 'vatan_nonce' ),
		'restNonce'          => wp_create_nonce( 'wp_rest' ),
		'isRtl'              => is_rtl(),
		'locale'             => get_locale(),
		'lang'               => function_exists( 'vatan_current_lang' ) ? vatan_current_lang() : '',
		'taxRate'            => (float) apply_filters( 'vatan_tax_rate', 0.09 ),
		'maxSelectableSeats' => (int) apply_filters( 'vatan_max_selectable_seats', 10 ),
		'currencySymbol'     => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '',
		'currencyPosition'   => get_option( 'woocommerce_currency_pos', 'left' ),
		'priceDecimals'      => (int) get_option( 'woocommerce_price_num_decimals', 2 ),
		'i18n'               => array(
			// Search.
			'searching'       => __( 'Searching…', 'vatan-event' ),
			'noResults'       => __( 'No events found.', 'vatan-event' ),
			'searchFailed'    => __( 'Search failed. Please try again.', 'vatan-event' ),
			// Seat map.
			'seatReserved'    => __( 'Reserved', 'vatan-event' ),
			'seatInCart'      => __( 'In your cart', 'vatan-event' ),
			'seatPanelEmpty'  => __( 'No seats selected yet.', 'vatan-event' ),
			'seatRow'         => __( 'Row', 'vatan-event' ),
			'seatRemove'      => __( 'Remove', 'vatan-event' ),
			'seatLoadFailed'  => __( 'Could not load seat map.', 'vatan-event' ),
			'seatNone'        => __( 'No seat map configured for this event.', 'vatan-event' ),
			/* translators: %d: maximum number of seats that can be selected */
			'seatMaxReached'  => __( 'You can select up to %d seats.', 'vatan-event' ),
			'seatEconomy'     => __( 'Economy', 'vatan-event' ),
			'seatSpecial'     => __( 'Special (CIP)', 'vatan-event' ),
			'addToCartFailed' => __( 'Could not add tickets to cart.', 'vatan-event' ),
			'addToCartOk'     => __( 'Tickets reserved.', 'vatan-event' ),
			'working'         => __( 'Working…', 'vatan-event' ),
			// Newsletter.
			'newsletterBadEmail' => __( 'Please enter a valid email address.', 'vatan-event' ),
			'newsletterError'    => __( 'Could not subscribe — please try again.', 'vatan-event' ),
			'newsletterNetwork'  => __( 'Network error. Please try again.', 'vatan-event' ),
		),
	) );
}
add_action( 'wp_enqueue_scripts', 'vatan_enqueue_assets' );

function vatan_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Primary Sidebar', 'vatan-event' ),
		'id'            => 'sidebar-primary',
		'description'   => __( 'Default sidebar shown on standard archives.', 'vatan-event' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget__title">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'vatan_widgets_init' );
