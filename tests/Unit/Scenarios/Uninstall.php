<?php
/**
 * Tests a scenario.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Scenarios;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test this scenario.
 */
class Uninstall extends externalFilesTests {
	/**
	 * Run uninstallation with deletion of all external files and test the database.
	 *
	 * @return void
	 */
	public function test_uninstall_with_file_deletion(): void {
		// set to delete files on uninstallation.
		update_option( 'eml_delete_on_deinstallation', 1 );

		// add a test file.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );

		// get file count.
		$files = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_files();

		// run the uninstallation.
		$this->uninstallation();

		// test the file count.
		$test_files = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_files();
		$this->assertEmpty( $test_files );
		$this->assertNotEquals( $files, $test_files );
	}

	/**
	 * Run uninstallation with switching of all external files to local files and test the database.
	 *
	 * @return void
	 */
	public function test_uninstall_with_file_switch(): void {
		// set to not delete files on uninstallation.
		update_option( 'eml_delete_on_deinstallation', 0 );

		// set to delete files on uninstallation.
		update_option( 'eml_switch_on_uninstallation', 1 );

		// add a test file.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );

		// get file count.
		$files = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_files();

		// run the uninstallation.
		$this->uninstallation();

		// test if the file has been switched.
		$test_files = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_files();
		$this->assertNotEmpty( $test_files );
		$this->assertEquals( $files, $test_files );

		// check the files in detail.
		foreach( $test_files as $file ) {
			$hosting_type = $file->is_locally_saved();
			$this->assertIsBool( $hosting_type );
			$this->assertTrue( $hosting_type );
		}
	}

	/**
	 * The main tests for uninstallation.
	 *
	 * @return void
	 */
	private function uninstallation(): void {
		// get list of settings.
		$settings = array();
		foreach( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj()->get_settings() as $setting ) {
			$settings[ $setting->get_name() ] = $setting->get_value();
		}

		// set a transient.
		$transient = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->add();
		$transient->set_name( 'my_test' );
		$transient->save();
		$this->assertIsObject( \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( 'my_test' ) );

		// run the uninstallation.
		\ExternalFilesInMediaLibrary\Plugin\Uninstall::get_instance()->run();

		// test if the transient has been deleted.
		$test_transient = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( 'my_test' );
		$this->assertFalse( $test_transient->is_set() );

		// test if the settings have been deleted.
		foreach( $settings as $name => $value ) {
			$value = get_option( $name );
			$this->assertIsBool( $value );
			$this->assertFalse( $value );
		}
	}
}
