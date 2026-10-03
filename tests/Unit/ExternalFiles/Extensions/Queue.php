<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue.
 */
class Queue extends externalFilesTests {

	/**
	 * Marker whether the test option was set during the import.
	 *
	 * @var bool
	 */
	private bool $option_was_set_during_import = false;

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->install();
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->clear();
	}

	/**
	 * Add the given URL to the queue.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	private function add_to_queue( string $url ): void {
		$_POST['add_to_queue'] = 1;
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->add_urls_to_queue( false, $url, array() ) );
		unset( $_POST['add_to_queue'] );
	}

	/**
	 * Add an option to each queue entry.
	 *
	 * @param array<string,mixed> $options The options.
	 *
	 * @return array<string,mixed>
	 */
	public function add_test_option( array $options ): array {
		$options['efml_test_option'] = 'yes';
		return $options;
	}

	/**
	 * Check if the test option is set during the import.
	 *
	 * @return void
	 */
	public function check_test_option(): void {
		$this->option_was_set_during_import = isset( $_POST['efml_test_option'] ) && 'yes' === $_POST['efml_test_option'];
	}

	/**
	 * Test if a URL without any credentials is added to the queue and imported by processing the queue.
	 *
	 * @return void
	 */
	public function test_process_queue_with_url_without_credentials(): void {
		$queue_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance();
		$url       = self::get_test_file( 'pdf', 'http' );

		// add the URL to the queue.
		$this->add_to_queue( $url );
		$this->assertCount( 1, $queue_obj->get_urls() );
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( $url ) );

		// process the queue.
		$queue_obj->process_queue();

		// the queue must be empty and the file must exist.
		$this->assertCount( 0, $queue_obj->get_urls() );
		$this->assertCount( 0, $queue_obj->get_urls( 'error' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( $url ) );
	}

	/**
	 * Test if a URL, which could not be imported, is marked as error and does not block the queue.
	 *
	 * @return void
	 */
	public function test_process_queue_with_faulty_url(): void {
		$queue_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance();

		// add a faulty and a valid URL to the queue.
		$this->add_to_queue( self::get_faulty_test_file( 'pdf', 'http' ) );
		$this->add_to_queue( self::get_test_file( 'pdf', 'http' ) );
		$this->assertCount( 2, $queue_obj->get_urls() );

		// process the queue.
		$queue_obj->process_queue();

		// the faulty URL must be marked as error, the valid URL must be imported.
		$this->assertCount( 0, $queue_obj->get_urls() );
		$this->assertCount( 1, $queue_obj->get_urls( 'error' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) ) );
	}

	/**
	 * Test if the options of a queue entry are available during its import, but not afterwards,
	 * to prevent their usage for the next entry.
	 *
	 * @return void
	 */
	public function test_options_of_queue_entry_are_reset_after_import(): void {
		$queue_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance();

		// add the URL with an option to the queue.
		add_filter( 'efml_import_options', array( $this, 'add_test_option' ) );
		$this->add_to_queue( self::get_test_file( 'pdf', 'http' ) );
		remove_filter( 'efml_import_options', array( $this, 'add_test_option' ) );
		$this->assertArrayNotHasKey( 'efml_test_option', $_POST );

		// process the queue.
		add_action( 'efml_before_import', array( $this, 'check_test_option' ), 10, 0 );
		$queue_obj->process_queue();
		remove_action( 'efml_before_import', array( $this, 'check_test_option' ) );

		// the option must have been set during the import and must be removed afterwards.
		$this->assertTrue( $this->option_was_set_during_import );
		$this->assertArrayNotHasKey( 'efml_test_option', $_POST );
	}
}
