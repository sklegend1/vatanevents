<?php
/**
 * My Events — WooCommerce My Account endpoint template.
 *
 * Variables (set by vatan_my_events_endpoint_content):
 *   $events — array of records, each with:
 *     id, title, status, submitted, event_date, date_display,
 *     venue, permalink, ticket_count, view_count
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/** @var array $events */

// Summary counts for the header card.
$counts = array(
	'publish' => 0,
	'pending' => 0,
	'draft'   => 0,
	'other'   => 0,
);
$total_tickets_sold = 0;
$total_earnings     = 0.0;
$total_paid_out     = 0.0;
foreach ( $events as $event ) {
	$key = isset( $counts[ $event['status'] ] ) ? $event['status'] : 'other';
	$counts[ $key ]++;
	$total_tickets_sold += (int) $event['tickets_sold'];
	$total_earnings     += (float) $event['gross_revenue'];
	$total_paid_out     += (float) $event['paid_out'];
}
$total_balance = max( 0.0, $total_earnings - $total_paid_out );

$create_url = function_exists( 'vatan_static_page_url' ) ? vatan_static_page_url( 'create-event' ) : home_url( '/create-event/' );
?>

<header class="my-events__header">
	<div class="my-events__header-text">
		<h2><?php esc_html_e( 'My Events', 'vatan-event' ); ?></h2>
		<p class="my-events__lead">
			<?php esc_html_e( 'Track the events you\'ve submitted, their review status, and how many people have viewed them.', 'vatan-event' ); ?>
		</p>
	</div>
	<a class="btn btn--primary" href="<?php echo esc_url( $create_url ); ?>">
		<?php esc_html_e( '+ Create new event', 'vatan-event' ); ?>
	</a>
</header>

<?php if ( empty( $events ) ) : ?>
	<div class="my-events__empty">
		<p><?php esc_html_e( 'You haven\'t submitted any events yet.', 'vatan-event' ); ?></p>
		<a class="btn btn--primary" href="<?php echo esc_url( $create_url ); ?>">
			<?php esc_html_e( 'Submit your first event', 'vatan-event' ); ?>
		</a>
	</div>
<?php else : ?>

	<!-- Summary tiles -->
	<div class="my-events__summary">
		<div class="my-events__summary-tile my-events__summary-tile--live">
			<span class="my-events__summary-value"><?php echo esc_html( vatan_to_persian_digits( $counts['publish'] ) ); ?></span>
			<span class="my-events__summary-label"><?php esc_html_e( 'Published', 'vatan-event' ); ?></span>
		</div>
		<div class="my-events__summary-tile my-events__summary-tile--pending">
			<span class="my-events__summary-value"><?php echo esc_html( vatan_to_persian_digits( $counts['pending'] ) ); ?></span>
			<span class="my-events__summary-label"><?php esc_html_e( 'Pending review', 'vatan-event' ); ?></span>
		</div>
		<div class="my-events__summary-tile my-events__summary-tile--sales">
			<span class="my-events__summary-value"><?php echo esc_html( vatan_to_persian_digits( $total_tickets_sold ) ); ?></span>
			<span class="my-events__summary-label"><?php esc_html_e( 'Tickets sold', 'vatan-event' ); ?></span>
		</div>
		<div class="my-events__summary-tile my-events__summary-tile--earnings">
			<span class="my-events__summary-value"><?php echo esc_html( vatan_format_price( $total_earnings ) ); ?></span>
			<span class="my-events__summary-label"><?php esc_html_e( 'Earnings (gross)', 'vatan-event' ); ?></span>
		</div>
		<div class="my-events__summary-tile my-events__summary-tile--paid">
			<span class="my-events__summary-value"><?php echo esc_html( vatan_format_price( $total_paid_out ) ); ?></span>
			<span class="my-events__summary-label"><?php esc_html_e( 'Paid out', 'vatan-event' ); ?></span>
		</div>
		<div class="my-events__summary-tile my-events__summary-tile--balance">
			<span class="my-events__summary-value"><?php echo esc_html( vatan_format_price( $total_balance ) ); ?></span>
			<span class="my-events__summary-label"><?php esc_html_e( 'Outstanding balance', 'vatan-event' ); ?></span>
		</div>
	</div>

	<!-- Event list -->
	<div class="my-events__list">
		<?php foreach ( $events as $event ) :
			$state = vatan_event_post_status_meta( $event['status'] );
			?>
			<article class="my-event">
				<header class="my-event__head">
					<h3 class="my-event__title">
						<?php if ( $event['permalink'] ) : ?>
							<a href="<?php echo esc_url( $event['permalink'] ); ?>"><?php echo esc_html( $event['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $event['title'] ); ?>
						<?php endif; ?>
					</h3>
					<span class="my-event__status my-event__status--<?php echo esc_attr( $state['state'] ); ?>">
						<?php echo esc_html( $state['label'] ); ?>
					</span>
				</header>

				<dl class="my-event__meta">
					<?php if ( $event['date_display'] ) : ?>
						<div>
							<dt><?php esc_html_e( 'Date', 'vatan-event' ); ?></dt>
							<dd><?php echo esc_html( $event['date_display'] ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $event['venue'] ) : ?>
						<div>
							<dt><?php esc_html_e( 'Venue', 'vatan-event' ); ?></dt>
							<dd><?php echo esc_html( $event['venue'] ); ?></dd>
						</div>
					<?php endif; ?>
					<div>
						<dt><?php esc_html_e( 'Ticket types', 'vatan-event' ); ?></dt>
						<dd><?php echo esc_html( vatan_to_persian_digits( $event['ticket_count'] ) ); ?></dd>
					</div>
					<?php if ( 'publish' === $event['status'] ) : ?>
						<div>
							<dt><?php esc_html_e( 'Views', 'vatan-event' ); ?></dt>
							<dd>
								<?php
								echo esc_html(
									function_exists( 'vatan_format_view_count' )
										? vatan_format_view_count( $event['view_count'] )
										: (string) $event['view_count']
								);
								?>
							</dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Sold', 'vatan-event' ); ?></dt>
							<dd><?php echo esc_html( vatan_to_persian_digits( $event['tickets_sold'] ) ); ?></dd>
						</div>
						<div class="my-event__earnings-cell">
							<dt><?php esc_html_e( 'Earnings (gross)', 'vatan-event' ); ?></dt>
							<dd><strong><?php echo esc_html( vatan_format_price( $event['gross_revenue'] ) ); ?></strong></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Paid out', 'vatan-event' ); ?></dt>
							<dd><?php echo esc_html( vatan_format_price( $event['paid_out'] ) ); ?></dd>
						</div>
						<div class="my-event__balance-cell">
							<dt><?php esc_html_e( 'Balance', 'vatan-event' ); ?></dt>
							<dd><strong><?php echo esc_html( vatan_format_price( $event['balance'] ) ); ?></strong></dd>
						</div>
					<?php endif; ?>
					<div>
						<dt><?php esc_html_e( 'Submitted', 'vatan-event' ); ?></dt>
						<dd>
							<?php
							echo esc_html(
								mysql2date( get_option( 'date_format' ), $event['submitted'] )
							);
							?>
						</dd>
					</div>
				</dl>

				<?php if ( 'pending' === $event['status'] ) : ?>
					<p class="my-event__note">
						<span aria-hidden="true">⏳</span>
						<?php esc_html_e( 'Your event is in the queue for review. We\'ll email you when it\'s approved.', 'vatan-event' ); ?>
					</p>
				<?php endif; ?>

				<?php
				$edit_url = function_exists( 'vatan_static_page_url' ) ? vatan_static_page_url( 'create-event' ) : home_url( '/create-event/' );
				$edit_url = add_query_arg( 'edit', (int) $event['id'], $edit_url );
				?>
				<footer class="my-event__actions">
					<?php if ( 'publish' === $event['status'] && $event['permalink'] ) : ?>
						<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $event['permalink'] ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'View on site', 'vatan-event' ); ?> ↗
						</a>
					<?php endif; ?>
					<a class="btn btn--primary btn--sm" href="<?php echo esc_url( $edit_url ); ?>">
						<?php esc_html_e( 'Edit', 'vatan-event' ); ?>
					</a>
				</footer>
			</article>
		<?php endforeach; ?>
	</div>

<?php endif; ?>
