<?php
/**
 * Plugin Name: Vatan Taxonomy Cleanup
 * Description: Admin tools to (1) audit event_category and event_city terms
 *              and (2) merge duplicates. Visit:
 *                /wp-admin/?vatan_tax_audit=1   → read-only audit report
 *                /wp-admin/?vatan_tax_merge=1   → merge duplicates by name
 *              Delete this file after cleanup.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
	if ( ! empty( $_GET['vatan_tax_audit'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		vatan_tax_print( vatan_tax_audit() );
	}
	if ( ! empty( $_GET['vatan_tax_merge'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		vatan_tax_print( vatan_tax_merge_duplicates() );
	}
	if ( ! empty( $_GET['vatan_tax_set_lang'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		$target = is_string( $_GET['vatan_tax_set_lang'] ) ? sanitize_key( wp_unslash( $_GET['vatan_tax_set_lang'] ) ) : 'fa';
		vatan_tax_print( vatan_tax_assign_language( $target ?: 'fa' ) );
	}
	if ( ! empty( $_GET['vatan_tax_set_flags'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		vatan_tax_print( vatan_tax_assign_flags() );
	}
	if ( ! empty( $_GET['vatan_tax_debug_search'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}
		$cat = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : 'concert';
		vatan_tax_print( vatan_tax_debug_search( $cat ) );
	}
} );

function vatan_tax_print( string $body ): void {
	wp_die(
		'<h1>Vatan taxonomy tools</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( $body )
		. '</pre><p>'
		. '<a href="' . esc_url( admin_url( '?vatan_tax_audit=1' ) ) . '">Audit</a> &middot; '
		. '<a href="' . esc_url( admin_url( '?vatan_tax_merge=1' ) ) . '">Merge duplicates</a> &middot; '
		. '<a href="' . esc_url( admin_url( '?vatan_tax_set_lang=fa' ) ) . '">Set events language → fa</a> &middot; '
		. '<a href="' . esc_url( admin_url( '?vatan_tax_set_flags=1' ) ) . '">Set country flags</a> &middot; '
		. '<a href="' . esc_url( admin_url() ) . '">Back to dashboard</a>'
		. '</p>',
		'Taxonomy tools',
		array( 'response' => 200 )
	);
}

/**
 * Force a Polylang language onto every `event` post + on every term in the
 * event_category and event_city taxonomies that's currently unassigned.
 *
 * The REST search endpoint applies a `lang=<current>` filter for Persian
 * visitors. Posts/terms with no language assignment get filtered out.
 *
 * Idempotent — already-assigned posts/terms are left alone.
 *
 * @param string $lang Slug, e.g. 'fa' or 'en'.
 */
function vatan_tax_assign_language( string $lang ): string {
	if ( ! function_exists( 'pll_set_post_language' ) ) {
		return 'Polylang is not active — nothing to do.';
	}

	$out = array();
	$out[] = 'Target language: ' . $lang;
	$out[] = '';

	// --- Events --------------------------------------------------------
	$events = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	$set = 0;
	foreach ( $events as $eid ) {
		$existing = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $eid ) : '';
		if ( $existing === $lang ) {
			continue;
		}
		pll_set_post_language( $eid, $lang );
		$set++;
	}
	$out[] = sprintf( 'events: %d post(s) set to %s (out of %d)', $set, $lang, count( $events ) );

	// --- Linked WooCommerce ticket products ----------------------------
	$products = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_vatan_event_id',
		'fields'         => 'ids',
	) );
	$set = 0;
	foreach ( $products as $pid ) {
		$existing = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $pid ) : '';
		if ( $existing === $lang ) {
			continue;
		}
		pll_set_post_language( $pid, $lang );
		$set++;
	}
	$out[] = sprintf( 'ticket products: %d set to %s (out of %d)', $set, $lang, count( $products ) );

	// --- Taxonomy terms ------------------------------------------------
	if ( function_exists( 'pll_set_term_language' ) ) {
		foreach ( array( 'event_category', 'event_city' ) as $tax ) {
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
			$set   = 0;
			foreach ( $terms as $t ) {
				$existing = function_exists( 'pll_get_term_language' ) ? pll_get_term_language( $t->term_id ) : '';
				if ( $existing === $lang ) {
					continue;
				}
				pll_set_term_language( $t->term_id, $lang );
				$set++;
			}
			$out[] = sprintf( '%s terms: %d set to %s (out of %d)', $tax, $set, $lang, count( $terms ) );
		}
	}

	return implode( "\n", $out );
}

/**
 * Restore `vatan_country_flag` term meta on top-level event_city terms.
 * The merge step kept the `-fa` clones, which didn't have the flag meta
 * the original seeder set on the now-deleted English-slug terms.
 *
 * Idempotent — re-running just rewrites the same emojis.
 */
function vatan_tax_assign_flags(): string {
	$flags = array(
		'germany'        => '🇩🇪',
		'united-kingdom' => '🇬🇧',
		'sweden'         => '🇸🇪',
		'netherlands'    => '🇳🇱',
		'france'         => '🇫🇷',
		'austria'        => '🇦🇹',
		'italy'          => '🇮🇹',
		'spain'          => '🇪🇸',
		'belgium'        => '🇧🇪',
		'switzerland'    => '🇨🇭',
		'denmark'        => '🇩🇰',
		'norway'         => '🇳🇴',
		'finland'        => '🇫🇮',
		'canada'         => '🇨🇦',
		'usa'            => '🇺🇸',
		'united-states'  => '🇺🇸',
		// Also match the Persian display names in case slugs differ.
		'آلمان'         => '🇩🇪',
		'انگلستان'      => '🇬🇧',
		'سوئد'          => '🇸🇪',
		'هلند'          => '🇳🇱',
		'فرانسه'        => '🇫🇷',
		'اتریش'         => '🇦🇹',
	);

	// Top-level (parent=0) event_city terms only — those are "countries"
	// in our nested taxonomy.
	$countries = get_terms( array(
		'taxonomy'   => 'event_city',
		'parent'     => 0,
		'hide_empty' => false,
	) );
	if ( is_wp_error( $countries ) || empty( $countries ) ) {
		return 'No top-level event_city terms found.';
	}

	$out = array();
	$out[] = 'Top-level event_city terms found: ' . count( $countries );
	$out[] = '';

	foreach ( $countries as $term ) {
		$flag = '';
		// Match by slug, name, or slug without `-fa` suffix.
		$slug_clean = preg_replace( '/-fa$/', '', $term->slug );
		foreach ( array( $term->slug, $slug_clean, mb_strtolower( $term->name ) ) as $key ) {
			if ( isset( $flags[ $key ] ) ) {
				$flag = $flags[ $key ];
				break;
			}
		}

		if ( $flag ) {
			update_term_meta( $term->term_id, 'vatan_country_flag', $flag );
			$out[] = sprintf( '  [ok ] #%-4d %-22s "%s" → %s', $term->term_id, $term->slug, $term->name, $flag );
		} else {
			$out[] = sprintf( '  [skip] #%-4d %-22s "%s" — no flag mapped', $term->term_id, $term->slug, $term->name );
		}
	}

	return implode( "\n", $out );
}

/**
 * Replay the exact query the REST endpoint runs for a category filter,
 * dump the term lookup, the WP_Query SQL, the result count, and the
 * Polylang language assignment of each matching event. Tells us where
 * exactly the chain is breaking.
 */
function vatan_tax_debug_search( string $category_slug ): string {
	$out = array();
	$out[] = 'Category slug submitted: ' . $category_slug;
	$out[] = '';

	// 1. Term lookup
	$term = get_term_by( 'slug', $category_slug, 'event_category' );
	if ( ! $term ) {
		$out[] = '[FAIL] No term found with slug "' . $category_slug . '" in event_category.';
		return implode( "\n", $out );
	}
	$out[] = '[ok] term #' . $term->term_id . ' name="' . $term->name . '" count=' . $term->count;
	if ( function_exists( 'pll_get_term_language' ) ) {
		$tlang = pll_get_term_language( $term->term_id ) ?: '(unassigned)';
		$out[] = '     Polylang term language: ' . $tlang;
	}
	$out[] = '';

	// 2. Posts directly attached to the term
	$objects = get_objects_in_term( $term->term_id, 'event_category' );
	$out[] = '[ok] Raw post IDs attached to term: ' . implode( ',', array_map( 'intval', $objects ) );
	foreach ( $objects as $pid ) {
		$p     = get_post( (int) $pid );
		$plang = function_exists( 'pll_get_post_language' ) ? ( pll_get_post_language( (int) $pid ) ?: '(none)' ) : '-';
		$out[] = '     #' . $pid . ' "' . ( $p ? $p->post_title : '(missing)' ) . '" status=' . ( $p ? $p->post_status : '?' ) . ' lang=' . $plang;
	}
	$out[] = '';

	// 3. Replay the REST query — with the lang filter
	$lang_current = function_exists( 'vatan_current_lang' ) ? vatan_current_lang() : 'fa';
	$out[] = '[query] running WP_Query with lang=' . $lang_current . ' …';
	$q1 = new WP_Query( array(
		'post_type'   => 'event',
		'post_status' => 'publish',
		'tax_query'   => array(
			array(
				'taxonomy' => 'event_category',
				'field'    => 'slug',
				'terms'    => $category_slug,
			),
		),
		'lang'        => $lang_current,
	) );
	$out[] = 'WITH lang filter: found ' . $q1->found_posts . ' post(s).';
	$out[] = 'SQL: ' . preg_replace( '/\s+/', ' ', $q1->request );
	$out[] = '';

	// 4. Same query, no lang filter
	$out[] = '[query] running WP_Query WITHOUT lang filter …';
	$q2 = new WP_Query( array(
		'post_type'   => 'event',
		'post_status' => 'publish',
		'tax_query'   => array(
			array(
				'taxonomy' => 'event_category',
				'field'    => 'slug',
				'terms'    => $category_slug,
			),
		),
	) );
	$out[] = 'NO lang filter: found ' . $q2->found_posts . ' post(s).';
	$out[] = 'SQL: ' . preg_replace( '/\s+/', ' ', $q2->request );

	return implode( "\n", $out );
}

function vatan_tax_audit(): string {
	$out = array();
	foreach ( array( 'event_category', 'event_city' ) as $tax ) {
		$out[] = strtoupper( $tax ) . ':';
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			$out[] = '  (no terms)';
			continue;
		}
		foreach ( $terms as $t ) {
			$lang = function_exists( 'pll_get_term_language' )
				? ( pll_get_term_language( $t->term_id ) ?: '-' )
				: '-';
			$out[] = sprintf(
				'  #%-4d parent=%-4d slug=%-22s name=%-30s lang=%s count=%d',
				$t->term_id, $t->parent, $t->slug, $t->name, $lang, $t->count
			);
		}
		$out[] = '';
	}
	return implode( "\n", $out );
}

/**
 * Merge duplicate terms within each taxonomy.
 *
 * Groups terms by their normalized name; if a name appears more than once,
 * picks the one with the most events as the keeper, re-tags any posts from
 * the duplicates, then deletes the duplicates.
 *
 * Keeper rules (in priority order, applied per name-group):
 *   1. Largest `count` (the term that actually has events on it).
 *   2. Polylang `-fa` slug, since the front-end is Persian-first.
 *   3. Lowest term_id (= oldest).
 */
function vatan_tax_merge_duplicates(): string {
	$out = array();
	foreach ( array( 'event_category', 'event_city' ) as $taxonomy ) {
		$out[] = '=== ' . $taxonomy . ' merges ===';
		$out[] = vatan_tax_merge_taxonomy( $taxonomy );
		$out[] = '';
	}
	return implode( "\n", $out );
}

function vatan_tax_merge_taxonomy( string $taxonomy ): string {
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '  (no terms)';
	}

	// Group by lowercase, whitespace-stripped name.
	$groups = array();
	foreach ( $terms as $t ) {
		$key             = mb_strtolower( trim( $t->name ) );
		$groups[ $key ][] = $t;
	}

	$lines = array();
	foreach ( $groups as $name => $group ) {
		if ( count( $group ) < 2 ) {
			continue;
		}

		// Pick the keeper.
		usort( $group, function ( $a, $b ) {
			if ( $a->count !== $b->count ) {
				return $b->count - $a->count; // most posts first
			}
			$a_fa = (int) ( substr( $a->slug, -3 ) === '-fa' );
			$b_fa = (int) ( substr( $b->slug, -3 ) === '-fa' );
			if ( $a_fa !== $b_fa ) {
				return $b_fa - $a_fa; // prefer the -fa slug (Persian front-end)
			}
			return $a->term_id - $b->term_id; // oldest as tiebreak
		} );
		$keeper = $group[0];

		$moves   = 0;
		$deleted = 0;
		for ( $i = 1; $i < count( $group ); $i++ ) {
			$dup   = $group[ $i ];
			$posts = get_objects_in_term( $dup->term_id, $taxonomy );
			if ( ! is_wp_error( $posts ) ) {
				foreach ( $posts as $pid ) {
					wp_set_object_terms( (int) $pid, array( (int) $keeper->term_id ), $taxonomy, true );
					$moves++;
				}
			}
			wp_delete_term( $dup->term_id, $taxonomy );
			$deleted++;
		}

		// Move the keeper's slug to the canonical short form so URLs are clean
		// (e.g. `concert-fa` → `concert`). Skip if the short slug is now taken.
		$short = preg_replace( '/-fa$/', '', $keeper->slug );
		if ( $short !== $keeper->slug && ! get_term_by( 'slug', $short, $taxonomy ) ) {
			wp_update_term( $keeper->term_id, $taxonomy, array( 'slug' => $short ) );
			$keeper = get_term( $keeper->term_id, $taxonomy );
		}

		$lines[] = sprintf(
			'  "%s": kept #%d %s — re-tagged %d post(s), deleted %d duplicate(s).',
			$keeper->name,
			$keeper->term_id,
			$keeper->slug,
			$moves,
			$deleted
		);
	}

	if ( ! $lines ) {
		return '  (no duplicates)';
	}
	return implode( "\n", $lines );
}
