<?php
/**
 * Music player — render gate, mini-bar bootstrap, asset enqueue.
 *
 * Visibility model (settings live on Theme Settings → Music tab):
 *   - `music_player_app_enabled` — show player in Capacitor app (default ON)
 *   - `music_player_web_enabled` — show player on public web   (default OFF)
 *
 * Detection: requests from the Capacitor shell append `VatanTicketApp` to
 * their User-Agent; the gate checks for that token. Admins can preview the
 * app-context view from a desktop browser by appending `?vatan_app_preview=1`
 * to any front-end URL (logged-in admin only).
 *
 * The actual mini-bar markup is a tiny skeleton — the engine in
 * /assets/js/music-player.js hydrates it on DOMContentLoaded, fetches the
 * catalog from /wp-json/vatan/v1/music/feed, and takes over from there.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_APP_UA_TOKEN = 'VatanTicketApp';

/**
 * True when the current request looks like it came from the native app shell.
 *
 * Admins can force-true this with `?vatan_app_preview=1` to preview the
 * app-context render from a desktop browser without spoofing UA.
 */
function vatan_is_app_request(): bool {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( apply_filters( 'vatan_is_app_request_override', false ) ) {
		return true;
	}
	$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
	return $ua !== '' && str_contains( $ua, VATAN_APP_UA_TOKEN );
}

add_action( 'init', function () {
	if ( isset( $_GET['vatan_app_preview'] ) && current_user_can( 'manage_options' ) ) {
		add_filter( 'vatan_is_app_request_override', '__return_true' );
	}
} );

/**
 * Whether the music player UI should render for the current request.
 *
 * Web vs app gates are independent and both default-sensible: web off, app on.
 * The same gate is reused by the page-builder block so visibility is uniform
 * across every surface (mini-bar, hero block, future music section pages).
 */
function vatan_music_player_should_render(): bool {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	// Hide the player on the front-end admin dashboard (/admin/) so it
	// doesn't compete with admin actions on a control-surface page. Catch
	// both the page slug and any page using page-admin.php as a template.
	if ( is_page( 'admin' ) || is_page_template( 'page-admin.php' ) ) {
		return false;
	}

	$is_app = vatan_is_app_request();
	$flag   = $is_app ? 'music_player_app_enabled' : 'music_player_web_enabled';
	return (bool) vatan_get_setting( $flag, $is_app );
}

/**
 * Enqueue the player engine + styles. Loaded lazily — only when the gate
 * is open, so web visitors with the player disabled don't pay the bytes.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! vatan_music_player_should_render() ) {
		return;
	}

	wp_enqueue_style(
		'vatan-music-player',
		VATAN_EVENT_URI . '/assets/css/music-player.css',
		array( 'vatan-main' ),
		VATAN_EVENT_VERSION
	);

	wp_enqueue_script(
		'vatan-music-player',
		VATAN_EVENT_URI . '/assets/js/music-player.js',
		array( 'vatan-main' ),
		VATAN_EVENT_VERSION,
		true
	);

	$is_fa = str_starts_with( get_locale(), 'fa' );

	wp_localize_script( 'vatan-music-player', 'vatanMusic', array(
		'restUrl' => esc_url_raw( rest_url( 'vatan/v1/music/' ) ),
		'isApp'   => vatan_is_app_request(),
		'isRtl'   => is_rtl(),
		'lang'    => function_exists( 'vatan_current_lang' ) ? vatan_current_lang() : '',
		'i18n'    => array(
			'play'         => $is_fa ? 'پخش'        : 'Play',
			'pause'        => $is_fa ? 'مکث'         : 'Pause',
			'next'         => $is_fa ? 'بعدی'        : 'Next',
			'previous'     => $is_fa ? 'قبلی'        : 'Previous',
			'open'         => $is_fa ? 'باز کردن'    : 'Open player',
			'close'        => $is_fa ? 'بستن'        : 'Close',
			'live'         => $is_fa ? 'زنده'        : 'LIVE',
			'browse'       => $is_fa ? 'مرور'        : 'Browse',
			'queue'        => $is_fa ? 'صف پخش'      : 'Queue',
			'nowPlaying'   => $is_fa ? 'در حال پخش'  : 'Now playing',
			'search'       => $is_fa ? 'جستجو'       : 'Search',
			'searchPh'     => $is_fa ? 'جستجوی موسیقی…' : 'Search music…',
			'featuredAlbums'  => $is_fa ? 'آلبوم‌های منتخب'  : 'Featured albums',
			'featuredArtists' => $is_fa ? 'هنرمندان منتخب' : 'Featured artists',
			'recentTracks'    => $is_fa ? 'تازه‌ترین آهنگ‌ها' : 'Recent tracks',
			'recentAlbums'    => $is_fa ? 'تازه‌ترین آلبوم‌ها' : 'Recent albums',
			'genres'          => $is_fa ? 'سبک‌ها'      : 'Genres',
			'tracks'          => $is_fa ? 'آهنگ‌ها'    : 'Tracks',
			'albums'          => $is_fa ? 'آلبوم‌ها'   : 'Albums',
			'artists'         => $is_fa ? 'هنرمندان'   : 'Artists',
			'noResults'       => $is_fa ? 'نتیجه‌ای یافت نشد.' : 'No results.',
			'loadingFailed'   => $is_fa ? 'بارگذاری ناموفق.'   : 'Loading failed.',
			'queueEmpty'      => $is_fa ? 'صف پخش خالی است.'  : 'Queue is empty.',
			'tracksFromAlbum' => $is_fa ? 'آهنگ‌های آلبوم'    : 'Album tracks',
		),
	) );
}, 20 );

/**
 * Render the mini-bar skeleton in the footer. The engine fills it in on
 * DOMContentLoaded. We keep the server-side markup minimal so a player
 * disabled by setting carries zero bytes (no hidden node either).
 */
add_action( 'wp_footer', function () {
	if ( ! vatan_music_player_should_render() ) {
		return;
	}
	?>
	<div id="vatan-music-root" class="vatan-music" data-vatan-music-root></div>
	<?php
}, 99 );

/* ============================================================
   Page-builder component: music_hero
   ============================================================ */

/**
 * Register the "Music hero" page-builder component. Renders as an
 * elegant featured-album card with a play button — clicking the play
 * starts the album in the mini-bar, clicking the card opens the music
 * panel into the album view.
 *
 * Visibility: bound to the same setting as the mini-bar — if the player
 * is disabled for the current context (web vs app), the block renders
 * nothing. This way the admin can leave the block in the homepage
 * layout permanently without leaking music UI into web contexts where
 * the siteowner has it disabled.
 */
add_filter( 'vatan_page_builder_components', function ( $components ) {
	$components['music_hero'] = array(
		'label'       => __( 'Music hero', 'vatan-event' ),
		'icon'        => '🎶',
		'description' => __( 'Elegant featured-album card with a play button. Wired to the music player — tapping play starts the album, tapping the card opens the full music panel. Only renders where the music player is enabled (Theme Settings → Music).', 'vatan-event' ),
		'props'       => array(
			'title' => array(
				'type'    => 'text',
				'label'   => __( 'Section title (optional)', 'vatan-event' ),
				'default' => '',
			),
			'eyebrow' => array(
				'type'    => 'text',
				'label'   => __( 'Eyebrow label', 'vatan-event' ),
				'default' => __( 'Featured album', 'vatan-event' ),
			),
			'album_id' => array(
				'type'        => 'post_select',
				'post_type'   => 'album',
				'empty_label' => __( '— Auto (first featured album) —', 'vatan-event' ),
				'label'       => __( 'Album to feature', 'vatan-event' ),
				'default'     => '',
			),
			'cta_label' => array(
				'type'    => 'text',
				'label'   => __( 'Play button label', 'vatan-event' ),
				'default' => __( 'Listen now', 'vatan-event' ),
			),
			'background' => array(
				'type'    => 'select',
				'label'   => __( 'Background style', 'vatan-event' ),
				'options' => array(
					'cover'    => __( 'Album cover (blurred backdrop)', 'vatan-event' ),
					'gradient' => __( 'Gradient (pink → purple)', 'vatan-event' ),
					'surface'  => __( 'Card surface', 'vatan-event' ),
				),
				'default' => 'cover',
			),
		),
		'render' => 'vatan_render_section_music_hero',
	);
	return $components;
} );

/**
 * Resolve the album post to feature: explicit selection wins; fallback
 * to the first published album with `album_is_featured = true`; final
 * fallback to the most recent published album. Returns null if the
 * catalog is empty.
 */
function vatan_music_hero_pick_album( $explicit_id ): ?WP_Post {
	$explicit_id = (int) $explicit_id;
	if ( $explicit_id > 0 ) {
		$post = get_post( $explicit_id );
		if ( $post && 'album' === $post->post_type && 'publish' === $post->post_status ) {
			return $post;
		}
	}
	$featured = get_posts( array(
		'post_type'      => 'album',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'album_is_featured', 'value' => '1', 'compare' => '=' ),
		),
	) );
	if ( $featured ) {
		return $featured[0];
	}
	$recent = get_posts( array(
		'post_type'      => 'album',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	return $recent ? $recent[0] : null;
}

function vatan_render_section_music_hero( $props ) {
	if ( ! vatan_music_player_should_render() ) {
		return;
	}

	$album = vatan_music_hero_pick_album( $props['album_id'] ?? '' );
	if ( ! $album ) {
		return; // nothing in the catalog yet
	}

	$title       = (string) ( $props['title'] ?? '' );
	$eyebrow     = (string) ( $props['eyebrow'] ?? '' );
	$cta_label   = (string) ( $props['cta_label'] ?? '' );
	$background  = (string) ( $props['background'] ?? 'cover' );

	$cover_id  = get_post_thumbnail_id( $album->ID );
	$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'large' ) : '';

	// Resolve the album artist (ACF post_object stored as ID).
	$artist_name = '';
	if ( function_exists( 'get_field' ) ) {
		$artist_id = (int) get_field( 'album_artist', $album->ID );
		if ( $artist_id ) {
			$artist_name = (string) get_the_title( $artist_id );
		}
	}

	$track_count = (int) ( new WP_Query( array(
		'post_type'      => 'track',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => 'track_album', 'value' => $album->ID, 'compare' => '=' ),
		),
	) ) )->found_posts;

	$has_bg_image = ( 'cover' === $background && $cover_url );
	?>
	<section class="vatan-section vatan-section--music-hero">
		<div class="container">
			<?php if ( $title ) : ?>
				<h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<article
				class="vatan-music-hero vatan-music-hero--<?php echo esc_attr( $background ); ?>"
				data-vatan-music-action="open-album"
				data-vatan-music-album-id="<?php echo esc_attr( (string) $album->ID ); ?>"
				<?php if ( $has_bg_image ) : ?>style="background-image: url(<?php echo esc_url( $cover_url ); ?>);"<?php endif; ?>
			>
				<?php if ( $has_bg_image ) : ?>
					<div class="vatan-music-hero__backdrop" aria-hidden="true"></div>
				<?php endif; ?>

				<?php if ( $cover_url ) : ?>
					<img class="vatan-music-hero__cover" src="<?php echo esc_url( $cover_url ); ?>" alt="" loading="lazy" decoding="async" />
				<?php else : ?>
					<div class="vatan-music-hero__cover vatan-music-hero__cover--blank" aria-hidden="true">♫</div>
				<?php endif; ?>

				<div class="vatan-music-hero__body">
					<?php if ( $eyebrow ) : ?>
						<span class="vatan-music-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
					<?php endif; ?>
					<h3 class="vatan-music-hero__title"><?php echo esc_html( $album->post_title ); ?></h3>
					<?php if ( $artist_name ) : ?>
						<p class="vatan-music-hero__artist"><?php echo esc_html( $artist_name ); ?></p>
					<?php endif; ?>
					<?php if ( $track_count > 0 ) : ?>
						<p class="vatan-music-hero__meta">
							<?php
							printf(
								/* translators: %d: track count */
								esc_html( _n( '%d track', '%d tracks', $track_count, 'vatan-event' ) ),
								$track_count
							);
							?>
						</p>
					<?php endif; ?>

					<button
						type="button"
						class="vatan-music-hero__play"
						data-vatan-music-action="play-album"
						data-vatan-music-album-id="<?php echo esc_attr( (string) $album->ID ); ?>"
					>
						<span class="vatan-music-hero__play-icon" aria-hidden="true">▶</span>
						<?php echo esc_html( $cta_label ?: __( 'Listen now', 'vatan-event' ) ); ?>
					</button>
				</div>
			</article>
		</div>
	</section>
	<?php
}
