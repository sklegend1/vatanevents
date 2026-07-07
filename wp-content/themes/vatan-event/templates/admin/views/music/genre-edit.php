<?php
/**
 * Music admin — genre create / edit form.
 *
 * Genres are taxonomy terms, not posts, so the form is much smaller than
 * the track / album / artist editors: name, slug (optional, auto from
 * name), emoji. Submits to vatan_music_admin_handle_genre_save.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$is_edit = ( 'edit' === $action );
$term_id = $is_edit && isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$term = $term_id ? get_term( $term_id, 'music_genre' ) : null;
if ( $is_edit && ( ! $term || is_wp_error( $term ) ) ) {
	echo '<div class="vatan-music-admin__empty"><p>' . esc_html__( 'Genre not found.', 'vatan-event' ) . '</p>';
	echo '<p><a class="vatan-music-admin__btn-mini" href="' . esc_url( vatan_music_admin_url( 'genres' ) ) . '">← ' . esc_html__( 'Back to genres', 'vatan-event' ) . '</a></p></div>';
	return;
}

$name  = $term ? $term->name : '';
$slug  = $term ? $term->slug : '';
$emoji = $term ? (string) get_term_meta( $term->term_id, 'vatan_emoji', true ) : '';

$track_count = $term ? (int) $term->count : 0;
?>

<header class="vatan-music-admin__head">
	<div>
		<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'genres' ) ); ?>" style="margin-bottom:8px;">
			← <?php esc_html_e( 'Back to genres', 'vatan-event' ); ?>
		</a>
		<h2 style="margin-top:6px;">
			<?php echo $is_edit ? esc_html__( 'Edit genre', 'vatan-event' ) : esc_html__( 'New genre', 'vatan-event' ); ?>
		</h2>
	</div>
</header>

<form method="post" action="" class="vatan-music-form">
	<input type="hidden" name="vatan_music_save_genre" value="1" />
	<input type="hidden" name="term_id" value="<?php echo (int) $term_id; ?>" />
	<?php wp_nonce_field( 'vatan_music_save_genre', '_vatan_music_genre_nonce' ); ?>

	<div style="max-width:560px;">

		<section class="vatan-music-form__card">
			<label class="vatan-music-form__label" for="vm-name">
				<?php esc_html_e( 'Name', 'vatan-event' ); ?>
				<span class="vatan-music-form__required">*</span>
			</label>
			<input id="vm-name" type="text" name="name" required
				value="<?php echo esc_attr( $name ); ?>"
				class="vatan-music-form__input vatan-music-form__input--big"
				placeholder="<?php esc_attr_e( 'Genre name (e.g. Pop, Traditional)', 'vatan-event' ); ?>" />

			<label class="vatan-music-form__label" for="vm-slug" style="margin-top:14px;">
				<?php esc_html_e( 'Slug', 'vatan-event' ); ?>
			</label>
			<input id="vm-slug" type="text" name="slug"
				value="<?php echo esc_attr( $slug ); ?>"
				class="vatan-music-form__input"
				placeholder="<?php esc_attr_e( 'Optional — auto-generated from name', 'vatan-event' ); ?>" />
			<p class="vatan-music-form__help"><?php esc_html_e( 'Stable identifier used in URLs and auto-emoji matching. English lowercase, dashes for spaces.', 'vatan-event' ); ?></p>

			<label class="vatan-music-form__label" for="vm-emoji" style="margin-top:14px;">
				<?php esc_html_e( 'Emoji', 'vatan-event' ); ?>
			</label>
			<input id="vm-emoji" type="text" name="vatan_emoji"
				value="<?php echo esc_attr( $emoji ); ?>"
				class="vatan-music-form__input"
				maxlength="8"
				style="max-width:120px;font-size:24px;text-align:center;"
				placeholder="🎵" />
			<p class="vatan-music-form__help">
				<?php esc_html_e( 'One emoji shown next to the genre everywhere. Leave blank to use the auto-detected one (matched from the slug).', 'vatan-event' ); ?>
			</p>

			<?php if ( $is_edit && $track_count > 0 ) : ?>
				<p class="vatan-music-form__help" style="margin-top:14px;">
					<?php
					/* translators: %s: track count */
					printf( esc_html__( 'Tagged on %s tracks.', 'vatan-event' ), esc_html( vatan_to_persian_digits( $track_count ) ) );
					?>
					<a href="<?php echo esc_url( vatan_music_admin_url( 'tracks', array( 'q' => $term->name ) ) ); ?>" style="color:inherit;text-decoration:underline;margin-inline-start:4px;">
						<?php esc_html_e( 'View tracks', 'vatan-event' ); ?> →
					</a>
				</p>
			<?php endif; ?>

			<div class="vatan-music-form__actions">
				<button type="submit" class="vatan-admin__btn vatan-admin__btn--primary">
					<?php echo $is_edit ? esc_html__( 'Save changes', 'vatan-event' ) : esc_html__( 'Create genre', 'vatan-event' ); ?>
				</button>
				<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( vatan_music_admin_url( 'genres' ) ); ?>">
					<?php esc_html_e( 'Cancel', 'vatan-event' ); ?>
				</a>
			</div>
		</section>
	</div>
</form>
