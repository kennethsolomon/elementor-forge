<?php
/**
 * Custom post type definitions for Elementor Forge.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\CPT;

/**
 * Declarative catalog of every CPT the plugin registers. Purely static data —
 * consumed by {@see Registrar} at `init` and by `uninstall.php` for cleanup.
 */
final class PostTypes {

	public const LOCATION    = 'ef_location';
	public const SERVICE     = 'ef_service';
	public const TESTIMONIAL = 'ef_testimonial';
	public const FAQ         = 'ef_faq';

	/**
	 * Return the full CPT definition set. Keys are post type slugs, values
	 * are the args passed straight to {@see register_post_type()}.
	 *
	 * The slugs are shortened to ef_* because WordPress enforces a 20-character
	 * limit on post_type values — 'elementor_forge_location' would overflow.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return array(
			self::LOCATION    => array(
				'labels'              => array(
					'name'               => 'Locations',
					'singular_name'      => 'Location',
					'menu_name'          => 'Locations',
					'add_new_item'       => 'Add New Location',
					'edit_item'          => 'Edit Location',
					'new_item'           => 'New Location',
					'view_item'          => 'View Location',
					'search_items'       => 'Search Locations',
					'not_found'          => 'No locations found.',
					'not_found_in_trash' => 'No locations found in trash.',
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-location',
				'menu_position'       => 25,
				'has_archive'         => true,
				'rewrite'             => array(
					'slug'       => 'locations',
					'with_front' => false,
				),
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'capability_type'     => 'post',
				'exclude_from_search' => false,
			),
			self::SERVICE     => array(
				'labels'              => array(
					'name'               => 'Services',
					'singular_name'      => 'Service',
					'menu_name'          => 'Services',
					'add_new_item'       => 'Add New Service',
					'edit_item'          => 'Edit Service',
					'new_item'           => 'New Service',
					'view_item'          => 'View Service',
					'search_items'       => 'Search Services',
					'not_found'          => 'No services found.',
					'not_found_in_trash' => 'No services found in trash.',
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-admin-tools',
				'menu_position'       => 26,
				'has_archive'         => true,
				'rewrite'             => array(
					'slug'       => 'services',
					'with_front' => false,
				),
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'capability_type'     => 'post',
				'exclude_from_search' => false,
			),
			self::TESTIMONIAL => array(
				'labels'              => array(
					'name'               => 'Testimonials',
					'singular_name'      => 'Testimonial',
					'menu_name'          => 'Testimonials',
					'add_new_item'       => 'Add New Testimonial',
					'edit_item'          => 'Edit Testimonial',
					'new_item'           => 'New Testimonial',
					'view_item'          => 'View Testimonial',
					'search_items'       => 'Search Testimonials',
					'not_found'          => 'No testimonials found.',
					'not_found_in_trash' => 'No testimonials found in trash.',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-format-quote',
				'menu_position'       => 27,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			),
			self::FAQ         => array(
				'labels'              => array(
					'name'               => 'FAQs',
					'singular_name'      => 'FAQ',
					'menu_name'          => 'FAQs',
					'add_new_item'       => 'Add New FAQ',
					'edit_item'          => 'Edit FAQ',
					'new_item'           => 'New FAQ',
					'view_item'          => 'View FAQ',
					'search_items'       => 'Search FAQs',
					'not_found'          => 'No FAQs found.',
					'not_found_in_trash' => 'No FAQs found in trash.',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-editor-help',
				'menu_position'       => 28,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			),
		);
	}

	/**
	 * Return just the slug list — used by uninstall.php for post cleanup.
	 *
	 * @return list<string>
	 */
	public static function slugs(): array {
		return array_keys( self::all() );
	}
}
