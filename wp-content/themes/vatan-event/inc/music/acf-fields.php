<?php
/**
 * ACF field groups for the music CPTs (`track`, `album`, `artist`).
 *
 * Field naming convention mirrors inc/acf-fields.php: keys are
 * `field_vatan_<type>_<name>`, names are `<type>_<name>` (so
 * `get_field( 'track_album' )` and so on).
 *
 * Requires ACF Pro for the `repeater` field on the artist group.
 * With ACF Free installed the repeater is silently ignored at
 * registration (no fatal, just absent in the editor).
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	return;
}

/* ============================================================
   Track — audio + metadata
   ============================================================ */

function vatan_register_track_field_group() {
	acf_add_local_field_group( array(
		'key'                   => 'group_vatan_track',
		'title'                 => __( 'Track', 'vatan-event' ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'location'              => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'track',
				),
			),
		),
		'fields' => array(

			// ---------- Source ----------
			array(
				'key'           => 'field_vatan_track_is_live_stream',
				'label'         => __( 'Live radio stream', 'vatan-event' ),
				'name'          => 'track_is_live_stream',
				'type'          => 'true_false',
				'instructions'  => __( 'Toggle on if this entry is a continuous live radio stream rather than a fixed-length song. Hides the file upload and reveals the Stream URL field.', 'vatan-event' ),
				'ui'            => 1,
				'default_value' => 0,
			),
			array(
				'key'           => 'field_vatan_track_audio_file',
				'label'         => __( 'Audio file', 'vatan-event' ),
				'name'          => 'track_audio_file',
				'type'          => 'file',
				'instructions'  => __( 'MP3 / M4A / OGG from the Media Library. Used by the in-app player.', 'vatan-event' ),
				'return_format' => 'array', // url + mime_type + filesize
				'library'       => 'all',
				'mime_types'    => 'mp3,m4a,aac,ogg,oga,wav',
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_vatan_track_is_live_stream',
							'operator' => '!=',
							'value'    => '1',
						),
					),
				),
			),
			array(
				'key'           => 'field_vatan_track_external_url',
				'label'         => __( 'Stream URL', 'vatan-event' ),
				'name'          => 'track_external_url',
				'type'          => 'url',
				'instructions'  => __( 'HTTPS stream URL (e.g. an Icecast / Shoutcast endpoint or a CDN-hosted MP3).', 'vatan-event' ),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_vatan_track_is_live_stream',
							'operator' => '==',
							'value'    => '1',
						),
					),
				),
			),

			// ---------- Catalog placement ----------
			array(
				'key'           => 'field_vatan_track_artist',
				'label'         => __( 'Artist', 'vatan-event' ),
				'name'          => 'track_artist',
				'type'          => 'post_object',
				'instructions'  => __( 'Performer of this track. Separate from the album artist so featured/collab tracks can credit someone different.', 'vatan-event' ),
				'post_type'     => array( 'artist' ),
				'return_format' => 'id',
				'allow_null'    => 1,
				'ui'            => 1,
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'           => 'field_vatan_track_album',
				'label'         => __( 'Album', 'vatan-event' ),
				'name'          => 'track_album',
				'type'          => 'post_object',
				'instructions'  => __( 'Optional. Singles can be left blank.', 'vatan-event' ),
				'post_type'     => array( 'album' ),
				'return_format' => 'id',
				'allow_null'    => 1,
				'ui'            => 1,
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'           => 'field_vatan_track_track_number',
				'label'         => __( 'Track number', 'vatan-event' ),
				'name'          => 'track_track_number',
				'type'          => 'number',
				'instructions'  => __( 'Position within the album. Used for ordering.', 'vatan-event' ),
				'min'           => 1,
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'           => 'field_vatan_track_duration_seconds',
				'label'         => __( 'Duration (seconds)', 'vatan-event' ),
				'name'          => 'track_duration_seconds',
				'type'          => 'number',
				'instructions'  => __( 'Length in seconds. Leave blank — the player computes it on first load.', 'vatan-event' ),
				'min'           => 0,
				'wrapper'       => array( 'width' => '25' ),
			),
			array(
				'key'           => 'field_vatan_track_explicit',
				'label'         => __( 'Explicit content', 'vatan-event' ),
				'name'          => 'track_explicit',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 0,
				'wrapper'       => array( 'width' => '50' ),
			),

			// ---------- Lyrics ----------
			array(
				'key'          => 'field_vatan_track_lyrics',
				'label'        => __( 'Lyrics', 'vatan-event' ),
				'name'         => 'track_lyrics',
				'type'         => 'textarea',
				'instructions' => __( 'Optional. Plain text — line breaks are preserved.', 'vatan-event' ),
				'rows'         => 10,
				'new_lines'    => 'br',
			),
		),
	) );
}
add_action( 'acf/init', 'vatan_register_track_field_group' );

/* ============================================================
   Album — collection of tracks
   ============================================================ */

function vatan_register_album_field_group() {
	acf_add_local_field_group( array(
		'key'                   => 'group_vatan_album',
		'title'                 => __( 'Album', 'vatan-event' ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'location'              => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'album',
				),
			),
		),
		'fields' => array(
			array(
				'key'           => 'field_vatan_album_artist',
				'label'         => __( 'Artist', 'vatan-event' ),
				'name'          => 'album_artist',
				'type'          => 'post_object',
				'instructions'  => __( 'Primary artist. Tracks can credit different artists in their own Artist field.', 'vatan-event' ),
				'post_type'     => array( 'artist' ),
				'return_format' => 'id',
				'allow_null'    => 1,
				'ui'            => 1,
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'           => 'field_vatan_album_type',
				'label'         => __( 'Type', 'vatan-event' ),
				'name'          => 'album_type',
				'type'          => 'select',
				'choices'       => array(
					'album'       => __( 'Album', 'vatan-event' ),
					'ep'          => __( 'EP', 'vatan-event' ),
					'single'      => __( 'Single', 'vatan-event' ),
					'playlist'    => __( 'Playlist (curated)', 'vatan-event' ),
					'compilation' => __( 'Compilation', 'vatan-event' ),
				),
				'default_value' => 'album',
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'            => 'field_vatan_album_release_date',
				'label'          => __( 'Release date', 'vatan-event' ),
				'name'           => 'album_release_date',
				'type'           => 'date_picker',
				'display_format' => 'Y-m-d',
				'return_format'  => 'Y-m-d',
				'first_day'      => 6,
				'wrapper'        => array( 'width' => '50' ),
			),
			array(
				'key'           => 'field_vatan_album_is_featured',
				'label'         => __( 'Featured', 'vatan-event' ),
				'name'          => 'album_is_featured',
				'type'          => 'true_false',
				'instructions'  => __( 'Show on the music landing-page "Featured" rail and as a candidate for the hero block.', 'vatan-event' ),
				'ui'            => 1,
				'default_value' => 0,
				'wrapper'       => array( 'width' => '50' ),
			),
		),
	) );
}
add_action( 'acf/init', 'vatan_register_album_field_group' );

/* ============================================================
   Artist — profile + social links
   ============================================================ */

function vatan_register_artist_field_group() {
	acf_add_local_field_group( array(
		'key'                   => 'group_vatan_artist',
		'title'                 => __( 'Artist', 'vatan-event' ),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'location'              => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'artist',
				),
			),
		),
		'fields' => array(
			array(
				'key'          => 'field_vatan_artist_country',
				'label'        => __( 'Country', 'vatan-event' ),
				'name'         => 'artist_country',
				'type'         => 'text',
				'instructions' => __( 'Optional. Free text — kept simple in v1.', 'vatan-event' ),
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'key'           => 'field_vatan_artist_is_featured',
				'label'         => __( 'Featured', 'vatan-event' ),
				'name'          => 'artist_is_featured',
				'type'          => 'true_false',
				'instructions'  => __( 'Show on the music landing-page "Featured artists" rail.', 'vatan-event' ),
				'ui'            => 1,
				'default_value' => 0,
				'wrapper'       => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_vatan_artist_links',
				'label'        => __( 'Links', 'vatan-event' ),
				'name'         => 'artist_links',
				'type'         => 'repeater',
				'instructions' => __( 'Social / streaming platforms.', 'vatan-event' ),
				'min'          => 0,
				'layout'       => 'table',
				'button_label' => __( 'Add link', 'vatan-event' ),
				'sub_fields'   => array(
					array(
						'key'     => 'field_vatan_artist_link_platform',
						'label'   => __( 'Platform', 'vatan-event' ),
						'name'    => 'platform',
						'type'    => 'select',
						'choices' => array(
							'website'   => __( 'Website', 'vatan-event' ),
							'instagram' => 'Instagram',
							'youtube'   => 'YouTube',
							'spotify'   => 'Spotify',
							'apple'     => 'Apple Music',
							'soundcloud'=> 'SoundCloud',
							'twitter'   => 'X / Twitter',
							'telegram'  => 'Telegram',
						),
					),
					array(
						'key'   => 'field_vatan_artist_link_url',
						'label' => __( 'URL', 'vatan-event' ),
						'name'  => 'url',
						'type'  => 'url',
					),
				),
			),
		),
	) );
}
add_action( 'acf/init', 'vatan_register_artist_field_group' );
