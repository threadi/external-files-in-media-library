<?php
/**
 * Tests for the usage of constants with a real FTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Services\Ftp;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the usage of constants for the credentials of an FTP server.
 *
 * Each test uses its own prefix for the constant names, as constants could not be removed.
 */
class FtpConstants extends externalFilesTests {
	/**
	 * The data of the used server.
	 *
	 * @var array<string,mixed>
	 */
	private array $server = array();

	/**
	 * The directory on the server.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * The prefix for the constants of the actual test.
	 *
	 * @var string
	 */
	private string $prefix = '';

	/**
	 * Counter for unique prefixes.
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// use a unique prefix for the constants of this test.
		++self::$counter;
		$this->prefix = 'EFMLFTPTEST' . self::$counter . '_';
		add_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );

		// get the server.
		$this->server    = TestServers::get( 'ftp', $this );
		$this->directory = TestServers::create_directory( $this->server );

		// enable the export.
		update_option( 'eml_export', 1 );
		add_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		remove_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) );
		update_option( 'eml_export', 0 );
		Ftp::get_instance()->set_fields( array() );
		Import::get_instance()->set_fields( array() );
		parent::tear_down();
	}

	/**
	 * Use the prefix of the actual test for the constant names.
	 *
	 * @param string $constant_name The name of the constant.
	 *
	 * @return string
	 */
	public function set_constant_name( string $constant_name ): string {
		return $this->prefix . $constant_name;
	}

	/**
	 * Define the constants for the test server.
	 *
	 * @param bool $with_server Whether the constant for the server should be defined.
	 *
	 * @return void
	 */
	private function define_constants( bool $with_server = true ): void {
		if ( $with_server ) {
			define( $this->prefix . 'EFML_FTP_SERVER', TestServers::get_url( $this->server ) );
		}
		define( $this->prefix . 'EFML_FTP_LOGIN', $this->server['login'] );
		define( $this->prefix . 'EFML_FTP_PASSWORD', $this->server['password'] );
	}

	/**
	 * Return the fields of an external source without credentials, as they are saved if constants are used.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_empty_fields(): array {
		$fields = TestServers::get_fields( $this->server );
		foreach ( $fields as $name => $field ) {
			$fields[ $name ]['value'] = '';
		}
		return $fields;
	}

	/**
	 * Create an external source without credentials and enable the export for it.
	 *
	 * @return int
	 */
	private function create_export_source(): int {
		$term_id = Taxonomy::get_instance()->add( 'ftp', TestServers::get_url( $this->server, $this->directory ), $this->get_empty_fields() );
		$this->assertGreaterThan( 0, $term_id );
		update_term_meta( $term_id, 'efml_export_url', 'https://cdn.example.com/files/' );
		Export::get_instance()->set_state_for_term( $term_id, 1 );
		return $term_id;
	}

	/**
	 * Upload the given test file to the media library and export it.
	 *
	 * @param string $file The test file in tests/Data/ftp.
	 *
	 * @return int
	 */
	private function upload( string $file ): int {
		$tmp = trailingslashit( get_temp_dir() ) . basename( $file );
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $file, $tmp );
		$attachment_id = @self::factory()->attachment->create_upload_object( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertIsInt( $attachment_id );
		@Export::get_instance()->export_file( $attachment_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $attachment_id;
	}

	/**
	 * Test the export and deletion via an external source without saved credentials.
	 *
	 * @return void
	 */
	public function test_export_with_constants(): void {
		$this->define_constants();
		$this->create_export_source();

		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$filename      = basename( (string) get_attached_file( $attachment_id, true ) );
		$this->assertFileExists( $this->server['root'] . $this->directory . $filename );

		// the credentials are not saved on the file.
		$this->assertStringNotContainsString( $this->server['password'], (string) wp_json_encode( Files::get_instance()->get_file( $attachment_id )->get_fields() ) );

		// the file is deleted on the server.
		@wp_delete_attachment( $attachment_id, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFileDoesNotExist( $this->server['root'] . $this->directory . $filename );
	}

	/**
	 * Test that the export fails if the constant for the server is missing.
	 *
	 * @return void
	 */
	public function test_export_without_server_constant(): void {
		$this->define_constants( false );
		$this->create_export_source();

		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$filename      = basename( (string) get_attached_file( $attachment_id, true ) );
		$this->assertFileDoesNotExist( $this->server['root'] . $this->directory . $filename );
	}

	/**
	 * Test the import of a file with empty fields (as sent from the browser).
	 *
	 * @return void
	 */
	public function test_import_with_constants(): void {
		$this->define_constants();

		$import = Import::get_instance();
		$import->set_fields( $this->get_empty_fields() );
		$this->assertTrue( @$import->add_url( TestServers::get_url( $this->server, $this->directory . 'example-red.jpg' ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Use the prefix for constants for another server from now on and define them.
	 *
	 * Before this, the constant names without prefix are used, which are not defined in the tests.
	 *
	 * @return void
	 */
	private function define_constants_for_other_server(): void {
		define( $this->prefix . 'EFML_FTP_SERVER', 'ftp://other.example.com' );
		define( $this->prefix . 'EFML_FTP_LOGIN', 'other-login' );
		define( $this->prefix . 'EFML_FTP_PASSWORD', 'other-password' );
		add_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
	}

	/**
	 * Create an external source with its own credentials.
	 *
	 * @return int
	 */
	private function create_source_with_credentials(): int {
		$term_id = Taxonomy::get_instance()->add( 'ftp', TestServers::get_url( $this->server, $this->directory ), TestServers::get_fields( $this->server ) );
		$this->assertGreaterThan( 0, $term_id );
		return $term_id;
	}

	/**
	 * Test that a source on another server is marked as not usable, a source on the server of the constants not.
	 *
	 * @return void
	 */
	public function test_source_blocked_by_constants(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		$term_id = $this->create_source_with_credentials();
		$term    = get_term( $term_id );
		$this->assertFalse( Services::get_instance()->is_source_blocked_by_constants( $term_id ) );

		$this->define_constants_for_other_server();
		$this->assertTrue( Services::get_instance()->is_source_blocked_by_constants( $term_id ) );

		// the list shows the hint and only the actions to edit, rename and delete.
		$this->assertStringContainsString( 'Not usable', Services::get_instance()->show_blocked_source_in_column( 'connect-link', 'connect', $term_id ) );
		$this->assertEquals( 'other', Services::get_instance()->show_blocked_source_in_column( 'other', 'type', $term_id ) );
		$actions = Services::get_instance()->remove_actions_of_blocked_source(
			array(
				'edit'    => 'e',
				'delete'  => 'd',
				'rename'  => 'r',
				'connect' => 'c',
				'sync'    => 's',
			),
			$term
		);
		$this->assertEquals( array( 'edit', 'delete', 'rename' ), array_keys( $actions ) );

		// URLs on the server of the constants are usable.
		$this->assertFalse( Ftp::get_instance()->is_url_blocked_by_constants( 'ftp://other.example.com/folder/' ) );
		$this->assertTrue( Ftp::get_instance()->is_url_blocked_by_constants( TestServers::get_url( $this->server ) ) );
	}

	/**
	 * Test that nothing is exported to a source on another server.
	 *
	 * @return void
	 */
	public function test_no_export_to_blocked_source(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		$term_id = $this->create_source_with_credentials();
		update_term_meta( $term_id, 'efml_export_url', 'https://cdn.example.com/files/' );
		Export::get_instance()->set_state_for_term( $term_id, 1 );
		$this->define_constants_for_other_server();

		$this->assertEmpty( Export::get_instance()->get_usable_export_terms() );
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$filename      = basename( (string) get_attached_file( $attachment_id, true ) );
		$this->assertFileDoesNotExist( $this->server['root'] . $this->directory . $filename );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

	/**
	 * Test that a file exported before is not deleted on a source on another server.
	 *
	 * @return void
	 */
	public function test_no_deletion_on_blocked_source(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		$term_id = $this->create_source_with_credentials();
		update_term_meta( $term_id, 'efml_export_url', 'https://cdn.example.com/files/' );
		Export::get_instance()->set_state_for_term( $term_id, 1 );
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$filename      = basename( (string) get_attached_file( $attachment_id, true ) );
		$this->assertFileExists( $this->server['root'] . $this->directory . $filename );

		$this->define_constants_for_other_server();
		@wp_delete_attachment( $attachment_id, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFileExists( $this->server['root'] . $this->directory . $filename );
	}

	/**
	 * Test that a source on another server is not synchronized.
	 *
	 * @return void
	 */
	public function test_no_sync_of_blocked_source(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		$term_id = $this->create_source_with_credentials();
		$this->define_constants_for_other_server();

		$url = TestServers::get_url( $this->server, $this->directory );
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( Files::get_instance()->get_file_by_url( TestServers::get_url( $this->server, $this->directory . 'example-red.jpg' ) ) );
	}

	/**
	 * Test that files imported before from a source on another server are still delivered with their own credentials.
	 *
	 * @return void
	 */
	public function test_files_of_blocked_source_are_delivered(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		$url    = TestServers::get_url( $this->server, $this->directory . 'example-red.jpg' );
		$import = Import::get_instance();
		$import->set_fields( TestServers::get_fields( $this->server ) );
		$this->assertTrue( @$import->add_url( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );

		$this->define_constants_for_other_server();
		$protocol = Protocols::get_instance()->get_protocol_object_for_external_file( $external_file_obj );
		$this->assertNotFalse( $protocol );
		$fields = $protocol->get_fields();
		$this->assertEquals( $this->server['login'], $fields['login']['value'] );
		$this->assertEquals( $this->server['password'], $fields['password']['value'] );
		$this->assertTrue( $external_file_obj->has_credentials() );
	}

	/**
	 * Test that a manual import from another server is not possible, even with own credentials.
	 *
	 * @return void
	 */
	public function test_no_manual_import_from_other_server(): void {
		remove_filter( 'efml_service_field_constant_name', array( $this, 'set_constant_name' ) );
		$this->define_constants_for_other_server();

		$url    = TestServers::get_url( $this->server, $this->directory . 'example-red.jpg' );
		$import = Import::get_instance();
		$import->set_fields( TestServers::get_fields( $this->server ) );
		$this->assertFalse( @$import->add_url( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( Files::get_instance()->get_file_by_url( $url ) );
	}

	/**
	 * Test the login of the service with empty fields (as sent from the browser).
	 *
	 * @return void
	 */
	public function test_service_login_with_constants(): void {
		$this->define_constants();

		$ftp = Ftp::get_instance();
		$ftp->set_fields( $this->get_empty_fields() );
		$this->assertTrue( $ftp->do_login( TestServers::get_url( $this->server, $this->directory ) ) );
		$this->assertNotEmpty( $ftp->get_directory_listing( TestServers::get_url( $this->server, $this->directory ) ) );
	}
}
