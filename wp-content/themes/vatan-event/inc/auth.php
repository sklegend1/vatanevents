<?php
/**
 * Custom frontend login + signup.
 *
 * Replaces the wp-login.php / wc-account-login flow with branded pages
 * served from /login/ and /signup/ (registered as static pages). Forms
 * POST to a single early-firing handler (`init` priority 1) so we can
 * issue redirects before WordPress emits any output.
 *
 * Public functions:
 *   vatan_auth_login_url( $redirect = '' )
 *   vatan_auth_signup_url( $redirect = '' )
 *   vatan_auth_should_show_register()
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

const VATAN_AUTH_LOGIN_ACTION  = 'vatan_login';
const VATAN_AUTH_SIGNUP_ACTION = 'vatan_signup';
const VATAN_AUTH_LOGIN_NONCE   = 'vatan_login_nonce';
const VATAN_AUTH_SIGNUP_NONCE  = 'vatan_signup_nonce';

/* =============================================================================
 *  URL helpers
 * ===========================================================================*/

function vatan_auth_login_url( string $redirect = '' ): string {
	$url = vatan_static_page_url( 'login' ) ?: home_url( '/login/' );
	if ( $redirect ) {
		$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
	}
	return $url;
}

function vatan_auth_signup_url( string $redirect = '' ): string {
	$url = vatan_static_page_url( 'signup' ) ?: home_url( '/signup/' );
	if ( $redirect ) {
		$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
	}
	return $url;
}

function vatan_auth_should_show_register(): bool {
	return (bool) get_option( 'users_can_register' );
}

/* Reroute the standard WP login / register links to our pages. */
add_filter( 'login_url', function ( $url, $redirect ) {
	// Don't interfere with admin login flows (e.g. wp_login_url called from
	// inside wp-admin). Front-end requests use the custom page.
	if ( is_admin() ) {
		return $url;
	}
	return vatan_auth_login_url( (string) $redirect );
}, 10, 2 );

add_filter( 'register_url', function ( $url ) {
	return vatan_auth_signup_url();
}, 10, 1 );

/* =============================================================================
 *  POST handlers — fire on `init` priority 1 so we can redirect cleanly.
 * ===========================================================================*/

add_action( 'init', 'vatan_auth_handle_submissions', 1 );

function vatan_auth_handle_submissions(): void {
	if ( empty( $_POST['vatan_auth_action'] ) ) {
		return;
	}
	$action = sanitize_key( wp_unslash( $_POST['vatan_auth_action'] ) );

	if ( VATAN_AUTH_LOGIN_ACTION === $action ) {
		vatan_auth_process_login();
	} elseif ( VATAN_AUTH_SIGNUP_ACTION === $action ) {
		vatan_auth_process_signup();
	}
}

function vatan_auth_process_login(): void {
	if ( ! isset( $_POST[ VATAN_AUTH_LOGIN_NONCE ] ) ||
	     ! wp_verify_nonce( wp_unslash( $_POST[ VATAN_AUTH_LOGIN_NONCE ] ), VATAN_AUTH_LOGIN_ACTION ) ) {
		vatan_auth_redirect_with_error( 'login', __( 'Security check failed. Please try again.', 'vatan-event' ) );
	}

	$creds = array(
		'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ?? '' ) ),
		'user_password' => isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '', // raw — wp_signon handles it
		'remember'      => ! empty( $_POST['rememberme'] ),
	);

	if ( '' === $creds['user_login'] || '' === $creds['user_password'] ) {
		vatan_auth_redirect_with_error( 'login', __( 'Please enter both a username/email and a password.', 'vatan-event' ) );
	}

	$user = wp_signon( $creds, is_ssl() );
	if ( is_wp_error( $user ) ) {
		// Strip HTML from WP's default error messages so we control rendering.
		$msg = wp_strip_all_tags( $user->get_error_message() );
		vatan_auth_redirect_with_error( 'login', $msg );
	}

	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	// `vatan_login_redirect` is filtered by inc/admin-dashboard.php so users
	// with admin caps land on /admin/ instead of WC's My Account.
	$redirect = (string) apply_filters( 'vatan_login_redirect', $redirect );
	if ( ! $redirect ) {
		$redirect = function_exists( 'wc_get_account_endpoint_url' )
			? wc_get_account_endpoint_url( 'dashboard' )
			: home_url( '/' );
	}
	wp_safe_redirect( $redirect );
	exit;
}

function vatan_auth_process_signup(): void {
	if ( ! isset( $_POST[ VATAN_AUTH_SIGNUP_NONCE ] ) ||
	     ! wp_verify_nonce( wp_unslash( $_POST[ VATAN_AUTH_SIGNUP_NONCE ] ), VATAN_AUTH_SIGNUP_ACTION ) ) {
		vatan_auth_redirect_with_error( 'signup', __( 'Security check failed. Please try again.', 'vatan-event' ) );
	}

	if ( ! vatan_auth_should_show_register() ) {
		vatan_auth_redirect_with_error( 'signup', __( 'Registration is currently disabled.', 'vatan-event' ) );
	}

	// Honeypot — bots fill the hidden field that humans never see.
	if ( ! empty( $_POST['website'] ) ) {
		vatan_auth_redirect_with_error( 'signup', __( 'Something went wrong. Please try again.', 'vatan-event' ) );
	}

	$first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
	$last  = sanitize_text_field( wp_unslash( $_POST['last_name']  ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$pwd   = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
	$pwd2  = isset( $_POST['password_confirm'] ) ? wp_unslash( $_POST['password_confirm'] ) : '';
	$tos   = ! empty( $_POST['tos'] );

	if ( ! $email || ! is_email( $email ) ) {
		vatan_auth_redirect_with_error( 'signup', __( 'Please enter a valid email address.', 'vatan-event' ) );
	}
	if ( email_exists( $email ) ) {
		vatan_auth_redirect_with_error( 'signup', __( 'An account with this email already exists.', 'vatan-event' ) );
	}
	if ( strlen( $pwd ) < 8 ) {
		vatan_auth_redirect_with_error( 'signup', __( 'Password must be at least 8 characters.', 'vatan-event' ) );
	}
	if ( $pwd !== $pwd2 ) {
		vatan_auth_redirect_with_error( 'signup', __( 'Passwords do not match.', 'vatan-event' ) );
	}
	if ( ! $tos ) {
		vatan_auth_redirect_with_error( 'signup', __( 'Please accept the terms to continue.', 'vatan-event' ) );
	}

	// Derive a unique username from the email local-part.
	$base     = sanitize_user( current( explode( '@', $email ) ), true );
	$username = $base;
	$i        = 1;
	while ( username_exists( $username ) ) {
		$username = $base . $i++;
	}

	$user_id = wp_create_user( $username, $pwd, $email );
	if ( is_wp_error( $user_id ) ) {
		vatan_auth_redirect_with_error( 'signup', wp_strip_all_tags( $user_id->get_error_message() ) );
	}

	wp_update_user( array(
		'ID'           => $user_id,
		'first_name'   => $first,
		'last_name'    => $last,
		'display_name' => trim( $first . ' ' . $last ) ?: $username,
	) );

	// Auto-login after signup.
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	$redirect = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'dashboard' )
		: home_url( '/' );
	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Bounce back to the form with an error message in the URL — read on the
 * page by the template. Never exposes the password.
 */
function vatan_auth_redirect_with_error( string $page, string $message ): void {
	$base = ( 'login' === $page ) ? vatan_auth_login_url() : vatan_auth_signup_url();
	// Preserve fields the user already filled in (everything except password).
	$preserve = array();
	if ( ! empty( $_POST['log'] ) ) {
		$preserve['u'] = sanitize_text_field( wp_unslash( $_POST['log'] ) );
	}
	if ( ! empty( $_POST['email'] ) ) {
		$preserve['e'] = sanitize_email( wp_unslash( $_POST['email'] ) );
	}
	if ( ! empty( $_POST['first_name'] ) ) {
		$preserve['fn'] = sanitize_text_field( wp_unslash( $_POST['first_name'] ) );
	}
	if ( ! empty( $_POST['last_name'] ) ) {
		$preserve['ln'] = sanitize_text_field( wp_unslash( $_POST['last_name'] ) );
	}
	if ( ! empty( $_POST['redirect_to'] ) ) {
		$preserve['redirect_to'] = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
	}
	$preserve['err'] = $message;

	wp_safe_redirect( add_query_arg( $preserve, $base ) );
	exit;
}
