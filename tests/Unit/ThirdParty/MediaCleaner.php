<?php
/**
 * Tests for the support of the plugin "Media Cleaner".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionProperty;
use WP_REST_Request;

/**
 * Object to test the support of the plugin "Media Cleaner".
 */
class MediaCleaner extends externalFilesTests {

	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * The references, which the simulated Media Cleaner has found.
	 *
	 * @var array<int,string>
	 */
	public array $references = array();

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-media-cleaner-' . uniqid() . '/';
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

		// use the support of Media Cleaner.
		$this->get_object()->add_hooks();

		// start with an empty cache and without a request of Media Cleaner.
		$property = new ReflectionProperty( Local_Urls::class, 'file_cache' );
		$property->setAccessible( true );
		$property->setValue( Local_Urls::get_instance(), array() );
		$property = new ReflectionProperty( \ExternalFilesInMediaLibrary\ThirdParty\MediaCleaner::class, 'is_scan' );
		$property->setAccessible( true );
		$property->setValue( $this->get_object(), false );

		// simulate the core object of Media Cleaner.
		$this->references     = array();
		$GLOBALS['wpmc_core'] = new class( $this ) {
			/**
			 * The test object.
			 *
			 * @var MediaCleaner
			 */
			private MediaCleaner $test;

			/**
			 * Constructor.
			 *
			 * @param MediaCleaner $test The test object.
			 */
			public function __construct( MediaCleaner $test ) {
				$this->test = $test;
			}

			/**
			 * Return whether the given file is referenced.
			 *
			 * @param string $file     The file.
			 * @param int    $media_id The media ID.
			 *
			 * @return false|string
			 */
			public function reference_exists( string $file, int $media_id ): false|string {
				return in_array( $file, $this->test->references, true ) ? 'CONTENT (URL)' : false;
			}
		};
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $GLOBALS['wpmc_core'] );
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
	 * Return the support object.
	 *
	 * @return \ExternalFilesInMediaLibrary\ThirdParty\MediaCleaner
	 */
	private function get_object(): \ExternalFilesInMediaLibrary\ThirdParty\MediaCleaner {
		return \ExternalFilesInMediaLibrary\ThirdParty\MediaCleaner::get_instance();
	}

	/**
	 * Create an image attachment with sizes.
	 *
	 * @return int
	 */
	private function create_image(): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-media-cleaner-' . wp_generate_password( 6, false ) . '.jpg';
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
	 * Simulate a request to the REST API of Media Cleaner.
	 *
	 * @return void
	 */
	private function start_scan(): void {
		$this->get_object()->detect_scan( null, null, new WP_REST_Request( 'GET', '/media-cleaner/v1/stats' ) );
	}

	/**
	 * Test the detection of requests of Media Cleaner.
	 *
	 * @return void
	 */
	public function test_detect_scan(): void {
		$this->get_object()->detect_scan( null, null, new WP_REST_Request( 'GET', '/wp/v2/media' ) );
		$this->assertFalse( $this->get_object()->is_scan() );
		$this->start_scan();
		$this->assertTrue( $this->get_object()->is_scan() );
	}

	/**
	 * Test the detection of broken files: external files use their availability.
	 *
	 * @return void
	 */
	public function test_is_file_broken(): void {
		// test 1: local files keep the result of Media Cleaner.
		$local_id = $this->create_image();
		$this->assertTrue( $this->get_object()->is_file_broken( true, $local_id ) );
		$this->assertFalse( $this->get_object()->is_file_broken( false, $local_id ) );

		// test 2: exported files are not broken, if they are available.
		list( $attachment_id ) = $this->create_exported_image();
		$this->assertFalse( $this->get_object()->is_file_broken( true, $attachment_id ) );

		// test 3: but if they are not available.
		update_post_meta( $attachment_id, EFML_POST_META_AVAILABILITY, 0 );
		$this->assertTrue( $this->get_object()->is_file_broken( false, (string) $attachment_id ) );
	}

	/**
	 * Test the file of external files during requests of Media Cleaner.
	 *
	 * @return void
	 */
	public function test_attached_file(): void {
		$local_id                       = $this->create_image();
		list( $attachment_id, $files ) = $this->create_exported_image();
		$imported_id                    = $this->create_image();
		update_post_meta( $imported_id, '_wp_attached_file', 'ftp://example.com/file.jpg' );
		update_post_meta( $imported_id, EFML_POST_META_URL, 'ftp://example.com/file.jpg' );

		// test 1: nothing changes without a request of Media Cleaner.
		$this->assertEquals( 'ftp://example.com/file.jpg', $this->get_object()->prevent_stream_access( 'ftp://example.com/file.jpg', $imported_id ) );
		$this->assertStringContainsString( $this->export_directory, (string) get_attached_file( $attachment_id ) );

		// test 2: during a request of Media Cleaner.
		$this->start_scan();
		$this->assertEquals( trailingslashit( wp_get_upload_dir()['basedir'] ) . $files[0], get_attached_file( $attachment_id ) );
		$this->assertFalse( $this->get_object()->prevent_stream_access( 'ftp://example.com/file.jpg', $imported_id ) );
		$this->assertEquals( '/path/file.jpg', $this->get_object()->prevent_stream_access( '/path/file.jpg', $local_id ) );
		$this->assertFalse( $this->get_object()->prevent_stream_access( false, $local_id ) );
	}

	/**
	 * Test the usage check of media.
	 *
	 * @return void
	 */
	public function test_check_media(): void {
		$local_id                       = $this->create_image();
		list( $attachment_id, $files ) = $this->create_exported_image();
		$imported_id                    = $this->create_image();
		update_post_meta( $imported_id, EFML_POST_META_URL, 'https://example.com/file.jpg' );
		delete_post_meta( $imported_id, Local_Urls::META );

		// test 1: used and broken media keep the result of Media Cleaner.
		$this->assertTrue( $this->get_object()->check_media( true, $attachment_id, false ) );
		$this->assertFalse( $this->get_object()->check_media( false, $attachment_id, true ) );

		// test 2: local files keep the result of Media Cleaner.
		$this->assertFalse( $this->get_object()->check_media( false, $local_id, false ) );

		// test 3: exported files are used, if one of their former local files (e.g. of a size) is referenced.
		$this->assertFalse( $this->get_object()->check_media( false, $attachment_id, false ) );
		$this->references = array( end( $files ) );
		$this->assertTrue( $this->get_object()->check_media( false, $attachment_id, false ) );

		// test 4: they are used, if the references could not be checked.
		unset( $GLOBALS['wpmc_core'] );
		$this->assertTrue( $this->get_object()->check_media( false, $attachment_id, false ) );

		// test 5: imported files are used, unless the filter allows the check of Media Cleaner.
		$this->assertTrue( $this->get_object()->check_media( false, $imported_id, false ) );
		add_filter( 'efml_media_cleaner_check_external_files', '__return_true' );
		$this->assertFalse( $this->get_object()->check_media( false, $imported_id, false ) );
		remove_filter( 'efml_media_cleaner_check_external_files', '__return_true' );
	}

	/**
	 * Test the check of files in the filesystem scan.
	 *
	 * @return void
	 */
	public function test_check_file(): void {
		list( , $files ) = $this->create_exported_image();
		$main            = $files[0];
		$size            = end( $files );
		$base            = trailingslashit( wp_get_upload_dir()['basedir'] );

		// test 1: used files and unknown files keep the result of Media Cleaner.
		$this->assertTrue( $this->get_object()->check_file( true, 'unknown.jpg' ) );
		$this->assertEquals( 'NO_CONTENT', $this->get_object()->check_file( 'NO_CONTENT', '2026/11/unknown.jpg' ) );
		$this->assertEquals( 'NO_CONTENT', $this->get_object()->check_file( 'NO_CONTENT', '' ) );

		// test 2: local files of exported files are used: the file, its size, variants and backups.
		$this->assertTrue( $this->get_object()->check_file( 'NO_CONTENT', $main ) );
		$this->assertTrue( $this->get_object()->check_file( 'NO_CONTENT', $base . $size ) );
		$this->assertTrue( $this->get_object()->check_file( 'NO_CONTENT', $main . '.webp' ) );
		$this->assertTrue( $this->get_object()->check_file( 'NO_CONTENT', (string) preg_replace( '#\.jpg$#', '.avif', $main ) ) );
		$this->assertTrue( $this->get_object()->check_file( 'NO_CONTENT', (string) preg_replace( '#\.jpg$#', '.ewww-backup.jpg', $main ) ) );
	}

	/**
	 * Test the local files of an exported image.
	 *
	 * @return void
	 */
	public function test_local_files(): void {
		list( $attachment_id, $files ) = $this->create_exported_image();

		// the main file and its sizes are in the same folder.
		$folder = dirname( $files[0] );
		foreach ( $files as $file ) {
			$this->assertEquals( $folder, dirname( $file ) );
			$this->assertEquals( $attachment_id, Local_Urls::get_instance()->get_attachment_of_local_file( $file ) );
		}

		// local files have none.
		$this->assertEquals( array(), Local_Urls::get_instance()->get_all_local_files( $this->create_image() ) );
		$this->assertEquals( 0, Local_Urls::get_instance()->get_attachment_of_local_file( '2026/11/unknown.jpg' ) );
	}
}
