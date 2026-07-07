<?php
/**
 * Admin dashboard — events list view.
 *
 * Paginated list of `event` posts with status filter, search box, and
 * per-row quick info (date, city, tickets sold). Each row links to the
 * editor view (/admin/events/edit/?id=N) — which will land in Batch D.
 *
 * Query params (all optional):
 *   - status   one of: all | publish | pending | draft | private
 *              (defaults to "all")
 *   - q        title search string
 *   - paged    page number (1-indexed)
 *   - orderby  one of: date | title | event_date (default: date)
 *   - order    asc | desc (default desc)
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ----- Dispatch the editor when ?vatan_action=edit -------------------- */

// page-admin.php routes /admin/events/edit/ → view=events + vatan_action=edit.
// Hand the request off to event-edit.php in that case; everything below this
// block is the list view only.
if ( isset( $action ) && 'edit' === $action ) {
	$edit_file = locate_template( 'templates/admin/views/event-edit.php', false, false );
	if ( $edit_file ) {
		include $edit_file;
		return;
	}
}

/* ----- Parse filters from the URL ------------------------------------- */

$allowed_statuses = array( 'all', 'publish', 'pending', 'draft', 'private' );
$status_filter    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $status_filter, $allowed_statuses, true ) ) {
	$status_filter = 'all';
}

$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$allowed_orderby = array( 'date', 'title', 'event_date' );
$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
	$orderby = 'date';
}
$order = isset( $_GET['order'] ) && 'asc' === strtolower( (string) $_GET['order'] ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/* ----- Build the query ----------------------------------------------- */

$per_page = 20;
$args     = array(
	'post_type'      => 'event',
	'post_status'    => ( 'all' === $status_filter )
		? array( 'publish', 'pending', 'draft', 'private', 'future' )
		: $status_filter,
	'posts_per_page' => $per_page,
	'paged'          => $paged,
	's'              => $search,
	'orderby'        => 'date',
	'order'          => $order,
);
if ( 'title' === $orderby ) {
	$args['orderby'] = 'title';
} elseif ( 'event_date' === $orderby ) {
	$args['orderby']  = 'meta_value';
	$args['meta_key'] = 'event_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$args['meta_type'] = 'DATE';
}

$query = new WP_Query( $args );

/* ----- Status tab counts --------------------------------------------- */

$counts = (object) wp_count_posts( 'event' );
$total_all = (int) $counts->publish + (int) $counts->pending + (int) $counts->draft + (int) $counts->private;
$tab_counts = array(
	'all'     => $total_all,
	'publish' => (int) $counts->publish,
	'pending' => (int) $counts->pending,
	'draft'   => (int) $counts->draft,
	'private' => (int) $counts->private,
);
$tab_labels = array(
	'all'     => __( 'All', 'vatan-event' ),
	'publish' => __( 'Published', 'vatan-event' ),
	'pending' => __( 'Pending', 'vatan-event' ),
	'draft'   => __( 'Drafts', 'vatan-event' ),
	'private' => __( 'Private', 'vatan-event' ),
);

/* ----- Helper: build a URL preserving the other filters --------------- */

$base_url = vatan_admin_url( 'events' );
$build_url = static function ( array $overrides ) use ( $base_url, $status_filter, $search, $orderby, $order ) {
	$query = array_filter( array(
		'status'  => $status_filter !== 'all' ? $status_filter : null,
		'q'       => $search ?: null,
		'orderby' => $orderby !== 'date' ? $orderby : null,
		'order'   => $order !== 'DESC' ? strtolower( $order ) : null,
	), static function ( $v ) { return null !== $v && '' !== $v; } );
	foreach ( $overrides as $k => $v ) {
		if ( null === $v || '' === $v ) {
			unset( $query[ $k ] );
		} else {
			$query[ $k ] = $v;
		}
	}
	return $query ? add_query_arg( $query, $base_url ) : $base_url;
};
?>

<div class="vatan-admin__events">

	<nav class="vatan-admin__tabs" aria-label="<?php esc_attr_e( 'Status filter', 'vatan-event' ); ?>">
		<?php foreach ( $tab_labels as $key => $label ) :
			$url = $build_url( array( 'status' => 'all' === $key ? null : $key, 'paged' => null ) );
			$is_active = ( $key === $status_filter );
			?>
			<a class="vatan-admin__tab<?php echo $is_active ? ' is-active' : ''; ?>"
			   href="<?php echo esc_url( $url ); ?>"
			   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
				<span class="vatan-admin__tab-count">
					<?php echo esc_html( vatan_to_persian_digits( (int) $tab_counts[ $key ] ) ); ?>
				</span>
			</a>
		<?php endforeach; ?>
	</nav>

	<form class="vatan-admin__filters" method="get" action="<?php echo esc_url( $base_url ); ?>">
		<?php if ( 'all' !== $status_filter ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>" />
		<?php endif; ?>
		<label class="vatan-admin__search" for="vatan-events-q">
			<span class="screen-reader-text"><?php esc_html_e( 'Search events', 'vatan-event' ); ?></span>
			<input
				type="search"
				id="vatan-events-q"
				name="q"
				value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Search by title…', 'vatan-event' ); ?>"
				autocomplete="off"
			/>
		</label>
		<button class="vatan-admin__btn" type="submit"><?php esc_html_e( 'Filter', 'vatan-event' ); ?></button>
		<?php if ( $search ) : ?>
			<a class="vatan-admin__link" href="<?php echo esc_url( $build_url( array( 'q' => null, 'paged' => null ) ) ); ?>">
				<?php esc_html_e( 'Clear', 'vatan-event' ); ?>
			</a>
		<?php endif; ?>
	</form>

	<?php if ( ! $query->have_posts() ) : ?>

		<div class="vatan-admin__empty-state">
			<h2><?php esc_html_e( 'No events match your filters.', 'vatan-event' ); ?></h2>
			<?php if ( $search || 'all' !== $status_filter ) : ?>
				<p>
					<a class="vatan-admin__link" href="<?php echo esc_url( $base_url ); ?>">
						<?php esc_html_e( 'Reset filters', 'vatan-event' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p>
					<a class="vatan-admin__btn vatan-admin__btn--primary"
					   href="<?php echo esc_url( function_exists( 'vatan_static_page_url' ) ? vatan_static_page_url( 'create-event' ) : home_url( '/create-event/' ) ); ?>">
						+ <?php esc_html_e( 'Create your first event', 'vatan-event' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

	<?php else : ?>

		<div class="vatan-admin__table-wrap">
			<table class="vatan-admin__table">
				<thead>
					<tr>
						<th class="vatan-admin__col-title">
							<a href="<?php echo esc_url( $build_url( array( 'orderby' => 'title', 'order' => ( 'title' === $orderby && 'ASC' === $order ) ? 'desc' : 'asc', 'paged' => null ) ) ); ?>">
								<?php esc_html_e( 'Event', 'vatan-event' ); ?>
								<?php if ( 'title' === $orderby ) : ?>
									<span aria-hidden="true"><?php echo 'ASC' === $order ? '↑' : '↓'; ?></span>
								<?php endif; ?>
							</a>
						</th>
						<th>
							<a href="<?php echo esc_url( $build_url( array( 'orderby' => 'event_date', 'order' => ( 'event_date' === $orderby && 'ASC' === $order ) ? 'desc' : 'asc', 'paged' => null ) ) ); ?>">
								<?php esc_html_e( 'Date', 'vatan-event' ); ?>
								<?php if ( 'event_date' === $orderby ) : ?>
									<span aria-hidden="true"><?php echo 'ASC' === $order ? '↑' : '↓'; ?></span>
								<?php endif; ?>
							</a>
						</th>
						<th><?php esc_html_e( 'City', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Sold', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'vatan-event' ); ?></th>
						<th><?php esc_html_e( 'Status', 'vatan-event' ); ?></th>
						<th class="vatan-admin__col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'vatan-event' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post();
						$post_id  = get_the_ID();
						$date     = function_exists( 'get_field' ) ? (string) get_field( 'event_date', $post_id ) : (string) get_post_meta( $post_id, 'event_date', true );
						$date_fmt = $date ? vatan_event_date_display( $date ) : '—';

						$cities      = get_the_terms( $post_id, 'event_city' );
						$city_labels = array();
						if ( is_array( $cities ) ) {
							foreach ( $cities as $c ) {
								$city_labels[] = $c->name;
							}
						}

						$sold    = function_exists( 'vatan_event_tickets_sold' ) ? (int) vatan_event_tickets_sold( $post_id ) : 0;
						$revenue = function_exists( 'vatan_event_gross_revenue' ) ? (float) vatan_event_gross_revenue( $post_id ) : 0.0;

						$status   = get_post_status();
						$edit_url = vatan_admin_url( 'events', array( 'vatan_action' => 'edit', 'id' => $post_id ) );
						$view_url = get_permalink( $post_id );
						?>
						<tr>
							<td class="vatan-admin__col-title">
								<a class="vatan-admin__row-title" href="<?php echo esc_url( $edit_url ); ?>"><?php the_title(); ?></a>
								<?php if ( has_post_thumbnail() ) : ?>
									<span class="vatan-admin__row-thumb-tag" aria-hidden="true">🖼</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $date_fmt ); ?></td>
							<td><?php echo esc_html( $city_labels ? implode( ', ', $city_labels ) : '—' ); ?></td>
							<td><?php echo esc_html( vatan_to_persian_digits( $sold ) ); ?></td>
							<td><?php echo esc_html( vatan_format_price( $revenue ) ); ?></td>
							<td>
								<span class="vatan-admin__badge vatan-admin__badge--<?php echo esc_attr( $status ); ?>">
									<?php echo esc_html( $status ); ?>
								</span>
							</td>
							<td class="vatan-admin__col-actions">
								<a class="vatan-admin__link" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'vatan-event' ); ?></a>
								<?php if ( $view_url && 'publish' === $status ) : ?>
									· <a class="vatan-admin__link" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'vatan-event' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endwhile; wp_reset_postdata(); ?>
				</tbody>
			</table>
		</div>

		<?php
		$total_pages = (int) $query->max_num_pages;
		if ( $total_pages > 1 ) :
			?>
			<nav class="vatan-admin__pagination" aria-label="<?php esc_attr_e( 'Events pagination', 'vatan-event' ); ?>">
				<?php
				$big = 999999999;
				$links = paginate_links( array(
					'base'      => str_replace( $big, '%#%', esc_url( add_query_arg( 'paged', $big, $base_url ) ) ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'mid_size'  => 1,
					'end_size'  => 1,
					'prev_text' => '‹ ' . __( 'Prev', 'vatan-event' ),
					'next_text' => __( 'Next', 'vatan-event' ) . ' ›',
					'type'      => 'array',
					'add_args'  => array_filter( array(
						'status'  => $status_filter !== 'all' ? $status_filter : null,
						'q'       => $search ?: null,
						'orderby' => $orderby !== 'date' ? $orderby : null,
						'order'   => $order !== 'DESC' ? strtolower( $order ) : null,
					), static function ( $v ) { return null !== $v && '' !== $v; } ),
				) );
				if ( is_array( $links ) ) {
					echo '<ul class="vatan-admin__pagination-list">';
					foreach ( $links as $link ) {
						echo '<li>' . $link . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — paginate_links output.
					}
					echo '</ul>';
				}
				?>
			</nav>
		<?php endif; ?>

	<?php endif; ?>

</div>
