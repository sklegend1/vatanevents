<?php
/**
 * Seat Manager admin page.
 *
 * Per-event capacity stats (from ACF ticket_types repeater + WC orders),
 * a textarea to block specific seats by row-col key, and a CSV-export
 * action that lists buyers for the selected event.
 *
 * Sales/buyer data only fills in once the WooCommerce `event_ticket`
 * product type is wired and orders carry `_vatan_event_id` line-item
 * meta. Until then the page renders gracefully with zeros / empty CSV.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

function vatan_seat_manager_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'vatan-event' ) );
	}

	// Save blocked seats — POST round-trip to the same admin URL.
	if ( isset( $_POST['vatan_block_save'] ) ) {
		check_admin_referer( 'vatan_block_seats', 'vatan_block_nonce' );
		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$blocked  = isset( $_POST['blocked'] ) ? sanitize_textarea_field( wp_unslash( $_POST['blocked'] ) ) : '';
		if ( $event_id ) {
			$list = preg_split( '/[\s,]+/', $blocked );
			$list = array_values( array_unique( array_filter( $list, function ( $key ) {
				return is_string( $key ) && preg_match( '/^\d+-\d+$/', $key );
			} ) ) );
			update_post_meta( $event_id, 'vatan_blocked_seats', $list );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Blocked seats saved.', 'vatan-event' ) . '</p></div>';
		}
	}

	$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$events = get_posts( array(
		'post_type'      => 'event',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft', 'private' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	?>
	<div class="wrap vatan-admin">
		<h1><?php esc_html_e( 'Seat Manager', 'vatan-event' ); ?></h1>

		<form method="get" class="vatan-seat-manager__picker">
			<input type="hidden" name="page" value="vatan-seat-manager" />
			<label for="event_id"><strong><?php esc_html_e( 'Event:', 'vatan-event' ); ?></strong></label>
			<select id="event_id" name="event_id" onchange="this.form.submit()">
				<option value="0"><?php esc_html_e( '— Select an event —', 'vatan-event' ); ?></option>
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>>
						<?php echo esc_html( get_the_title( $event ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Go', 'vatan-event' ); ?></button></noscript>
		</form>

		<?php if ( $event_id ) : ?>
			<?php vatan_render_seat_manager_event( $event_id ); ?>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Pick an event above to see capacity, block seats, and export buyers.', 'vatan-event' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function vatan_render_seat_manager_event( $event_id ) {
	$tickets = function_exists( 'get_field' ) ? get_field( 'ticket_types', $event_id ) : array();
	if ( ! is_array( $tickets ) ) {
		$tickets = array();
	}

	$blocked     = (array) get_post_meta( $event_id, 'vatan_blocked_seats', true );
	$sold_counts = vatan_count_sold_per_type( $event_id );
	?>
	<h2 class="vatan-section-title"><?php echo esc_html( get_the_title( $event_id ) ); ?></h2>

	<h3><?php esc_html_e( 'Capacity by ticket type', 'vatan-event' ); ?></h3>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Type', 'vatan-event' ); ?></th>
				<th><?php esc_html_e( 'Capacity', 'vatan-event' ); ?></th>
				<th><?php esc_html_e( 'Sold', 'vatan-event' ); ?></th>
				<th><?php esc_html_e( 'Remaining', 'vatan-event' ); ?></th>
				<th><?php esc_html_e( 'Price', 'vatan-event' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $tickets ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No ticket types defined for this event yet (requires the ACF Pro repeater).', 'vatan-event' ); ?></td></tr>
			<?php else : ?>
				<?php
				$tot_cap  = 0;
				$tot_sold = 0;
				foreach ( $tickets as $tier ) :
					$name  = isset( $tier['ticket_name'] ) ? (string) $tier['ticket_name'] : '';
					$cap   = isset( $tier['ticket_capacity'] ) ? (int) $tier['ticket_capacity'] : 0;
					$price = isset( $tier['ticket_price'] ) ? (float) $tier['ticket_price'] : 0.0;
					$sold  = isset( $sold_counts[ $name ] )
						? (int) $sold_counts[ $name ]
						: ( isset( $tier['ticket_sold'] ) ? (int) $tier['ticket_sold'] : 0 );
					$rem   = max( 0, $cap - $sold );
					$tot_cap  += $cap;
					$tot_sold += $sold;
					?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td><?php echo esc_html( vatan_to_persian_digits( $cap ) ); ?></td>
						<td><?php echo esc_html( vatan_to_persian_digits( $sold ) ); ?></td>
						<td><?php echo esc_html( vatan_to_persian_digits( $rem ) ); ?></td>
						<td><?php echo esc_html( vatan_format_price( $price ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th><?php esc_html_e( 'Total', 'vatan-event' ); ?></th>
					<th><?php echo esc_html( vatan_to_persian_digits( $tot_cap ) ); ?></th>
					<th><?php echo esc_html( vatan_to_persian_digits( $tot_sold ) ); ?></th>
					<th><?php echo esc_html( vatan_to_persian_digits( max( 0, $tot_cap - $tot_sold ) ) ); ?></th>
					<th>—</th>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php vatan_render_seat_editor( $event_id ); ?>

	<details class="vatan-seat-manager__advanced">
		<summary><?php esc_html_e( 'Advanced — block seats by row-col key', 'vatan-event' ); ?></summary>
		<form method="post">
			<?php wp_nonce_field( 'vatan_block_seats', 'vatan_block_nonce' ); ?>
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<p>
				<label for="blocked"><?php esc_html_e( 'Comma- or newline-separated row-col keys (e.g. "1-5, 2-3, 7-9"):', 'vatan-event' ); ?></label>
			</p>
			<textarea id="blocked" name="blocked" rows="4" class="large-text code"><?php echo esc_textarea( implode( ', ', $blocked ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Manual override. The editor above sets these visually; this textarea exists for bulk operations.', 'vatan-event' ); ?></p>
			<?php submit_button( __( 'Save blocked seats', 'vatan-event' ), 'secondary', 'vatan_block_save' ); ?>
		</form>
	</details>

	<h3><?php esc_html_e( 'Export buyers', 'vatan-event' ); ?></h3>
	<p>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vatan_export_buyers&event_id=' . $event_id ), 'vatan_export_buyers' ) ); ?>">
			<?php esc_html_e( 'Download CSV', 'vatan-event' ); ?>
		</a>
		<span class="description"><?php esc_html_e( 'Order ID, customer name, email, ticket type, seats, total. Empty until orders exist with line-item meta `_vatan_event_id`.', 'vatan-event' ); ?></span>
	</p>
	<?php
}

/**
 * Sum quantities by ticket type from completed/processing WC orders for
 * a given event. Returns [ 'Economy' => 12, ... ].
 *
 * @param int $event_id
 * @return int[]
 */
function vatan_count_sold_per_type( $event_id ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}
	$orders = wc_get_orders( array(
		'limit'      => -1,
		'status'     => array( 'wc-completed', 'wc-processing' ),
	) );
	if ( ! is_array( $orders ) ) {
		return array();
	}
	$counts = array();
	foreach ( $orders as $order ) {
		foreach ( $order->get_items() as $item ) {
			$type = (string) $item->get_meta( 'ticket_type' );
			if ( $type ) {
				$counts[ $type ] = ( isset( $counts[ $type ] ) ? $counts[ $type ] : 0 ) + (int) $item->get_quantity();
			}
		}
	}
	return $counts;
}

/**
 * admin-post.php handler — streams a CSV of buyers for the given event.
 */
function vatan_handle_export_buyers() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'vatan-event' ) );
	}
	check_admin_referer( 'vatan_export_buyers' );

	$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
	if ( ! $event_id ) {
		wp_die( esc_html__( 'Event not specified.', 'vatan-event' ) );
	}

	$filename = sanitize_title( get_the_title( $event_id ) );
	if ( ! $filename ) {
		$filename = 'event-' . $event_id;
	}
	$filename .= '-buyers-' . gmdate( 'Y-m-d' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$out = fopen( 'php://output', 'w' );
	// UTF-8 BOM so Excel opens Persian text without garbling.
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Order ID', 'Customer', 'Email', 'Ticket type', 'Seats', 'Total' ) );

	if ( function_exists( 'wc_get_orders' ) ) {
		$orders = wc_get_orders( array(
			'limit'      => -1,
		) );
		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				$types = array();
				$seats = array();
				foreach ( $order->get_items() as $item ) {
					$t = (string) $item->get_meta( 'ticket_type' );
					if ( $t ) {
						$types[] = $t;
					}
					$seats_meta = $item->get_meta( 'seats' );
					if ( is_array( $seats_meta ) ) {
						foreach ( $seats_meta as $seat ) {
							$row = isset( $seat['row'] ) ? (int) $seat['row'] : '';
							$col = isset( $seat['col'] ) ? (int) $seat['col'] : '';
							if ( $row && $col ) {
								$seats[] = $row . '-' . $col;
							}
						}
					}
				}
				fputcsv( $out, array(
					$order->get_id(),
					trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					$order->get_billing_email(),
					implode( ', ', array_unique( $types ) ),
					implode( '; ', $seats ),
					$order->get_total(),
				) );
			}
		}
	}

	fclose( $out );
	exit;
}
add_action( 'admin_post_vatan_export_buyers', 'vatan_handle_export_buyers' );

/**
 * Visual seat-map editor.
 *
 * Renders a paintable grid + tier toolbar. State serializes to the same
 * `seat_map_config` ACF JSON field the front-end SeatMap class reads. Row
 * and column counts are stored alongside (`seat_map_rows`, `seat_map_cols`).
 *
 * Output schema (per section):
 *   { "type": "Economy", "price": 850000, "color": "#06B6D4",
 *     "seats": ["1-1","1-2","2-1",…] }
 *
 * @param int $event_id
 */
function vatan_render_seat_editor( $event_id ) {
	$tickets = function_exists( 'get_field' ) ? get_field( 'ticket_types', $event_id ) : array();
	if ( ! is_array( $tickets ) ) {
		$tickets = array();
	}
	?>
	<h3><?php esc_html_e( 'Seat Map Editor', 'vatan-event' ); ?></h3>
	<?php

	if ( empty( $tickets ) ) {
		?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Define ticket types on the event before configuring the seat map.', 'vatan-event' ); ?>
				<a href="<?php echo esc_url( get_edit_post_link( $event_id ) ); ?>">
					<?php esc_html_e( 'Edit event →', 'vatan-event' ); ?>
				</a>
			</p>
		</div>
		<?php
		return;
	}

	if ( isset( $_GET['seat_map_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible inline"><p>' . esc_html__( 'Seat map saved.', 'vatan-event' ) . '</p></div>';
	}

	$rows        = function_exists( 'get_field' ) ? (int) get_field( 'seat_map_rows', $event_id ) : 0;
	$cols        = function_exists( 'get_field' ) ? (int) get_field( 'seat_map_cols', $event_id ) : 0;
	$config_json = function_exists( 'get_field' ) ? (string) get_field( 'seat_map_config', $event_id ) : '';
	if ( $rows < 1 ) {
		$rows = 5;
	}
	if ( $cols < 1 ) {
		$cols = 8;
	}

	$config = array(
		'rows'     => $rows,
		'cols'     => $cols,
		'sections' => array(),
		'reserved' => array(),
		'hallways' => array(),
		'tables'   => array(),
	);
	if ( $config_json ) {
		$decoded = json_decode( $config_json, true );
		if ( is_array( $decoded ) ) {
			$config['sections'] = isset( $decoded['sections'] ) && is_array( $decoded['sections'] ) ? $decoded['sections'] : array();
			$config['reserved'] = isset( $decoded['reserved'] ) && is_array( $decoded['reserved'] ) ? $decoded['reserved'] : array();
			$config['hallways'] = isset( $decoded['hallways'] ) && is_array( $decoded['hallways'] ) ? $decoded['hallways'] : array();
			$config['tables']   = isset( $decoded['tables'] )   && is_array( $decoded['tables'] )   ? $decoded['tables']   : array();
		}
	}

	$tiers = array();
	$fallback_palette = array( '#06B6D4', '#8B5CF6', '#F59E0B', '#10B981', '#FF2D78', '#EF4444' );
	$i                = 0;
	foreach ( $tickets as $tier ) {
		$name = isset( $tier['ticket_name'] ) ? (string) $tier['ticket_name'] : '';
		if ( '' === $name ) {
			continue;
		}
		$price = isset( $tier['ticket_price'] ) ? (float) $tier['ticket_price'] : 0.0;
		$color = isset( $tier['ticket_color'] ) ? (string) $tier['ticket_color'] : '';
		if ( ! sanitize_hex_color( $color ) ) {
			$color = $fallback_palette[ $i % count( $fallback_palette ) ];
		}
		$tiers[] = array(
			'name'  => $name,
			'price' => $price,
			'color' => $color,
		);
		$i++;
	}

	$payload = array(
		'rows'   => $rows,
		'cols'   => $cols,
		'tiers'  => $tiers,
		'config' => $config,
		'i18n'   => array(
			'reserved'         => __( 'Reserved', 'vatan-event' ),
			'hallway'          => __( 'Hallway', 'vatan-event' ),
			'erase'            => __( 'Erase', 'vatan-event' ),
			'unassigned'       => __( 'Unassigned', 'vatan-event' ),
			'paintLabel'       => __( 'Paint:', 'vatan-event' ),
			'rowsLabel'        => __( 'Rows:', 'vatan-event' ),
			'colsLabel'        => __( 'Columns:', 'vatan-event' ),
			'resetConfirm'     => __( 'Clear every painted, reserved, and hallway seat (tables stay)?', 'vatan-event' ),
			'unsavedChanges'   => __( 'You have unsaved changes.', 'vatan-event' ),
			'pickPaintFirst'   => __( 'Pick a tier or tool first.', 'vatan-event' ),
			'tableLabel'       => __( 'Label', 'vatan-event' ),
			'tableSeats'       => __( 'Seats', 'vatan-event' ),
			'tableTier'        => __( 'Tier', 'vatan-event' ),
			'tableRow'         => __( 'Lane', 'vatan-event' ),
			'tableRowHint'     => __( 'Lane 1 is closest to the grid.', 'vatan-event' ),
			'tableRemove'      => __( 'Remove table', 'vatan-event' ),
			'tableAdd'         => __( 'Add round table', 'vatan-event' ),
			'tableNoTiers'     => __( 'Add a ticket type to the event first to assign a tier.', 'vatan-event' ),
			'tableNone'        => __( 'No tables yet. Add one to start a banquet / round-table layout.', 'vatan-event' ),
		),
	);
	?>

	<div class="vatan-seat-editor" data-vatan-seat-editor>
		<div class="vatan-seat-editor__toolbar">
			<div class="vatan-seat-editor__group">
				<span class="vatan-seat-editor__label"><?php esc_html_e( 'Paint:', 'vatan-event' ); ?></span>
				<div class="vatan-seat-editor__tiers" data-vatan-tier-buttons></div>
				<button type="button" class="vatan-tool" data-tool="reserved">
					<span class="vatan-tool__chip vatan-tool__chip--reserved" aria-hidden="true">×</span>
					<?php esc_html_e( 'Reserved', 'vatan-event' ); ?>
				</button>
				<button type="button" class="vatan-tool" data-tool="hallway">
					<span class="vatan-tool__chip vatan-tool__chip--hallway" aria-hidden="true">⇕</span>
					<?php esc_html_e( 'Hallway', 'vatan-event' ); ?>
				</button>
				<button type="button" class="vatan-tool" data-tool="erase">
					<?php esc_html_e( 'Erase', 'vatan-event' ); ?>
				</button>
			</div>
			<div class="vatan-seat-editor__group">
				<label class="vatan-seat-editor__num">
					<span><?php esc_html_e( 'Rows', 'vatan-event' ); ?></span>
					<input type="number" min="1" max="50" value="<?php echo esc_attr( $rows ); ?>" data-vatan-rows-control />
				</label>
				<label class="vatan-seat-editor__num">
					<span><?php esc_html_e( 'Columns', 'vatan-event' ); ?></span>
					<input type="number" min="1" max="50" value="<?php echo esc_attr( $cols ); ?>" data-vatan-cols-control />
				</label>
			</div>
		</div>

		<div class="vatan-seat-editor__stage" aria-hidden="true">
			<span><?php esc_html_e( 'Stage', 'vatan-event' ); ?></span>
		</div>

		<div class="vatan-seat-editor__grid" data-vatan-editor-grid></div>

		<div class="vatan-seat-editor__counts" data-vatan-counts></div>

		<!-- Round tables — organised into lanes that flow below the seat grid -->
		<section class="vatan-seat-editor__tables">
			<header class="vatan-seat-editor__tables-head">
				<h4><?php esc_html_e( 'Round tables', 'vatan-event' ); ?></h4>
				<button type="button" class="button" data-vatan-table-add>
					<?php esc_html_e( '+ Add round table', 'vatan-event' ); ?>
				</button>
			</header>
			<p class="description"><?php esc_html_e( 'Each table sits in a numbered lane below the seat grid. Lane 1 is the row nearest the grid; lane 2 is further below; and so on. Tables in the same lane line up horizontally — no overlap with the seats is possible.', 'vatan-event' ); ?></p>

			<!-- Live preview — mini wireframe of how the seat grid + table
			     lanes will lay out on the event page. Updates as the admin
			     edits the rows below. -->
			<div class="vatan-seat-editor__tables-preview" data-vatan-tables-preview>
				<div class="vatan-seat-editor__preview-grid" data-vatan-tables-preview-grid aria-hidden="true"></div>
				<div class="vatan-seat-editor__preview-lanes" data-vatan-tables-preview-lanes></div>
			</div>

			<div class="vatan-seat-editor__tables-list" data-vatan-tables-list></div>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vatan-seat-editor__save">
			<?php wp_nonce_field( 'vatan_save_seat_map' ); ?>
			<input type="hidden" name="action" value="vatan_save_seat_map" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="seat_map_rows" data-vatan-rows-input value="<?php echo esc_attr( $rows ); ?>" />
			<input type="hidden" name="seat_map_cols" data-vatan-cols-input value="<?php echo esc_attr( $cols ); ?>" />
			<input type="hidden" name="seat_map_config" data-vatan-config-input value="" />
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save seat map', 'vatan-event' ); ?></button>
			<button type="button" class="button" data-vatan-editor-reset><?php esc_html_e( 'Reset all', 'vatan-event' ); ?></button>
		</form>

		<script type="application/json" data-vatan-editor-payload>
			<?php echo wp_json_encode( $payload ); ?>
		</script>
	</div>
	<?php
}

/**
 * admin-post.php handler — persist the seat-map editor's output back to ACF.
 */
function vatan_handle_save_seat_map() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit seat maps.', 'vatan-event' ) );
	}
	check_admin_referer( 'vatan_save_seat_map' );

	$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
	if ( ! $event_id || 'event' !== get_post_type( $event_id ) ) {
		wp_die( esc_html__( 'Event not found.', 'vatan-event' ) );
	}

	$rows = isset( $_POST['seat_map_rows'] ) ? max( 1, min( 50, absint( $_POST['seat_map_rows'] ) ) ) : 5;
	$cols = isset( $_POST['seat_map_cols'] ) ? max( 1, min( 50, absint( $_POST['seat_map_cols'] ) ) ) : 8;

	$raw = isset( $_POST['seat_map_config'] ) ? wp_unslash( $_POST['seat_map_config'] ) : '';
	$decoded = json_decode( (string) $raw, true );
	if ( ! is_array( $decoded ) ) {
		wp_die( esc_html__( 'Invalid seat-map data.', 'vatan-event' ) );
	}

	// Re-validate and re-encode server-side. Nothing from the JSON makes it
	// onto disk without going through these filters.
	$clean = array(
		'rows'     => $rows,
		'cols'     => $cols,
		'sections' => array(),
		'reserved' => array(),
		'hallways' => array(),
		'tables'   => array(),
	);

	// Collect valid table ids first so we can also accept "Tid-seat" reserved
	// keys for table seats (and silently drop any pointing at a deleted table).
	$valid_table_ids = array();

	if ( isset( $decoded['tables'] ) && is_array( $decoded['tables'] ) && function_exists( 'vatan_normalize_seat_table' ) ) {
		$seen_ids = array();
		foreach ( $decoded['tables'] as $raw_table ) {
			$normalized = vatan_normalize_seat_table( $raw_table );
			if ( ! $normalized ) {
				continue;
			}
			// Drop duplicate ids.
			if ( isset( $seen_ids[ $normalized['id'] ] ) ) {
				continue;
			}
			$seen_ids[ $normalized['id'] ] = true;
			$valid_table_ids[ $normalized['id'] ] = (int) $normalized['seats'];
			$clean['tables'][] = $normalized;
		}
	}

	$within_grid = function ( $key ) use ( $rows, $cols ) {
		if ( ! is_string( $key ) || ! preg_match( '/^\d+-\d+$/', $key ) ) return false;
		list( $r, $c ) = array_map( 'intval', explode( '-', $key ) );
		return $r >= 1 && $r <= $rows && $c >= 1 && $c <= $cols;
	};
	$within_table = function ( $key ) use ( $valid_table_ids ) {
		if ( ! is_string( $key ) || ! preg_match( '/^(T[A-Za-z0-9_-]*)-(\d+)$/', $key, $m ) ) return false;
		$id = $m[1];
		$n  = (int) $m[2];
		return isset( $valid_table_ids[ $id ] ) && $n >= 1 && $n <= $valid_table_ids[ $id ];
	};

	if ( isset( $decoded['sections'] ) && is_array( $decoded['sections'] ) ) {
		foreach ( $decoded['sections'] as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$keys = array();
			if ( isset( $section['seats'] ) && is_array( $section['seats'] ) ) {
				foreach ( $section['seats'] as $key ) {
					if ( $within_grid( $key ) || $within_table( $key ) ) {
						$keys[] = $key;
					}
				}
			}
			if ( empty( $keys ) ) {
				continue;
			}
			$clean['sections'][] = array(
				'type'  => isset( $section['type'] ) ? sanitize_text_field( (string) $section['type'] ) : '',
				'price' => isset( $section['price'] ) ? (float) $section['price'] : 0.0,
				'color' => isset( $section['color'] ) && sanitize_hex_color( $section['color'] ) ? $section['color'] : '',
				'seats' => array_values( array_unique( $keys ) ),
			);
		}
	}

	if ( isset( $decoded['reserved'] ) && is_array( $decoded['reserved'] ) ) {
		foreach ( $decoded['reserved'] as $key ) {
			if ( $within_grid( $key ) || $within_table( $key ) ) {
				$clean['reserved'][] = $key;
			}
		}
		$clean['reserved'] = array_values( array_unique( $clean['reserved'] ) );
	}

	if ( isset( $decoded['hallways'] ) && is_array( $decoded['hallways'] ) ) {
		foreach ( $decoded['hallways'] as $key ) {
			if ( $within_grid( $key ) ) {
				$clean['hallways'][] = $key;
			}
		}
		$clean['hallways'] = array_values( array_unique( $clean['hallways'] ) );
	}

	$json = wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	if ( function_exists( 'update_field' ) ) {
		update_field( 'seat_map_rows', $rows, $event_id );
		update_field( 'seat_map_cols', $cols, $event_id );
		update_field( 'seat_map_config', $json, $event_id );
	} else {
		update_post_meta( $event_id, 'seat_map_rows', $rows );
		update_post_meta( $event_id, 'seat_map_cols', $cols );
		update_post_meta( $event_id, 'seat_map_config', $json );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=vatan-seat-manager&event_id=' . $event_id . '&seat_map_saved=1' ) );
	exit;
}
add_action( 'admin_post_vatan_save_seat_map', 'vatan_handle_save_seat_map' );
