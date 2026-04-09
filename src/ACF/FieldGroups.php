<?php
/**
 * ACF field group definitions — Free and Pro modes.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\ACF;

use ElementorForge\CPT\PostTypes;

/**
 * Pure data. {@see Registrar} loads it into ACF. The catalogue is split by mode
 * (`free` vs `pro`) so the Free fallback (related CPT + Loop Grid) and the Pro
 * repeater path are both first-class, tested branches.
 */
final class FieldGroups {

	/**
	 * Return every field group for the given mode.
	 *
	 * @param 'free'|'pro' $mode
	 * @return list<array<string, mixed>>
	 */
	public static function all( string $mode ): array {
		$groups = array(
			self::location_group( $mode ),
			self::service_group( $mode ),
			self::testimonial_group(),
			self::faq_group(),
		);

		/** @var list<array<string, mixed>> */
		return $groups;
	}

	/**
	 * Location ACF field group. Fields common to all modes plus an optional
	 * testimonials/faqs repeater in Pro mode.
	 *
	 * @param 'free'|'pro' $mode
	 * @return array<string, mixed>
	 */
	private static function location_group( string $mode ): array {
		$fields = array(
			self::text_field( 'suburb_name', 'Suburb Name' ),
			self::text_field( 'local_phone', 'Local Phone' ),
			self::image_field( 'hero_image', 'Hero Image' ),
			self::url_field( 'map_embed_url', 'Map Embed URL' ),
			self::text_field( 'meta_title', 'SEO Meta Title' ),
			self::textarea_field( 'meta_description', 'SEO Meta Description' ),
		);

		if ( 'pro' === $mode ) {
			$fields[] = self::testimonials_repeater();
			$fields[] = self::faqs_repeater();
		} else {
			$fields[] = self::relationship_field( 'related_testimonials', 'Testimonials', array( PostTypes::TESTIMONIAL ) );
			$fields[] = self::relationship_field( 'related_faqs', 'FAQs', array( PostTypes::FAQ ) );
		}

		return array(
			'key'                   => 'group_ef_location',
			'title'                 => 'Location Details',
			'fields'                => $fields,
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => PostTypes::LOCATION,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
		);
	}

	/**
	 * Service ACF field group.
	 *
	 * @param 'free'|'pro' $mode
	 * @return array<string, mixed>
	 */
	private static function service_group( string $mode ): array {
		$fields = array(
			self::text_field( 'service_name', 'Service Name' ),
			self::wysiwyg_field( 'description', 'Description' ),
			self::select_field(
				'price_tier',
				'Price Tier',
				array(
					'budget'  => 'Budget',
					'mid'     => 'Mid-range',
					'premium' => 'Premium',
				)
			),
			self::image_field( 'hero_image', 'Hero Image' ),
		);

		if ( 'pro' === $mode ) {
			$fields[] = self::faqs_repeater();
		} else {
			$fields[] = self::relationship_field( 'related_faqs', 'FAQs', array( PostTypes::FAQ ) );
		}

		return array(
			'key'                   => 'group_ef_service',
			'title'                 => 'Service Details',
			'fields'                => $fields,
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => PostTypes::SERVICE,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
		);
	}

	/**
	 * Testimonial CPT ACF field group. Used in Free mode as the repeater replacement.
	 *
	 * @return array<string, mixed>
	 */
	private static function testimonial_group(): array {
		return array(
			'key'        => 'group_ef_testimonial',
			'title'      => 'Testimonial Details',
			'fields'     => array(
				self::text_field( 'author_name', 'Author Name' ),
				self::text_field( 'author_role', 'Author Role' ),
				self::number_field( 'rating', 'Rating (1-5)' ),
			),
			'location'   => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => PostTypes::TESTIMONIAL,
					),
				),
			),
			'menu_order' => 0,
			'active'     => true,
		);
	}

	/**
	 * FAQ CPT ACF field group.
	 *
	 * @return array<string, mixed>
	 */
	private static function faq_group(): array {
		return array(
			'key'        => 'group_ef_faq',
			'title'      => 'FAQ Details',
			'fields'     => array(
				self::text_field( 'question', 'Question' ),
				self::textarea_field( 'answer', 'Answer' ),
			),
			'location'   => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => PostTypes::FAQ,
					),
				),
			),
			'menu_order' => 0,
			'active'     => true,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function text_field( string $name, string $label ): array {
		return array(
			'key'   => 'field_ef_' . $name,
			'label' => $label,
			'name'  => $name,
			'type'  => 'text',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function textarea_field( string $name, string $label ): array {
		return array(
			'key'   => 'field_ef_' . $name,
			'label' => $label,
			'name'  => $name,
			'type'  => 'textarea',
			'rows'  => 3,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function wysiwyg_field( string $name, string $label ): array {
		return array(
			'key'          => 'field_ef_' . $name,
			'label'        => $label,
			'name'         => $name,
			'type'         => 'wysiwyg',
			'tabs'         => 'all',
			'toolbar'      => 'full',
			'media_upload' => 1,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function image_field( string $name, string $label ): array {
		return array(
			'key'           => 'field_ef_' . $name,
			'label'         => $label,
			'name'          => $name,
			'type'          => 'image',
			'return_format' => 'array',
			'preview_size'  => 'medium',
			'library'       => 'all',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function url_field( string $name, string $label ): array {
		return array(
			'key'   => 'field_ef_' . $name,
			'label' => $label,
			'name'  => $name,
			'type'  => 'url',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function number_field( string $name, string $label ): array {
		return array(
			'key'   => 'field_ef_' . $name,
			'label' => $label,
			'name'  => $name,
			'type'  => 'number',
			'min'   => 1,
			'max'   => 5,
		);
	}

	/**
	 * @param array<string, string> $choices
	 * @return array<string, mixed>
	 */
	private static function select_field( string $name, string $label, array $choices ): array {
		return array(
			'key'           => 'field_ef_' . $name,
			'label'         => $label,
			'name'          => $name,
			'type'          => 'select',
			'choices'       => $choices,
			'default_value' => '',
			'allow_null'    => 1,
			'return_format' => 'value',
		);
	}

	/**
	 * @param list<string> $post_types
	 * @return array<string, mixed>
	 */
	private static function relationship_field( string $name, string $label, array $post_types ): array {
		return array(
			'key'           => 'field_ef_' . $name,
			'label'         => $label,
			'name'          => $name,
			'type'          => 'relationship',
			'post_type'     => $post_types,
			'filters'       => array( 'search' ),
			'return_format' => 'id',
		);
	}

	/**
	 * ACF Pro repeater for testimonials. Only emitted in `pro` mode — ACF Free
	 * has no repeater support.
	 *
	 * @return array<string, mixed>
	 */
	private static function testimonials_repeater(): array {
		return array(
			'key'          => 'field_ef_testimonials',
			'label'        => 'Testimonials',
			'name'         => 'testimonials',
			'type'         => 'repeater',
			'layout'       => 'block',
			'button_label' => 'Add Testimonial',
			'sub_fields'   => array(
				self::text_field( 'author_name', 'Author Name' ),
				self::textarea_field( 'quote', 'Quote' ),
				self::number_field( 'rating', 'Rating (1-5)' ),
			),
		);
	}

	/**
	 * ACF Pro repeater for FAQs. Only emitted in `pro` mode.
	 *
	 * @return array<string, mixed>
	 */
	private static function faqs_repeater(): array {
		return array(
			'key'          => 'field_ef_faqs',
			'label'        => 'FAQs',
			'name'         => 'faqs',
			'type'         => 'repeater',
			'layout'       => 'block',
			'button_label' => 'Add FAQ',
			'sub_fields'   => array(
				self::text_field( 'question', 'Question' ),
				self::textarea_field( 'answer', 'Answer' ),
			),
		);
	}
}
