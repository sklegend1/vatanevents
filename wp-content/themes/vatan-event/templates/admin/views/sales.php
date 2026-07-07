<?php
/**
 * Admin dashboard — sales analytics view.
 *
 * Re-skinned version of the wp-admin Sales Analytics page (see
 * inc/admin/sales-analytics.php) using the .vatan-admin__* class system.
 * Pulls the same data via vatan_collect_revenue() + vatan_top_events_for_period().
 *
 * Period filter: ?days=7 | 30 | 90 (default 30).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

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
?>

<div class="vatan-admin__sales">

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
		// Simple inline bar chart — avoids pulling Chart.js into the frontend
		// admin. Each bar is sized as a percentage of the period max.
		$max = max( array_map( 'floatval', $current['daily'] ) );
		if ( $max <= 0 ) {
			$max = 1; // avoid div-by-zero
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

</div>
