<?php
/**
 * Per-event earnings + ticket-sold counts.
 *
 * Used by the organizer dashboard (`/my-account/my-events/`) to show how
 * each event is performing financially. Revenue is computed from
 * completed + processing WooCommerce orders that carry a line item
 * tagged with `_vatan_event_id` — the same meta our checkout integration
 * already writes.
 *
 * No payout workflow yet — that's a separate admin tool. These helpers
 * just answer "how much money has this event generated so far?".
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Statuses we count as "sold" — completed orders for sure, plus
 * processing (paid, awaiting fulfilment) so the organizer sees their
 * earnings in real time rather than waiting for ship/fulfilment.
 *
 * Filterable so an integrator can include `on-hold` (bank transfer) or
 * exclude `processing` if they only count fully completed sales.
 *
 * @return string[]
 */
function vatan_earnings_counted_statuses() {
	/**
	 * Order statuses counted toward an event's earnings.
	 *
	 * @param string[] $statuses Default: completed + processing.
	 */
	return (array) apply_filters( 'vatan_earnings_counted_statuses', array( 'wc-completed', 'wc-processing' ) );
}

/**
 * Sum the per-event sales (gross, before refunds / fees) across all
 * relevant orders. Cached for the request so repeated calls in the same
 * page render don't hammer wc_get_orders.
 *
 * @param int $event_id
 * @return float  Gross revenue in the WC store currency.
 */
function vatan_event_gross_revenue( $event_id ) {
	$event_id = (int) $event_id;
	if ( ! $event_id || ! function_exists( 'wc_get_orders' ) ) {
		return 0.0;
	}

	static $cache = array();
	if ( isset( $cache[ $event_id ] ) ) {
		return $cache[ $event_id ];
	}

	$orders = wc_get_orders( array(
		'limit'      => -1,
		'status'     => vatan_earnings_counted_statuses(),
	) );

	$total = 0.0;
	if ( is_array( $orders ) ) {
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_meta( '_vatan_event_id' ) !== $event_id ) {
					continue;
				}
				$total += (float) $item->get_total();
			}
		}
	}

	$cache[ $event_id ] = $total;
	return $total;
}

/**
 * Total tickets sold for an event (sum of quantities across all qualifying
 * line items). Caches per request alongside the revenue helper.
 *
 * @param int $event_id
 * @return int
 */
function vatan_event_tickets_sold( $event_id ) {
	$event_id = (int) $event_id;
	if ( ! $event_id || ! function_exists( 'wc_get_orders' ) ) {
		return 0;
	}

	static $cache = array();
	if ( isset( $cache[ $event_id ] ) ) {
		return $cache[ $event_id ];
	}

	$orders = wc_get_orders( array(
		'limit'      => -1,
		'status'     => vatan_earnings_counted_statuses(),
	) );

	$total = 0;
	if ( is_array( $orders ) ) {
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_meta( '_vatan_event_id' ) !== $event_id ) {
					continue;
				}
				$total += (int) $item->get_quantity();
			}
		}
	}

	$cache[ $event_id ] = $total;
	return $total;
}

/**
 * Sum the gross earnings across every event submitted by a user.
 *
 * @param int $user_id
 * @return float
 */
function vatan_user_total_earnings( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return 0.0;
	}

	$event_ids = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_vatan_submitted_by',
				'value'   => $user_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
		),
	) );

	$total = 0.0;
	foreach ( (array) $event_ids as $event_id ) {
		$total += vatan_event_gross_revenue( (int) $event_id );
	}
	return $total;
}

/**
 * Total tickets sold across every event submitted by a user. Used for
 * the summary tile in the dashboard.
 *
 * @param int $user_id
 * @return int
 */
function vatan_user_total_tickets_sold( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return 0;
	}

	$event_ids = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_vatan_submitted_by',
				'value'   => $user_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
		),
	) );

	$total = 0;
	foreach ( (array) $event_ids as $event_id ) {
		$total += vatan_event_tickets_sold( (int) $event_id );
	}
	return $total;
}
