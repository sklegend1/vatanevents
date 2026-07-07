<?php
/**
 * Page Builder — admin screen.
 *
 * Renders a two-column editor:
 *   - Left: library of available components (clickable to insert).
 *   - Right: drag-reorderable canvas of placed blocks, each with an inline
 *     prop editor.
 *
 * The canvas is hydrated and serialised entirely by
 * `assets/admin/js/page-builder.js`. Just before form submit, the JS writes
 * the current blocks (as JSON) into a hidden field, which the PHP handler
 * decodes, sanitises via `vatan_sanitize_page_layout()`, and persists with
 * `vatan_save_page_layout()`.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Process a submitted layout. Runs early on the admin screen.
 */
function vatan_page_builder_handle_submit() {
	if ( empty( $_POST['vatan_pb_submit'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to save layouts.', 'vatan-event' ) );
	}
	check_admin_referer( 'vatan_save_page_builder', 'vatan_pb_nonce' );

	$page = isset( $_POST['vatan_pb_page'] ) ? sanitize_key( wp_unslash( $_POST['vatan_pb_page'] ) ) : 'homepage';
	if ( '' === $page ) {
		$page = 'homepage';
	}

	$raw    = isset( $_POST['vatan_pb_layout'] ) ? wp_unslash( $_POST['vatan_pb_layout'] ) : '[]';
	$layout = json_decode( $raw, true );
	if ( ! is_array( $layout ) ) {
		$layout = array();
	}

	$clean = vatan_sanitize_page_layout( $layout );
	vatan_save_page_layout( $page, $clean );

	add_settings_error(
		'vatan_page_builder',
		'vatan_pb_saved',
		__( 'Page layout saved.', 'vatan-event' ),
		'updated'
	);
	// Persist the message across the redirect-after-POST.
	set_transient( 'vatan_pb_notice_' . get_current_user_id(), get_settings_errors( 'vatan_page_builder' ), 30 );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'vatan-page-builder', 'updated' => '1' ),
		admin_url( 'admin.php' )
	) );
	exit;
}

/**
 * Render the Page Builder screen.
 */
function vatan_page_builder_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'vatan-event' ) );
	}

	$notice = get_transient( 'vatan_pb_notice_' . get_current_user_id() );
	if ( is_array( $notice ) ) {
		delete_transient( 'vatan_pb_notice_' . get_current_user_id() );
		foreach ( $notice as $n ) {
			add_settings_error(
				$n['setting'],
				$n['code'],
				$n['message'],
				isset( $n['type'] ) ? $n['type'] : 'updated'
			);
		}
	}

	$page_slug = isset( $_GET['layout'] ) ? sanitize_key( wp_unslash( $_GET['layout'] ) ) : 'homepage'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' === $page_slug ) {
		$page_slug = 'homepage';
	}

	$schema  = vatan_page_builder_schema_for_js();
	$initial = vatan_get_page_layout( $page_slug );

	// Inject the runtime id used by JS into each block (PHP doesn't store one
	// distinct from block 'id'; keep them equal so the JS can dedupe on it).
	foreach ( $initial as &$blk ) {
		if ( empty( $blk['id'] ) ) {
			$blk['id'] = wp_generate_uuid4();
		}
	}
	unset( $blk );
	?>
	<div class="wrap vatan-admin vatan-pb">
		<header class="vatan-admin__header">
			<h1><?php esc_html_e( 'Page Builder', 'vatan-event' ); ?></h1>
			<p class="vatan-admin__subtitle">
				<?php esc_html_e( 'Compose your homepage from drag-reorderable components. Each component exposes its own settings.', 'vatan-event' ); ?>
			</p>
		</header>

		<?php settings_errors( 'vatan_page_builder' ); ?>

		<form method="post" id="vatan-pb-form" class="vatan-pb__form">
			<?php wp_nonce_field( 'vatan_save_page_builder', 'vatan_pb_nonce' ); ?>
			<input type="hidden" name="vatan_pb_submit" value="1">
			<input type="hidden" name="vatan_pb_page" value="<?php echo esc_attr( $page_slug ); ?>">
			<input type="hidden" name="vatan_pb_layout" id="vatan-pb-layout" value="">

			<div class="vatan-pb__toolbar">
				<div class="vatan-pb__toolbar-info">
					<span class="vatan-pb__page-label">
						<?php
						printf(
							/* translators: %s: the slug of the page being edited */
							esc_html__( 'Editing layout: %s', 'vatan-event' ),
							'<code>' . esc_html( $page_slug ) . '</code>'
						);
						?>
					</span>
					<span class="vatan-pb__status" data-vatan-pb-status data-state="loaded">
						<?php
						$initial_count = count( $initial );
						if ( $initial_count > 0 ) {
							printf(
								/* translators: %d: number of saved components */
								esc_html( _n( '%d saved component loaded — edit and Save to keep changes.', '%d saved components loaded — edit and Save to keep changes.', $initial_count, 'vatan-event' ) ),
								(int) $initial_count
							);
						} else {
							esc_html_e( 'No layout saved yet. Add components below, then click Save.', 'vatan-event' );
						}
						?>
					</span>
				</div>
				<div class="vatan-pb__toolbar-actions">
					<button type="button" class="button" id="vatan-pb-reset">
						<?php esc_html_e( 'Reset to saved', 'vatan-event' ); ?>
					</button>
					<button type="submit" class="button button-primary" id="vatan-pb-save">
						<?php esc_html_e( 'Save layout', 'vatan-event' ); ?>
					</button>
				</div>
			</div>

			<div class="vatan-pb__columns">

				<aside class="vatan-pb__library" aria-label="<?php esc_attr_e( 'Component library', 'vatan-event' ); ?>">
					<h2 class="vatan-pb__col-title"><?php esc_html_e( 'Components', 'vatan-event' ); ?></h2>
					<ul class="vatan-pb__library-list" id="vatan-pb-library">
						<?php foreach ( $schema as $slug => $cmp ) : ?>
							<li class="vatan-pb__lib-item" data-type="<?php echo esc_attr( $slug ); ?>" tabindex="0" role="button" aria-label="<?php echo esc_attr( sprintf( __( 'Add %s', 'vatan-event' ), $cmp['label'] ) ); ?>">
								<span class="vatan-pb__lib-icon" aria-hidden="true"><?php echo esc_html( $cmp['icon'] ); ?></span>
								<span class="vatan-pb__lib-body">
									<span class="vatan-pb__lib-label"><?php echo esc_html( $cmp['label'] ); ?></span>
									<?php if ( $cmp['description'] ) : ?>
										<span class="vatan-pb__lib-desc"><?php echo esc_html( $cmp['description'] ); ?></span>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</aside>

				<section class="vatan-pb__canvas-wrap" aria-label="<?php esc_attr_e( 'Layout canvas', 'vatan-event' ); ?>">
					<h2 class="vatan-pb__col-title"><?php esc_html_e( 'Layout', 'vatan-event' ); ?></h2>

					<div class="vatan-pb__empty" id="vatan-pb-empty" hidden>
						<p>
							<?php esc_html_e( 'No components yet. Add one from the left to get started — drag, or click.', 'vatan-event' ); ?>
						</p>
					</div>

					<ul class="vatan-pb__canvas" id="vatan-pb-canvas">
						<!-- Blocks rendered by JS from window.vatanPageBuilder.initial -->
					</ul>
				</section>

			</div>
		</form>

		<!-- Block row template — cloned per block in JS. -->
		<template id="vatan-pb-block-tpl">
			<li class="vatan-pb__block" data-block-id="" data-type="">
				<header class="vatan-pb__block-head">
					<button type="button" class="vatan-pb__handle" title="<?php esc_attr_e( 'Drag to reorder', 'vatan-event' ); ?>" aria-label="<?php esc_attr_e( 'Drag handle', 'vatan-event' ); ?>">
						<span aria-hidden="true">⋮⋮</span>
					</button>
					<span class="vatan-pb__block-icon" aria-hidden="true"></span>
					<span class="vatan-pb__block-title"></span>
					<span class="vatan-pb__block-type"></span>
					<button type="button" class="vatan-pb__block-toggle" aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle settings', 'vatan-event' ); ?>">
						<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
					</button>
					<button type="button" class="vatan-pb__block-remove" aria-label="<?php esc_attr_e( 'Remove block', 'vatan-event' ); ?>" title="<?php esc_attr_e( 'Remove', 'vatan-event' ); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					</button>
				</header>
				<div class="vatan-pb__block-body" hidden>
					<div class="vatan-pb__fields"></div>
					<p class="vatan-pb__no-props" hidden>
						<?php esc_html_e( 'This component has no settings.', 'vatan-event' ); ?>
					</p>
				</div>
			</li>
		</template>

		<script type="application/json" id="vatan-pb-schema"><?php echo wp_json_encode( $schema ); ?></script>
		<script type="application/json" id="vatan-pb-initial"><?php echo wp_json_encode( array_values( $initial ) ); ?></script>
	</div>
	<?php
}
