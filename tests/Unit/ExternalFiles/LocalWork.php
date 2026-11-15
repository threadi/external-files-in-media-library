<?php
/**
 * Tests for the work with the local files of exported images (e.g. to create their sizes again).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Work as Local_Work_Object;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes as Sizes_Object;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;

/**
 * Object to test the work with the local files of exported images.
 */
class LocalWork extends externalFilesTests {

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
		$property->setAccessible( true );
		$property->setValue( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance(), array() );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-local-work-' . uniqid() . '/';
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

		// make sure the hooks of the export are active (they are only added if the export was enabled during the start).
		if ( false === has_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ) ) ) {
			add_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ), 10, 2 );
		}
		if ( false === has_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) ) ) {
			add_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) );
		}

		// allow the work with local files in the tests (they run outside the backend).
		add_filter( 'efml_local_work_allowed', '__return_true' );

		// use the support for the plugin "Media File Renamer" (it is not installed in the tests).
		\ExternalFilesInMediaLibrary\ThirdParty\MediaFileRenamer::get_instance()->add_hooks();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		remove_all_filters( 'efml_file_prevent_proxied_url' );
		remove_filter( 'efml_local_work_allowed', '__return_true' );
		remove_image_size( 'efml-test' );
		\ExternalFilesInMediaLibrary\ExternalFiles\Local_Work::get_instance()->close_all();
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
	 * Create the sizes of the given image again, like "wp media regenerate" and "Regenerate Thumbnails" do it.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	private function regenerate( int $attachment_id ): void {
		$file = wp_get_original_image_path( $attachment_id );
		$this->assertIsString( $file );
		$this->assertFileExists( $file );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Return the local files of the given image in the uploads directory.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,string>
	 */
	private function get_local_files_of( int $attachment_id ): array {
		$path = \ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls::get_instance()->get_local_paths( $attachment_id )[0];
		$name = pathinfo( $path, PATHINFO_FILENAME );
		$name = (string) preg_replace( '/-scaled$/', '', $name );
		return (array) glob( trailingslashit( wp_get_upload_dir()['basedir'] ) . dirname( $path ) . '/' . $name . '*' );
	}

	/**
	 * Test that the sizes of an exported image are created again from its original image and exported.
	 *
	 * @return void
	 */
	public function test_regenerate_exports_new_sizes(): void {
		$attachment_id = $this->create_exported_image();
		$url           = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );

		// add a new size and create the sizes again.
		add_image_size( 'efml-test', 500, 500, true );
		$this->regenerate( $attachment_id );
		Local_Work_Object::get_instance()->close_all();

		// the new size is exported.
		$sizes = Sizes_Object::get_instance()->get_sizes( $attachment_id, true );
		$this->assertArrayHasKey( 'efml-test', $sizes );
		$this->assertFileExists( $this->export_directory . $sizes['efml-test']['file'] );
		$this->assertArrayHasKey( Sizes_Object::ORIGINAL, $sizes );

		// the image is still the exported file, without local files and sizes in its metadata.
		$this->assertEquals( $url, get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$this->assertIsArray( $metadata );
		$this->assertArrayNotHasKey( 'sizes', $metadata );
		$this->assertFalse( Local_Work_Object::get_instance()->is_open( $attachment_id ) );
	}

	/**
	 * Test that replaced sizes are kept on the external source and deleted together with the image.
	 *
	 * @return void
	 */
	public function test_replaced_sizes_are_kept_and_deleted(): void {
		$attachment_id = $this->create_exported_image();
		$medium_size   = Sizes_Object::get_instance()->get_sizes( $attachment_id )['medium'];
		$medium        = $medium_size['file'];

		// change the dimensions of the medium size and create the sizes again.
		add_filter( 'pre_option_medium_size_w', array( $this, 'get_medium_width' ) );
		$this->regenerate( $attachment_id );
		remove_filter( 'pre_option_medium_size_w', array( $this, 'get_medium_width' ) );

		// the new medium size is used, the former one is kept.
		$sizes = Sizes_Object::get_instance()->get_sizes( $attachment_id );
		$this->assertNotEquals( $medium, $sizes['medium']['file'] );
		$this->assertFileExists( $this->export_directory . $sizes['medium']['file'] );
		$this->assertFileExists( $this->export_directory . $medium );
		$this->assertContains( $medium, wp_list_pluck( Sizes_Object::get_instance()->get_data( $attachment_id )['former'][ Sizes_Object::get_instance()->get_data( $attachment_id )['main'] ] ?? array(), 'file' ) );

		// the proxy still delivers the former size, which contents could use.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$dimensions        = array( absint( $medium_size['width'] ), absint( $medium_size['height'] ) );
		$external_file_obj->delete_thumbs();
		$this->assertTrue( Sizes_Object::get_instance()->cache_size( $external_file_obj, $dimensions ) );
		$this->assertFileExists( $external_file_obj->get_cache_file( $dimensions ) );

		// all files are deleted together with the image.
		wp_delete_attachment( $attachment_id, true );
		$this->assertFileDoesNotExist( $this->export_directory . $medium );
		$this->assertFileDoesNotExist( $this->export_directory . $sizes['medium']['file'] );
	}

	/**
	 * Return the width of the medium size for the test.
	 *
	 * @return int
	 */
	public function get_medium_width(): int {
		return 400;
	}

	/**
	 * Test that only the missing sizes are created via wp_update_image_subsizes().
	 *
	 * @return void
	 */
	public function test_update_image_subsizes(): void {
		$attachment_id = $this->create_exported_image( 1600, 1200 );

		// add a new size and create the missing sizes.
		add_image_size( 'efml-test', 500, 500, true );
		$this->assertIsArray( wp_update_image_subsizes( $attachment_id ) );
		Local_Work_Object::get_instance()->close_all();

		// the new size is exported, the local files are removed.
		$this->assertArrayHasKey( 'efml-test', Sizes_Object::get_instance()->get_sizes( $attachment_id ) );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$this->assertIsArray( $metadata );
		$this->assertArrayNotHasKey( 'sizes', $metadata );
	}

	/**
	 * Test that the local files are not used for visitors of the website.
	 *
	 * @return void
	 */
	public function test_not_for_visitors(): void {
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		remove_filter( 'efml_local_work_allowed', '__return_true' );

		// the path is not changed and nothing is downloaded.
		$path = wp_get_original_image_path( $attachment_id );
		$this->assertFalse( Local_Work_Object::get_instance()->is_open( $attachment_id ) );
		$this->assertFalse( is_string( $path ) && str_starts_with( $path, wp_get_upload_dir()['basedir'] ) );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
	}

	/**
	 * Save the given exported image in the image editor of WordPress.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $action        The action: "save" or "restore".
	 *
	 * @return object
	 */
	private function use_image_editor( int $attachment_id, string $action ): object {
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
		$_POST    = array(
			'postid'      => $attachment_id,
			'do'          => $action,
			'_ajax_nonce' => wp_create_nonce( 'image_editor-' . $attachment_id ),
			'history'     => wp_slash( '[{"r":90}]' ),
			'target'      => 'all',
			'context'     => 'edit-attachment',
		);
		$_REQUEST = $_POST;
		do_action( 'wp_ajax_image-editor' );
		$result = 'restore' === $action ? wp_restore_image( $attachment_id ) : wp_save_image( $attachment_id );
		Local_Work_Object::get_instance()->close_all();
		$_POST    = array();
		$_REQUEST = array();
		return $result;
	}

	/**
	 * Test that an image, which has been edited in the image editor, is exported under its new name.
	 *
	 * @return void
	 */
	public function test_image_editor_exports_edited_image(): void {
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		$old_name      = wp_basename( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		// rotate the image.
		$result = $this->use_image_editor( $attachment_id, 'save' );
		$this->assertObjectNotHasProperty( 'error', $result );

		// the edited image is exported under its new name and used.
		$url = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$this->assertMatchesRegularExpression( '#-e\d+\.jpg$#', $url );
		$this->assertStringStartsWith( 'file://', $url );
		$this->assertFileExists( $this->export_directory . wp_basename( $url ) );
		$this->assertEquals( wp_basename( $url ), get_the_title( $attachment_id ) );

		// its sizes are exported with the new dimensions.
		$medium = Sizes_Object::get_instance()->get_sizes( $attachment_id )['medium'];
		$this->assertEquals( 225, $medium['width'] );
		$this->assertFileExists( $this->export_directory . $medium['file'] );

		// the former image is kept as backup on the external source and for "Restore image".
		$this->assertFileExists( $this->export_directory . $old_name );
		$backup = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		$this->assertIsArray( $backup );
		$this->assertEquals( $old_name, $backup['full-orig']['file'] );
		$this->assertArrayHasKey( 'medium-orig', $backup );

		// nothing stays local and the proxy finds the image also via its former name.
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, Files::get_instance()->get_file_by_former_title( $old_name ) );

		// all files are deleted together with the image.
		$files = (array) glob( $this->export_directory . '*' );
		$this->assertNotEmpty( $files );
		wp_delete_attachment( $attachment_id, true );
		$this->assertEmpty( (array) glob( $this->export_directory . '*' ) );
	}

	/**
	 * Test that "Restore image" in the image editor uses the former files on the external source again.
	 *
	 * @return void
	 */
	public function test_image_editor_restores_original(): void {
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		$old_url       = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$old_medium    = Sizes_Object::get_instance()->get_sizes( $attachment_id )['medium']['file'];

		// rotate and restore the image.
		$this->use_image_editor( $attachment_id, 'save' );
		$result = $this->use_image_editor( $attachment_id, 'restore' );
		$this->assertObjectNotHasProperty( 'error', $result );

		// the former image and sizes are used again.
		$this->assertEquals( $old_url, get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertEquals( $old_medium, Sizes_Object::get_instance()->get_sizes( $attachment_id )['medium']['file'] );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
	}

	/**
	 * Test that an image renamed by the plugin "Media File Renamer" is exported under its new name.
	 *
	 * @return void
	 */
	public function test_media_file_renamer(): void {
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		$old_name      = wp_basename( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$old_base      = pathinfo( $old_name, PATHINFO_FILENAME );
		$new_base      = $old_base . '-renamed';

		// the plugin asks whether it may rename the file: the local files are loaded.
		$this->assertTrue( apply_filters( 'mfrh_allow_rename', true, array( 'ID' => $attachment_id ), '' ) );
		$file = get_attached_file( $attachment_id );
		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		// rename the files like the plugin.
		$metadata  = wp_get_attachment_metadata( $attachment_id );
		$directory = dirname( $file );
		$new_file  = $directory . '/' . $new_base . '.jpg';
		rename( $file, $new_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		foreach ( $metadata['sizes'] as $size_name => $size ) {
			$new_size = str_replace( $old_base, $new_base, $size['file'] );
			rename( $directory . '/' . $size['file'], $directory . '/' . $new_size ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			$metadata['sizes'][ $size_name ]['file'] = $new_size;
		}
		$metadata['file'] = _wp_relative_upload_path( $new_file );
		update_attached_file( $attachment_id, $new_file );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		Local_Work_Object::get_instance()->close_all();

		// the renamed files are exported and used, the former files are deleted on the external source.
		$this->assertStringEndsWith( '/' . $new_base . '.jpg', (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertFileExists( $this->export_directory . $new_base . '.jpg' );
		$this->assertFileDoesNotExist( $this->export_directory . $old_name );
		$medium = Sizes_Object::get_instance()->get_sizes( $attachment_id )['medium']['file'];
		$this->assertStringStartsWith( $new_base, $medium );
		$this->assertFileExists( $this->export_directory . $medium );
		$this->assertEmpty( Sizes_Object::get_instance()->get_former_sizes( $attachment_id ) );
		$this->assertEmpty( (array) glob( $directory . '/' . $old_base . '*' ) );
	}

	/**
	 * Test that the block editor could edit an exported image (it creates a new image from it).
	 *
	 * @return void
	 */
	public function test_block_editor_edit(): void {
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		$image         = wp_get_attachment_image_src( $attachment_id, 'large' );
		$this->assertIsArray( $image );

		// rotate the image via the REST API like the block editor.
		$request = new WP_REST_Request( 'POST', '/wp/v2/media/' . $attachment_id . '/edit' );
		$request->set_param( 'src', $image[0] );
		$request->set_param(
			'modifiers',
			array(
				array(
					'type' => 'rotate',
					'args' => array( 'angle' => 90 ),
				),
			)
		);
		$response = rest_do_request( $request );
		Local_Work_Object::get_instance()->close_all();

		// a new image has been created and the exported image has no local files.
		$this->assertEquals( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertGreaterThan( 0, $data['id'] );
		$this->assertEmpty( array_filter( $this->get_local_files_of( $attachment_id ), fn( $file ) => ! str_contains( (string) $file, '-edited' ) ) );
	}

	/**
	 * Test that an image renamed via the REST API of the plugin "Media File Renamer" is exported under its new name.
	 *
	 * The route is simulated like the plugin works: it checks the local file and renames it.
	 *
	 * @return void
	 */
	public function test_media_file_renamer_via_rest(): void {
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		$old_name      = wp_basename( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		// register a route like the plugin.
		register_rest_route(
			'media-file-renamer/v1',
			'/rename',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'simulate_media_file_renamer' ),
			)
		);

		// rename the image.
		$request = new WP_REST_Request( 'POST', '/media-file-renamer/v1/rename' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'mediaId' => $attachment_id ) ) );
		$response = rest_do_request( $request );
		Local_Work_Object::get_instance()->close_all();

		// the plugin has found the file and the renamed image is exported.
		$this->assertTrue( $response->get_data() );
		$new_name = pathinfo( $old_name, PATHINFO_FILENAME ) . '-renamed.jpg';
		$this->assertStringEndsWith( '/' . $new_name, (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertFileExists( $this->export_directory . $new_name );
		$this->assertFileDoesNotExist( $this->export_directory . $old_name );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
	}

	/**
	 * Rename an image like the plugin "Media File Renamer": only if its local file exists.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function simulate_media_file_renamer( WP_REST_Request $request ): bool {
		$attachment_id = absint( $request->get_json_params()['mediaId'] ?? 0 );
		$file          = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || ! file_exists( $file ) ) {
			return false;
		}
		$metadata  = wp_get_attachment_metadata( $attachment_id );
		$directory = dirname( $file );
		$old_base  = pathinfo( $file, PATHINFO_FILENAME );
		$new_file  = $directory . '/' . $old_base . '-renamed.jpg';
		rename( $file, $new_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		foreach ( $metadata['sizes'] as $size_name => $size ) {
			$new_size = str_replace( $old_base, $old_base . '-renamed', $size['file'] );
			if ( file_exists( $directory . '/' . $size['file'] ) ) {
				rename( $directory . '/' . $size['file'], $directory . '/' . $new_size ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			}
			$metadata['sizes'][ $size_name ]['file'] = $new_size;
		}
		$metadata['file'] = _wp_relative_upload_path( $new_file );
		update_attached_file( $attachment_id, $new_file );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		return true;
	}
}
