<?php
/**
 * Single Organizer template.
 *
 * Public profile for one organizer: logo + tagline + bio + contact actions,
 * followed by a grid of their upcoming events.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$org_id    = get_the_ID();
	$logo_url  = get_the_post_thumbnail_url( $org_id, 'medium' );

	$tagline   = function_exists( 'get_field' ) ? (string) get_field( 'organizer_tagline',   $org_id ) : (string) get_post_meta( $org_id, 'organizer_tagline',   true );
	$email     = function_exists( 'get_field' ) ? (string) get_field( 'organizer_email',     $org_id ) : (string) get_post_meta( $org_id, 'organizer_email',     true );
	$phone     = function_exists( 'get_field' ) ? (string) get_field( 'organizer_phone',     $org_id ) : (string) get_post_meta( $org_id, 'organizer_phone',     true );
	$website   = function_exists( 'get_field' ) ? (string) get_field( 'organizer_website',   $org_id ) : (string) get_post_meta( $org_id, 'organizer_website',   true );
	$whatsapp  = function_exists( 'get_field' ) ? (string) get_field( 'organizer_whatsapp',  $org_id ) : (string) get_post_meta( $org_id, 'organizer_whatsapp',  true );
	$instagram = function_exists( 'get_field' ) ? (string) get_field( 'organizer_instagram', $org_id ) : (string) get_post_meta( $org_id, 'organizer_instagram', true );
	$telegram  = function_exists( 'get_field' ) ? (string) get_field( 'organizer_telegram',  $org_id ) : (string) get_post_meta( $org_id, 'organizer_telegram',  true );
	?>

	<main class="site-main site-main--single-organizer">

		<!-- Breadcrumb -->
		<nav class="container breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'vatan-event' ); ?>">
			<ol class="breadcrumb__list">
				<li class="breadcrumb__item">
					<a class="breadcrumb__link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'vatan-event' ); ?></a>
				</li>
				<li class="breadcrumb__sep" aria-hidden="true">›</li>
				<li class="breadcrumb__item breadcrumb__item--current" aria-current="page"><?php the_title(); ?></li>
			</ol>
		</nav>

		<!-- Profile header -->
		<header class="container organizer-profile">
			<div class="organizer-profile__logo">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
				<?php else : ?>
					<span aria-hidden="true">🏢</span>
				<?php endif; ?>
			</div>

			<div class="organizer-profile__body">
				<h1 class="organizer-profile__name"><?php the_title(); ?></h1>
				<?php if ( $tagline ) : ?>
					<p class="organizer-profile__tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>

				<?php if ( get_the_content() ) : ?>
					<div class="organizer-profile__bio"><?php the_content(); ?></div>
				<?php endif; ?>

				<div class="organizer-profile__actions">
					<?php if ( $whatsapp ) : ?>
						<a class="btn btn--primary" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener noreferrer">
							<span aria-hidden="true">💬</span> <?php esc_html_e( 'Message on WhatsApp', 'vatan-event' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<a class="btn btn--ghost" href="mailto:<?php echo esc_attr( $email ); ?>">
							<span aria-hidden="true">✉</span> <?php esc_html_e( 'Email', 'vatan-event' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $website ) : ?>
						<a class="btn btn--ghost" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer">
							<span aria-hidden="true">🌐</span> <?php esc_html_e( 'Website', 'vatan-event' ); ?>
						</a>
					<?php endif; ?>
				</div>

				<?php if ( $instagram || $telegram || $phone ) : ?>
					<ul class="organizer-profile__channels">
						<?php if ( $phone ) : ?>
							<li><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>"><span aria-hidden="true">📞</span> <?php echo esc_html( $phone ); ?></a></li>
						<?php endif; ?>
						<?php if ( $instagram ) : ?>
							<li><a href="<?php echo esc_url( $instagram ); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">📷</span> Instagram</a></li>
						<?php endif; ?>
						<?php if ( $telegram ) : ?>
							<li><a href="<?php echo esc_url( $telegram ); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">✈️</span> Telegram</a></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>
		</header>

		<!-- Events by this organizer -->
		<section class="container organizer-events">
			<header class="organizer-events__head">
				<h2 class="section-title"><?php esc_html_e( 'Upcoming events', 'vatan-event' ); ?></h2>
			</header>

			<?php
			$today    = current_time( 'Y-m-d' );
			$upcoming = new WP_Query( array(
				'post_type'      => 'event',
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'no_found_rows'  => true,
				'orderby'        => array(
					'meta_value' => 'ASC',
					'date'       => 'DESC',
				),
				'meta_key'       => 'event_date',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'event_organizer',
						'value'   => (string) $org_id,
						'compare' => '=',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => 'event_date',
							'value'   => $today,
							'compare' => '>=',
							'type'    => 'DATE',
						),
						array(
							'key'     => 'event_date',
							'compare' => 'NOT EXISTS',
						),
					),
				),
			) );

			if ( $upcoming->have_posts() ) : ?>
				<div class="event-grid">
					<?php while ( $upcoming->have_posts() ) :
						$upcoming->the_post();
						get_template_part( 'template-parts/event-card' );
					endwhile; ?>
				</div>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p class="organizer-events__empty"><?php esc_html_e( 'No upcoming events from this organizer yet.', 'vatan-event' ); ?></p>
			<?php endif; ?>
		</section>
	</main>

	<?php
endwhile;

get_footer();
