<?php
/**
 * Tests for uploads whose image sizes are created in the browser (client-side media processing since WordPress 7.1).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Client_Uploads as Client_Uploads_Object;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;

/**
 * Object to test uploads with sizes created in the browser.
 *
 * The tests run in separate processes, as other tests define constants like "EFML_URL_IMPORT_RUNNING", which prevent
 * the export of uploads.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ClientUploads extends externalFilesTests {

	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * The image file to upload.
	 *
	 * @var string
	 */
	private string $image_file = '';

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// bail if client-side media processing is not available (before WordPress 7.1).
		if ( ! function_exists( 'wp_is_client_side_media_processing_enabled' ) ) {
			$this->markTestSkipped( 'Client-side media processing is not available.' );
		}

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// forget the file types of former tests, as attachment IDs could be used again after their rollback.
		$property = new \ReflectionProperty( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::class, 'files' );
		$property->setValue( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance(), array() );

		// enable the client-side media processing and register the routes with it.
		add_filter( 'wp_client_side_media_processing_enabled', '__return_true' );
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-client-' . uniqid() . '/';
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

		// make sure the export of uploads is active.
		if ( false === has_filter( 'wp_generate_attachment_metadata', array( Export::get_instance(), 'export_file_by_upload' ) ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( Export::get_instance(), 'export_file_by_upload' ), 20, 2 );
		}
		if ( false === has_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ) ) ) {
			add_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ), 10, 2 );
		}

		// create the image to upload.
		$this->image_file = trailingslashit( get_temp_dir() ) . 'efml-client-' . wp_generate_password( 6, false, false ) . '.jpg';
		$image            = imagecreatetruecolor( 2400, 1600 );
		imagejpeg( $image, $this->image_file );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'wp_client_side_media_processing_enabled', '__return_true' );
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		$GLOBALS['wp_rest_server'] = null;
		Import_Runs::get_instance()->cancel_all();
		if ( '' !== $this->export_directory ) {
			\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
		}
		if ( '' !== $this->image_file ) {
			wp_delete_file( $this->image_file );
		}
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
	 * Upload the image via the REST API like the block editor and return its ID.
	 *
	 * @param bool $client_side Whether the sizes are created in the browser.
	 *
	 * @return int
	 */
	private function upload( bool $client_side = true ): int {
		$request = new WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=' . basename( $this->image_file ) );
		if ( $client_side ) {
			$request->set_param( 'generate_sub_sizes', false );
		}
		$request->set_body( (string) file_get_contents( $this->image_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$response = rest_do_request( $request );
		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		return absint( $data['id'] );
	}

	/**
	 * Create the given sizes like the browser, send them to the sideload endpoint and return their data.
	 *
	 * @param int                           $attachment_id The attachment ID.
	 * @param array<string,array<int,bool|int>> $sizes     List of sizes (name => width, height, crop).
	 *
	 * @return array<int,mixed>
	 */
	private function sideload( int $attachment_id, array $sizes ): array {
		$sub_sizes = array();
		$name      = pathinfo( $this->image_file, PATHINFO_FILENAME );
		foreach ( $sizes as $size_name => $size ) {
			$editor = wp_get_image_editor( $this->image_file );
			$this->assertNotWPError( $editor );
			$editor->resize( absint( $size[0] ), absint( $size[1] ), (bool) $size[2] );
			$dimensions = $editor->get_size();
			$file       = trailingslashit( get_temp_dir() ) . $name . '-' . $dimensions['width'] . 'x' . $dimensions['height'] . '.jpg';
			$editor->save( $file );

			$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id . '/sideload' );
			$request->set_header( 'Content-Type', 'image/jpeg' );
			$request->set_header( 'Content-Disposition', 'attachment; filename=' . basename( $file ) );
			$request->set_param( 'image_size', $size_name );
			$request->set_body( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$response = rest_do_request( $request );
			wp_delete_file( $file );
			$this->assertEquals( 200, $response->get_status() );
			$sub_sizes[] = $response->get_data();
		}
		return $sub_sizes;
	}

	/**
	 * Test that the export of an upload waits until its sizes have been created in the browser.
	 *
	 * @return void
	 */
	public function test_upload_waits_for_finalization(): void {
		$attachment_id = $this->upload();

		// the file is not exported yet and waits for its sizes.
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertTrue( Client_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		$this->assertIsInt( wp_next_scheduled( Client_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );

		// the marker is only valid for the upload request.
		$this->assertFalse( Client_Uploads_Object::get_instance()->is_client_upload() );
	}

	/**
	 * Test that the finalization exports the file together with the sizes created in the browser.
	 *
	 * @return void
	 */
	public function test_finalize_exports_with_sizes(): void {
		$attachment_id = $this->upload();
		$sub_sizes     = $this->sideload(
			$attachment_id,
			array(
				'thumbnail' => array( 150, 150, true ),
				'medium'    => array( 300, 300, false ),
				'large'     => array( 1024, 1024, false ),
			)
		);

		// finalize the upload.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id . '/finalize' );
		$request->set_param( 'sub_sizes', $sub_sizes );
		$response = rest_do_request( $request );
		$this->assertEquals( 200, $response->get_status() );

		// the file is exported and does not wait anymore.
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( Client_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		$this->assertFalse( wp_next_scheduled( Client_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );

		// the sizes from the browser are exported.
		$sizes = Sizes::get_instance()->get_sizes( $attachment_id );
		$this->assertArrayHasKey( 'thumbnail', $sizes );
		$this->assertArrayHasKey( 'medium', $sizes );
		$this->assertArrayHasKey( 'large', $sizes );
		foreach ( $sizes as $size ) {
			$this->assertFileExists( $this->export_directory . $size['file'] );
		}

		// the content image gets a srcset with the sizes.
		$html = wp_get_attachment_image( $attachment_id, 'large' );
		$this->assertStringContainsString( 'srcset="', $html );
	}

	/**
	 * Test that an upload which is not finalized in the browser is exported via WP-Cron with sizes created on the server.
	 *
	 * @return void
	 */
	public function test_unfinalized_upload_is_exported(): void {
		$attachment_id = $this->upload();
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// run the planned event.
		do_action( Client_Uploads_Object::CRON_HOOK, $attachment_id );

		// the file is exported together with sizes created on the server.
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( Client_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		$this->assertArrayHasKey( 'medium', Sizes::get_instance()->get_sizes( $attachment_id ) );
	}

	/**
	 * Test that sizes for external files are rejected, as WordPress would save them beside their URL.
	 *
	 * @return void
	 */
	public function test_sideload_on_external_file_is_rejected(): void {
		$attachment_id = $this->upload( false );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id . '/sideload' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=example-150x150.jpg' );
		$request->set_param( 'image_size', 'thumbnail' );
		$request->set_body( (string) file_get_contents( $this->image_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$response = rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'efml_sideload_on_external_file', $response->as_error()->get_error_code() );
	}

	/**
	 * Test that uploads with sizes created on the server are exported immediately as before.
	 *
	 * @return void
	 */
	public function test_server_upload_is_exported_immediately(): void {
		$attachment_id = $this->upload( false );

		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( Client_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		$this->assertArrayHasKey( 'medium', Sizes::get_instance()->get_sizes( $attachment_id ) );
	}
}
