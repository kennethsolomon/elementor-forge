<?php
/**
 * MCP tool: manage_slider — Smart Slider 3 CRUD remote API.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP\Tools;

use ElementorForge\Safety\Gate;
use ElementorForge\SmartSlider\SliderRepository;
use ElementorForge\SmartSlider\SmartSliderUnavailable;
use WP_Error;

/**
 * Remote MCP wrapper around {@see SliderRepository}. Single tool, multi-action
 * surface so the abilities API only registers one ability instead of seven.
 *
 * Supported actions:
 *   - create_slider     payload: { title, params? }                          → { slider_id }
 *   - update_slider     payload: { slider_id, title, params }                → { updated:bool }
 *   - get_slider        payload: { slider_id }                               → slider row
 *   - delete_slider     payload: { slider_id }                               → { deleted:bool }
 *   - add_slide         payload: { slider_id, title, body?, layers?, params? } → { slide_id }
 *   - update_slide      payload: { slide_id, title?, body?, layers?, params? } → { updated:bool }
 *   - delete_slide      payload: { slide_id }                                → { deleted:bool }
 *   - list_sliders      payload: {}                                          → { sliders:list }
 */
final class ManageSlider {

	public const ACTIONS = array(
		'create_slider',
		'update_slider',
		'get_slider',
		'delete_slider',
		'add_slide',
		'update_slide',
		'delete_slide',
		'list_sliders',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'action' ),
			'additionalProperties' => false,
			'properties'           => array(
				'action'  => array( 'type' => 'string', 'enum' => self::ACTIONS ),
				'payload' => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action' => array( 'type' => 'string' ),
				'result' => array( 'type' => 'object' ),
			),
		);
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute( array $input ) {
		// manage_slider's CRUD surface mixes list/get (reads) with create/update/delete
		// (writes). The Gate treats the entire tool as a write surface in read_only
		// mode — callers that want read access in read_only must run SQL directly.
		// In page_only mode sliders are not posts so the allowlist is bypassed per
		// Gate's special-case handling.
		$gate = Gate::check( 'manage_slider', Gate::ACTION_MODIFY );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$action = isset( $input['action'] ) && is_string( $input['action'] ) ? $input['action'] : '';
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return new WP_Error( 'elementor_forge_invalid_action', 'Unknown manage_slider action.' );
		}
		$payload = isset( $input['payload'] ) && is_array( $input['payload'] ) ? $input['payload'] : array();

		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return new WP_Error( 'elementor_forge_no_wpdb', 'Global $wpdb is not available.' );
		}
		$repo = new SliderRepository( $wpdb );

		try {
			$result = self::dispatch( $repo, $action, $payload );
		} catch ( SmartSliderUnavailable $e ) {
			return new WP_Error( 'elementor_forge_smart_slider_unavailable', $e->getMessage() );
		}

		return array(
			'action' => $action,
			'result' => $result,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private static function dispatch( SliderRepository $repo, string $action, array $payload ): array {
		switch ( $action ) {
			case 'create_slider':
				$title  = self::str( $payload, 'title' );
				$params = self::arr( $payload, 'params' );
				return array( 'slider_id' => $repo->create_slider( $title, $params ) );

			case 'update_slider':
				$id     = self::int( $payload, 'slider_id' );
				$title  = self::str( $payload, 'title' );
				$params = self::arr( $payload, 'params' );
				return array( 'updated' => $repo->update_slider( $id, $title, $params ) );

			case 'get_slider':
				$row = $repo->get_slider( self::int( $payload, 'slider_id' ) );
				return array( 'slider' => $row ?? array() );

			case 'delete_slider':
				return array( 'deleted' => $repo->delete_slider( self::int( $payload, 'slider_id' ) ) );

			case 'add_slide':
				return array( 'slide_id' => $repo->add_slide( self::int( $payload, 'slider_id' ), $payload ) );

			case 'update_slide':
				return array( 'updated' => $repo->update_slide( self::int( $payload, 'slide_id' ), $payload ) );

			case 'delete_slide':
				return array( 'deleted' => $repo->delete_slide( self::int( $payload, 'slide_id' ) ) );

			case 'list_sliders':
				return array( 'sliders' => $repo->list_sliders() );

			default:
				return array();
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function str( array $payload, string $key, string $fallback = '' ): string {
		return isset( $payload[ $key ] ) && is_string( $payload[ $key ] ) ? $payload[ $key ] : $fallback;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function int( array $payload, string $key, int $fallback = 0 ): int {
		if ( isset( $payload[ $key ] ) && ( is_int( $payload[ $key ] ) || is_numeric( $payload[ $key ] ) ) ) {
			return (int) $payload[ $key ];
		}
		return $fallback;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private static function arr( array $payload, string $key ): array {
		return isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ? $payload[ $key ] : array();
	}
}
