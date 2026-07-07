<?php
/**
 * Music admin — album create / edit form.
 *
 * Single template covering both ?vatan_action=new and ?vatan_action=edit&id=N.
 * Submits to inc/music/admin.php::vatan_music_admin_handle_album_save.
 *
 * Edit mode also includes a track manager: lists every track linked to
 * this album, lets the editor change track positions or detach tracks,
 * and offers a "+ Add new track to album" deep-link.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$is_edit = ( 'edit' === $action );
$post_id = $is_edit && isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$post = $post_id ? get_post( $post_id ) : null;
if ( $is_edit && ( ! $post || 'album' !== $post->post_type ) ) {
	echo '<div class="vatan-music-admin__empty"><p>' . esc_html__( 'Album not found.', 'vatan-event' ) . '</p>';
	echo '<p><a class="vatan-music-admin__btn-mini" href="' . esc_url( vatan_music_admin_url( 'albums' ) ) . '">← ' . esc_html__( 'Back to albums', 'vatan-event' ) . '</a></p></div>';
	return;
}

/* ----- Pre-fill --------------------------------------------------- */

$title         = $post ? $post->post_title : '';
$content       = $post ? $post->post_content : '';
$post_status   = $post ? $post->post_status : 'publish';
$artist_id     = $post && function_exists( 'get_field' ) ? (int) get_field( 'album_artist', $post_id ) : 0;
$type          = $post && function_exists( 'get_field' ) ? (string) get_field( 'album_type', $post_id ) : 'album';
$release_date  = $post && function_exists( 'get_field' ) ? (string) get_field( 'album_release_date', $post_id ) : '';
$featured      = $post && function_exists( 'get_field' ) ? (bool) get_field( 'album_is_featured', $post_id ) : false;

$cover_id  = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
$cover_url = $cover_id ? (string) wp_get_attachment_image_url( $cover_id, 'medium' ) : '';

$artists = get_posts( array(
	'post_type'      => 'artist',
	'post_status'    => 'publish',
	'posts_per_page' => 500,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

$type_options = array(
	'album'       => __( 'Album', 'vatan-event' ),
	'ep'          => __( 'EP', 'vatan-event' ),
	'single'      => __( 'Single', 'vatan-event' ),
	'playlist'    => __( 'Playlist (curated)', 'vatan-event' ),
	'compilation' => __( 'Compilation', 'vatan-event' ),
);

// Tracks currently linked to this album, ordered by track number.
$album_tracks = $post_id ? get_posts( array(
	'post_type'      => 'track',
	'post_status'    => array( 'publish', 'draft' ),
	'posts_per_page' => 200,
	'meta_key'       => 'track_track_number', // phpcs:ignore WordPress.DB.SlowDBQuery
	'orderby'        => 'meta_value_num title',
	'order'          => 'ASC',
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'track_album', 'value' => $post_id, 'compare' => '=' ),
	),
) ) : array();
?>

<header class="vatan-music-admin__head">
	<div>
		<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'albums' ) ); ?>" style="margin-bottom:8px;">
			← <?php esc_html_e( 'Back to albums', 'vatan-event' ); ?>
		</a>
		<h2 style="margin-top:6px;">
			<?php echo $is_edit ? esc_html__( 'Edit album', 'vatan-event' ) : esc_html__( 'New album', 'vatan-event' ); ?>
		</h2>
	</div>
</header>

<form method="post" action="" enctype="multipart/form-data" class="vatan-music-form">
	<input type="hidden" name="vatan_music_save_album" value="1" />
	<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>" />
	<?php wp_nonce_field( 'vatan_music_save_album', '_vatan_music_album_nonce' ); ?>

	<div class="vatan-music-form__grid">

		<div class="vatan-music-form__col">

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-title">
					<?php esc_html_e( 'Title', 'vatan-event' ); ?>
					<span class="vatan-music-form__required">*</span>
				</label>
				<input id="vm-title" type="text" name="title" required
					value="<?php echo esc_attr( $title ); ?>"
					class="vatan-music-form__input vatan-music-form__input--big"
					placeholder="<?php esc_attr_e( 'Album title', 'vatan-event' ); ?>" />
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-content"><?php esc_html_e( 'Description', 'vatan-event' ); ?></label>
				<textarea id="vm-content" name="post_content" rows="6" class="vatan-music-form__textarea"
					placeholder="<?php esc_attr_e( 'Optional liner notes, credits, etc.', 'vatan-event' ); ?>"><?php echo esc_textarea( $content ); ?></textarea>
			</section>

			<?php if ( $is_edit ) : ?>
			<section class="vatan-music-form__card">
				<header style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
					<h3 style="margin:0;font-size:14px;text-transform:uppercase;letter-spacing:.6px;color:var(--vma-text-mute);">
						<?php esc_html_e( 'Tracks in this album', 'vatan-event' ); ?>
					</h3>
					<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'tracks', array( 'vatan_action' => 'new', 'album' => $post_id ) ) ); ?>">
						+ <?php esc_html_e( 'Add new track', 'vatan-event' ); ?>
					</a>
				</header>

				<?php if ( empty( $album_tracks ) ) : ?>
					<p class="vatan-music-form__help"><?php esc_html_e( 'No tracks linked yet. Use the button above to create one — it will be attached to this album automatically.', 'vatan-event' ); ?></p>
				<?php else : ?>
					<p class="vatan-music-form__help" style="margin-bottom:10px;">
						<?php esc_html_e( 'Drag rows to reorder. Track numbers update automatically.', 'vatan-event' ); ?>
					</p>
					<ul class="vatan-music-form__track-list" data-vm-track-list>
						<?php foreach ( $album_tracks as $i => $t ) :
							$track_no = function_exists( 'get_field' ) ? get_field( 'track_track_number', $t->ID ) : '';
							if ( '' === $track_no ) $track_no = $i + 1;
							$track_cover = function_exists( 'vatan_admin_music_cover' ) ? vatan_admin_music_cover( (int) $t->ID, 'thumbnail' ) : '';
							?>
							<li class="vatan-music-form__track-row" draggable="true" data-vm-track-row>
								<span class="vatan-music-form__track-handle" aria-hidden="true" data-vm-track-handle>⠿</span>
								<?php if ( $track_cover ) : ?>
									<img src="<?php echo esc_url( $track_cover ); ?>" alt="" class="vatan-music-form__track-cover" />
								<?php else : ?>
									<span class="vatan-music-admin__cover-placeholder" aria-hidden="true">♪</span>
								<?php endif; ?>
								<input type="number" min="1" name="album_tracks[<?php echo (int) $t->ID; ?>][position]"
									value="<?php echo esc_attr( (string) $track_no ); ?>"
									class="vatan-music-form__input vatan-music-form__track-pos"
									title="<?php esc_attr_e( 'Track number', 'vatan-event' ); ?>"
									data-vm-track-pos />
								<a class="vatan-music-form__track-title" href="<?php echo esc_url( vatan_music_admin_edit_url( (int) $t->ID ) ); ?>">
									<?php echo esc_html( get_the_title( $t ) ?: __( '(no title)', 'vatan-event' ) ); ?>
								</a>
								<label class="vatan-music-form__track-remove">
									<input type="checkbox" name="album_tracks[<?php echo (int) $t->ID; ?>][remove]" value="1" />
									<span><?php esc_html_e( 'Remove', 'vatan-event' ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>

					<script>
					( function () {
						var list = document.querySelector('[data-vm-track-list]');
						if ( ! list ) return;
						var dragging = null;

						function renumber() {
							var rows = list.querySelectorAll('[data-vm-track-row]');
							rows.forEach(function ( row, idx ) {
								var input = row.querySelector('[data-vm-track-pos]');
								if ( input ) input.value = idx + 1;
							});
						}

						list.addEventListener('dragstart', function ( e ) {
							var row = e.target.closest('[data-vm-track-row]');
							if ( ! row ) return;
							dragging = row;
							row.classList.add('is-dragging');
							try { e.dataTransfer.effectAllowed = 'move'; } catch ( err ) {}
						});

						list.addEventListener('dragend', function () {
							if ( dragging ) dragging.classList.remove('is-dragging');
							dragging = null;
							renumber();
						});

						list.addEventListener('dragover', function ( e ) {
							e.preventDefault();
							if ( ! dragging ) return;
							var target = e.target.closest('[data-vm-track-row]');
							if ( ! target || target === dragging ) return;
							var rect = target.getBoundingClientRect();
							var after = ( e.clientY - rect.top ) > ( rect.height / 2 );
							list.insertBefore( dragging, after ? target.nextSibling : target );
						});
					} )();
					</script>
				<?php endif; ?>
			</section>
			<?php endif; ?>

		</div>

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

				<label class="vatan-music-form__check" style="margin-top:14px;">
					<input type="checkbox" name="album_is_featured" value="1" <?php checked( $featured ); ?> />
					<span>
						<strong>★ <?php esc_html_e( 'Featured', 'vatan-event' ); ?></strong>
						<small><?php esc_html_e( 'Surface this album on the player landing page.', 'vatan-event' ); ?></small>
					</span>
				</label>

				<div class="vatan-music-form__actions">
					<button type="submit" class="vatan-admin__btn vatan-admin__btn--primary">
						<?php echo $is_edit ? esc_html__( 'Save changes', 'vatan-event' ) : esc_html__( 'Create album', 'vatan-event' ); ?>
					</button>
					<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'albums' ) ); ?>">
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
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-artist"><?php esc_html_e( 'Artist', 'vatan-event' ); ?></label>
				<select id="vm-artist" name="album_artist" class="vatan-music-form__input">
					<option value="">— <?php esc_html_e( 'None', 'vatan-event' ); ?> —</option>
					<?php foreach ( $artists as $a ) : ?>
						<option value="<?php echo (int) $a->ID; ?>" <?php selected( $artist_id, $a->ID ); ?>>
							<?php echo esc_html( $a->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label class="vatan-music-form__label" for="vm-type" style="margin-top:14px;"><?php esc_html_e( 'Type', 'vatan-event' ); ?></label>
				<select id="vm-type" name="album_type" class="vatan-music-form__input">
					<?php foreach ( $type_options as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="vatan-music-form__label" for="vm-release" style="margin-top:14px;"><?php esc_html_e( 'Release date', 'vatan-event' ); ?></label>
				<input id="vm-release" type="date" name="album_release_date"
					value="<?php echo esc_attr( $release_date ); ?>"
					class="vatan-music-form__input" />
			</section>

		</div>
	</div>
</form>
