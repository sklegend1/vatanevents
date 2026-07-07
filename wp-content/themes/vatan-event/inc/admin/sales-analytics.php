<?php
/**
 * Sales Analytics admin page.
 *
 * Pulls completed/processing WooCommerce orders from the last 30 days and
 * compares against the prior 30 days. Renders summary cards, a Chart.js
 * line chart of daily revenue, and a top-events table. Without orders the
 * page shows the same UI with all-zero data.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

function vatan_sales_analytics_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'vatan-event' ) );
	}

	$current  = vatan_collect_revenue( 30, 0 );
	$previous = vatan_collect_revenue( 60, 30 );

	$current_total  = array_sum( $current['daily'] );
	$previous_total = array_sum( $previous['daily'] );

	$delta_pct = null;
	if ( $previous_total > 0 ) {
		$delta_pct = ( ( $current_total - $previous_total ) / $previous_total ) * 100.0;
	} elseif ( $current_total > 0 ) {
		$delta_pct = 100.0;
	}

	$top = vatan_top_events_for_period( 30 );

	$chart_payload = array(
		'labels'   => array_keys( $current['daily'] ),
		'current'  => array_values( $current['daily'] ),
		'previous' => array_values( $previous['daily'] ),
		'i18n'     => array(
			'currentLabel'  => __( 'This period', 'vatan-event' ),
			'previousLabel' => __( 'Previous 30 days', 'vatan-event' ),
		),
	);
	?>
	<div class="wrap vatan-admin">
		<h1><?php esc_html_e( 'Sales Analytics', 'vatan-event' ); ?></h1>

		<?php if ( ! function_exists( 'wc_get_orders' ) ) : ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'WooCommerce is not active — analytics will show empty until orders exist.', 'vatan-event' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="vatan-admin__cards">
			<div class="vatan-card">
				<span class="vatan-card__label"><?php esc_html_e( 'Revenue (30 days)', 'vatan-event' ); ?></span>
				<span class="vatan-card__value"><?php echo esc_html( vatan_format_price( $current_total ) ); ?></span>
				<?php if ( null !== $delta_pct ) : ?>
					<span class="vatan-card__delta vatan-card__delta--<?php echo $delta_pct >= 0 ? 'up' : 'down'; ?>">
						<?php
						echo esc_html(
							sprintf(
								'%s%s%% %s',
								$delta_pct >= 0 ? '+' : '',
								number_format_i18n( round( $delta_pct, 1 ), 1 ),
								__( 'vs previous period', 'vatan-event' )
							)
						);
						?>
					</span>
				<?php endif; ?>
			</div>
			<div class="vatan-card">
				<span class="vatan-card__label"><?php esc_html_e( 'Tickets sold (30 days)', 'vatan-event' ); ?></span>
				<span class="vatan-card__value"><?php echo esc_html( vatan_to_persian_digits( (int) $current['count'] ) ); ?></span>
			</div>
			<div class="vatan-card">
				<span class="vatan-card__label"><?php esc_html_e( 'Average order value', 'vatan-event' ); ?></span>
				<span class="vatan-card__value">
					<?php echo esc_html( vatan_format_price( $current['count'] ? $current_total / $current['count'] : 0 ) ); ?>
				</span>
			</div>
		</div>

		<section class="vatan-section">
			<h2><?php esc_html_e( 'Daily revenue', 'vatan-event' ); ?></h2>
			<div class="vatan-chart">
				<canvas id="vatan-daily-chart" height="80"></canvas>
			</div>
		</section>

		<section class="vatan-section">
			<h2><?php esc_html_e( 'Top events', 'vatan-event' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Event', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Tickets', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'vatan-event' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No sales recorded yet.', 'vatan-event' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $top as $row ) : ?>
							<tr>
								<td>
									<?php $edit = get_edit_post_link( $row['event_id'] ); ?>
									<?php if ( $edit ) : ?>
										<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $row['title'] ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( vatan_to_persian_digits( (int) $row['tickets'] ) ); ?></td>
								<td><?php echo esc_html( vatan_format_price( $row['revenue'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="vatan-section">
			<h2><?php esc_html_e( 'Export', 'vatan-event' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vatan_export_revenue&days=30' ), 'vatan_export_revenue' ) ); ?>">
					<?php esc_html_e( 'Download daily-revenue CSV', 'vatan-event' ); ?>
				</a>
			</p>
		</section>

		<script type="application/json" id="vatan-chart-data">
			<?php echo wp_json_encode( $chart_payload ); ?>
		</script>
	</div>
	<?php
}

/**
 * Build a daily-revenue bucket map for an [N…M] day window.
 * `$days_ago_start` is the older boundary (e.g. 60), `$days_ago_end` is
 * the newer boundary (e.g. 30 means "until 30 days ago").
 *
 * @param int $days_ago_start
 * @param int $days_ago_end
 * @return array{daily:array<string,float>,count:int}
 */
function vatan_collect_revenue( $days_ago_start, $days_ago_end ) {
	$start_ts = strtotime( '-' . $days_ago_start . ' days' );
	$end_ts   = $days_ago_end > 0
		? strtotime( '-' . $days_ago_end . ' days' )
		: time();

	$buckets = array();
	$cursor  = strtotime( gmdate( 'Y-m-d', $start_ts ) );
	$end_day = strtotime( gmdate( 'Y-m-d', $end_ts ) );
	for ( $d = $cursor; $d <= $end_day; $d += DAY_IN_SECONDS ) {
		$buckets[ gmdate( 'Y-m-d', $d ) ] = 0.0;
	}

	$count = 0;
	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders( array(
			'limit'        => -1,
			'status'       => array( 'wc-completed', 'wc-processing' ),
			'date_created' => gmdate( 'Y-m-d', $start_ts ) . '...' . gmdate( 'Y-m-d', $end_ts ),
		) );
		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				$created = $order->get_date_created();
				if ( ! $created ) {
					continue;
				}
				$key = $created->date( 'Y-m-d' );
				if ( isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] += (float) $order->get_total();
				}
				foreach ( $order->get_items() as $item ) {
					$count += (int) $item->get_quantity();
				}
			}
		}
	}

	return array(
		'daily' => $buckets,
		'count' => $count,
	);
}

/**
 * Top events by revenue across the last N days.
 *
 * @param int $days
 * @return array<int,array{event_id:int,title:string,tickets:int,revenue:float}>
 */
function vatan_top_events_for_period( $days = 30 ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$start_ts = strtotime( '-' . $days . ' days' );
	$orders   = wc_get_orders( array(
		'limit'        => -1,
		'status'       => array( 'wc-completed', 'wc-processing' ),
		'date_created' => '>' . gmdate( 'Y-m-d', $start_ts ),
	) );
	if ( ! is_array( $orders ) ) {
		return array();
	}
	$by_event = array();
	foreach ( $orders as $order ) {
		foreach ( $order->get_items() as $item ) {
			$event_id = (int) $item->get_meta( '_vatan_event_id' );
			if ( ! $event_id ) {
				continue;
			}
			if ( ! isset( $by_event[ $event_id ] ) ) {
				$by_event[ $event_id ] = array(
					'event_id' => $event_id,
					'title'    => get_the_title( $event_id ),
					'tickets'  => 0,
					'revenue'  => 0.0,
				);
			}
			$by_event[ $event_id ]['tickets'] += (int) $item->get_quantity();
			$by_event[ $event_id ]['revenue'] += (float) $item->get_total();
		}
	}
	usort(
		$by_event,
		static function ( $a, $b ) {
			if ( $a['revenue'] === $b['revenue'] ) {
				return 0;
			}
			return ( $a['revenue'] < $b['revenue'] ) ? 1 : -1;
		}
	);
	return array_slice( array_values( $by_event ), 0, 10 );
}

/**
 * admin-post.php handler — streams the daily-revenue series as CSV.
 */
function vatan_handle_export_revenue() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'vatan-event' ) );
	}
	check_admin_referer( 'vatan_export_revenue' );
	$days = isset( $_GET['days'] ) ? max( 1, min( 365, absint( $_GET['days'] ) ) ) : 30;

	$current = vatan_collect_revenue( $days, 0 );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="vatan-revenue-' . gmdate( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Date', 'Revenue' ) );
	foreach ( $current['daily'] as $day => $amount ) {
		fputcsv( $out, array( $day, $amount ) );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_vatan_export_revenue', 'vatan_handle_export_revenue' );
