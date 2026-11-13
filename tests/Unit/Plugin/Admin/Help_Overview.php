<?php
/**
 * Tests for the help tab with the overview of the features.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin\Admin;

use ExternalFilesInMediaLibrary\Plugin\Admin\Help_System;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the help tab with the overview of the features.
 */
class Help_Overview extends externalFilesTests {
	/**
	 * Test the help tab: it is the first tab and links to the settings and the documentation.
	 *
	 * @return void
	 */
	public function test_overview(): void {
		$list = Help_System::get_instance()->add_overview( array( array( 'id' => 'other' ) ) );
		$this->assertEquals( 'efml-overview', $list[0]['id'] );
		$this->assertEquals( 'other', $list[1]['id'] );
		$this->assertStringContainsString( 'tab=eml_export', $list[0]['content'] );
		$this->assertStringContainsString( 'subtab=eml_proxy', $list[0]['content'] );
		$this->assertStringContainsString( 'docs/constants.md', $list[0]['content'] );
	}

	/**
	 * Test the URLs of the documentation in the language of the user.
	 *
	 * @return void
	 */
	public function test_documentation_url(): void {
		$this->assertStringEndsWith( '/docs/constants.md', Helper::get_documentation_url( 'constants' ) );
		add_filter( 'locale', array( $this, 'set_german' ) );
		$user = self::factory()->user->create( array( 'locale' => 'de_DE' ) );
		wp_set_current_user( $user );
		$this->assertStringEndsWith( '/docs/import/directories_de.md', Helper::get_documentation_url( 'import/directories' ) );
		remove_filter( 'locale', array( $this, 'set_german' ) );
	}

	/**
	 * Return German as locale.
	 *
	 * @return string
	 */
	public function set_german(): string {
		return 'de_DE';
	}
}
