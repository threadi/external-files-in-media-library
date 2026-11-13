<?php
/**
 * Tests for the grouping of the tabs in the settings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the grouping of the tabs in the settings.
 */
class Settings_Tabs extends externalFilesTests {
	/**
	 * Test that the moved tabs are sub-tabs of their groups.
	 *
	 * @return void
	 */
	public function test_groups(): void {
		$settings = Settings::get_instance();

		// the groups with their sub-tabs.
		$groups = array(
			'eml_file_types' => array( 'images', 'eml_proxy' ),
			'eml_advanced'   => array( 'eml_advanced_files', 'eml_permissions' ),
			'eml_status'     => array( 'eml_logs', 'eml_queue_table', 'eml_statistics' ),
		);
		foreach ( $groups as $group => $sub_tabs ) {
			$group_tab = $settings->get_group_tab( $group );
			$this->assertInstanceOf( Tab::class, $group_tab );
			foreach ( $sub_tabs as $sub_tab ) {
				$this->assertInstanceOf( Tab::class, $group_tab->get_tab( $sub_tab ), $sub_tab );
				$this->assertSame( $group_tab->get_tab( $sub_tab ), $settings->get_tab( $sub_tab ) );
			}
		}

		// the moved tabs are no longer main tabs.
		$page = $settings->get_settings_obj()->get_page( $settings->get_menu_slug() );
		$this->assertNotFalse( $page );
		foreach ( array_keys( $settings->get_moved_tabs() ) as $name ) {
			$this->assertFalse( $page->get_tab( $name ), $name );
		}

		// the default sub-tabs.
		$this->assertEquals( 'eml_logs', $settings->get_group_tab( 'eml_status' )->get_default_tab()->get_name() );
		$this->assertEquals( 'images', $settings->get_group_tab( 'eml_file_types' )->get_default_tab()->get_name() );

		// the title of the tab for file types and proxy.
		$this->assertEquals( 'Delivery', $settings->get_group_tab( 'eml_file_types' )->get_title() );

		// unknown groups are not added.
		$this->assertFalse( $settings->get_group_tab( 'unknown' ) );
		$this->assertFalse( $settings->get_tab( 'unknown' ) );
	}

	/**
	 * Test the URLs of moved tabs.
	 *
	 * @return void
	 */
	public function test_urls(): void {
		$settings = Settings::get_instance();

		$url = $settings->get_url( 'eml_logs' );
		$this->assertStringContainsString( 'tab=eml_status', $url );
		$this->assertStringContainsString( 'subtab=eml_logs', $url );

		$url = $settings->get_url( 'eml_permissions' );
		$this->assertStringContainsString( 'tab=eml_advanced', $url );
		$this->assertStringContainsString( 'subtab=eml_permissions', $url );

		// the search in the logs is kept.
		$this->assertStringContainsString( 's=', $settings->get_url( 'eml_logs', '', 'https://example.com/file.pdf' ) );

		// tabs, which have not been moved, are unchanged.
		$url = $settings->get_url( 'eml_general' );
		$this->assertStringContainsString( 'tab=eml_general', $url );
		$this->assertStringNotContainsString( 'subtab', $url );
		$this->assertStringContainsString( 'subtab=eml_advanced_misc', $settings->get_url( 'eml_advanced', 'eml_advanced_misc' ) );
	}

	/**
	 * Test the forwarding of old URLs of moved tabs with all their parameters.
	 *
	 * @return void
	 */
	public function test_forward_url(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/options-general.php?page=eml_settings&tab=eml_queue_table&paged=2';
		$url                    = Settings::get_instance()->get_forward_url_for_moved_tab( 'eml_queue_table' );
		$this->assertStringContainsString( 'tab=eml_status', $url );
		$this->assertStringContainsString( 'subtab=eml_queue_table', $url );
		$this->assertStringContainsString( 'paged=2', $url );
		$this->assertStringContainsString( 'page=eml_settings', $url );
	}
}
