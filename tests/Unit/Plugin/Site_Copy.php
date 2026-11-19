<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Site_Copy.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionClass;
use ReflectionMethod;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Site_Copy.
 */
class Site_Copy extends externalFilesTests {
	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * The term ID of the external source we use as export target.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * The original instance of the object.
	 *
	 * @var \ExternalFilesInMediaLibrary\Plugin\Site_Copy|null
	 */
	private ?\ExternalFilesInMediaLibrary\Plugin\Site_Copy $original_instance = null;

	/**
	 * The URL of the website for the actual test.
	 *
	 * @var string
	 */
	private string $home = '';

	/**
	 * Prepare the test environment for each test: an external source as export target and this website as main website.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-site-copy-' . uniqid() . '/';
		wp_mkdir_p( $this->export_directory );
		add_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );

		// make sure the local service is used as export object.
		if ( false === has_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) ) ) {
			add_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) );
		}

		// create the external source and enable the export for it.
		$this->term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file://' . $this->export_directory, array( 'path' => array( 'value' => $this->export_directory ) ) );
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->set_state_for_term( $this->term_id, 1 );

		// save the original instance.
		$this->original_instance = \ExternalFilesInMediaLibrary\Plugin\Site_Copy::get_instance();

		// this website is the main website.
		$this->home = 'http://' . WP_TESTS_DOMAIN;
		add_filter( 'pre_option_home', array( $this, 'get_home' ) );
		delete_option( 'efml_site_fingerprint' );
		delete_option( 'efml_site_copy_confirmed' );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
		remove_filter( 'pre_option_home', array( $this, 'get_home' ) );
		$this->set_instance( $this->original_instance );
		parent::tear_down();

		// remove the fingerprints also outside the transaction of the test, as some statements (e.g. on multisites) commit it.
		delete_option( 'efml_site_fingerprint' );
		delete_option( 'efml_site_copy_confirmed' );
	}

	/**
	 * Return the URL of the website for the actual test (without changing the database).
	 *
	 * @return string
	 */
	public function get_home(): string {
		return $this->home;
	}

	/**
	 * Return the export directory.
	 *
	 * @return string
	 */
	public function get_export_directory(): string {
		return $this->export_directory;
	}

	/**
	 * Return the object to test.
	 *
	 * @return \ExternalFilesInMediaLibrary\Plugin\Site_Copy
	 */
	private function get_object(): \ExternalFilesInMediaLibrary\Plugin\Site_Copy {
		return \ExternalFilesInMediaLibrary\Plugin\Site_Copy::get_instance();
	}

	/**
	 * Set the singleton instance.
	 *
	 * @param \ExternalFilesInMediaLibrary\Plugin\Site_Copy|null $instance The instance.
	 *
	 * @return void
	 */
	private function set_instance( ?\ExternalFilesInMediaLibrary\Plugin\Site_Copy $instance ): void {
		( new ReflectionClass( \ExternalFilesInMediaLibrary\Plugin\Site_Copy::class ) )->setStaticPropertyValue( 'instance', $instance );
	}

	/**
	 * Use an object, which reports the given environment type.
	 *
	 * @param string $environment The environment type.
	 *
	 * @return void
	 */
	private function use_environment( string $environment ): void {
		$mock = $this->getMockBuilder( \ExternalFilesInMediaLibrary\Plugin\Site_Copy::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_current_environment' ) )
			->getMock();
		$mock->method( 'get_current_environment' )->willReturn( $environment );
		$this->set_instance( $mock );
	}

	/**
	 * Simulate a copy of this website by changing its URL.
	 *
	 * @return void
	 */
	private function make_copy(): void {
		// save this website with its original URL as main website.
		$this->home = 'http://' . WP_TESTS_DOMAIN;
		$this->get_object()->set_as_main();

		// change the URL like on a staging site.
		$this->home = 'https://staging.example.org';
		$this->assertTrue( $this->get_object()->is_copy() );
	}

	/**
	 * Create an exported file: an external file on the export target, which is marked as exported.
	 *
	 * @return array<int,int|string> The attachment ID and the path of the file on the export target.
	 */
	private function create_exported_file(): array {
		// add an external file.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$external_file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );
		$attachment_id = $external_file_obj->get_id();

		// create the file on the export target.
		$path = $this->export_directory . basename( (string) get_attached_file( $attachment_id, true ) );
		file_put_contents( $path, 'this is a file on the export target' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// mark the file as exported to our external source.
		update_post_meta( $attachment_id, 'eml_exported_file', time() );
		update_post_meta( $attachment_id, 'efml_export_sources', array( $this->term_id ) );

		// return the data.
		return array( $attachment_id, $path );
	}

	/**
	 * Test if the fingerprint is saved silently, if none exists.
	 *
	 * @return void
	 */
	public function test_fingerprint_is_saved_silently(): void {
		$this->assertFalse( get_option( 'efml_site_fingerprint' ) );
		$this->assertFalse( $this->get_object()->is_copy() );

		// the fingerprint has been saved.
		$fingerprint = get_option( 'efml_site_fingerprint' );
		$this->assertIsArray( $fingerprint );
		$this->assertEquals( $this->get_object()->get_current_fingerprint(), $fingerprint['hash'] );
		$this->assertEquals( wp_get_environment_type(), $fingerprint['environment'] );

		// no hint is added.
		$this->get_object()->add_hint();
		$this->assertFalse( $this->get_hint() );
	}

	/**
	 * Test the detection of copies by the URL.
	 *
	 * @return void
	 */
	public function test_is_copy_by_url(): void {
		$this->home = 'http://www.example.org';
		$this->get_object()->set_as_main();

		// test 1: other scheme, trailing slash and uppercase letters are the same website.
		foreach ( array( 'https://www.example.org', 'http://www.example.org/', 'HTTPS://WWW.Example.org/' ) as $url ) {
			$this->home = $url;
			$this->assertFalse( $this->get_object()->is_copy(), $url . ' is detected as copy.' );
		}

		// test 2: other hosts or paths are copies.
		foreach ( array( 'https://staging.example.org', 'https://example.org', 'https://www.example.org/staging' ) as $url ) {
			$this->home = $url;
			$this->assertTrue( $this->get_object()->is_copy(), $url . ' is not detected as copy.' );
		}

		// test 3: after setting it as main website, it is not a copy anymore.
		$this->get_object()->set_as_main();
		$this->assertFalse( $this->get_object()->is_copy() );
	}

	/**
	 * Test the detection of copies by the environment type.
	 *
	 * @return void
	 */
	public function test_is_copy_by_environment(): void {
		// test 1: the main website is a production website, the website with the same URL is now a staging website.
		$this->use_environment( 'production' );
		$this->get_object()->set_as_main();
		foreach ( array( 'staging', 'development', 'local' ) as $environment ) {
			$this->use_environment( $environment );
			$this->assertTrue( $this->get_object()->is_copy(), $environment . ' is not detected as copy.' );
		}

		// test 2: a staging website, which is the main website, is not a copy.
		$this->use_environment( 'staging' );
		$this->get_object()->set_as_main();
		$this->assertFalse( $this->get_object()->is_copy() );

		// test 3: a development website saved as main website is not a copy as production website.
		$this->use_environment( 'production' );
		$this->assertFalse( $this->get_object()->is_copy() );
	}

	/**
	 * Test if the detection is filterable.
	 *
	 * @return void
	 */
	public function test_filter(): void {
		$this->get_object()->set_as_main();
		add_filter( 'efml_is_website_copy', '__return_true' );
		$this->assertTrue( $this->get_object()->is_copy() );
		remove_filter( 'efml_is_website_copy', '__return_true' );

		$this->make_copy();
		add_filter( 'efml_is_website_copy', '__return_false' );
		$this->assertFalse( $this->get_object()->is_copy() );
		remove_filter( 'efml_is_website_copy', '__return_false' );
	}

	/**
	 * Return the transient with the hint, if it is set.
	 *
	 * @return \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transient|false
	 */
	private function get_hint(): \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transient|false {
		$transient_obj = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( 'efml_site_copy' );
		return $transient_obj->is_set() ? $transient_obj : false;
	}

	/**
	 * Test the hint in the backend (via transient) and its actions.
	 *
	 * @return void
	 */
	public function test_hint(): void {
		// test 1: no hint on the main website.
		$this->get_object()->add_hint();
		$this->assertFalse( $this->get_hint() );

		// test 2: the hint is added on a copy, with both actions.
		$this->make_copy();
		$this->get_object()->add_hint();
		$hint = $this->get_hint();
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transient::class, $hint );
		$this->assertEquals( 'error', $hint->get_type() );
		$this->assertTrue( $hint->is_prioritized() );
		$this->assertStringContainsString( 'action=efml_site_is_main', $hint->get_message() );
		$this->assertStringContainsString( 'action=efml_site_is_copy', $hint->get_message() );
		$this->assertStringContainsString( 'This website is a copy', $this->get_object()->get_settings_hint() );

		// test 3: not for users without permission to manage options.
		$hint->delete();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->get_object()->add_hint();
		$this->assertFalse( $this->get_hint() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// test 4: after the confirmation as copy, the hint is removed and not added again, but it is still a copy.
		$this->get_object()->add_hint();
		$this->assertNotFalse( $this->get_hint() );
		$this->get_object()->confirm_copy();
		$this->assertFalse( $this->get_hint() );
		$this->get_object()->add_hint();
		$this->assertFalse( $this->get_hint() );
		$this->assertTrue( $this->get_object()->is_copy_confirmed() );
		$this->assertTrue( $this->get_object()->is_copy() );
		$this->assertNotEmpty( $this->get_object()->get_settings_hint() );

		// test 5: a copy on another URL must be confirmed again.
		$this->home = 'https://other-staging.example.org';
		$this->assertFalse( $this->get_object()->is_copy_confirmed() );
		$this->get_object()->add_hint();
		$this->assertNotFalse( $this->get_hint() );

		// test 6: setting it as main website removes the hint, the confirmation and the hint in the settings.
		$this->get_object()->set_as_main();
		$this->assertFalse( $this->get_hint() );
		$this->assertFalse( $this->get_object()->is_copy() );
		$this->assertFalse( get_option( 'efml_site_copy_confirmed' ) );
		$this->assertEquals( '', $this->get_object()->get_settings_hint() );
	}

	/**
	 * Test the registered hooks.
	 *
	 * @return void
	 */
	public function test_init(): void {
		$this->assertIsInt( has_filter( 'efml_prevent_sync', array( $this->get_object(), 'prevent_scheduled_sync' ) ) );
		$this->assertIsInt( has_action( 'admin_init', array( $this->get_object(), 'add_hint' ) ) );
		$this->assertIsInt( has_action( 'admin_action_efml_site_is_main', array( $this->get_object(), 'set_as_main_by_request' ) ) );
		$this->assertIsInt( has_action( 'admin_action_efml_site_is_copy', array( $this->get_object(), 'confirm_copy_by_request' ) ) );
	}

	/**
	 * Test that files are not exported on a copy and that the export works on the main website.
	 *
	 * @return void
	 */
	public function test_export_is_blocked_on_copy(): void {
		$attachment_id = (int) self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$this->assertGreaterThan( 0, $attachment_id );
		$target = $this->export_directory . basename( (string) get_attached_file( $attachment_id, true ) );

		// test 1: on a copy, the file is not exported.
		$this->make_copy();
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->export_file( $attachment_id ) );
		$this->assertFileDoesNotExist( $target );
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// the reason is logged.
		$entries = \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_logs();
		$this->assertStringContainsString( 'Export of file has been skipped.', wp_json_encode( $entries ) );

		// test 2: on the main website, the export is not blocked (it checks the external sources).
		$this->get_object()->set_as_main();
		update_option( 'eml_log_mode', 2 );
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->truncate_log();
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->export_file( $attachment_id );
		$entries = wp_json_encode( \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_logs() );
		$this->assertStringNotContainsString( 'Export of file has been skipped.', (string) $entries );
		$this->assertStringContainsString( 'Check, which service could be used to export the given file.', (string) $entries );
	}

	/**
	 * Test that exported files are not deleted on the export target on a copy, but on the main website.
	 *
	 * @return void
	 */
	public function test_deletion_is_blocked_on_copy(): void {
		list( $attachment_id, $path ) = $this->create_exported_file();

		// test 1: on a copy, the file on the export target stays, also if the attachment is deleted.
		$this->make_copy();
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->delete_exported_file( (int) $attachment_id );
		$this->assertFileExists( (string) $path );
		$this->assertNotEmpty( get_post_meta( (int) $attachment_id, 'efml_export_sources', true ) );

		// test 2: on the main website, the file on the export target is deleted.
		$this->get_object()->set_as_main();
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->delete_exported_file( (int) $attachment_id );
		$this->assertFileDoesNotExist( (string) $path );
	}

	/**
	 * Test the scenario of a staging site: the deletion of an exported file in the media library of the copy
	 * keeps the file on the export target.
	 *
	 * @return void
	 */
	public function test_scenario_delete_attachment_on_staging_site(): void {
		list( $attachment_id, $path ) = $this->create_exported_file();
		$this->make_copy();

		// delete the attachment like a user does it.
		wp_delete_attachment( (int) $attachment_id, true );

		// the attachment is deleted, the file on the export target still exists.
		$this->assertNull( get_post( (int) $attachment_id ) );
		$this->assertFileExists( (string) $path );
	}

	/**
	 * Test that exports in the queue stay in the queue on a copy, without counting it as failed attempt.
	 *
	 * @return void
	 */
	public function test_queue_on_copy(): void {
		update_option( 'eml_export', 1 );
		$attachment_id = (int) self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$queue         = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance();
		$queue->add_export( $attachment_id );
		$entry = ( new ReflectionMethod( $queue, 'get_export_entry' ) )->invoke( $queue, $attachment_id );
		$this->assertNotEmpty( $entry );
		$entry['attachment_id'] = $attachment_id;

		// process the entry on a copy.
		$this->make_copy();
		( new ReflectionMethod( $queue, 'process_export_entry' ) )->invoke( $queue, $entry );

		// the entry is still in the queue without failed attempt.
		$entry_after = ( new ReflectionMethod( $queue, 'get_export_entry' ) )->invoke( $queue, $attachment_id );
		$this->assertNotEmpty( $entry_after );
		$this->assertEquals( absint( $entry['attempts'] ), absint( $entry_after['attempts'] ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// cleanup.
		$queue->remove_export_entries_of_attachment( $attachment_id );
	}

	/**
	 * Test that scheduled synchronizations are paused on a copy, but manual synchronizations are possible.
	 *
	 * @return void
	 */
	public function test_sync_on_copy(): void {
		$this->make_copy();

		// test 1: manual synchronizations are possible.
		$this->assertFalse( $this->get_object()->prevent_scheduled_sync( false, 'https://example.com/' ) );

		// test 2: scheduled synchronizations are paused.
		add_filter( 'wp_doing_cron', '__return_true' );
		$this->assertTrue( $this->get_object()->prevent_scheduled_sync( false, 'https://example.com/' ) );
		$this->assertTrue( apply_filters( 'efml_prevent_sync', false, 'https://example.com/' ) );

		// test 3: on the main website, scheduled synchronizations are running.
		$this->get_object()->set_as_main();
		$this->assertFalse( $this->get_object()->prevent_scheduled_sync( false, 'https://example.com/' ) );
		remove_filter( 'wp_doing_cron', '__return_true' );

		// test 4: an already prevented synchronization stays prevented.
		$this->assertTrue( $this->get_object()->prevent_scheduled_sync( true, 'https://example.com/' ) );
	}
}
