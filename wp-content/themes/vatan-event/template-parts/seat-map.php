<?php
/**
 * Seat map — full layout (side panel + grid).
 *
 * Hydrated by the SeatMap class in /assets/js/seat-map.js. Reserved seats
 * + section colors come from GET /wp-json/vatan/v1/seats/{event_id}.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$event_id = get_the_ID();
?>

<div
	id="choose-seats"
	class="seat-map"
	data-vatan-seat-map
	data-event-id="<?php echo esc_attr( $event_id ); ?>"
>
	<div class="seat-map__layout">

		<!-- Side panel: selection + totals + add-to-cart. -->
		<aside class="seat-map__panel">
			<header class="seat-map__panel-header">
				<h3 class="seat-map__panel-title">
					<span aria-hidden="true">🎟</span>
					<?php esc_html_e( 'Selected Seats', 'vatan-event' ); ?>
				</h3>
			</header>

			<div class="seat-map__selection" data-vatan-seat-selection>
				<p class="seat-map__panel-empty">
					<?php esc_html_e( 'No seats selected yet.', 'vatan-event' ); ?>
				</p>
			</div>

			<dl class="seat-map__totals">
				<div class="seat-map__totals-row">
					<dt><?php esc_html_e( 'Base price', 'vatan-event' ); ?></dt>
					<dd data-vatan-seat-base>—</dd>
				</div>
				<div class="seat-map__totals-row">
					<dt><?php esc_html_e( 'Tax & fees', 'vatan-event' ); ?></dt>
					<dd data-vatan-seat-tax>—</dd>
				</div>
				<div class="seat-map__totals-row seat-map__total">
					<dt><?php esc_html_e( 'Total', 'vatan-event' ); ?></dt>
					<dd data-vatan-seat-total>—</dd>
				</div>
			</dl>

			<button
				type="button"
				class="btn btn--primary btn--full"
				data-vatan-add-to-cart
				disabled
			>
				<?php esc_html_e( 'Add to cart', 'vatan-event' ); ?>
			</button>
		</aside>

		<!-- Main column: legend + stage + grid. -->
		<div class="seat-map__main">
			<div class="seat-map__legend" data-vatan-seat-legend>
				<span class="seat-map__legend-item">
					<?php esc_html_e( 'Loading…', 'vatan-event' ); ?>
				</span>
			</div>

			<div class="seat-map__stage" aria-hidden="true">
				<span><?php esc_html_e( 'Stage', 'vatan-event' ); ?></span>
			</div>

			<div class="seat-map__grid" data-vatan-seat-grid>
				<p class="seat-map__loading">
					<?php esc_html_e( 'Loading seats…', 'vatan-event' ); ?>
				</p>
			</div>
		</div>

	</div>
</div>
