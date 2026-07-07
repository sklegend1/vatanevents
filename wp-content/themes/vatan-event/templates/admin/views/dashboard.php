<?php
/**
 * Admin dashboard — overview view.
 *
 * Read-only at-a-glance numbers + lists for the staff that lives in
 * /admin/. Tries to surface the four things they ask about most:
 *   - how many events are live vs pending
 *   - revenue + tickets in the last 30 days
 *   - total outstanding payout balance
 *   - most recent events (so they can jump straight to one)
 *
 * Receives $view + $action from page-admin.php (unused here — kept for
 * symmetry with the other views).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ----- Stats ---------------------------------------------------------- */

$published_events = (int) wp_count_posts( 'event' )->publish;
$pending_events   = (int) wp_count_posts( 'event' )->pending;
$draft_events     = (int) wp_count_posts( 'event' )->draft;

$today_ts    = current_time( 'timestamp' );
$upcoming_q  = new WP_Query( array(
	'post_type'      => 'event',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'no_found_rows'  => false,
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		array(
			'key'     => 'event_date',
			'value'   => gmdate( 'Y-m-d', $today_ts ),
			'compare' => '>=',
			'type'    => 'DATE',
		),
	),
) );
$upcoming_count = (int) $upcoming_q->found_posts;

// Revenue / tickets — last 30 days from completed+processing orders.
$revenue = function_exists( 'vatan_collect_revenue' ) ? vatan_collect_revenue( 30, 0 ) : array( 'daily' => array(), 'count' => 0 );
$revenue_total = array_sum( $revenue['daily'] );
$tickets_30d   = (int) $revenue['count'];

// Outstanding payout balance — sum of (gross − paid_out) across all events
// that have at least one organizer assignment. Cap the per-event query so
// we don't choke if the catalog ever blows up.
$outstanding_total = 0.0;
if ( function_exists( 'vatan_event_balance' ) ) {
	$balance_event_ids = get_posts( array(
		'post_type'      => 'event',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => 200,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_vatan_submitted_by',
				'compare' => 'EXISTS',
			),
		),
	) );
	foreach ( (array) $balance_event_ids as $bid ) {
		$outstanding_total += (float) vatan_event_balance( (int) $bid );
	}
}

/* ----- Recent activity ------------------------------------------------ */

$recent_events = get_posts( array(
	'post_type'      => 'event',
	'post_status'    => array( 'publish', 'pending', 'draft' ),
	'posts_per_page' => 6,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

$top_events = function_exists( 'vatan_top_events_for_period' ) ? vatan_top_events_for_period( 30 ) : array();
$top_events = array_slice( $top_events, 0, 5 );

$pending_q = new WP_Query( array(
	'post_type'      => 'event',
	'post_status'    => 'pending',
	'posts_per_page' => 5,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );
?>

<div class="vatan-admin__dashboard">

	<section class="vatan-admin__stats">
		<a class="vatan-admin__stat" href="<?php echo esc_url( vatan_admin_url( 'events' ) ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Live events', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_to_persian_digits( $published_events ) ); ?></span>
			<span class="vatan-admin__stat-sub">
				<?php
				/* translators: %s: count of upcoming events */
				echo esc_html( sprintf( __( '%s upcoming', 'vatan-event' ), vatan_to_persian_digits( $upcoming_count ) ) );
				?>
			</span>
		</a>

		<a class="vatan-admin__stat" href="<?php echo esc_url( vatan_admin_url( 'events', array( 'status' => 'pending' ) ) ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Pending review', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_to_persian_digits( $pending_events ) ); ?></span>
			<span class="vatan-admin__stat-sub">
				<?php
				/* translators: %s: count of draft events */
				echo esc_html( sprintf( __( '%s drafts', 'vatan-event' ), vatan_to_persian_digits( $draft_events ) ) );
				?>
			</span>
		</a>

		<a class="vatan-admin__stat" href="<?php echo esc_url( vatan_admin_url( 'sales' ) ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Revenue (30 days)', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $revenue_total ) ); ?></span>
			<span class="vatan-admin__stat-sub">
				<?php
				/* translators: %s: ticket count */
				echo esc_html( sprintf( __( '%s tickets sold', 'vatan-event' ), vatan_to_persian_digits( $tickets_30d ) ) );
				?>
			</span>
		</a>

		<a class="vatan-admin__stat" href="<?php echo esc_url( vatan_admin_url( 'payouts' ) ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Outstanding payouts', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $outstanding_total ) ); ?></span>
			<span class="vatan-admin__stat-sub"><?php esc_html_e( 'Across all organizers', 'vatan-event' ); ?></span>
		</a>
	</section>

	<section class="vatan-admin__quick-actions">
		<a class="vatan-admin__btn vatan-admin__btn--primary"
		   href="<?php echo esc_url( function_exists( 'vatan_static_page_url' ) ? vatan_static_page_url( 'create-event' ) : home_url( '/create-event/' ) ); ?>">
			+ <?php esc_html_e( 'Create event', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( vatan_admin_url( 'scanner' ) ); ?>">
			🎫 <?php esc_html_e( 'Open door scanner', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( vatan_admin_url( 'payouts', array( 'vatan_action' => 'new' ) ) ); ?>">
			💳 <?php esc_html_e( 'Record payout', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( vatan_admin_url( 'sales' ) ); ?>">
			📈 <?php esc_html_e( 'Sales analytics', 'vatan-event' ); ?>
		</a>
	</section>

	<div class="vatan-admin__grid">

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Recent events', 'vatan-event' ); ?></h2>
				<a class="vatan-admin__link" href="<?php echo esc_url( vatan_admin_url( 'events' ) ); ?>"><?php esc_html_e( 'View all', 'vatan-event' ); ?> →</a>
			</header>

			<?php if ( empty( $recent_events ) ) : ?>
				<p class="vatan-admin__empty"><?php esc_html_e( 'No events yet. Create your first one to get started.', 'vatan-event' ); ?></p>
			<?php else : ?>
				<ul class="vatan-admin__list">
					<?php foreach ( $recent_events as $ev ) :
						$date    = function_exists( 'get_field' ) ? (string) get_field( 'event_date', $ev->ID ) : (string) get_post_meta( $ev->ID, 'event_date', true );
						$date_fmt = $date ? vatan_event_date_display( $date ) : '—';
						$status   = get_post_status( $ev );
						$edit_url = vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => $ev->ID ) );
						$sold     = function_exists( 'vatan_event_tickets_sold' ) ? (int) vatan_event_tickets_sold( $ev->ID ) : 0;
						?>
						<li class="vatan-admin__list-item">
							<a class="vatan-admin__list-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $ev ) ); ?></a>
							<span class="vatan-admin__list-meta">
								<span class="vatan-admin__badge vatan-admin__badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span>
								<span><?php echo esc_html( $date_fmt ); ?></span>
								<span>
									<?php
									/* translators: %s: tickets sold count */
									echo esc_html( sprintf( __( '%s sold', 'vatan-event' ), vatan_to_persian_digits( $sold ) ) );
									?>
								</span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Top events (30 days)', 'vatan-event' ); ?></h2>
				<a class="vatan-admin__link" href="<?php echo esc_url( vatan_admin_url( 'sales' ) ); ?>"><?php esc_html_e( 'Sales detail', 'vatan-event' ); ?> →</a>
			</header>

			<?php if ( empty( $top_events ) ) : ?>
				<p class="vatan-admin__empty"><?php esc_html_e( 'No sales recorded in the last 30 days.', 'vatan-event' ); ?></p>
			<?php else : ?>
				<ul class="vatan-admin__list">
					<?php foreach ( $top_events as $row ) :
						$edit_url = vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => (int) $row['event_id'] ) );
						?>
						<li class="vatan-admin__list-item">
							<a class="vatan-admin__list-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
							<span class="vatan-admin__list-meta">
								<span><?php echo esc_html( vatan_format_price( $row['revenue'] ) ); ?></span>
								<span>
									<?php
									/* translators: %s: ticket count */
									echo esc_html( sprintf( __( '%s tickets', 'vatan-event' ), vatan_to_persian_digits( (int) $row['tickets'] ) ) );
									?>
								</span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<?php if ( $pending_q->have_posts() ) : ?>
			<section class="vatan-admin__panel vatan-admin__panel--accent">
				<header class="vatan-admin__panel-head">
					<h2><?php esc_html_e( 'Awaiting your review', 'vatan-event' ); ?></h2>
					<a class="vatan-admin__link" href="<?php echo esc_url( vatan_admin_url( 'events', array( 'status' => 'pending' ) ) ); ?>"><?php esc_html_e( 'See all pending', 'vatan-event' ); ?> →</a>
				</header>
				<ul class="vatan-admin__list">
					<?php while ( $pending_q->have_posts() ) : $pending_q->the_post();
						$ev_id    = get_the_ID();
						$author   = get_user_by( 'id', (int) get_post_meta( $ev_id, '_vatan_submitted_by', true ) ?: (int) get_post_field( 'post_author', $ev_id ) );
						$edit_url = vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => $ev_id ) );
						?>
						<li class="vatan-admin__list-item">
							<a class="vatan-admin__list-title" href="<?php echo esc_url( $edit_url ); ?>"><?php the_title(); ?></a>
							<span class="vatan-admin__list-meta">
								<?php if ( $author ) : ?>
									<span>
										<?php
										/* translators: %s: organizer display name */
										echo esc_html( sprintf( __( 'by %s', 'vatan-event' ), $author->display_name ) );
										?>
									</span>
								<?php endif; ?>
								<span><?php echo esc_html( get_the_date() ); ?></span>
							</span>
						</li>
					<?php endwhile; wp_reset_postdata(); ?>
				</ul>
			</section>
		<?php endif; ?>

	</div>
</div>
