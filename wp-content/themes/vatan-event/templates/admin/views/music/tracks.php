<?php
/**
 * Music admin — tracks list view.
 *
 * Routed via /admin/music/?type=tracks (or pretty `/admin/music/tracks/`).
 *
 * Query params:
 *   q       title search
 *   paged   page number
 *   status  one of: all | publish | draft | trash (default: all but trash)
 *
 * Inherits $type from music.php dispatcher; $view + $action from page-admin.php.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ----- Dispatch the editor when ?vatan_action=new|edit ---------------- */
if ( isset( $action ) && in_array( $action, array( 'new', 'edit' ), true ) ) {
	$edit_file = locate_template( 'templates/admin/views/music/track-edit.php', false, false );
	if ( $edit_file ) {
		include $edit_file;
		return;
	}
}

$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'live'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$allowed_statuses = array( 'live', 'publish', 'draft', 'trash' );
if ( ! in_array( $status, $allowed_statuses, true ) ) {
	$status = 'live';
}

$per_page = 20;
$args = array(
	'post_type'      => 'track',
	'posts_per_page' => $per_page,
	'paged'          => $paged,
	's'              => $search,
	'orderby'        => 'date',
	'order'          => 'DESC',
);
$args['post_status'] = ( 'live' === $status )
	? array( 'publish', 'draft' )
	: $status;

$query = new WP_Query( $args );

// Per-status counts for the sub-tabs (cheaper than running 4 full queries).
$counts = (object) wp_count_posts( 'track' );
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

// URL builders that preserve the current filters.
$build_url = static function ( array $overrides ) use ( $status, $search ) {
	$args = array_filter( array(
		'status' => $status,
		'q'      => $search,
	), 'strlen' );
	$args = array_merge( $args, $overrides );
	return vatan_music_admin_url( 'tracks', $args );
};
?>

<header class="vatan-music-admin__head">
	<h2><?php esc_html_e( 'Tracks', 'vatan-event' ); ?></h2>
	<a class="vatan-admin__btn vatan-admin__btn--primary" href="<?php echo esc_url( vatan_music_admin_new_track_url() ); ?>">
		+ <?php esc_html_e( 'Add track', 'vatan-event' ); ?>
	</a>
</header>

<div class="vatan-music-admin__filters">
	<form method="get" action="" style="display:contents;">
		<input type="hidden" name="pagename" value="admin" />
		<input type="hidden" name="view" value="music" />
		<input type="hidden" name="type" value="tracks" />
		<?php if ( $status !== 'live' ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
		<?php endif; ?>
		<input class="vatan-music-admin__search" type="search" name="q" value="<?php echo esc_attr( $search ); ?>"
		       placeholder="<?php esc_attr_e( 'Search tracks…', 'vatan-event' ); ?>" />
	</form>
	<span class="vatan-music-admin__count">
		<?php
		/* translators: %s: total track count */
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
		<?php if ( $search ) : ?>
			<p><?php
			/* translators: %s: search query */
			echo esc_html( sprintf( __( 'No tracks match "%s".', 'vatan-event' ), $search ) );
			?></p>
		<?php elseif ( 'trash' === $status ) : ?>
			<p><?php esc_html_e( 'Trash is empty.', 'vatan-event' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'No tracks yet.', 'vatan-event' ); ?></p>
			<p>
				<a class="vatan-admin__btn vatan-admin__btn--primary" href="<?php echo esc_url( vatan_music_admin_new_track_url() ); ?>">
					+ <?php esc_html_e( 'Add your first track', 'vatan-event' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
<?php else : ?>
	<!-- Bulk form lives outside the table so the per-row delete forms
	     (which are nested inside row TDs) don't break HTML5 parsing.
	     Checkboxes below use `form="vatan-music-bulk"` to associate. -->
	<form id="vatan-music-bulk" method="post" action="">
		<input type="hidden" name="type" value="tracks" />
		<?php wp_nonce_field( 'vatan_music_bulk', '_vatan_music_bulk_nonce' ); ?>
	</form>

	<div class="vatan-music-admin__bulk-bar">
		<select form="vatan-music-bulk" name="vatan_music_bulk_action" class="vatan-music-admin__btn-mini">
			<option value=""><?php esc_html_e( 'Bulk actions', 'vatan-event' ); ?></option>
			<?php if ( 'trash' === $status ) : ?>
				<option value="untrash"><?php esc_html_e( 'Restore', 'vatan-event' ); ?></option>
			<?php else : ?>
				<option value="trash"><?php esc_html_e( 'Move to trash', 'vatan-event' ); ?></option>
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
					<th><?php esc_html_e( 'Album', 'vatan-event' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'Status', 'vatan-event' ); ?></th>
					<th style="width:180px;text-align:end;"><?php esc_html_e( 'Actions', 'vatan-event' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $query->posts as $tr ) :
					$album_id    = function_exists( 'get_field' ) ? (int) get_field( 'track_album', $tr->ID ) : 0;
					$artist_id   = function_exists( 'get_field' ) ? (int) get_field( 'track_artist', $tr->ID ) : 0;
					$is_live     = function_exists( 'get_field' ) ? (bool) get_field( 'track_is_live_stream', $tr->ID ) : false;
					$status_lbl  = get_post_status( $tr );
					$edit_url    = vatan_music_admin_edit_url( (int) $tr->ID );
					$cover_url   = function_exists( 'vatan_admin_music_cover' ) ? vatan_admin_music_cover( (int) $tr->ID ) : '';
					if ( ! $cover_url && $album_id && has_post_thumbnail( $album_id ) ) {
						$cover_url = get_the_post_thumbnail_url( $album_id, 'thumbnail' );
					}
					?>
					<tr>
						<td><input form="vatan-music-bulk" type="checkbox" name="bulk_ids[]" value="<?php echo (int) $tr->ID; ?>" data-vm-bulk-check aria-label="<?php esc_attr_e( 'Select row', 'vatan-event' ); ?>" /></td>
						<td>
							<?php if ( $cover_url ) : ?>
								<img class="vatan-music-admin__cover" src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" />
							<?php else : ?>
								<span class="vatan-music-admin__cover-placeholder" aria-hidden="true">♪</span>
							<?php endif; ?>
						</td>
						<td>
							<a class="vatan-music-admin__row-title" href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( get_the_title( $tr ) ?: __( '(no title)', 'vatan-event' ) ); ?>
							</a>
							<?php if ( $is_live ) : ?>
								<span class="vatan-admin__badge" style="background:#e11d48;color:#fff;margin-inline-start:6px;font-size:10px;padding:2px 5px;border-radius:3px;font-weight:700;">LIVE</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $artist_id ) : ?>
								<a href="<?php echo esc_url( $build_url( array( 'q' => get_the_title( $artist_id ) ) ) ); ?>" style="color:inherit;">
									<?php echo esc_html( get_the_title( $artist_id ) ); ?>
								</a>
							<?php else : ?>
								<span style="opacity:.5;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $album_id ) : ?>
								<a href="<?php echo esc_url( vatan_music_admin_edit_url( $album_id ) ); ?>" style="color:inherit;">
									<?php echo esc_html( get_the_title( $album_id ) ); ?>
								</a>
							<?php else : ?>
								<span style="opacity:.5;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="vatan-admin__badge vatan-admin__badge--<?php echo esc_attr( $status_lbl ); ?>">
								<?php echo esc_html( $status_lbl ); ?>
							</span>
						</td>
						<td style="text-align:end;">
							<span class="vatan-music-admin__row-actions">
								<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( $edit_url ); ?>">
									<?php esc_html_e( 'Edit', 'vatan-event' ); ?>
								</a>
								<?php if ( 'trash' === $status_lbl ) : ?>
									<?php vatan_music_admin_action_form(
										'untrash',
										(int) $tr->ID,
										esc_html__( 'Restore', 'vatan-event' ),
										array( 'class' => 'vatan-music-admin__btn-mini' )
									); ?>
								<?php else : ?>
									<?php vatan_music_admin_action_form(
										'delete',
										(int) $tr->ID,
										esc_html__( 'Delete', 'vatan-event' ),
										array(
											'class'   => 'vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger',
											'onclick' => "return confirm('" . esc_js( __( 'Move this track to trash?', 'vatan-event' ) ) . "');",
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
		<nav class="vatan-music-admin__pagination" aria-label="<?php esc_attr_e( 'Pagination', 'vatan-event' ); ?>">
			<?php for ( $p = 1; $p <= $query->max_num_pages; $p++ ) :
				$is_curr = ( $p === $paged );
				if ( $is_curr ) {
					echo '<span class="is-current">' . esc_html( vatan_to_persian_digits( $p ) ) . '</span>';
				} else {
					echo '<a href="' . esc_url( $build_url( array( 'paged' => $p ) ) ) . '">' . esc_html( vatan_to_persian_digits( $p ) ) . '</a>';
				}
			endfor; ?>
		</nav>
	<?php endif; ?>

<?php endif; ?>
