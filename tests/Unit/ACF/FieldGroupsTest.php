<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\ACF;

use ElementorForge\ACF\FieldGroups;
use ElementorForge\CPT\PostTypes;
use PHPUnit\Framework\TestCase;

final class FieldGroupsTest extends TestCase {

	public function test_free_mode_uses_relationship_fields_not_repeaters(): void {
		$groups = FieldGroups::all( 'free' );
		$location = $this->find_group( $groups, 'group_ef_location' );

		$types = array_column( $location['fields'], 'type' );
		$this->assertContains( 'relationship', $types );
		$this->assertNotContains( 'repeater', $types );
	}

	public function test_pro_mode_includes_repeater_fields(): void {
		$groups = FieldGroups::all( 'pro' );
		$location = $this->find_group( $groups, 'group_ef_location' );

		$types = array_column( $location['fields'], 'type' );
		$this->assertContains( 'repeater', $types );
	}

	public function test_service_free_mode_has_faq_relationship(): void {
		$groups  = FieldGroups::all( 'free' );
		$service = $this->find_group( $groups, 'group_ef_service' );

		$field_names = array_column( $service['fields'], 'name' );
		$this->assertContains( 'related_faqs', $field_names );
	}

	public function test_field_group_locations_bind_to_correct_cpts(): void {
		$groups  = FieldGroups::all( 'free' );
		$location = $this->find_group( $groups, 'group_ef_location' );

		$this->assertSame( PostTypes::LOCATION, $location['location'][0][0]['value'] );
	}

	/**
	 * @param list<array<string, mixed>> $groups
	 * @return array<string, mixed>
	 */
	private function find_group( array $groups, string $key ): array {
		foreach ( $groups as $group ) {
			if ( isset( $group['key'] ) && $group['key'] === $key ) {
				return $group;
			}
		}
		$this->fail( 'Group ' . $key . ' not found.' );
	}
}
