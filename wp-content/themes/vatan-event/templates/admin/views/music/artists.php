<?php
/**
 * Music admin — artists list view.
 *
 * Routed via /admin/music/?type=artists.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ----- Dispatch the editor when ?vatan_action=new|edit ---------------- */
if ( isset( $action ) && in_array( $action, array( 'new', 'edit' ), true ) ) {
	$edit_file = locate_template( 'templates/admin/views/music/artist-edit.php', false, false );
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
	'post_type'      => 'artist',
	'posts_per_page' => $per_page,
	'paged'          => $paged,
	's'              => $search,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'post_status'    => ( 'live' === $status ) ? array( 'publish', 'draft' ) : $status,
);
if ( $featured ) {
	$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'artist_is_featured', 'value' => '1', 'compare' => '=' ),
	);
}

$query = new WP_Query( $args );

$counts = (object) wp_count_posts( 'artist' );
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
	return vatan_music_admin_url( 'artists', $args );
};

$catalog_count_for = static function ( int $artist_id ): array {
	$tracks = (int) ( new WP_Query( array(
		'post_type'      => 'track',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'track_artist', 'value' => $artist_id, 'compare' => '=' ),
		),
	) ) )->found_posts;
	$albums = (int) ( new WP_Query( array(
		'post_type'      => 'album',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'album_artist', 'value' => $artist_id, 'compare' => '=' ),
		),
	) ) )->found_posts;
	return array( 'tracks' => $tracks, 'albums' => $albums );
};
?>

<header class="vatan-music-admin__head">
	<h2><?php esc_html_e( 'Artists', 'vatan-event' ); ?></h2>
	<a class="vatan-admin__btn vatan-admin__btn--primary" href="<?php echo esc_url( vatan_music_admin_new_artist_url() ); ?>">
		+ <?php esc_html_e( 'Add artist', 'vatan-event' ); ?>
	</a>
</header>

<div class="vatan-music-admin__filters">
	<form method="get" action="" style="display:contents;">
		<input type="hidden" name="pagename" value="admin" />
		<input type="hidden" name="view" value="music" />
		<input type="hidden" name="type" value="artists" />
		<?php if ( $status !== 'live' ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
		<?php endif; ?>
		<?php if ( $featured ) : ?>
			<input type="hidden" name="featured" value="1" />
		<?php endif; ?>
		<input class="vatan-music-admin__search" type="search" name="q" value="<?php echo esc_attr( $search ); ?>"
		       placeholder="<?php esc_attr_e( 'Search artists…', 'vatan-event' ); ?>" />
	</form>
	<a class="vatan-music-admin__btn-mini<?php echo $featured ? ' vatan-music-admin__btn-mini--star-on' : ''; ?>"
	   href="<?php echo esc_url( $build_url( array( 'featured' => $featured ? '' : '1', 'paged' => false ) ) ); ?>">
		<span class="vatan-music-admin__star <?php echo $featured ? 'vatan-music-admin__star--on' : ''; ?>">★</span>
		<?php echo $featured ? esc_html__( 'Featured only', 'vatan-event' ) : esc_html__( 'All', 'vatan-event' ); ?>
	</a>
	<span class="vatan-music-admin__count">
		<?php
		/* translators: %s: total artist count */
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
			echo esc_html( sprintf( __( 'No artists match "%s".', 'vatan-event' ), $search ) );
		} elseif ( 'trash' === $status ) {
			esc_html_e( 'Trash is empty.', 'vatan-event' );
		} else {
			esc_html_e( 'No artists yet.', 'vatan-event' );
		}
		?></p>
	</div>
<?php else : ?>
	<form id="vatan-music-bulk" method="post" action="">
		<input type="hidden" name="type" value="artists" />
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
					<th style="width:60px;"><?php esc_html_e( 'Photo', 'vatan-event' ); ?></th>
					<th><?php esc_html_e( 'Name', 'vatan-event' ); ?></th>
					<th><?php esc_html_e( 'Country', 'vatan-event' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Tracks', 'vatan-event' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Albums', 'vatan-event' ); ?></th>
					<th style="width:60px;text-align:center;"><?php esc_html_e( 'Featured', 'vatan-event' ); ?></th>
					<th style="width:180px;text-align:end;"><?php esc_html_e( 'Actions', 'vatan-event' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $query->posts as $art ) :
					$country     = function_exists( 'get_field' ) ? (string) get_field( 'artist_country', $art->ID ) : '';
					$is_featured = (bool) get_post_meta( $art->ID, 'artist_is_featured', true );
					$status_lbl  = get_post_status( $art );
					$edit_url    = vatan_music_admin_edit_url( (int) $art->ID );
					$photo_url   = has_post_thumbnail( $art->ID ) ? get_the_post_thumbnail_url( $art->ID, 'thumbnail' ) : '';
					$catalog     = $catalog_count_for( (int) $art->ID );
					?>
					<tr>
						<td><input form="vatan-music-bulk" type="checkbox" name="bulk_ids[]" value="<?php echo (int) $art->ID; ?>" data-vm-bulk-check aria-label="<?php esc_attr_e( 'Select row', 'vatan-event' ); ?>" /></td>
						<td>
							<?php if ( $photo_url ) : ?>
								<img class="vatan-music-admin__cover vatan-music-admin__cover--round" src="<?php echo esc_url( $photo_url ); ?>" alt="" loading="lazy" />
							<?php else : ?>
								<span class="vatan-music-admin__cover-placeholder" style="border-radius:50%;" aria-hidden="true">🎤</span>
							<?php endif; ?>
						</td>
						<td>
							<a class="vatan-music-admin__row-title" href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( get_the_title( $art ) ?: __( '(no name)', 'vatan-event' ) ); ?>
							</a>
						</td>
						<td>
							<?php echo $country ? esc_html( $country ) : '<span style="opacity:.5;">—</span>'; ?>
						</td>
						<td>
							<span style="opacity:.85;"><?php echo esc_html( vatan_to_persian_digits( $catalog['tracks'] ) ); ?></span>
						</td>
						<td>
							<span style="opacity:.85;"><?php echo esc_html( vatan_to_persian_digits( $catalog['albums'] ) ); ?></span>
						</td>
						<td style="text-align:center;">
							<?php vatan_music_admin_action_form(
								'toggle-featured',
								(int) $art->ID,
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
										(int) $art->ID,
										esc_html__( 'Restore', 'vatan-event' ),
										array( 'class' => 'vatan-music-admin__btn-mini' )
									); ?>
								<?php else : ?>
									<?php vatan_music_admin_action_form(
										'delete',
										(int) $art->ID,
										esc_html__( 'Delete', 'vatan-event' ),
										array(
											'class'   => 'vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger',
											'onclick' => "return confirm('" . esc_js( __( 'Move this artist to trash? Linked tracks and albums remain.', 'vatan-event' ) ) . "');",
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
