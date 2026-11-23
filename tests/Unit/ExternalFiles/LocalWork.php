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
use ExternalFilesInMediaLibrary\ExternalFiles\Restore;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes as Sizes_Object;
use WP_Error;
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
	 * The number of calls of the simulated route of "Regenerate Thumbnails".
	 *
	 * @var int
	 */
	private int $regenerate_thumbnails_calls = 0;

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

		// use the support for the plugins, which create the sizes again (they are not installed in the tests).
		\ExternalFilesInMediaLibrary\ThirdParty\PerfectImages::get_instance()->add_hooks();
		\ExternalFilesInMediaLibrary\ThirdParty\RegenerateThumbnails::get_instance()->add_hooks();
		\ExternalFilesInMediaLibrary\ThirdParty\ForceRegenerateThumbnails::get_instance()->add_hooks();
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
		remove_filter( 'big_image_size_threshold', '__return_false' );
		remove_filter( 'wp_generate_attachment_metadata', array( $this, 'simulate_perfect_images_files' ), 10 );
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
	 * Test that the local files are only used in the backend for users, who are allowed to edit the image, and
	 * not if WordPress requests the original image only for information (e.g. its name in the media library).
	 *
	 * @return void
	 */
	public function test_backend_requires_permission_and_work(): void {
		$attachment_id = $this->create_exported_image( 3000, 2000 );
		remove_filter( 'efml_local_work_allowed', '__return_true' );
		set_current_screen( 'upload' );

		// an author is not allowed to edit the image of the administrator.
		$admin_id = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		wp_get_original_image_path( $attachment_id );
		$this->assertFalse( Local_Work_Object::get_instance()->is_open( $attachment_id ) );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );

		// the media library requests the original image only for its name: nothing is downloaded.
		wp_set_current_user( $admin_id );
		wp_prepare_attachment_for_js( $attachment_id );
		$this->assertFalse( Local_Work_Object::get_instance()->is_open( $attachment_id ) );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );

		// to create the sizes again, the local files are used.
		add_image_size( 'efml-test', 500, 500, true );
		$this->assertIsArray( wp_update_image_subsizes( $attachment_id ) );
		Local_Work_Object::get_instance()->close_all();
		$this->assertArrayHasKey( 'efml-test', Sizes_Object::get_instance()->get_sizes( $attachment_id ) );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );

		set_current_screen( 'front' );
	}

	/**
	 * Test that visitors do not download the files via the REST API.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_not_for_visitors_via_rest_api(): void {
		$attachment_id = $this->create_exported_image( 3000, 2000 );
		remove_filter( 'efml_local_work_allowed', '__return_true' );
		wp_set_current_user( 0 );
		define( 'REST_REQUEST', true );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/media/' . $attachment_id ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( Local_Work_Object::get_instance()->is_open( $attachment_id ) );
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

	/**
	 * Create an external image without local files and return its ID: like an image imported via HTTP without a local
	 * copy, its attached file is its URL.
	 *
	 * Files from a local directory are always saved locally during their import, so the local file is removed after
	 * the import for this test.
	 *
	 * @return int
	 */
	private function create_image_without_local_files(): int {
		$directory = $this->export_directory . 'import/';
		wp_mkdir_p( $directory );
		$path  = $directory . 'efml-import-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $path );
		$url = 'file://' . $path;
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( $url ) );
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );

		// remove the local file and use the URL as attached file.
		$local_file = get_attached_file( $external_file_obj->get_id(), true );
		if ( is_string( $local_file ) && file_exists( $local_file ) ) {
			wp_delete_file( $local_file );
		}
		update_post_meta( $external_file_obj->get_id(), '_wp_attached_file', $url );
		$external_file_obj->set_is_local_saved( false );
		$this->assertTrue( Local_Work_Object::get_instance()->is_without_local_files( $external_file_obj->get_id() ) );
		return $external_file_obj->get_id();
	}

	/**
	 * Test that the original image of a scaled exported image is kept, if the sizes are created again without
	 * scaling (like "Regenerate Thumbnails Advanced" does it).
	 *
	 * @return void
	 */
	public function test_regenerate_without_scaling_keeps_original(): void {
		$attachment_id = $this->create_exported_image();
		$this->assertArrayHasKey( Sizes_Object::ORIGINAL, Sizes_Object::get_instance()->get_sizes( $attachment_id, true ) );

		// create the sizes again from the original image without scaling.
		add_filter( 'big_image_size_threshold', '__return_false' );
		$file = wp_get_original_image_path( $attachment_id );
		$this->assertIsString( $file );
		$this->assertFileExists( $file );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		remove_filter( 'big_image_size_threshold', '__return_false' );
		$this->assertIsArray( $metadata );
		$this->assertArrayHasKey( 'original_image', $metadata );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		Local_Work_Object::get_instance()->close_all();

		// the exported original image is still used.
		$this->assertArrayHasKey( Sizes_Object::ORIGINAL, Sizes_Object::get_instance()->get_sizes( $attachment_id, true ) );
		$this->assertNotEmpty( Restore::get_instance()->get_original_paths( $attachment_id )['original'] );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
	}

	/**
	 * Register a route of the REST API of the plugin "Perfect Images" for the test.
	 *
	 * @param string   $route    The route.
	 * @param callable $callback The callback.
	 *
	 * @return void
	 */
	private function register_perfect_images_route( string $route, callable $callback ): void {
		rest_get_server();
		register_rest_route(
			'wp-retina-2x/v1',
			'/' . $route,
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => $callback,
			)
		);
	}

	/**
	 * Send a request to the REST API of the plugin "Perfect Images" like its dashboard.
	 *
	 * @param string              $route         The route.
	 * @param int                 $attachment_id The attachment ID.
	 * @param array<string,mixed> $params        Further parameters.
	 *
	 * @return \WP_REST_Response
	 */
	private function request_perfect_images( string $route, int $attachment_id, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wp-retina-2x/v1/' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array_merge( array( 'mediaId' => $attachment_id ), $params ) ) );
		return rest_do_request( $request );
	}

	/**
	 * Create the sizes again like the plugin "Perfect Images": from the attached file.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array<string,mixed>
	 */
	public function simulate_perfect_images_regenerate( WP_REST_Request $request ): array {
		$attachment_id = absint( $request->get_param( 'mediaId' ) );
		do_action( 'wr2x_before_generate_thumbnails', $attachment_id );
		$file     = (string) get_attached_file( $attachment_id );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		return array( 'success' => true );
	}

	/**
	 * Replace the file like the plugin "Perfect Images": the attached file is replaced by the upload.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array<string,mixed>
	 */
	public function simulate_perfect_images_replace( WP_REST_Request $request ): array {
		$file = (string) get_attached_file( absint( $request->get_param( 'mediaId' ) ) );
		if ( file_exists( $file ) ) {
			wp_delete_file( $file );
		}
		file_put_contents( $file, 'replaced' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return array( 'success' => true );
	}

	/**
	 * Create retina and WebP files beside the medium size like the plugin "Perfect Images".
	 *
	 * @param mixed $metadata      The metadata.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function simulate_perfect_images_files( mixed $metadata, int $attachment_id ): mixed {
		if ( ! is_array( $metadata ) || empty( $metadata['sizes']['medium']['file'] ) || empty( $metadata['file'] ) ) {
			return $metadata;
		}
		$directory = trailingslashit( wp_get_upload_dir()['basedir'] ) . trailingslashit( dirname( $metadata['file'] ) );
		$medium    = $directory . $metadata['sizes']['medium']['file'];
		$info      = pathinfo( $medium );
		copy( $medium, $directory . $info['filename'] . '@2x.' . $info['extension'] );
		$image = imagecreatefromjpeg( $medium );
		imagewebp( $image, $medium . '.webp' );
		return $metadata;
	}

	/**
	 * Test that "Perfect Images" creates the sizes of an exported image again with its local files.
	 *
	 * @return void
	 */
	public function test_perfect_images_regenerate(): void {
		$attachment_id = $this->create_exported_image();
		$url           = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$this->register_perfect_images_route( 'regenerate', array( $this, 'simulate_perfect_images_regenerate' ) );

		// add a new size and create the sizes again.
		add_image_size( 'efml-test', 500, 500, true );
		$response = $this->request_perfect_images( 'regenerate', $attachment_id );
		Local_Work_Object::get_instance()->close_all();
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( $data['success'] );

		// the new size is exported, the original image is kept, the image is still the exported file.
		$sizes = Sizes_Object::get_instance()->get_sizes( $attachment_id, true );
		$this->assertArrayHasKey( 'efml-test', $sizes );
		$this->assertFileExists( $this->export_directory . $sizes['efml-test']['file'] );
		$this->assertArrayHasKey( Sizes_Object::ORIGINAL, $sizes );
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$this->assertIsArray( $metadata );
		$this->assertNotEmpty( $metadata['original_image'] );
		$this->assertEquals( $url, get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
	}

	/**
	 * Test that the functions of "Perfect Images", which would change the external source directly, are prevented.
	 *
	 * @return void
	 */
	public function test_perfect_images_prevented_for_external_files(): void {
		$this->register_perfect_images_route( 'replace', array( $this, 'simulate_perfect_images_replace' ) );
		$this->register_perfect_images_route( 'regenerate', array( $this, 'simulate_perfect_images_regenerate' ) );

		// the exported file in the local directory is not replaced.
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		$path          = $this->export_directory . wp_basename( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertFileExists( $path );
		$hash = md5_file( $path );
		$data = $this->request_perfect_images( 'replace', $attachment_id )->get_data();
		$this->assertIsArray( $data );
		$this->assertFalse( $data['success'] );
		$this->assertNotEmpty( $data['message'] );
		$this->assertEquals( $hash, md5_file( $path ) );

		// the optimized regeneration of exported images is prevented.
		$files = (array) glob( $this->export_directory . '*' );
		$data  = $this->request_perfect_images( 'regenerate', $attachment_id, array( 'optimized' => true ) )->get_data();
		$this->assertIsArray( $data );
		$this->assertFalse( $data['success'] );
		$this->assertEquals( $files, (array) glob( $this->export_directory . '*' ) );

		// the regeneration of external files without local files is prevented.
		$data = $this->request_perfect_images( 'regenerate', $this->create_image_without_local_files() )->get_data();
		$this->assertIsArray( $data );
		$this->assertFalse( $data['success'] );

		// local files are not affected.
		$file  = trailingslashit( get_temp_dir() ) . 'efml-local-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( 400, 300 );
		imagejpeg( $image, $file );
		$local_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $local_id );
		$data = $this->request_perfect_images( 'regenerate', $local_id )->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test that the retina and WebP files of "Perfect Images" are exported and deleted together with the image.
	 *
	 * @return void
	 */
	public function test_perfect_images_files_are_exported_and_deleted(): void {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'simulate_perfect_images_files' ), 10, 2 );
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		remove_filter( 'wp_generate_attachment_metadata', array( $this, 'simulate_perfect_images_files' ), 10 );

		// the files are exported beside the sizes and removed locally.
		$medium = Sizes_Object::get_instance()->get_sizes( $attachment_id )['medium']['file'];
		$retina = pathinfo( $medium, PATHINFO_FILENAME ) . '@2x.jpg';
		$webp   = $medium . '.webp';
		$this->assertFileExists( $this->export_directory . $retina );
		$this->assertFileExists( $this->export_directory . $webp );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );

		// they are deleted together with the image.
		wp_delete_attachment( $attachment_id, true );
		$this->assertFileDoesNotExist( $this->export_directory . $retina );
		$this->assertFileDoesNotExist( $this->export_directory . $webp );
	}

	/**
	 * Test that the retina and WebP files of "Perfect Images" are restored together with the image.
	 *
	 * @return void
	 */
	public function test_perfect_images_files_are_restored(): void {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'simulate_perfect_images_files' ), 10, 2 );
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		remove_filter( 'wp_generate_attachment_metadata', array( $this, 'simulate_perfect_images_files' ), 10 );
		$medium = Sizes_Object::get_instance()->get_sizes( $attachment_id )['medium']['file'];

		// restore the image as local file.
		$this->assertTrue( Restore::get_instance()->restore_file( $attachment_id ) );
		$file = get_attached_file( $attachment_id );
		$this->assertIsString( $file );
		$this->assertFileExists( $file );

		// the files of the plugin are local files beside the medium size again.
		$directory = trailingslashit( dirname( $file ) );
		$this->assertFileExists( $directory . pathinfo( $medium, PATHINFO_FILENAME ) . '@2x.jpg' );
		$this->assertFileExists( $directory . $medium . '.webp' );
	}

	/**
	 * Create the sizes again like the plugin "Regenerate Thumbnails": from the original image, if it exists.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return mixed
	 */
	public function simulate_regenerate_thumbnails( WP_REST_Request $request ): mixed {
		++$this->regenerate_thumbnails_calls;
		$attachment_id = absint( $request->get_param( 'id' ) );
		$file          = wp_get_original_image_path( $attachment_id );
		if ( false === $file || ! file_exists( $file ) ) {
			return new WP_Error( 'regenerate_thumbnails_regenerator_file_not_found', 'The fullsize image file cannot be found.', array( 'status' => 404 ) );
		}
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		return $metadata;
	}

	/**
	 * Test that "Regenerate Thumbnails" does not create sizes of imported files on their external source, but of
	 * exported images with their local files.
	 *
	 * @return void
	 */
	public function test_regenerate_thumbnails(): void {
		rest_get_server();
		register_rest_route(
			'regenerate-thumbnails/v1',
			'/regenerate/(?P<id>[\d]+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'simulate_regenerate_thumbnails' ),
			)
		);

		// an external file without local files gets the hint, the plugin does not work with its URL.
		$this->regenerate_thumbnails_calls = 0;
		$response                          = rest_do_request( new WP_REST_Request( 'POST', '/regenerate-thumbnails/v1/regenerate/' . $this->create_image_without_local_files() ) );
		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertSame( 'efml_external_file_without_local_files', $response->as_error()->get_error_code() );
		$this->assertSame( 0, $this->regenerate_thumbnails_calls );

		// the sizes of an exported image are created again and exported.
		$exported_id = $this->create_exported_image( 1600, 1200 );
		add_image_size( 'efml-test', 500, 500, true );
		$response = rest_do_request( new WP_REST_Request( 'POST', '/regenerate-thumbnails/v1/regenerate/' . $exported_id ) );
		Local_Work_Object::get_instance()->close_all();
		$this->assertFalse( $response->is_error() );
		$this->assertArrayHasKey( 'efml-test', Sizes_Object::get_instance()->get_sizes( $exported_id ) );
		$this->assertEmpty( $this->get_local_files_of( $exported_id ) );
	}

	/**
	 * Test that "Force Regenerate Thumbnails" skips imported files and finds the local files of exported images.
	 *
	 * @return void
	 */
	public function test_force_regenerate_thumbnails(): void {
		// external files without local files are skipped.
		$this->assertTrue( apply_filters( 'regenerate_thumbs_skip_image', false, $this->create_image_without_local_files() ) );

		// the plugin finds the local file of an exported image.
		$attachment_id = $this->create_exported_image( 1600, 1200 );
		$this->assertFalse( apply_filters( 'regenerate_thumbs_skip_image', false, $attachment_id ) );
		$file = get_attached_file( $attachment_id );
		$this->assertIsString( $file );
		$this->assertFileExists( $file );
		$this->assertNotFalse( realpath( $file ) );

		// create the sizes again like the plugin and export them.
		add_image_size( 'efml-test', 500, 500, true );
		$original = wp_get_original_image_path( $attachment_id, true );
		$this->assertIsString( $original );
		$this->assertFileExists( $original );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $original );
		wp_update_attachment_metadata( $attachment_id, $metadata );
		Local_Work_Object::get_instance()->close_all();
		$this->assertArrayHasKey( 'efml-test', Sizes_Object::get_instance()->get_sizes( $attachment_id ) );
		$this->assertEmpty( $this->get_local_files_of( $attachment_id ) );
	}
}
