<?php
/**
 * Event card.
 *
 * Designed to run inside The Loop. Reads ACF meta when available and
 * gracefully degrades when fields are unset (e.g. on a freshly seeded site
 * before authors fill in dates / venues / ticket tiers).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$event_id  = get_the_ID();
$permalink = get_permalink( $event_id );
$thumb     = get_the_post_thumbnail( $event_id, 'medium_large', array( 'class' => 'event-card__image' ) );

// Taxonomy terms — first of each.
$cat_terms  = wp_get_post_terms( $event_id, 'event_category', array( 'number' => 1 ) );
$category   = ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) ? $cat_terms[0] : null;
$city_terms = wp_get_post_terms( $event_id, 'event_city', array( 'number' => 1 ) );
$city       = ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) ? $city_terms[0] : null;

// ACF / meta — read through helpers when possible, raw post-meta otherwise.
$event_date = function_exists( 'get_field' )
	? (string) get_field( 'event_date', $event_id )
	: (string) get_post_meta( $event_id, 'event_date', true );
$event_time = function_exists( 'get_field' )
	? (string) get_field( 'event_time_start', $event_id )
	: (string) get_post_meta( $event_id, 'event_time_start', true );
$venue      = function_exists( 'get_field' )
	? (string) get_field( 'event_venue', $event_id )
	: (string) get_post_meta( $event_id, 'event_venue', true );

$days_left = vatan_event_days_left( $event_id );
$min_price = vatan_event_starting_price( $event_id );

// Build the date meta line (e.g. "Friday, starts at 21:00")
$date_meta = '';
if ( $event_date ) {
	$ts        = strtotime( $event_date );
	$weekday   = $ts ? date_i18n( 'l', $ts ) : '';
	$date_meta = $weekday;
	if ( $event_time ) {
		$display_time = vatan_to_persian_digits( $event_time );
		$date_meta .= ( $weekday ? '، ' : '' ) . sprintf(
			/* translators: %s: time of day in HH:MM, e.g. 21:00 */
			__( 'starts at %s', 'vatan-event' ),
			$display_time
		);
	}
}

$category_color = $category ? vatan_event_category_color( $category->slug ) : '#FF2D78';
?>

<article id="event-card-<?php echo esc_attr( $event_id ); ?>" <?php post_class( 'event-card' ); ?>>

	<div class="event-card__media">
		<a class="event-card__media-link" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( get_the_title( $event_id ) ); ?>">
			<?php
			// $thumb is already-escaped HTML from get_the_post_thumbnail.
			echo $thumb ? $thumb : '<span class="event-card__placeholder" aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</a>

		<?php if ( null !== $days_left ) : ?>
			<span class="event-card__days-left">
				<strong><?php echo esc_html( vatan_to_persian_digits( $days_left ) ); ?></strong>
				<small><?php esc_html_e( 'days', 'vatan-event' ); ?></small>
			</span>
		<?php endif; ?>

		<?php if ( $category ) : ?>
			<span class="event-card__category" style="--cat-color: <?php echo esc_attr( $category_color ); ?>;">
				<?php echo esc_html( $category->name ); ?>
			</span>
		<?php endif; ?>

		<button type="button" class="event-card__bookmark" aria-label="<?php esc_attr_e( 'Bookmark', 'vatan-event' ); ?>" data-event-id="<?php echo esc_attr( $event_id ); ?>">
			<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
			</svg>
		</button>

		<?php
		// View count badge — only rendered after the event has been seen at
		// least once, so we don't broadcast "0 views" on brand-new events.
		if ( function_exists( 'vatan_event_views_badge' ) ) {
			$badge = vatan_event_views_badge( $event_id );
			if ( $badge ) {
				echo '<span class="event-card__views">' . $badge . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		?>
	</div>

	<div class="event-card__body">
		<h3 class="event-card__title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php the_title(); ?></a>
		</h3>

		<?php if ( $city || $venue || $date_meta ) : ?>
			<div class="event-card__meta">
				<?php if ( $city || $venue ) : ?>
					<span class="event-card__meta-line">
						<span class="event-card__meta-icon" aria-hidden="true">📍</span>
						<?php
						$location_parts = array_filter( array(
							$city ? $city->name : '',
							$venue,
						) );
						echo esc_html( implode( ' — ', $location_parts ) );
						?>
					</span>
				<?php endif; ?>

				<?php if ( $date_meta ) : ?>
					<span class="event-card__meta-line">
						<span class="event-card__meta-icon" aria-hidden="true">📅</span>
						<?php echo esc_html( $date_meta ); ?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="event-card__footer">
			<?php if ( null !== $min_price ) : ?>
				<div class="event-card__price">
					<span class="event-card__price-label"><?php esc_html_e( 'Starting from', 'vatan-event' ); ?></span>
					<span class="event-card__price-amount"><?php echo esc_html( vatan_format_price( $min_price ) ); ?></span>
				</div>
			<?php else : ?>
				<span class="event-card__price-label"><?php esc_html_e( 'Tickets coming soon', 'vatan-event' ); ?></span>
			<?php endif; ?>

			<a class="btn btn--primary btn--sm" href="<?php echo esc_url( $permalink ); ?>">
				<?php esc_html_e( 'Buy ticket', 'vatan-event' ); ?>
			</a>
		</div>
	</div>
</article>
