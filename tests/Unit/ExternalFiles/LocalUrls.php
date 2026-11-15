<?php
/**
 * Tests for the replacement of local URLs of exported files in contents.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionProperty;

/**
 * Object to test the replacement of local URLs of exported files in contents.
 */
class LocalUrls extends externalFilesTests {

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-local-urls-' . uniqid() . '/';
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

		// enable the export, which deletes the local files.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 0 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_in_background', 0 );

		// start with an empty cache.
		$this->reset_cache();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'efml_local_urls_backfill_size' );
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		wp_clear_scheduled_hook( Local_Urls::CRON_HOOK );
		delete_option( Local_Urls::BACKFILL_OPTION );
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
	 * Reset the cache of the attachments of local files.
	 *
	 * @return void
	 */
	private function reset_cache(): void {
		$property = new ReflectionProperty( Local_Urls::class, 'file_cache' );
		$property->setAccessible( true );
		$property->setValue( Local_Urls::get_instance(), array() );
	}

	/**
	 * Create an image attachment with sizes.
	 *
	 * @return int
	 */
	private function create_image(): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-local-url-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		return $attachment_id;
	}

	/**
	 * Test that the export saves the local path and the local URLs in contents are replaced afterward.
	 *
	 * @return void
	 */
	public function test_export_and_replacement(): void {
		// create an image with sizes and get its local URLs.
		$attachment_id = $this->create_image();
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$this->assertNotEmpty( $metadata['sizes'] );
		$size      = (array) reset( $metadata['sizes'] );
		$local_url = (string) wp_get_attachment_url( $attachment_id );
		$size_url  = trailingslashit( dirname( $local_url ) ) . $size['file'];

		// export it.
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );

		// the local path is saved.
		$this->assertEquals( array( $attached_file ), Local_Urls::get_instance()->get_local_paths( $attachment_id ) );
		$this->assertEquals( 1, absint( get_option( Local_Urls::ACTIVE_OPTION ) ) );

		// the local URLs in a content are replaced: the file, its size, a srcset and a link without scheme.
		$content = '<p><img src="' . $local_url . '" srcset="' . $size_url . ' 300w, ' . $local_url . ' 800w" class="wp-image-' . $attachment_id . '"><img src="' . $size_url . '"><a href="' . str_replace( 'http:', '', $local_url ) . '">x</a> <img src="https://example.com/other.jpg"></p>';
		$output  = (string) Local_Urls::get_instance()->replace_urls( $content );
		$this->assertStringNotContainsString( $local_url, $output );
		$this->assertStringNotContainsString( $size_url, $output );
		$this->assertStringNotContainsString( str_replace( 'http:', '', $local_url ), $output );
		$this->assertStringContainsString( (string) wp_get_attachment_url( $attachment_id ), $output );
		$size_image = wp_get_attachment_image_src( $attachment_id, array( absint( $size['width'] ), absint( $size['height'] ) ) );
		$this->assertIsArray( $size_image );
		$this->assertStringContainsString( (string) $size_image[0], $output );
		$this->assertStringContainsString( 'https://example.com/other.jpg', $output );

		// the content in the database is not changed.
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );
		$this->assertEquals( $content, get_post_field( 'post_content', $post_id, 'raw' ) );

		// nothing is replaced, if the file is not an exported file anymore (e.g. switched to a local file).
		delete_post_meta( $attachment_id, 'eml_exported_file' );
		$this->reset_cache();
		$this->assertEquals( $content, Local_Urls::get_instance()->replace_urls( $content ) );
	}

	/**
	 * Test that the local paths of images in contents are added to the cache via their attachment ID.
	 *
	 * @return void
	 */
	public function test_image_cache(): void {
		global $wpdb;

		// create and export an image.
		$attachment_id = $this->create_image();
		$local_url     = (string) wp_get_attachment_url( $attachment_id );
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$this->reset_cache();

		// add the image to the cache like WordPress does for images in contents.
		$tag = '<img src="' . $local_url . '" class="wp-image-' . $attachment_id . '">';
		$this->assertEquals( $tag, Local_Urls::get_instance()->add_image_to_cache( $tag, 'the_content', $attachment_id ) );

		// the replacement does not need a query for it (the data of the attachment itself is loaded before).
		get_post_meta( $attachment_id );
		wp_get_attachment_url( $attachment_id );
		$queries = $wpdb->num_queries;
		$output  = (string) Local_Urls::get_instance()->replace_urls( $tag );
		$this->assertStringNotContainsString( $local_url, $output );
		$this->assertEquals( $queries, $wpdb->num_queries );
	}

	/**
	 * Test that the scaled file and its original image of large images are both saved.
	 *
	 * @return void
	 */
	public function test_scaled_image(): void {
		// create a large image, which WordPress scales.
		$file  = trailingslashit( get_temp_dir() ) . 'efml-local-url-large-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( 2700, 300 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$this->assertStringContainsString( '-scaled.jpg', $attached_file );

		// save its local paths.
		Local_Urls::get_instance()->save_local_paths( $attachment_id );
		$this->assertEquals( array( $attached_file, str_replace( '-scaled.jpg', '.jpg', $attached_file ) ), Local_Urls::get_instance()->get_local_paths( $attachment_id ) );

		// nothing is saved for files with a URL as attached file.
		update_post_meta( $attachment_id, '_wp_attached_file', 'https://example.com/image.jpg' );
		delete_post_meta( $attachment_id, Local_Urls::META );
		Local_Urls::get_instance()->save_local_paths( $attachment_id );
		$this->assertEmpty( Local_Urls::get_instance()->get_local_paths( $attachment_id ) );
	}

	/**
	 * Test that other contents are not changed.
	 *
	 * @return void
	 */
	public function test_other_contents(): void {
		// a local file, which is not exported.
		$attachment_id = $this->create_image();
		$local_url     = (string) wp_get_attachment_url( $attachment_id );
		$content       = '<img src="' . $local_url . '">';
		$this->assertEquals( $content, Local_Urls::get_instance()->replace_urls( $content ) );

		// contents without URLs and other values.
		$this->assertEquals( 'Hello', Local_Urls::get_instance()->replace_urls( 'Hello' ) );
		$this->assertEquals( '', Local_Urls::get_instance()->replace_urls( '' ) );
		$this->assertEquals( array( 'x' ), Local_Urls::get_instance()->replace_urls( array( 'x' ) ) );
	}

	/**
	 * Test the path relative to the uploads directory.
	 *
	 * @return void
	 */
	public function test_relative_path(): void {
		$base_url = wp_get_upload_dir()['baseurl'];
		$this->assertEquals( '2026/11/image.jpg', Local_Urls::get_instance()->get_relative_path( $base_url . '/2026/11/image.jpg' ) );
		$this->assertEquals( '2026/11/image.jpg', Local_Urls::get_instance()->get_relative_path( str_replace( 'http:', 'https:', $base_url ) . '/2026/11/image.jpg?ver=1' ) );
		$this->assertEquals( 'my image.jpg', Local_Urls::get_instance()->get_relative_path( $base_url . '/my%20image.jpg' ) );
		$this->assertEquals( '', Local_Urls::get_instance()->get_relative_path( 'https://example.com/image.jpg' ) );
	}

	/**
	 * Test the saving of local paths of files, which have been exported before 6.0.0, from their GUID.
	 *
	 * @return void
	 */
	public function test_backfill(): void {
		// create two files exported before 6.0.0 (without local paths) and one external file from another URL.
		$ids = array();
		for ( $i = 0; $i < 2; $i++ ) {
			$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
			$this->assertIsInt( $attachment_id );
			update_post_meta( $attachment_id, 'eml_exported_file', time() );
			$ids[] = $attachment_id;
		}
		$other_id = self::factory()->attachment->create( array( 'guid' => 'https://example.com/file.pdf', 'post_mime_type' => 'application/pdf' ) );
		update_post_meta( $other_id, 'eml_exported_file', time() );
		foreach ( array_merge( $ids, array( $other_id ) ) as $attachment_id ) {
			delete_post_meta( $attachment_id, Local_Urls::META );
		}
		delete_option( Local_Urls::BACKFILL_OPTION );

		// it is scheduled.
		Local_Urls::get_instance()->schedule_backfill();
		$this->assertNotFalse( wp_next_scheduled( Local_Urls::CRON_HOOK ) );
		wp_clear_scheduled_hook( Local_Urls::CRON_HOOK );

		// run it in parts of one file: it continues with the next part.
		add_filter(
			'efml_local_urls_backfill_size',
			function () {
				return 1;
			}
		);
		Local_Urls::get_instance()->run_backfill();
		$this->assertNotFalse( wp_next_scheduled( Local_Urls::CRON_HOOK ) );
		wp_clear_scheduled_hook( Local_Urls::CRON_HOOK );
		for ( $i = 0; $i < 10 && empty( ( (array) get_option( Local_Urls::BACKFILL_OPTION ) )['done'] ); $i++ ) {
			Local_Urls::get_instance()->run_backfill();
			wp_clear_scheduled_hook( Local_Urls::CRON_HOOK );
		}
		$this->assertNotEmpty( ( (array) get_option( Local_Urls::BACKFILL_OPTION ) )['done'] );

		// the local paths are taken from the GUID.
		foreach ( $ids as $attachment_id ) {
			$guid = (string) get_post_field( 'guid', $attachment_id, 'raw' );
			$this->assertEquals( array( Local_Urls::get_instance()->get_relative_path( $guid ) ), Local_Urls::get_instance()->get_local_paths( $attachment_id ) );
			$this->assertNotEmpty( Local_Urls::get_instance()->get_local_paths( $attachment_id )[0] );
		}

		// files with a GUID outside the uploads directory get none.
		$this->assertEmpty( Local_Urls::get_instance()->get_local_paths( $other_id ) );

		// it is not scheduled again, when it is done.
		Local_Urls::get_instance()->schedule_backfill();
		$this->assertFalse( wp_next_scheduled( Local_Urls::CRON_HOOK ) );
	}

	/**
	 * Test that a missing marker is restored from the saved local paths.
	 *
	 * @return void
	 */
	public function test_restore_marker(): void {
		$method = new \ReflectionMethod( Local_Urls::class, 'is_active' );
		$method->setAccessible( true );

		// without local paths the marker is restored as 0.
		delete_post_meta_by_key( Local_Urls::META );
		delete_option( Local_Urls::ACTIVE_OPTION );
		$this->assertFalse( $method->invoke( Local_Urls::get_instance() ) );
		$this->assertSame( '0', (string) get_option( Local_Urls::ACTIVE_OPTION ) );

		// an export sets it to 1.
		$attachment_id = $this->create_image();
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$this->assertEquals( 1, absint( get_option( Local_Urls::ACTIVE_OPTION ) ) );

		// with local paths a missing marker is restored as 1.
		delete_option( Local_Urls::ACTIVE_OPTION );
		$this->assertTrue( $method->invoke( Local_Urls::get_instance() ) );
		$this->assertEquals( 1, absint( get_option( Local_Urls::ACTIVE_OPTION ) ) );
	}
}
