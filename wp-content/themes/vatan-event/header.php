<?php
/**
 * Site header.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$cart_count = 0;
if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
	$cart_count = WC()->cart->get_cart_contents_count();
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'vatan-event' ); ?></a>

<header class="site-header">
	<div class="container">

		<!-- Mobile menu trigger (only visible <992px) -->
		<button type="button" class="drawer-toggle" data-vatan-drawer-toggle aria-label="<?php esc_attr_e( 'Open menu', 'vatan-event' ); ?>" aria-expanded="false" aria-controls="vatan-drawer">
			<span class="drawer-toggle__bars" aria-hidden="true">
				<span></span><span></span><span></span>
			</span>
		</button>

		<!-- Logo -->
		<?php vatan_render_site_logo( 'lg' ); ?>

		<!-- Navigation -->
		<nav class="main-nav" aria-label="<?php esc_attr_e( 'Primary', 'vatan-event' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => vatan_current_menu_location(),
				'container'      => false,
				'menu_class'     => 'main-nav__menu',
				'menu_id'        => 'primary-menu',
				'fallback_cb'    => 'vatan_default_primary_menu',
				'depth'          => 2,
			) );
			?>
		</nav>

		<!-- Actions -->
		<div class="header-actions">

			<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>
				<a class="cart-icon" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Cart', 'vatan-event' ); ?>">
					<svg class="cart-icon__svg" aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="9" cy="21" r="1"></circle>
						<circle cx="20" cy="21" r="1"></circle>
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
					</svg>
					<span class="cart-count" data-empty="<?php echo $cart_count > 0 ? 'false' : 'true'; ?>" data-vatan-cart-count><?php echo esc_html( $cart_count ); ?></span>
				</a>
			<?php endif; ?>

			<!-- Language switcher -->
			<?php vatan_render_language_switcher(); ?>

			<!-- Auth -->
			<?php if ( is_user_logged_in() ) : ?>
				<?php
				$current_user = wp_get_current_user();
				$account_url  = function_exists( 'wc_get_account_endpoint_url' )
					? wc_get_account_endpoint_url( 'dashboard' )
					: admin_url( 'profile.php' );
				$tickets_url  = function_exists( 'wc_get_account_endpoint_url' )
					? wc_get_account_endpoint_url( 'my-tickets' )
					: '#';
				?>
				<details class="user-menu">
					<summary>
						<?php
						echo get_avatar(
							$current_user->ID,
							28,
							'',
							$current_user->display_name,
							array( 'class' => 'user-menu__avatar' )
						);
						?>
						<span class="user-menu__name"><?php echo esc_html( $current_user->display_name ); ?></span>
					</summary>
					<ul class="user-menu__dropdown">
						<li><a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'My Account', 'vatan-event' ); ?></a></li>
						<li><a href="<?php echo esc_url( $tickets_url ); ?>"><?php esc_html_e( 'My Tickets', 'vatan-event' ); ?></a></li>
						<?php if ( function_exists( 'vatan_can_submit_event' ) && vatan_can_submit_event() ) :
							$create_url = function_exists( 'vatan_static_page_url' ) ? vatan_static_page_url( 'create-event' ) : '';
							?>
							<li><a href="<?php echo esc_url( $create_url ?: home_url( '/create-event/' ) ); ?>"><?php esc_html_e( 'Create event', 'vatan-event' ); ?></a></li>
						<?php endif; ?>
						<?php
						// Admin Panel — shown only to administrators and shop managers.
						// `manage_woocommerce` is the lowest capability that covers both
						// roles cleanly without listing them by name.
						if ( current_user_can( 'manage_woocommerce' ) ) :
							$admin_url = function_exists( 'vatan_static_page_url' )
								? vatan_static_page_url( 'admin' )
								: '';
							?>
							<li class="user-menu__item--admin">
								<a href="<?php echo esc_url( $admin_url ?: home_url( '/admin/' ) ); ?>">
									<svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-inline-end:6px;">
										<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"></path>
									</svg>
									<?php esc_html_e( 'Admin Panel', 'vatan-event' ); ?>
								</a>
							</li>
						<?php endif; ?>
						<li><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Logout', 'vatan-event' ); ?></a></li>
					</ul>
				</details>
			<?php else : ?>
				<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( wp_login_url() ); ?>">
					<?php esc_html_e( 'Login', 'vatan-event' ); ?>
				</a>
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<a class="btn btn--primary btn--sm" href="<?php echo esc_url( wp_registration_url() ); ?>">
						<?php esc_html_e( 'Register', 'vatan-event' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>
		</div>

	</div>
</header>

<!-- Mobile drawer (slides in from the inline-end on <992px) -->
<?php
// ─── Bottom mobile nav ─────────────────────────────────────────────────
// Fixed at the bottom on <992px. The "Menu" button reuses the same
// `data-vatan-drawer-toggle` attribute the header hamburger uses — same
// drawer JS, no extra wiring. Cart stays in the header (since it's the
// hottest action and we want the count badge visible there too).
$bn_home_url   = home_url( '/' );
$bn_events_url = get_post_type_archive_link( 'event' ) ?: home_url( '/' );
$bn_logged_in  = is_user_logged_in();
if ( $bn_logged_in ) {
	$bn_tickets_url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'my-tickets' )
		: home_url( '/' );
	$bn_account_url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'dashboard' )
		: admin_url( 'profile.php' );
} else {
	$bn_tickets_url = function_exists( 'vatan_auth_login_url' )
		? vatan_auth_login_url( home_url( '/' ) )
		: wp_login_url();
	$bn_account_url = $bn_tickets_url;
}

// Highlight the active tab. Cheap-and-cheerful URL prefix match; works
// for the canonical pages and survives query strings.
$bn_current = ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . strtok( $_SERVER['REQUEST_URI'] ?? '/', '?' );
$bn_is_active = static function ( $url ) use ( $bn_current ) {
	$path = parse_url( $url, PHP_URL_PATH ) ?: '/';
	$cur  = parse_url( $bn_current, PHP_URL_PATH ) ?: '/';
	if ( '/' === $path ) {
		return '/' === $cur;
	}
	return 0 === strpos( $cur, $path );
};
?>
<nav class="bottom-nav" aria-label="<?php esc_attr_e( 'Primary mobile', 'vatan-event' ); ?>">
	<a class="bottom-nav__item<?php echo $bn_is_active( $bn_home_url ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $bn_home_url ); ?>">
		<svg class="bottom-nav__icon" aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1z"></path>
		</svg>
		<span class="bottom-nav__label"><?php esc_html_e( 'Home', 'vatan-event' ); ?></span>
	</a>

	<a class="bottom-nav__item<?php echo $bn_is_active( $bn_events_url ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $bn_events_url ); ?>">
		<svg class="bottom-nav__icon" aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<rect x="3" y="5" width="18" height="16" rx="2"></rect>
			<line x1="3" y1="10" x2="21" y2="10"></line>
			<line x1="8" y1="3" x2="8" y2="7"></line>
			<line x1="16" y1="3" x2="16" y2="7"></line>
		</svg>
		<span class="bottom-nav__label"><?php esc_html_e( 'Events', 'vatan-event' ); ?></span>
	</a>

	<a class="bottom-nav__item<?php echo $bn_is_active( $bn_tickets_url ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $bn_tickets_url ); ?>">
		<svg class="bottom-nav__icon" aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"></path>
			<line x1="9" y1="7" x2="9" y2="17" stroke-dasharray="2 3"></line>
		</svg>
		<span class="bottom-nav__label"><?php echo $bn_logged_in ? esc_html__( 'Tickets', 'vatan-event' ) : esc_html__( 'Login', 'vatan-event' ); ?></span>
	</a>

	<a class="bottom-nav__item<?php echo $bn_logged_in && $bn_is_active( $bn_account_url ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $bn_account_url ); ?>">
		<svg class="bottom-nav__icon" aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<circle cx="12" cy="8" r="4"></circle>
			<path d="M4 21a8 8 0 0 1 16 0"></path>
		</svg>
		<span class="bottom-nav__label"><?php echo $bn_logged_in ? esc_html__( 'Account', 'vatan-event' ) : esc_html__( 'Sign up', 'vatan-event' ); ?></span>
	</a>

	<button type="button" class="bottom-nav__item bottom-nav__item--menu" data-vatan-drawer-toggle aria-label="<?php esc_attr_e( 'Open menu', 'vatan-event' ); ?>" aria-expanded="false" aria-controls="vatan-drawer">
		<svg class="bottom-nav__icon" aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
			<line x1="4" y1="7"  x2="20" y2="7"></line>
			<line x1="4" y1="12" x2="20" y2="12"></line>
			<line x1="4" y1="17" x2="20" y2="17"></line>
		</svg>
		<span class="bottom-nav__label"><?php esc_html_e( 'Menu', 'vatan-event' ); ?></span>
	</button>
</nav>

<aside id="vatan-drawer" class="drawer" data-vatan-drawer aria-hidden="true" aria-label="<?php esc_attr_e( 'Mobile menu', 'vatan-event' ); ?>" tabindex="-1">
	<header class="drawer__head">
		<?php vatan_render_site_logo( 'sm' ); ?>
		<button type="button" class="drawer__close" data-vatan-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'vatan-event' ); ?>">
			<svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
				<line x1="18" y1="6" x2="6" y2="18"></line>
				<line x1="6" y1="6" x2="18" y2="18"></line>
			</svg>
		</button>
	</header>

	<nav class="drawer__nav" aria-label="<?php esc_attr_e( 'Primary mobile', 'vatan-event' ); ?>">
		<?php
		wp_nav_menu( array(
			'theme_location' => vatan_current_menu_location(),
			'container'      => false,
			'menu_class'     => 'drawer__menu',
			'menu_id'        => 'mobile-primary-menu',
			'fallback_cb'    => 'vatan_default_primary_menu',
			'depth'          => 2,
		) );
		?>
	</nav>

	<footer class="drawer__foot">
		<?php vatan_render_language_switcher(); ?>

		<?php if ( ! is_user_logged_in() ) : ?>
			<a class="btn btn--ghost btn--full" href="<?php echo esc_url( wp_login_url() ); ?>">
				<?php esc_html_e( 'Login', 'vatan-event' ); ?>
			</a>
			<?php if ( get_option( 'users_can_register' ) ) : ?>
				<a class="btn btn--primary btn--full" href="<?php echo esc_url( wp_registration_url() ); ?>">
					<?php esc_html_e( 'Register', 'vatan-event' ); ?>
				</a>
			<?php endif; ?>
		<?php else : ?>
			<a class="btn btn--ghost btn--full" href="<?php echo esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : admin_url( 'profile.php' ) ); ?>">
				<?php esc_html_e( 'My Account', 'vatan-event' ); ?>
			</a>
			<a class="btn btn--primary btn--full" href="<?php echo esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'my-tickets' ) : '#' ); ?>">
				<?php esc_html_e( 'My Tickets', 'vatan-event' ); ?>
			</a>
			<?php
			// Admin Panel button — drawer mirror of the desktop dropdown entry.
			// Same `manage_woocommerce` gate so admins + shop managers see it.
			if ( current_user_can( 'manage_woocommerce' ) ) :
				$admin_url = function_exists( 'vatan_static_page_url' )
					? vatan_static_page_url( 'admin' )
					: '';
				?>
				<a class="btn btn--full drawer__admin-btn" href="<?php echo esc_url( $admin_url ?: home_url( '/admin/' ) ); ?>">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-inline-end:8px;">
						<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"></path>
					</svg>
					<?php esc_html_e( 'Admin Panel', 'vatan-event' ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</footer>
</aside>
<div class="drawer-backdrop" data-vatan-drawer-backdrop aria-hidden="true"></div>

<div id="main" class="site-content">
