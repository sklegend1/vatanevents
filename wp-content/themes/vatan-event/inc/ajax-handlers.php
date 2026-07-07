<?php
/**
 * AJAX handlers.
 *
 * Action callbacks for `wp_ajax_*` and `wp_ajax_nopriv_*` are registered here
 * in upcoming prompts (search, seat hold, newsletter signup, …).
 * Each handler must verify a nonce via `check_ajax_referer( 'vatan_nonce' )`
 * and capability where applicable.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

// Handlers registered in later prompts.
