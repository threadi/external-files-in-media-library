<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Cli.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use Exception;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Cli.
 *
 * WP CLI is not loaded during the tests, so we stop each command after the preparation of the import.
 */
class Cli extends externalFilesTests {
	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_action( 'efml_cli_arguments', array( $this, 'stop_command' ) );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_action( 'efml_cli_arguments', array( $this, 'stop_command' ) );
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->set_fields( array() );
		parent::tear_down();
	}

	/**
	 * Stop the command.
	 *
	 * @return void
	 * @throws Exception To stop the command.
	 */
	public function stop_command(): void {
		throw new Exception( 'stopped' );
	}

	/**
	 * Run the import command with the given arguments and return the used fields.
	 *
	 * @param array<string,string> $arguments The arguments.
	 *
	 * @return array
	 */
	private function get_fields_for_import( array $arguments ): array {
		try {
			( new \ExternalFilesInMediaLibrary\Plugin\Cli() )->import( array( 'https://example.com/example.pdf' ), $arguments );
		} catch ( Exception $e ) {
			$this->assertEquals( 'stopped', $e->getMessage() );
		}
		return \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->get_fields();
	}

	/**
	 * Test if the credentials are used for the import.
	 *
	 * @return void
	 */
	public function test_import_with_credentials(): void {
		$fields = $this->get_fields_for_import(
			array(
				'login'    => 'my-login',
				'password' => 'my-password',
			)
		);
		$this->assertEquals( 'my-login', $fields['login']['value'] );
		$this->assertEquals( 'my-password', $fields['password']['value'] );
	}

	/**
	 * Test if a login without password does not result in a warning.
	 *
	 * @return void
	 */
	public function test_import_with_login_only(): void {
		$fields = $this->get_fields_for_import( array( 'login' => 'my-login' ) );
		$this->assertEquals( 'my-login', $fields['login']['value'] );
		$this->assertEquals( '', $fields['password']['value'] );
	}

	/**
	 * Test if no credentials are used without login.
	 *
	 * @return void
	 */
	public function test_import_without_login(): void {
		$fields = $this->get_fields_for_import( array( 'password' => 'my-password' ) );
		$this->assertEquals( '', $fields['login']['value'] );
		$this->assertEquals( '', $fields['password']['value'] );
	}
}
