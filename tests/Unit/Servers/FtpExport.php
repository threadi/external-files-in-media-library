<?php
/**
 * Tests for the export of files to a real FTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the export of uploaded files to an FTP server.
 */
class FtpExport extends externalFilesTests {
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
	 * The term ID of the external source used for the export.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Prepare each test: an FTP directory as external source with enabled export.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// enable the export and use the hooks of it (they are only added during init if the export is enabled).
		// the export of uploads is started directly (see upload()), as the hook for it does nothing during a URL import,
		// which other tests mark with a constant for the rest of the process.
		update_option( 'eml_export', 1 );
		add_filter( 'wp_unique_filename', array( Export::get_instance(), 'check_for_exported_filenames' ), 10, 3 );
		add_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) );
		add_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ), 10, 2 );
		add_action( 'efml_before_sync', array( Export::get_instance(), 'add_sync_filter' ), 10, 3 );
		add_action( 'pre_delete_term', array( Export::get_instance(), 'on_delete_archive_term' ), 10, 2 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_unique_filename', array( Export::get_instance(), 'check_for_exported_filenames' ) );
		remove_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) );
		remove_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ) );
		remove_action( 'efml_before_sync', array( Export::get_instance(), 'add_sync_filter' ) );
		remove_action( 'pre_delete_term', array( Export::get_instance(), 'on_delete_archive_term' ) );
		remove_filter( 'efml_external_file_infos', array( Export::get_instance(), 'prevent_sync_of_exported_file' ), 20 );
		update_option( 'eml_export', 0 );
		update_option( 'eml_export_main_source', 0 );
		update_option( 'eml_export_local_files', 0 );
		update_option( 'eml_export_do_not_delete_local_files', 0 );
		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		parent::tear_down();
	}

	/**
	 * Create the external source on the given server and enable the export for it.
	 *
	 * @param string $server_name The name of the test server.
	 *
	 * @return void
	 */
	private function create_export_source( string $server_name = 'ftp' ): void {
		$this->server    = TestServers::get( $server_name, $this );
		$this->directory = TestServers::create_directory( $this->server );
		$this->term_id   = Taxonomy::get_instance()->add( 'ftp', TestServers::get_url( $this->server, $this->directory ), TestServers::get_fields( $this->server ) );
		$this->assertGreaterThan( 0, $this->term_id );

		// the FTP export needs the public URL of the files.
		update_term_meta( $this->term_id, 'efml_export_url', 'https://cdn.example.com/files/' );
		Export::get_instance()->set_state_for_term( $this->term_id, 1 );
	}

	/**
	 * Upload the given test file to the media library and export it.
	 *
	 * @param string $file The test file in tests/Data/ftp.
	 * @param string $name The file name for the upload, if it should differ.
	 *
	 * @return int
	 */
	private function upload( string $file, string $name = '' ): int {
		// copy the file, as the upload moves it.
		$tmp = trailingslashit( get_temp_dir() ) . ( empty( $name ) ? basename( $file ) : $name );
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $file, $tmp );
		$attachment_id = @self::factory()->attachment->create_upload_object( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertIsInt( $attachment_id );

		// export it like the hook "wp_generate_attachment_metadata" does it after each upload.
		@Export::get_instance()->export_file( $attachment_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $attachment_id;
	}

	/**
	 * Return the path of a file on the server.
	 *
	 * @param string $filename The file name.
	 *
	 * @return string
	 */
	private function get_server_path( string $filename ): string {
		return $this->server['root'] . $this->directory . $filename;
	}

	/**
	 * Test the export of an uploaded file: it is saved on the server and is an external file then.
	 *
	 * @return void
	 */
	public function test_upload_is_exported(): void {
		$this->create_export_source();
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$filename      = basename( (string) get_attached_file( $attachment_id, true ) );

		// the file is on the server.
		$this->assertFileEquals( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/subdirectory/example-grey.jpg', $this->get_server_path( $filename ) );

		// the attachment is an external file of the source now and marked as exported.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertTrue( $external_file_obj->is_valid() );
		$this->assertSame( TestServers::get_url( $this->server, $this->directory . $filename ), $external_file_obj->get_url( true ) );
		$this->assertNotEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
		$this->assertTrue( has_term( $this->term_id, Taxonomy::get_instance()->get_name(), $attachment_id ) );
	}

	/**
	 * Test that deleting the exported file in the media library deletes it on the server.
	 *
	 * @return void
	 */
	public function test_delete_exported_file(): void {
		$this->create_export_source();
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );

		// get the used file name (WordPress adds a number, if the file exists in the uploads directory).
		$filename = basename( (string) get_attached_file( $attachment_id, true ) );
		$this->assertFileExists( $this->get_server_path( $filename ) );

		@wp_delete_attachment( $attachment_id, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFileDoesNotExist( $this->get_server_path( $filename ) );

		// the other files on the server are not touched.
		$this->assertFileExists( $this->get_server_path( 'example-red.jpg' ) );
	}

	/**
	 * Test that an upload with the name of an existing file on the server does not overwrite it.
	 *
	 * The upload is not exported then and stays a local file.
	 *
	 * A unique file name is used, as WordPress would rename the upload if a file with this name
	 * exists in the uploads directory (e.g. from previous test runs).
	 *
	 * @return void
	 */
	public function test_existing_file_on_server_is_not_overwritten(): void {
		$this->create_export_source();
		$filename = 'existing-' . wp_generate_password( 8, false, false ) . '.png';
		file_put_contents( $this->get_server_path( $filename ), 'existing file' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$attachment_id = $this->upload( 'example-blue.png', $filename );
		$this->assertSame( $filename, basename( (string) get_attached_file( $attachment_id, true ) ) );

		// the file on the server is unchanged.
		$this->assertStringEqualsFile( $this->get_server_path( $filename ), 'existing file' );

		// the upload is not exported and stays local.
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
		$this->assertFileEquals( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-blue.png', (string) get_attached_file( $attachment_id ) );

		// the reason is logged with the URL of the file on the server.
		$logs = \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_logs( TestServers::get_url( $this->server, $this->directory . $filename ), 'error' );
		$this->assertNotEmpty( $logs );
		$this->assertStringContainsString( 'already exists on the FTP server', (string) wp_json_encode( $logs ) );
	}

	/**
	 * Test that nothing is exported if the export is disabled for the external source.
	 *
	 * @return void
	 */
	public function test_no_export_if_disabled_for_source(): void {
		$this->create_export_source();
		Export::get_instance()->set_state_for_term( $this->term_id, 0 );
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );

		$this->assertSame( array(), glob( $this->get_server_path( 'example-grey*' ) ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFileExists( (string) get_attached_file( $attachment_id ) );
	}

	/**
	 * Test that files with a not allowed file type are not exported.
	 *
	 * @return void
	 */
	public function test_no_export_of_not_allowed_file_type(): void {
		$this->create_export_source();
		unlink( $this->get_server_path( 'example.txt' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		$attachment_id = $this->upload( 'example.txt' );

		$this->assertFileDoesNotExist( $this->get_server_path( 'example.txt' ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

	/**
	 * Test that files outside the configured size range of the source are not exported.
	 *
	 * @return void
	 */
	public function test_no_export_outside_size_range(): void {
		$this->create_export_source();
		update_option( 'eml_export_extensions', array( 'export_by_size' ) );

		// the test file has 822 bytes.
		update_term_meta( $this->term_id, 'eml_export_min_size', 1000 );
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$this->assertSame( array(), glob( $this->get_server_path( 'example-grey*' ) ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// with a lower minimum size it is exported.
		update_term_meta( $this->term_id, 'eml_export_min_size', 500 );
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertCount( 1, glob( $this->get_server_path( 'example-grey*' ) ) );
	}

	/**
	 * Test that a failed export keeps the file local and leaves no incomplete file on the server.
	 *
	 * @return void
	 */
	public function test_failed_export_keeps_local_file(): void {
		$this->create_export_source( 'ftps_noreuse' );
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );

		$this->assertSame( array(), glob( $this->get_server_path( 'example-grey*' ) ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
		$this->assertFileExists( (string) get_attached_file( $attachment_id ) );
	}

	/**
	 * Upload the given test file without exporting it and return its ID and the path of its local file.
	 *
	 * @param string $file The test file in tests/Data/ftp.
	 *
	 * @return array{0: int, 1: string}
	 */
	private function upload_local( string $file ): array {
		$tmp = trailingslashit( get_temp_dir() ) . basename( $file );
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $file, $tmp );
		$attachment_id = @self::factory()->attachment->create_upload_object( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertIsInt( $attachment_id );
		$path = (string) get_attached_file( $attachment_id );
		$this->assertFileExists( $path );
		return array( $attachment_id, $path );
	}

	/**
	 * Test that the local file of an uploaded file is deleted after its export, as it is used from the server then.
	 *
	 * @return void
	 */
	public function test_local_file_is_deleted_after_export(): void {
		$this->create_export_source();
		list( $attachment_id, $path ) = $this->upload_local( 'example-red.jpg' );

		$this->assertTrue( @Export::get_instance()->export_file( $attachment_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFileDoesNotExist( $path );
		$this->assertFileExists( $this->get_server_path( basename( $path ) ) );
	}

	/**
	 * Test that the local file of an uploaded file is kept after its export, if this is enabled.
	 *
	 * @return void
	 */
	public function test_local_file_is_kept_after_export_if_enabled(): void {
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		$this->create_export_source();
		list( $attachment_id, $path ) = $this->upload_local( 'example-red.jpg' );

		$this->assertTrue( @Export::get_instance()->export_file( $attachment_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFileExists( $path );
		$this->assertFileExists( $this->get_server_path( basename( $path ) ) );
	}

	/**
	 * Test the bulk action to export local files of the media library.
	 *
	 * @return void
	 */
	public function test_bulk_export(): void {
		$this->create_export_source();
		update_option( 'eml_export_local_files', 1 );
		list( $first_id ) = $this->upload_local( 'example-red.jpg' );
		list( $second_id ) = $this->upload_local( 'subdirectory/example-grey.jpg' );

		// another action does nothing.
		$this->assertSame( 'sendback', Export::get_instance()->run_bulk_action( 'sendback', 'delete', array( $first_id ) ) );
		$this->assertEmpty( get_post_meta( $first_id, 'eml_exported_file', true ) );

		// without the option to export local files, nothing is exported.
		update_option( 'eml_export_local_files', 0 );
		@Export::get_instance()->run_bulk_action( 'sendback', 'efml-export', array( $first_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertEmpty( get_post_meta( $first_id, 'eml_exported_file', true ) );
		update_option( 'eml_export_local_files', 1 );

		// export both files.
		$this->assertSame( 'sendback', @Export::get_instance()->run_bulk_action( 'sendback', 'efml-export', array( $first_id, $second_id ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		foreach ( array( $first_id, $second_id ) as $attachment_id ) {
			$this->assertNotEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
			$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		}
		$this->assertFileExists( $this->get_server_path( basename( Files::get_instance()->get_file( $second_id )->get_url( true ) ) ) );

		// an already exported file is not exported again.
		$exported = get_post_meta( $first_id, 'eml_exported_file', true );
		@Export::get_instance()->run_bulk_action( 'sendback', 'efml-export', array( $first_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( $exported, get_post_meta( $first_id, 'eml_exported_file', true ) );
	}

	/**
	 * Test that a user without the capability to export files can not use the bulk action.
	 *
	 * @return void
	 */
	public function test_bulk_export_without_capability(): void {
		$this->create_export_source();
		update_option( 'eml_export_local_files', 1 );
		list( $attachment_id ) = $this->upload_local( 'example-red.jpg' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		@Export::get_instance()->run_bulk_action( 'sendback', 'efml-export', array( $attachment_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
	}

	/**
	 * Test that the synchronization of the export source neither imports the exported files again nor deletes them.
	 *
	 * @return void
	 */
	public function test_synchronization_of_export_source(): void {
		$this->create_export_source();
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		$attachment_id = $this->upload( 'subdirectory/example-grey.jpg' );
		$url           = Files::get_instance()->get_file( $attachment_id )->get_url( true );
		$source_url    = TestServers::get_url( $this->server, $this->directory );

		@\ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->sync( $source_url, Taxonomy::get_instance()->get_entry( $this->term_id ), $this->term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// the other files on the server are imported.
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, Files::get_instance()->get_file_by_url( $source_url . 'example-red.jpg' ) );

		// the exported file still exists once, with its attachment.
		$this->assertInstanceOf( \WP_Post::class, get_post( $attachment_id ) );
		$this->assertSame( $attachment_id, Files::get_instance()->get_file_by_url( $url )->get_id() );
		$ids = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => -1,
				'meta_key'    => EFML_POST_META_URL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$this->assertCount( 1, $ids );
	}

	/**
	 * Test that the deletion of the main export source removes it as main export source.
	 *
	 * @return void
	 */
	public function test_deleting_main_export_source(): void {
		$this->create_export_source();
		$this->assertSame( $this->term_id, absint( get_option( 'eml_export_main_source' ) ) );

		wp_delete_term( $this->term_id, Taxonomy::get_instance()->get_name() );
		$this->assertSame( 0, absint( get_option( 'eml_export_main_source' ) ) );
	}

	/**
	 * Test that disabling the export for the main export source removes it as main export source.
	 *
	 * @return void
	 */
	public function test_disabling_main_export_source(): void {
		$this->create_export_source();
		Export::get_instance()->set_state_for_term( $this->term_id, 0 );
		$this->assertSame( 0, absint( get_option( 'eml_export_main_source' ) ) );

		// an external source without enabled export can not be the main export source.
		update_option( 'eml_export_main_source', $this->term_id );
		$this->assertSame( 0, absint( get_option( 'eml_export_main_source' ) ) );
	}
}
