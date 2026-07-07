<?php
/**
 * Music admin — track create / edit form.
 *
 * Single form used for both `?vatan_action=new` and
 * `?vatan_action=edit&id=N`. Posts to /admin/music/?type=tracks with
 * `vatan_music_save_track=1`; the handler in inc/music/admin.php does
 * the save + redirect.
 *
 * Inherits `$action` from page-admin.php.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$is_edit = ( 'edit' === $action );
$post_id = $is_edit && isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$post = $post_id ? get_post( $post_id ) : null;
if ( $is_edit && ( ! $post || 'track' !== $post->post_type ) ) {
	echo '<div class="vatan-music-admin__empty"><p>' . esc_html__( 'Track not found.', 'vatan-event' ) . '</p>';
	echo '<p><a class="vatan-music-admin__btn-mini" href="' . esc_url( vatan_music_admin_url( 'tracks' ) ) . '">← ' . esc_html__( 'Back to tracks', 'vatan-event' ) . '</a></p></div>';
	return;
}

/* ----- Pre-fill values --------------------------------------------- */

$title         = $post ? $post->post_title : '';
$post_status   = $post ? $post->post_status : 'publish';
$artist_id     = $post && function_exists( 'get_field' ) ? (int) get_field( 'track_artist', $post_id ) : 0;
$album_id      = $post && function_exists( 'get_field' ) ? (int) get_field( 'track_album', $post_id ) : 0;

// Deep-link from the album editor: ?album=N pre-selects on a new track.
if ( ! $is_edit && ! $album_id && isset( $_GET['album'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$album_id = (int) $_GET['album']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
$track_no      = $post && function_exists( 'get_field' ) ? get_field( 'track_track_number', $post_id ) : '';
$duration      = $post && function_exists( 'get_field' ) ? get_field( 'track_duration_seconds', $post_id ) : '';
$is_live       = $post && function_exists( 'get_field' ) ? (bool) get_field( 'track_is_live_stream', $post_id ) : false;
$external_url  = $post && function_exists( 'get_field' ) ? (string) get_field( 'track_external_url', $post_id ) : '';
$lyrics        = $post && function_exists( 'get_field' ) ? (string) get_field( 'track_lyrics', $post_id ) : '';
$explicit      = $post && function_exists( 'get_field' ) ? (bool) get_field( 'track_explicit', $post_id ) : false;
$current_genres = $post_id ? wp_get_object_terms( $post_id, 'music_genre', array( 'fields' => 'ids' ) ) : array();
if ( is_wp_error( $current_genres ) ) {
	$current_genres = array();
}

$audio_file = $post && function_exists( 'get_field' ) ? get_field( 'track_audio_file', $post_id ) : null;
$audio_url  = '';
$audio_name = '';
if ( is_array( $audio_file ) ) {
	$audio_url  = (string) ( $audio_file['url'] ?? '' );
	$audio_name = (string) ( $audio_file['filename'] ?? $audio_file['title'] ?? '' );
} elseif ( is_numeric( $audio_file ) ) {
	$audio_url  = (string) wp_get_attachment_url( (int) $audio_file );
	$audio_name = (string) get_the_title( (int) $audio_file );
}

$cover_id   = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
$cover_url  = $cover_id ? (string) wp_get_attachment_image_url( $cover_id, 'medium' ) : '';

/* ----- Pull dropdown options --------------------------------------- */

$artists = get_posts( array(
	'post_type'      => 'artist',
	'post_status'    => 'publish',
	'posts_per_page' => 500,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );
$albums = get_posts( array(
	'post_type'      => 'album',
	'post_status'    => 'publish',
	'posts_per_page' => 500,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );
$genres = get_terms( array(
	'taxonomy'   => 'music_genre',
	'hide_empty' => false,
	'orderby'    => 'name',
) );
if ( is_wp_error( $genres ) ) {
	$genres = array();
}
?>

<header class="vatan-music-admin__head">
	<div>
		<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'tracks' ) ); ?>" style="margin-bottom:8px;">
			← <?php esc_html_e( 'Back to tracks', 'vatan-event' ); ?>
		</a>
		<h2 style="margin-top:6px;">
			<?php echo $is_edit ? esc_html__( 'Edit track', 'vatan-event' ) : esc_html__( 'New track', 'vatan-event' ); ?>
		</h2>
	</div>
</header>

<form method="post" action="" enctype="multipart/form-data" class="vatan-music-form">
	<input type="hidden" name="vatan_music_save_track" value="1" />
	<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>" />
	<?php wp_nonce_field( 'vatan_music_save_track', '_vatan_music_track_nonce' ); ?>

	<div class="vatan-music-form__grid">

		<!-- LEFT COLUMN: primary content -->
		<div class="vatan-music-form__col">

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-title">
					<?php esc_html_e( 'Title', 'vatan-event' ); ?>
					<span class="vatan-music-form__required">*</span>
				</label>
				<input id="vm-title" type="text" name="title" required
					value="<?php echo esc_attr( $title ); ?>"
					class="vatan-music-form__input vatan-music-form__input--big"
					placeholder="<?php esc_attr_e( 'Track title', 'vatan-event' ); ?>" />
			</section>

			<section class="vatan-music-form__card">
				<div class="vatan-music-form__toggle-row">
					<label class="vatan-music-form__check">
						<input type="checkbox" name="track_is_live_stream" value="1" data-vm-live-toggle
							<?php checked( $is_live ); ?> />
						<span>
							<strong><?php esc_html_e( 'Live radio stream', 'vatan-event' ); ?></strong>
							<small><?php esc_html_e( 'This entry is a continuous stream, not a fixed song.', 'vatan-event' ); ?></small>
						</span>
					</label>
				</div>

				<div data-vm-audio-file <?php echo $is_live ? 'hidden' : ''; ?>>
					<label class="vatan-music-form__label"><?php esc_html_e( 'Audio file', 'vatan-event' ); ?></label>
					<?php if ( $audio_url ) : ?>
						<div class="vatan-music-form__file-current">
							<span>🎵 <?php echo esc_html( $audio_name ); ?></span>
							<a href="<?php echo esc_url( $audio_url ); ?>" target="_blank" rel="noopener" class="vatan-music-admin__btn-mini"><?php esc_html_e( 'Preview', 'vatan-event' ); ?></a>
							<label class="vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger">
								<input type="checkbox" name="remove_audio" value="1" style="margin-inline-end:4px;" />
								<?php esc_html_e( 'Remove on save', 'vatan-event' ); ?>
							</label>
						</div>
					<?php endif; ?>
					<input type="file" name="track_audio_file" accept="audio/mpeg,audio/mp3,audio/mp4,audio/aac,audio/x-m4a,audio/ogg,audio/wav,.mp3,.m4a,.aac,.ogg,.wav" class="vatan-music-form__file" />
					<p class="vatan-music-form__help"><?php esc_html_e( 'MP3, M4A, AAC, OGG, or WAV. Will replace the current file if one exists.', 'vatan-event' ); ?></p>
				</div>

				<div data-vm-stream-url <?php echo $is_live ? '' : 'hidden'; ?>>
					<label class="vatan-music-form__label" for="vm-external"><?php esc_html_e( 'Stream URL', 'vatan-event' ); ?></label>
					<input id="vm-external" type="url" name="track_external_url"
						value="<?php echo esc_attr( $external_url ); ?>"
						class="vatan-music-form__input"
						placeholder="https://stream.example.com/live.mp3" />
				</div>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-lyrics"><?php esc_html_e( 'Lyrics', 'vatan-event' ); ?></label>
				<textarea id="vm-lyrics" name="track_lyrics" rows="8" class="vatan-music-form__textarea"
					placeholder="<?php esc_attr_e( 'Optional. Plain text — line breaks preserved.', 'vatan-event' ); ?>"><?php echo esc_textarea( $lyrics ); ?></textarea>
			</section>

		</div>

		<!-- RIGHT COLUMN: meta + publish controls -->
		<div class="vatan-music-form__col vatan-music-form__col--side">

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Status', 'vatan-event' ); ?></label>
				<div class="vatan-music-form__radio-row">
					<label class="vatan-music-form__check">
						<input type="radio" name="post_status" value="publish" <?php checked( $post_status, 'publish' ); ?> />
						<span><?php esc_html_e( 'Published', 'vatan-event' ); ?></span>
					</label>
					<label class="vatan-music-form__check">
						<input type="radio" name="post_status" value="draft" <?php checked( $post_status, 'draft' ); ?> />
						<span><?php esc_html_e( 'Draft', 'vatan-event' ); ?></span>
					</label>
				</div>

				<div class="vatan-music-form__actions">
					<button type="submit" class="vatan-admin__btn vatan-admin__btn--primary">
						<?php echo $is_edit ? esc_html__( 'Save changes', 'vatan-event' ) : esc_html__( 'Create track', 'vatan-event' ); ?>
					</button>
					<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'tracks' ) ); ?>">
						<?php esc_html_e( 'Cancel', 'vatan-event' ); ?>
					</a>
				</div>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Cover art', 'vatan-event' ); ?></label>
				<?php if ( $cover_url ) : ?>
					<div class="vatan-music-form__cover-preview">
						<img src="<?php echo esc_url( $cover_url ); ?>" alt="" />
						<label class="vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger">
							<input type="checkbox" name="remove_cover" value="1" style="margin-inline-end:4px;" />
							<?php esc_html_e( 'Remove on save', 'vatan-event' ); ?>
						</label>
					</div>
				<?php endif; ?>
				<input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif" class="vatan-music-form__file" />
				<p class="vatan-music-form__help"><?php esc_html_e( 'Optional. Square images look best.', 'vatan-event' ); ?></p>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-artist"><?php esc_html_e( 'Artist', 'vatan-event' ); ?></label>
				<select id="vm-artist" name="track_artist" class="vatan-music-form__input">
					<option value="">— <?php esc_html_e( 'None', 'vatan-event' ); ?> —</option>
					<?php foreach ( $artists as $a ) : ?>
						<option value="<?php echo (int) $a->ID; ?>" <?php selected( $artist_id, $a->ID ); ?>>
							<?php echo esc_html( $a->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $artists ) ) : ?>
					<p class="vatan-music-form__help"><?php esc_html_e( 'No artists yet. Add one from the Artists tab first.', 'vatan-event' ); ?></p>
				<?php endif; ?>

				<label class="vatan-music-form__label" for="vm-album" style="margin-top:14px;"><?php esc_html_e( 'Album', 'vatan-event' ); ?></label>
				<select id="vm-album" name="track_album" class="vatan-music-form__input">
					<option value="">— <?php esc_html_e( 'None (single)', 'vatan-event' ); ?> —</option>
					<?php foreach ( $albums as $a ) : ?>
						<option value="<?php echo (int) $a->ID; ?>" <?php selected( $album_id, $a->ID ); ?>>
							<?php echo esc_html( $a->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<div class="vatan-music-form__row">
					<div>
						<label class="vatan-music-form__label" for="vm-tracknum"><?php esc_html_e( 'Track number', 'vatan-event' ); ?></label>
						<input id="vm-tracknum" type="number" min="1" name="track_track_number"
							value="<?php echo esc_attr( (string) $track_no ); ?>"
							class="vatan-music-form__input" />
					</div>
					<div>
						<label class="vatan-music-form__label" for="vm-duration"><?php esc_html_e( 'Duration (sec)', 'vatan-event' ); ?></label>
						<input id="vm-duration" type="number" min="0" name="track_duration_seconds"
							value="<?php echo esc_attr( (string) $duration ); ?>"
							class="vatan-music-form__input"
							placeholder="<?php esc_attr_e( 'Auto', 'vatan-event' ); ?>" />
					</div>
				</div>

				<label class="vatan-music-form__check" style="margin-top:14px;">
					<input type="checkbox" name="track_explicit" value="1" <?php checked( $explicit ); ?> />
					<span><?php esc_html_e( 'Explicit content', 'vatan-event' ); ?></span>
				</label>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Genres', 'vatan-event' ); ?></label>
				<?php if ( empty( $genres ) ) : ?>
					<p class="vatan-music-form__help"><?php esc_html_e( 'No genres yet. Add some from the Genres tab.', 'vatan-event' ); ?></p>
				<?php else : ?>
					<div class="vatan-music-form__chips">
						<?php foreach ( $genres as $g ) :
							$on    = in_array( (int) $g->term_id, array_map( 'intval', (array) $current_genres ), true );
							$emoji = (string) get_term_meta( $g->term_id, 'vatan_emoji', true );
							?>
							<label class="vatan-music-form__chip <?php echo $on ? 'is-on' : ''; ?>">
								<input type="checkbox" name="music_genre[]" value="<?php echo (int) $g->term_id; ?>" <?php checked( $on ); ?> />
								<?php if ( $emoji ) : ?><span class="vatan-music-form__chip-emoji"><?php echo esc_html( $emoji ); ?></span><?php endif; ?>
								<span><?php echo esc_html( $g->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

		</div>
	</div>
</form>

<?php // JS for the live-stream toggle + genre chip class sync lives in assets/js/admin-music.js (inline scripts in admin templates get serialized to text by some output filter). ?>
