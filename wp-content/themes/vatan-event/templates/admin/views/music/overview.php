<?php
/**
 * Admin dashboard — music management view.
 *
 * At-a-glance stats for the music catalog (tracks / albums / artists)
 * plus quick-action buttons that deep-link into wp-admin for editing,
 * a visibility-status tile pointing at Theme Settings → Music, and two
 * lists: recent tracks and featured albums.
 *
 * Routed via `?view=music` (or pretty URL `/admin/music/`) — see
 * VATAN_ADMIN_VIEWS in inc/admin-dashboard.php.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ----- Stats ---------------------------------------------------------- */

$track_count  = (int) wp_count_posts( 'track' )->publish;
$album_count  = (int) wp_count_posts( 'album' )->publish;
$artist_count = (int) wp_count_posts( 'artist' )->publish;

// Featured counts — tracks aren't marked featured (we use "recent" as a
// proxy), so we surface featured albums + artists for visibility.
$featured_albums_count = (int) ( new WP_Query( array(
	'post_type'      => 'album',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'no_found_rows'  => false,
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'album_is_featured', 'value' => '1', 'compare' => '=' ),
	),
) ) )->found_posts;

$featured_artists_count = (int) ( new WP_Query( array(
	'post_type'      => 'artist',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'no_found_rows'  => false,
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'artist_is_featured', 'value' => '1', 'compare' => '=' ),
	),
) ) )->found_posts;

// Visibility status string — summarises the two Theme Settings toggles.
$web_on = (bool) vatan_get_setting( 'music_player_web_enabled' );
$app_on = (bool) vatan_get_setting( 'music_player_app_enabled' );
if ( $web_on && $app_on ) {
	$visibility_label = __( 'Web + App', 'vatan-event' );
	$visibility_sub   = __( 'Visible everywhere', 'vatan-event' );
} elseif ( $app_on ) {
	$visibility_label = __( 'App only', 'vatan-event' );
	$visibility_sub   = __( 'Hidden on website', 'vatan-event' );
} elseif ( $web_on ) {
	$visibility_label = __( 'Web only', 'vatan-event' );
	$visibility_sub   = __( 'Hidden in app', 'vatan-event' );
} else {
	$visibility_label = __( 'Hidden', 'vatan-event' );
	$visibility_sub   = __( 'Player disabled everywhere', 'vatan-event' );
}

/* ----- Recent + featured content ------------------------------------- */

$recent_tracks = get_posts( array(
	'post_type'      => 'track',
	'post_status'    => array( 'publish', 'draft' ),
	'posts_per_page' => 8,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

$featured_albums = get_posts( array(
	'post_type'      => 'album',
	'post_status'    => 'publish',
	'posts_per_page' => 6,
	'orderby'        => 'date',
	'order'          => 'DESC',
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'album_is_featured', 'value' => '1', 'compare' => '=' ),
	),
) );

$url_add_track     = vatan_music_admin_new_track_url();
$url_add_album     = vatan_music_admin_new_album_url();
$url_add_artist    = vatan_music_admin_new_artist_url();
$url_list_tracks   = vatan_music_admin_url( 'tracks' );
$url_list_albums   = vatan_music_admin_url( 'albums' );
$url_list_artists  = vatan_music_admin_url( 'artists' );
$url_list_genres   = vatan_music_admin_url( 'genres' );
$url_settings      = admin_url( 'admin.php?page=vatan-theme-settings&tab=music' );

// `vatan_admin_music_cover()` lives in inc/music/admin.php so every
// music sub-view (overview, tracks list, etc.) shares it.
?>

<div class="vatan-admin__dashboard">

	<section class="vatan-admin__stats">
		<a class="vatan-admin__stat" href="<?php echo esc_url( $url_list_tracks ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Tracks', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_to_persian_digits( $track_count ) ); ?></span>
			<span class="vatan-admin__stat-sub"><?php esc_html_e( 'In catalog', 'vatan-event' ); ?></span>
		</a>

		<a class="vatan-admin__stat" href="<?php echo esc_url( $url_list_albums ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Albums', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_to_persian_digits( $album_count ) ); ?></span>
			<span class="vatan-admin__stat-sub">
				<?php
				/* translators: %s: count of featured albums */
				echo esc_html( sprintf( __( '%s featured', 'vatan-event' ), vatan_to_persian_digits( $featured_albums_count ) ) );
				?>
			</span>
		</a>

		<a class="vatan-admin__stat" href="<?php echo esc_url( $url_list_artists ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Artists', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value"><?php echo esc_html( vatan_to_persian_digits( $artist_count ) ); ?></span>
			<span class="vatan-admin__stat-sub">
				<?php
				/* translators: %s: count of featured artists */
				echo esc_html( sprintf( __( '%s featured', 'vatan-event' ), vatan_to_persian_digits( $featured_artists_count ) ) );
				?>
			</span>
		</a>

		<a class="vatan-admin__stat" href="<?php echo esc_url( $url_settings ); ?>">
			<span class="vatan-admin__stat-label"><?php esc_html_e( 'Player visibility', 'vatan-event' ); ?></span>
			<span class="vatan-admin__stat-value" style="font-size:24px;line-height:1.2;"><?php echo esc_html( $visibility_label ); ?></span>
			<span class="vatan-admin__stat-sub"><?php echo esc_html( $visibility_sub ); ?></span>
		</a>
	</section>

	<section class="vatan-admin__quick-actions">
		<a class="vatan-admin__btn vatan-admin__btn--primary" href="<?php echo esc_url( $url_add_track ); ?>">
			+ <?php esc_html_e( 'Add track', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( $url_add_album ); ?>">
			💿 <?php esc_html_e( 'Add album', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( $url_add_artist ); ?>">
			🎤 <?php esc_html_e( 'Add artist', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( $url_list_genres ); ?>">
			🎨 <?php esc_html_e( 'Manage genres', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( vatan_music_admin_url( 'batch' ) ); ?>">
			📦 <?php esc_html_e( 'Batch upload', 'vatan-event' ); ?>
		</a>
		<a class="vatan-admin__btn" href="<?php echo esc_url( $url_settings ); ?>">
			⚙️ <?php esc_html_e( 'Player settings', 'vatan-event' ); ?>
		</a>
	</section>

	<div class="vatan-admin__grid">

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Recent tracks', 'vatan-event' ); ?></h2>
				<a class="vatan-admin__link" href="<?php echo esc_url( $url_list_tracks ); ?>"><?php esc_html_e( 'View all', 'vatan-event' ); ?> →</a>
			</header>

			<?php if ( empty( $recent_tracks ) ) : ?>
				<p class="vatan-admin__empty">
					<?php esc_html_e( 'No tracks yet. Add your first one to get started.', 'vatan-event' ); ?>
					<br />
					<a class="vatan-admin__link" href="<?php echo esc_url( $url_add_track ); ?>"><?php esc_html_e( 'Add a track', 'vatan-event' ); ?> →</a>
				</p>
			<?php else : ?>
				<ul class="vatan-admin__list">
					<?php foreach ( $recent_tracks as $tr ) :
						$artist_id   = function_exists( 'get_field' ) ? (int) get_field( 'track_artist', $tr->ID ) : 0;
						$artist_name = $artist_id ? get_the_title( $artist_id ) : '';
						$is_live     = function_exists( 'get_field' ) ? (bool) get_field( 'track_is_live_stream', $tr->ID ) : false;
						$status      = get_post_status( $tr );
						$edit_url    = vatan_music_admin_edit_url( (int) $tr->ID );
						$cover_url   = vatan_admin_music_cover( (int) $tr->ID );
						?>
						<li class="vatan-admin__list-item">
							<?php if ( $cover_url ) : ?>
								<img src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" style="width:36px;height:36px;border-radius:6px;object-fit:cover;flex:0 0 auto;margin-inline-end:10px;" />
							<?php endif; ?>
							<a class="vatan-admin__list-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $tr ) ); ?></a>
							<span class="vatan-admin__list-meta">
								<?php if ( 'publish' !== $status ) : ?>
									<span class="vatan-admin__badge vatan-admin__badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span>
								<?php endif; ?>
								<?php if ( $is_live ) : ?>
									<span class="vatan-admin__badge" style="background:#e11d48;color:#fff;">LIVE</span>
								<?php endif; ?>
								<?php if ( $artist_name ) : ?>
									<span><?php echo esc_html( $artist_name ); ?></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="vatan-admin__panel">
			<header class="vatan-admin__panel-head">
				<h2><?php esc_html_e( 'Featured albums', 'vatan-event' ); ?></h2>
				<a class="vatan-admin__link" href="<?php echo esc_url( $url_list_albums . '&featured=1' ); ?>"><?php esc_html_e( 'View all', 'vatan-event' ); ?> →</a>
			</header>

			<?php if ( empty( $featured_albums ) ) : ?>
				<p class="vatan-admin__empty">
					<?php esc_html_e( 'No featured albums yet. Tick the "Featured" toggle on an album to surface it on the player landing page.', 'vatan-event' ); ?>
				</p>
			<?php else : ?>
				<ul class="vatan-admin__list">
					<?php foreach ( $featured_albums as $alb ) :
						$artist_id   = function_exists( 'get_field' ) ? (int) get_field( 'album_artist', $alb->ID ) : 0;
						$artist_name = $artist_id ? get_the_title( $artist_id ) : '';
						$edit_url    = vatan_music_admin_edit_url( (int) $alb->ID );
						$cover_url   = has_post_thumbnail( $alb->ID ) ? get_the_post_thumbnail_url( $alb->ID, 'thumbnail' ) : '';
						?>
						<li class="vatan-admin__list-item">
							<?php if ( $cover_url ) : ?>
								<img src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" style="width:36px;height:36px;border-radius:6px;object-fit:cover;flex:0 0 auto;margin-inline-end:10px;" />
							<?php endif; ?>
							<a class="vatan-admin__list-title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $alb ) ); ?></a>
							<?php if ( $artist_name ) : ?>
								<span class="vatan-admin__list-meta">
									<span><?php echo esc_html( $artist_name ); ?></span>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

	</div>
</div>
