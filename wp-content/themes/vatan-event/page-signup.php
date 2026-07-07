<?php
/**
 * Template Name: Vatan — Signup
 *
 * Sister to page-login.php. Posts to inc/auth.php's signup handler which
 * creates the user, logs them in, and redirects to My Account.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
	$dashboard = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'dashboard' )
		: home_url( '/' );
	wp_safe_redirect( $dashboard );
	exit;
}

if ( ! vatan_auth_should_show_register() ) {
	get_header();
	echo '<main class="site-main site-main--auth"><div class="container"><div class="auth-disabled"><h1>' . esc_html__( 'Registration is currently disabled.', 'vatan-event' ) . '</h1><p>' . esc_html__( 'Please contact the site administrator.', 'vatan-event' ) . '</p></div></div></main>';
	get_footer();
	return;
}

$error      = isset( $_GET['err'] ) ? sanitize_text_field( wp_unslash( $_GET['err'] ) ) : '';
$first_name = isset( $_GET['fn'] )  ? sanitize_text_field( wp_unslash( $_GET['fn'] ) )  : '';
$last_name  = isset( $_GET['ln'] )  ? sanitize_text_field( wp_unslash( $_GET['ln'] ) )  : '';
$email      = isset( $_GET['e'] )   ? sanitize_email( wp_unslash( $_GET['e'] ) )        : '';
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

$splash_url = '';
$state      = (array) get_option( 'vatan_media_seeded', array() );
foreach ( $state['attachments'] ?? array() as $key => $id ) {
	if ( str_ends_with( $key, ':hero-festival-night' ) ) {
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
				<h2 class="auth-splash__title">به جامعهٔ ما بپیوند</h2>
				<p class="auth-splash__lead">با ساخت حساب کاربری: بلیت رزرو کن، اعلان رویدادها بگیر، و حتی رویداد خودت را ثبت کن.</p>
				<ul class="auth-splash__bullets">
					<li>🎫 خرید بلیت در چند ثانیه</li>
					<li>📣 اعلان رویدادهای جدید در شهر تو</li>
					<li>🎤 ثبت و فروش رویداد خودت</li>
				</ul>
			</div>
		</aside>

		<section class="auth-card">
			<header class="auth-card__head">
				<a class="auth-card__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<span class="auth-card__brand-icon" aria-hidden="true">V</span>
					<span class="auth-card__brand-text"><?php bloginfo( 'name' ); ?></span>
				</a>
				<h1 class="auth-card__title"><?php esc_html_e( 'Create your account', 'vatan-event' ); ?></h1>
				<p class="auth-card__lead"><?php esc_html_e( 'Takes less than a minute. No credit card required.', 'vatan-event' ); ?></p>
			</header>

			<?php if ( $error ) : ?>
				<div class="auth-card__error" role="alert">
					<?php echo esc_html( $error ); ?>
				</div>
			<?php endif; ?>

			<form class="auth-form" method="post" action="">
				<input type="hidden" name="vatan_auth_action" value="<?php echo esc_attr( VATAN_AUTH_SIGNUP_ACTION ); ?>" />
				<?php wp_nonce_field( VATAN_AUTH_SIGNUP_ACTION, VATAN_AUTH_SIGNUP_NONCE ); ?>
				<?php if ( $redirect_to ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
				<?php endif; ?>

				<div class="auth-form__row">
					<div class="auth-form__field">
						<label for="vatan-su-first"><?php esc_html_e( 'First name', 'vatan-event' ); ?></label>
						<input type="text" id="vatan-su-first" name="first_name" autocomplete="given-name"
						       value="<?php echo esc_attr( $first_name ); ?>" required />
					</div>
					<div class="auth-form__field">
						<label for="vatan-su-last"><?php esc_html_e( 'Last name', 'vatan-event' ); ?></label>
						<input type="text" id="vatan-su-last" name="last_name" autocomplete="family-name"
						       value="<?php echo esc_attr( $last_name ); ?>" required />
					</div>
				</div>

				<div class="auth-form__field">
					<label for="vatan-su-email"><?php esc_html_e( 'Email', 'vatan-event' ); ?></label>
					<input type="email" id="vatan-su-email" name="email" autocomplete="email"
					       value="<?php echo esc_attr( $email ); ?>" required />
				</div>

				<div class="auth-form__field">
					<label for="vatan-su-pwd"><?php esc_html_e( 'Password', 'vatan-event' ); ?></label>
					<input type="password" id="vatan-su-pwd" name="password" autocomplete="new-password" minlength="8" required />
					<small class="auth-form__hint"><?php esc_html_e( 'At least 8 characters.', 'vatan-event' ); ?></small>
				</div>

				<div class="auth-form__field">
					<label for="vatan-su-pwd2"><?php esc_html_e( 'Confirm password', 'vatan-event' ); ?></label>
					<input type="password" id="vatan-su-pwd2" name="password_confirm" autocomplete="new-password" minlength="8" required />
				</div>

				<?php // Honeypot — visually hidden, ignored by humans, filled by bots. ?>
				<label class="auth-form__honeypot" aria-hidden="true">
					<span><?php esc_html_e( 'Leave this field empty', 'vatan-event' ); ?></span>
					<input type="text" name="website" tabindex="-1" autocomplete="off" />
				</label>

				<label class="auth-form__tos">
					<input type="checkbox" name="tos" value="1" required />
					<span>
						<?php
						printf(
							/* translators: %1$s: terms URL, %2$s: privacy URL. */
							esc_html__( 'I agree to the %1$sterms%2$s and %3$sprivacy policy%4$s.', 'vatan-event' ),
							'<a href="' . esc_url( vatan_static_page_url( 'terms' ) ?: home_url( '/terms/' ) ) . '" target="_blank">',
							'</a>',
							'<a href="' . esc_url( vatan_static_page_url( 'privacy' ) ?: home_url( '/privacy/' ) ) . '" target="_blank">',
							'</a>'
						);
						?>
					</span>
				</label>

				<button type="submit" class="btn btn--primary btn--lg btn--full">
					<?php esc_html_e( 'Create account', 'vatan-event' ); ?>
				</button>
			</form>

			<p class="auth-card__alt">
				<?php esc_html_e( 'Already have an account?', 'vatan-event' ); ?>
				<a class="auth-form__link" href="<?php echo esc_url( vatan_auth_login_url( $redirect_to ) ); ?>">
					<?php esc_html_e( 'Sign in', 'vatan-event' ); ?>
				</a>
			</p>
		</section>
	</div>
</main>

<?php get_footer();
