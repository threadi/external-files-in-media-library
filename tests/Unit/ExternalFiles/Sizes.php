<?php
/**
 * Tests for the sizes of exported images.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the sizes of exported images.
 */
class Sizes extends externalFilesTests {

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

		// forget the file types of former tests, as attachment IDs could be used again after their rollback.
		$property = new \ReflectionProperty( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::class, 'files' );
		$property->setValue( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance(), array() );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-sizes-' . uniqid() . '/';
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

		// enable the export.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 0 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_in_background', 0 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		remove_all_filters( 'efml_file_prevent_proxied_url' );
		Import_Runs::get_instance()->cancel_all();
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
	 * Create an exported image and return its ID.
	 *
	 * @param int $width  The width.
	 * @param int $height The height.
	 *
	 * @return int
	 */
	private function create_exported_image( int $width = 3000, int $height = 2000 ): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-size-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( $width, $height );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		return $attachment_id;
	}

	/**
	 * Test that the sizes and the original image are exported together with the image.
	 *
	 * @return void
	 */
	public function test_export_with_sizes(): void {
		$attachment_id = $this->create_exported_image();

		// the sizes and the original image are saved.
		$sizes = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id, true );
		$this->assertArrayHasKey( 'medium', $sizes );
		$this->assertArrayHasKey( 'thumbnail', $sizes );
		$this->assertArrayHasKey( \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::ORIGINAL, $sizes );

		// they exist on the external source.
		foreach ( $sizes as $size ) {
			$this->assertFileExists( $this->export_directory . $size['file'] );
		}

		// the original image has the dimensions of the upload.
		$original = wp_getimagesize( $this->export_directory . $sizes[ \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::ORIGINAL ]['file'] );
		$this->assertIsArray( $original );
		$this->assertEquals( 3000, $original[0] );
	}

	/**
	 * Test that the deletion of the local files after the export does not delete files on the external source.
	 *
	 * @return void
	 */
	public function test_local_cleanup_keeps_target(): void {
		$attachment_id = $this->create_exported_image();
		$sizes         = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id, true );
		$base_dir      = trailingslashit( wp_get_upload_dir()['basedir'] );
		$paths         = \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->get_original_paths( $attachment_id );

		// run the cleanup of the local files like WordPress does after the export.
		Export::get_instance()->update_attachment_metadata( (array) wp_get_attachment_metadata( $attachment_id ), $attachment_id );

		// the local files are deleted.
		$this->assertFileDoesNotExist( $base_dir . $paths['file'] );
		$this->assertFileDoesNotExist( $base_dir . $paths['original'] );

		// the files on the external source are kept.
		foreach ( $sizes as $size ) {
			$this->assertFileExists( $this->export_directory . $size['file'] );
		}
	}

	/**
	 * Test the delivery of the sizes via the proxy (default for images).
	 *
	 * @return void
	 */
	public function test_delivery_via_proxy(): void {
		$attachment_id = $this->create_exported_image();
		$medium        = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id )['medium'];

		// the size is delivered via the proxy with its dimensions.
		$image = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$this->assertIsArray( $image );
		$this->assertStringContainsString( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), $image[0] );
		$this->assertStringEndsWith( '-' . $medium['width'] . 'x' . $medium['height'] . '.jpg', remove_query_arg( 'ver', $image[0] ) );
		$this->assertStringContainsString( 'ver=', $image[0] );
		$this->assertEquals( $medium['width'], $image[1] );

		// the srcset contains the proxy URLs of the sizes.
		$html = wp_get_attachment_image( $attachment_id, 'large' );
		$this->assertStringContainsString( 'srcset="', $html );
		$this->assertStringNotContainsString( 'file://', $html );
		$this->assertMatchesRegularExpression( '/-' . $medium['width'] . 'x' . $medium['height'] . '\\.jpg(&amp;ver=\\d+)? ' . $medium['width'] . 'w/', $html );

		// the proxy loads the size from the external source into its cache.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->cache_size( $external_file_obj, array( absint( $medium['width'] ), absint( $medium['height'] ) ) ) );
		$cached = wp_getimagesize( $external_file_obj->get_cache_file( array( absint( $medium['width'] ), absint( $medium['height'] ) ) ) );
		$this->assertIsArray( $cached );
		$this->assertEquals( $medium['width'], $cached[0] );
		$this->assertFalse( $external_file_obj->is_cached() );
	}

	/**
	 * Test that the REST API returns the sizes of exported images, as the block editor needs them for the choice of the size.
	 *
	 * @return void
	 */
	public function test_rest_response_contains_sizes(): void {
		$attachment_id = $this->create_exported_image();
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// get the response.
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id ) );
		$data     = $response->get_data();
		$this->assertIsArray( $data );
		$sizes = (array) $data['media_details']['sizes'];

		// the sizes and the full image are contained, each with its own URL.
		$this->assertArrayHasKey( 'medium', $sizes );
		$this->assertArrayHasKey( 'large', $sizes );
		$this->assertArrayHasKey( 'full', $sizes );
		$this->assertNotEquals( $sizes['full']['source_url'], $sizes['medium']['source_url'] );
		$this->assertEquals( \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id )['medium']['width'], $sizes['medium']['width'] );
	}

	/**
	 * Test the delivery of the sizes of private files: always via the proxy, which loads the exported size.
	 *
	 * @return void
	 */
	public function test_delivery_of_private_file(): void {
		// disable the proxy for images, which must not apply to private files.
		update_option( 'eml_images_mode', 'external' );
		update_option( 'eml_images_proxy', 0 );
		$attachment_id = $this->create_exported_image();
		$medium        = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id )['medium'];

		// mark the file as private.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$external_file_obj->set_private( true );
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->is_usable( $external_file_obj ) );

		// the size is delivered via the proxy.
		$image = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$this->assertIsArray( $image );
		$this->assertStringContainsString( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), $image[0] );
		$this->assertStringEndsWith( '-' . $medium['width'] . 'x' . $medium['height'] . '.jpg', remove_query_arg( 'ver', $image[0] ) );
		$this->assertStringContainsString( 'ver=', $image[0] );

		// the srcset contains only proxy URLs.
		$html = wp_get_attachment_image( $attachment_id, 'large' );
		$this->assertStringContainsString( 'srcset="', $html );
		$this->assertStringNotContainsString( 'https://example.com/export/', $html );
		$this->assertStringNotContainsString( 'file://', $html );

		// the proxy loads the exported size into its cache.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->cache_size( $external_file_obj, array( absint( $medium['width'] ), absint( $medium['height'] ) ) ) );
		$this->assertFileExists( $external_file_obj->get_cache_file( array( absint( $medium['width'] ), absint( $medium['height'] ) ) ) );
	}

	/**
	 * Test the delivery of the sizes without proxy: their URLs below the public URL of the external source are used,
	 * as browsers could not load the paths of files in a local directory.
	 *
	 * @return void
	 */
	public function test_delivery_without_proxy(): void {
		update_option( 'eml_images_mode', 'external' );
		update_option( 'eml_images_proxy', 0 );
		$attachment_id = $this->create_exported_image();
		$medium        = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id )['medium'];
		$public_url    = 'https://example.com/export/' . $medium['file'];

		// the image and its sizes use the public URL.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertFalse( $external_file_obj->is_proxy_required() );
		$this->assertStringStartsWith( 'https://example.com/export/', (string) wp_get_attachment_url( $attachment_id ) );
		$image = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$this->assertIsArray( $image );
		$this->assertEquals( $public_url, $image[0] );
		$this->assertStringContainsString( $public_url . ' ' . $medium['width'] . 'w', wp_get_attachment_image( $attachment_id, 'large' ) );

		// without public URL of the external source, the proxy is required.
		foreach ( wp_get_object_terms( $attachment_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(), array( 'fields' => 'ids' ) ) as $term_id ) {
			delete_term_meta( absint( $term_id ), 'efml_export_url' );
		}
		$this->assertTrue( $external_file_obj->is_proxy_required() );
		$image = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$this->assertIsArray( $image );
		$this->assertStringContainsString( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), $image[0] );
	}

	/**
	 * Test the srcset of an exported image in a content with its local URL.
	 *
	 * @return void
	 */
	public function test_content_srcset(): void {
		$attachment_id = $this->create_exported_image();
		$local_url     = trailingslashit( wp_get_upload_dir()['baseurl'] ) . dirname( \ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls::get_instance()->get_local_paths( $attachment_id )[0] ) . '/' . \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id )['large']['file'];

		// the srcset is added with local URLs (they are replaced by Local_Urls during the output).
		$image = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->add_srcset_to_content_image( '<img src="' . $local_url . '" width="1024" height="683" class="wp-image-' . $attachment_id . '">', 'the_content', $attachment_id );
		$this->assertStringContainsString( ' srcset="', $image );
		$this->assertStringContainsString( ' sizes="', $image );
	}

	/**
	 * Test that the sizes are deleted on the external source together with the image.
	 *
	 * @return void
	 */
	public function test_delete(): void {
		$attachment_id = $this->create_exported_image();
		$sizes         = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id, true );

		Export::get_instance()->delete_exported_file( $attachment_id );

		foreach ( $sizes as $size ) {
			$this->assertFileDoesNotExist( $this->export_directory . $size['file'] );
		}
		$this->assertEmpty( get_post_meta( $attachment_id, \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::META, true ) );
	}

	/**
	 * Test the export of the sizes of images, which have been exported without them.
	 *
	 * @return void
	 */
	public function test_export_of_sizes_of_exported_images(): void {
		$attachment_id = $this->create_exported_image();
		$sizes         = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id );

		// simulate an image exported without its sizes (and without local files).
		foreach ( \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id, true ) as $size ) {
			wp_delete_file( $this->export_directory . $size['file'] );
		}
		delete_post_meta( $attachment_id, \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::META );
		$base_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
		$paths    = \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->get_original_paths( $attachment_id );
		wp_delete_file( $base_dir . $paths['file'] );
		wp_delete_file( $base_dir . $paths['original'] );
		$this->assertEquals( 1, \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->count_images_without_sizes() );

		// export them.
		$result = Import_Runs::get_instance()->start_sizes();
		$this->assertTrue( $result['success'] );

		// the sizes exist again with their former names.
		$new_sizes = \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->get_sizes( $attachment_id );
		$this->assertEquals( wp_list_pluck( $sizes, 'file' ), wp_list_pluck( $new_sizes, 'file' ) );
		foreach ( $new_sizes as $size ) {
			$this->assertFileExists( $this->export_directory . $size['file'] );
		}
		$this->assertEquals( 0, \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->count_images_without_sizes() );
		$this->assertFalse( Import_Runs::get_instance()->start_sizes()['success'] );
	}

	/**
	 * Test that the restore uses the exported original image.
	 *
	 * @return void
	 */
	public function test_restore_with_original(): void {
		$attachment_id = $this->create_exported_image();

		// remove the local files, so they must be downloaded.
		$base_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
		$paths    = \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->get_original_paths( $attachment_id );
		wp_delete_file( $base_dir . $paths['file'] );
		wp_delete_file( $base_dir . $paths['original'] );

		// restore it.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_file( $attachment_id ) );

		// the original image has its full size again and the metadata the size of the scaled image.
		$original = wp_getimagesize( $base_dir . $paths['original'] );
		$this->assertIsArray( $original );
		$this->assertEquals( 3000, $original[0] );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$this->assertEquals( 2560, $metadata['width'] );
	}

	/**
	 * Test a service without support for sizes: nothing changes.
	 *
	 * @return void
	 */
	public function test_service_without_sizes(): void {
		$export_obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Export_Base();
		$this->assertFalse( $export_obj->supports_sizes() );

		$file          = trailingslashit( get_temp_dir() ) . 'efml-size-none.jpg';
		$image         = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );

		\ExternalFilesInMediaLibrary\ExternalFiles\Sizes::get_instance()->export_sizes( $attachment_id, $export_obj, 1, 'file:///tmp/x.jpg', array() );
		$this->assertEmpty( get_post_meta( $attachment_id, \ExternalFilesInMediaLibrary\ExternalFiles\Sizes::META, true ) );
	}
}
