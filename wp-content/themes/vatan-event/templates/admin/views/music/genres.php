<?php
/**
 * Music admin — genres list view.
 *
 * Genres are taxonomy terms (`music_genre`), not posts. Phase 1 surfaces
 * a read-friendly table with emoji + track counts; create / edit / delete
 * route to wp-admin's taxonomy screens. Phase 3 swaps in the in-dashboard
 * editor (small enough to skip the trash-recovery pattern used elsewhere).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/* ----- Dispatch the editor when ?vatan_action=new|edit ---------------- */
if ( isset( $action ) && in_array( $action, array( 'new', 'edit' ), true ) ) {
	$edit_file = locate_template( 'templates/admin/views/music/genre-edit.php', false, false );
	if ( $edit_file ) {
		include $edit_file;
		return;
	}
}

$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$terms = get_terms( array(
	'taxonomy'   => 'music_genre',
	'hide_empty' => false,
	'search'     => $search,
	'orderby'    => 'count',
	'order'      => 'DESC',
) );
if ( is_wp_error( $terms ) ) {
	$terms = array();
}

$url_new = vatan_music_admin_new_genre_url();
?>

<header class="vatan-music-admin__head">
	<h2><?php esc_html_e( 'Genres', 'vatan-event' ); ?></h2>
	<a class="vatan-admin__btn vatan-admin__btn--primary" href="<?php echo esc_url( $url_new ); ?>">
		+ <?php esc_html_e( 'Add genre', 'vatan-event' ); ?>
	</a>
</header>

<div class="vatan-music-admin__filters">
	<form method="get" action="" style="display:contents;">
		<input type="hidden" name="pagename" value="admin" />
		<input type="hidden" name="view" value="music" />
		<input type="hidden" name="type" value="genres" />
		<input class="vatan-music-admin__search" type="search" name="q" value="<?php echo esc_attr( $search ); ?>"
		       placeholder="<?php esc_attr_e( 'Search genres…', 'vatan-event' ); ?>" />
	</form>
	<span class="vatan-music-admin__count">
		<?php
		/* translators: %s: total genre count */
		echo esc_html( sprintf( __( '%s total', 'vatan-event' ), vatan_to_persian_digits( count( $terms ) ) ) );
		?>
	</span>
</div>

<?php if ( empty( $terms ) ) : ?>
	<div class="vatan-music-admin__empty">
		<p><?php
		if ( $search ) {
			/* translators: %s: search query */
			echo esc_html( sprintf( __( 'No genres match "%s".', 'vatan-event' ), $search ) );
		} else {
			esc_html_e( 'No genres yet. Re-activate the theme to seed the starter set, or add one manually.', 'vatan-event' );
		}
		?></p>
	</div>
<?php else : ?>
	<div class="vatan-music-admin__table-wrap">
		<table class="vatan-music-admin__table">
			<thead>
				<tr>
					<th style="width:50px;text-align:center;"><?php esc_html_e( 'Emoji', 'vatan-event' ); ?></th>
					<th><?php esc_html_e( 'Name', 'vatan-event' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'vatan-event' ); ?></th>
					<?php if ( function_exists( 'pll_get_term_language' ) ) : ?>
						<th style="width:80px;"><?php esc_html_e( 'Lang', 'vatan-event' ); ?></th>
					<?php endif; ?>
					<th style="width:90px;"><?php esc_html_e( 'Tracks', 'vatan-event' ); ?></th>
					<th style="width:120px;text-align:end;"><?php esc_html_e( 'Actions', 'vatan-event' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $terms as $term ) :
					$emoji    = (string) get_term_meta( $term->term_id, 'vatan_emoji', true );
					$edit_url = vatan_music_admin_edit_genre_url( (int) $term->term_id );
					$lang     = function_exists( 'pll_get_term_language' ) ? (string) pll_get_term_language( $term->term_id ) : '';
					$nonce    = wp_create_nonce( 'vatan_music_delete_genre_' . $term->term_id );
					?>
					<tr>
						<td style="text-align:center;font-size:20px;line-height:1;"><?php echo esc_html( $emoji ?: '🎵' ); ?></td>
						<td>
							<a class="vatan-music-admin__row-title" href="<?php echo esc_url( $edit_url ); ?>">
								<?php echo esc_html( $term->name ); ?>
							</a>
						</td>
						<td><code style="font-size:12px;opacity:.7;"><?php echo esc_html( $term->slug ); ?></code></td>
						<?php if ( function_exists( 'pll_get_term_language' ) ) : ?>
							<td><?php echo $lang ? esc_html( strtoupper( $lang ) ) : '<span style="opacity:.5;">—</span>'; ?></td>
						<?php endif; ?>
						<td>
							<a href="<?php echo esc_url( vatan_music_admin_url( 'tracks', array( 'q' => $term->name ) ) ); ?>" style="color:inherit;">
								<?php echo esc_html( vatan_to_persian_digits( (int) $term->count ) ); ?>
							</a>
						</td>
						<td style="text-align:end;">
							<span class="vatan-music-admin__row-actions">
								<a class="vatan-music-admin__btn-mini" href="<?php echo esc_url( $edit_url ); ?>">
									<?php esc_html_e( 'Edit', 'vatan-event' ); ?>
								</a>
								<form method="post" action="" style="display:inline;margin:0;"
									onsubmit="return confirm('<?php echo esc_js( __( 'Delete this genre? Tracks tagged with it will lose the tag.', 'vatan-event' ) ); ?>');">
									<input type="hidden" name="vatan_music_delete_genre" value="1" />
									<input type="hidden" name="term_id" value="<?php echo (int) $term->term_id; ?>" />
									<input type="hidden" name="_vatan_music_genre_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
									<button type="submit" class="vatan-music-admin__btn-mini vatan-music-admin__btn-mini--danger">
										<?php esc_html_e( 'Delete', 'vatan-event' ); ?>
									</button>
								</form>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

