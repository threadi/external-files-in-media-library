<?php
/**
 * Tests for the search in the settings and for single settings in the command palette.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Plugin\Commands;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;

/**
 * Object to test the search in the settings.
 */
class Settings_Search extends externalFilesTests {
	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// skip, if the library does not support the search yet.
		if ( ! method_exists( Settings::get_instance()->get_settings_obj(), 'get_search' ) ) {
			$this->markTestSkipped( 'easy-settings-for-wordpress 3.10.0 is required.' );
		}

		// build the index again for this test.
		Settings::get_instance()->get_settings_obj()->get_search()->reset_index();
	}

	/**
	 * Return the entry of the index with the given type and name.
	 *
	 * @param string $type The type.
	 * @param string $name The name.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_entry( string $type, string $name ): ?array {
		foreach ( Settings::get_instance()->get_settings_obj()->get_search()->get_index() as $entry ) {
			if ( $type === $entry['type'] && $name === $entry['name'] ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * The search is enabled, but not the command palette of the library (the plugin has an own one).
	 *
	 * @return void
	 */
	public function test_enabled(): void {
		$settings_obj = Settings::get_instance()->get_settings_obj();
		$this->assertTrue( $settings_obj->is_search_enabled() );
		$this->assertFalse( $settings_obj->is_search_in_command_palette_enabled() );
		$this->assertSame( 'Search settings', $settings_obj->get_translations()['search_placeholder'] );
	}

	/**
	 * The index contains the settings and tabs with the search terms of this plugin and its pages.
	 *
	 * @return void
	 */
	public function test_index(): void {
		// a setting with its search terms.
		$entry = $this->get_entry( 'setting', 'eml_export' );
		$this->assertNotNull( $entry );
		$this->assertStringContainsString( 'offload', $entry['keywords'] );

		// a tab without settings with its search terms.
		$entry = $this->get_entry( 'tab', 'eml_logs' );
		$this->assertNotNull( $entry );
		$this->assertStringContainsString( 'debug', $entry['keywords'] );

		// pages and documentations of the plugin.
		$this->assertNotNull( $this->get_entry( 'page', 'add-files' ) );
		$entry = $this->get_entry( 'page', 'docs-constants' );
		$this->assertNotNull( $entry );
		$this->assertTrue( $entry['external'] );

		// the tabs of the settings are not added twice.
		$this->assertNull( $this->get_entry( 'page', 'settings-eml_general' ) );
	}

	/**
	 * The search finds a setting by its search terms.
	 *
	 * @return void
	 */
	public function test_results(): void {
		$results = Settings::get_instance()->get_settings_obj()->get_search()->get_results( 'offload', 0, array( 'setting' ) );
		$this->assertContains( 'eml_export', array_column( $results, 'name' ) );
	}

	/**
	 * The command palette finds single settings and opens them highlighted.
	 *
	 * @return void
	 */
	public function test_command_palette(): void {
		$request = new WP_REST_Request( 'GET', '/external-files-in-media-library/v1/pages' );
		$request->set_param( 'search', 'offload' );
		$result = Commands::get_instance()->get_pages_via_rest( $request );

		// the setting is in the list with its path and the URL to highlight it.
		$entries = array_values( array_filter( $result, static fn( array $entry ): bool => 'setting-eml_export' === $entry['value'] ) );
		$this->assertCount( 1, $entries );
		$this->assertStringStartsWith( 'External files setting: ', $entries[0]['label'] );
		$this->assertStringContainsString( 'esfw_highlight=eml_export', $entries[0]['url'] );
		$this->assertStringContainsString( 'esfw_search=offload', $entries[0]['url'] );

		// the ids are unique.
		$ids = array_column( $result, 'id' );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );
	}
}
