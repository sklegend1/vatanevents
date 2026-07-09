<?php
/**
 * Admin dashboard — sales analytics view.
 *
 * Re-skinned version of the wp-admin Sales Analytics page (see
 * inc/admin/sales-analytics.php) using the .vatan-admin__* class system.
 * Pulls the same data via vatan_collect_revenue() + vatan_top_events_for_period().
 *
 * Tabs: Analytics | Payments
 * Period filter: ?days=7 | 30 | 90 (default 30).
 * Payment filter: ?payment_status=all | on-hold | processing | completed
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* -- Handle payment verification actions -------------------------------- */
if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['vatan_verify_payment'] ) ) {
	check_admin_referer( 'vatan_verify_payment' );
	$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
	$new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';
	$valid_statuses = array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-cancelled' );

	if ( $order_id && in_array( $new_status, $valid_statuses, true ) && function_exists( 'wc_get_order' ) ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_status( str_replace( 'wc-', '', $new_status ), __( 'Payment verified by admin', 'vatan-event' ) );
		}
	}
	wp_safe_redirect( vatan_admin_url( 'sales', array( 'tab' => 'payments', 'status' => 'updated' ) ) );
	exit;
}

/* -- Determine active tab ----------------------------------------------- */
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'analytics'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $active_tab, array( 'analytics', 'payments' ), true ) ) {
	$active_tab = 'analytics';
}

$payment_status = isset( $_GET['payment_status'] ) ? sanitize_key( wp_unslash( $_GET['payment_status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $payment_status, array( 'all', 'on-hold', 'processing', 'completed' ), true ) ) {
	$payment_status = 'all';
}

$update_flash = isset( $_GET['status'] ) && 'updated' === $_GET['status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/* -- Analytics data ----------------------------------------------------- */
$allowed_days = array( 7, 30, 90 );
$days = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $days, $allowed_days, true ) ) {
	$days = 30;
}

$current  = function_exists( 'vatan_collect_revenue' ) ? vatan_collect_revenue( $days, 0 )           : array( 'daily' => array(), 'count' => 0 );
$previous = function_exists( 'vatan_collect_revenue' ) ? vatan_collect_revenue( $days * 2, $days )   : array( 'daily' => array(), 'count' => 0 );

$current_total  = array_sum( $current['daily'] );
$previous_total = array_sum( $previous['daily'] );

$delta_pct = null;
if ( $previous_total > 0 ) {
	$delta_pct = ( ( $current_total - $previous_total ) / $previous_total ) * 100.0;
} elseif ( $current_total > 0 ) {
	$delta_pct = 100.0;
}

$avg_order = $current['count'] ? $current_total / $current['count'] : 0;
$top       = function_exists( 'vatan_top_events_for_period' ) ? vatan_top_events_for_period( $days ) : array();

$export_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=vatan_export_revenue&days=' . $days ),
	'vatan_export_revenue'
);

/* -- Payments data ------------------------------------------------------ */
$payments_per_page = 20;
$payments_page = isset( $_GET['payments_page'] ) ? max( 1, (int) $_GET['payments_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( function_exists( 'wc_get_orders' ) ) {
	$payments_args = array(
		'limit'  => $payments_per_page,
		'paged'  => $payments_page,
		'orderby' => 'date',
		'order'   => 'DESC',
	);
	if ( 'all' !== $payment_status ) {
		$payments_args['status'] = array( $payment_status );
	} else {
		$payments_args['status'] = array( 'wc-on-hold', 'wc-processing', 'wc-completed' );
	}
	$payments_orders = wc_get_orders( $payments_args );

	// Get total count for pagination
	$count_args = $payments_args;
	unset( $count_args['limit'] );
	$count_args['return'] = 'ids';
	$all_payments = wc_get_orders( $count_args );
	$payments_total = count( $all_payments );
	$payments_total_pages = ceil( $payments_total / $payments_per_page );
} else {
	$payments_orders = array();
	$payments_total = 0;
	$payments_total_pages = 0;
}

// Status counts for tabs
if ( function_exists( 'wc_get_orders' ) ) {
	$status_counts = array(
		'all'        => count( wc_get_orders( array( 'status' => array( 'wc-on-hold', 'wc-processing', 'wc-completed' ), 'return' => 'ids', 'limit' => -1 ) ) ),
		'on-hold'    => count( wc_get_orders( array( 'status' => 'wc-on-hold', 'return' => 'ids', 'limit' => -1 ) ) ),
		'processing' => count( wc_get_orders( array( 'status' => 'wc-processing', 'return' => 'ids', 'limit' => -1 ) ) ),
		'completed'  => count( wc_get_orders( array( 'status' => 'wc-completed', 'return' => 'ids', 'limit' => -1 ) ) ),
	);
} else {
	$status_counts = array( 'all' => 0, 'on-hold' => 0, 'processing' => 0, 'completed' => 0 );
}
?>

<div class="vatan-admin__sales">

	<?php if ( $update_flash ) : ?>
		<div class="vatan-admin__notice vatan-admin__notice--success">
			<?php esc_html_e( 'Payment status updated.', 'vatan-event' ); ?>
		</div>
	<?php endif; ?>

	<?php /* -- Top-level tabs: Analytics | Payments -- */ ?>
	<nav class="vatan-admin__tabs" aria-label="<?php esc_attr_e( 'Sales sections', 'vatan-event' ); ?>">
		<a class="vatan-admin__tab<?php echo 'analytics' === $active_tab ? ' is-active' : ''; ?>"
		   href="<?php echo esc_url( vatan_admin_url( 'sales' ) ); ?>"
		   <?php echo 'analytics' === $active_tab ? 'aria-current="page"' : ''; ?>>
			📊 <?php esc_html_e( 'Analytics', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__tab<?php echo 'payments' === $active_tab ? ' is-active' : ''; ?>"
		   href="<?php echo esc_url( vatan_admin_url( 'sales', array( 'tab' => 'payments' ) ) ); ?>"
		   <?php echo 'payments' === $active_tab ? 'aria-current="page"' : ''; ?>>
			💳 <?php esc_html_e( 'Payments', 'vatan-event' ); ?>
			<?php if ( $status_counts['on-hold'] > 0 ) : ?>
				<span class="vatan-admin__badge"><?php echo esc_html( vatan_to_persian_digits( $status_counts['on-hold'] ) ); ?></span>
			<?php endif; ?>
		</a>
	</nav>

	<?php if ( 'analytics' === $active_tab ) : ?>

		<?php /* -- Analytics view -- */ ?>

		<nav class="vatan-admin__tabs" aria-label="<?php esc_attr_e( 'Period', 'vatan-event' ); ?>">
			<?php
			$labels = array(
				7  => __( 'Last 7 days', 'vatan-event' ),
				30 => __( 'Last 30 days', 'vatan-event' ),
				90 => __( 'Last 90 days', 'vatan-event' ),
			);
			foreach ( $labels as $value => $label ) :
				$url       = add_query_arg( 'days', $value, vatan_admin_url( 'sales' ) );
				$is_active = ( $value === $days );
				?>
				<a class="vatan-admin__tab<?php echo $is_active ? ' is-active' : ''; ?>"
				   href="<?php echo esc_url( $url ); ?>"
				   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<section class="vatan-admin__stats">
			<div class="vatan-admin__stat">
				<span class="vatan-admin__stat-label">
					<?php
					/* translators: %d: number of days */
					echo esc_html( sprintf( __( 'Revenue (%d days)', 'vatan-event' ), $days ) );
					?>
				</span>
				<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $current_total ) ); ?></span>
				<?php if ( null !== $delta_pct ) : ?>
					<span class="vatan-admin__stat-sub vatan-admin__stat-sub--<?php echo $delta_pct >= 0 ? 'up' : 'down'; ?>">
						<?php
						echo esc_html( sprintf(
							'%s%s%% %s',
							$delta_pct >= 0 ? '+' : '',
							number_format_i18n( round( $delta_pct, 1 ), 1 ),
							__( 'vs previous period', 'vatan-event' )
						) );
						?>
					</span>
				<?php endif; ?>
			</div>
			<div class="vatan-admin__stat">
				<span class="vatan-admin__stat-label"><?php esc_html_e( 'Tickets sold', 'vatan-event' ); ?></span>
				<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_to_persian_digits( (int) $current['count'] ) ); ?></span>
			</div>
			<div class="vatan-admin__stat">
				<span class="vatan-admin__stat-label"><?php esc_html_e( 'Average order', 'vatan-event' ); ?></span>
				<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $avg_order ) ); ?></span>
			</div>
			<div class="vatan-admin__stat">
				<span class="vatan-admin__stat-label"><?php esc_html_e( 'Previous period', 'vatan-event' ); ?></span>
				<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $previous_total ) ); ?></span>
			</div>
		</section>

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Daily revenue', 'vatan-event' ); ?></h2>
				<a class="vatan-admin__link" href="<?php echo esc_url( $export_url ); ?>">
					⬇ <?php esc_html_e( 'Download CSV', 'vatan-event' ); ?>
				</a>
			</header>

			<?php
			$max = max( array_map( 'floatval', $current['daily'] ) );
			if ( $max <= 0 ) {
				$max = 1;
			}
			?>
			<div class="vatan-admin__bars" role="img" aria-label="<?php esc_attr_e( 'Daily revenue bar chart', 'vatan-event' ); ?>">
				<?php foreach ( $current['daily'] as $day => $amount ) :
					$pct = ( (float) $amount / $max ) * 100.0;
					$title = sprintf( '%s — %s', $day, vatan_format_price( $amount ) );
					?>
					<div class="vatan-admin__bar" title="<?php echo esc_attr( $title ); ?>">
						<div class="vatan-admin__bar-fill" style="height: <?php echo esc_attr( max( 1, $pct ) ); ?>%"></div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Top events', 'vatan-event' ); ?></h2>
			</header>

			<?php if ( empty( $top ) ) : ?>
				<p class="vatan-admin__empty"><?php esc_html_e( 'No sales in this period.', 'vatan-event' ); ?></p>
			<?php else : ?>
				<table class="vatan-admin__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Event', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Tickets', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Revenue', 'vatan-event' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top as $row ) :
							$edit_url = vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => (int) $row['event_id'] ) );
							?>
							<tr>
								<td>
									<a class="vatan-admin__row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
								</td>
								<td><?php echo esc_html( vatan_to_persian_digits( (int) $row['tickets'] ) ); ?></td>
								<td><?php echo esc_html( vatan_format_price( $row['revenue'] ) ); ?></td>
								<td><a class="vatan-admin__link" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Open', 'vatan-event' ); ?> →</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

	<?php else : ?>

		<?php /* -- Payments verification view -- */ ?>

		<nav class="vatan-admin__tabs" aria-label="<?php esc_attr_e( 'Payment status', 'vatan-event' ); ?>">
			<?php
			$status_labels = array(
				'all'        => __( 'All', 'vatan-event' ),
				'on-hold'    => __( 'Pending', 'vatan-event' ),
				'processing' => __( 'Processing', 'vatan-event' ),
				'completed'  => __( 'Completed', 'vatan-event' ),
			);
			foreach ( $status_labels as $s => $label ) :
				$count = (int) ( $status_counts[ $s ] ?? 0 );
				$url = vatan_admin_url( 'sales', array( 'tab' => 'payments', 'payment_status' => $s ) );
				$is_active = ( $s === $payment_status );
				?>
				<a class="vatan-admin__tab<?php echo $is_active ? ' is-active' : ''; ?>"
				   href="<?php echo esc_url( $url ); ?>"
				   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
					<span style="opacity:.7;">(<?php echo esc_html( vatan_to_persian_digits( $count ) ); ?>)</span>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php if ( empty( $payments_orders ) ) : ?>
			<div class="vatan-admin__empty">
				<p><?php esc_html_e( 'No orders found with this status.', 'vatan-event' ); ?></p>
			</div>
		<?php else : ?>
			<div class="vatan-admin__table-wrap">
				<table class="vatan-admin__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Event', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Status', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Date', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'vatan-event' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $payments_orders as $order ) :
							$order_id    = $order->get_id();
							$customer    = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
							$email       = $order->get_billing_email();
							$total       = $order->get_total();
							$status      = $order->get_status();
							$date_created = $order->get_date_created();
							$wc_status   = 'wc-' . $status;

							// Get event from order items
							$event_title = '—';
							$event_id    = 0;
							foreach ( $order->get_items() as $item ) {
								$eid = (int) $item->get_meta( '_vatan_event_id' );
								if ( $eid ) {
									$event_id = $eid;
									$event_title = get_the_title( $eid ) ?: '#' . $eid;
									break;
								}
							}

							$status_class = array(
								'on-hold'    => 'warning',
								'processing' => 'info',
								'completed'  => 'success',
							);
							?>
							<tr>
								<td>
									<a class="vatan-admin__row-title" href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>">
										#<?php echo esc_html( $order_id ); ?>
									</a>
								</td>
								<td>
									<?php echo esc_html( trim( $customer ) ?: '—' ); ?>
									<?php if ( $email ) : ?>
										<br><small style="opacity:.6;"><?php echo esc_html( $email ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $event_id ) : ?>
										<a href="<?php echo esc_url( vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => $event_id ) ) ); ?>" style="color:inherit;">
											<?php echo esc_html( $event_title ); ?>
										</a>
									<?php else : ?>
										<span style="opacity:.5;"><?php echo esc_html( $event_title ); ?></span>
									<?php endif; ?>
								</td>
								<td><strong><?php echo esc_html( vatan_format_price( (float) $total ) ); ?></strong></td>
								<td>
									<span class="vatan-admin__badge vatan-admin__badge--<?php echo esc_attr( $status_class[ $status ] ?? '' ); ?>">
										<?php echo esc_html( function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : ucfirst( str_replace( '-', ' ', $status ) ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $date_created ? $date_created->date( 'Y-m-d H:i' ) : '—' ); ?></td>
								<td>
									<?php if ( 'on-hold' === $status ) : ?>
										<form method="post" style="display:inline">
											<?php wp_nonce_field( 'vatan_verify_payment' ); ?>
											<input type="hidden" name="vatan_verify_payment" value="1" />
											<input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>" />
											<input type="hidden" name="new_status" value="wc-completed" />
											<button type="submit" class="vatan-admin__btn-mini vatan-admin__btn-mini--success">
												✓ <?php esc_html_e( 'Approve', 'vatan-event' ); ?>
											</button>
										</form>
										<form method="post" style="display:inline">
											<?php wp_nonce_field( 'vatan_verify_payment' ); ?>
											<input type="hidden" name="vatan_verify_payment" value="1" />
											<input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>" />
											<input type="hidden" name="new_status" value="wc-cancelled" />
											<button type="submit" class="vatan-admin__btn-mini vatan-admin__btn-mini--danger"
												onclick="return confirm('<?php echo esc_js( __( 'Cancel this order?', 'vatan-event' ) ); ?>');">
												✗ <?php esc_html_e( 'Cancel', 'vatan-event' ); ?>
											</button>
										</form>
									<?php elseif ( 'processing' === $status ) : ?>
										<form method="post" style="display:inline">
											<?php wp_nonce_field( 'vatan_verify_payment' ); ?>
											<input type="hidden" name="vatan_verify_payment" value="1" />
											<input type="hidden" name="order_id" value="<?php echo (int) $order_id; ?>" />
											<input type="hidden" name="new_status" value="wc-completed" />
											<button type="submit" class="vatan-admin__btn-mini vatan-admin__btn-mini--success">
												✓ <?php esc_html_e( 'Complete', 'vatan-event' ); ?>
											</button>
										</form>
									<?php endif; ?>
									<a class="vatan-admin__btn-mini" href="<?php echo esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ); ?>">
										<?php esc_html_e( 'View', 'vatan-event' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $payments_total_pages > 1 ) : ?>
				<nav class="vatan-admin__pagination" aria-label="<?php esc_attr_e( 'Pagination', 'vatan-event' ); ?>">
					<?php for ( $p = 1; $p <= $payments_total_pages; $p++ ) :
						$url = vatan_admin_url( 'sales', array(
							'tab'            => 'payments',
							'payment_status' => $payment_status,
							'payments_page'  => $p,
						) );
						$is_curr = ( $p === $payments_page );
						?>
						<?php if ( $is_curr ) : ?>
							<span class="is-current"><?php echo esc_html( vatan_to_persian_digits( $p ) ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( vatan_to_persian_digits( $p ) ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</nav>
			<?php endif; ?>

		<?php endif; ?>

	<?php endif; ?>

</div>
