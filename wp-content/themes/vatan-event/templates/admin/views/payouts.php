<?php
/**
 * Admin dashboard — organizer payouts view.
 *
 * Frontend equivalent of inc/payouts.php's wp-admin page. Record a new
 * payout (POST handled in inc/admin-dashboard.php's
 * vatan_admin_handle_payout_post) and browse recent payouts.
 *
 * Query params:
 *   - vatan_action=new   open with the record-form scrolled into view
 *   - status=recorded|deleted|error    feedback after an action
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	?>
	<div class="vatan-admin__empty-state">
		<h2><?php esc_html_e( 'You don\'t have permission to view payouts.', 'vatan-event' ); ?></h2>
	</div>
	<?php
	return;
}

$status_flash = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$err_msg      = get_transient( 'vatan_admin_payout_error_' . get_current_user_id() );
if ( $err_msg ) {
	delete_transient( 'vatan_admin_payout_error_' . get_current_user_id() );
}

$events = function_exists( 'vatan_record_payout' ) ? get_posts( array(
	'post_type'      => 'event',
	'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		array(
			'key'     => '_vatan_submitted_by',
			'compare' => 'EXISTS',
		),
	),
) ) : array();

$payouts = function_exists( 'vatan_list_payouts' ) ? vatan_list_payouts( array( 'limit' => 50 ) ) : array();
?>

<div class="vatan-admin__payouts">

	<?php if ( 'recorded' === $status_flash ) : ?>
		<div class="vatan-admin__notice vatan-admin__notice--success">
			<?php esc_html_e( 'Payout recorded — organizer notified.', 'vatan-event' ); ?>
		</div>
	<?php elseif ( 'deleted' === $status_flash ) : ?>
		<div class="vatan-admin__notice vatan-admin__notice--success">
			<?php esc_html_e( 'Payout deleted.', 'vatan-event' ); ?>
		</div>
	<?php elseif ( 'error' === $status_flash && $err_msg ) : ?>
		<div class="vatan-admin__notice vatan-admin__notice--error">
			<?php echo esc_html( $err_msg ); ?>
		</div>
	<?php endif; ?>

	<section class="vatan-admin__panel" id="vatan-admin-record-payout">
		<header class="vatan-admin__panel-head">
			<h2><?php esc_html_e( 'Record a payout', 'vatan-event' ); ?></h2>
		</header>
		<p class="vatan-admin__hint">
			<?php esc_html_e( 'Record what you have paid out to an organizer for one of their events. The organizer is emailed with the new balance.', 'vatan-event' ); ?>
		</p>

		<?php if ( empty( $events ) ) : ?>
			<p class="vatan-admin__empty">
				<?php esc_html_e( 'No events with an assigned organizer yet. Organizers are linked to an event automatically when they submit it through the frontend create form.', 'vatan-event' ); ?>
			</p>
		<?php else : ?>
			<form method="post" class="vatan-admin__form">
				<?php wp_nonce_field( 'vatan_admin_record_payout' ); ?>
				<input type="hidden" name="vatan_admin_payout" value="1" />

				<div class="vatan-admin__form-row">
					<label for="vatan-payout-event"><?php esc_html_e( 'Event', 'vatan-event' ); ?></label>
					<select id="vatan-payout-event" name="event_id" required>
						<option value=""><?php esc_html_e( '— Select event —', 'vatan-event' ); ?></option>
						<?php foreach ( $events as $event ) :
							$gross   = function_exists( 'vatan_event_gross_revenue' ) ? vatan_event_gross_revenue( $event->ID ) : 0;
							$paid    = function_exists( 'vatan_event_paid_out' )      ? vatan_event_paid_out( $event->ID )      : 0;
							$balance = max( 0, $gross - $paid );
							?>
							<option value="<?php echo esc_attr( $event->ID ); ?>">
								<?php echo esc_html( sprintf(
									'%s — %s: %s',
									get_the_title( $event ),
									__( 'balance', 'vatan-event' ),
									vatan_format_price( $balance )
								) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vatan-admin__form-row">
					<label for="vatan-payout-amount"><?php esc_html_e( 'Amount', 'vatan-event' ); ?></label>
					<input type="number" id="vatan-payout-amount" name="amount" step="any" min="0" required />
				</div>

				<div class="vatan-admin__form-row">
					<label for="vatan-payout-date"><?php esc_html_e( 'Paid on', 'vatan-event' ); ?></label>
					<input type="date" id="vatan-payout-date" name="paid_at" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
				</div>

				<div class="vatan-admin__form-row">
					<label for="vatan-payout-notes"><?php esc_html_e( 'Notes', 'vatan-event' ); ?></label>
					<textarea id="vatan-payout-notes" name="notes" rows="3" placeholder="<?php esc_attr_e( 'Bank transfer reference, batch ID, etc.', 'vatan-event' ); ?>"></textarea>
				</div>

				<button class="vatan-admin__btn vatan-admin__btn--primary" type="submit">
					<?php esc_html_e( 'Record payout', 'vatan-event' ); ?>
				</button>
			</form>
		<?php endif; ?>
	</section>

	<section class="vatan-admin__panel">
		<header class="vatan-admin__panel-head">
			<h2><?php esc_html_e( 'Recent payouts', 'vatan-event' ); ?></h2>
		</header>

		<?php if ( empty( $payouts ) ) : ?>
			<p class="vatan-admin__empty"><?php esc_html_e( 'No payouts recorded yet.', 'vatan-event' ); ?></p>
		<?php else : ?>
			<table class="vatan-admin__table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Paid on', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Event', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Organizer', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Notes', 'vatan-event' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $payouts as $row ) :
						$event_obj = get_post( (int) $row->event_id );
						$user_obj  = get_userdata( (int) $row->organizer_id );
						$del_url   = wp_nonce_url(
							vatan_admin_url( 'payouts', array( 'vatan_payout_delete' => (int) $row->id ) ),
							'vatan_admin_delete_payout_' . (int) $row->id
						);
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row->paid_at ) ); ?></td>
							<td>
								<?php if ( $event_obj ) :
									$edit_url = vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => (int) $row->event_id ) );
									?>
									<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $event_obj->post_title ); ?></a>
								<?php else : ?>
									<em>#<?php echo esc_html( (int) $row->event_id ); ?> <?php esc_html_e( '(deleted)', 'vatan-event' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo $user_obj ? esc_html( $user_obj->display_name ) : '<em>—</em>'; ?></td>
							<td><?php echo esc_html( vatan_format_price( $row->amount ) ); ?></td>
							<td><?php echo esc_html( (string) $row->notes ); ?></td>
							<td>
								<a class="vatan-admin__link vatan-admin__link--danger"
								   href="<?php echo esc_url( $del_url ); ?>"
								   onclick="return confirm('<?php echo esc_js( __( 'Delete this payout?', 'vatan-event' ) ); ?>');">
									<?php esc_html_e( 'Delete', 'vatan-event' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

</div>

<?php if ( 'new' === ( isset( $action ) ? $action : '' ) ) : ?>
	<script>
		(function () {
			var anchor = document.getElementById('vatan-admin-record-payout');
			if (anchor) { anchor.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
		})();
	</script>
<?php endif; ?>
