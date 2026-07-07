<?php
/**
 * My Tickets — WooCommerce My Account endpoint.
 *
 * Variables exposed by vatan_my_tickets_endpoint_content():
 *   $tickets — array of ticket records, each with:
 *     order_id, order_number, item_id, event_id, event_title,
 *     event_date, date_display, ticket_type, seats, seat_keys,
 *     price, status, qr_data
 *
 * QR codes render client-side via /assets/js/my-tickets.js using qrcode.js.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/** @var array $tickets */
?>

<header class="my-tickets__header">
	<h2><?php esc_html_e( 'My Tickets', 'vatan-event' ); ?></h2>
	<p class="my-tickets__lead">
		<?php esc_html_e( 'Show the QR code at the venue. Download as PDF or print a copy.', 'vatan-event' ); ?>
	</p>
</header>

<?php if ( empty( $tickets ) ) : ?>
	<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
		<?php esc_html_e( 'You have no tickets yet.', 'vatan-event' ); ?>
		<a class="woocommerce-Button button" href="<?php echo esc_url( get_post_type_archive_link( 'event' ) ?: home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Browse events', 'vatan-event' ); ?>
		</a>
	</div>
<?php else : ?>
	<div class="my-tickets__list">
		<?php foreach ( $tickets as $ticket ) : ?>
			<article class="my-ticket" id="ticket-<?php echo esc_attr( $ticket['item_id'] ); ?>">
				<div class="my-ticket__main">
					<header class="my-ticket__heading">
						<h3 class="my-ticket__title"><?php echo esc_html( $ticket['event_title'] ); ?></h3>
						<span class="my-ticket__status my-ticket__status--<?php echo esc_attr( $ticket['status'] ); ?>">
							<?php echo esc_html( wc_get_order_status_name( $ticket['status'] ) ); ?>
						</span>
					</header>

					<dl class="my-ticket__meta">
						<?php if ( $ticket['date_display'] ) : ?>
							<div>
								<dt><?php esc_html_e( 'Date', 'vatan-event' ); ?></dt>
								<dd><?php echo esc_html( $ticket['date_display'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( $ticket['ticket_type'] ) : ?>
							<div>
								<dt><?php esc_html_e( 'Ticket type', 'vatan-event' ); ?></dt>
								<dd><?php echo esc_html( $ticket['ticket_type'] ); ?></dd>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $ticket['seat_keys'] ) ) : ?>
							<div>
								<dt><?php esc_html_e( 'Seats', 'vatan-event' ); ?></dt>
								<dd>
									<?php
									$persian_seats = array_map( 'vatan_to_persian_digits', $ticket['seat_keys'] );
									echo esc_html( implode( '، ', $persian_seats ) );
									?>
								</dd>
							</div>
						<?php endif; ?>
						<div>
							<dt><?php esc_html_e( 'Order', 'vatan-event' ); ?></dt>
							<dd>
								<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'view-order' ) . $ticket['order_id'] ); ?>">
									<?php
									printf(
										/* translators: %s: order number */
										esc_html__( '#%s', 'vatan-event' ),
										esc_html( $ticket['order_number'] )
									);
									?>
								</a>
							</dd>
						</div>
					</dl>
				</div>

				<div class="my-ticket__qr-wrap">
					<div class="my-ticket__qr" data-vatan-qr="<?php echo esc_attr( $ticket['qr_data'] ); ?>" aria-label="<?php esc_attr_e( 'Ticket QR code', 'vatan-event' ); ?>">
						<noscript>
							<small><?php esc_html_e( 'Enable JavaScript to view the QR code.', 'vatan-event' ); ?></small>
						</noscript>
					</div>
					<small class="my-ticket__qr-id">#<?php echo esc_html( vatan_to_persian_digits( $ticket['item_id'] ) ); ?></small>
				</div>

				<div class="my-ticket__actions">
					<button type="button" class="button button--primary" data-vatan-pdf-ticket="<?php echo esc_attr( $ticket['item_id'] ); ?>">
						<?php esc_html_e( 'Download PDF', 'vatan-event' ); ?>
					</button>
					<button type="button" class="button" data-vatan-print-ticket="<?php echo esc_attr( $ticket['item_id'] ); ?>">
						<?php esc_html_e( 'Print', 'vatan-event' ); ?>
					</button>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
