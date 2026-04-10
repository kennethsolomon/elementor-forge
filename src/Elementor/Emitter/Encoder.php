<?php
/**
 * _elementor_data encoding dance.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

/**
 * Elementor stores its element tree as JSON in the `_elementor_data` postmeta
 * entry. There are two properties of this storage that bite every plugin
 * generating Elementor content programmatically:
 *
 *   1. `update_post_meta()` calls `wp_slash()` on its input, which adds
 *      backslashes in front of quotes. If you hand it a JSON string, every
 *      quote gets an extra backslash and the JSON becomes corrupt when
 *      Elementor reads it back.
 *
 *   2. Elementor's own `_elementor_data` read path expects the string to have
 *      been slashed, so it calls `wp_unslash()` on read. If you pre-unslash
 *      your JSON, the read path double-unslashes and corrupts escaped
 *      characters.
 *
 * The canonical encoding dance is:
 *
 *     json_encode( $tree )  →  $json
 *     wp_slash( $json )     →  $slashed
 *     update_post_meta( id, '_elementor_data', $slashed )
 *
 * This class centralizes that pattern so every caller goes through a single
 * tested path, and provides the matching read-side helper for parsing the
 * meta back into a PHP array for round-trip updates.
 */
final class Encoder {

	/**
	 * Encode an Elementor content tree (list of top-level element arrays) into
	 * the slashed JSON string that `_elementor_data` expects.
	 *
	 * @param list<array<string, mixed>> $content
	 */
	public static function encode_for_meta( array $content ): string {
		$json = wp_json_encode( $content, JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			throw new EncoderException(
				'Failed to JSON-encode Elementor content tree. Possible cause: non-UTF-8 data in widget settings.'
			);
		}
		return wp_slash( $json );
	}

	/**
	 * Decode `_elementor_data` back into a PHP list of element arrays.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function decode_from_meta( string $meta_value ): array {
		$unslashed = wp_unslash( $meta_value );
		if ( ! is_string( $unslashed ) || '' === $unslashed ) {
			return array();
		}
		$decoded = json_decode( $unslashed, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		/** @var list<array<string, mixed>> */
		return $decoded;
	}

	/**
	 * Write an entire {@see Document}'s content array to `_elementor_data` on
	 * the given post, following the slash dance.
	 */
	public static function write_document( int $post_id, Document $document ): bool {
		$payload = $document->to_array();
		$content = isset( $payload['content'] ) && is_array( $payload['content'] ) ? $payload['content'] : array();
		/** @var list<array<string, mixed>> $content */
		$slashed = self::encode_for_meta( $content );
		return false !== update_post_meta( $post_id, '_elementor_data', $slashed );
	}

	/**
	 * Read an existing post's `_elementor_data` and return the parsed
	 * {@see Document}. Returns null if the post has no Elementor data.
	 */
	public static function read_document( int $post_id, Parser $parser ): ?Document {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$content = self::decode_from_meta( $raw );
		if ( empty( $content ) ) {
			return null;
		}
		$title = get_the_title( $post_id );
		return $parser->parse_document(
			array(
				'content'       => $content,
				'title'         => is_string( $title ) ? $title : '',
				'type'          => 'page',
				'page_settings' => array(),
			)
		);
	}
}
