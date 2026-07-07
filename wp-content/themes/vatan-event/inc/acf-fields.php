<?php
/**
 * ACF field group registration for the `event` CPT.
 *
 * NOTE on ACF Free vs Pro: the `gallery` and `repeater` field types are
 * Pro-only. With ACF Free installed they are silently ignored at registration
 * (no fatals, just absent in the editor). Install ACF Pro or the
 * Secure Custom Fields fork to use them.
 *
 * @package VatanEvent
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	return;
}

/**
 * Register the Event Details field group.
 */
function vatan_register_event_field_group() {
	acf_add_local_field_group( array(
		'key'                   => 'group_vatan_event_details',
		'title'                 => __( 'Event Details', 'vatan-event' ),
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
					'value'    => 'event',
				),
			),
		),
		'fields'                => array(

			// ---------- Schedule ----------
			array(
				'key'            => 'field_vatan_event_date',
				'label'          => __( 'Date', 'vatan-event' ),
				'name'           => 'event_date',
				'type'           => 'date_picker',
				'display_format' => 'Y-m-d',
				'return_format'  => 'Y-m-d',
				'first_day'      => 6, // Saturday — matches the Iranian week.
				'wrapper'        => array( 'width' => '33' ),
			),
			array(
				'key'            => 'field_vatan_event_time_start',
				'label'          => __( 'Start Time', 'vatan-event' ),
				'name'           => 'event_time_start',
				'type'           => 'time_picker',
				'display_format' => 'H:i',
				'return_format'  => 'H:i',
				'wrapper'        => array( 'width' => '33' ),
			),
			array(
				'key'            => 'field_vatan_event_time_end',
				'label'          => __( 'End Time', 'vatan-event' ),
				'name'           => 'event_time_end',
				'type'           => 'time_picker',
				'display_format' => 'H:i',
				'return_format'  => 'H:i',
				'wrapper'        => array( 'width' => '34' ),
			),

			// ---------- Venue ----------
			array(
				'key'     => 'field_vatan_event_venue',
				'label'   => __( 'Venue', 'vatan-event' ),
				'name'    => 'event_venue',
				'type'    => 'text',
				'wrapper' => array( 'width' => '60' ),
			),
			array(
				'key'         => 'field_vatan_event_venue_map_link',
				'label'       => __( 'Venue Map Link', 'vatan-event' ),
				'name'        => 'event_venue_map_link',
				'type'        => 'url',
				'placeholder' => 'https://maps.google.com/…',
				'wrapper'     => array( 'width' => '40' ),
			),
			array(
				'key'          => 'field_vatan_event_venue_lat',
				'label'        => __( 'Venue Latitude', 'vatan-event' ),
				'name'         => 'event_venue_lat',
				'type'         => 'text',
				'placeholder'  => '52.5074',
				'instructions' => __( 'Decimal degrees, e.g. 52.5074 for Berlin. Leave both lat & lng empty to skip the map embed.', 'vatan-event' ),
				'wrapper'      => array( 'width' => '20' ),
			),
			array(
				'key'         => 'field_vatan_event_venue_lng',
				'label'       => __( 'Venue Longitude', 'vatan-event' ),
				'name'        => 'event_venue_lng',
				'type'        => 'text',
				'placeholder' => '13.4053',
				'wrapper'     => array( 'width' => '20' ),
			),

			// ---------- Organizer ----------
			array(
				'key'           => 'field_vatan_event_organizer',
				'label'         => __( 'Organizer', 'vatan-event' ),
				'name'          => 'event_organizer',
				'type'          => 'post_object',
				'post_type'     => array( 'organizer' ),
				'return_format' => 'id',
				'ui'            => 1,
				'allow_null'    => 1,
				'multiple'      => 0,
				'instructions'  => __( 'Pick the organizer running this event. Manage organizers under Vatan Event → Organizers.', 'vatan-event' ),
				'wrapper'       => array( 'width' => '100' ),
			),

			// ---------- Misc ----------
			array(
				'key'     => 'field_vatan_event_duration',
				'label'   => __( 'Duration', 'vatan-event' ),
				'name'    => 'event_duration',
				'type'    => 'number',
				'min'     => 0,
				'append'  => __( 'minutes', 'vatan-event' ),
				'wrapper' => array( 'width' => '33' ),
			),
			array(
				'key'     => 'field_vatan_event_age_limit',
				'label'   => __( 'Age Limit', 'vatan-event' ),
				'name'    => 'event_age_limit',
				'type'    => 'number',
				'min'     => 0,
				'append'  => __( '+ years', 'vatan-event' ),
				'wrapper' => array( 'width' => '33' ),
			),
			array(
				'key'           => 'field_vatan_event_status',
				'label'         => __( 'Status', 'vatan-event' ),
				'name'          => 'event_status',
				'type'          => 'select',
				'choices'       => array(
					'upcoming'  => __( 'Upcoming', 'vatan-event' ),
					'ongoing'   => __( 'Ongoing', 'vatan-event' ),
					'finished'  => __( 'Finished', 'vatan-event' ),
					'cancelled' => __( 'Cancelled', 'vatan-event' ),
				),
				'default_value' => 'upcoming',
				'return_format' => 'value',
				'allow_null'    => 0,
				'multiple'      => 0,
				'wrapper'       => array( 'width' => '34' ),
			),
			array(
				'key'     => 'field_vatan_event_is_featured',
				'label'   => __( 'Featured Event', 'vatan-event' ),
				'name'    => 'event_is_featured',
				'type'    => 'true_false',
				'ui'      => 1,
				'wrapper' => array( 'width' => '50' ),
			),

			// ---------- Gallery (ACF Pro) ----------
			array(
				'key'           => 'field_vatan_event_gallery',
				'label'         => __( 'Gallery', 'vatan-event' ),
				'name'          => 'event_gallery',
				'type'          => 'gallery',
				'instructions'  => __( 'Requires ACF Pro / Secure Custom Fields.', 'vatan-event' ),
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'insert'        => 'append',
				'library'       => 'all',
			),

			// ---------- Ticket Types (repeater — ACF Pro) ----------
			array(
				'key'          => 'field_vatan_ticket_types',
				'label'        => __( 'Ticket Types', 'vatan-event' ),
				'name'         => 'ticket_types',
				'type'         => 'repeater',
				'instructions' => __( 'Requires ACF Pro / Secure Custom Fields.', 'vatan-event' ),
				'layout'       => 'block',
				'button_label' => __( 'Add Ticket Type', 'vatan-event' ),
				'min'          => 0,
				'sub_fields'   => array(
					array(
						'key'     => 'field_vatan_ticket_name',
						'label'   => __( 'Name', 'vatan-event' ),
						'name'    => 'ticket_name',
						'type'    => 'text',
						'wrapper' => array( 'width' => '30' ),
					),
					array(
						'key'     => 'field_vatan_ticket_price',
						'label'   => __( 'Price', 'vatan-event' ),
						'name'    => 'ticket_price',
						'type'    => 'number',
						'min'     => 0,
						'wrapper' => array( 'width' => '20' ),
					),
					array(
						'key'     => 'field_vatan_ticket_color',
						'label'   => __( 'Color', 'vatan-event' ),
						'name'    => 'ticket_color',
						'type'    => 'color_picker',
						'wrapper' => array( 'width' => '20' ),
					),
					array(
						'key'     => 'field_vatan_ticket_capacity',
						'label'   => __( 'Capacity', 'vatan-event' ),
						'name'    => 'ticket_capacity',
						'type'    => 'number',
						'min'     => 0,
						'wrapper' => array( 'width' => '15' ),
					),
					array(
						'key'           => 'field_vatan_ticket_sold',
						'label'         => __( 'Sold', 'vatan-event' ),
						'name'          => 'ticket_sold',
						'type'          => 'number',
						'min'           => 0,
						'default_value' => 0,
						'instructions'  => __( 'Auto-incremented by the WooCommerce order hook. Do not edit by hand.', 'vatan-event' ),
						'wrapper'       => array( 'width' => '15' ),
					),
				),
			),

			// ---------- Seat Map ----------
			array(
				'key'     => 'field_vatan_seat_map_enabled',
				'label'   => __( 'Enable Seat Map', 'vatan-event' ),
				'name'    => 'seat_map_enabled',
				'type'    => 'true_false',
				'ui'      => 1,
				'wrapper' => array( 'width' => '100' ),
			),
			array(
				'key'               => 'field_vatan_seat_map_rows',
				'label'             => __( 'Rows', 'vatan-event' ),
				'name'              => 'seat_map_rows',
				'type'              => 'number',
				'min'               => 1,
				'wrapper'           => array( 'width' => '50' ),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_vatan_seat_map_enabled',
							'operator' => '==',
							'value'    => '1',
						),
					),
				),
			),
			array(
				'key'               => 'field_vatan_seat_map_cols',
				'label'             => __( 'Columns', 'vatan-event' ),
				'name'              => 'seat_map_cols',
				'type'              => 'number',
				'min'               => 1,
				'wrapper'           => array( 'width' => '50' ),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_vatan_seat_map_enabled',
							'operator' => '==',
							'value'    => '1',
						),
					),
				),
			),
			array(
				'key'               => 'field_vatan_seat_map_config',
				'label'             => __( 'Seat Map Configuration (JSON)', 'vatan-event' ),
				'name'              => 'seat_map_config',
				'type'              => 'textarea',
				'rows'              => 16,
				'instructions'      => __( "JSON describing zones, reserved seats, hallways, and round tables. Example:\n\n{\n  \"rows\": 8, \"cols\": 12,\n  \"sections\": [\n    { \"rows\": [1,2], \"type\": \"vip\", \"price\": 2500000, \"color\": \"#FF2D78\" },\n    { \"rows\": [3,4,5,6,7,8], \"type\": \"economy\", \"price\": 1500000, \"color\": \"#7C3AED\" }\n  ],\n  \"reserved\": [\"1-1\", \"T1-3\"],\n  \"hallways\": [\"4-6\", \"4-7\", \"5-6\", \"5-7\"],\n  \"tables\": [\n    { \"id\": \"T1\", \"seats\": 8, \"label\": \"Table 1\",\n      \"type\": \"vip\", \"price\": 3500000, \"color\": \"#FF2D78\" }\n  ]\n}\n\n• `hallways` are grid cells (row-col) that render as empty space — use them to create aisles.\n• `tables` are round tables with N seats around them. Each table id must start with `T` (e.g. T1, Tvip). Seat key inside a table is `<id>-<n>`, e.g. T1-5.", 'vatan-event' ),
				'conditional_logic' => array(
					array(
						array(
							'field'    => 'field_vatan_seat_map_enabled',
							'operator' => '==',
							'value'    => '1',
						),
					),
				),
			),
		),
	) );
}
add_action( 'acf/init', 'vatan_register_event_field_group' );

/**
 * Register the Organizer Profile field group.
 *
 * Logo lives on the featured image; everything else is here.
 */
function vatan_register_organizer_field_group() {
	acf_add_local_field_group( array(
		'key'                   => 'group_vatan_organizer_details',
		'title'                 => __( 'Organizer Details', 'vatan-event' ),
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
					'value'    => 'organizer',
				),
			),
		),
		'fields'                => array(

			// ---------- Identity ----------
			array(
				'key'         => 'field_vatan_organizer_tagline',
				'label'       => __( 'Tagline', 'vatan-event' ),
				'name'        => 'organizer_tagline',
				'type'        => 'text',
				'placeholder' => __( 'e.g. Event production since 2014', 'vatan-event' ),
				'wrapper'     => array( 'width' => '100' ),
			),

			// ---------- Contact ----------
			array(
				'key'         => 'field_vatan_organizer_email',
				'label'       => __( 'Email', 'vatan-event' ),
				'name'        => 'organizer_email',
				'type'        => 'email',
				'placeholder' => 'hello@example.com',
				'wrapper'     => array( 'width' => '50' ),
			),
			array(
				'key'         => 'field_vatan_organizer_phone',
				'label'       => __( 'Phone', 'vatan-event' ),
				'name'        => 'organizer_phone',
				'type'        => 'text',
				'placeholder' => '+49 176 …',
				'wrapper'     => array( 'width' => '50' ),
			),
			array(
				'key'         => 'field_vatan_organizer_website',
				'label'       => __( 'Website', 'vatan-event' ),
				'name'        => 'organizer_website',
				'type'        => 'url',
				'placeholder' => 'https://…',
				'wrapper'     => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_vatan_organizer_whatsapp',
				'label'        => __( 'WhatsApp link', 'vatan-event' ),
				'name'         => 'organizer_whatsapp',
				'type'         => 'url',
				'placeholder'  => 'https://wa.me/4917…',
				'instructions' => __( 'Full wa.me deeplink. Used as the primary "Contact" button on the public profile.', 'vatan-event' ),
				'wrapper'      => array( 'width' => '50' ),
			),

			// ---------- Social ----------
			array(
				'key'         => 'field_vatan_organizer_instagram',
				'label'       => __( 'Instagram', 'vatan-event' ),
				'name'        => 'organizer_instagram',
				'type'        => 'url',
				'placeholder' => 'https://instagram.com/…',
				'wrapper'     => array( 'width' => '50' ),
			),
			array(
				'key'         => 'field_vatan_organizer_telegram',
				'label'       => __( 'Telegram', 'vatan-event' ),
				'name'        => 'organizer_telegram',
				'type'        => 'url',
				'placeholder' => 'https://t.me/…',
				'wrapper'     => array( 'width' => '50' ),
			),
		),
	) );
}
add_action( 'acf/init', 'vatan_register_organizer_field_group' );
