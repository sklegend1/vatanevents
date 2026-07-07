<?php
/**
 * Site footer.
 *
 * Four-column layout inspired by evento.events:
 *   1. About + brand + tagline
 *   2. Discover — content / browse links
 *   3. Help & company — onboarding, FAQ, contact
 *   4. Get the app + social icons
 *
 * Each link is wrapped in `vatan_static_page_url(...) ?: '#'` so the
 * footer keeps rendering cleanly even when a target page hasn't been
 * seeded yet.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$events_archive = get_post_type_archive_link( 'event' );
$events_archive = is_string( $events_archive ) ? $events_archive : home_url( '/' );

// Blog landing — the seeded "blog" page is wired as WP's Posts page so
// home.php renders the post list there.
$blog_archive = function_exists( 'vatan_static_page_url' ) ? (string) vatan_static_page_url( 'blog' ) : '';
if ( ! $blog_archive ) {
	$blog_archive = home_url( '/blog/' );
}

$page_url = function ( string $slug, string $fallback = '#' ) {
	$u = function_exists( 'vatan_static_page_url' ) ? (string) vatan_static_page_url( $slug ) : '';
	return $u ?: $fallback;
};

$cat_url = function ( string $slug ) use ( $events_archive ) {
	$t = get_term_by( 'slug', $slug, 'event_category' );
	if ( ! $t ) {
		return $events_archive;
	}
	$u = get_term_link( $t );
	return is_string( $u ) ? $u : $events_archive;
};
?>

</div><!-- /#main -->

<footer class="site-footer">
	<div class="container site-footer__inner">

		<!-- 1. Brand + tagline ........................................ -->
		<div class="site-footer__column site-footer__about">
			<div class="site-footer__brand">
				<?php vatan_render_site_logo( 'footer' ); ?>
			</div>
			<p class="site-footer__tagline">
				<?php
				$tagline = (string) get_bloginfo( 'description' );
				echo esc_html( $tagline ?: __( 'A ticketing platform for Persian-language events and community gatherings across Europe.', 'vatan-event' ) );
				?>
			</p>
			<?php if ( function_exists( 'vatan_render_social_links' ) ) {
				vatan_render_social_links();
			} ?>
		</div>

		<!-- 2. Discover ............................................... -->
		<div class="site-footer__column">
			<h4 class="site-footer__title"><?php esc_html_e( 'Discover', 'vatan-event' ); ?></h4>
			<ul class="site-footer__menu">
				<li><a href="<?php echo esc_url( $events_archive ); ?>"><?php esc_html_e( 'All Events', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $cat_url( 'concert' ) ); ?>"><?php esc_html_e( 'Concerts', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $cat_url( 'theater' ) ); ?>"><?php esc_html_e( 'Theater', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $cat_url( 'traditional-music' ) ); ?>"><?php esc_html_e( 'Traditional music', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $cat_url( 'standup' ) ); ?>"><?php esc_html_e( 'Standup comedy', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $blog_archive ); ?>"><?php esc_html_e( 'Guides', 'vatan-event' ); ?></a></li>
			</ul>
		</div>

		<!-- 3. Help & company ......................................... -->
		<div class="site-footer__column">
			<h4 class="site-footer__title"><?php esc_html_e( 'Help & company', 'vatan-event' ); ?></h4>
			<ul class="site-footer__menu">
				<li><a href="<?php echo esc_url( $page_url( 'support' ) ); ?>"><?php esc_html_e( 'Help Center', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'faq' ) ); ?>"><?php esc_html_e( 'FAQ', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'contact' ) ); ?>"><?php esc_html_e( 'Contact Us', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'about' ) ); ?>"><?php esc_html_e( 'About Us', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'create-event' ) ); ?>"><?php esc_html_e( 'Create event', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'terms' ) ); ?>"><?php esc_html_e( 'Terms', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Privacy', 'vatan-event' ); ?></a></li>
			</ul>
		</div>

		<!-- 4. App + payment .......................................... -->
		<div class="site-footer__column site-footer__apps">
			<h4 class="site-footer__title"><?php esc_html_e( 'Get the app', 'vatan-event' ); ?></h4>
			<?php
			$play_store_url = function_exists( 'vatan_get_setting' ) ? (string) vatan_get_setting( 'play_store_url' ) : '';
			?>
			<div class="site-footer__app-badges">
				<!-- <?php
				$app_store_url = function_exists( 'vatan_get_setting' ) ? (string) vatan_get_setting( 'app_store_url' ) : '';
				if ( $app_store_url ) :
				?>
				<a class="app-badge" href="<?php echo esc_url( $app_store_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'App Store', 'vatan-event' ); ?>
				</a>
				<?php endif; ?>
				-->
				<a class="app-badge" href="<?php echo esc_url( $play_store_url ?: '#' ); ?>"<?php if ( $play_store_url ) : ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
					<?php esc_html_e( 'Download Android App', 'vatan-event' ); ?>
				</a>
			</div>

			<p class="site-footer__newsletter-prompt">
				<?php esc_html_e( 'Subscribe to our newsletter to hear about new events first.', 'vatan-event' ); ?>
			</p>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $page_url( 'contact' ) ); ?>#newsletter">
				<?php esc_html_e( 'Subscribe', 'vatan-event' ); ?>
			</a>
		</div>
	</div>

	<div class="site-footer__bottom">
		<div class="container site-footer__bottom-inner">
			<p class="site-footer__copy">
				<?php
				printf(
					/* translators: 1: year, 2: site name */
					esc_html__( '© %1$s %2$s. All rights reserved.', 'vatan-event' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</p>
			<ul class="site-footer__legal">
				<li><a href="<?php echo esc_url( $page_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Privacy', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'terms' ) ); ?>"><?php esc_html_e( 'Terms', 'vatan-event' ); ?></a></li>
				<li><a href="<?php echo esc_url( $page_url( 'contact' ) ); ?>"><?php esc_html_e( 'Contact', 'vatan-event' ); ?></a></li>
			</ul>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
