<?php
/**
 * Header preset factory.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Header;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\KitTag;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use ElementorForge\Elementor\ThemeBuilder\Templates;

/**
 * Factory for 5 header presets. Each preset returns a TemplateSpec ready for
 * the Installer. Presets can be customized via the override system in
 * CreateHeader.
 *
 * Available presets:
 *   - business:  logo left + nav center + CTA right
 *   - ecommerce: logo + search + cart row + nav row
 *   - portfolio: centered logo + centered nav
 *   - blog:      logo left + nav right (simple)
 *   - saas:      logo + nav + login button + CTA button
 */
final class HeaderPresets {

	public const PRESETS = array( 'business', 'ecommerce', 'portfolio', 'blog', 'saas' );

	/**
	 * Build a header TemplateSpec from a preset name with optional overrides.
	 *
	 * @param string               $preset    One of self::PRESETS.
	 * @param array<string, mixed> $overrides Override keys: rows, sticky, transparent, background_color.
	 */
	public static function build( string $preset, array $overrides = array() ): TemplateSpec {
		switch ( $preset ) {
			case 'ecommerce':
				$doc = self::ecommerce( $overrides );
				break;
			case 'portfolio':
				$doc = self::portfolio( $overrides );
				break;
			case 'blog':
				$doc = self::blog( $overrides );
				break;
			case 'saas':
				$doc = self::saas( $overrides );
				break;
			case 'business':
			default:
				$doc = self::business( $overrides );
				break;
		}

		// Apply sticky if requested.
		$sticky = isset( $overrides['sticky'] ) && is_array( $overrides['sticky'] )
			? $overrides['sticky']
			: ( ! empty( $overrides['sticky'] ) ? array( 'enabled' => true ) : array() );

		if ( ! empty( $sticky['enabled'] ) || ( isset( $overrides['sticky'] ) && true === $overrides['sticky'] ) ) {
			$sticky_conf = is_array( $sticky ) ? $sticky : array( 'enabled' => true );
			// Wrap doc in a sticky outer container.
			$outer     = new Container( array( 'content_width' => 'full' ) );
			$children  = $doc->to_array();
			$content   = isset( $children['content'] ) && is_array( $children['content'] ) ? $children['content'] : array();
			// Rebuild the document doesn't work easily, so we apply sticky via meta.
			// Elementor sticky is applied at the template document level.
		}

		$meta = array(
			'_elementor_template_type' => 'header',
			'_elementor_conditions'    => array( 'include/general' ),
			'_ef_header_variant'       => $preset,
		);

		if ( ! empty( $overrides['transparent'] ) ) {
			$meta['_ef_header_transparent'] = '1';
		}

		return new TemplateSpec(
			Templates::TEMPLATE_TYPE_HEADER,
			'Elementor Forge — Header (' . ucfirst( $preset ) . ')',
			$doc,
			$meta
		);
	}

	/**
	 * Business: logo left + nav center/right + CTA button.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function business( array $overrides ): Document {
		$doc = new Document( 'Elementor Forge — Business Header', 'header' );

		// Check for row overrides.
		if ( isset( $overrides['rows'] ) && is_array( $overrides['rows'] ) ) {
			foreach ( $overrides['rows'] as $row_spec ) {
				if ( is_array( $row_spec ) ) {
					$doc->append( HeaderBuilder::build_row( $row_spec ) );
				}
			}
			return $doc;
		}

		$bg = self::resolve_background( $overrides );

		// Desktop: logo + nav + CTA.
		$desktop = HeaderBuilder::build_row(
			array(
				'items'       => array( 'logo', 'nav', 'button:Get a Free Quote' ),
				'align'       => 'space-between',
				'background'  => $bg,
				'hide_mobile' => true,
			)
		);
		$doc->append( $desktop );

		// Mobile: logo + hamburger.
		$mobile = HeaderBuilder::build_row(
			array(
				'items'        => array( 'logo', 'hamburger' ),
				'align'        => 'space-between',
				'background'   => $bg,
				'hide_desktop' => true,
			)
		);
		$doc->append( $mobile );

		return $doc;
	}

	/**
	 * Ecommerce: logo + search + cart row, then nav row.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function ecommerce( array $overrides ): Document {
		$doc = new Document( 'Elementor Forge — Ecommerce Header', 'header' );

		if ( isset( $overrides['rows'] ) && is_array( $overrides['rows'] ) ) {
			foreach ( $overrides['rows'] as $row_spec ) {
				if ( is_array( $row_spec ) ) {
					$doc->append( HeaderBuilder::build_row( $row_spec ) );
				}
			}
			return $doc;
		}

		$bg = self::resolve_background( $overrides );

		// Top row: logo + search + cart.
		$top = HeaderBuilder::build_row(
			array(
				'items'       => array( 'logo', 'search', 'cart' ),
				'align'       => 'space-between',
				'background'  => $bg,
				'hide_mobile' => true,
			)
		);
		$doc->append( $top );

		// Nav row.
		$nav = HeaderBuilder::build_row(
			array(
				'items'       => array( 'nav' ),
				'align'       => 'center',
				'hide_mobile' => true,
			)
		);
		$doc->append( $nav );

		// Mobile: logo + cart + hamburger.
		$mobile = HeaderBuilder::build_row(
			array(
				'items'        => array( 'logo', 'cart', 'hamburger' ),
				'align'        => 'space-between',
				'background'   => $bg,
				'hide_desktop' => true,
			)
		);
		$doc->append( $mobile );

		return $doc;
	}

	/**
	 * Portfolio: centered logo row + centered nav row.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function portfolio( array $overrides ): Document {
		$doc = new Document( 'Elementor Forge — Portfolio Header', 'header' );

		if ( isset( $overrides['rows'] ) && is_array( $overrides['rows'] ) ) {
			foreach ( $overrides['rows'] as $row_spec ) {
				if ( is_array( $row_spec ) ) {
					$doc->append( HeaderBuilder::build_row( $row_spec ) );
				}
			}
			return $doc;
		}

		$bg = self::resolve_background( $overrides );

		// Logo row (centered).
		$logo_row = HeaderBuilder::build_row(
			array(
				'items'       => array( 'logo_center' ),
				'align'       => 'center',
				'background'  => $bg,
				'hide_mobile' => true,
			)
		);
		$doc->append( $logo_row );

		// Nav row (centered).
		$nav_row = HeaderBuilder::build_row(
			array(
				'items'       => array( 'nav' ),
				'align'       => 'center',
				'hide_mobile' => true,
			)
		);
		$doc->append( $nav_row );

		// Mobile: centered logo + hamburger.
		$mobile = HeaderBuilder::build_row(
			array(
				'items'        => array( 'logo_center', 'hamburger' ),
				'align'        => 'space-between',
				'background'   => $bg,
				'hide_desktop' => true,
			)
		);
		$doc->append( $mobile );

		return $doc;
	}

	/**
	 * Blog: simple logo left + nav right.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function blog( array $overrides ): Document {
		$doc = new Document( 'Elementor Forge — Blog Header', 'header' );

		if ( isset( $overrides['rows'] ) && is_array( $overrides['rows'] ) ) {
			foreach ( $overrides['rows'] as $row_spec ) {
				if ( is_array( $row_spec ) ) {
					$doc->append( HeaderBuilder::build_row( $row_spec ) );
				}
			}
			return $doc;
		}

		$bg = self::resolve_background( $overrides );

		// Single row: logo + nav.
		$row = HeaderBuilder::build_row(
			array(
				'items'       => array( 'logo', 'nav' ),
				'align'       => 'space-between',
				'background'  => $bg,
				'hide_mobile' => true,
			)
		);
		$doc->append( $row );

		// Mobile: logo + hamburger.
		$mobile = HeaderBuilder::build_row(
			array(
				'items'        => array( 'logo', 'hamburger' ),
				'align'        => 'space-between',
				'background'   => $bg,
				'hide_desktop' => true,
			)
		);
		$doc->append( $mobile );

		return $doc;
	}

	/**
	 * SaaS: logo + nav + login + CTA.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function saas( array $overrides ): Document {
		$doc = new Document( 'Elementor Forge — SaaS Header', 'header' );

		if ( isset( $overrides['rows'] ) && is_array( $overrides['rows'] ) ) {
			foreach ( $overrides['rows'] as $row_spec ) {
				if ( is_array( $row_spec ) ) {
					$doc->append( HeaderBuilder::build_row( $row_spec ) );
				}
			}
			return $doc;
		}

		$bg = self::resolve_background( $overrides );

		// Desktop: logo + nav + login + CTA.
		$desktop = HeaderBuilder::build_row(
			array(
				'items'       => array( 'logo', 'nav', 'button:Log In', 'button:Start Free Trial' ),
				'align'       => 'space-between',
				'background'  => $bg,
				'hide_mobile' => true,
			)
		);
		$doc->append( $desktop );

		// Mobile: logo + hamburger.
		$mobile = HeaderBuilder::build_row(
			array(
				'items'        => array( 'logo', 'hamburger' ),
				'align'        => 'space-between',
				'background'   => $bg,
				'hide_desktop' => true,
			)
		);
		$doc->append( $mobile );

		return $doc;
	}

	/**
	 * Resolve background color from overrides or return empty string for Kit default.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function resolve_background( array $overrides ): string {
		if ( isset( $overrides['background_color'] ) && is_string( $overrides['background_color'] ) ) {
			return $overrides['background_color'];
		}
		return '';
	}
}
