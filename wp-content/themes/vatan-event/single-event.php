<?php
/**
 * Single event template.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$event_id  = get_the_ID();
	$thumb_url = get_the_post_thumbnail_url( $event_id, 'full' );

	// Taxonomy — first term of each.
	$cat_terms  = wp_get_post_terms( $event_id, 'event_category', array( 'number' => 1 ) );
	$category   = ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) ? $cat_terms[0] : null;
	$cat_color  = $category ? vatan_event_category_color( $category->slug ) : '#FF2D78';
	$city_terms = wp_get_post_terms( $event_id, 'event_city', array( 'number' => 1 ) );
	$city       = ( ! is_wp_error( $city_terms ) && ! empty( $city_terms ) ) ? $city_terms[0] : null;

	// ACF / meta — guarded so the page still renders without ACF.
	$event_date       = function_exists( 'get_field' ) ? (string) get_field( 'event_date', $event_id )       : (string) get_post_meta( $event_id, 'event_date', true );
	$event_time_start = function_exists( 'get_field' ) ? (string) get_field( 'event_time_start', $event_id ) : (string) get_post_meta( $event_id, 'event_time_start', true );
	$event_time_end   = function_exists( 'get_field' ) ? (string) get_field( 'event_time_end', $event_id )   : (string) get_post_meta( $event_id, 'event_time_end', true );
	$event_venue      = function_exists( 'get_field' ) ? (string) get_field( 'event_venue', $event_id )      : (string) get_post_meta( $event_id, 'event_venue', true );
	$event_duration   = function_exists( 'get_field' ) ? (int) get_field( 'event_duration', $event_id )      : 0;
	$event_age_limit  = function_exists( 'get_field' ) ? (int) get_field( 'event_age_limit', $event_id )     : 0;
	$event_status     = function_exists( 'get_field' ) ? (string) get_field( 'event_status', $event_id )     : 'upcoming';
	$tickets          = function_exists( 'get_field' ) ? get_field( 'ticket_types', $event_id )              : null;
	$has_seat_map     = function_exists( 'get_field' ) && (bool) get_field( 'seat_map_enabled', $event_id );

	$status_meta = vatan_event_status_meta( $event_status ?: 'upcoming' );

	// Rating — placeholder until comments/reviews are wired up.
	$rating = 4.8;

	// Display strings.
	$date_display = vatan_event_date_display( $event_date );
	$time_range   = '';
	if ( $event_time_start && $event_time_end ) {
		$time_range = sprintf(
			/* translators: 1: start time HH:MM, 2: end time HH:MM */
			__( '%1$s to %2$s', 'vatan-event' ),
			vatan_to_persian_digits( $event_time_start ),
			vatan_to_persian_digits( $event_time_end )
		);
	} elseif ( $event_time_start ) {
		$time_range = vatan_to_persian_digits( $event_time_start );
	}

	// Breadcrumb labels & URLs.
	$archive_url   = get_post_type_archive_link( 'event' );
	$archive_url   = is_string( $archive_url ) ? $archive_url : home_url( '/' );
	$archive_label = post_type_archive_title( '', false );
	if ( ! $archive_label ) {
		$archive_label = __( 'Events', 'vatan-event' );
	}
	?>

	<main class="site-main site-main--single-event">

		<!-- Breadcrumb -->
		<nav class="container breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'vatan-event' ); ?>">
			<ol class="breadcrumb__list">
				<li class="breadcrumb__item">
					<a class="breadcrumb__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
						<?php esc_html_e( 'Home', 'vatan-event' ); ?>
					</a>
				</li>
				<li class="breadcrumb__sep" aria-hidden="true">›</li>
				<li class="breadcrumb__item">
					<a class="breadcrumb__link" href="<?php echo esc_url( $archive_url ); ?>">
						<?php echo esc_html( $archive_label ); ?>
					</a>
				</li>
				<li class="breadcrumb__sep" aria-hidden="true">›</li>
				<li class="breadcrumb__item breadcrumb__item--current" aria-current="page">
					<?php the_title(); ?>
				</li>
			</ol>
		</nav>

		<!-- Hero -->
		<section class="container event-hero">
			<?php if ( $thumb_url ) : ?>
				<img class="event-hero-bg" src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
			<?php endif; ?>
			<div class="event-hero-overlay" aria-hidden="true"></div>

			<div class="event-hero-info">
				<div class="event-hero-meta">
					<span class="event-rating" aria-label="<?php esc_attr_e( 'Rating', 'vatan-event' ); ?>">
						<span aria-hidden="true">★</span>
						<?php echo esc_html( vatan_to_persian_digits( number_format_i18n( $rating, 1 ) ) ); ?>
					</span>
					<?php
					if ( function_exists( 'vatan_event_views_badge' ) ) {
						echo vatan_event_views_badge( $event_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — helper returns pre-escaped markup.
					}
					?>
					<?php if ( $category ) : ?>
						<span class="event-category" style="background-color: <?php echo esc_attr( $cat_color ); ?>;">
							<?php echo esc_html( $category->name ); ?>
						</span>
					<?php endif; ?>
				</div>

				<h1 class="event-title"><?php the_title(); ?></h1>

				<?php if ( $city || $event_venue ) : ?>
					<p class="event-venue">
						<span aria-hidden="true">📍</span>
						<?php
						$location_parts = array_filter( array(
							$event_venue,
							$city ? $city->name : '',
						) );
						echo esc_html( implode( '، ', $location_parts ) );
						?>
					</p>
				<?php endif; ?>
			</div>
		</section>

		<!-- Two-column layout -->
		<div class="container event-layout">

			<!-- Main column -->
			<div class="event-main">

				<!-- Share row -->
				<?php get_template_part( 'template-parts/share-row' ); ?>

				<!-- About -->
				<section class="event-about">
					<h2 class="event-section-title"><?php esc_html_e( 'About Event', 'vatan-event' ); ?></h2>
					<div class="event-about__content">
						<?php the_content(); ?>
					</div>
				</section>

				<!-- Stats grid -->
				<?php if ( $date_display || $time_range || $event_duration > 0 || $event_age_limit > 0 ) : ?>
					<div class="event-stats">
						<?php if ( $date_display ) : ?>
							<div class="event-stat">
								<span class="event-stat__icon" aria-hidden="true">📅</span>
								<div class="event-stat__body">
									<span class="event-stat__label"><?php esc_html_e( 'Date', 'vatan-event' ); ?></span>
									<span class="event-stat__value"><?php echo esc_html( $date_display ); ?></span>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $time_range ) : ?>
							<div class="event-stat">
								<span class="event-stat__icon" aria-hidden="true">🕐</span>
								<div class="event-stat__body">
									<span class="event-stat__label"><?php esc_html_e( 'Time', 'vatan-event' ); ?></span>
									<span class="event-stat__value"><?php echo esc_html( $time_range ); ?></span>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $event_duration > 0 ) : ?>
							<div class="event-stat">
								<span class="event-stat__icon" aria-hidden="true">⏱</span>
								<div class="event-stat__body">
									<span class="event-stat__label"><?php esc_html_e( 'Duration', 'vatan-event' ); ?></span>
									<span class="event-stat__value">
										<?php
										printf(
											/* translators: %s: number of minutes */
											esc_html__( '%s minutes', 'vatan-event' ),
											esc_html( vatan_to_persian_digits( $event_duration ) )
										);
										?>
									</span>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $event_age_limit > 0 ) : ?>
							<div class="event-stat">
								<span class="event-stat__icon" aria-hidden="true">🔞</span>
								<div class="event-stat__body">
									<span class="event-stat__label"><?php esc_html_e( 'Age limit', 'vatan-event' ); ?></span>
									<span class="event-stat__value">
										<?php echo esc_html( vatan_to_persian_digits( $event_age_limit ) ); ?>+
									</span>
								</div>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Venue map (OSM embed) -->
				<?php vatan_render_venue_map( $event_id ); ?>

				<!-- Organizer card -->
				<?php
				$org_id = function_exists( 'get_field' )
					? (int) get_field( 'event_organizer', $event_id )
					: (int) get_post_meta( $event_id, 'event_organizer', true );
				if ( $org_id && 'organizer' === get_post_type( $org_id ) && 'publish' === get_post_status( $org_id ) ) :
					$org_logo     = get_the_post_thumbnail_url( $org_id, 'thumbnail' );
					$org_link     = get_permalink( $org_id );
					$org_name     = get_the_title( $org_id );
					$org_tagline  = function_exists( 'get_field' ) ? (string) get_field( 'organizer_tagline',  $org_id ) : '';
					$org_whatsapp = function_exists( 'get_field' ) ? (string) get_field( 'organizer_whatsapp', $org_id ) : '';
					$org_email    = function_exists( 'get_field' ) ? (string) get_field( 'organizer_email',    $org_id ) : '';
					?>
					<aside class="organizer-card" aria-labelledby="organizer-card-name">
						<header class="organizer-card__head">
							<span class="organizer-card__eyebrow"><?php esc_html_e( 'Presented by', 'vatan-event' ); ?></span>
						</header>
						<div class="organizer-card__body">
							<a class="organizer-card__logo" href="<?php echo esc_url( $org_link ); ?>" aria-hidden="true" tabindex="-1">
								<?php if ( $org_logo ) : ?>
									<img src="<?php echo esc_url( $org_logo ); ?>" alt="" />
								<?php else : ?>
									<span>🏢</span>
								<?php endif; ?>
							</a>
							<div class="organizer-card__meta">
								<h3 class="organizer-card__name" id="organizer-card-name">
									<a href="<?php echo esc_url( $org_link ); ?>"><?php echo esc_html( $org_name ); ?></a>
								</h3>
								<?php if ( $org_tagline ) : ?>
									<p class="organizer-card__tagline"><?php echo esc_html( $org_tagline ); ?></p>
								<?php endif; ?>
								<div class="organizer-card__actions">
									<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $org_link ); ?>">
										<?php esc_html_e( 'View profile', 'vatan-event' ); ?> →
									</a>
									<?php if ( $org_whatsapp ) : ?>
										<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $org_whatsapp ); ?>" target="_blank" rel="noopener noreferrer">
											<span aria-hidden="true">💬</span> <?php esc_html_e( 'Contact', 'vatan-event' ); ?>
										</a>
									<?php elseif ( $org_email ) : ?>
										<a class="btn btn--ghost btn--sm" href="mailto:<?php echo esc_attr( $org_email ); ?>">
											<span aria-hidden="true">✉</span> <?php esc_html_e( 'Contact', 'vatan-event' ); ?>
										</a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					</aside>
				<?php endif; ?>

				<!-- Categories & city -->
				<footer class="event-taxonomies">
					<?php
					$cat_list = get_the_term_list(
						$event_id,
						'event_category',
						'<span class="event-taxonomy__label">' . esc_html__( 'Categories:', 'vatan-event' ) . '</span> <span class="event-taxonomy__terms">',
						'، ',
						'</span>'
					);
					if ( $cat_list && ! is_wp_error( $cat_list ) ) {
						echo '<div class="event-taxonomy">' . $cat_list . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}

					$city_list = get_the_term_list(
						$event_id,
						'event_city',
						'<span class="event-taxonomy__label">' . esc_html__( 'City:', 'vatan-event' ) . '</span> <span class="event-taxonomy__terms">',
						'، ',
						'</span>'
					);
					if ( $city_list && ! is_wp_error( $city_list ) ) {
						echo '<div class="event-taxonomy">' . $city_list . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</footer>
			</div>

			<!-- Ticket sidebar — non-sticky so it scrolls with the page;
			     keeps the full-width seat map below from being obscured. -->
			<aside class="ticket-sidebar">

				<header class="ticket-sidebar__head">
					<h2 class="ticket-sidebar__title"><?php esc_html_e( 'Buy Ticket', 'vatan-event' ); ?></h2>
					<span class="sale-status sale-status--<?php echo esc_attr( $status_meta['state'] ); ?>">
						<?php echo esc_html( $status_meta['label'] ); ?>
					</span>
				</header>

				<?php if ( $date_display || $time_range ) : ?>
					<div class="event-date-display">
						<span class="calendar-icon" aria-hidden="true">📅</span>
						<div class="event-date-display__body">
							<span class="event-date-display__label"><?php esc_html_e( 'Selected date', 'vatan-event' ); ?></span>
							<span class="event-date-display__value">
								<?php echo esc_html( trim( $date_display . ( $time_range ? ' — ' . $time_range : '' ) ) ); ?>
							</span>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( is_array( $tickets ) && ! empty( $tickets ) ) : ?>
					<ul class="ticket-types">
						<?php
						foreach ( $tickets as $ticket ) :
							$ticket_color = isset( $ticket['ticket_color'] ) ? sanitize_hex_color( $ticket['ticket_color'] ) : '';
							$ticket_name  = isset( $ticket['ticket_name'] ) ? (string) $ticket['ticket_name'] : '';
							$ticket_price = isset( $ticket['ticket_price'] ) ? (float) $ticket['ticket_price'] : 0.0;
							?>
							<li class="ticket-type">
								<span class="ticket-dot"<?php echo $ticket_color ? ' style="background-color: ' . esc_attr( $ticket_color ) . ';"' : ''; ?>></span>
								<span class="ticket-name"><?php echo esc_html( $ticket_name ); ?></span>
								<span class="ticket-price"><?php echo esc_html( vatan_format_price( $ticket_price ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php
				$btn_label = $has_seat_map
					? __( 'Select seat', 'vatan-event' )
					: __( 'Buy now', 'vatan-event' );
				$btn_target = $has_seat_map ? '#choose-seats' : '#';
				?>
				<a class="btn btn--primary btn--full" id="select-seat-btn" href="<?php echo esc_url( $btn_target ); ?>" data-event-id="<?php echo esc_attr( $event_id ); ?>">
					<?php echo esc_html( $btn_label ); ?>
				</a>

				<p class="refund-notice">
					<span aria-hidden="true">🔒</span>
					<?php esc_html_e( 'Refund guarantee up to 72 hours before the event.', 'vatan-event' ); ?>
				</p>
			</aside>
		</div>

		<!-- Seat map — full container width, below the 2-col block. -->
		<?php if ( $has_seat_map ) : ?>
			<section class="container event-seats" id="choose-seats">
				<h2 class="event-section-title"><?php esc_html_e( 'Choose Your Seats', 'vatan-event' ); ?></h2>
				<?php get_template_part( 'template-parts/seat-map' ); ?>
			</section>
		<?php endif; ?>
	</main>

	<!-- Related events -->
	<?php
	$related = vatan_get_related_events( $event_id, 3 );
	if ( ! empty( $related ) ) :
		?>
		<section class="container related-events">
			<header class="related-events__head">
				<h2 class="section-title"><?php esc_html_e( 'You might also like', 'vatan-event' ); ?></h2>
			</header>
			<div class="event-grid event-grid--related">
				<?php
				global $post;
				foreach ( $related as $post ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					setup_postdata( $post );
					get_template_part( 'template-parts/event-card' );
				endforeach;
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

	<?php
endwhile;

get_footer();
