<?php
/**
 * Theme helper functions.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a URL to a theme asset.
 *
 * @param string $relative_path Path relative to /assets/, e.g. 'css/main.css'.
 * @return string
 */
function vatan_asset( $relative_path ) {
	return get_template_directory_uri() . '/assets/' . ltrim( $relative_path, '/' );
}

/**
 * Whether the current request is for the event CPT in any form.
 *
 * @return bool
 */
function vatan_is_event_context() {
	return is_singular( 'event' )
		|| is_post_type_archive( 'event' )
		|| is_tax( array( 'event_category', 'event_city' ) );
}

/**
 * Pick the menu location for the current request — language-keyed.
 *
 * Maps the active locale's language code to the matching menu location
 * (`primary-fa` or `primary-en`). Falls back to `primary-fa` when the
 * locale is something we don't have a menu for.
 *
 * @return string
 */
function vatan_current_menu_location() {
	$lang = substr( get_locale(), 0, 2 );
	return 'primary-' . ( in_array( $lang, array( 'fa', 'en' ), true ) ? $lang : 'fa' );
}

/**
 * Render a default primary menu when the location has no menu assigned.
 * Used as `wp_nav_menu`'s `fallback_cb` so the header still looks alive
 * before an admin has built a menu in Appearance → Menus.
 */
function vatan_default_primary_menu( $args = array() ) {
	$archive_url = get_post_type_archive_link( 'event' );
	$archive_url = is_string( $archive_url ) ? $archive_url : home_url( '/' );

	$resolve_term_url = static function ( $slug ) use ( $archive_url ) {
		$term = get_term_by( 'slug', $slug, 'event_category' );
		if ( ! $term ) {
			return $archive_url;
		}
		$url = get_term_link( $term );
		return is_string( $url ) ? $url : $archive_url;
	};

	$create_url  = function_exists( 'vatan_static_page_url' ) ? ( vatan_static_page_url( 'create-event' ) ?: home_url( '/create-event/' ) ) : home_url( '/create-event/' );
	$support_url = function_exists( 'vatan_static_page_url' ) ? ( vatan_static_page_url( 'support' )     ?: home_url( '/support/' )    ) : home_url( '/support/' );
	// "Blog" page (set as WP's Posts page) renders the post listing via home.php.
	$blog_url = function_exists( 'vatan_static_page_url' ) ? (string) vatan_static_page_url( 'blog' ) : '';
	if ( ! $blog_url ) {
		$blog_url = home_url( '/blog/' );
	}

	$items = array(
		array( 'label' => __( 'Home', 'vatan-event' ),         'url' => home_url( '/' ) ),
		array( 'label' => __( 'Events', 'vatan-event' ),       'url' => $archive_url ),
		array( 'label' => __( 'Concerts', 'vatan-event' ),     'url' => $resolve_term_url( 'concert' ) ),
		array( 'label' => __( 'Create event', 'vatan-event' ), 'url' => $create_url ),
		array( 'label' => __( 'Guides', 'vatan-event' ),       'url' => $blog_url ),
		array( 'label' => __( 'Support', 'vatan-event' ),      'url' => $support_url ),
	);

	// WordPress passes the original wp_nav_menu args to the fallback
	// (as an array or stdClass depending on context). Respect the caller's
	// menu_class so the drawer gets `.drawer__menu` instead of the desktop
	// `.main-nav__menu` (the latter is a horizontal flex layout and looks
	// broken in a vertical drawer).
	$args = is_object( $args ) ? (array) $args : (array) $args;
	$menu_class = isset( $args['menu_class'] ) && is_string( $args['menu_class'] )
		? $args['menu_class']
		: 'main-nav__menu';

	// Derive item / link BEM classes from the menu_class base when possible
	// (e.g. menu_class="drawer__menu" → li="drawer__item", a="drawer__link"),
	// falling back to the main-nav names so existing CSS keeps applying.
	$base       = preg_replace( '/__.*/', '', $menu_class );
	$item_class = $base ? $base . '__item' : 'main-nav__item';
	$link_class = $base ? $base . '__link' : 'main-nav__link';

	echo '<ul class="' . esc_attr( $menu_class ) . '">';
	foreach ( $items as $item ) {
		$url = ( is_string( $item['url'] ) && $item['url'] ) ? $item['url'] : '#';
		printf(
			'<li class="%s"><a class="%s" href="%s">%s</a></li>',
			esc_attr( $item_class ),
			esc_attr( $link_class ),
			esc_url( $url ),
			esc_html( $item['label'] )
		);
	}
	echo '</ul>';
}

/**
 * Build a homepage URL for the given language code.
 *
 * Integrates with Polylang or WPML if either is active; otherwise falls back
 * to a `?lang=…` query parameter on the homepage URL so the link is at least
 * present and unique per language.
 *
 * @param string $lang Two-letter language code (e.g. 'fa', 'en').
 * @return string
 */
function vatan_lang_url( $lang ) {
	if ( function_exists( 'pll_home_url' ) ) {
		// Try to find a translation of the currently-queried object first
		// (e.g. switching languages on a single-event page should land on
		// the translated event, not the home page). Falls back to the
		// language's home URL when no translation exists.
		$queried_id = get_queried_object_id();
		if ( $queried_id && function_exists( 'pll_get_post' ) ) {
			$translated = pll_get_post( $queried_id, $lang );
			if ( $translated ) {
				$url = get_permalink( $translated );
				if ( $url ) {
					return $url;
				}
			}
		}
		if ( $queried_id && function_exists( 'pll_get_term' ) && is_tax() ) {
			$translated = pll_get_term( $queried_id, $lang );
			if ( $translated ) {
				$url = get_term_link( $translated );
				if ( ! is_wp_error( $url ) ) {
					return $url;
				}
			}
		}
		return pll_home_url( $lang );
	}
	if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		return apply_filters( 'wpml_permalink', home_url( '/' ), $lang );
	}
	return add_query_arg( 'lang', $lang, home_url( '/' ) );
}

/**
 * Convert ASCII digits to Persian-Indic digits (۰-۹) when the active locale
 * is Persian; pass-through otherwise. Use for any number rendered into UI:
 * counts, prices, dates.
 *
 * @param int|string $value
 * @return string
 */
function vatan_to_persian_digits( $value ) {
	$value = (string) $value;
	if ( strpos( get_locale(), 'fa' ) !== 0 ) {
		return $value;
	}
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return str_replace( $en, $fa, $value );
}

/**
 * Brand color for an event_category term, keyed by slug. Falls back to the
 * primary brand color for categories not in the map.
 *
 * @param string $slug
 * @return string
 */
function vatan_event_category_color( $slug ) {
	$colors = array(
		'concert'           => '#FF2D78', // pink
		'theater'           => '#7C3AED', // purple
		'traditional-music' => '#06B6D4', // cyan
		'standup'           => '#F59E0B', // amber
		'festival'          => '#10B981', // green
	);
	return isset( $colors[ $slug ] ) ? $colors[ $slug ] : '#FF2D78';
}

/**
 * Format a numeric amount for display in event UI.
 *
 * Pulls the currency symbol and position from WooCommerce so prices match
 * WC's own formatting. Falls back to a bare number when WC isn't loaded.
 *
 * @param int|float $amount
 * @return string
 */
function vatan_format_price( $amount ) {
	$decimals = function_exists( 'get_option' ) ? (int) get_option( 'woocommerce_price_num_decimals', 2 ) : 2;
	$num      = number_format_i18n( (float) $amount, $decimals );
	$num      = vatan_to_persian_digits( $num );

	if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
		return $num;
	}

	$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
	$pos    = get_option( 'woocommerce_currency_pos', 'left' );

	switch ( $pos ) {
		case 'right':       return $num . $symbol;
		case 'left_space':  return $symbol . ' ' . $num;
		case 'right_space': return $num . ' ' . $symbol;
		default:            return $symbol . $num; // 'left'
	}
}

/**
 * Days remaining until an event's date. Returns null when the date is unset
 * or in the past.
 *
 * @param int $post_id
 * @return int|null
 */
function vatan_event_days_left( $post_id ) {
	$date = function_exists( 'get_field' )
		? (string) get_field( 'event_date', $post_id )
		: (string) get_post_meta( $post_id, 'event_date', true );
	if ( ! $date ) {
		return null;
	}
	$ts = strtotime( $date );
	if ( ! $ts ) {
		return null;
	}
	$days = (int) floor( ( $ts - time() ) / DAY_IN_SECONDS );
	return $days >= 0 ? $days : null;
}

/**
 * Lowest non-zero price across an event's `ticket_types` repeater. Returns
 * null when no priced tier exists (e.g. ACF Pro absent, or event not priced).
 *
 * @param int $post_id
 * @return float|null
 */
function vatan_event_starting_price( $post_id ) {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}
	$tiers = get_field( 'ticket_types', $post_id );
	if ( ! is_array( $tiers ) || empty( $tiers ) ) {
		return null;
	}
	$min = null;
	foreach ( $tiers as $tier ) {
		$price = isset( $tier['ticket_price'] ) ? (float) $tier['ticket_price'] : 0.0;
		if ( $price > 0 && ( null === $min || $price < $min ) ) {
			$min = $price;
		}
	}
	return $min;
}

/**
 * Convert a Gregorian Y/M/D triplet to its Jalali (Solar Hijri) equivalent.
 *
 * Public-domain algorithm — the standard one used by jdf / jdatetime libs.
 *
 * @param int $gy Gregorian year.
 * @param int $gm Gregorian month (1-12).
 * @param int $gd Gregorian day (1-31).
 * @return int[] Three-element list [jy, jm, jd].
 */
function vatan_gregorian_to_jalali( $gy, $gm, $gd ) {
	$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
	if ( $gy <= 1600 ) {
		$jy  = 0;
		$gy -= 621;
	} else {
		$jy  = 979;
		$gy -= 1600;
	}
	$gy2  = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days = ( 365 * $gy ) + (int) ( ( $gy2 + 3 ) / 4 ) - (int) ( ( $gy2 + 99 ) / 100 )
			+ (int) ( ( $gy2 + 399 ) / 400 ) - 80 + $gd + $g_d_m[ $gm - 1 ];
	$jy  += 33 * (int) ( $days / 12053 );
	$days = $days % 12053;
	$jy  += 4 * (int) ( $days / 1461 );
	$days = $days % 1461;
	if ( $days > 365 ) {
		$jy  += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}
	if ( $days < 186 ) {
		$jm = 1 + (int) ( $days / 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + (int) ( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}
	return array( $jy, $jm, $jd );
}

/**
 * Format a Y-m-d Gregorian date as a Jalali string with Persian digits and
 * Persian month names — e.g. "21 آبان 1403".
 *
 * @param string $date Y-m-d Gregorian date.
 * @return string
 */
function vatan_jalali_format( $date ) {
	$ts = strtotime( $date );
	if ( ! $ts ) {
		return '';
	}
	list( $jy, $jm, $jd ) = vatan_gregorian_to_jalali(
		(int) gmdate( 'Y', $ts ),
		(int) gmdate( 'n', $ts ),
		(int) gmdate( 'j', $ts )
	);
	$months = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);
	return vatan_to_persian_digits( $jd ) . ' ' . $months[ $jm ] . ' ' . vatan_to_persian_digits( $jy );
}

/**
 * Locale-aware event date display. Renders Jalali under Persian locales,
 * the WP `date_format` option Gregorian otherwise.
 *
 * @param string $date Y-m-d Gregorian date.
 * @return string
 */
function vatan_event_date_display( $date ) {
	if ( ! $date ) {
		return '';
	}
	if ( strpos( get_locale(), 'fa' ) === 0 ) {
		return vatan_jalali_format( $date );
	}
	$ts = strtotime( $date );
	return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '';
}

/**
 * Map an event_status value (upcoming|ongoing|finished|cancelled) to a
 * display label + visual state slug used by the .sale-status--{state} CSS.
 *
 * @param string $status
 * @return array{label:string,state:string}
 */
function vatan_event_status_meta( $status ) {
	$map = array(
		'upcoming'  => array( 'label' => __( 'On sale', 'vatan-event' ),      'state' => 'active' ),
		'ongoing'   => array( 'label' => __( 'In progress', 'vatan-event' ),  'state' => 'live' ),
		'finished'  => array( 'label' => __( 'Finished', 'vatan-event' ),     'state' => 'finished' ),
		'cancelled' => array( 'label' => __( 'Cancelled', 'vatan-event' ),    'state' => 'cancelled' ),
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : array(
		'label' => __( 'On sale', 'vatan-event' ),
		'state' => 'active',
	);
}

/**
 * Render the language switcher.
 *
 * When Polylang (or WPML) is active, lists every configured language and
 * uses each plugin's own URL resolver to translate the link. Without a
 * multilingual plugin it falls back to a static FA/EN pair pointing at
 * the same URL — useful for sites that aren't multilingual yet.
 *
 * Each link gets `lang-switcher__link--active` and `aria-current="true"`
 * when it's the current language.
 */
/**
 * Render the site logo as a link to the homepage.
 *
 * Lookup order for an actual logo image:
 *   1. Vatan Event → Theme Settings → Site Identity → Logo (`logo_id`).
 *   2. WordPress core's Site Identity → Logo (custom-logo theme support).
 *   3. Fall back to the stylized "V" icon + the site name wordmark.
 *
 * @param string $size 'lg' (header), 'sm' (drawer), or 'footer'. Just a
 *                     CSS modifier class — actual dimensions live in CSS.
 */
function vatan_render_site_logo( string $size = 'lg' ): void {
	$logo_id = function_exists( 'vatan_get_setting' ) ? (int) vatan_get_setting( 'logo_id' ) : 0;
	if ( ! $logo_id ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
	}

	$mods = 'logo--' . sanitize_html_class( $size );

	if ( $logo_id ) {
		$img = wp_get_attachment_image( $logo_id, 'medium', false, array(
			'class' => 'logo__img',
			'alt'   => get_bloginfo( 'name' ),
		) );
		if ( $img ) {
			printf(
				'<a class="logo logo--image %s" href="%s" aria-label="%s">%s</a>',
				esc_attr( $mods ),
				esc_url( home_url( '/' ) ),
				esc_attr( get_bloginfo( 'name' ) ),
				$img // already escaped by wp_get_attachment_image
			);
			return;
		}
	}

	// Fallback: stylized V icon + site name.
	?>
	<a class="logo <?php echo esc_attr( $mods ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<span class="logo-icon" aria-hidden="true">V</span>
		<span class="logo-text"><?php bloginfo( 'name' ); ?></span>
	</a>
	<?php
}

function vatan_render_language_switcher() {
	$current = function_exists( 'vatan_current_lang' ) ? vatan_current_lang() : substr( get_locale(), 0, 2 );

	// Get the language list — full set from Polylang when active, fa+en
	// fallback otherwise.
	if ( function_exists( 'vatan_available_languages' ) ) {
		$languages = vatan_available_languages();
	} else {
		$languages = array(
			array( 'slug' => 'fa', 'name' => 'فارسی',   'locale' => 'fa_IR', 'flag' => '' ),
			array( 'slug' => 'en', 'name' => 'English', 'locale' => 'en_US', 'flag' => '' ),
		);
	}

	if ( count( $languages ) < 2 ) {
		return; // single-language site — nothing to switch
	}
	?>
	<div class="lang-switcher" role="group" aria-label="<?php esc_attr_e( 'Language', 'vatan-event' ); ?>">
		<?php
		$first = true;
		foreach ( $languages as $lang ) :
			$code  = $lang['slug'];
			// Polylang returns the full name (e.g. "English"); we render
			// a compact uppercase code (EN / FA) so the chip stays small.
			$label = strtoupper( $code );
			if ( ! $first ) :
				?>
				<span class="lang-switcher__sep" aria-hidden="true">|</span>
				<?php
			endif;
			$first     = false;
			$is_active = ( $current === $code );
			$classes   = 'lang-switcher__link' . ( $is_active ? ' lang-switcher__link--active' : '' );
			?>
			<a
				class="<?php echo esc_attr( $classes ); ?>"
				href="<?php echo esc_url( vatan_lang_url( $code ) ); ?>"
				hreflang="<?php echo esc_attr( $code ); ?>"
				title="<?php echo esc_attr( $lang['name'] ); ?>"
				<?php echo $is_active ? 'aria-current="true"' : ''; ?>
			><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Inline SVG glyphs for the social icon row. Kept as a flat associative
 * array keyed by the same platform slugs used by the social_links repeater
 * (theme-settings.php). All paths use `currentColor` so they pick up the
 * link color set via CSS.
 *
 * @return array<string,array{label:string,svg:string}>
 */
function vatan_social_platforms() {
	$accessible = function ( $title ) {
		return '<title>' . esc_html( $title ) . '</title>';
	};
	$attrs = 'viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
	return array(
		'instagram' => array(
			'label' => 'Instagram',
			'svg'   => '<svg ' . $attrs . '><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>',
		),
		'twitter'   => array(
			'label' => 'Twitter / X',
			'svg'   => '<svg ' . $attrs . '><path d="M4 4l7.5 9.3L4.5 20H7l5.8-5.4L17 20h3L12.1 10.3 19.4 4h-2.5L11.6 9 8 4H4z"/></svg>',
		),
		'facebook'  => array(
			'label' => 'Facebook',
			'svg'   => '<svg ' . $attrs . '><path d="M15 8h-2a1 1 0 0 0-1 1v3H9v3h3v6h3v-6h2.5l.5-3H15V9.5a.5.5 0 0 1 .5-.5H17V6h-2z"/></svg>',
		),
		'youtube'   => array(
			'label' => 'YouTube',
			'svg'   => '<svg ' . $attrs . '><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M11 9.5l4 2.5-4 2.5z" fill="currentColor"/></svg>',
		),
		'linkedin'  => array(
			'label' => 'LinkedIn',
			'svg'   => '<svg ' . $attrs . '><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v8M8 7v.01M12 18v-4a2 2 0 0 1 4 0v4M12 12v6"/></svg>',
		),
		'telegram'  => array(
			'label' => 'Telegram',
			'svg'   => '<svg ' . $attrs . '><path d="M21 4 3 11l6 2 2 6 4-4 5 4z"/></svg>',
		),
		'tiktok'    => array(
			'label' => 'TikTok',
			'svg'   => '<svg ' . $attrs . '><path d="M14 4v9a4 4 0 1 1-4-4"/><path d="M14 4c.5 2.5 2.5 4.5 5 5"/></svg>',
		),
		'whatsapp'  => array(
			'label' => 'WhatsApp',
			'svg'   => '<svg ' . $attrs . '><path d="M5 19l1.5-4A8 8 0 1 1 9 19.5L5 19z"/><path d="M9 11c0 3 2 5 5 5l1.5-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2L8.5 9.5C8.5 10 9 11 9 11z" fill="currentColor" stroke="none"/></svg>',
		),
		'email'     => array(
			'label' => 'Email',
			'svg'   => '<svg ' . $attrs . '><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>',
		),
	);
}

/**
 * Find related events for a given event.
 *
 * Strategy (in order):
 *   1. Upcoming events that share the event's first `event_category` term.
 *   2. If short, top up with upcoming events from the same `event_city`.
 *   3. If still short, top up with any other upcoming events.
 * Within each tier, results are ordered by ascending `event_date` (so the
 * soonest events surface first) then by post date as a fallback for events
 * with no ACF date set.
 *
 * @param int $event_id
 * @param int $limit Number of related events to return (default 3).
 * @return WP_Post[]
 */
function vatan_get_related_events( $event_id, $limit = 3 ) {
	$event_id = (int) $event_id;
	$limit    = max( 1, (int) $limit );
	if ( ! $event_id ) {
		return array();
	}

	$cat_terms  = wp_get_post_terms( $event_id, 'event_category', array( 'fields' => 'ids' ) );
	$city_terms = wp_get_post_terms( $event_id, 'event_city', array( 'fields' => 'ids' ) );
	$cat_terms  = is_wp_error( $cat_terms ) ? array() : array_map( 'intval', $cat_terms );
	$city_terms = is_wp_error( $city_terms ) ? array() : array_map( 'intval', $city_terms );

	$today    = current_time( 'Y-m-d' );
	$base_args = array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'post__not_in'   => array( $event_id ),
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'orderby'        => array(
			'meta_value' => 'ASC',
			'date'       => 'DESC',
		),
		'meta_key'       => 'event_date',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => 'event_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
			array(
				'key'     => 'event_date',
				'compare' => 'NOT EXISTS',
			),
		),
	);

	$found = array();

	$run_query = function ( $extra ) use ( $base_args, &$found, $limit ) {
		if ( count( $found ) >= $limit ) {
			return;
		}
		$args = array_merge( $base_args, $extra );
		$args['post__not_in']   = array_merge( $base_args['post__not_in'], wp_list_pluck( $found, 'ID' ) );
		$args['posts_per_page'] = $limit - count( $found );
		$q = new WP_Query( $args );
		foreach ( $q->posts as $p ) {
			$found[] = $p;
		}
	};

	// Tier 1 — shared category.
	if ( ! empty( $cat_terms ) ) {
		$run_query( array(
			'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'event_category',
					'field'    => 'term_id',
					'terms'    => $cat_terms,
				),
			),
		) );
	}

	// Tier 2 — same city.
	if ( ! empty( $city_terms ) ) {
		$run_query( array(
			'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'event_city',
					'field'    => 'term_id',
					'terms'    => $city_terms,
				),
			),
		) );
	}

	// Tier 3 — any upcoming event.
	$run_query( array() );

	return array_slice( $found, 0, $limit );
}

/**
 * Top-level `event_city` terms — i.e. countries in our hierarchical
 * Country > City layout. Each entry includes an **inclusive** event count
 * (own events + events tagged with any descendant city), the country's
 * flag emoji (term meta `vatan_country_flag`), and its optional cover
 * image (`vatan_city_image_id`).
 *
 * Ordering is by descending inclusive count so the most-active countries
 * surface first. Empty countries are dropped.
 *
 * @param int  $limit
 * @param bool $upcoming_only  When true, only count events whose
 *                             `event_date` is today or later. Default true.
 * @return array<int,array{term:WP_Term,count:int,flag:string,image_id:int}>
 */
function vatan_get_country_terms( $limit = 12, $upcoming_only = true ) {
	$limit = max( 1, (int) $limit );

	$countries = get_terms( array(
		'taxonomy'   => 'event_city',
		'parent'     => 0,
		'hide_empty' => false,
	) );
	if ( is_wp_error( $countries ) || empty( $countries ) ) {
		return array();
	}

	// Single grouped query — count events per country (including children)
	// instead of running a separate WP_Query per country term.
	$country_ids = wp_list_pluck( $countries, 'term_id' );
	$today       = current_time( 'Y-m-d' );

	$count_args = array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy'         => 'event_city',
				'field'            => 'term_id',
				'terms'            => $country_ids,
				'include_children' => true,
			),
		),
	);
	if ( $upcoming_only ) {
		$count_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'event_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);
	}

	$q = new WP_Query( $count_args );

	// Map each event ID to its country term(s) and count per country.
	$counts = array_fill_keys( $country_ids, 0 );
	foreach ( $q->posts as $post_id ) {
		$terms = wp_get_post_terms( $post_id, 'event_city', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $tid ) {
			if ( isset( $counts[ $tid ] ) ) {
				$counts[ $tid ]++;
			}
		}
	}

	$out = array();
	foreach ( $countries as $term ) {
		$count = (int) ( $counts[ $term->term_id ] ?? 0 );
		if ( $count < 1 ) {
			continue;
		}
		$out[] = array(
			'term'     => $term,
			'count'    => $count,
			'flag'     => (string) get_term_meta( $term->term_id, 'vatan_country_flag', true ),
			'image_id' => (int) get_term_meta( $term->term_id, 'vatan_city_image_id', true ),
		);
	}

	usort( $out, function ( $a, $b ) { return $b['count'] - $a['count']; } );
	return array_slice( $out, 0, $limit );
}

/**
 * Top cities by published-event count. Used by the `popular_cities`
 * homepage component. Only counts non-empty terms — empty cities don't
 * make for a good "popular cities" tile.
 *
 * @param int  $limit
 * @param bool $upcoming_only  When true, count only events whose
 *                             `event_date` is today or later. Default true.
 * @return array<int,array{term:WP_Term,count:int,image_id:int}>
 */
function vatan_get_popular_cities( $limit = 8, $upcoming_only = true ) {
	$limit = max( 1, (int) $limit );

	$terms = get_terms( array(
		'taxonomy'   => 'event_city',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => $upcoming_only ? 0 : $limit,
	) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$term_ids = wp_list_pluck( $terms, 'term_id' );
	$counts   = array_fill_keys( $term_ids, 0 );

	if ( $upcoming_only ) {
		// Single grouped query to count upcoming events per city.
		$today  = current_time( 'Y-m-d' );
		$q      = new WP_Query( array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'event_city',
					'field'    => 'term_id',
					'terms'    => $term_ids,
				),
			),
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'event_date',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		) );
		foreach ( $q->posts as $post_id ) {
			$post_terms = wp_get_post_terms( $post_id, 'event_city', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $post_terms ) ) {
				continue;
			}
			foreach ( $post_terms as $tid ) {
				if ( isset( $counts[ $tid ] ) ) {
					$counts[ $tid ]++;
				}
			}
		}
	}

	$out = array();
	foreach ( $terms as $term ) {
		$count = $upcoming_only ? (int) ( $counts[ $term->term_id ] ?? 0 ) : (int) $term->count;
		if ( $count < 1 ) {
			continue;
		}
		$out[] = array(
			'term'     => $term,
			'count'    => $count,
			'image_id' => (int) get_term_meta( $term->term_id, 'vatan_city_image_id', true ),
		);
		if ( count( $out ) >= $limit ) {
			break;
		}
	}

	usort( $out, function ( $a, $b ) { return $b['count'] - $a['count']; } );
	return $out;
}

/**
 * Static-page slugs we provision once for footer links. The values are the
 * page titles displayed on first creation; admins can rename / re-style
 * the pages freely after that — only the slug → page-ID mapping in the
 * `vatan_static_pages` option is what `vatan_static_page_url()` looks up.
 *
 * @return array<string,array{title:string,content:string}>
 */
function vatan_static_page_definitions() {
	return array(
		'about'   => array(
			'title'   => __( 'About Us', 'vatan-event' ),
			'content' => "<p>" . __( 'Vatan Event is a ticketing platform for Persian-language events and community gatherings. We help organizers sell tickets and event-goers discover what is happening near them.', 'vatan-event' ) . "</p>\n<p>" . __( 'Edit this page from the WordPress dashboard to tell your own story.', 'vatan-event' ) . "</p>",
		),
		'faq'     => array(
			'title'   => __( 'Frequently Asked Questions', 'vatan-event' ),
			'content' => "<h2>" . __( 'How do I buy a ticket?', 'vatan-event' ) . "</h2>\n<p>" . __( 'Open the event page, pick a ticket type (and seats if the event has a seat map), add to cart, and check out. After payment your tickets appear in My Account → My Tickets.', 'vatan-event' ) . "</p>\n\n<h2>" . __( 'Can I get a refund?', 'vatan-event' ) . "</h2>\n<p>" . __( 'Yes — up to 72 hours before the event. Request a refund from your order page.', 'vatan-event' ) . "</p>\n\n<h2>" . __( 'How do I show my ticket at the venue?', 'vatan-event' ) . "</h2>\n<p>" . __( 'Each ticket has a QR code in My Tickets. Show the QR code on your phone, or download the PDF.', 'vatan-event' ) . "</p>",
		),
		'privacy' => array(
			'title'   => __( 'Privacy Policy', 'vatan-event' ),
			'content' => "<p>" . __( 'This page explains what data we collect and how we use it. Replace this placeholder with your actual privacy policy before launch.', 'vatan-event' ) . "</p>",
		),
		'terms'   => array(
			'title'   => __( 'Terms & Conditions', 'vatan-event' ),
			'content' => "<p>" . __( 'These are the terms that govern your use of Vatan Event. Replace this placeholder with your actual terms before launch.', 'vatan-event' ) . "</p>",
		),
		'support' => array(
			'title'   => __( 'Help Center', 'vatan-event' ),
			'content' => "<p>" . __( 'Need help? Check the FAQ first — if the answer is not there, reach out via the contact page or WhatsApp.', 'vatan-event' ) . "</p>",
		),
		'contact' => array(
			'title'   => __( 'Contact Us', 'vatan-event' ),
			'content' => "<p>" . __( 'Email us at the address shown in the footer, or message us on WhatsApp / Telegram. We try to respond within one business day.', 'vatan-event' ) . "</p>",
		),
		'create-event' => array(
			'title'   => __( 'Create your event', 'vatan-event' ),
			'content' => "<p>" . __( 'Submit your event for review. Once approved by our team it goes live on the platform — and you start selling tickets immediately.', 'vatan-event' ) . "</p>",
		),
		'login'   => array(
			'title'   => __( 'Login', 'vatan-event' ),
			'content' => '', // page-login.php handles the layout entirely.
		),
		'signup'  => array(
			'title'   => __( 'Create an account', 'vatan-event' ),
			'content' => '', // page-signup.php handles the layout entirely.
		),
		'blog'    => array(
			'title'   => __( 'Guides', 'vatan-event' ),
			'content' => '', // Empty — when assigned as the WP "Posts page" the home.php template renders the post list.
		),
		'admin'   => array(
			'title'   => __( 'Admin', 'vatan-event' ),
			'content' => '', // page-admin.php handles the entire shell + view routing.
		),
	);
}

/**
 * Provision the static pages listed in vatan_static_page_definitions() one
 * time per install. Idempotent — pages that already exist at the target slug
 * are reused, not duplicated.
 *
 * Hooked to `admin_init` so an existing install (where after_switch_theme
 * already fired) still picks the pages up on the next admin visit.
 */
function vatan_seed_static_pages() {
	// Only seed when an admin is around, so we don't race with REST/cron.
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$definitions = vatan_static_page_definitions();
	// Re-run the loop whenever the *set* of slugs changes — fingerprinting
	// by slug list means adding `create-event` (or any future page) picks
	// up new entries without manual flag resets.
	$fingerprint = md5( implode( ',', array_keys( $definitions ) ) );
	if ( get_option( 'vatan_pages_seeded' ) === $fingerprint ) {
		return;
	}

	$existing_map = (array) get_option( 'vatan_static_pages', array() );
	$map          = array();
	foreach ( $definitions as $slug => $def ) {
		// Reuse the already-seeded ID first so admins keep their edits.
		if ( ! empty( $existing_map[ $slug ] ) && get_post( (int) $existing_map[ $slug ] ) ) {
			$map[ $slug ] = (int) $existing_map[ $slug ];
			continue;
		}
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing instanceof WP_Post ) {
			$map[ $slug ] = (int) $existing->ID;
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $def['title'],
			'post_content' => $def['content'],
		), true );
		if ( ! is_wp_error( $id ) && $id ) {
			$map[ $slug ] = (int) $id;
		}
	}

	update_option( 'vatan_static_pages', $map );
	update_option( 'vatan_pages_seeded', $fingerprint );

	// New page slugs above need a rewrite refresh so their pretty URLs
	// (/blog/, /login/, /signup/, …) start resolving immediately.
	flush_rewrite_rules( false );
}
add_action( 'admin_init', 'vatan_seed_static_pages', 30 );

/**
 * Look up the URL for a seeded static page by slug. Falls back to a slug
 * query for resilience if the option got cleared. Returns '' when nothing
 * matches — callers should use `?: '#'` if they need a non-empty href.
 *
 * @param string $slug
 * @return string
 */
function vatan_static_page_url( $slug ) {
	$slug = sanitize_key( $slug );
	$map  = (array) get_option( 'vatan_static_pages', array() );
	if ( ! empty( $map[ $slug ] ) ) {
		$url = get_permalink( (int) $map[ $slug ] );
		if ( $url ) {
			return $url;
		}
	}
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	return $page ? (string) get_permalink( $page ) : '';
}

/**
 * Render an OpenStreetMap embed + "Get directions" link for an event venue.
 *
 * Reads ACF fields `event_venue_lat`, `event_venue_lng`, `event_venue_map_link`,
 * `event_venue`. When lat/lng are both set, renders an <iframe> from
 * openstreetmap.org with a marker; otherwise falls back to just the directions
 * button using `event_venue_map_link` (if set). Renders nothing when neither
 * coords nor a map link are provided.
 *
 * @param int $event_id
 */
function vatan_render_venue_map( $event_id ) {
	$lat       = function_exists( 'get_field' ) ? trim( (string) get_field( 'event_venue_lat', $event_id ) )      : (string) get_post_meta( $event_id, 'event_venue_lat', true );
	$lng       = function_exists( 'get_field' ) ? trim( (string) get_field( 'event_venue_lng', $event_id ) )      : (string) get_post_meta( $event_id, 'event_venue_lng', true );
	$map_link  = function_exists( 'get_field' ) ? (string) get_field( 'event_venue_map_link', $event_id )         : (string) get_post_meta( $event_id, 'event_venue_map_link', true );
	$venue     = function_exists( 'get_field' ) ? (string) get_field( 'event_venue', $event_id )                  : (string) get_post_meta( $event_id, 'event_venue', true );

	$has_coords = ( '' !== $lat && '' !== $lng && is_numeric( $lat ) && is_numeric( $lng ) );

	if ( ! $has_coords && '' === $map_link ) {
		return;
	}

	if ( $has_coords ) {
		$lat = (float) $lat;
		$lng = (float) $lng;
		// Bounding box ≈ 500m around the marker. OSM expects west,south,east,north.
		$d   = 0.005;
		$bbox = ( $lng - $d ) . ',' . ( $lat - $d ) . ',' . ( $lng + $d ) . ',' . ( $lat + $d );

		$embed_url = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $bbox )
			. '&layer=mapnik&marker=' . rawurlencode( $lat . ',' . $lng );

		// Directions URL prefers the admin's map_link (often Google Maps);
		// falls back to OSM directions when no external link is set.
		$directions_url = $map_link ?: ( 'https://www.openstreetmap.org/directions?to=' . rawurlencode( $lat . ',' . $lng ) );
	} else {
		$embed_url      = '';
		$directions_url = $map_link;
	}
	?>
	<section class="venue-map">
		<header class="venue-map__head">
			<h2 class="event-section-title"><?php esc_html_e( 'Location', 'vatan-event' ); ?></h2>
			<?php if ( $venue ) : ?>
				<p class="venue-map__address"><span aria-hidden="true">📍</span> <?php echo esc_html( $venue ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $embed_url ) : ?>
			<div class="venue-map__embed">
				<iframe
					src="<?php echo esc_url( $embed_url ); ?>"
					title="<?php esc_attr_e( 'Venue map', 'vatan-event' ); ?>"
					loading="lazy"
					referrerpolicy="no-referrer-when-downgrade"
				></iframe>
			</div>
		<?php endif; ?>

		<?php if ( $directions_url ) : ?>
			<a class="btn btn--ghost venue-map__directions" href="<?php echo esc_url( $directions_url ); ?>" target="_blank" rel="noopener noreferrer">
				<span aria-hidden="true">🧭</span>
				<?php esc_html_e( 'Get directions', 'vatan-event' ); ?>
			</a>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Render the configured social-links row. Outputs nothing when no links are
 * set. Reads from Theme Settings → Footer → Social links.
 *
 * @param array $args  Optional. `class` to extend the wrapper class.
 */
function vatan_render_social_links( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'class' => 'footer-social',
	) );

	$links = (array) ( function_exists( 'vatan_get_setting' ) ? vatan_get_setting( 'social_links' ) : array() );
	if ( empty( $links ) ) {
		return;
	}

	$platforms = vatan_social_platforms();
	?>
	<ul class="<?php echo esc_attr( $args['class'] ); ?>">
		<?php
		foreach ( $links as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$platform = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : '';
			$url      = isset( $row['url'] ) ? (string) $row['url'] : '';
			if ( ! $platform || ! $url || ! isset( $platforms[ $platform ] ) ) {
				continue;
			}

			// `mailto:` for email; `https://` already validated for the rest via esc_url.
			$href = ( 'email' === $platform && 0 !== strpos( $url, 'mailto:' ) )
				? 'mailto:' . $url
				: $url;

			$label = $platforms[ $platform ]['label'];
			$svg   = $platforms[ $platform ]['svg'];
			$is_external = ! in_array( $platform, array( 'email', 'whatsapp' ), true );
			?>
			<li class="footer-social__item">
				<a
					class="footer-social__link footer-social__link--<?php echo esc_attr( $platform ); ?>"
					href="<?php echo esc_url( $href ); ?>"
					<?php if ( $is_external ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
					aria-label="<?php echo esc_attr( $label ); ?>"
					title="<?php echo esc_attr( $label ); ?>"
				>
					<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — hardcoded inline SVG. ?>
					<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
				</a>
			</li>
			<?php
		}
		?>
	</ul>
	<?php
}

/* =============================================================================
 *  SEO helpers — meta tags, OG, Twitter Cards
 * ===========================================================================*/

/**
 * Get the page title for SEO purposes.
 *
 * @return string
 */
function vatan_get_page_title(): string {
	if ( is_singular( 'event' ) ) {
		$event = get_post();
		if ( $event ) {
			return get_the_title( $event ) . ' — ' . get_bloginfo( 'name' );
		}
	}
	if ( is_singular( 'post' ) ) {
		return get_the_title() . ' — ' . get_bloginfo( 'name' );
	}
	if ( is_post_type_archive( 'event' ) ) {
		return __( 'Events', 'vatan-event' ) . ' — ' . get_bloginfo( 'name' );
	}
	if ( is_home() ) {
		return get_bloginfo( 'name' ) . ' — ' . get_bloginfo( 'description' );
	}
	return get_bloginfo( 'name' );
}

/**
 * Get the meta description for SEO purposes.
 *
 * @return string
 */
function vatan_get_meta_description(): string {
	if ( is_singular( 'event' ) ) {
		$event = get_post();
		if ( $event ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $event->post_content ), 30, '…' );
			if ( $excerpt ) {
				return $excerpt;
			}
		}
	}
	if ( is_singular( 'post' ) ) {
		return wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 30, '…' );
	}
	return get_bloginfo( 'description' );
}

/**
 * Get the OG/Twitter image URL for SEO purposes.
 *
 * @return string
 */
function vatan_get_seo_image(): string {
	if ( is_singular( 'event' ) ) {
		$thumb = get_the_post_thumbnail_url( get_post(), 'large' );
		if ( $thumb ) {
			return $thumb;
		}
	}
	if ( is_singular( 'post' ) ) {
		$thumb = get_the_post_thumbnail_url( get_post(), 'large' );
		if ( $thumb ) {
			return $thumb;
		}
	}
	// Fallback to site logo or default image.
	$logo_id = (int) vatan_get_setting( 'logo_id' );
	if ( $logo_id ) {
		$url = wp_get_attachment_image_url( $logo_id, 'large' );
		if ( $url ) {
			return $url;
		}
	}
	return '';
}

/**
 * Emit SEO meta tags into <head>.
 *
 * Hooks into wp_head at priority 1.
 */
function vatan_emit_seo_meta(): void {
	$title       = vatan_get_page_title();
	$description = vatan_get_meta_description();
	$image       = vatan_get_seo_image();
	$url         = get_permalink();
	$site_name   = get_bloginfo( 'name' );
	?>
	<meta name="description" content="<?php echo esc_attr( $description ); ?>" />
	<meta property="og:title" content="<?php echo esc_attr( $title ); ?>" />
	<meta property="og:description" content="<?php echo esc_attr( $description ); ?>" />
	<meta property="og:url" content="<?php echo esc_url( $url ); ?>" />
	<meta property="og:site_name" content="<?php echo esc_attr( $site_name ); ?>" />
	<?php if ( $image ) : ?>
		<meta property="og:image" content="<?php echo esc_url( $image ); ?>" />
	<?php endif; ?>
	<?php if ( is_singular( 'event' ) ) : ?>
		<meta property="og:type" content="event" />
	<?php elseif ( is_singular( 'post' ) ) : ?>
		<meta property="og:type" content="article" />
	<?php else : ?>
		<meta property="og:type" content="website" />
	<?php endif; ?>
	<meta name="twitter:card" content="<?php echo $image ? 'summary_large_image' : 'summary'; ?>" />
	<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>" />
	<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>" />
	<?php if ( $image ) : ?>
		<meta name="twitter:image" content="<?php echo esc_url( $image ); ?>" />
	<?php endif; ?>
	<?php
}
add_action( 'wp_head', 'vatan_emit_seo_meta', 1 );

/**
 * Emit Schema.org JSON-LD structured data.
 *
 * Hooks into wp_head at priority 2 (after meta tags).
 */
function vatan_emit_schema_jsonld(): void {
	$schema = array();

	if ( is_singular( 'event' ) ) {
		$event = get_post();
		if ( $event ) {
			$event_date = get_post_meta( $event->ID, 'event_date', true );
			$end_date   = get_post_meta( $event->ID, 'event_end_date', true );
			$venue      = get_post_meta( $event->ID, 'event_venue', true );
			$organizer_id = (int) get_post_meta( $event->ID, '_vatan_submitted_by', true );

			$schema['@context'] = 'https://schema.org';
			$schema['@type'] = 'Event';
			$schema['name'] = get_the_title( $event );
			$schema['description'] = wp_trim_words( wp_strip_all_tags( $event->post_content ), 50, '…' );
			$schema['url'] = get_permalink( $event );

			if ( $event_date ) {
				$schema['startDate'] = $event_date;
			}
			if ( $end_date ) {
				$schema['endDate'] = $end_date;
			}
			if ( $venue ) {
				$schema['location'] = array(
					'@type' => 'Place',
					'name' => $venue,
				);
			}
			if ( $organizer_id ) {
				$organizer = get_userdata( $organizer_id );
				if ( $organizer ) {
					$schema['organizer'] = array(
						'@type' => 'Person',
						'name' => $organizer->display_name,
					);
				}
			}
			$thumb = get_the_post_thumbnail_url( $event, 'large' );
			if ( $thumb ) {
				$schema['image'] = $thumb;
			}
		}
	} elseif ( is_front_page() ) {
		$schema['@context'] = 'https://schema.org';
		$schema['@type'] = 'Organization';
		$schema['name'] = get_bloginfo( 'name' );
		$schema['url'] = home_url( '/' );
		$logo_id = (int) vatan_get_setting( 'logo_id' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'large' );
			if ( $logo_url ) {
				$schema['logo'] = $logo_url;
			}
		}
	}

	if ( ! empty( $schema ) ) {
		?>
		<script type="application/ld+json">
		<?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
		</script>
		<?php
	}
}
add_action( 'wp_head', 'vatan_emit_schema_jsonld', 2 );
