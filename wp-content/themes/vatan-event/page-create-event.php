<?php
/**
 * Page template: Create Event (organizer-facing form).
 *
 * Matches the static-pages seeder slug `create-event`. WP picks this up
 * automatically via the `page-{slug}.php` template hierarchy.
 *
 * The submission is handled in inc/create-event.php on template_redirect
 * — by the time we render, the form has either already been processed
 * (and we show a notice in the URL) or is fresh.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();

$logged_in = is_user_logged_in();
$can       = function_exists( 'vatan_can_submit_event' ) && vatan_can_submit_event();
$notice    = function_exists( 'vatan_create_event_get_notice' ) ? vatan_create_event_get_notice() : null;
$error_fields = ( $notice && 'error' === $notice['status'] && ! empty( $notice['fields'] ) ) ? $notice['fields'] : array();

// Edit mode — when `?edit=ID` is in the URL and the user owns that event,
// pre-fill the form and switch copy. Otherwise behave as a brand-new create.
$edit_id      = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$is_edit_mode = false;
$existing     = array();
$edit_blocked = false;
if ( $edit_id && $logged_in ) {
	if ( function_exists( 'vatan_create_event_user_can_edit' ) && vatan_create_event_user_can_edit( $edit_id ) ) {
		$existing     = vatan_create_event_load_existing( $edit_id );
		$is_edit_mode = ! empty( $existing );
	} else {
		$edit_blocked = true;
	}
}

// Field defaults (used in inputs' `value=` so we don't repeat ?: everywhere).
$default = array(
	'title'      => $is_edit_mode ? (string) $existing['title']      : '',
	'excerpt'    => $is_edit_mode ? (string) $existing['excerpt']    : '',
	'content'    => $is_edit_mode ? (string) $existing['content']    : '',
	'date'       => $is_edit_mode ? (string) $existing['date']       : '',
	'time_start' => $is_edit_mode ? (string) $existing['time_start'] : '',
	'time_end'   => $is_edit_mode ? (string) $existing['time_end']   : '',
	'venue'      => $is_edit_mode ? (string) $existing['venue']      : '',
	'venue_map'  => $is_edit_mode ? (string) $existing['venue_map']  : '',
	'duration'   => $is_edit_mode ? (int)    $existing['duration']   : 0,
	'age_limit'  => $is_edit_mode ? (int)    $existing['age_limit']  : 0,
	'category'   => $is_edit_mode ? (int)    $existing['category']   : 0,
	'city'       => $is_edit_mode ? (int)    $existing['city']       : 0,
	'tickets'    => $is_edit_mode ? (array)  $existing['tickets']    : array(),
	'thumb_id'   => $is_edit_mode ? (int)    $existing['thumbnail']  : 0,
);

// Taxonomy pickers — we hand-roll the selects so we control their classes.
$categories = get_terms( array(
	'taxonomy'   => 'event_category',
	'hide_empty' => false,
	'orderby'    => 'name',
) );
$cities = get_terms( array(
	'taxonomy'   => 'event_city',
	'hide_empty' => false,
	'orderby'    => 'name',
) );
$today_min = current_time( 'Y-m-d' );
?>

<main class="site-main site-main--create-event">
	<div class="container create-event">

		<header class="create-event__header">
			<h1 class="create-event__title">
				<?php echo esc_html( $is_edit_mode ? __( 'Edit your event', 'vatan-event' ) : __( 'Create your event', 'vatan-event' ) ); ?>
			</h1>
			<p class="create-event__lead">
				<?php
				if ( $is_edit_mode ) {
					esc_html_e( 'Update the details below. Changes save immediately — published events stay live, pending events stay in the queue.', 'vatan-event' );
				} else {
					esc_html_e( 'Tell us about your event. Once approved by our team, it goes live on the platform and you start selling tickets.', 'vatan-event' );
				}
				?>
			</p>
		</header>

		<?php if ( $edit_blocked ) : ?>
			<div class="create-event__notice create-event__notice--error" role="status" aria-live="polite">
				<?php esc_html_e( 'You can\'t edit that event — it doesn\'t belong to you, or the link is no longer valid.', 'vatan-event' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $notice ) : ?>
			<div class="create-event__notice create-event__notice--<?php echo esc_attr( $notice['status'] ); ?>" role="status" aria-live="polite">
				<?php echo esc_html( $notice['message'] ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! $logged_in ) : ?>
			<div class="create-event__gate">
				<p><?php esc_html_e( 'You need an account to submit an event.', 'vatan-event' ); ?></p>
				<p>
					<a class="btn btn--primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
						<?php esc_html_e( 'Log in', 'vatan-event' ); ?>
					</a>
					<a class="btn btn--ghost" href="<?php echo esc_url( wp_registration_url() ); ?>">
						<?php esc_html_e( 'Create an account', 'vatan-event' ); ?>
					</a>
				</p>
			</div>
		<?php elseif ( ! $can ) : ?>
			<div class="create-event__gate">
				<p><?php esc_html_e( 'Your account isn\'t enabled for event submissions yet. Contact the admin to request access.', 'vatan-event' ); ?></p>
			</div>
		<?php else : ?>

			<form class="create-event__form" method="post" enctype="multipart/form-data" novalidate>
				<?php wp_nonce_field( VATAN_CREATE_EVENT_ACTION, VATAN_CREATE_EVENT_NONCE ); ?>
				<input type="hidden" name="vatan_create_event_action" value="<?php echo esc_attr( VATAN_CREATE_EVENT_ACTION ); ?>">
				<?php if ( $is_edit_mode ) : ?>
					<input type="hidden" name="event_id" value="<?php echo esc_attr( $edit_id ); ?>">
				<?php endif; ?>

				<!-- ── Section 1: Basics ── -->
				<section class="create-event__section">
					<h2 class="create-event__section-title"><?php esc_html_e( 'About the event', 'vatan-event' ); ?></h2>

					<label class="create-event__field create-event__field--full <?php echo isset( $error_fields['event_title'] ) ? 'has-error' : ''; ?>">
						<span class="create-event__label"><?php esc_html_e( 'Event title', 'vatan-event' ); ?> <em>*</em></span>
						<input type="text" name="event_title" value="<?php echo esc_attr( $default['title'] ); ?>" required minlength="4" maxlength="160" placeholder="<?php esc_attr_e( 'e.g. Mohsen Yeganeh — Live in Tehran', 'vatan-event' ); ?>" />
					</label>

					<label class="create-event__field create-event__field--full">
						<span class="create-event__label"><?php esc_html_e( 'Short summary', 'vatan-event' ); ?></span>
						<textarea name="event_excerpt" rows="2" maxlength="280" placeholder="<?php esc_attr_e( 'One sentence that will show on event cards and previews.', 'vatan-event' ); ?>"><?php echo esc_textarea( $default['excerpt'] ); ?></textarea>
					</label>

					<label class="create-event__field create-event__field--full">
						<span class="create-event__label"><?php esc_html_e( 'Full description', 'vatan-event' ); ?></span>
						<textarea name="event_content" rows="8" placeholder="<?php esc_attr_e( 'Lineup, what to expect, dress code, any logistical notes…', 'vatan-event' ); ?>"><?php echo esc_textarea( $default['content'] ); ?></textarea>
					</label>

					<label class="create-event__field create-event__field--full">
						<span class="create-event__label"><?php esc_html_e( 'Cover image', 'vatan-event' ); ?></span>
						<input type="file" name="event_image" accept="image/jpeg,image/png,image/webp,image/gif" data-vatan-image-input />
						<small class="create-event__hint">
							<?php
							if ( $is_edit_mode && $default['thumb_id'] ) {
								esc_html_e( 'Leave empty to keep the current image. Upload a new file to replace it.', 'vatan-event' );
							} else {
								esc_html_e( 'JPEG / PNG / WebP. Wide images work best (16:10).', 'vatan-event' );
							}
							?>
						</small>
						<?php
						$current_thumb = $is_edit_mode && $default['thumb_id'] ? wp_get_attachment_image_url( $default['thumb_id'], 'medium' ) : '';
						if ( $current_thumb ) : ?>
							<div class="create-event__image-preview" data-vatan-image-preview>
								<img src="<?php echo esc_url( $current_thumb ); ?>" alt="" />
							</div>
						<?php else : ?>
							<div class="create-event__image-preview" data-vatan-image-preview hidden></div>
						<?php endif; ?>
					</label>
				</section>

				<!-- ── Section 2: When & where ── -->
				<section class="create-event__section">
					<h2 class="create-event__section-title"><?php esc_html_e( 'When & where', 'vatan-event' ); ?></h2>

					<label class="create-event__field <?php echo ( isset( $error_fields['event_date'] ) ) ? 'has-error' : ''; ?>">
						<span class="create-event__label"><?php esc_html_e( 'Date', 'vatan-event' ); ?> <em>*</em></span>
						<?php
						// In edit mode, allow the existing past date through HTML5 validation
						// (organizers may be editing details on a past event); for new events
						// we still enforce today-or-later.
						$date_min = $is_edit_mode ? '' : $today_min;
						?>
						<input type="date" name="event_date" value="<?php echo esc_attr( $default['date'] ); ?>" required <?php if ( $date_min ) : ?>min="<?php echo esc_attr( $date_min ); ?>"<?php endif; ?> />
					</label>

					<label class="create-event__field">
						<span class="create-event__label"><?php esc_html_e( 'Start time', 'vatan-event' ); ?></span>
						<input type="time" name="event_time_start" value="<?php echo esc_attr( $default['time_start'] ); ?>" />
					</label>

					<label class="create-event__field">
						<span class="create-event__label"><?php esc_html_e( 'End time', 'vatan-event' ); ?></span>
						<input type="time" name="event_time_end" value="<?php echo esc_attr( $default['time_end'] ); ?>" />
					</label>

					<label class="create-event__field <?php echo isset( $error_fields['event_city'] ) ? 'has-error' : ''; ?>">
						<span class="create-event__label"><?php esc_html_e( 'City', 'vatan-event' ); ?> <em>*</em></span>
						<select name="event_city" required>
							<option value=""><?php esc_html_e( '— Select a city —', 'vatan-event' ); ?></option>
							<?php foreach ( $cities as $city ) :
								// Pad nested cities (children of a country) with an indent
								// so admins see the hierarchy.
								$indent = $city->parent ? '— ' : '';
								?>
								<option value="<?php echo esc_attr( $city->term_id ); ?>" <?php selected( $default['city'], (int) $city->term_id ); ?>>
									<?php echo esc_html( $indent . $city->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="create-event__field <?php echo isset( $error_fields['event_category'] ) ? 'has-error' : ''; ?>">
						<span class="create-event__label"><?php esc_html_e( 'Category', 'vatan-event' ); ?> <em>*</em></span>
						<select name="event_category" required>
							<option value=""><?php esc_html_e( '— Select a category —', 'vatan-event' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $default['category'], (int) $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="create-event__field create-event__field--full">
						<span class="create-event__label"><?php esc_html_e( 'Venue name', 'vatan-event' ); ?></span>
						<input type="text" name="event_venue" value="<?php echo esc_attr( $default['venue'] ); ?>" maxlength="160" placeholder="<?php esc_attr_e( 'e.g. Vahdat Hall, Tehran', 'vatan-event' ); ?>" />
					</label>

					<label class="create-event__field create-event__field--full">
						<span class="create-event__label"><?php esc_html_e( 'Map link (optional)', 'vatan-event' ); ?></span>
						<input type="url" name="event_venue_map_link" value="<?php echo esc_attr( $default['venue_map'] ); ?>" placeholder="https://maps.google.com/…" />
					</label>

					<label class="create-event__field">
						<span class="create-event__label"><?php esc_html_e( 'Duration (minutes)', 'vatan-event' ); ?></span>
						<input type="number" name="event_duration" value="<?php echo $default['duration'] > 0 ? esc_attr( $default['duration'] ) : ''; ?>" min="0" step="5" placeholder="120" />
					</label>

					<label class="create-event__field">
						<span class="create-event__label"><?php esc_html_e( 'Age limit', 'vatan-event' ); ?></span>
						<input type="number" name="event_age_limit" value="<?php echo $default['age_limit'] > 0 ? esc_attr( $default['age_limit'] ) : ''; ?>" min="0" max="99" placeholder="0" />
					</label>
				</section>

				<!-- ── Section 3: Tickets ── -->
				<section class="create-event__section">
					<header class="create-event__section-head">
						<h2 class="create-event__section-title"><?php esc_html_e( 'Ticket types', 'vatan-event' ); ?></h2>
						<button type="button" class="btn btn--ghost btn--sm" data-vatan-ticket-add>
							<?php esc_html_e( '+ Add ticket type', 'vatan-event' ); ?>
						</button>
					</header>
					<p class="create-event__section-hint <?php echo isset( $error_fields['tickets'] ) ? 'has-error' : ''; ?>">
						<?php esc_html_e( 'Add at least one ticket type. Capacity is optional — leave 0 to let the admin set it later.', 'vatan-event' ); ?>
					</p>

					<div class="create-event__tickets" data-vatan-tickets>
						<?php
						$rows = $is_edit_mode && ! empty( $default['tickets'] ) ? $default['tickets'] : array( array() );
						foreach ( $rows as $i => $row ) :
							$row_name  = isset( $row['ticket_name'] )     ? (string) $row['ticket_name']     : '';
							$row_price = isset( $row['ticket_price'] )    ? (float)  $row['ticket_price']    : 0;
							$row_cap   = isset( $row['ticket_capacity'] ) ? (int)    $row['ticket_capacity'] : 0;
							$row_color = isset( $row['ticket_color'] )    ? (string) $row['ticket_color']    : '#7C3AED';
							?>
							<div class="create-event__ticket-row" data-vatan-ticket-row>
								<label class="create-event__field">
									<span class="create-event__label"><?php esc_html_e( 'Name', 'vatan-event' ); ?></span>
									<input type="text" name="ticket_types[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $row_name ); ?>" placeholder="<?php esc_attr_e( 'VIP', 'vatan-event' ); ?>" maxlength="80" />
								</label>
								<label class="create-event__field">
									<span class="create-event__label"><?php esc_html_e( 'Price', 'vatan-event' ); ?></span>
									<input type="number" name="ticket_types[<?php echo (int) $i; ?>][price]" value="<?php echo $row_price > 0 ? esc_attr( $row_price ) : ''; ?>" min="0" step="1000" placeholder="0" />
								</label>
								<label class="create-event__field">
									<span class="create-event__label"><?php esc_html_e( 'Capacity', 'vatan-event' ); ?></span>
									<input type="number" name="ticket_types[<?php echo (int) $i; ?>][capacity]" value="<?php echo $row_cap > 0 ? esc_attr( $row_cap ) : ''; ?>" min="0" step="1" placeholder="0" />
								</label>
								<label class="create-event__field create-event__field--color">
									<span class="create-event__label"><?php esc_html_e( 'Colour', 'vatan-event' ); ?></span>
									<input type="color" name="ticket_types[<?php echo (int) $i; ?>][color]" value="<?php echo esc_attr( $row_color ?: '#7C3AED' ); ?>" />
								</label>
								<button type="button" class="create-event__ticket-remove" data-vatan-ticket-remove aria-label="<?php esc_attr_e( 'Remove ticket type', 'vatan-event' ); ?>">×</button>
							</div>
						<?php endforeach; ?>
					</div>

					<!-- Hidden template (cloned by JS) -->
					<template data-vatan-ticket-template>
						<div class="create-event__ticket-row" data-vatan-ticket-row>
							<label class="create-event__field">
								<span class="create-event__label"><?php esc_html_e( 'Name', 'vatan-event' ); ?></span>
								<input type="text" name="ticket_types[__INDEX__][name]" maxlength="80" />
							</label>
							<label class="create-event__field">
								<span class="create-event__label"><?php esc_html_e( 'Price', 'vatan-event' ); ?></span>
								<input type="number" name="ticket_types[__INDEX__][price]" min="0" step="1000" placeholder="0" />
							</label>
							<label class="create-event__field">
								<span class="create-event__label"><?php esc_html_e( 'Capacity', 'vatan-event' ); ?></span>
								<input type="number" name="ticket_types[__INDEX__][capacity]" min="0" step="1" placeholder="0" />
							</label>
							<label class="create-event__field create-event__field--color">
								<span class="create-event__label"><?php esc_html_e( 'Colour', 'vatan-event' ); ?></span>
								<input type="color" name="ticket_types[__INDEX__][color]" value="#7C3AED" />
							</label>
							<button type="button" class="create-event__ticket-remove" data-vatan-ticket-remove aria-label="<?php esc_attr_e( 'Remove ticket type', 'vatan-event' ); ?>">×</button>
						</div>
					</template>
				</section>

				<!-- ── Section 4: Seat map (optional) ── -->
				<?php
				if ( function_exists( 'vatan_render_seat_planner_field' ) ) {
					vatan_render_seat_planner_field( $default );
				}
				?>

				<!-- ── Submit ── -->
				<div class="create-event__submit">
					<button type="submit" class="btn btn--primary btn--lg">
						<?php echo esc_html( $is_edit_mode ? __( 'Save changes', 'vatan-event' ) : __( 'Submit for review', 'vatan-event' ) ); ?>
					</button>
					<?php if ( ! $is_edit_mode ) : ?>
						<p class="create-event__legalese">
							<?php esc_html_e( 'By submitting you confirm the information is accurate and you have rights to organise this event.', 'vatan-event' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</form>

		<?php endif; ?>

	</div>
</main>

<?php
get_footer();
