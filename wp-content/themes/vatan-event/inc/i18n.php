<?php
/**
 * Polylang integration — makes the Vatan Event types translatable, exposes
 * translatable theme-settings strings, and gives the rest of the codebase
 * a uniform `vatan_current_lang()` helper that works with Polylang, WPML,
 * or no plugin at all.
 *
 * Polylang Setup checklist for the admin (one-time):
 *   1. Languages → Add the languages you want (e.g. fa, en).
 *   2. Set one as default. Polylang will offer to assign existing content.
 *   3. Languages → Settings → URL modifications: pick a URL format
 *      (recommended: `Add /language/ slug` or `Use directory`).
 *   4. Languages → Strings translations: translate the registered strings
 *      this file exposes (theme tagline, newsletter copy, etc.).
 *   5. Posts / Cities / Categories — each item gets a language column;
 *      use the "+" buttons there to create translations.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   Register CPTs + taxonomies as translatable
   ============================================================ */

/**
 * Add our custom post types to Polylang's translatable list. Polylang
 * already auto-detects most public CPTs, but explicitly listing them
 * guarantees we get the language column / language picker in wp-admin
 * even after a Polylang update changes its auto-detection rules.
 *
 * @param string[] $types
 * @return string[]
 */
function vatan_polylang_translatable_post_types( $types ) {
	$add = array( 'event', 'organizer' );
	foreach ( $add as $type ) {
		if ( post_type_exists( $type ) && ! in_array( $type, $types, true ) ) {
			$types[] = $type;
		}
	}
	return $types;
}
add_filter( 'pll_get_post_types', 'vatan_polylang_translatable_post_types', 10, 1 );

/**
 * Same for taxonomies. Make event categories and cities translatable so
 * each language gets its own term tree (e.g. "Concert" / "کنسرت").
 *
 * @param string[] $taxonomies
 * @return string[]
 */
function vatan_polylang_translatable_taxonomies( $taxonomies ) {
	$add = array( 'event_category', 'event_city' );
	foreach ( $add as $tax ) {
		if ( taxonomy_exists( $tax ) && ! in_array( $tax, $taxonomies, true ) ) {
			$taxonomies[] = $tax;
		}
	}
	return $taxonomies;
}
add_filter( 'pll_get_taxonomies', 'vatan_polylang_translatable_taxonomies', 10, 1 );

/* ============================================================
   Current-language helper (Polylang / WPML / fallback)
   ============================================================ */

/**
 * Best-effort current-language code. Returns a 2-letter ISO code.
 *
 *   1. Polylang's pll_current_language() if Polylang is active.
 *   2. WPML's ICL_LANGUAGE_CODE if WPML is active.
 *   3. First two characters of get_locale() (e.g. 'fa' from 'fa_IR').
 *
 * @return string
 */
function vatan_current_lang() {
	if ( function_exists( 'pll_current_language' ) ) {
		$code = (string) pll_current_language( 'slug' );
		if ( $code ) {
			return $code;
		}
	}
	if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		return (string) ICL_LANGUAGE_CODE;
	}
	$locale = get_locale();
	return strtolower( substr( $locale, 0, 2 ) );
}

/**
 * Return all configured languages as [{slug, name, locale, flag}, ...].
 * Falls back to a hardcoded fa+en pair when no multilingual plugin is
 * active, so the front-end language switcher keeps something to render.
 *
 * @return array<int,array{slug:string,name:string,locale:string,flag:string}>
 */
function vatan_available_languages() {
	if ( function_exists( 'pll_languages_list' ) ) {
		$slugs = (array) pll_languages_list( array( 'fields' => '' ) );
		// Polylang returns array of WP_Term-like objects when fields=''.
		// Normalise to a flat shape.
		$out = array();
		foreach ( $slugs as $lang ) {
			if ( is_object( $lang ) ) {
				$out[] = array(
					'slug'   => (string) ( $lang->slug ?? '' ),
					'name'   => (string) ( $lang->name ?? '' ),
					'locale' => (string) ( $lang->locale ?? '' ),
					'flag'   => (string) ( $lang->flag ?? '' ),
				);
			} elseif ( is_string( $lang ) ) {
				$out[] = array( 'slug' => $lang, 'name' => strtoupper( $lang ), 'locale' => '', 'flag' => '' );
			}
		}
		if ( $out ) {
			return $out;
		}
	}

	// No multilingual plugin → fall back to fa + en pair we used previously.
	return array(
		array( 'slug' => 'fa', 'name' => 'فارسی',   'locale' => 'fa_IR', 'flag' => '' ),
		array( 'slug' => 'en', 'name' => 'English', 'locale' => 'en_US', 'flag' => '' ),
	);
}

/* ============================================================
   String Translation — register theme-settings strings
   ============================================================ */

/**
 * Register theme-settings strings with Polylang so admins can translate
 * them per language under Languages → Strings translations. Only fires
 * when Polylang is active (function_exists check on the helper). Hooked
 * to `init` so it runs after settings are loaded but before front-end
 * rendering.
 */
function vatan_register_polylang_strings() {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}
	if ( ! function_exists( 'vatan_get_setting' ) ) {
		return;
	}

	$group = __( 'Vatan Event', 'vatan-event' );

	$strings = array(
		'newsletter_title'    => (string) vatan_get_setting( 'newsletter_title' ),
		'newsletter_subtitle' => (string) vatan_get_setting( 'newsletter_subtitle' ),
		'footer_about'        => (string) vatan_get_setting( 'footer_about' ),
		'hero_title'          => (string) vatan_get_setting( 'hero_title' ),
		'hero_subtitle'       => (string) vatan_get_setting( 'hero_subtitle' ),
		'hero_btn_label'      => (string) vatan_get_setting( 'hero_btn_label' ),
	);

	foreach ( $strings as $name => $value ) {
		if ( '' === $value ) {
			continue; // pll_register_string ignores empty values anyway, save the call
		}
		pll_register_string( $name, $value, $group );
	}

	// Per-slide hero text (loop over the repeater).
	$slides = (array) vatan_get_setting( 'hero_slides' );
	foreach ( $slides as $i => $slide ) {
		foreach ( array( 'eyebrow', 'title', 'title_highlight', 'subtitle', 'primary_label', 'secondary_label' ) as $key ) {
			$value = isset( $slide[ $key ] ) ? (string) $slide[ $key ] : '';
			if ( '' !== $value ) {
				pll_register_string( 'hero_slides[' . $i . '][' . $key . ']', $value, $group );
			}
		}
	}
}
add_action( 'init', 'vatan_register_polylang_strings', 20 );

/**
 * Wrapper that runs a string through Polylang's translation lookup
 * (`pll__`) when available, and falls back to the input verbatim
 * otherwise. Use this in templates for any setting-stored string.
 *
 * @param string $value
 * @return string
 */
function vatan_pll_translate( $value ) {
	if ( '' === $value ) {
		return $value;
	}
	if ( function_exists( 'pll__' ) ) {
		return (string) pll__( $value );
	}
	return $value;
}

/* ============================================================
   Hreflang tags — improve SEO for multilingual sites
   ============================================================ */

/**
 * Emit hreflang alternates in <head> on singular pages. Polylang adds
 * these automatically when configured, but only for translated posts —
 * this helper covers static-style pages that lack translations gracefully.
 *
 * Only emits when at least 2 languages are configured.
 */
function vatan_emit_hreflang_tags() {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return;
	}
	$langs = vatan_available_languages();
	if ( count( $langs ) < 2 ) {
		return;
	}
	// Polylang already injects hreflang via its own filter — let it do its job.
	// This stub stays here so we can add custom logic later if needed.
}
add_action( 'wp_head', 'vatan_emit_hreflang_tags', 5 );

/* ============================================================
   Auto-assign Polylang language to new content

   Posts created via the admin UI get a language from Polylang's
   own metabox flow. But posts created programmatically (REST
   endpoints, mu-plugin seeders, frontend submission handlers,
   `vatan_sync_event_ticket_products`, etc.) skip that flow and
   end up language-less — which makes them invisible to any
   Polylang-filtered query. The fallbacks below close that gap:
   if no language is set after save, we set the admin's current
   language (or site default) so the post is always queryable.
   ============================================================ */

/**
 * Pick a sensible fallback language slug. Priority:
 *   1. Polylang's current language (admin's active language).
 *   2. Polylang's configured default language.
 *   3. The two-letter site locale (e.g. 'fa' from fa_IR).
 *   4. Hardcoded 'fa' (this site is Persian-primary).
 */
function vatan_default_post_language(): string {
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = (string) pll_current_language( 'slug' );
		if ( $lang ) {
			return $lang;
		}
	}
	if ( function_exists( 'pll_default_language' ) ) {
		$lang = (string) pll_default_language( 'slug' );
		if ( $lang ) {
			return $lang;
		}
	}
	$two = strtolower( substr( get_locale(), 0, 2 ) );
	return $two ?: 'fa';
}

/**
 * If a post lands without a Polylang language, assign one. Idempotent.
 * Skips revisions and autosaves.
 *
 * @param int $post_id
 */
function vatan_ensure_post_language( $post_id ) {
	if ( ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_get_post_language' ) ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$existing = (string) pll_get_post_language( $post_id );
	if ( $existing ) {
		return;
	}
	pll_set_post_language( $post_id, vatan_default_post_language() );
}

// Priority 99 so we run AFTER Polylang's own save hooks (which would
// have already set the language if the admin UI picked one).
add_action( 'save_post_event',     'vatan_ensure_post_language', 99, 1 );
add_action( 'save_post_organizer', 'vatan_ensure_post_language', 99, 1 );
add_action( 'save_post_product',   'vatan_ensure_post_language', 99, 1 );
add_action( 'save_post_post',      'vatan_ensure_post_language', 99, 1 );

/**
 * Same pattern for taxonomy terms. Wraps Polylang's `pll_set_term_language`
 * so wp_insert_term() / wp_update_term() calls from custom code always
 * land in a language bucket.
 *
 * @param int    $term_id
 * @param int    $tt_id
 * @param string $taxonomy
 */
function vatan_ensure_term_language( $term_id, $tt_id, $taxonomy ) {
	if ( ! function_exists( 'pll_set_term_language' ) || ! function_exists( 'pll_get_term_language' ) ) {
		return;
	}
	if ( ! in_array( $taxonomy, array( 'event_category', 'event_city' ), true ) ) {
		return;
	}
	$existing = (string) pll_get_term_language( $term_id );
	if ( $existing ) {
		return;
	}
	pll_set_term_language( $term_id, vatan_default_post_language() );
}
add_action( 'created_term', 'vatan_ensure_term_language', 99, 3 );
