<?php
/**
 * Organizer payouts — track what the platform has paid out to organizers
 * for which event, when, and by whom.
 *
 * Pairs with inc/earnings.php (which tells us gross revenue per event):
 *   balance(event) = gross_revenue(event) − sum(payouts for event)
 *
 * One custom table (`{prefix}vatan_payouts`) holds each payout row. The
 * admin records a payout from Vatan Event → Payouts. Organizers see their
 * "Paid out" and "Balance" amounts in the My Events dashboard.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_PAYOUTS_TABLE_VERSION = '1.0.0';

/**
 * Full table name including the WP prefix.
 *
 * @return string
 */
function vatan_payouts_table() {
	global $wpdb;
	return $wpdb->prefix . 'vatan_payouts';
}

/**
 * Create / migrate the payouts table. Runs on theme activation and on
 * admin_init when the stored version doesn't match.
 */
function vatan_payouts_maybe_install_table() {
	if ( get_option( 'vatan_payouts_schema_version' ) === VATAN_PAYOUTS_TABLE_VERSION ) {
		return;
	}

	global $wpdb;
	$table   = vatan_payouts_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id BIGINT UNSIGNED NOT NULL,
		organizer_id BIGINT UNSIGNED NOT NULL,
		amount DECIMAL(18,4) NOT NULL DEFAULT 0,
		paid_at DATETIME NOT NULL,
		notes TEXT NULL,
		recorded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY event_idx (event_id),
		KEY organizer_idx (organizer_id)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'vatan_payouts_schema_version', VATAN_PAYOUTS_TABLE_VERSION );
}
add_action( 'after_switch_theme', 'vatan_payouts_maybe_install_table' );
add_action( 'admin_init', 'vatan_payouts_maybe_install_table', 5 );

/* ============================================================
   Data helpers
   ============================================================ */

/**
 * Insert a payout row. Returns the new row ID or WP_Error.
 *
 * @param array $data {event_id, organizer_id, amount, paid_at, notes}
 * @return int|WP_Error
 */
function vatan_record_payout( $data ) {
	global $wpdb;

	$event_id     = isset( $data['event_id'] )     ? (int) $data['event_id'] : 0;
	$organizer_id = isset( $data['organizer_id'] ) ? (int) $data['organizer_id'] : 0;
	$amount       = isset( $data['amount'] )       ? (float) $data['amount'] : 0.0;
	$paid_at      = isset( $data['paid_at'] )      ? (string) $data['paid_at'] : current_time( 'mysql' );
	$notes        = isset( $data['notes'] )        ? sanitize_textarea_field( (string) $data['notes'] ) : '';

	if ( $event_id < 1 || 'event' !== get_post_type( $event_id ) ) {
		return new WP_Error( 'invalid_event', __( 'Pick a valid event.', 'vatan-event' ) );
	}
	if ( $organizer_id < 1 ) {
		// Auto-derive from event's submitter meta.
		$organizer_id = (int) get_post_meta( $event_id, '_vatan_submitted_by', true );
		if ( $organizer_id < 1 ) {
			$organizer_id = (int) get_post_field( 'post_author', $event_id );
		}
	}
	if ( $amount <= 0 ) {
		return new WP_Error( 'invalid_amount', __( 'Amount must be greater than zero.', 'vatan-event' ) );
	}

	// Normalise paid_at — accept either Y-m-d or full datetime.
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $paid_at ) ) {
		$paid_at .= ' 00:00:00';
	}

	$inserted = $wpdb->insert(
		vatan_payouts_table(),
		array(
			'event_id'     => $event_id,
			'organizer_id' => $organizer_id,
			'amount'       => $amount,
			'paid_at'      => $paid_at,
			'notes'        => $notes,
			'recorded_by'  => get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%f', '%s', '%s', '%d', '%s' )
	);

	if ( false === $inserted ) {
		return new WP_Error( 'db_error', __( 'Could not save the payout. Please try again.', 'vatan-event' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * Delete a payout row by ID.
 *
 * @param int $id
 * @return bool
 */
function vatan_delete_payout( $id ) {
	global $wpdb;
	$id = (int) $id;
	if ( ! $id ) return false;
	return false !== $wpdb->delete( vatan_payouts_table(), array( 'id' => $id ), array( '%d' ) );
}

/**
 * Sum of payouts paid out for one event.
 *
 * @param int $event_id
 * @return float
 */
function vatan_event_paid_out( $event_id ) {
	global $wpdb;
	$event_id = (int) $event_id;
	if ( ! $event_id ) return 0.0;

	static $cache = array();
	if ( isset( $cache[ $event_id ] ) ) return $cache[ $event_id ];

	$table = vatan_payouts_table();
	$sum   = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE event_id = %d", $event_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$cache[ $event_id ] = $sum;
	return $sum;
}

/**
 * Sum of payouts paid out to one organizer (across all their events).
 *
 * @param int $organizer_id
 * @return float
 */
function vatan_user_paid_out( $organizer_id ) {
	global $wpdb;
	$organizer_id = (int) $organizer_id;
	if ( ! $organizer_id ) return 0.0;

	$table = vatan_payouts_table();
	return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE organizer_id = %d", $organizer_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
}

/**
 * Outstanding balance for one event = gross revenue − payouts.
 *
 * @param int $event_id
 * @return float
 */
function vatan_event_balance( $event_id ) {
	$gross = function_exists( 'vatan_event_gross_revenue' ) ? vatan_event_gross_revenue( (int) $event_id ) : 0.0;
	return max( 0.0, $gross - vatan_event_paid_out( (int) $event_id ) );
}

/**
 * Outstanding balance across all events for a user.
 *
 * @param int $user_id
 * @return float
 */
function vatan_user_balance( $user_id ) {
	$gross = function_exists( 'vatan_user_total_earnings' ) ? vatan_user_total_earnings( (int) $user_id ) : 0.0;
	return max( 0.0, $gross - vatan_user_paid_out( (int) $user_id ) );
}

/**
 * Recent payouts — paginated; used by the admin list view.
 *
 * @param array $args {organizer_id?, event_id?, limit?, offset?}
 * @return array
 */
function vatan_list_payouts( $args = array() ) {
	global $wpdb;
	$args = wp_parse_args( $args, array(
		'organizer_id' => 0,
		'event_id'     => 0,
		'limit'        => 50,
		'offset'       => 0,
	) );

	$where  = array( '1=1' );
	$params = array();
	if ( $args['organizer_id'] ) {
		$where[]  = 'organizer_id = %d';
		$params[] = (int) $args['organizer_id'];
	}
	if ( $args['event_id'] ) {
		$where[]  = 'event_id = %d';
		$params[] = (int) $args['event_id'];
	}

	$table   = vatan_payouts_table();
	$where_s = implode( ' AND ', $where );
	$sql     = "SELECT * FROM $table WHERE $where_s ORDER BY paid_at DESC, id DESC LIMIT %d OFFSET %d";
	$params[] = (int) $args['limit'];
	$params[] = (int) $args['offset'];

	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
}

/* ============================================================
   Admin page — Vatan Event → Payouts
   ============================================================ */

/**
 * Handler that records / deletes a payout from the admin form. Hooks to
 * admin_init so we can wp_safe_redirect on success.
 */
function vatan_payouts_handle_form() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Record a new payout.
	if ( isset( $_POST['vatan_payout_record'] ) ) {
		check_admin_referer( 'vatan_record_payout' );

		$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
		$amount   = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
		$paid_at  = isset( $_POST['paid_at'] ) ? sanitize_text_field( wp_unslash( $_POST['paid_at'] ) ) : '';
		$notes    = isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '';

		$result = vatan_record_payout( array(
			'event_id' => $event_id,
			'amount'   => $amount,
			'paid_at'  => $paid_at,
			'notes'    => $notes,
		) );

		$status = is_wp_error( $result ) ? 'error' : 'recorded';
		if ( is_wp_error( $result ) ) {
			set_transient( 'vatan_payouts_error_' . get_current_user_id(), $result->get_error_message(), 30 );
		} else {
			// Email the organizer with the new balance.
			vatan_notify_organizer_of_payout( $event_id, (float) $amount, $paid_at, (string) $notes );
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'vatan-payouts', 'status' => $status ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	// Delete a payout.
	if ( isset( $_GET['vatan_payout_delete'] ) ) {
		$id = (int) $_GET['vatan_payout_delete'];
		check_admin_referer( 'vatan_delete_payout_' . $id );
		vatan_delete_payout( $id );
		wp_safe_redirect( add_query_arg(
			array( 'page' => 'vatan-payouts', 'status' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
add_action( 'admin_init', 'vatan_payouts_handle_form' );

/**
 * Render the Payouts admin page.
 */
function vatan_payouts_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'vatan-event' ) );
	}

	$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$err    = get_transient( 'vatan_payouts_error_' . get_current_user_id() );
	if ( $err ) {
		delete_transient( 'vatan_payouts_error_' . get_current_user_id() );
	}

	// Events with a known submitter — only events tied to an organizer
	// can sensibly receive a payout.
	$events = get_posts( array(
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
	) );

	$payouts = vatan_list_payouts( array( 'limit' => 50 ) );
	?>
	<div class="wrap vatan-admin">
		<h1><?php esc_html_e( 'Organizer Payouts', 'vatan-event' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Record what you\'ve paid out to organizers for their events. Each event\'s "Balance" in their dashboard updates as soon as you save a payout here.', 'vatan-event' ); ?>
		</p>

		<?php if ( 'recorded' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payout recorded.', 'vatan-event' ); ?></p></div>
		<?php elseif ( 'deleted' === $status ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payout deleted.', 'vatan-event' ); ?></p></div>
		<?php elseif ( 'error' === $status && $err ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Record a payout', 'vatan-event' ); ?></h2>
		<form method="post" class="vatan-payouts__form">
			<?php wp_nonce_field( 'vatan_record_payout' ); ?>
			<input type="hidden" name="vatan_payout_record" value="1">

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="vp-event"><?php esc_html_e( 'Event', 'vatan-event' ); ?></label></th>
					<td>
						<select name="event_id" id="vp-event" required>
							<option value=""><?php esc_html_e( '— Select event —', 'vatan-event' ); ?></option>
							<?php foreach ( $events as $event ) :
								$gross = function_exists( 'vatan_event_gross_revenue' ) ? vatan_event_gross_revenue( $event->ID ) : 0;
								$paid  = vatan_event_paid_out( $event->ID );
								$bal   = max( 0, $gross - $paid );
								?>
								<option value="<?php echo esc_attr( $event->ID ); ?>">
									<?php
									printf(
										'%s — %s: %s',
										esc_html( get_the_title( $event ) ),
										esc_html__( 'balance', 'vatan-event' ),
										esc_html( function_exists( 'vatan_format_price' ) ? vatan_format_price( $bal ) : number_format( $bal, 0 ) )
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="vp-amount"><?php esc_html_e( 'Amount', 'vatan-event' ); ?></label></th>
					<td>
						<input type="number" id="vp-amount" name="amount" step="any" min="0" required class="regular-text" />
					</td>
				</tr>
				<tr>
					<th><label for="vp-paid-at"><?php esc_html_e( 'Paid on', 'vatan-event' ); ?></label></th>
					<td>
						<input type="date" id="vp-paid-at" name="paid_at" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label for="vp-notes"><?php esc_html_e( 'Notes', 'vatan-event' ); ?></label></th>
					<td>
						<textarea id="vp-notes" name="notes" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Bank transfer reference, batch ID, etc.', 'vatan-event' ); ?>"></textarea>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Record payout', 'vatan-event' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Recent payouts', 'vatan-event' ); ?></h2>
		<?php if ( empty( $payouts ) ) : ?>
			<p><?php esc_html_e( 'No payouts recorded yet.', 'vatan-event' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
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
						$event = get_post( (int) $row->event_id );
						$user  = get_userdata( (int) $row->organizer_id );
						$del_url = wp_nonce_url(
							add_query_arg( array(
								'page' => 'vatan-payouts',
								'vatan_payout_delete' => (int) $row->id,
							), admin_url( 'admin.php' ) ),
							'vatan_delete_payout_' . (int) $row->id
						);
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $row->paid_at ) ); ?></td>
							<td>
								<?php if ( $event ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $event->ID ) ); ?>"><?php echo esc_html( $event->post_title ); ?></a>
								<?php else : ?>
									<em>#<?php echo esc_html( $row->event_id ); ?> <?php esc_html_e( '(deleted)', 'vatan-event' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo $user ? esc_html( $user->display_name ) : '<em>—</em>'; ?></td>
							<td><?php echo esc_html( function_exists( 'vatan_format_price' ) ? vatan_format_price( $row->amount ) : number_format( (float) $row->amount, 0 ) ); ?></td>
							<td><?php echo esc_html( $row->notes ); ?></td>
							<td>
								<a href="<?php echo esc_url( $del_url ); ?>" class="button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this payout?', 'vatan-event' ) ); ?>');">
									<?php esc_html_e( 'Delete', 'vatan-event' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Email the organizer when a payout is recorded for one of their events.
 * Shows the paid amount + the new remaining balance so they can tally
 * against what they expect.
 *
 * @param int    $event_id
 * @param float  $amount
 * @param string $paid_at
 * @param string $notes
 */
function vatan_notify_organizer_of_payout( $event_id, $amount, $paid_at = '', $notes = '' ) {
	$event_id = (int) $event_id;
	if ( ! $event_id ) {
		return;
	}

	$organizer_id = (int) get_post_meta( $event_id, '_vatan_submitted_by', true );
	if ( $organizer_id < 1 ) {
		$organizer_id = (int) get_post_field( 'post_author', $event_id );
	}
	$user = $organizer_id ? get_userdata( $organizer_id ) : null;
	if ( ! $user || ! $user->user_email ) {
		return;
	}

	$event_title = get_the_title( $event_id );
	$site_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$balance_after = vatan_event_balance( $event_id );
	$dashboard     = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'my-events' )
		: home_url( '/my-account/my-events/' );

	$amount_fmt  = function_exists( 'vatan_format_price' ) ? vatan_format_price( $amount )       : number_format( (float) $amount, 0 );
	$balance_fmt = function_exists( 'vatan_format_price' ) ? vatan_format_price( $balance_after ) : number_format( (float) $balance_after, 0 );
	$paid_at_fmt = $paid_at ? mysql2date( get_option( 'date_format' ), $paid_at ) : mysql2date( get_option( 'date_format' ), current_time( 'mysql' ) );

	/* translators: %s: event title */
	$subject = sprintf( __( '[%s] Payout received', 'vatan-event' ), $site_name );

	$lines = array(
		sprintf( __( 'Hi %s,', 'vatan-event' ), $user->first_name ? $user->first_name : $user->display_name ),
		'',
		sprintf(
			/* translators: 1: amount 2: event title 3: paid-on date */
			__( 'A payout of %1$s has been recorded for your event "%2$s" on %3$s.', 'vatan-event' ),
			$amount_fmt,
			$event_title,
			$paid_at_fmt
		),
		'',
		sprintf(
			/* translators: %s: remaining balance amount */
			__( 'Remaining balance on this event: %s.', 'vatan-event' ),
			$balance_fmt
		),
	);

	if ( '' !== trim( (string) $notes ) ) {
		$lines[] = '';
		$lines[] = __( 'Notes from the admin:', 'vatan-event' );
		$lines[] = $notes;
	}

	$lines[] = '';
	$lines[] = __( 'See the full breakdown on your dashboard:', 'vatan-event' );
	$lines[] = $dashboard;

	$sent = wp_mail( $user->user_email, $subject, implode( "\n", $lines ) );

	/**
	 * Fires after we attempt to email an organizer about a recorded payout.
	 *
	 * @param int     $event_id
	 * @param float   $amount
	 * @param WP_User $user
	 * @param bool    $sent
	 */
	do_action( 'vatan_payout_notified', $event_id, (float) $amount, $user, (bool) $sent );
}
