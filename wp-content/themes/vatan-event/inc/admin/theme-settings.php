<?php
/**
 * Theme Settings page (Settings API).
 *
 * Storage: a single option `vatan_theme_settings` carrying a structured
 * array. Tabs render different field groups but share the same option +
 * sanitize callback.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'VATAN_SETTINGS_OPTION' ) ) {
	define( 'VATAN_SETTINGS_OPTION', 'vatan_theme_settings' );
}

/**
 * Default values for every setting. Reading happens through
 * vatan_get_setting() so callers don't need to know defaults.
 *
 * @return array
 */
function vatan_theme_settings_defaults() {
	return array(
		'logo_id'             => 0,
		'color_scheme'        => 'dark',  // 'dark' | 'light'
		'primary_color'       => '#FF2D78',
		'secondary_color'     => '#7C3AED',
		// Surface palette overrides — stored per scheme so switching scheme
		// also switches the active overrides. Empty = use scheme default.
		'bg_color_dark'         => '',
		'bg_color_light'        => '',
		'surface_color_dark'    => '',
		'surface_color_light'   => '',
		'border_color_dark'     => '',
		'border_color_light'    => '',
		'text_color_dark'       => '',
		'text_color_light'      => '',
		'text_muted_color_dark' => '',
		'text_muted_color_light'=> '',
		'font_family'         => 'vazirmatn',
		'hero_title'          => '',
		'hero_subtitle'       => '',
		'hero_image_id'       => 0,
		'hero_video_url'      => '',
		'hero_btn_label'      => '',
		'hero_btn_link'       => '',
		'home_event_count'    => 4,
		'home_categories'     => array(),
		'hero_slides'         => array(),
		'footer_about'        => '',
		'social_links'        => array(),
		'app_store_url'       => '',
		'play_store_url'      => '',
		'newsletter_title'    => '',
		'newsletter_subtitle' => '',
		'newsletter_api_key'  => '',
		// Music player visibility — independent toggles for web vs app.
		// Default: app on, web off. The siteowner can flip web on temporarily
		// for desktop testing without exposing the player publicly.
		'music_player_web_enabled' => false,
		'music_player_app_enabled' => true,
	);
}

/**
 * Read one setting value, falling back to the registered default.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function vatan_get_setting( $key, $default = null ) {
	static $cache = null;
	if ( null === $cache ) {
		$saved    = get_option( VATAN_SETTINGS_OPTION, array() );
		$defaults = vatan_theme_settings_defaults();
		$cache    = is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
	}
	if ( null === $default && array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}
	return array_key_exists( $key, $cache ) ? $cache[ $key ] : $default;
}

function vatan_register_theme_settings() {
	register_setting( 'vatan_theme_settings_group', VATAN_SETTINGS_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'vatan_sanitize_theme_settings',
		'default'           => vatan_theme_settings_defaults(),
	) );
}
add_action( 'admin_init', 'vatan_register_theme_settings' );

function vatan_sanitize_theme_settings( $input ) {
	$defaults = vatan_theme_settings_defaults();
	$existing = get_option( VATAN_SETTINGS_OPTION, array() );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}
	if ( ! is_array( $input ) ) {
		$input = array();
	}

	// Start from existing values so unsubmitted tabs keep their data.
	$clean = array_merge( $defaults, $existing );

	$tab = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';

	// Identity tab fields.
	if ( '' === $tab || 'identity' === $tab ) {
		if ( isset( $input['logo_id'] ) ) {
			$clean['logo_id'] = absint( $input['logo_id'] );
		}
		if ( isset( $input['color_scheme'] ) ) {
			$scheme = sanitize_key( $input['color_scheme'] );
			$clean['color_scheme'] = in_array( $scheme, array( 'dark', 'light' ), true ) ? $scheme : 'dark';
		}
		if ( isset( $input['primary_color'] ) ) {
			$hex = sanitize_hex_color( $input['primary_color'] );
			$clean['primary_color'] = $hex ? $hex : $defaults['primary_color'];
		}
		if ( isset( $input['secondary_color'] ) ) {
			$hex = sanitize_hex_color( $input['secondary_color'] );
			$clean['secondary_color'] = $hex ? $hex : $defaults['secondary_color'];
		}
		// Surface overrides — per scheme. Empty value = use that scheme's default.
		foreach ( array( 'bg', 'surface', 'border', 'text', 'text_muted' ) as $base ) {
			foreach ( array( 'dark', 'light' ) as $scheme ) {
				$key = $base . '_color_' . $scheme;
				if ( isset( $input[ $key ] ) ) {
					$raw = trim( (string) $input[ $key ] );
					if ( '' === $raw ) {
						$clean[ $key ] = '';
					} else {
						$hex = sanitize_hex_color( $raw );
						$clean[ $key ] = $hex ? $hex : '';
					}
				}
			}
		}
		if ( isset( $input['font_family'] ) ) {
			$clean['font_family'] = sanitize_key( $input['font_family'] );
		}
	}

	// Homepage tab.
	if ( '' === $tab || 'home' === $tab ) {
		if ( isset( $input['hero_title'] ) ) {
			$clean['hero_title'] = sanitize_text_field( $input['hero_title'] );
		}
		if ( isset( $input['hero_subtitle'] ) ) {
			$clean['hero_subtitle'] = sanitize_textarea_field( $input['hero_subtitle'] );
		}
		if ( isset( $input['hero_image_id'] ) ) {
			$clean['hero_image_id'] = absint( $input['hero_image_id'] );
		}
		if ( isset( $input['hero_video_url'] ) ) {
			$clean['hero_video_url'] = esc_url_raw( $input['hero_video_url'] );
		}
		if ( isset( $input['hero_btn_label'] ) ) {
			$clean['hero_btn_label'] = sanitize_text_field( $input['hero_btn_label'] );
		}
		if ( isset( $input['hero_btn_link'] ) ) {
			$clean['hero_btn_link'] = esc_url_raw( $input['hero_btn_link'] );
		}
		if ( isset( $input['home_event_count'] ) ) {
			$clean['home_event_count'] = max( 0, min( 24, absint( $input['home_event_count'] ) ) );
		}
		if ( isset( $input['home_categories'] ) && is_array( $input['home_categories'] ) ) {
			$clean['home_categories'] = array_values( array_filter( array_map( 'absint', $input['home_categories'] ) ) );
		} elseif ( isset( $input['_home_categories_submitted'] ) ) {
			// Empty multi-select still POSTed — clear the saved list.
			$clean['home_categories'] = array();
		}

		// Hero slides repeater.
		if ( isset( $input['_hero_slides_submitted'] ) ) {
			$clean['hero_slides'] = array();
			if ( isset( $input['hero_slides'] ) && is_array( $input['hero_slides'] ) ) {
				foreach ( $input['hero_slides'] as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$image_id = isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0;
					$title    = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
					// Skip rows where neither image nor title is set — empty placeholder.
					if ( ! $image_id && '' === $title ) {
						continue;
					}
					$clean['hero_slides'][] = array(
						'image_id'        => $image_id,
						'eyebrow'         => isset( $row['eyebrow'] ) ? sanitize_text_field( $row['eyebrow'] ) : '',
						'title'           => $title,
						'title_highlight' => isset( $row['title_highlight'] ) ? sanitize_text_field( $row['title_highlight'] ) : '',
						'subtitle'        => isset( $row['subtitle'] ) ? sanitize_textarea_field( $row['subtitle'] ) : '',
						'primary_label'   => isset( $row['primary_label'] ) ? sanitize_text_field( $row['primary_label'] ) : '',
						'primary_url'     => isset( $row['primary_url'] ) ? esc_url_raw( $row['primary_url'] ) : '',
						'secondary_label' => isset( $row['secondary_label'] ) ? sanitize_text_field( $row['secondary_label'] ) : '',
						'secondary_url'   => isset( $row['secondary_url'] ) ? esc_url_raw( $row['secondary_url'] ) : '',
					);
				}
			}
		}
	}

	// Footer tab.
	if ( '' === $tab || 'footer' === $tab ) {
		if ( isset( $input['footer_about'] ) ) {
			$clean['footer_about'] = sanitize_textarea_field( $input['footer_about'] );
		}
		if ( isset( $input['app_store_url'] ) ) {
			$clean['app_store_url'] = esc_url_raw( $input['app_store_url'] );
		}
		if ( isset( $input['play_store_url'] ) ) {
			$clean['play_store_url'] = esc_url_raw( $input['play_store_url'] );
		}
		if ( isset( $input['_social_links_submitted'] ) ) {
			$clean['social_links'] = array();
			if ( isset( $input['social_links'] ) && is_array( $input['social_links'] ) ) {
				foreach ( $input['social_links'] as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$platform = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : '';
					$url      = isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '';
					if ( $platform && $url ) {
						$clean['social_links'][] = array(
							'platform' => $platform,
							'url'      => $url,
						);
					}
				}
			}
		}
	}

	// Newsletter tab.
	if ( '' === $tab || 'newsletter' === $tab ) {
		if ( isset( $input['newsletter_title'] ) ) {
			$clean['newsletter_title'] = sanitize_text_field( $input['newsletter_title'] );
		}
		if ( isset( $input['newsletter_subtitle'] ) ) {
			$clean['newsletter_subtitle'] = sanitize_textarea_field( $input['newsletter_subtitle'] );
		}
		if ( isset( $input['newsletter_api_key'] ) ) {
			$clean['newsletter_api_key'] = sanitize_text_field( $input['newsletter_api_key'] );
		}
	}

	// Music tab — visibility toggles for the player UI.
	if ( '' === $tab || 'music' === $tab ) {
		if ( isset( $input['_music_submitted'] ) ) {
			$clean['music_player_web_enabled'] = ! empty( $input['music_player_web_enabled'] );
			$clean['music_player_app_enabled'] = ! empty( $input['music_player_app_enabled'] );
		}
	}

	// Reset the static cache in vatan_get_setting() so subsequent reads
	// in the same request see the new values.
	wp_cache_delete( VATAN_SETTINGS_OPTION, 'options' );

	return $clean;
}

function vatan_setting_name( $key ) {
	return VATAN_SETTINGS_OPTION . '[' . $key . ']';
}

function vatan_theme_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'vatan-event' ) );
	}

	$tabs = array(
		'identity'   => __( 'Identity', 'vatan-event' ),
		'home'       => __( 'Homepage', 'vatan-event' ),
		'categories' => __( 'Categories', 'vatan-event' ),
		'footer'     => __( 'Footer', 'vatan-event' ),
		'newsletter' => __( 'Newsletter', 'vatan-event' ),
		'music'      => __( 'Music', 'vatan-event' ),
	);
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'identity'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'identity';
	}
	?>
	<div class="wrap vatan-admin vatan-settings">
		<h1><?php esc_html_e( 'Theme Settings', 'vatan-event' ); ?></h1>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=vatan-theme-settings&tab=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>

		<?php settings_errors(); ?>

		<?php if ( 'categories' === $tab ) : ?>
			<?php vatan_render_categories_tab(); ?>
		<?php else : ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'vatan_theme_settings_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( vatan_setting_name( '_tab' ) ); ?>" value="<?php echo esc_attr( $tab ); ?>" />
				<?php
				switch ( $tab ) {
					case 'home':       vatan_render_home_tab(); break;
					case 'footer':     vatan_render_footer_tab(); break;
					case 'newsletter': vatan_render_newsletter_tab(); break;
					case 'music':      vatan_render_music_tab(); break;
					default:           vatan_render_identity_tab(); break;
				}
				?>
				<?php submit_button(); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function vatan_render_identity_tab() {
	$logo_id   = (int) vatan_get_setting( 'logo_id' );
	$scheme    = (string) vatan_get_setting( 'color_scheme', 'dark' );
	$primary   = (string) vatan_get_setting( 'primary_color' );
	$secondary = (string) vatan_get_setting( 'secondary_color' );
	$font      = (string) vatan_get_setting( 'font_family' );

	// Default palettes per scheme — used as picker placeholders / fallbacks.
	$scheme_palettes = array(
		'dark' => array(
			'bg'         => '#0D0D1A',
			'surface'    => '#1A1A2E',
			'border'     => '#2A2A40',
			'text'       => '#FFFFFF',
			'text_muted' => '#A0A0B8',
		),
		'light' => array(
			'bg'         => '#FFFFFF',
			'surface'    => '#F7F8FA',
			'border'     => '#E4E6EB',
			'text'       => '#0D0D1A',
			'text_muted' => '#6B7280',
		),
	);

	$override_rows = array(
		'bg'         => __( 'Page background', 'vatan-event' ),
		'surface'    => __( 'Card surface', 'vatan-event' ),
		'border'     => __( 'Border', 'vatan-event' ),
		'text'       => __( 'Text — primary', 'vatan-event' ),
		'text_muted' => __( 'Text — muted', 'vatan-event' ),
	);

	$fonts = array(
		'vazirmatn' => 'Vazirmatn',
		'inter'     => 'Inter',
		'iransans'  => 'IranSans',
		'system'    => __( 'System default', 'vatan-event' ),
	);
	?>
	<h2><?php esc_html_e( 'Site Identity', 'vatan-event' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Logo', 'vatan-event' ); ?></th>
			<td><?php vatan_field_media( 'logo_id', $logo_id ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="font_family"><?php esc_html_e( 'Primary font', 'vatan-event' ); ?></label></th>
			<td>
				<select id="font_family" name="<?php echo esc_attr( vatan_setting_name( 'font_family' ) ); ?>">
					<?php foreach ( $fonts as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $font, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Color scheme', 'vatan-event' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Pick a base scheme. Surface colors below override individual tokens for fine-tuning — leave blank to follow the scheme default.', 'vatan-event' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Scheme', 'vatan-event' ); ?></th>
			<td>
				<label class="vatan-scheme-radio">
					<input type="radio" name="<?php echo esc_attr( vatan_setting_name( 'color_scheme' ) ); ?>" value="dark" <?php checked( $scheme, 'dark' ); ?> />
					<span class="vatan-scheme-radio__chip" style="background:#0D0D1A; color:#fff;">●</span>
					<?php esc_html_e( 'Dark (default)', 'vatan-event' ); ?>
				</label>
				<label class="vatan-scheme-radio">
					<input type="radio" name="<?php echo esc_attr( vatan_setting_name( 'color_scheme' ) ); ?>" value="light" <?php checked( $scheme, 'light' ); ?> />
					<span class="vatan-scheme-radio__chip" style="background:#FFFFFF; color:#0D0D1A; border:1px solid #ccc;">●</span>
					<?php esc_html_e( 'Light', 'vatan-event' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Adds the .vatan-theme--light class on body. Brand colors and seat colors stay the same.', 'vatan-event' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Brand colors', 'vatan-event' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="primary_color"><?php esc_html_e( 'Primary', 'vatan-event' ); ?></label></th>
			<td>
				<input type="text" id="primary_color" class="vatan-color-picker" name="<?php echo esc_attr( vatan_setting_name( 'primary_color' ) ); ?>" value="<?php echo esc_attr( $primary ); ?>" data-default-color="#FF2D78" />
				<p class="description"><?php esc_html_e( 'Buttons, links, hot pink accents.', 'vatan-event' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="secondary_color"><?php esc_html_e( 'Secondary', 'vatan-event' ); ?></label></th>
			<td>
				<input type="text" id="secondary_color" class="vatan-color-picker" name="<?php echo esc_attr( vatan_setting_name( 'secondary_color' ) ); ?>" value="<?php echo esc_attr( $secondary ); ?>" data-default-color="#7C3AED" />
				<p class="description"><?php esc_html_e( 'Logo gradient, hero accents.', 'vatan-event' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Surface palette (per-scheme overrides)', 'vatan-event' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Each scheme has its own override palette. Switching the active scheme above also switches which set of overrides applies on the front-end. Leave a field empty to use that scheme default.', 'vatan-event' ); ?>
	</p>
	<table class="form-table vatan-scheme-overrides" role="presentation">
		<tr class="vatan-scheme-overrides__head">
			<th scope="col"></th>
			<td>
				<div class="vatan-scheme-pair">
					<span class="vatan-scheme-pair__label">
						<?php esc_html_e( 'Dark scheme', 'vatan-event' ); ?>
						<?php if ( 'dark' === $scheme ) : ?>
							<em>· <?php esc_html_e( 'active', 'vatan-event' ); ?></em>
						<?php endif; ?>
					</span>
					<span class="vatan-scheme-pair__label">
						<?php esc_html_e( 'Light scheme', 'vatan-event' ); ?>
						<?php if ( 'light' === $scheme ) : ?>
							<em>· <?php esc_html_e( 'active', 'vatan-event' ); ?></em>
						<?php endif; ?>
					</span>
				</div>
			</td>
		</tr>
		<?php foreach ( $override_rows as $base => $label ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td>
					<div class="vatan-scheme-pair">
						<input
							type="text"
							class="vatan-color-picker"
							name="<?php echo esc_attr( vatan_setting_name( $base . '_color_dark' ) ); ?>"
							value="<?php echo esc_attr( (string) vatan_get_setting( $base . '_color_dark' ) ); ?>"
							data-default-color="<?php echo esc_attr( $scheme_palettes['dark'][ $base ] ); ?>"
							data-alpha-enabled="false"
						/>
						<input
							type="text"
							class="vatan-color-picker"
							name="<?php echo esc_attr( vatan_setting_name( $base . '_color_light' ) ); ?>"
							value="<?php echo esc_attr( (string) vatan_get_setting( $base . '_color_light' ) ); ?>"
							data-default-color="<?php echo esc_attr( $scheme_palettes['light'][ $base ] ); ?>"
							data-alpha-enabled="false"
						/>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}

function vatan_render_home_tab() {
	$title    = (string) vatan_get_setting( 'hero_title' );
	$subtitle = (string) vatan_get_setting( 'hero_subtitle' );
	$image_id = (int) vatan_get_setting( 'hero_image_id' );
	$video    = (string) vatan_get_setting( 'hero_video_url' );
	$btn_lbl  = (string) vatan_get_setting( 'hero_btn_label' );
	$btn_lnk  = (string) vatan_get_setting( 'hero_btn_link' );
	$count    = (int) vatan_get_setting( 'home_event_count' );
	$cats     = (array) vatan_get_setting( 'home_categories' );

	$available_cats = get_terms( array(
		'taxonomy'   => 'event_category',
		'hide_empty' => false,
	) );
	if ( is_wp_error( $available_cats ) ) {
		$available_cats = array();
	}
	?>
	<h2><?php esc_html_e( 'Hero slides (carousel)', 'vatan-event' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Add one or more slides for a carousel. If empty, the single hero fields below render as a static slide.', 'vatan-event' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Slides', 'vatan-event' ); ?></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( vatan_setting_name( '_hero_slides_submitted' ) ); ?>" value="1" />
				<div class="vatan-repeater vatan-repeater--cards" data-vatan-repeater>
					<div class="vatan-repeater__rows" data-vatan-repeater-rows>
						<?php
						$slides = (array) vatan_get_setting( 'hero_slides' );
						foreach ( $slides as $i => $slide ) {
							vatan_render_hero_slide_row( $i, $slide );
						}
						?>
					</div>
					<script type="text/html" data-vatan-repeater-template>
						<?php vatan_render_hero_slide_row( '__INDEX__', array() ); ?>
					</script>
					<button type="button" class="button" data-vatan-repeater-add>
						<?php esc_html_e( 'Add slide', 'vatan-event' ); ?>
					</button>
				</div>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Hero (legacy single-slide)', 'vatan-event' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Used only when no slides are configured above. Same fields as one slide.', 'vatan-event' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="hero_title"><?php esc_html_e( 'Title', 'vatan-event' ); ?></label></th>
			<td><input type="text" id="hero_title" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'hero_title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="hero_subtitle"><?php esc_html_e( 'Subtitle', 'vatan-event' ); ?></label></th>
			<td><textarea id="hero_subtitle" class="large-text" rows="3" name="<?php echo esc_attr( vatan_setting_name( 'hero_subtitle' ) ); ?>"><?php echo esc_textarea( $subtitle ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Background image', 'vatan-event' ); ?></th>
			<td><?php vatan_field_media( 'hero_image_id', $image_id ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="hero_video_url"><?php esc_html_e( 'Video URL', 'vatan-event' ); ?></label></th>
			<td>
				<input type="url" id="hero_video_url" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'hero_video_url' ) ); ?>" value="<?php echo esc_attr( $video ); ?>" placeholder="https://..." />
				<p class="description"><?php esc_html_e( 'Optional. When set, the hero plays this video as background.', 'vatan-event' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="hero_btn_label"><?php esc_html_e( 'Primary button label', 'vatan-event' ); ?></label></th>
			<td><input type="text" id="hero_btn_label" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'hero_btn_label' ) ); ?>" value="<?php echo esc_attr( $btn_lbl ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="hero_btn_link"><?php esc_html_e( 'Primary button link', 'vatan-event' ); ?></label></th>
			<td><input type="url" id="hero_btn_link" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'hero_btn_link' ) ); ?>" value="<?php echo esc_attr( $btn_lnk ); ?>" /></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Events feed', 'vatan-event' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="home_event_count"><?php esc_html_e( 'Events on homepage', 'vatan-event' ); ?></label></th>
			<td><input type="number" id="home_event_count" min="0" max="24" class="small-text" name="<?php echo esc_attr( vatan_setting_name( 'home_event_count' ) ); ?>" value="<?php echo esc_attr( $count ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="home_categories"><?php esc_html_e( 'Featured categories', 'vatan-event' ); ?></label></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( vatan_setting_name( '_home_categories_submitted' ) ); ?>" value="1" />
				<select id="home_categories" multiple size="6" name="<?php echo esc_attr( vatan_setting_name( 'home_categories' ) ); ?>[]" class="vatan-multi-select">
					<?php foreach ( $available_cats as $cat ) : ?>
						<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( in_array( (int) $cat->term_id, $cats, true ) ); ?>><?php echo esc_html( $cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple. Empty means all categories.', 'vatan-event' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

function vatan_render_categories_tab() {
	$cats = get_terms( array(
		'taxonomy'   => 'event_category',
		'hide_empty' => false,
	) );
	if ( is_wp_error( $cats ) || empty( $cats ) ) {
		echo '<p>' . esc_html__( 'No categories yet — add some under Events → Categories first.', 'vatan-event' ) . '</p>';
		return;
	}

	if ( isset( $_POST['vatan_categories_save'] ) ) {
		check_admin_referer( 'vatan_categories', 'vatan_categories_nonce' );
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'vatan-event' ) );
		}
		$emojis = isset( $_POST['vatan_emoji'] ) && is_array( $_POST['vatan_emoji'] )
			? wp_unslash( $_POST['vatan_emoji'] )
			: array();
		foreach ( $emojis as $term_id => $emoji ) {
			update_term_meta( (int) $term_id, 'vatan_emoji', sanitize_text_field( $emoji ) );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'vatan-event' ) . '</p></div>';
	}
	?>
	<h2><?php esc_html_e( 'Category Icons', 'vatan-event' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Pick an emoji rendered in the homepage category strip for each category.', 'vatan-event' ); ?></p>

	<form method="post">
		<?php wp_nonce_field( 'vatan_categories', 'vatan_categories_nonce' ); ?>
		<table class="form-table" role="presentation">
			<?php
			foreach ( $cats as $cat ) :
				$emoji = (string) get_term_meta( $cat->term_id, 'vatan_emoji', true );
				?>
				<tr>
					<th scope="row"><label for="vatan_emoji_<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></label></th>
					<td>
						<input
							type="text"
							id="vatan_emoji_<?php echo esc_attr( $cat->term_id ); ?>"
							class="vatan-emoji-input"
							maxlength="8"
							name="vatan_emoji[<?php echo esc_attr( $cat->term_id ); ?>]"
							value="<?php echo esc_attr( $emoji ); ?>"
							placeholder="🎤"
						/>
						<span class="description"><?php echo esc_html( $cat->slug ); ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php submit_button( __( 'Save icons', 'vatan-event' ), 'primary', 'vatan_categories_save' ); ?>
	</form>
	<?php
}

function vatan_render_footer_tab() {
	$about      = (string) vatan_get_setting( 'footer_about' );
	$social     = (array) vatan_get_setting( 'social_links' );
	$app_store  = (string) vatan_get_setting( 'app_store_url' );
	$play_store = (string) vatan_get_setting( 'play_store_url' );

	$platforms = array(
		'instagram' => 'Instagram',
		'twitter'   => 'Twitter / X',
		'facebook'  => 'Facebook',
		'youtube'   => 'YouTube',
		'linkedin'  => 'LinkedIn',
		'telegram'  => 'Telegram',
		'tiktok'    => 'TikTok',
		'whatsapp'  => 'WhatsApp',
		'email'     => 'Email (mailto:)',
	);
	?>
	<h2><?php esc_html_e( 'Footer', 'vatan-event' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="footer_about"><?php esc_html_e( 'About text', 'vatan-event' ); ?></label></th>
			<td><textarea id="footer_about" class="large-text" rows="4" name="<?php echo esc_attr( vatan_setting_name( 'footer_about' ) ); ?>"><?php echo esc_textarea( $about ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Social links', 'vatan-event' ); ?></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( vatan_setting_name( '_social_links_submitted' ) ); ?>" value="1" />
				<div class="vatan-repeater" data-vatan-repeater>
					<div class="vatan-repeater__rows" data-vatan-repeater-rows>
						<?php
						if ( ! empty( $social ) ) {
							foreach ( $social as $i => $row ) {
								vatan_render_social_row( $i, $row, $platforms );
							}
						}
						?>
					</div>
					<script type="text/html" data-vatan-repeater-template>
						<?php vatan_render_social_row( '__INDEX__', array(), $platforms ); ?>
					</script>
					<button type="button" class="button" data-vatan-repeater-add><?php esc_html_e( 'Add row', 'vatan-event' ); ?></button>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="app_store_url"><?php esc_html_e( 'App Store URL', 'vatan-event' ); ?></label></th>
			<td><input type="url" id="app_store_url" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'app_store_url' ) ); ?>" value="<?php echo esc_attr( $app_store ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="play_store_url"><?php esc_html_e( 'Google Play URL', 'vatan-event' ); ?></label></th>
			<td><input type="url" id="play_store_url" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'play_store_url' ) ); ?>" value="<?php echo esc_attr( $play_store ); ?>" /></td>
		</tr>
	</table>
	<?php
}

function vatan_render_social_row( $index, $row, $platforms ) {
	$platform    = isset( $row['platform'] ) ? $row['platform'] : '';
	$url         = isset( $row['url'] ) ? $row['url'] : '';
	$name_prefix = VATAN_SETTINGS_OPTION . '[social_links][' . $index . ']';
	?>
	<div class="vatan-repeater__row">
		<select name="<?php echo esc_attr( $name_prefix . '[platform]' ); ?>">
			<option value=""><?php esc_html_e( '— Platform —', 'vatan-event' ); ?></option>
			<?php foreach ( $platforms as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $platform, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="url" class="regular-text" name="<?php echo esc_attr( $name_prefix . '[url]' ); ?>" value="<?php echo esc_attr( $url ); ?>" placeholder="https://..." />
		<button type="button" class="button button-link-delete" data-vatan-repeater-remove aria-label="<?php esc_attr_e( 'Remove', 'vatan-event' ); ?>">×</button>
	</div>
	<?php
}

function vatan_render_newsletter_tab() {
	$title    = (string) vatan_get_setting( 'newsletter_title' );
	$subtitle = (string) vatan_get_setting( 'newsletter_subtitle' );
	$api_key  = (string) vatan_get_setting( 'newsletter_api_key' );
	?>
	<h2><?php esc_html_e( 'Newsletter', 'vatan-event' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="newsletter_title"><?php esc_html_e( 'Title', 'vatan-event' ); ?></label></th>
			<td><input type="text" id="newsletter_title" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'newsletter_title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="newsletter_subtitle"><?php esc_html_e( 'Subtitle', 'vatan-event' ); ?></label></th>
			<td><textarea id="newsletter_subtitle" class="large-text" rows="3" name="<?php echo esc_attr( vatan_setting_name( 'newsletter_subtitle' ) ); ?>"><?php echo esc_textarea( $subtitle ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="newsletter_api_key"><?php esc_html_e( 'Mailing list API key', 'vatan-event' ); ?></label></th>
			<td>
				<input type="text" id="newsletter_api_key" class="regular-text" name="<?php echo esc_attr( vatan_setting_name( 'newsletter_api_key' ) ); ?>" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off" />
				<p class="description"><?php esc_html_e( 'Used by the subscribe form to push into Mailchimp / Sendgrid / etc. Stored as plain text — keep this account read-only.', 'vatan-event' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

function vatan_render_music_tab() {
	$web_enabled = (bool) vatan_get_setting( 'music_player_web_enabled' );
	$app_enabled = (bool) vatan_get_setting( 'music_player_app_enabled' );
	?>
	<h2><?php esc_html_e( 'Music Player', 'vatan-event' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Independent visibility toggles for the music player UI. Catalog content (Tracks / Albums / Artists) is managed via their own admin menus; this tab only controls where the player surface is rendered.', 'vatan-event' ); ?>
	</p>

	<input type="hidden" name="<?php echo esc_attr( vatan_setting_name( '_music_submitted' ) ); ?>" value="1" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Mobile app', 'vatan-event' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( vatan_setting_name( 'music_player_app_enabled' ) ); ?>" value="1" <?php checked( $app_enabled ); ?> />
					<?php esc_html_e( 'Show the music player inside the Capacitor-wrapped mobile app', 'vatan-event' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Detected via the User-Agent token "VatanTicketApp" the app appends to every request.', 'vatan-event' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Website', 'vatan-event' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( vatan_setting_name( 'music_player_web_enabled' ) ); ?>" value="1" <?php checked( $web_enabled ); ?> />
					<?php esc_html_e( 'Show the music player on the public website too', 'vatan-event' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Off by default. Flip on while testing in your desktop browser, or leave it on permanently if you want the player available to web visitors as well.', 'vatan-event' ); ?>
					<br />
					<?php
					printf(
						/* translators: %s: preview URL */
						esc_html__( 'Admin override: append %s to any front-end URL to preview the player without changing this setting.', 'vatan-event' ),
						'<code>?vatan_app_preview=1</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Render one hero-slide block for the repeater.
 *
 * @param int|string $index Numeric index or `__INDEX__` template placeholder.
 * @param array      $slide
 */
function vatan_render_hero_slide_row( $index, $slide ) {
	$image_id  = isset( $slide['image_id'] ) ? (int) $slide['image_id'] : 0;
	$eyebrow   = isset( $slide['eyebrow'] ) ? (string) $slide['eyebrow'] : '';
	$title     = isset( $slide['title'] ) ? (string) $slide['title'] : '';
	$highlight = isset( $slide['title_highlight'] ) ? (string) $slide['title_highlight'] : '';
	$subtitle  = isset( $slide['subtitle'] ) ? (string) $slide['subtitle'] : '';
	$pri_label = isset( $slide['primary_label'] ) ? (string) $slide['primary_label'] : '';
	$pri_url   = isset( $slide['primary_url'] ) ? (string) $slide['primary_url'] : '';
	$sec_label = isset( $slide['secondary_label'] ) ? (string) $slide['secondary_label'] : '';
	$sec_url   = isset( $slide['secondary_url'] ) ? (string) $slide['secondary_url'] : '';

	$prefix    = VATAN_SETTINGS_OPTION . '[hero_slides][' . $index . ']';
	$thumb_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	?>
	<div class="vatan-repeater__row vatan-slide-row">
		<header class="vatan-slide-row__head">
			<strong><?php esc_html_e( 'Slide', 'vatan-event' ); ?></strong>
			<button type="button" class="button-link-delete" data-vatan-repeater-remove aria-label="<?php esc_attr_e( 'Remove slide', 'vatan-event' ); ?>">×</button>
		</header>
		<div class="vatan-slide-row__grid">
			<div class="vatan-slide-row__cell vatan-slide-row__cell--media">
				<label><?php esc_html_e( 'Background image', 'vatan-event' ); ?></label>
				<div class="vatan-media vatan-media--small" data-vatan-media>
					<div class="vatan-media__preview" data-vatan-media-preview>
						<?php if ( $thumb_url ) : ?>
							<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
						<?php endif; ?>
					</div>
					<input type="hidden" name="<?php echo esc_attr( $prefix . '[image_id]' ); ?>" value="<?php echo esc_attr( $image_id ); ?>" data-vatan-media-input />
					<p class="vatan-media__actions">
						<button type="button" class="button button-small" data-vatan-media-pick><?php esc_html_e( 'Choose…', 'vatan-event' ); ?></button>
						<button type="button" class="button button-small button-link-delete" data-vatan-media-clear<?php echo $image_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'vatan-event' ); ?></button>
					</p>
				</div>
			</div>

			<div class="vatan-slide-row__cell">
				<label><?php esc_html_e( 'Eyebrow / badge', 'vatan-event' ); ?></label>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[eyebrow]' ); ?>" value="<?php echo esc_attr( $eyebrow ); ?>" placeholder="<?php esc_attr_e( 'e.g. Special Sale', 'vatan-event' ); ?>" />
			</div>

			<div class="vatan-slide-row__cell vatan-slide-row__cell--wide">
				<label><?php esc_html_e( 'Title', 'vatan-event' ); ?></label>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[title]' ); ?>" value="<?php echo esc_attr( $title ); ?>" />
			</div>

			<div class="vatan-slide-row__cell">
				<label><?php esc_html_e( 'Highlighted phrase', 'vatan-event' ); ?></label>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[title_highlight]' ); ?>" value="<?php echo esc_attr( $highlight ); ?>" />
				<p class="description"><?php esc_html_e( 'Substring of title rendered in the primary brand color.', 'vatan-event' ); ?></p>
			</div>

			<div class="vatan-slide-row__cell vatan-slide-row__cell--wide">
				<label><?php esc_html_e( 'Subtitle', 'vatan-event' ); ?></label>
				<textarea class="large-text" rows="2" name="<?php echo esc_attr( $prefix . '[subtitle]' ); ?>"><?php echo esc_textarea( $subtitle ); ?></textarea>
			</div>

			<div class="vatan-slide-row__cell">
				<label><?php esc_html_e( 'Primary button — label', 'vatan-event' ); ?></label>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[primary_label]' ); ?>" value="<?php echo esc_attr( $pri_label ); ?>" />
			</div>
			<div class="vatan-slide-row__cell">
				<label><?php esc_html_e( 'Primary button — URL', 'vatan-event' ); ?></label>
				<input type="url" class="regular-text" name="<?php echo esc_attr( $prefix . '[primary_url]' ); ?>" value="<?php echo esc_attr( $pri_url ); ?>" placeholder="https://" />
			</div>
			<div class="vatan-slide-row__cell">
				<label><?php esc_html_e( 'Secondary button — label', 'vatan-event' ); ?></label>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $prefix . '[secondary_label]' ); ?>" value="<?php echo esc_attr( $sec_label ); ?>" />
			</div>
			<div class="vatan-slide-row__cell">
				<label><?php esc_html_e( 'Secondary button — URL', 'vatan-event' ); ?></label>
				<input type="url" class="regular-text" name="<?php echo esc_attr( $prefix . '[secondary_url]' ); ?>" value="<?php echo esc_attr( $sec_url ); ?>" placeholder="https://" />
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render a media-library uploader for a single attachment ID.
 *
 * @param string $key
 * @param int    $current_id
 */
function vatan_field_media( $key, $current_id ) {
	$url = $current_id ? wp_get_attachment_image_url( $current_id, 'thumbnail' ) : '';
	?>
	<div class="vatan-media" data-vatan-media>
		<div class="vatan-media__preview" data-vatan-media-preview>
			<?php if ( $url ) : ?>
				<img src="<?php echo esc_url( $url ); ?>" alt="" />
			<?php endif; ?>
		</div>
		<input type="hidden" name="<?php echo esc_attr( vatan_setting_name( $key ) ); ?>" value="<?php echo esc_attr( $current_id ); ?>" data-vatan-media-input />
		<p class="vatan-media__actions">
			<button type="button" class="button" data-vatan-media-pick><?php esc_html_e( 'Choose…', 'vatan-event' ); ?></button>
			<button type="button" class="button button-link-delete" data-vatan-media-clear<?php echo $current_id ? '' : ' style="display:none"'; ?>><?php esc_html_e( 'Remove', 'vatan-event' ); ?></button>
		</p>
	</div>
	<?php
}
