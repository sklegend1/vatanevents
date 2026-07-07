<?php
/**
 * Admin dashboard — door scanner view.
 *
 * Reuses inc/checkin.php's REST endpoint (vatan/v1/checkin) and the
 * existing assets/admin/js/door-scanner.js module. The DOM IDs below
 * (#vatan-door-event, #vatan-door-start, #vatan-door-video, etc.) MUST
 * stay in sync with the wp-admin Door Scanner page so we can share the
 * JS without duplication.
 *
 * Scripts are enqueued from inc/admin-dashboard.php on wp_enqueue_scripts
 * when this view is active.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	?>
	<div class="vatan-admin__empty-state">
		<h2><?php esc_html_e( 'You don\'t have permission to use the door scanner.', 'vatan-event' ); ?></h2>
	</div>
	<?php
	return;
}

$events = get_posts( array(
	'post_type'      => 'event',
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'orderby'        => 'meta_value',
	'meta_key'       => 'event_date',
	'order'          => 'ASC',
	'fields'         => array( 'ID', 'post_title' ),
) );
?>

<div class="vatan-admin__scanner vatan-door">

	<p class="vatan-admin__hint">
		<?php esc_html_e( 'Scan a customer\'s QR code to verify their ticket. Already-used tickets and tickets for other events are rejected. Pick an event to scope the scan to one show, or leave the filter empty to accept any valid ticket.', 'vatan-event' ); ?>
	</p>

	<div class="vatan-door__layout">

		<section class="vatan-admin__panel vatan-door__cam-card">
			<header class="vatan-admin__panel-head vatan-door__head">
				<label for="vatan-door-event">
					<?php esc_html_e( 'Event filter', 'vatan-event' ); ?>
				</label>
				<select id="vatan-door-event">
					<option value=""><?php esc_html_e( 'Any event', 'vatan-event' ); ?></option>
					<?php foreach ( $events as $ev ) : ?>
						<option value="<?php echo esc_attr( $ev->ID ); ?>"><?php echo esc_html( $ev->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="vatan-admin__btn vatan-admin__btn--primary" id="vatan-door-start">
					<?php esc_html_e( 'Start camera', 'vatan-event' ); ?>
				</button>
			</header>

			<div class="vatan-door__viewport">
				<video id="vatan-door-video" playsinline muted></video>
				<canvas id="vatan-door-canvas" hidden></canvas>
				<div class="vatan-door__crosshair" aria-hidden="true"></div>
			</div>

			<details class="vatan-door__manual">
				<summary><?php esc_html_e( 'Manual lookup (no camera)', 'vatan-event' ); ?></summary>
				<div class="vatan-door__manual-body">
					<input type="text" id="vatan-door-manual-input"
					       placeholder="VATAN:123:456:..." />
					<button type="button" class="vatan-admin__btn" id="vatan-door-manual-submit">
						<?php esc_html_e( 'Check', 'vatan-event' ); ?>
					</button>
				</div>
			</details>
		</section>

		<section class="vatan-admin__panel vatan-door__result-card">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Last scan', 'vatan-event' ); ?></h2>
			</header>
			<div class="vatan-door__result" id="vatan-door-result" data-empty="true">
				<p class="vatan-door__placeholder">
					<?php esc_html_e( 'Awaiting scan…', 'vatan-event' ); ?>
				</p>
			</div>

			<header class="vatan-admin__panel-head" style="margin-top:24px">
				<h2><?php esc_html_e( 'Recent', 'vatan-event' ); ?></h2>
			</header>
			<ul class="vatan-door__history" id="vatan-door-history">
				<li class="vatan-door__history-empty">
					<?php esc_html_e( 'No scans yet.', 'vatan-event' ); ?>
				</li>
			</ul>
		</section>

	</div>
</div>
