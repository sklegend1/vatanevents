<?php
/**
 * Admin dashboard — single event control panel.
 *
 * Loaded via /admin/events/edit/?id=N. Surfaces the most common per-event
 * actions in one place:
 *   - Cover image, title, status badge, key facts (date / city / venue).
 *   - Status toggle (publish / pending / draft / trash) via the POST
 *     handler in inc/admin-dashboard.php.
 *   - "Full editor" link → /create-event/?edit=N (existing form already
 *     supports admin edits — vatan_create_event_user_can_edit returns true
 *     for any user with edit_others_posts).
 *   - Ticket-type breakdown with sold / capacity.
 *   - Recent orders for this event.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$event_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$event    = $event_id ? get_post( $event_id ) : null;

if ( ! $event || 'event' !== $event->post_type ) {
	?>
	<div class="vatan-admin__empty-state">
		<h2><?php esc_html_e( 'Event not found.', 'vatan-event' ); ?></h2>
		<p>
			<a class="vatan-admin__btn" href="<?php echo esc_url( vatan_admin_url( 'events' ) ); ?>">
				← <?php esc_html_e( 'Back to events', 'vatan-event' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

if ( ! current_user_can( 'edit_post', $event_id ) ) {
	?>
	<div class="vatan-admin__empty-state">
		<h2><?php esc_html_e( 'You don\'t have permission to edit this event.', 'vatan-event' ); ?></h2>
	</div>
	<?php
	return;
}

$status   = get_post_status( $event );
$date     = function_exists( 'get_field' ) ? (string) get_field( 'event_date', $event_id ) : (string) get_post_meta( $event_id, 'event_date', true );
$date_fmt = $date ? vatan_event_date_display( $date ) : '—';
$venue    = function_exists( 'get_field' ) ? (string) get_field( 'event_venue', $event_id ) : (string) get_post_meta( $event_id, 'event_venue', true );
$tickets  = function_exists( 'get_field' ) ? (array)  get_field( 'ticket_types', $event_id ) : array();

$sold    = function_exists( 'vatan_event_tickets_sold' ) ? (int)   vatan_event_tickets_sold( $event_id )    : 0;
$revenue = function_exists( 'vatan_event_gross_revenue' ) ? (float) vatan_event_gross_revenue( $event_id ) : 0.0;
$paid    = function_exists( 'vatan_event_paid_out' )      ? (float) vatan_event_paid_out( $event_id )      : 0.0;
$balance = function_exists( 'vatan_event_balance' )       ? (float) vatan_event_balance( $event_id )       : max( 0.0, $revenue - $paid );

$cities       = get_the_terms( $event_id, 'event_city' );
$categories   = get_the_terms( $event_id, 'event_category' );
$city_labels  = is_array( $cities )     ? wp_list_pluck( $cities, 'name' )     : array();
$cat_labels   = is_array( $categories ) ? wp_list_pluck( $categories, 'name' ) : array();

$full_editor_url = function_exists( 'vatan_static_page_url' ) ? vatan_static_page_url( 'create-event' ) : home_url( '/create-event/' );
$full_editor_url = add_query_arg( 'edit', $event_id, $full_editor_url );

$status_updated = ! empty( $_GET['status_updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="vatan-admin__event-edit">

	<a class="vatan-admin__link" href="<?php echo esc_url( vatan_admin_url( 'events' ) ); ?>">
		← <?php esc_html_e( 'Back to events', 'vatan-event' ); ?>
	</a>

	<?php if ( $status_updated ) : ?>
		<div class="vatan-admin__notice vatan-admin__notice--success">
			<?php esc_html_e( 'Status updated.', 'vatan-event' ); ?>
		</div>
	<?php endif; ?>

	<header class="vatan-admin__event-head">
		<?php if ( has_post_thumbnail( $event_id ) ) : ?>
			<div class="vatan-admin__event-cover">
				<?php echo get_the_post_thumbnail( $event_id, 'medium_large' ); ?>
			</div>
		<?php endif; ?>

		<div class="vatan-admin__event-titleblock">
			<span class="vatan-admin__badge vatan-admin__badge--<?php echo esc_attr( $status ); ?>">
				<?php echo esc_html( $status ); ?>
			</span>
			<h1><?php echo esc_html( get_the_title( $event ) ); ?></h1>
			<p class="vatan-admin__event-meta">
				<span>📅 <?php echo esc_html( $date_fmt ); ?></span>
				<?php if ( $venue ) : ?>
					<span>📍 <?php echo esc_html( $venue ); ?></span>
				<?php endif; ?>
				<?php if ( $city_labels ) : ?>
					<span>🏙 <?php echo esc_html( implode( ', ', $city_labels ) ); ?></span>
				<?php endif; ?>
				<?php if ( $cat_labels ) : ?>
					<span>🏷 <?php echo esc_html( implode( ', ', $cat_labels ) ); ?></span>
				<?php endif; ?>
			</p>
		</div>

		<div class="vatan-admin__event-actions">
			<a class="vatan-admin__btn vatan-admin__btn--primary" href="<?php echo esc_url( $full_editor_url ); ?>">
				✏️ <?php esc_html_e( 'Open full editor', 'vatan-event' ); ?>
			</a>
			<?php $permalink = get_permalink( $event_id ); if ( $permalink ) : ?>
				<a class="vatan-admin__btn" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener">
					👁 <?php esc_html_e( 'View public page', 'vatan-event' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</header>

	<section class="vatan-admin__stats vatan-admin__stats--row">
		<div class="vatan-admin__stat">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Tickets sold', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_to_persian_digits( $sold ) ); ?></span>
		</div>
		<div class="vatan-admin__stat">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Gross revenue', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $revenue ) ); ?></span>
		</div>
		<div class="vatan-admin__stat">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Paid out', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $paid ) ); ?></span>
		</div>
		<div class="vatan-admin__stat">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Balance', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_format_price( $balance ) ); ?></span>
		</div>
	</section>

	<div class="vatan-admin__grid">

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Status', 'vatan-event' ); ?></h2>
			</header>
			<p class="vatan-admin__hint">
				<?php esc_html_e( 'Quick status changes. Use the full editor for content edits, tickets, and seat map.', 'vatan-event' ); ?>
			</p>
			<form method="post" class="vatan-admin__status-form">
				<?php wp_nonce_field( 'vatan_admin_event_status_' . $event_id ); ?>
				<input type="hidden" name="vatan_admin_event_status" value="1" />
				<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />

				<?php
				$buttons = array(
					'publish' => __( 'Publish', 'vatan-event' ),
					'pending' => __( 'Move to pending', 'vatan-event' ),
					'draft'   => __( 'Mark as draft', 'vatan-event' ),
				);
				foreach ( $buttons as $value => $label ) :
					if ( $value === $status ) continue;
					?>
					<button class="vatan-admin__btn" type="submit" name="new_status" value="<?php echo esc_attr( $value ); ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>

				<button class="vatan-admin__btn vatan-admin__btn--danger"
				        type="submit"
				        name="new_status"
				        value="trash"
				        onclick="return confirm('<?php echo esc_js( __( 'Move this event to the trash?', 'vatan-event' ) ); ?>');">
					🗑 <?php esc_html_e( 'Trash', 'vatan-event' ); ?>
				</button>
			</form>
		</section>

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Ticket types', 'vatan-event' ); ?></h2>
				<a class="vatan-admin__link" href="<?php echo esc_url( $full_editor_url ); ?>"><?php esc_html_e( 'Edit', 'vatan-event' ); ?> →</a>
			</header>

			<?php if ( empty( $tickets ) ) : ?>
				<p class="vatan-admin__empty"><?php esc_html_e( 'No ticket types configured.', 'vatan-event' ); ?></p>
			<?php else : ?>
				<table class="vatan-admin__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Price', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Capacity', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Sold', 'vatan-event' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tickets as $tier ) :
							$name  = isset( $tier['ticket_name'] ) ? (string) $tier['ticket_name'] : '—';
							$price = isset( $tier['ticket_price'] ) ? (float) $tier['ticket_price'] : 0;
							$cap   = isset( $tier['ticket_capacity'] ) ? (int) $tier['ticket_capacity'] : 0;
							$tsold = isset( $tier['ticket_sold'] ) ? (int) $tier['ticket_sold'] : 0;
							?>
							<tr>
								<td><?php echo esc_html( $name ); ?></td>
								<td><?php echo esc_html( vatan_format_price( $price ) ); ?></td>
								<td><?php echo esc_html( $cap ? vatan_to_persian_digits( $cap ) : '∞' ); ?></td>
								<td><?php echo esc_html( vatan_to_persian_digits( $tsold ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<?php
		// Recent paid orders containing this event's line items.
		$recent_orders = array();
		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders( array(
				'limit'      => 10,
				'status'     => array( 'wc-completed', 'wc-processing' ),
				'meta_key'   => '_vatan_event_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $event_id,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'    => 'date',
				'order'      => 'DESC',
			) );
			$recent_orders = is_array( $orders ) ? $orders : array();
		}
		?>

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Recent orders', 'vatan-event' ); ?></h2>
			</header>

			<?php if ( empty( $recent_orders ) ) : ?>
				<p class="vatan-admin__empty"><?php esc_html_e( 'No paid orders for this event yet.', 'vatan-event' ); ?></p>
			<?php else : ?>
				<table class="vatan-admin__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Tickets', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Total', 'vatan-event' ); ?></th>
							<th><?php esc_html_e( 'Date', 'vatan-event' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_orders as $order ) :
							$qty   = 0;
							$total = 0.0;
							foreach ( $order->get_items() as $line ) {
								if ( (int) $line->get_meta( '_vatan_event_id' ) === $event_id ) {
									$qty   += (int) $line->get_quantity();
									$total += (float) $line->get_total();
								}
							}
							$customer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
							if ( ! $customer ) {
								$customer = $order->get_billing_email();
							}
							$order_url = $order->get_edit_order_url();
							?>
							<tr>
								<td>
									<?php if ( $order_url ) : ?>
										<a href="<?php echo esc_url( $order_url ); ?>" target="_blank" rel="noopener">#<?php echo esc_html( $order->get_order_number() ); ?></a>
									<?php else : ?>
										#<?php echo esc_html( $order->get_order_number() ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $customer ); ?></td>
								<td><?php echo esc_html( vatan_to_persian_digits( $qty ) ); ?></td>
								<td><?php echo esc_html( vatan_format_price( $total ) ); ?></td>
								<td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

	</div>
</div>
