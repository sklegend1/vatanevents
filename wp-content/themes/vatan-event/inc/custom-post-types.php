<?php
/**
 * Custom post types and taxonomies.
 *
 * Registers the `event` CPT plus its `event_category` (flat genre)
 * and `event_city` (hierarchical Country > City) taxonomies, and seeds
 * default category terms once on theme activation.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the `event` custom post type.
 */
function vatan_register_event_post_type() {
	$labels = array(
		'name'                  => _x( 'Events', 'post type general name', 'vatan-event' ),
		'singular_name'         => _x( 'Event', 'post type singular name', 'vatan-event' ),
		'menu_name'             => _x( 'Events', 'admin menu', 'vatan-event' ),
		'name_admin_bar'        => _x( 'Event', 'add new on admin bar', 'vatan-event' ),
		'add_new'               => _x( 'Add Event', 'event', 'vatan-event' ),
		'add_new_item'          => __( 'Add New Event', 'vatan-event' ),
		'new_item'              => __( 'New Event', 'vatan-event' ),
		'edit_item'             => __( 'Edit Event', 'vatan-event' ),
		'view_item'             => __( 'View Event', 'vatan-event' ),
		'view_items'            => __( 'View Events', 'vatan-event' ),
		'all_items'             => __( 'All Events', 'vatan-event' ),
		'search_items'          => __( 'Search Events', 'vatan-event' ),
		'parent_item_colon'     => __( 'Parent Event:', 'vatan-event' ),
		'not_found'             => __( 'No events found.', 'vatan-event' ),
		'not_found_in_trash'    => __( 'No events found in Trash.', 'vatan-event' ),
		'archives'              => __( 'Event Archives', 'vatan-event' ),
		'attributes'            => __( 'Event Attributes', 'vatan-event' ),
		'featured_image'        => __( 'Event Cover', 'vatan-event' ),
		'set_featured_image'    => __( 'Set event cover', 'vatan-event' ),
		'remove_featured_image' => __( 'Remove event cover', 'vatan-event' ),
		'use_featured_image'    => __( 'Use as event cover', 'vatan-event' ),
		'filter_items_list'     => __( 'Filter events list', 'vatan-event' ),
		'items_list_navigation' => __( 'Events list navigation', 'vatan-event' ),
		'items_list'            => __( 'Events list', 'vatan-event' ),
	);

	$args = array(
		'labels'        => $labels,
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => array(
			'slug'       => 'events',
			'with_front' => false,
		),
		'menu_icon'     => 'dashicons-calendar-alt',
		'menu_position' => 5,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
		'taxonomies'    => array( 'event_category', 'event_city' ),
	);

	register_post_type( 'event', $args );
}
add_action( 'init', 'vatan_register_event_post_type' );

/**
 * Register the `organizer` CPT — entity that produces / runs events.
 *
 * Linked to events via the ACF `event_organizer` post_object field. The
 * single-organizer.php template shows the org's profile + their upcoming
 * events.
 */
function vatan_register_organizer_post_type() {
	$labels = array(
		'name'                  => _x( 'Organizers', 'post type general name', 'vatan-event' ),
		'singular_name'         => _x( 'Organizer', 'post type singular name', 'vatan-event' ),
		'menu_name'             => _x( 'Organizers', 'admin menu', 'vatan-event' ),
		'name_admin_bar'        => _x( 'Organizer', 'add new on admin bar', 'vatan-event' ),
		'add_new'               => _x( 'Add Organizer', 'organizer', 'vatan-event' ),
		'add_new_item'          => __( 'Add New Organizer', 'vatan-event' ),
		'new_item'              => __( 'New Organizer', 'vatan-event' ),
		'edit_item'             => __( 'Edit Organizer', 'vatan-event' ),
		'view_item'             => __( 'View Organizer', 'vatan-event' ),
		'all_items'             => __( 'All Organizers', 'vatan-event' ),
		'search_items'          => __( 'Search Organizers', 'vatan-event' ),
		'not_found'             => __( 'No organizers found.', 'vatan-event' ),
		'not_found_in_trash'    => __( 'No organizers found in Trash.', 'vatan-event' ),
		'featured_image'        => __( 'Organizer Logo', 'vatan-event' ),
		'set_featured_image'    => __( 'Set organizer logo', 'vatan-event' ),
		'remove_featured_image' => __( 'Remove organizer logo', 'vatan-event' ),
		'use_featured_image'    => __( 'Use as organizer logo', 'vatan-event' ),
	);

	register_post_type( 'organizer', array(
		'labels'        => $labels,
		'public'        => true,
		'has_archive'   => false,
		'rewrite'       => array(
			'slug'       => 'organizer',
			'with_front' => false,
		),
		'menu_icon'     => 'dashicons-businessperson',
		'menu_position' => 6,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
	) );
}
add_action( 'init', 'vatan_register_organizer_post_type' );

/**
 * Register the taxonomies attached to the `event` CPT.
 */
function vatan_register_event_taxonomies() {

	// event_category — genre (concert / theater / traditional / stand-up / festival).
	register_taxonomy( 'event_category', array( 'event' ), array(
		'labels'            => array(
			'name'                       => _x( 'Categories', 'taxonomy general name', 'vatan-event' ),
			'singular_name'              => _x( 'Category', 'taxonomy singular name', 'vatan-event' ),
			'menu_name'                  => __( 'Categories', 'vatan-event' ),
			'all_items'                  => __( 'All Categories', 'vatan-event' ),
			'parent_item'                => __( 'Parent Category', 'vatan-event' ),
			'parent_item_colon'          => __( 'Parent Category:', 'vatan-event' ),
			'edit_item'                  => __( 'Edit Category', 'vatan-event' ),
			'update_item'                => __( 'Update Category', 'vatan-event' ),
			'add_new_item'               => __( 'Add New Category', 'vatan-event' ),
			'new_item_name'              => __( 'New Category Name', 'vatan-event' ),
			'search_items'               => __( 'Search Categories', 'vatan-event' ),
			'not_found'                  => __( 'No categories found.', 'vatan-event' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'vatan-event' ),
			'separate_items_with_commas' => null,
		),
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array(
			'slug'         => 'event-category',
			'with_front'   => false,
			'hierarchical' => true,
		),
	) );

	// event_city — geographic placement, hierarchical so Country > City nests cleanly.
	register_taxonomy( 'event_city', array( 'event' ), array(
		'labels'            => array(
			'name'              => _x( 'Cities', 'taxonomy general name', 'vatan-event' ),
			'singular_name'     => _x( 'City', 'taxonomy singular name', 'vatan-event' ),
			'menu_name'         => __( 'Cities', 'vatan-event' ),
			'all_items'         => __( 'All Cities', 'vatan-event' ),
			'parent_item'       => __( 'Country', 'vatan-event' ),
			'parent_item_colon' => __( 'Country:', 'vatan-event' ),
			'edit_item'         => __( 'Edit City', 'vatan-event' ),
			'update_item'       => __( 'Update City', 'vatan-event' ),
			'add_new_item'      => __( 'Add New City', 'vatan-event' ),
			'new_item_name'     => __( 'New City Name', 'vatan-event' ),
			'search_items'      => __( 'Search Cities', 'vatan-event' ),
			'not_found'         => __( 'No cities found.', 'vatan-event' ),
		),
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array(
			'slug'         => 'event-city',
			'with_front'   => false,
			'hierarchical' => true,
		),
	) );
}
add_action( 'init', 'vatan_register_event_taxonomies' );

/**
 * Seed the default event_category terms once on theme activation.
 *
 * Stable English slugs paired with Persian display names so a future
 * Polylang/WPML translation layer can attach English counterparts without
 * having to rename anything. Terms that already exist are skipped.
 */
function vatan_seed_default_event_categories() {
	if ( ! taxonomy_exists( 'event_category' ) ) {
		return;
	}

	$defaults = array(
		'concert'           => 'کنسرت',
		'theater'           => 'تئاتر',
		'traditional-music' => 'موسیقی سنتی',
		'standup'           => 'استندآپ',
		'festival'          => 'جشنواره',
	);

	foreach ( $defaults as $slug => $name ) {
		if ( term_exists( $slug, 'event_category' ) ) {
			continue;
		}
		wp_insert_term( $name, 'event_category', array( 'slug' => $slug ) );
	}
}
add_action( 'after_switch_theme', 'vatan_seed_default_event_categories' );

/* ============================================================
   event_city — admin: cover image term meta
   ============================================================ */

/**
 * Render the cover-image picker on the event_city "Add new term" form.
 */
function vatan_event_city_add_image_field() {
	?>
	<div class="form-field term-flag-wrap">
		<label for="vatan-country-flag"><?php esc_html_e( 'Flag emoji (countries)', 'vatan-event' ); ?></label>
		<input type="text" id="vatan-country-flag" name="vatan_country_flag" value="" maxlength="8" style="max-width:96px;font-size:20px;" placeholder="🇩🇪" />
		<p class="description"><?php esc_html_e( 'Optional. Shown on the "Country chips" homepage component. Use the flag emoji (🇩🇪 🇸🇪 🇬🇧 …).', 'vatan-event' ); ?></p>
	</div>
	<div class="form-field term-image-wrap">
		<label for="vatan-city-image"><?php esc_html_e( 'Cover image', 'vatan-event' ); ?></label>
		<div class="vatan-city-image" data-vatan-city-image>
			<input type="hidden" name="vatan_city_image_id" id="vatan-city-image" value="" data-vatan-city-image-input />
			<div class="vatan-city-image__preview" data-vatan-city-image-preview></div>
			<p>
				<button type="button" class="button" data-vatan-city-image-pick><?php esc_html_e( 'Choose image', 'vatan-event' ); ?></button>
				<button type="button" class="button-link-delete" data-vatan-city-image-clear style="display:none"><?php esc_html_e( 'Remove', 'vatan-event' ); ?></button>
			</p>
		</div>
		<p class="description"><?php esc_html_e( 'Used as the background of the "Popular cities" homepage tile.', 'vatan-event' ); ?></p>
	</div>
	<?php
}
add_action( 'event_city_add_form_fields', 'vatan_event_city_add_image_field' );

/**
 * Render the cover-image picker on the event_city "Edit term" screen.
 *
 * @param WP_Term $term
 */
function vatan_event_city_edit_image_field( $term ) {
	$image_id = (int) get_term_meta( $term->term_id, 'vatan_city_image_id', true );
	$url      = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	$flag     = (string) get_term_meta( $term->term_id, 'vatan_country_flag', true );
	?>
	<tr class="form-field term-flag-wrap">
		<th scope="row"><label for="vatan-country-flag"><?php esc_html_e( 'Flag emoji (countries)', 'vatan-event' ); ?></label></th>
		<td>
			<input type="text" id="vatan-country-flag" name="vatan_country_flag" value="<?php echo esc_attr( $flag ); ?>" maxlength="8" style="max-width:96px;font-size:20px;" placeholder="🇩🇪" />
			<p class="description"><?php esc_html_e( 'Optional. Shown on the "Country chips" homepage component for top-level (country) terms.', 'vatan-event' ); ?></p>
		</td>
	</tr>
	<tr class="form-field term-image-wrap">
		<th scope="row"><label for="vatan-city-image"><?php esc_html_e( 'Cover image', 'vatan-event' ); ?></label></th>
		<td>
			<div class="vatan-city-image" data-vatan-city-image>
				<input type="hidden" name="vatan_city_image_id" id="vatan-city-image" value="<?php echo esc_attr( $image_id ); ?>" data-vatan-city-image-input />
				<div class="vatan-city-image__preview" data-vatan-city-image-preview>
					<?php if ( $url ) : ?>
						<img src="<?php echo esc_url( $url ); ?>" alt="" style="max-width:240px;height:auto;display:block;border-radius:6px;" />
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button" data-vatan-city-image-pick><?php esc_html_e( 'Choose image', 'vatan-event' ); ?></button>
					<button type="button" class="button-link-delete" data-vatan-city-image-clear<?php echo $image_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'vatan-event' ); ?></button>
				</p>
			</div>
			<p class="description"><?php esc_html_e( 'Used as the background of the "Popular cities" homepage tile.', 'vatan-event' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'event_city_edit_form_fields', 'vatan_event_city_edit_image_field' );

/**
 * Save the cover-image term meta.
 *
 * @param int $term_id
 */
function vatan_event_city_save_image( $term_id ) {
	// Term edit form has its own nonces; we re-check capability here.
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	if ( isset( $_POST['vatan_city_image_id'] ) ) {
		$id = absint( wp_unslash( $_POST['vatan_city_image_id'] ) );
		if ( $id > 0 ) {
			update_term_meta( $term_id, 'vatan_city_image_id', $id );
		} else {
			delete_term_meta( $term_id, 'vatan_city_image_id' );
		}
	}

	if ( isset( $_POST['vatan_country_flag'] ) ) {
		$flag = sanitize_text_field( wp_unslash( $_POST['vatan_country_flag'] ) );
		if ( '' !== $flag ) {
			update_term_meta( $term_id, 'vatan_country_flag', $flag );
		} else {
			delete_term_meta( $term_id, 'vatan_country_flag' );
		}
	}
}
add_action( 'created_event_city', 'vatan_event_city_save_image' );
add_action( 'edited_event_city',  'vatan_event_city_save_image' );

/**
 * Enqueue the media library + small inline JS on the event_city term screens.
 *
 * @param string $hook
 */
function vatan_event_city_enqueue_media( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}
	$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'event_city' !== $tax ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_script( 'media-editor', "
( function () {
	function init( root ) {
		var input   = root.querySelector( '[data-vatan-city-image-input]' );
		var preview = root.querySelector( '[data-vatan-city-image-preview]' );
		var pick    = root.querySelector( '[data-vatan-city-image-pick]' );
		var clear   = root.querySelector( '[data-vatan-city-image-clear]' );
		if ( ! input || ! pick ) return;

		var frame;
		pick.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = wp.media( {
					title: 'Choose cover image',
					button: { text: 'Use this image' },
					library: { type: 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					input.value = att.id;
					preview.innerHTML = '';
					var img = document.createElement( 'img' );
					img.src = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
					img.alt = '';
					img.style.maxWidth = '240px';
					img.style.borderRadius = '6px';
					preview.appendChild( img );
					if ( clear ) clear.style.display = '';
				} );
			}
			frame.open();
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				input.value = '';
				preview.innerHTML = '';
				clear.style.display = 'none';
			} );
		}
	}
	document.querySelectorAll( '[data-vatan-city-image]' ).forEach( init );
	// Edit-tags screen reuses the form for the inline 'add new' form; init on submit-success too.
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-vatan-city-image]' ).forEach( init );
	} );
} )();
" );
}
add_action( 'admin_enqueue_scripts', 'vatan_event_city_enqueue_media' );

/* ============================================================
   Auto-flag for new event_city country terms

   When a top-level (parent=0) event_city term is created or edited,
   try to detect its ISO-3166 alpha-2 country code from the slug or
   name, then generate the flag emoji from the regional-indicator
   Unicode symbols. The admin can still override by editing the
   `vatan_country_flag` term meta manually.
   ============================================================ */

/**
 * Slug / lowercase-name → ISO-3166 alpha-2. Covers diaspora-relevant
 * countries plus the rest of Europe. Extend as needed — adding an entry
 * here makes the auto-flag work for that country forever after.
 */
function vatan_country_slug_to_iso(): array {
	return array(
		// English slugs
		'germany'          => 'DE',
		'united-kingdom'   => 'GB',
		'uk'               => 'GB',
		'britain'          => 'GB',
		'great-britain'    => 'GB',
		'england'          => 'GB',
		'sweden'           => 'SE',
		'netherlands'      => 'NL',
		'holland'          => 'NL',
		'france'           => 'FR',
		'austria'          => 'AT',
		'italy'            => 'IT',
		'spain'            => 'ES',
		'belgium'          => 'BE',
		'switzerland'      => 'CH',
		'denmark'          => 'DK',
		'norway'           => 'NO',
		'finland'          => 'FI',
		'iceland'          => 'IS',
		'ireland'          => 'IE',
		'portugal'         => 'PT',
		'greece'           => 'GR',
		'poland'           => 'PL',
		'czechia'          => 'CZ',
		'czech-republic'   => 'CZ',
		'hungary'          => 'HU',
		'romania'          => 'RO',
		'bulgaria'         => 'BG',
		'croatia'          => 'HR',
		'serbia'           => 'RS',
		'slovenia'         => 'SI',
		'slovakia'         => 'SK',
		'estonia'          => 'EE',
		'latvia'           => 'LV',
		'lithuania'        => 'LT',
		'luxembourg'       => 'LU',
		'turkey'           => 'TR',
		'cyprus'           => 'CY',
		'malta'            => 'MT',
		'canada'           => 'CA',
		'usa'              => 'US',
		'united-states'    => 'US',
		'america'          => 'US',
		'australia'        => 'AU',
		'new-zealand'      => 'NZ',
		'uae'              => 'AE',
		'united-arab-emirates' => 'AE',
		'qatar'            => 'QA',
		'iran'             => 'IR',
		// Persian / common alternative names — lowercase, matched case-insensitively
		'آلمان'           => 'DE',
		'انگلستان'        => 'GB',
		'بریتانیا'        => 'GB',
		'سوئد'            => 'SE',
		'هلند'            => 'NL',
		'فرانسه'          => 'FR',
		'اتریش'           => 'AT',
		'ایتالیا'         => 'IT',
		'اسپانیا'         => 'ES',
		'بلژیک'           => 'BE',
		'سوئیس'           => 'CH',
		'دانمارک'         => 'DK',
		'نروژ'            => 'NO',
		'فنلاند'          => 'FI',
		'ایرلند'          => 'IE',
		'پرتغال'          => 'PT',
		'یونان'           => 'GR',
		'لهستان'          => 'PL',
		'مجارستان'        => 'HU',
		'رومانی'          => 'RO',
		'بلغارستان'       => 'BG',
		'کرواسی'          => 'HR',
		'صربستان'         => 'RS',
		'ترکیه'           => 'TR',
		'کانادا'          => 'CA',
		'آمریکا'          => 'US',
		'استرالیا'        => 'AU',
		'امارات'          => 'AE',
		'قطر'             => 'QA',
		'ایران'           => 'IR',
	);
}

/**
 * Convert an ISO-3166 alpha-2 country code (e.g. "DE") into the matching
 * flag emoji ("🇩🇪") by mapping each letter to its regional-indicator
 * symbol (Unicode block U+1F1E6 – U+1F1FF).
 *
 * Returns empty string for invalid input.
 */
function vatan_iso_to_flag_emoji( string $iso ): string {
	$iso = strtoupper( trim( $iso ) );
	if ( ! preg_match( '/^[A-Z]{2}$/', $iso ) ) {
		return '';
	}
	$a = mb_chr( 0x1F1E6 + ( ord( $iso[0] ) - ord( 'A' ) ), 'UTF-8' );
	$b = mb_chr( 0x1F1E6 + ( ord( $iso[1] ) - ord( 'A' ) ), 'UTF-8' );
	return $a . $b;
}

/**
 * Try to resolve a flag emoji for a country term by:
 *   1. Looking up the slug (with `-fa` suffix stripped) in our slug→ISO map.
 *   2. Looking up the lowercase name in the same map.
 *   3. Returning '' if nothing matches.
 */
function vatan_resolve_country_flag( $term ): string {
	$map      = vatan_country_slug_to_iso();
	$slug     = (string) $term->slug;
	$slug_alt = preg_replace( '/-fa$/', '', $slug );
	$name     = mb_strtolower( (string) $term->name );

	foreach ( array( $slug, $slug_alt, $name ) as $key ) {
		if ( isset( $map[ $key ] ) ) {
			return vatan_iso_to_flag_emoji( $map[ $key ] );
		}
	}
	return '';
}

/**
 * Hook: on create/edit of an event_city term, auto-fill the flag meta
 * if it's a top-level (country) term and no flag is set yet.
 *
 * Runs at priority 50 — well after Polylang's term-clone hooks (10) so
 * the Polylang `-fa` clones also get their flags filled on first save.
 */
function vatan_autoflag_event_city( $term_id, $tt_id = 0, $taxonomy = '' ) {
	// `created_event_city` and `edited_event_city` actions don't pass the
	// taxonomy arg, but the global `created_term` / `edited_term` do — so
	// the function is robust to either binding.
	if ( $taxonomy && 'event_city' !== $taxonomy ) {
		return;
	}
	$term = get_term( $term_id, 'event_city' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}
	// Country = top-level term (parent = 0). City terms (with a parent)
	// are skipped — they inherit context from their country.
	if ( (int) $term->parent !== 0 ) {
		return;
	}
	// Don't overwrite an existing flag (admin may have set one manually).
	$existing = (string) get_term_meta( $term_id, 'vatan_country_flag', true );
	if ( $existing ) {
		return;
	}
	$flag = vatan_resolve_country_flag( $term );
	if ( $flag ) {
		update_term_meta( $term_id, 'vatan_country_flag', $flag );
	}
}
add_action( 'created_event_city', 'vatan_autoflag_event_city', 50, 2 );
add_action( 'edited_event_city',  'vatan_autoflag_event_city', 50, 2 );

/* ============================================================
   Auto-emoji for event_category terms

   Same idea as the country auto-flag system: when an admin
   creates an event_category, try to match its slug/name in our
   map and set the `vatan_emoji` term meta automatically. The
   admin can still override by editing the term.
   ============================================================ */

/**
 * Slug or lowercase-name → emoji icon. Extend as you add new
 * categories. Persian names are matched case-insensitively too.
 */
function vatan_category_slug_to_emoji(): array {
	return array(
		// Canonical English slugs
		'concert'            => '🎤',
		'music'              => '🎵',
		'traditional-music'  => '🪕',
		'classical'          => '🎼',
		'classical-symphony' => '🎼',
		'symphony'           => '🎼',
		'theater'            => '🎭',
		'theatre'            => '🎭',
		'play'               => '🎭',
		'opera'              => '🎭',
		'standup'            => '😂',
		'stand-up'           => '😂',
		'comedy'             => '😂',
		'standup-comedy'     => '😂',
		'festival'           => '🎪',
		'festivals'          => '🎪',
		'film'               => '🎬',
		'cinema'             => '🎬',
		'movie'              => '🎬',
		'party'              => '🎉',
		'parties'            => '🎉',
		'club'               => '💃',
		'dance'              => '💃',
		'dj'                 => '🎧',
		'charity'            => '💝',
		'fundraiser'         => '💝',
		'gift-cards'         => '🎁',
		'workshop'           => '🛠️',
		'conference'         => '🎓',
		'sports'             => '🏟️',
		'kids'               => '🧸',
		'family'             => '👨‍👩‍👧',
		'food'               => '🍽️',
		'exhibition'         => '🖼️',
		'art'                => '🎨',
		// Persian names — lowercase, matched case-insensitively
		'کنسرت'           => '🎤',
		'موسیقی'         => '🎵',
		'موسیقی سنتی'    => '🪕',
		'موسیقی-سنتی'    => '🪕',
		'سنتی'            => '🪕',
		'سمفونیک'        => '🎼',
		'کلاسیک'         => '🎼',
		'تئاتر'           => '🎭',
		'تیاتر'           => '🎭',
		'نمایش'           => '🎭',
		'اپرا'            => '🎭',
		'استندآپ'        => '😂',
		'کمدی'           => '😂',
		'جشنواره'        => '🎪',
		'فیلم'            => '🎬',
		'سینما'           => '🎬',
		'پارتی'           => '🎉',
		'مهمانی'          => '🎉',
		'رقص'             => '💃',
		'خیریه'           => '💝',
		'کارت هدیه'      => '🎁',
		'کارگاه'          => '🛠️',
		'همایش'           => '🎓',
		'ورزش'            => '🏟️',
		'کودک'            => '🧸',
		'خانواده'         => '👨‍👩‍👧',
		'غذا'             => '🍽️',
		'نمایشگاه'        => '🖼️',
		'هنر'             => '🎨',
	);
}

/**
 * Resolve an emoji for a category term — tries slug (with Polylang
 * `-fa` suffix stripped) and lowercase name. Returns '' on miss.
 */
function vatan_resolve_category_emoji( $term ): string {
	$map      = vatan_category_slug_to_emoji();
	$slug     = (string) $term->slug;
	$slug_alt = preg_replace( '/-fa$/', '', $slug );
	$name     = mb_strtolower( trim( (string) $term->name ) );

	foreach ( array( $slug, $slug_alt, $name ) as $key ) {
		if ( '' !== $key && isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
	}
	return '';
}

/**
 * Hook: on create/edit of an event_category term, fill its
 * `vatan_emoji` meta if we can match it to an emoji and the
 * field is empty.
 */
function vatan_autoemoji_event_category( $term_id ) {
	$term = get_term( $term_id, 'event_category' );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}
	$existing = (string) get_term_meta( $term_id, 'vatan_emoji', true );
	if ( $existing ) {
		return;
	}
	$emoji = vatan_resolve_category_emoji( $term );
	if ( $emoji ) {
		update_term_meta( $term_id, 'vatan_emoji', $emoji );
	}
}
add_action( 'created_event_category', 'vatan_autoemoji_event_category', 50, 1 );
add_action( 'edited_event_category',  'vatan_autoemoji_event_category', 50, 1 );

/**
 * One-shot admin tool to backfill emojis on existing terms.
 * Visit /wp-admin/?vatan_emoji_backfill=1 as an admin.
 */
add_action( 'admin_init', function () {
	if ( empty( $_GET['vatan_emoji_backfill'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	$terms = get_terms( array( 'taxonomy' => 'event_category', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		wp_die( 'No categories.' );
	}

	$lines = array();
	foreach ( $terms as $term ) {
		$existing = (string) get_term_meta( $term->term_id, 'vatan_emoji', true );
		$emoji    = vatan_resolve_category_emoji( $term );

		if ( $existing && ! empty( $_GET['force'] ) && $emoji ) {
			update_term_meta( $term->term_id, 'vatan_emoji', $emoji );
			$lines[] = "[over ] #{$term->term_id} {$term->slug} ({$term->name}) → {$emoji} (was {$existing})";
		} elseif ( ! $existing && $emoji ) {
			update_term_meta( $term->term_id, 'vatan_emoji', $emoji );
			$lines[] = "[set  ] #{$term->term_id} {$term->slug} ({$term->name}) → {$emoji}";
		} elseif ( ! $existing ) {
			$lines[] = "[miss ] #{$term->term_id} {$term->slug} ({$term->name}) — no map entry";
		} else {
			$lines[] = "[skip ] #{$term->term_id} {$term->slug} ({$term->name}) — already {$existing}";
		}
	}

	wp_die(
		'<h1>Category emoji backfill</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( implode( "\n", $lines ) )
		. '</pre><p><a href="' . esc_url( home_url( '/' ) ) . '">→ Homepage</a></p>',
		'Emoji backfill',
		array( 'response' => 200 )
	);
} );
