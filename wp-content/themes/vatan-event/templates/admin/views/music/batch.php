<?php
/**
 * Music admin — batch upload view.
 *
 * Allows uploading multiple audio files at once, with optional metadata
 * (artist, album, genres) applied to all tracks in the batch.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

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

/* ----- Session results from last upload ---------------------------- */
$batch_results = isset( $_SESSION['vatan_batch_results'] ) ? $_SESSION['vatan_batch_results'] : null; // phpcs:ignore WordPress.Security.NonceVerification
unset( $_SESSION['vatan_batch_results'] ); // phpcs:ignore WordPress.Security.NonceVerification
?>

<header class="vatan-music-admin__head">
	<h2><?php esc_html_e( 'Batch Upload', 'vatan-event' ); ?></h2>
	<p class="vatan-music-admin__head-desc">
		<?php esc_html_e( 'Upload multiple audio files at once. Metadata is auto-detected from ID3 tags.', 'vatan-event' ); ?>
	</p>
</header>

<?php if ( $batch_results ) : ?>
	<div class="vatan-music-admin__batch-results">
		<h3><?php esc_html_e( 'Upload Results', 'vatan-event' ); ?></h3>
		<div class="vatan-music-admin__batch-stats">
			<span class="vatan-admin__badge vatan-admin__badge--success">
				<?php
				/* translators: %s: number of successful uploads */
				echo esc_html( sprintf( __( '%s created', 'vatan-event' ), vatan_to_persian_digits( $batch_results['created'] ) ) );
				?>
			</span>
			<?php if ( $batch_results['failed'] > 0 ) : ?>
				<span class="vatan-admin__badge vatan-admin__badge--error">
					<?php
					/* translators: %s: number of failed uploads */
					echo esc_html( sprintf( __( '%s failed', 'vatan-event' ), vatan_to_persian_digits( $batch_results['failed'] ) ) );
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $batch_results['errors'] ) ) : ?>
			<ul class="vatan-music-admin__batch-errors">
				<?php foreach ( $batch_results['errors'] as $err ) : ?>
					<li><?php echo esc_html( $err ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( ! empty( $batch_results['tracks'] ) ) : ?>
			<div class="vatan-music-admin__batch-tracks">
				<?php foreach ( $batch_results['tracks'] as $track ) : ?>
					<div class="vatan-music-admin__batch-track">
						<span class="vatan-music-admin__batch-track-title"><?php echo esc_html( $track['title'] ); ?></span>
						<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_edit_url( $track['id'] ) ); ?>">
							<?php esc_html_e( 'Edit', 'vatan-event' ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>

<form method="post" action="" enctype="multipart/form-data" class="vatan-music-form vatan-music-form--batch" id="vatan-batch-form">
	<input type="hidden" name="vatan_music_batch_upload" value="1" />
	<input type="hidden" name="batch_use_detected" value="1" id="vatan-use-detected" />
	<?php wp_nonce_field( 'vatan_music_batch_upload', '_vatan_music_batch_nonce' ); ?>

	<div class="vatan-music-form__grid">

		<!-- LEFT COLUMN: file upload + detected metadata -->
		<div class="vatan-music-form__col">

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label">
					<?php esc_html_e( 'Audio files', 'vatan-event' ); ?>
					<span class="vatan-music-form__required">*</span>
				</label>
				<div class="vatan-music-form__dropzone" id="vatan-dropzone" data-vm-dropzone>
					<div class="vatan-music-form__dropzone-inner">
						<span class="vatan-music-form__dropzone-icon">🎵</span>
						<p class="vatan-music-form__dropzone-text">
							<?php esc_html_e( 'Drag & drop audio files here, or click to browse', 'vatan-event' ); ?>
						</p>
						<p class="vatan-music-form__dropzone-help">
							<?php esc_html_e( 'MP3, M4A, AAC, OGG, or WAV. Max 50MB per file.', 'vatan-event' ); ?>
						</p>
					</div>
					<input type="file" name="batch_audio_files[]" multiple
						accept="audio/mpeg,audio/mp3,audio/mp4,audio/aac,audio/x-m4a,audio/ogg,audio/wav,.mp3,.m4a,.aac,.ogg,.wav"
						class="vatan-music-form__dropzone-input"
						id="vatan-batch-files" />
				</div>

				<div class="vatan-music-form__file-list" id="vatan-file-list" data-vm-file-list></div>
			</section>

			<!-- Auto-detected metadata from ID3 tags -->
			<section class="vatan-music-form__card" id="vatan-detected-meta" hidden>
				<div class="vatan-music-form__detected-header">
					<label class="vatan-music-form__label">
						<?php esc_html_e( 'Detected from ID3 tags', 'vatan-event' ); ?>
						<span class="vatan-music-form__detected-badge">AUTO</span>
					</label>
					<label class="vatan-music-form__check">
						<input type="checkbox" name="batch_use_detected" value="1" id="vatan-use-detected-cb" checked />
						<span><?php esc_html_e( 'Use detected values', 'vatan-event' ); ?></span>
					</label>
				</div>

				<div class="vatan-music-form__detected-grid" id="vatan-detected-grid">
					<div class="vatan-music-form__detected-field">
						<label><?php esc_html_e( 'Title', 'vatan-event' ); ?></label>
						<input type="text" name="detected_title" id="vatan-detected-title" class="vatan-music-form__input" readonly />
					</div>
					<div class="vatan-music-form__detected-field">
						<label><?php esc_html_e( 'Artist', 'vatan-event' ); ?></label>
						<input type="text" name="detected_artist" id="vatan-detected-artist" class="vatan-music-form__input" readonly />
					</div>
					<div class="vatan-music-form__detected-field">
						<label><?php esc_html_e( 'Album', 'vatan-event' ); ?></label>
						<input type="text" name="detected_album" id="vatan-detected-album" class="vatan-music-form__input" readonly />
					</div>
					<div class="vatan-music-form__detected-row">
						<div class="vatan-music-form__detected-field">
							<label><?php esc_html_e( 'Duration', 'vatan-event' ); ?></label>
							<input type="text" id="vatan-detected-duration" class="vatan-music-form__input" readonly />
						</div>
						<div class="vatan-music-form__detected-field">
							<label><?php esc_html_e( 'Track #', 'vatan-event' ); ?></label>
							<input type="number" name="detected_track_number" id="vatan-detected-track" class="vatan-music-form__input" min="1" />
						</div>
						<div class="vatan-music-form__detected-field">
							<label><?php esc_html_e( 'Year', 'vatan-event' ); ?></label>
							<input type="text" name="detected_year" id="vatan-detected-year" class="vatan-music-form__input" maxlength="4" />
						</div>
					</div>
				</div>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Apply to all tracks', 'vatan-event' ); ?></label>
				<div class="vatan-music-form__row">
					<div>
						<label class="vatan-music-form__label" for="vm-batch-artist"><?php esc_html_e( 'Artist', 'vatan-event' ); ?></label>
						<select id="vm-batch-artist" name="batch_artist" class="vatan-music-form__input">
							<option value="">— <?php esc_html_e( 'None', 'vatan-event' ); ?> —</option>
							<?php foreach ( $artists as $a ) : ?>
								<option value="<?php echo (int) $a->ID; ?>">
									<?php echo esc_html( $a->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<label class="vatan-music-form__check" style="margin-top:8px;">
							<input type="checkbox" name="batch_create_artist" value="1" checked />
							<span><?php esc_html_e( 'Auto-create artist if not found', 'vatan-event' ); ?></span>
						</label>
					</div>
					<div>
						<label class="vatan-music-form__label" for="vm-batch-album"><?php esc_html_e( 'Album', 'vatan-event' ); ?></label>
						<select id="vm-batch-album" name="batch_album" class="vatan-music-form__input">
							<option value="">— <?php esc_html_e( 'None', 'vatan-event' ); ?> —</option>
							<?php foreach ( $albums as $a ) : ?>
								<option value="<?php echo (int) $a->ID; ?>">
									<?php echo esc_html( $a->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<label class="vatan-music-form__check" style="margin-top:8px;">
							<input type="checkbox" name="batch_create_album" value="1" checked />
							<span><?php esc_html_e( 'Auto-create album if not found', 'vatan-event' ); ?></span>
						</label>
					</div>
				</div>

				<label class="vatan-music-form__check" style="margin-top:12px;">
					<input type="checkbox" name="batch_auto_title" value="1" checked />
					<span><?php esc_html_e( 'Auto-detect title from filename (fallback)', 'vatan-event' ); ?></span>
				</label>

				<label class="vatan-music-form__check" style="margin-top:8px;">
					<input type="checkbox" name="batch_auto_number" value="1" checked />
					<span><?php esc_html_e( 'Auto-number tracks sequentially', 'vatan-event' ); ?></span>
				</label>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Genres (applied to all)', 'vatan-event' ); ?></label>
				<?php if ( empty( $genres ) ) : ?>
					<p class="vatan-music-form__help"><?php esc_html_e( 'No genres yet. Add some from the Genres tab.', 'vatan-event' ); ?></p>
				<?php else : ?>
					<div class="vatan-music-form__chips">
						<?php foreach ( $genres as $g ) :
							$emoji = (string) get_term_meta( $g->term_id, 'vatan_emoji', true );
							?>
							<label class="vatan-music-form__chip">
								<input type="checkbox" name="batch_genres[]" value="<?php echo (int) $g->term_id; ?>" />
								<?php if ( $emoji ) : ?><span class="vatan-music-form__chip-emoji"><?php echo esc_html( $emoji ); ?></span><?php endif; ?>
								<span><?php echo esc_html( $g->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

		</div>

		<!-- RIGHT COLUMN: options + cover preview -->
		<div class="vatan-music-form__col vatan-music-form__col--side">

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Status', 'vatan-event' ); ?></label>
				<div class="vatan-music-form__radio-row">
					<label class="vatan-music-form__check">
						<input type="radio" name="batch_status" value="publish" checked />
						<span><?php esc_html_e( 'Published', 'vatan-event' ); ?></span>
					</label>
					<label class="vatan-music-form__check">
						<input type="radio" name="batch_status" value="draft" />
						<span><?php esc_html_e( 'Draft', 'vatan-event' ); ?></span>
					</label>
				</div>

				<div class="vatan-music-form__actions">
					<button type="submit" class="vatan-admin__btn vatan-admin__btn--primary" id="vatan-batch-submit">
						<?php esc_html_e( 'Upload tracks', 'vatan-event' ); ?>
					</button>
				</div>

				<div class="vatan-music-form__progress" id="vatan-upload-progress" hidden>
					<div class="vatan-music-form__progress-bar">
						<div class="vatan-music-form__progress-fill" id="vatan-progress-fill"></div>
					</div>
					<p class="vatan-music-form__progress-text" id="vatan-progress-text">
						<?php esc_html_e( 'Uploading…', 'vatan-event' ); ?>
					</p>
				</div>
			</section>

			<!-- Cover art from ID3 tags -->
			<section class="vatan-music-form__card" id="vatan-detected-cover" hidden>
				<label class="vatan-music-form__label"><?php esc_html_e( 'Album art (detected)', 'vatan-event' ); ?></label>
				<div class="vatan-music-form__cover-preview">
					<img id="vatan-cover-preview" src="" alt="" />
					<label class="vatan-music-form__check">
						<input type="checkbox" name="batch_use_cover" value="1" id="vatan-use-cover" checked />
						<span><?php esc_html_e( 'Use as cover art for all tracks', 'vatan-event' ); ?></span>
					</label>
				</div>
			</section>

			<section class="vatan-music-form__card">
				<h4 class="vatan-music-form__label"><?php esc_html_e( 'How it works', 'vatan-event' ); ?></h4>
				<ul class="vatan-music-form__help-list">
					<li><?php esc_html_e( 'Select multiple audio files at once', 'vatan-event' ); ?></li>
					<li><?php esc_html_e( 'ID3 tags are auto-detected (title, artist, album)', 'vatan-event' ); ?></li>
					<li><?php esc_html_e( 'New artists/albums are created automatically', 'vatan-event' ); ?></li>
					<li><?php esc_html_e( 'Cover art from ID3 tags is used when available', 'vatan-event' ); ?></li>
					<li><?php esc_html_e( 'Duration is auto-detected from metadata', 'vatan-event' ); ?></li>
					<li><?php esc_html_e( 'You can edit each track after upload', 'vatan-event' ); ?></li>
				</ul>
			</section>

		</div>

	</div>
</form>
