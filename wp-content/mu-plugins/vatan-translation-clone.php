<?php
/**
 * Plugin Name: Vatan Translation Cloner
 * Description: One-shot tool that creates EN clones of every FA-language
 *              event / page / post / organizer, links them as Polylang
 *              translation pairs, and reuses the same WC ticket products
 *              so checkout from EN URLs works. Demo bridge — for a real
 *              bilingual site, replace each clone's post_content with
 *              actual English text in wp-admin afterwards.
 *
 * Visit /wp-admin/?vatan_clone_translations=1 as an administrator.
 * Idempotent — skips posts that already have an EN translation.
 *
 * Delete the mu-plugin once translation cloning is done.
 */

defined( 'ABSPATH' ) || exit;

const VATAN_CLONE_TARGET_LANG = 'en';

add_action( 'admin_init', function () {
	if ( empty( $_GET['vatan_clone_translations'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}
	$log = vatan_clone_run();
	wp_die(
		'<h1>Vatan translation cloner</h1><pre style="direction:ltr;text-align:left;background:#f6f7f7;padding:16px;border:1px solid #ccd0d4;white-space:pre-wrap">'
		. esc_html( $log )
		. '</pre><p><a href="' . esc_url( home_url( '/' ) ) . '">→ Homepage</a></p>',
		'Translation cloner',
		array( 'response' => 200 )
	);
} );

function vatan_clone_run(): string {
	if ( ! function_exists( 'pll_set_post_language' ) ||
	     ! function_exists( 'pll_get_post' ) ||
	     ! function_exists( 'pll_save_post_translations' ) ) {
		return 'Polylang is not active — nothing to do.';
	}

	$log   = array();
	$lang  = VATAN_CLONE_TARGET_LANG;

	// Don't fire the event → WC product sync on our clones. The clone
	// shares the FA original's products via the _vatan_event_id meta
	// we copy below, so we don't want duplicate products.
	remove_action( 'save_post_event', 'vatan_sync_event_ticket_products', 30 );

	$types = array( 'page', 'post', 'event', 'organizer' );

	foreach ( $types as $type ) {
		if ( ! post_type_exists( $type ) ) {
			continue;
		}

		$fa_posts = get_posts( array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'lang'           => 'fa',
			'fields'         => 'ids',
		) );

		$created = $skipped = $errors = 0;

		foreach ( $fa_posts as $fa_id ) {
			$fa_id = (int) $fa_id;

			// Skip if there's already an EN translation linked.
			if ( pll_get_post( $fa_id, $lang ) ) {
				$skipped++;
				continue;
			}

			$fa_post = get_post( $fa_id );
			if ( ! $fa_post ) {
				continue;
			}

			// Clone the post — content is identical to the FA original.
			$en_id = wp_insert_post( array(
				'post_type'    => $fa_post->post_type,
				'post_status'  => $fa_post->post_status,
				'post_title'   => $fa_post->post_title,
				'post_content' => $fa_post->post_content,
				'post_excerpt' => $fa_post->post_excerpt,
				'post_name'    => $fa_post->post_name . '-en',
				'post_parent'  => $fa_post->post_parent,
				'post_author'  => $fa_post->post_author,
				'menu_order'   => $fa_post->menu_order,
				'meta_input'   => array( '_vatan_cloned_from' => $fa_id ),
			), true );

			if ( is_wp_error( $en_id ) ) {
				$errors++;
				$log[] = '[fail ] ' . $type . ' #' . $fa_id . ': ' . $en_id->get_error_message();
				continue;
			}

			// Copy all postmeta verbatim — featured image, ACF fields, the
			// _vatan_event_id link (so EN events reference the same WC
			// products as their FA originals), seat-map JSON, etc.
			$meta = get_post_meta( $fa_id );
			foreach ( $meta as $key => $values ) {
				if ( str_starts_with( $key, '_pll_' ) || str_starts_with( $key, '_edit_' ) ) {
					continue;
				}
				delete_post_meta( $en_id, $key );
				foreach ( (array) $values as $v ) {
					add_post_meta( $en_id, $key, maybe_unserialize( $v ) );
				}
			}

			// Copy taxonomy assignments (categories, cities, tags …).
			foreach ( get_object_taxonomies( $type ) as $tx ) {
				if ( 'language' === $tx || str_starts_with( $tx, 'post_translations' ) ) {
					continue;
				}
				$terms = wp_get_object_terms( $fa_id, $tx, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					// Map each FA term to its EN counterpart if one exists,
					// otherwise reuse the FA term (Polylang will treat it as
					// "no language" and let the EN post use it directly).
					$en_terms = array();
					foreach ( $terms as $tid ) {
						if ( function_exists( 'pll_get_term' ) ) {
							$en_tid = pll_get_term( (int) $tid, $lang );
							$en_terms[] = $en_tid ? (int) $en_tid : (int) $tid;
						} else {
							$en_terms[] = (int) $tid;
						}
					}
					wp_set_object_terms( $en_id, $en_terms, $tx, false );
				}
			}

			// Mark language + link as translation pair.
			pll_set_post_language( $en_id, $lang );
			pll_save_post_translations( array(
				'fa' => $fa_id,
				'en' => $en_id,
			) );

			$created++;
			$log[] = '[ok   ] ' . $type . ' #' . $fa_id . ' → #' . $en_id . ' (' . $fa_post->post_title . ')';
		}

		$log[] = sprintf( '[%s] created=%d, skipped=%d, errors=%d', $type, $created, $skipped, $errors );
		$log[] = '';
	}

	// Re-attach the WC product sync hook so future event saves work normally.
	add_action( 'save_post_event', 'vatan_sync_event_ticket_products', 30 );

	$log[] = '[done] Translation pairs built. Edit each EN clone in wp-admin to write real English content.';
	return implode( "\n", $log );
}
