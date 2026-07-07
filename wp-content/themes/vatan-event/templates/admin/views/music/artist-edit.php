<?php
/**
 * Music admin — artist create / edit form.
 *
 * Submits to inc/music/admin.php::vatan_music_admin_handle_artist_save.
 *
 * Includes a JS-driven repeater for social links (platform select + URL).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$is_edit = ( 'edit' === $action );
$post_id = $is_edit && isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$post = $post_id ? get_post( $post_id ) : null;
if ( $is_edit && ( ! $post || 'artist' !== $post->post_type ) ) {
	echo '<div class="vatan-music-admin__empty"><p>' . esc_html__( 'Artist not found.', 'vatan-event' ) . '</p>';
	echo '<p><a class="vatan-music-admin__btn-mini" href="' . esc_url( vatan_music_admin_url( 'artists' ) ) . '">← ' . esc_html__( 'Back to artists', 'vatan-event' ) . '</a></p></div>';
	return;
}

$title       = $post ? $post->post_title : '';
$content     = $post ? $post->post_content : '';
$post_status = $post ? $post->post_status : 'publish';
$country     = $post && function_exists( 'get_field' ) ? (string) get_field( 'artist_country', $post_id ) : '';
$featured    = $post && function_exists( 'get_field' ) ? (bool) get_field( 'artist_is_featured', $post_id ) : false;
$links       = $post && function_exists( 'get_field' ) ? (array) ( get_field( 'artist_links', $post_id ) ?: array() ) : array();

$photo_id  = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
$photo_url = $photo_id ? (string) wp_get_attachment_image_url( $photo_id, 'medium' ) : '';

$platforms = array(
	'website'    => __( 'Website', 'vatan-event' ),
	'instagram'  => 'Instagram',
	'youtube'    => 'YouTube',
	'spotify'    => 'Spotify',
	'apple'      => 'Apple Music',
	'soundcloud' => 'SoundCloud',
	'twitter'    => 'X / Twitter',
	'telegram'   => 'Telegram',
);

// Catalog counts surfaced in the sidebar.
$track_count = $post_id ? (int) ( new WP_Query( array(
	'post_type'      => 'track',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'track_artist', 'value' => $post_id, 'compare' => '=' ),
	),
) ) )->found_posts : 0;
$album_count = $post_id ? (int) ( new WP_Query( array(
	'post_type'      => 'album',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
		array( 'key' => 'album_artist', 'value' => $post_id, 'compare' => '=' ),
	),
) ) )->found_posts : 0;
?>

<header class="vatan-music-admin__head">
	<div>
		<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'artists' ) ); ?>" style="margin-bottom:8px;">
			← <?php esc_html_e( 'Back to artists', 'vatan-event' ); ?>
		</a>
		<h2 style="margin-top:6px;">
			<?php echo $is_edit ? esc_html__( 'Edit artist', 'vatan-event' ) : esc_html__( 'New artist', 'vatan-event' ); ?>
		</h2>
	</div>
</header>

<form method="post" action="" enctype="multipart/form-data" class="vatan-music-form">
	<input type="hidden" name="vatan_music_save_artist" value="1" />
	<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>" />
	<?php wp_nonce_field( 'vatan_music_save_artist', '_vatan_music_artist_nonce' ); ?>

	<div class="vatan-music-form__grid">

		<div class="vatan-music-form__col">

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-title">
					<?php esc_html_e( 'Name', 'vatan-event' ); ?>
					<span class="vatan-music-form__required">*</span>
				</label>
				<input id="vm-title" type="text" name="title" required
					value="<?php echo esc_attr( $title ); ?>"
					class="vatan-music-form__input vatan-music-form__input--big"
					placeholder="<?php esc_attr_e( 'Artist name', 'vatan-event' ); ?>" />
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-content"><?php esc_html_e( 'Biography', 'vatan-event' ); ?></label>
				<textarea id="vm-content" name="post_content" rows="8" class="vatan-music-form__textarea"
					placeholder="<?php esc_attr_e( 'Short bio shown on the artist profile.', 'vatan-event' ); ?>"><?php echo esc_textarea( $content ); ?></textarea>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Links', 'vatan-event' ); ?></label>
				<div data-vm-link-list class="vatan-music-form__links">
					<?php
					$rows = $links ?: array();
					$render_link_row = function ( $row, $idx, $platforms ) {
						$platform = (string) ( $row['platform'] ?? '' );
						$url      = (string) ( $row['url'] ?? '' );
						?>
						<div class="vatan-music-form__link-row" data-vm-link-row>
							<select name="artist_links[<?php echo (int) $idx; ?>][platform]" class="vatan-music-form__input">
								<?php foreach ( $platforms as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $platform, $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="url" name="artist_links[<?php echo (int) $idx; ?>][url]"
								value="<?php echo esc_attr( $url ); ?>"
								class="vatan-music-form__input" placeholder="https://" />
							<button type="button" class="vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger" data-vm-link-remove>
								×
							</button>
						</div>
						<?php
					};
					if ( empty( $rows ) ) {
						$render_link_row( array(), 0, $platforms );
					} else {
						foreach ( $rows as $i => $row ) {
							$render_link_row( $row, $i, $platforms );
						}
					}
					?>
				</div>
				<button type="button" class="vatan-music-admin__btn-mini" data-vm-link-add style="margin-top:8px;">
					+ <?php esc_html_e( 'Add link', 'vatan-event' ); ?>
				</button>

				<!-- Hidden template row used by JS -->
				<template data-vm-link-template>
					<div class="vatan-music-form__link-row" data-vm-link-row>
						<select name="artist_links[__INDEX__][platform]" class="vatan-music-form__input">
							<?php foreach ( $platforms as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="url" name="artist_links[__INDEX__][url]" class="vatan-music-form__input" placeholder="https://" />
						<button type="button" class="vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger" data-vm-link-remove>×</button>
					</div>
				</template>
			</section>

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
					<input type="checkbox" name="artist_is_featured" value="1" <?php checked( $featured ); ?> />
					<span>
						<strong>★ <?php esc_html_e( 'Featured', 'vatan-event' ); ?></strong>
						<small><?php esc_html_e( 'Surface this artist on the player landing page.', 'vatan-event' ); ?></small>
					</span>
				</label>

				<div class="vatan-music-form__actions">
					<button type="submit" class="vatan-admin__btn vatan-admin__btn--primary">
						<?php echo $is_edit ? esc_html__( 'Save changes', 'vatan-event' ) : esc_html__( 'Create artist', 'vatan-event' ); ?>
					</button>
					<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'artists' ) ); ?>">
						<?php esc_html_e( 'Cancel', 'vatan-event' ); ?>
					</a>
				</div>
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Photo', 'vatan-event' ); ?></label>
				<?php if ( $photo_url ) : ?>
					<div class="vatan-music-form__cover-preview">
						<img src="<?php echo esc_url( $photo_url ); ?>" alt="" style="border-radius:50%;max-width:160px;" />
						<label class="vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger">
							<input type="checkbox" name="remove_photo" value="1" style="margin-inline-end:4px;" />
							<?php esc_html_e( 'Remove on save', 'vatan-event' ); ?>
						</label>
					</div>
				<?php endif; ?>
				<input type="file" name="photo_file" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif" class="vatan-music-form__file" />
			</section>

			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label" for="vm-country"><?php esc_html_e( 'Country', 'vatan-event' ); ?></label>
				<input id="vm-country" type="text" name="artist_country"
					value="<?php echo esc_attr( $country ); ?>"
					class="vatan-music-form__input"
					placeholder="<?php esc_attr_e( 'Optional — free text', 'vatan-event' ); ?>" />
			</section>

			<?php if ( $is_edit ) : ?>
			<section class="vatan-music-form__card">
				<label class="vatan-music-form__label"><?php esc_html_e( 'Catalog', 'vatan-event' ); ?></label>
				<p style="margin:0;font-size:13px;color:var(--vma-text-soft);">
					<?php
					/* translators: 1: track count, 2: album count */
					printf( esc_html__( '%1$s tracks · %2$s albums', 'vatan-event' ),
						esc_html( vatan_to_persian_digits( $track_count ) ),
						esc_html( vatan_to_persian_digits( $album_count ) )
					);
					?>
				</p>
				<p style="margin:8px 0 0;">
					<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'tracks', array( 'q' => get_the_title( $post_id ) ) ) ); ?>">
						<?php esc_html_e( 'View tracks', 'vatan-event' ); ?>
					</a>
				</p>
			</section>
			<?php endif; ?>

		</div>
	</div>
</form>

<script>
( function () {
	var list = document.querySelector('[data-vm-link-list]');
	var addBtn = document.querySelector('[data-vm-link-add]');
	var tpl = document.querySelector('[data-vm-link-template]');
	if ( ! list || ! addBtn || ! tpl ) return;

	function nextIndex() {
		var rows = list.querySelectorAll('[data-vm-link-row]');
		return rows.length;
	}

	addBtn.addEventListener('click', function () {
		var html = tpl.innerHTML.replace(/__INDEX__/g, nextIndex());
		var wrap = document.createElement('div');
		wrap.innerHTML = html;
		list.appendChild(wrap.firstElementChild);
	});

	list.addEventListener('click', function ( e ) {
		var btn = e.target.closest('[data-vm-link-remove]');
		if ( ! btn ) return;
		var row = btn.closest('[data-vm-link-row]');
		if ( row ) row.remove();
	});
} )();
</script>
