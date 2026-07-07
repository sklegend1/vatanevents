<?php
/**
 * Per-event view counter.
 *
 * Counts unique views per browser session (cookie-deduped), not raw page
 * hits — refreshing the same event page doesn't inflate the number. Stored
 * as `vatan_view_count` post meta on the event.
 *
 * Hooks:
 *   - `template_redirect` (priority 20) → record a view on single-event pages.
 *   - Filter `vatan_record_event_view` lets plugins opt a request out
 *     (e.g. bot-detection, admin previews).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_VIEW_META_KEY     = 'vatan_view_count';
const VATAN_VIEW_COOKIE       = 'vatan_viewed_events';
const VATAN_VIEW_COOKIE_TTL   = WEEK_IN_SECONDS; // re-credit a view after a week

/**
 * Decide whether the current request should count as a view, and if so,
 * increment the counter exactly once for this browser-session×event pair.
 */
function vatan_maybe_record_event_view() {
	if ( ! is_singular( 'event' ) ) {
		return;
	}
	// Don't count admin/editor previews — they're not real visitors.
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}
	// REST / cron / CLI never count.
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	$event_id = get_queried_object_id();
	if ( ! $event_id ) {
		return;
	}

	/**
	 * Lets plugins block counting for specific requests (bot detection,
	 * staging, etc.). Return false to skip.
	 *
	 * @param bool $should  Default true.
	 * @param int  $event_id
	 */
	if ( ! apply_filters( 'vatan_record_event_view', true, $event_id ) ) {
		return;
	}

	// Cookie dedup — only count first view of this event per session.
	$viewed = array();
	if ( isset( $_COOKIE[ VATAN_VIEW_COOKIE ] ) ) {
		$decoded = json_decode( wp_unslash( $_COOKIE[ VATAN_VIEW_COOKIE ] ), true );
		if ( is_array( $decoded ) ) {
			$viewed = array_map( 'intval', $decoded );
		}
	}

	if ( in_array( $event_id, $viewed, true ) ) {
		return; // already counted this session
	}

	$count = (int) get_post_meta( $event_id, VATAN_VIEW_META_KEY, true );
	update_post_meta( $event_id, VATAN_VIEW_META_KEY, $count + 1 );

	$viewed[] = $event_id;
	// Cap the cookie at 200 ids to avoid header bloat for power users.
	if ( count( $viewed ) > 200 ) {
		$viewed = array_slice( $viewed, -200 );
	}
	$cookie_value = wp_json_encode( $viewed );

	// `setcookie` is fine at template_redirect time (before output_buffering ends).
	setcookie(
		VATAN_VIEW_COOKIE,
		(string) $cookie_value,
		array(
			'expires'  => time() + VATAN_VIEW_COOKIE_TTL,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => false, // not security-sensitive; cookie holds public event ids
			'samesite' => 'Lax',
		)
	);
	// Update the in-memory cookie so subsequent code on this request sees it.
	$_COOKIE[ VATAN_VIEW_COOKIE ] = $cookie_value;
}
add_action( 'template_redirect', 'vatan_maybe_record_event_view', 20 );

/**
 * Read the stored view count for an event.
 *
 * @param int $event_id
 * @return int
 */
function vatan_event_view_count( $event_id ) {
	return max( 0, (int) get_post_meta( (int) $event_id, VATAN_VIEW_META_KEY, true ) );
}

/**
 * Pretty-print a view count for the UI. Uses Persian digits when the
 * locale is fa_*, abbreviates above 1k / 1M so the label stays compact.
 *
 * @param int $count
 * @return string
 */
function vatan_format_view_count( $count ) {
	$count = (int) $count;
	if ( $count < 1000 ) {
		$out = (string) $count;
	} elseif ( $count < 1000000 ) {
		$value = $count / 1000;
		$out   = ( $value >= 10 ? round( $value ) : number_format( $value, 1 ) ) . 'K';
	} else {
		$value = $count / 1000000;
		$out   = ( $value >= 10 ? round( $value ) : number_format( $value, 1 ) ) . 'M';
	}
	return function_exists( 'vatan_to_persian_digits' ) ? vatan_to_persian_digits( $out ) : $out;
}

/**
 * Convenience markup: emoji-icon + formatted count. Returns empty when
 * the event has no recorded views yet (so we don't show "0 views" — feels
 * worse than showing nothing).
 *
 * @param int  $event_id
 * @param bool $always Pass true to render even at zero views.
 * @return string Safe HTML.
 */
function vatan_event_views_badge( $event_id, $always = false ) {
	$count = vatan_event_view_count( $event_id );
	if ( ! $always && $count < 1 ) {
		return '';
	}
	return sprintf(
		'<span class="event-views" aria-label="%1$s"><span class="event-views__icon" aria-hidden="true">👁</span><span class="event-views__count">%2$s</span></span>',
		esc_attr__( 'Views', 'vatan-event' ),
		esc_html( vatan_format_view_count( $count ) )
	);
}

/**
 * Helper for a future "trending events" surface: returns event IDs ordered
 * by view count, descending, filtered to upcoming-only by default.
 *
 * @param int  $limit
 * @param bool $upcoming_only
 * @return int[]
 */
function vatan_get_trending_event_ids( $limit = 6, $upcoming_only = true ) {
	$args = array(
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => max( 1, (int) $limit ),
		'no_found_rows'  => true,
		'meta_key'       => VATAN_VIEW_META_KEY,
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	);
	if ( $upcoming_only ) {
		$today = current_time( 'Y-m-d' );
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'AND',
			array(
				'key'     => VATAN_VIEW_META_KEY,
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'event_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		);
	}
	$q = new WP_Query( $args );
	return array_map( 'intval', $q->posts );
}
