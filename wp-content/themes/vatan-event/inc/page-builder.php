<?php
/**
 * Page Builder — component registry, storage, rendering.
 *
 * Architecture:
 *   - `vatan_get_page_builder_components()` returns the registry (filterable).
 *     Each component declares: label, icon, description, prop schema, render
 *     callback. Plugins / child themes can extend via the
 *     `vatan_page_builder_components` filter.
 *
 *   - Layout JSON for each page lives in option `vatan_page_layouts`:
 *       [ 'homepage' => [ {id, type, props}, ... ] ]
 *
 *   - Front-end calls `vatan_render_page_layout( 'homepage' )` from
 *     `front-page.php`. Returns false when no layout is saved; the caller
 *     then renders the legacy hardcoded markup as a fallback.
 *
 *   - Saving is sanitised against the component's prop schema — no untyped
 *     fields make it into the database.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_PAGE_LAYOUTS_OPTION = 'vatan_page_layouts';

/* ============================================================
   1. Component registry
   ============================================================ */

/**
 * @return array<string, array{label:string,icon:string,description:string,props:array,render:callable}>
 */
function vatan_get_page_builder_components() {
	$components = array(

		'hero' => array(
			'label'       => __( 'Hero / Carousel', 'vatan-event' ),
			'icon'        => '🎤',
			'description' => __( 'Full-width carousel. Slides come from Theme Settings → Homepage → Hero slides.', 'vatan-event' ),
			'props'       => array(),
			'render'      => 'vatan_render_section_hero',
		),

		'search_bar' => array(
			'label'       => __( 'Search bar', 'vatan-event' ),
			'icon'        => '🔍',
			'description' => __( 'Search form with city / date / category filters and an AJAX results dropdown.', 'vatan-event' ),
			'props'       => array(
				'floating' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Float over previous section (use under a hero)', 'vatan-event' ),
					'default' => true,
				),
			),
			'render'      => 'vatan_render_section_search_bar',
		),

		'categories_row' => array(
			'label'       => __( 'Category icons row', 'vatan-event' ),
			'icon'        => '📂',
			'description' => __( 'Horizontal strip of clickable event categories (uses the term emoji from Theme Settings → Categories).', 'vatan-event' ),
			'props'       => array(
				'title' => array(
					'type'    => 'text',
					'label'   => __( 'Section title (optional)', 'vatan-event' ),
					'default' => '',
				),
			),
			'render'      => 'vatan_render_section_categories_row',
		),

		'events_grid' => array(
			'label'       => __( 'Events grid', 'vatan-event' ),
			'icon'        => '🎟',
			'description' => __( 'Grid of event cards, optionally filtered by category.', 'vatan-event' ),
			'props'       => array(
				'title'         => array(
					'type'    => 'text',
					'label'   => __( 'Section title', 'vatan-event' ),
					'default' => __( 'Hottest Events', 'vatan-event' ),
				),
				'count'         => array(
					'type'    => 'number',
					'label'   => __( 'Number of events', 'vatan-event' ),
					'default' => 4,
					'min'     => 1,
					'max'     => 24,
				),
				'category'      => array(
					'type'     => 'taxonomy_select',
					'taxonomy' => 'event_category',
					'label'    => __( 'Filter by category (blank = all)', 'vatan-event' ),
					'default'  => '',
				),
				'show_view_all' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Show "View all" link', 'vatan-event' ),
					'default' => true,
				),
				'featured_only' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Featured events only (event_is_featured = true)', 'vatan-event' ),
					'default' => false,
				),
			),
			'render'      => 'vatan_render_section_events_grid',
		),

		'cta_banner' => array(
			'label'       => __( 'CTA banner', 'vatan-event' ),
			'icon'        => '📢',
			'description' => __( 'Full-width call-to-action with title, subtitle, icon, and up to two buttons.', 'vatan-event' ),
			'props'       => array(
				'title'           => array(
					'type'    => 'text',
					'label'   => __( 'Title', 'vatan-event' ),
					'default' => '',
				),
				'subtitle'        => array(
					'type'    => 'textarea',
					'label'   => __( 'Subtitle', 'vatan-event' ),
					'default' => '',
				),
				'icon'            => array(
					'type'    => 'text',
					'label'   => __( 'Icon (emoji)', 'vatan-event' ),
					'default' => '📅',
				),
				'primary_label'   => array(
					'type'    => 'text',
					'label'   => __( 'Primary button — label', 'vatan-event' ),
					'default' => '',
				),
				'primary_url'     => array(
					'type'    => 'url',
					'label'   => __( 'Primary button — URL', 'vatan-event' ),
					'default' => '',
				),
				'secondary_label' => array(
					'type'    => 'text',
					'label'   => __( 'Secondary button — label', 'vatan-event' ),
					'default' => '',
				),
				'secondary_url'   => array(
					'type'    => 'url',
					'label'   => __( 'Secondary button — URL', 'vatan-event' ),
					'default' => '',
				),
				'background'      => array(
					'type'    => 'select',
					'label'   => __( 'Background style', 'vatan-event' ),
					'options' => array(
						'gradient'  => __( 'Gradient (pink → purple)', 'vatan-event' ),
						'primary'   => __( 'Solid primary', 'vatan-event' ),
						'secondary' => __( 'Solid secondary', 'vatan-event' ),
						'surface'   => __( 'Card surface', 'vatan-event' ),
					),
					'default' => 'gradient',
				),
			),
			'render'      => 'vatan_render_section_cta_banner',
		),

		'text_block' => array(
			'label'       => __( 'Text block', 'vatan-event' ),
			'icon'        => '📝',
			'description' => __( 'Heading + paragraph. Use for short copy between content blocks.', 'vatan-event' ),
			'props'       => array(
				'title'     => array(
					'type'    => 'text',
					'label'   => __( 'Heading', 'vatan-event' ),
					'default' => '',
				),
				'body'      => array(
					'type'    => 'textarea',
					'label'   => __( 'Body text', 'vatan-event' ),
					'default' => '',
				),
				'alignment' => array(
					'type'    => 'select',
					'label'   => __( 'Text alignment', 'vatan-event' ),
					'options' => array(
						'start'  => __( 'Start', 'vatan-event' ),
						'center' => __( 'Center', 'vatan-event' ),
						'end'    => __( 'End', 'vatan-event' ),
					),
					'default' => 'start',
				),
			),
			'render'      => 'vatan_render_section_text_block',
		),

		'newsletter' => array(
			'label'       => __( 'Newsletter signup', 'vatan-event' ),
			'icon'        => '✉',
			'description' => __( 'Email subscription form. Title / subtitle pull from Theme Settings → Newsletter.', 'vatan-event' ),
			'props'       => array(),
			'render'      => 'vatan_render_section_newsletter',
		),

		'value_props' => array(
			'label'       => __( 'Value props (3 columns)', 'vatan-event' ),
			'icon'        => '✨',
			'description' => __( 'Three feature highlights with icon + title + short description. Great for "why use us" homepage sections.', 'vatan-event' ),
			'props'       => array(
				'title'        => array(
					'type'    => 'text',
					'label'   => __( 'Section title (optional)', 'vatan-event' ),
					'default' => '',
				),
				'subtitle'     => array(
					'type'    => 'textarea',
					'label'   => __( 'Section subtitle (optional)', 'vatan-event' ),
					'default' => '',
				),
				'item_1_icon'  => array(
					'type'    => 'text',
					'label'   => __( '#1 — Icon (emoji)', 'vatan-event' ),
					'default' => '⚡',
				),
				'item_1_title' => array(
					'type'    => 'text',
					'label'   => __( '#1 — Title', 'vatan-event' ),
					'default' => '',
				),
				'item_1_body'  => array(
					'type'    => 'textarea',
					'label'   => __( '#1 — Description', 'vatan-event' ),
					'default' => '',
				),
				'item_2_icon'  => array(
					'type'    => 'text',
					'label'   => __( '#2 — Icon (emoji)', 'vatan-event' ),
					'default' => '📣',
				),
				'item_2_title' => array(
					'type'    => 'text',
					'label'   => __( '#2 — Title', 'vatan-event' ),
					'default' => '',
				),
				'item_2_body'  => array(
					'type'    => 'textarea',
					'label'   => __( '#2 — Description', 'vatan-event' ),
					'default' => '',
				),
				'item_3_icon'  => array(
					'type'    => 'text',
					'label'   => __( '#3 — Icon (emoji)', 'vatan-event' ),
					'default' => '🎪',
				),
				'item_3_title' => array(
					'type'    => 'text',
					'label'   => __( '#3 — Title', 'vatan-event' ),
					'default' => '',
				),
				'item_3_body'  => array(
					'type'    => 'textarea',
					'label'   => __( '#3 — Description', 'vatan-event' ),
					'default' => '',
				),
			),
			'render'      => 'vatan_render_section_value_props',
		),

		'guides_grid' => array(
			'label'       => __( 'Guides / Blog grid', 'vatan-event' ),
			'icon'        => '📰',
			'description' => __( 'Grid of latest blog posts (the standard `post` post type). Use on the homepage to surface ticket-buying guides, how-tos, and announcements.', 'vatan-event' ),
			'props'       => array(
				'title'         => array(
					'type'    => 'text',
					'label'   => __( 'Section title', 'vatan-event' ),
					'default' => __( 'Ticket-buying guides', 'vatan-event' ),
				),
				'count'         => array(
					'type'    => 'number',
					'label'   => __( 'Number of posts', 'vatan-event' ),
					'default' => 3,
					'min'     => 1,
					'max'     => 12,
				),
				'category'      => array(
					'type'     => 'taxonomy_select',
					'taxonomy' => 'category',
					'label'    => __( 'Filter by category (blank = all)', 'vatan-event' ),
					'default'  => '',
				),
				'show_view_all' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Show "View all" link', 'vatan-event' ),
					'default' => true,
				),
			),
			'render'      => 'vatan_render_section_guides_grid',
		),

		'partners' => array(
			'label'       => __( 'Partner logos', 'vatan-event' ),
			'icon'        => '🤝',
			'description' => __( 'Horizontal strip of partner / sponsor logos. Add images from the media library — they\'ll render in the order you pick them.', 'vatan-event' ),
			'props'       => array(
				'title'     => array(
					'type'    => 'text',
					'label'   => __( 'Section title (optional)', 'vatan-event' ),
					'default' => __( 'In partnership with', 'vatan-event' ),
				),
				'logos'     => array(
					'type'        => 'media_gallery',
					'label'       => __( 'Logos (one image URL per line)', 'vatan-event' ),
					'default'     => '',
				),
				'grayscale' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Render logos in grayscale (color on hover)', 'vatan-event' ),
					'default' => true,
				),
			),
			'render'      => 'vatan_render_section_partners',
		),

		'country_chips' => array(
			'label'       => __( 'Country chips', 'vatan-event' ),
			'icon'        => '🌍',
			'description' => __( 'Horizontal row of country chips with flag emoji and event count. Click sends users to the country\'s archive (which includes all cities under it). Set the flag emoji on each top-level event_city term.', 'vatan-event' ),
			'props'       => array(
				'title'         => array(
					'type'    => 'text',
					'label'   => __( 'Section title (optional)', 'vatan-event' ),
					'default' => __( 'Browse by country', 'vatan-event' ),
				),
				'count'         => array(
					'type'    => 'number',
					'label'   => __( 'Maximum countries', 'vatan-event' ),
					'default' => 12,
					'min'     => 2,
					'max'     => 32,
				),
				'upcoming_only' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Count only upcoming events', 'vatan-event' ),
					'default' => true,
				),
				'show_count'    => array(
					'type'    => 'checkbox',
					'label'   => __( 'Show event count on each chip', 'vatan-event' ),
					'default' => true,
				),
			),
			'render'      => 'vatan_render_section_country_chips',
		),

		'popular_cities' => array(
			'label'       => __( 'Popular cities', 'vatan-event' ),
			'icon'        => '🏙',
			'description' => __( 'Grid of cities with the most upcoming events. Cover image per city is set on the event_city term edit screen.', 'vatan-event' ),
			'props'       => array(
				'title'         => array(
					'type'    => 'text',
					'label'   => __( 'Section title', 'vatan-event' ),
					'default' => __( 'Popular cities', 'vatan-event' ),
				),
				'count'         => array(
					'type'    => 'number',
					'label'   => __( 'Number of cities', 'vatan-event' ),
					'default' => 8,
					'min'     => 2,
					'max'     => 24,
				),
				'upcoming_only' => array(
					'type'    => 'checkbox',
					'label'   => __( 'Count only upcoming events (event_date ≥ today)', 'vatan-event' ),
					'default' => true,
				),
			),
			'render'      => 'vatan_render_section_popular_cities',
		),

	);

	/**
	 * Filter the available page-builder components.
	 *
	 * @param array $components
	 */
	return apply_filters( 'vatan_page_builder_components', $components );
}

/**
 * Schema reshaped for the JS editor: each component's props become a flat
 * indexed array with a `key` field, and option maps become indexed.
 */
function vatan_page_builder_schema_for_js() {
	$components = vatan_get_page_builder_components();
	$out = array();
	foreach ( $components as $slug => $cmp ) {
		$props = array();
		if ( ! empty( $cmp['props'] ) ) {
			foreach ( $cmp['props'] as $key => $spec ) {
				$entry = array_merge( array( 'key' => $key ), $spec );
				// Resolve taxonomy_select to a literal options list so the JS
				// doesn't need to know about taxonomies.
				if ( 'taxonomy_select' === ( $spec['type'] ?? '' ) && ! empty( $spec['taxonomy'] ) ) {
					$entry['type']    = 'select';
					$entry['options'] = array( '' => __( '— Any —', 'vatan-event' ) );
					$terms = get_terms( array(
						'taxonomy'   => $spec['taxonomy'],
						'hide_empty' => false,
					) );
					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$entry['options'][ $term->slug ] = $term->name;
						}
					}
				}
				// Same pattern for post_select — resolves a CPT to a list of
				// published posts so the JS gets a plain <select>. The blank
				// option represents "auto-pick" semantics defined per-component.
				if ( 'post_select' === ( $spec['type'] ?? '' ) && ! empty( $spec['post_type'] ) ) {
					$entry['type']    = 'select';
					$entry['options'] = array( '' => isset( $spec['empty_label'] ) ? (string) $spec['empty_label'] : __( '— Auto —', 'vatan-event' ) );
					$posts = get_posts( array(
						'post_type'      => $spec['post_type'],
						'post_status'    => 'publish',
						'posts_per_page' => 200,
						'orderby'        => 'title',
						'order'          => 'ASC',
					) );
					foreach ( $posts as $p ) {
						$entry['options'][ (string) $p->ID ] = $p->post_title;
					}
				}
				// Convert PHP associative `options` to the structure the JS expects.
				if ( isset( $entry['options'] ) && is_array( $entry['options'] ) ) {
					$opts = array();
					foreach ( $entry['options'] as $value => $label ) {
						$opts[] = array( 'value' => (string) $value, 'label' => (string) $label );
					}
					$entry['options'] = $opts;
				}
				unset( $entry['render'] );
				$props[] = $entry;
			}
		}
		$out[ $slug ] = array(
			'label'       => $cmp['label'],
			'icon'        => isset( $cmp['icon'] ) ? $cmp['icon'] : '',
			'description' => isset( $cmp['description'] ) ? $cmp['description'] : '',
			'props'       => $props,
		);
	}
	return $out;
}

/* ============================================================
   2. Storage
   ============================================================ */

/**
 * Return the saved blocks for a page slug. Empty array when nothing saved.
 *
 * @param string $page
 * @return array
 */
function vatan_get_page_layout( $page = 'homepage' ) {
	$all = get_option( VATAN_PAGE_LAYOUTS_OPTION, array() );
	if ( ! is_array( $all ) ) {
		return array();
	}
	return isset( $all[ $page ] ) && is_array( $all[ $page ] ) ? $all[ $page ] : array();
}

function vatan_save_page_layout( $page, $layout ) {
	$all = get_option( VATAN_PAGE_LAYOUTS_OPTION, array() );
	if ( ! is_array( $all ) ) {
		$all = array();
	}
	$all[ $page ] = $layout;
	update_option( VATAN_PAGE_LAYOUTS_OPTION, $all );
}

/**
 * Sanitize a layout payload against the registered component schemas.
 * Drops unknown component types and any props not declared in their schema.
 */
function vatan_sanitize_page_layout( $layout ) {
	if ( ! is_array( $layout ) ) {
		return array();
	}
	$components = vatan_get_page_builder_components();
	$clean      = array();

	foreach ( $layout as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
		if ( ! isset( $components[ $type ] ) ) {
			continue;
		}

		$spec_props  = isset( $components[ $type ]['props'] ) ? $components[ $type ]['props'] : array();
		$input_props = isset( $block['props'] ) && is_array( $block['props'] ) ? $block['props'] : array();
		$clean_props = array();
		foreach ( $spec_props as $key => $spec ) {
			$raw                 = array_key_exists( $key, $input_props ) ? $input_props[ $key ] : ( isset( $spec['default'] ) ? $spec['default'] : '' );
			$clean_props[ $key ] = vatan_sanitize_page_builder_prop( $raw, $spec );
		}

		$clean[] = array(
			'id'    => isset( $block['id'] ) ? sanitize_text_field( (string) $block['id'] ) : wp_generate_uuid4(),
			'type'  => $type,
			'props' => $clean_props,
		);
	}
	return $clean;
}

/**
 * One-prop sanitizer dispatched by the prop's declared type.
 */
function vatan_sanitize_page_builder_prop( $value, $spec ) {
	$type = isset( $spec['type'] ) ? $spec['type'] : 'text';
	switch ( $type ) {
		case 'number':
			$v = (int) $value;
			if ( isset( $spec['min'] ) ) {
				$v = max( $v, (int) $spec['min'] );
			}
			if ( isset( $spec['max'] ) ) {
				$v = min( $v, (int) $spec['max'] );
			}
			return $v;

		case 'url':
			return esc_url_raw( (string) $value );

		case 'textarea':
			return sanitize_textarea_field( (string) $value );

		case 'checkbox':
			return (bool) $value;

		case 'select':
			$allowed = array();
			if ( isset( $spec['options'] ) && is_array( $spec['options'] ) ) {
				$allowed = array_keys( $spec['options'] );
			}
			$value = (string) $value;
			return in_array( $value, $allowed, true ) ? $value : ( isset( $spec['default'] ) ? (string) $spec['default'] : '' );

		case 'taxonomy_select':
			return sanitize_text_field( (string) $value );

		case 'post_select':
			// Stored as a string (matches how `select` works in this system).
			// Render callbacks cast to (int) when they want a post ID.
			return (string) absint( $value );

		case 'media_gallery':
			// Stored as a newline-separated list of URLs. Validate each line
			// independently with esc_url_raw, drop empty lines, keep order.
			if ( is_array( $value ) ) {
				$value = implode( "\n", $value );
			}
			$lines = preg_split( '/\r?\n/', (string) $value );
			$clean = array();
			foreach ( (array) $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$url = esc_url_raw( $line );
				if ( $url ) {
					$clean[] = $url;
				}
			}
			return implode( "\n", $clean );

		case 'text':
		default:
			return sanitize_text_field( (string) $value );
	}
}

/* ============================================================
   3. Front-end renderer
   ============================================================ */

/**
 * Render a saved page layout. Returns true when something was rendered,
 * false when no layout is saved (so the caller can render a fallback).
 *
 * @param string $page
 * @return bool
 */
function vatan_render_page_layout( $page = 'homepage' ) {
	$layout = vatan_get_page_layout( $page );
	if ( empty( $layout ) ) {
		return false;
	}
	$components = vatan_get_page_builder_components();

	foreach ( $layout as $block ) {
		$type = isset( $block['type'] ) ? $block['type'] : '';
		if ( ! isset( $components[ $type ] ) ) {
			continue;
		}
		$props  = isset( $block['props'] ) && is_array( $block['props'] ) ? $block['props'] : array();
		$render = $components[ $type ]['render'];
		if ( is_callable( $render ) ) {
			call_user_func( $render, $props );
		}
	}
	return true;
}

/* ============================================================
   4. Section render callbacks
   ============================================================ */

function vatan_render_section_hero( $props ) {
	get_template_part( 'template-parts/hero' );
}

function vatan_render_section_search_bar( $props ) {
	get_template_part(
		'template-parts/search-bar',
		null,
		array( 'floating' => ! empty( $props['floating'] ) )
	);
}

function vatan_render_section_newsletter( $props ) {
	get_template_part( 'template-parts/newsletter' );
}

function vatan_render_section_categories_row( $props ) {
	$title = isset( $props['title'] ) ? (string) $props['title'] : '';
	$terms = get_terms( array(
		'taxonomy'   => 'event_category',
		'hide_empty' => true, // skip placeholder duplicates with zero events
	) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}
	?>
	<section class="vatan-section vatan-section--categories">
		<div class="container">
			<?php if ( $title ) : ?>
				<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<ul class="categories-row" data-vatan-anim-children>
				<?php foreach ( $terms as $term ) :
					$emoji = (string) get_term_meta( $term->term_id, 'vatan_emoji', true );
					$url   = get_term_link( $term );
					if ( is_wp_error( $url ) ) {
						$url = '#';
					}
					?>
					<li class="categories-row__item">
						<a href="<?php echo esc_url( $url ); ?>" class="categories-row__link">
							<span class="categories-row__icon" aria-hidden="true"><?php echo esc_html( $emoji ?: '🎫' ); ?></span>
							<span class="categories-row__label"><?php echo esc_html( $term->name ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php
}

function vatan_render_section_events_grid( $props ) {
	$title         = isset( $props['title'] ) ? (string) $props['title'] : '';
	$count         = isset( $props['count'] ) ? (int) $props['count'] : 4;
	$category      = isset( $props['category'] ) ? (string) $props['category'] : '';
	$show_view_all = ! empty( $props['show_view_all'] );
	$featured_only = ! empty( $props['featured_only'] );

	$args = array(
		'post_type'      => 'event',
		'posts_per_page' => $count,
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	);
	if ( $category ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'event_category',
				'field'    => 'slug',
				'terms'    => $category,
			),
		);
	}
	if ( $featured_only ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'event_is_featured',
				'value'   => '1',
				'compare' => '=',
			),
		);
	}
	$q = new WP_Query( $args );

	$archive_url = get_post_type_archive_link( 'event' );
	$archive_url = is_string( $archive_url ) ? $archive_url : home_url( '/' );
	?>
	<section class="vatan-section vatan-section--events">
		<div class="container">
			<header class="section-header">
				<?php if ( $title ) : ?>
					<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $show_view_all ) : ?>
					<a class="section-more" href="<?php echo esc_url( $archive_url ); ?>">
						<?php esc_html_e( 'View all', 'vatan-event' ); ?> →
					</a>
				<?php endif; ?>
			</header>

			<?php if ( $q->have_posts() ) : ?>
				<div class="event-grid" data-vatan-anim-children>
					<?php while ( $q->have_posts() ) : $q->the_post(); ?>
						<?php get_template_part( 'template-parts/event-card' ); ?>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No events to show yet.', 'vatan-event' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
	wp_reset_postdata();
}

function vatan_render_section_cta_banner( $props ) {
	$title    = isset( $props['title'] ) ? (string) $props['title'] : '';
	$subtitle = isset( $props['subtitle'] ) ? (string) $props['subtitle'] : '';
	$icon     = isset( $props['icon'] ) ? (string) $props['icon'] : '';
	$pri_l    = isset( $props['primary_label'] ) ? (string) $props['primary_label'] : '';
	$pri_u    = isset( $props['primary_url'] ) ? (string) $props['primary_url'] : '';
	$sec_l    = isset( $props['secondary_label'] ) ? (string) $props['secondary_label'] : '';
	$sec_u    = isset( $props['secondary_url'] ) ? (string) $props['secondary_url'] : '';
	$bg       = isset( $props['background'] ) ? (string) $props['background'] : 'gradient';

	if ( '' === $title && '' === $subtitle && '' === $pri_l ) {
		return; // empty banner — skip
	}
	?>
	<section class="vatan-section vatan-section--cta">
		<div class="container">
			<div class="cta-banner cta-banner--<?php echo esc_attr( $bg ); ?>">
				<?php if ( $icon ) : ?>
					<div class="cta-banner__icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></div>
				<?php endif; ?>
				<div class="cta-banner__body">
					<?php if ( $title ) : ?>
						<h2 class="cta-banner__title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( $subtitle ) : ?>
						<p class="cta-banner__subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
					<?php if ( $pri_l || $sec_l ) : ?>
						<div class="cta-banner__actions">
							<?php if ( $pri_l ) : ?>
								<a class="btn btn--primary btn--lg" href="<?php echo esc_url( $pri_u ?: '#' ); ?>">
									<?php echo esc_html( $pri_l ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $sec_l ) : ?>
								<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( $sec_u ?: '#' ); ?>">
									<?php echo esc_html( $sec_l ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
	<?php
}

function vatan_render_section_text_block( $props ) {
	$title     = isset( $props['title'] ) ? (string) $props['title'] : '';
	$body      = isset( $props['body'] ) ? (string) $props['body'] : '';
	$alignment = isset( $props['alignment'] ) ? (string) $props['alignment'] : 'start';
	if ( '' === $title && '' === $body ) {
		return;
	}
	?>
	<section class="vatan-section vatan-section--text">
		<div class="container">
			<div class="text-block text-block--<?php echo esc_attr( $alignment ); ?>">
				<?php if ( $title ) : ?>
					<h2 class="text-block__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $body ) : ?>
					<?php echo wpautop( esc_html( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — esc_html'd before wpautop wraps in <p>. ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

function vatan_render_section_country_chips( $props ) {
	$title         = isset( $props['title'] ) ? (string) $props['title'] : '';
	$count         = isset( $props['count'] ) ? (int) $props['count'] : 12;
	$upcoming_only = ! isset( $props['upcoming_only'] ) || ! empty( $props['upcoming_only'] );
	$show_count    = ! isset( $props['show_count'] ) || ! empty( $props['show_count'] );

	if ( ! function_exists( 'vatan_get_country_terms' ) ) {
		return;
	}
	$countries = vatan_get_country_terms( $count, $upcoming_only );
	if ( empty( $countries ) ) {
		return;
	}
	?>
	<section class="vatan-section vatan-section--countries">
		<div class="container">
			<?php if ( $title ) : ?>
				<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<ul class="country-chips" data-vatan-anim-children>
				<?php foreach ( $countries as $entry ) :
					$term  = $entry['term'];
					$flag  = (string) $entry['flag'];
					$count = (int) $entry['count'];
					$url   = get_term_link( $term );
					if ( is_wp_error( $url ) ) {
						$url = '#';
					}
					?>
					<li class="country-chips__item">
						<a class="country-chips__link" href="<?php echo esc_url( $url ); ?>">
							<?php if ( $flag ) : ?>
								<span class="country-chips__flag" aria-hidden="true"><?php echo esc_html( $flag ); ?></span>
							<?php endif; ?>
							<span class="country-chips__body">
								<span class="country-chips__name"><?php echo esc_html( $term->name ); ?></span>
								<?php if ( $show_count ) : ?>
									<span class="country-chips__count">
										<?php
										printf(
											/* translators: %s: number of events */
											esc_html( _n( '%s event', '%s events', $count, 'vatan-event' ) ),
											esc_html( vatan_to_persian_digits( $count ) )
										);
										?>
									</span>
								<?php endif; ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php
}

function vatan_render_section_popular_cities( $props ) {
	$title         = isset( $props['title'] ) ? (string) $props['title'] : '';
	$count         = isset( $props['count'] ) ? (int) $props['count'] : 8;
	$upcoming_only = ! isset( $props['upcoming_only'] ) || ! empty( $props['upcoming_only'] );

	if ( ! function_exists( 'vatan_get_popular_cities' ) ) {
		return;
	}
	$cities = vatan_get_popular_cities( $count, $upcoming_only );
	if ( empty( $cities ) ) {
		return;
	}
	?>
	<section class="vatan-section vatan-section--cities">
		<div class="container">
			<?php if ( $title ) : ?>
				<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<ul class="popular-cities" data-vatan-anim-children>
				<?php foreach ( $cities as $entry ) :
					$term     = $entry['term'];
					$count_n  = (int) $entry['count'];
					$image_id = (int) $entry['image_id'];
					$url      = get_term_link( $term );
					if ( is_wp_error( $url ) ) {
						$url = '#';
					}
					$bg = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
					?>
					<li class="popular-cities__item">
						<a class="popular-cities__card<?php echo $bg ? ' has-image' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $bg ? ' style="background-image: url(' . esc_url( $bg ) . ');"' : ''; ?>>
							<span class="popular-cities__overlay" aria-hidden="true"></span>
							<span class="popular-cities__body">
								<span class="popular-cities__name"><?php echo esc_html( $term->name ); ?></span>
								<span class="popular-cities__count">
									<?php
									printf(
										/* translators: %s: number of events */
										esc_html( _n( '%s event', '%s events', $count_n, 'vatan-event' ) ),
										esc_html( vatan_to_persian_digits( $count_n ) )
									);
									?>
								</span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php
}

function vatan_render_section_value_props( $props ) {
	$title    = isset( $props['title'] ) ? (string) $props['title'] : '';
	$subtitle = isset( $props['subtitle'] ) ? (string) $props['subtitle'] : '';

	$items = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$icon  = isset( $props[ 'item_' . $i . '_icon' ] ) ? (string) $props[ 'item_' . $i . '_icon' ] : '';
		$ititle = isset( $props[ 'item_' . $i . '_title' ] ) ? (string) $props[ 'item_' . $i . '_title' ] : '';
		$ibody  = isset( $props[ 'item_' . $i . '_body' ] ) ? (string) $props[ 'item_' . $i . '_body' ] : '';
		if ( '' === $ititle && '' === $ibody && '' === $icon ) {
			continue;
		}
		$items[] = array( 'icon' => $icon, 'title' => $ititle, 'body' => $ibody );
	}
	if ( empty( $items ) ) {
		return;
	}
	?>
	<section class="vatan-section vatan-section--value-props">
		<div class="container">
			<?php if ( $title || $subtitle ) : ?>
				<header class="value-props__head">
					<?php if ( $title ) : ?>
						<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( $subtitle ) : ?>
						<p class="value-props__subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<ul class="value-props" data-vatan-anim-children>
				<?php foreach ( $items as $item ) : ?>
					<li class="value-props__item">
						<?php if ( $item['icon'] ) : ?>
							<span class="value-props__icon" aria-hidden="true"><?php echo esc_html( $item['icon'] ); ?></span>
						<?php endif; ?>
						<?php if ( $item['title'] ) : ?>
							<h3 class="value-props__title"><?php echo esc_html( $item['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( $item['body'] ) : ?>
							<p class="value-props__body"><?php echo esc_html( $item['body'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php
}

function vatan_render_section_partners( $props ) {
	$title     = isset( $props['title'] ) ? (string) $props['title'] : '';
	$raw       = isset( $props['logos'] ) ? (string) $props['logos'] : '';
	$grayscale = ! isset( $props['grayscale'] ) || ! empty( $props['grayscale'] );

	if ( '' === trim( $raw ) ) {
		return;
	}
	$urls = array_filter( array_map( 'trim', preg_split( '/\r?\n/', $raw ) ) );
	if ( empty( $urls ) ) {
		return;
	}

	$mod = $grayscale ? ' partners--grayscale' : '';
	?>
	<section class="vatan-section vatan-section--partners">
		<div class="container">
			<?php if ( $title ) : ?>
				<h2 class="section-title section-title--quiet"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
			<ul class="partners<?php echo esc_attr( $mod ); ?>" data-vatan-anim-children>
				<?php foreach ( $urls as $url ) : ?>
					<li class="partners__item">
						<img class="partners__logo" src="<?php echo esc_url( $url ); ?>" alt="" loading="lazy" decoding="async" />
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php
}

function vatan_render_section_guides_grid( $props ) {
	$title         = isset( $props['title'] ) ? (string) $props['title'] : '';
	$count         = isset( $props['count'] ) ? (int) $props['count'] : 3;
	$category      = isset( $props['category'] ) ? (string) $props['category'] : '';
	$show_view_all = ! empty( $props['show_view_all'] );

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'no_found_rows'       => true,
		'ignore_sticky_posts' => 1,
	);
	if ( $category ) {
		$args['category_name'] = $category;
	}
	$q = new WP_Query( $args );

	if ( ! $q->have_posts() ) {
		return;
	}

	// Pick the most reasonable "view all" target: dedicated posts page if set,
	// otherwise the post-type archive (which falls back to home.php).
	$archive_url   = '';
	$posts_page_id = (int) get_option( 'page_for_posts' );
	if ( $posts_page_id ) {
		$archive_url = (string) get_permalink( $posts_page_id );
	}
	if ( '' === $archive_url ) {
		$archive_url = (string) ( get_post_type_archive_link( 'post' ) ?: home_url( '/' ) );
	}
	?>
	<section class="vatan-section vatan-section--guides">
		<div class="container">
			<header class="section-header">
				<?php if ( $title ) : ?>
					<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $show_view_all ) : ?>
					<a class="section-more" href="<?php echo esc_url( $archive_url ); ?>">
						<?php esc_html_e( 'View all', 'vatan-event' ); ?> →
					</a>
				<?php endif; ?>
			</header>

			<div class="post-grid" data-vatan-anim-children>
				<?php while ( $q->have_posts() ) :
					$q->the_post();
					get_template_part( 'template-parts/post-card' );
				endwhile; ?>
			</div>
		</div>
	</section>
	<?php
	wp_reset_postdata();
}
