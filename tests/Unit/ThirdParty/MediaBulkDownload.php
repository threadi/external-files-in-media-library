<?php
/**
 * Tests for the support of the plugin "Media Bulk Download".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionProperty;

/**
 * Object to test the support of the plugin "Media Bulk Download".
 */
class MediaBulkDownload extends externalFilesTests {

	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-third-party-' . uniqid() . '/';
		wp_mkdir_p( $this->export_directory );
		add_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );

		// make sure the local service is used as export object.
		if ( false === has_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) ) ) {
			add_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) );
		}

		// create the external source and enable the export for it.
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file://' . $this->export_directory, array( 'path' => array( 'value' => $this->export_directory ) ) );
		$this->assertGreaterThan( 0, $term_id );
		update_option( 'eml_export_main_source', 0 );
		Export::get_instance()->set_state_for_term( $term_id, 1 );
		update_term_meta( $term_id, 'efml_export_url', 'https://example.com/export/' );
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 0 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_in_background', 0 );

		// start with an empty cache.
		$property = new ReflectionProperty( Local_Urls::class, 'file_cache' );
		$property->setValue( Local_Urls::get_instance(), array() );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
		parent::tear_down();
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
	 * Create an image attachment with sizes.
	 *
	 * @return int
	 */
	private function create_image(): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-third-party-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		return $attachment_id;
	}

	/**
	 * Create an exported image and return its ID and its former local files.
	 *
	 * @return array{0:int,1:array<int,string>}
	 */
	private function create_exported_image(): array {
		$attachment_id = $this->create_image();
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$files = Local_Urls::get_instance()->get_all_local_files( $attachment_id );
		$this->assertGreaterThan( 1, count( $files ) );
		return array( $attachment_id, $files );
	}

	/**
	 * Return the support object.
	 *
	 * @return \ExternalFilesInMediaLibrary\ThirdParty\MediaBulkDownload
	 */
	private function get_object(): \ExternalFilesInMediaLibrary\ThirdParty\MediaBulkDownload {
		$obj = \ExternalFilesInMediaLibrary\ThirdParty\MediaBulkDownload::get_instance();
		$obj->add_hooks();
		return $obj;
	}

	/**
	 * Test the detection of the requests of the plugin.
	 *
	 * @return void
	 */
	public function test_detect_requests(): void {
		$obj = $this->get_object();
		$obj->set_active( false );

		// test 1: other bulk actions are not marked.
		$this->assertEquals( 'url', $obj->detect_bulk_action( 'url', 'delete' ) );
		$this->assertFalse( $obj->is_active() );

		// test 2: the bulk action of the plugin is marked.
		$obj->detect_bulk_action( 'url', 'pluginnests_mbdl_download_zip' );
		$this->assertTrue( $obj->is_active() );
		$obj->set_active( false );

		// test 3: AJAX requests are marked only for the actions of the plugin.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'other_action';
		$obj->detect_ajax_request();
		$this->assertFalse( $obj->is_active() );
		$_REQUEST['action'] = 'pluginnests_mbdl_type_download';
		$obj->detect_ajax_request();
		$this->assertTrue( $obj->is_active() );
		unset( $_REQUEST['action'] );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		$obj->set_active( false );
	}

	/**
	 * Test the local file of external files during the requests of the plugin.
	 *
	 * @return void
	 */
	public function test_local_file(): void {
		$obj      = $this->get_object();
		$local_id = $this->create_image();
		list( $attachment_id, $files ) = $this->create_exported_image();
		$local_file = (string) get_attached_file( $local_id );

		// test 1: nothing changes without a request of the plugin.
		$obj->set_active( false );
		$this->assertStringContainsString( $this->export_directory, (string) get_attached_file( $attachment_id ) );

		// test 2: during a request of the plugin, external files get a readable local file with the content of the file.
		$obj->set_active( true );
		$path = (string) get_attached_file( $attachment_id );
		$this->assertFileExists( $path );
		$this->assertEquals( file_get_contents( $this->export_directory . basename( $files[0] ) ), file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( $path, get_attached_file( $attachment_id ) );

		// test 3: the file has the name of the file in the ZIP file.
		$this->assertEquals( basename( $files[0] ), $obj->get_entry_name( basename( $path ), $path ) );
		$this->assertEquals( 'other.jpg', $obj->get_entry_name( 'other.jpg', '/tmp/other.jpg' ) );

		// test 4: local files are not changed.
		$this->assertEquals( $local_file, get_attached_file( $local_id ) );

		// test 5: temporary files are deleted at the end of the request.
		$obj->delete_temp_files();
		if ( ! str_contains( $path, 'cache' ) ) {
			$this->assertFileDoesNotExist( $path );
		}
		$obj->set_active( false );
	}
}
