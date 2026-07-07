<?php
/**
 * Search bar.
 *
 * Pass `array( 'floating' => true )` via get_template_part's third argument
 * to render the bar inside a positioned wrapper that overlaps the hero.
 *
 *   get_template_part( 'template-parts/search-bar', null, array( 'floating' => true ) );
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

$args = wp_parse_args(
	isset( $args ) && is_array( $args ) ? $args : array(),
	array(
		'floating' => false,
	)
);

$bar_class = $args['floating'] ? 'search-bar search-bar--floating' : 'search-bar';

if ( $args['floating'] ) :
	?>
	<div class="search-bar-wrapper">
		<div class="container">
	<?php
endif;
?>

<section class="<?php echo esc_attr( $bar_class ); ?>" aria-label="<?php esc_attr_e( 'Find events', 'vatan-event' ); ?>">
	<form class="search-bar__form" role="search" data-vatan-search>

		<div class="search-bar__field search-bar__field--query">
			<label for="vatan-search-q" class="search-bar__label">
				<?php esc_html_e( 'Search', 'vatan-event' ); ?>
			</label>
			<input
				type="search"
				id="vatan-search-q"
				name="q"
				class="search-bar__input"
				placeholder="<?php esc_attr_e( 'Search events, venues, cities…', 'vatan-event' ); ?>"
				autocomplete="off"
			/>
		</div>

		<?php
		// Country select — only top-level event_city terms (those whose parent = 0).
		$countries = get_terms( array(
			'taxonomy'   => 'event_city',
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
		) );
		if ( ! is_wp_error( $countries ) && ! empty( $countries ) ) :
			?>
			<div class="search-bar__field">
				<label for="vatan-search-country" class="search-bar__label">
					<?php esc_html_e( 'Country', 'vatan-event' ); ?>
				</label>
				<select id="vatan-search-country" name="country" class="search-bar__input">
					<option value=""><?php esc_html_e( 'All countries', 'vatan-event' ); ?></option>
					<?php foreach ( $countries as $country ) : ?>
						<option value="<?php echo esc_attr( $country->slug ); ?>"><?php echo esc_html( $country->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<div class="search-bar__field">
			<label for="vatan-search-city" class="search-bar__label">
				<?php esc_html_e( 'City', 'vatan-event' ); ?>
			</label>
			<?php
			wp_dropdown_categories( array(
				'taxonomy'        => 'event_city',
				'name'            => 'city',
				'id'              => 'vatan-search-city',
				'class'           => 'search-bar__input',
				'value_field'     => 'slug',
				'show_option_all' => __( 'All cities', 'vatan-event' ),
				'hierarchical'    => true,
				'hide_empty'      => false,
				'orderby'         => 'name',
				'show_count'      => 0,
			) );
			?>
		</div>

		<div class="search-bar__field">
			<label for="vatan-search-date" class="search-bar__label">
				<?php esc_html_e( 'Date', 'vatan-event' ); ?>
			</label>
			<input
				type="date"
				id="vatan-search-date"
				name="date"
				class="search-bar__input"
			/>
		</div>

		<div class="search-bar__field">
			<label for="vatan-search-category" class="search-bar__label">
				<?php esc_html_e( 'Category', 'vatan-event' ); ?>
			</label>
			<?php
			wp_dropdown_categories( array(
				'taxonomy'        => 'event_category',
				'name'            => 'category',
				'id'              => 'vatan-search-category',
				'class'           => 'search-bar__input',
				'value_field'     => 'slug',
				'show_option_all' => __( 'All categories', 'vatan-event' ),
				'hierarchical'    => true,
				'hide_empty'      => false,
				'orderby'         => 'name',
				'show_count'      => 0,
			) );
			?>
		</div>

		<button type="submit" class="btn btn--primary search-bar__submit">
			<?php esc_html_e( 'Find!', 'vatan-event' ); ?>
		</button>

	</form>

	<div class="search-bar__results" data-vatan-search-results aria-live="polite"></div>
</section>

<?php
if ( $args['floating'] ) :
	?>
		</div>
	</div>
	<?php
endif;
