<?php
/**
 * Music admin — albums list view.
 *
 * Routed via /admin/music/?type=albums.
 *
 * Query params: q, paged, status (live | publish | draft | trash),
 * featured ("1" to filter to featured-only).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ----- Dispatch the editor when ?vatan_action=new|edit ---------------- */
if ( isset( $action ) && in_array( $action, array( 'new', 'edit' ), true ) ) {
	$edit_file = locate_template( 'templates/admin/views/music/album-edit.php', false, false );
	if ( $edit_file ) {
		include $edit_file;
		return;
	}
}

$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'live'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$featured = isset( $_GET['featured'] ) && '1' === (string) $_GET['featured']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$allowed_statuses = array( 'live', 'publish', 'draft', 'trash' );
if ( ! in_array( $status, $allowed_statuses, true ) ) {
	$status = 'live';
}

$per_page = 20;
$args = array(
	'post_type'      => 'album',
	'posts_per_page' => $per_page,
	'paged'          => $paged,
	's'              => $search,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'post_status'    => ( 'live' === $status ) ? array( 'publish', 'draft' ) : $status,
);
if ( $featured ) {
	$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'album_is_featured', 'value' => '1', 'compare' => '=' ),
	);
}

$query = new WP_Query( $args );

$counts = (object) wp_count_posts( 'album' );
$status_counts = array(
	'live'    => (int) $counts->publish + (int) $counts->draft,
	'publish' => (int) $counts->publish,
	'draft'   => (int) $counts->draft,
	'trash'   => (int) $counts->trash,
);
$status_labels = array(
	'live'    => __( 'All', 'vatan-event' ),
	'publish' => __( 'Published', 'vatan-event' ),
	'draft'   => __( 'Drafts', 'vatan-event' ),
	'trash'   => __( 'Trash', 'vatan-event' ),
);

$build_url = static function ( array $overrides ) use ( $status, $search, $featured ) {
	$args = array_filter( array(
		'status'   => $status,
		'q'        => $search,
		'featured' => $featured ? '1' : '',
	), 'strlen' );
	$args = array_merge( $args, $overrides );
	return vatan_music_admin_url( 'albums', $args );
};

/**
 * Count tracks linked to a given album. Inline helper — runs once per row
 * but the query is cheap (only 1 row matched via meta key).
 */
$track_count_for = static function ( int $album_id ): int {
	$q = new WP_Query( array(
		'post_type'      => 'track',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'track_album', 'value' => $album_id, 'compare' => '=' ),
		),
	) );
	return (int) $q->found_posts;
};
?>

<header class="vatan-music-admin__head">
	<h2><?php esc_html_e( 'Albums', 'vatan-event' ); ?></h2>
	<a class="vatan-admin__btn vatan-admin__btn--primary" href="<?php echo esc_url( vatan_music_admin_new_album_url() ); ?>">
		+ <?php esc_html_e( 'Add album', 'vatan-event' ); ?>
	</a>
</header>

<div class="vatan-music-admin__filters">
	<form method="get" action="" style="display:contents;">
		<input type="hidden" name="pagename" value="admin" />
		<input type="hidden" name="view" value="music" />
		<input type="hidden" name="type" value="albums" />
		<?php if ( $status !== 'live' ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
		<?php endif; ?>
		<?php if ( $featured ) : ?>
			<input type="hidden" name="featured" value="1" />
		<?php endif; ?>
		<input class="vatan-music-admin__search" type="search" name="q" value="<?php echo esc_attr( $search ); ?>"
		       placeholder="<?php esc_attr_e( 'Search albums…', 'vatan-event' ); ?>" />
	</form>
	<a class="vatan-music-admin__btn-mini<?php echo $featured ? ' vatan-music-admin__btn-mini--star-on' : ''; ?>"
	   href="<?php echo esc_url( $build_url( array( 'featured' => $featured ? '' : '1', 'paged' => false ) ) ); ?>">
		<span class="vatan-music-admin__star <?php echo $featured ? 'vatan-music-admin__star--on' : ''; ?>">★</span>
		<?php echo $featured ? esc_html__( 'Featured only', 'vatan-event' ) : esc_html__( 'All', 'vatan-event' ); ?>
	</a>
	<span class="vatan-music-admin__count">
		<?php
		/* translators: %s: total album count */
		echo esc_html( sprintf( __( '%s total', 'vatan-event' ), vatan_to_persian_digits( (int) $query->found_posts ) ) );
		?>
	</span>
</div>

<nav class="vatan-music-admin__filters" aria-label="<?php esc_attr_e( 'Status filter', 'vatan-event' ); ?>">
	<?php foreach ( $status_labels as $s => $label ) :
		$count   = (int) ( $status_counts[ $s ] ?? 0 );
		$is_curr = ( $s === $status );
		?>
		<a class="vatan-music-admin__btn-mini<?php echo $is_curr ? ' is-current' : ''; ?>"
		   href="<?php echo esc_url( $build_url( array( 'status' => $s, 'paged' => false ) ) ); ?>">
			<?php echo esc_html( $label ); ?>
			<span style="opacity:.7;">(<?php echo esc_html( vatan_to_persian_digits( $count ) ); ?>)</span>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ( ! $query->have_posts() ) : ?>
	<div class="vatan-music-admin__empty">
		<p><?php
		if ( $search ) {
			/* translators: %s: search query */
			echo esc_html( sprintf( __( 'No albums match "%s".', 'vatan-event' ), $search ) );
		} elseif ( 'trash' === $status ) {
			esc_html_e( 'Trash is empty.', 'vatan-event' );
		} else {
			esc_html_e( 'No albums yet.', 'vatan-event' );
		}
		?></p>
	</div>
<?php else : ?>
	<form id="vatan-music-bulk" method="post" action="">
		<input type="hidden" name="type" value="albums" />
		<?php wp_nonce_field( 'vatan_music_bulk', '_vatan_music_bulk_nonce' ); ?>
	</form>

	<div class="vatan-music-admin__bulk-bar">
		<select form="vatan-music-bulk" name="vatan_music_bulk_action" class="vatan-music-admin__btn-mini">
			<option value=""><?php esc_html_e( 'Bulk actions', 'vatan-event' ); ?></option>
			<?php if ( 'trash' === $status ) : ?>
				<option value="untrash"><?php esc_html_e( 'Restore', 'vatan-event' ); ?></option>
			<?php else : ?>
				<option value="trash"><?php esc_html_e( 'Move to trash', 'vatan-event' ); ?></option>
				<option value="feature"><?php esc_html_e( 'Mark as featured', 'vatan-event' ); ?></option>
				<option value="unfeature"><?php esc_html_e( 'Remove from featured', 'vatan-event' ); ?></option>
			<?php endif; ?>
		</select>
		<button form="vatan-music-bulk" type="submit" class="vatan-music-admin__btn-mini" data-vm-bulk-apply>
			<?php esc_html_e( 'Apply', 'vatan-event' ); ?>
		</button>
		<span class="vatan-music-admin__bulk-count" data-vm-bulk-count></span>
	</div>

	<div class="vatan-music-admin__table-wrap">
		<table class="vatan-music-admin__table">
			<thead>
				<tr>
					<th style="width:32px;"><input type="checkbox" data-vm-bulk-all aria-label="<?php esc_attr_e( 'Select all', 'vatan-event' ); ?>" /></th>
					<th style="width:60px;"><?php esc_html_e( 'Cover', 'vatan-event' ); ?></th>
					<th><?php esc_html_e( 'Title', 'vatan-event' ); ?></th>
					<th><?php esc_html_e( 'Artist', 'vatan-event' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Type', 'vatan-event' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Tracks', 'vatan-event' ); ?></th>
					<th style="width:60px;text-align:center;"><?php esc_html_e( 'Featured', 'vatan-event' ); ?></th>
					<th style="width:180px;text-align:end;"><?php esc_html_e( 'Actions', 'vatan-event' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $query->posts as $alb ) :
					$artist_id    = function_exists( 'get_field' ) ? (int) get_field( 'album_artist', $alb->ID ) : 0;
					$album_type   = function_exists( 'get_field' ) ? (string) get_field( 'album_type', $alb->ID ) : '';
					$is_featured  = (bool) get_post_meta( $alb->ID, 'album_is_featured', true );
					$status_lbl   = get_post_status( $alb );
					$edit_url     = vatan_music_admin_edit_url( (int) $alb->ID );
					$cover_url    = has_post_thumbnail( $alb->ID ) ? get_the_post_thumbnail_url( $alb->ID, 'thumbnail' ) : '';
					$tracks_count = $track_count_for( (int) $alb->ID );
					?>
					<tr>
						<td><input form="vatan-music-bulk" type="checkbox" name="bulk_ids[]" value="<?php echo (int) $alb->ID; ?>" data-vm-bulk-check aria-label="<?php esc_attr_e( 'Select row', 'vatan-event' ); ?>" /></td>
						<td>
							<?php if ( $cover_url ) : ?>
								<img class="vatan-music-admin__cover" src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" />
							<?php else : ?>
								<span class="vatan-music-admin__cover-placeholder" aria-hidden="true">💿</span>
							<?php endif; ?>
						</td>
						<td>
							<a class="vatan-music-admin__row-title" href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( get_the_title( $alb ) ?: __( '(no title)', 'vatan-event' ) ); ?>
							</a>
						</td>
						<td>
							<?php if ( $artist_id ) : ?>
								<a href="<?php echo esc_url( vatan_music_admin_edit_url( $artist_id ) ); ?>" style="color:inherit;">
									<?php echo esc_html( get_the_title( $artist_id ) ); ?>
								</a>
							<?php else : ?>
								<span style="opacity:.5;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<span style="text-transform:capitalize;opacity:.85;"><?php echo esc_html( $album_type ?: 'album' ); ?></span>
						</td>
						<td>
							<span style="opacity:.85;"><?php echo esc_html( vatan_to_persian_digits( $tracks_count ) ); ?></span>
						</td>
						<td style="text-align:center;">
							<?php vatan_music_admin_action_form(
								'toggle-featured',
								(int) $alb->ID,
								'<span class="vatan-music-admin__star ' . ( $is_featured ? 'vatan-music-admin__star--on' : '' ) . '">★</span>',
								array(
									'class'      => 'vatan-music-admin__btn-mini',
									'title'      => $is_featured ? __( 'Remove from featured', 'vatan-event' ) : __( 'Mark as featured', 'vatan-event' ),
									'aria-label' => $is_featured ? __( 'Remove from featured', 'vatan-event' ) : __( 'Mark as featured', 'vatan-event' ),
									'style'      => 'padding:5px 8px;' . ( $is_featured ? 'border-color:rgba(251,191,36,.4);background:rgba(251,191,36,.12);' : '' ),
								)
							); ?>
						</td>
						<td style="text-align:end;">
							<span class="vatan-music-admin__row-actions">
								<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( $edit_url ); ?>">
									<?php esc_html_e( 'Edit', 'vatan-event' ); ?>
								</a>
								<?php if ( 'trash' === $status_lbl ) : ?>
									<?php vatan_music_admin_action_form(
										'untrash',
										(int) $alb->ID,
										esc_html__( 'Restore', 'vatan-event' ),
										array( 'class' => 'vatan-music-admin__btn-mini' )
									); ?>
								<?php else : ?>
									<?php vatan_music_admin_action_form(
										'delete',
										(int) $alb->ID,
										esc_html__( 'Delete', 'vatan-event' ),
										array(
											'class'   => 'vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger',
											'onclick' => "return confirm('" . esc_js( __( 'Move this album to trash? Tracks remain.', 'vatan-event' ) ) . "');",
										)
									); ?>
								<?php endif; ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $query->max_num_pages > 1 ) : ?>
		<nav class="vatan-music-admin__pagination">
			<?php for ( $p = 1; $p <= $query->max_num_pages; $p++ ) :
				if ( $p === $paged ) {
					echo '<span class="is-current">' . esc_html( vatan_to_persian_digits( $p ) ) . '</span>';
				} else {
					echo '<a href="' . esc_url( $build_url( array( 'paged' => $p ) ) ) . '">' . esc_html( vatan_to_persian_digits( $p ) ) . '</a>';
				}
			endfor; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
