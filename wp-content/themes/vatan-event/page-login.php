<?php
/**
 * Template Name: Vatan — Login
 *
 * Custom login page. Branded form on the right, photo splash on the left.
 * The form POSTs to itself; inc/auth.php picks it up on init and either
 * signs the user in (and redirects) or bounces back with ?err=... in the URL.
 *
 * Auto-loaded by WordPress when assigned to a page named "Login".
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

// If already logged in, send them to My Account.
if ( is_user_logged_in() ) {
	$dashboard = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'dashboard' )
		: home_url( '/' );
	wp_safe_redirect( $dashboard );
	exit;
}

$error       = isset( $_GET['err'] ) ? sanitize_text_field( wp_unslash( $_GET['err'] ) ) : '';
$username    = isset( $_GET['u'] )   ? sanitize_text_field( wp_unslash( $_GET['u'] ) )   : '';
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

// Splash image — reuse one of the already-seeded event covers.
$splash_url = '';
$state      = (array) get_option( 'vatan_media_seeded', array() );
foreach ( $state['attachments'] ?? array() as $key => $id ) {
	if ( str_ends_with( $key, ':hero-concert-crowd' ) ) {
		$splash_url = wp_get_attachment_image_url( (int) $id, 'large' );
		break;
	}
}

get_header();
?>

<main class="site-main site-main--auth">
	<div class="auth-shell">
		<aside class="auth-splash" aria-hidden="true">
			<?php if ( $splash_url ) : ?>
				<img class="auth-splash__img" src="<?php echo esc_url( $splash_url ); ?>" alt="" loading="eager" />
			<?php endif; ?>
			<div class="auth-splash__overlay"></div>
			<div class="auth-splash__copy">
				<p class="auth-splash__eyebrow"><?php esc_html_e( 'Vatan Event', 'vatan-event' ); ?></p>
				<h2 class="auth-splash__title">رویدادهای فارسی‌زبان، یک کلیک فاصله</h2>
				<p class="auth-splash__lead">به حساب کاربری وارد شو تا بلیت‌هایت را ببینی، رویدادها را دنبال کنی، و تجربهٔ کامل پلتفرم را داشته باشی.</p>
			</div>
		</aside>

		<section class="auth-card">
			<header class="auth-card__head">
				<a class="auth-card__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<span class="auth-card__brand-icon" aria-hidden="true">V</span>
					<span class="auth-card__brand-text"><?php bloginfo( 'name' ); ?></span>
				</a>
				<h1 class="auth-card__title"><?php esc_html_e( 'Welcome back', 'vatan-event' ); ?></h1>
				<p class="auth-card__lead"><?php esc_html_e( 'Sign in to continue to your dashboard.', 'vatan-event' ); ?></p>
			</header>

			<?php if ( $error ) : ?>
				<div class="auth-card__error" role="alert">
					<?php echo esc_html( $error ); ?>
				</div>
			<?php endif; ?>

			<form class="auth-form" method="post" action="">
				<input type="hidden" name="vatan_auth_action" value="<?php echo esc_attr( VATAN_AUTH_LOGIN_ACTION ); ?>" />
				<?php wp_nonce_field( VATAN_AUTH_LOGIN_ACTION, VATAN_AUTH_LOGIN_NONCE ); ?>
				<?php if ( $redirect_to ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
				<?php endif; ?>

				<div class="auth-form__field">
					<label for="vatan-login-user"><?php esc_html_e( 'Email or username', 'vatan-event' ); ?></label>
					<input type="text" id="vatan-login-user" name="log" autocomplete="username"
					       value="<?php echo esc_attr( $username ); ?>" required autofocus />
				</div>

				<div class="auth-form__field">
					<div class="auth-form__label-row">
						<label for="vatan-login-pwd"><?php esc_html_e( 'Password', 'vatan-event' ); ?></label>
						<a class="auth-form__link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
							<?php esc_html_e( 'Forgot password?', 'vatan-event' ); ?>
						</a>
					</div>
					<input type="password" id="vatan-login-pwd" name="pwd" autocomplete="current-password" required />
				</div>

				<label class="auth-form__remember">
					<input type="checkbox" name="rememberme" value="1" checked />
					<span><?php esc_html_e( 'Keep me signed in', 'vatan-event' ); ?></span>
				</label>

				<button type="submit" class="btn btn--primary btn--lg btn--full">
					<?php esc_html_e( 'Sign in', 'vatan-event' ); ?>
				</button>
			</form>

			<?php if ( vatan_auth_should_show_register() ) : ?>
				<p class="auth-card__alt">
					<?php esc_html_e( 'New here?', 'vatan-event' ); ?>
					<a class="auth-form__link" href="<?php echo esc_url( vatan_auth_signup_url( $redirect_to ) ); ?>">
						<?php esc_html_e( 'Create an account', 'vatan-event' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</section>
	</div>
</main>

<?php get_footer();
