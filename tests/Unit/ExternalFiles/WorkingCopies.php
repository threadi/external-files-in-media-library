<?php
/**
 * Tests for the local working copies of exported images, e.g. for image optimizers.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Copies;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes;
use ExternalFilesInMediaLibrary\ExternalFiles\Variants;
use ExternalFilesInMediaLibrary\ExternalFiles\Working_Copies as Working_Copies_Object;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the local working copies of exported images.
 */
class WorkingCopies extends externalFilesTests {

	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * The backups, which a simulated image optimizer saves (path => name on the external source).
	 *
	 * @var array<string,string>
	 */
	private array $backups = array();

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-working-copies-' . uniqid() . '/';
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
		if ( false === has_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ) ) ) {
			add_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ), 10, 2 );
		}

		// let a simulated image optimizer report its WebP files and backups.
		$this->backups = array();
		add_filter( 'efml_image_variants', array( $this, 'add_variants' ), 10, 3 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		remove_filter( 'efml_image_variants', array( $this, 'add_variants' ) );
		Import_Runs::get_instance()->cancel_all();
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( WP_CONTENT_DIR . '/efml-test-backups/' );
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
	 * Add the variants of the simulated image optimizer: "image.jpg.webp" beside the image and its backups.
	 *
	 * @param array<int,array<string,string>> $variants      The variants.
	 * @param int                             $attachment_id The attachment ID.
	 * @param array<string,string>            $paths         The paths of the files.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function add_variants( array $variants, int $attachment_id, array $paths ): array {
		foreach ( $paths as $size_name => $path ) {
			if ( file_exists( $path . '.webp' ) ) {
				$variants[] = array(
					'size'      => (string) $size_name,
					'path'      => $path . '.webp',
					'mime-type' => 'image/webp',
				);
			}
			$backup = $this->get_backup_path( $path );
			if ( file_exists( $backup ) ) {
				$info       = pathinfo( $path );
				$variants[] = array(
					'size'      => (string) $size_name,
					'path'      => $backup,
					'mime-type' => 'image/jpeg',
					'kind'      => 'backup',
					'name'      => $info['filename'] . '.test-backup.' . $info['extension'],
				);
			}
		}
		return $variants;
	}

	/**
	 * Return the path of the backup of the simulated image optimizer: same name in another directory.
	 *
	 * @param string $path The path of the file.
	 *
	 * @return string
	 */
	private function get_backup_path( string $path ): string {
		return WP_CONTENT_DIR . '/efml-test-backups/' . wp_basename( $path );
	}

	/**
	 * Create an exported image and return its ID.
	 *
	 * @param int $width  The width.
	 * @param int $height The height.
	 *
	 * @return int
	 */
	private function create_exported_image( int $width = 800, int $height = 600 ): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-wc-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( $width, $height );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 200, 100, 50 ) );
		imagejpeg( $image, $file, 100 );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$this->assertStringStartsWith( 'file://', (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		return $attachment_id;
	}

	/**
	 * "Optimize" the given local file: save it again with lower quality.
	 *
	 * @param string $path The path.
	 *
	 * @return void
	 */
	private function optimize( string $path ): void {
		$image = imagecreatefromjpeg( $path );
		$this->assertNotFalse( $image );
		imagejpeg( $image, $path, 20 );
	}

	/**
	 * Return the path of a file on the external source.
	 *
	 * @param string $name The file name.
	 *
	 * @return string
	 */
	private function target( string $name ): string {
		return $this->export_directory . $name;
	}

	/**
	 * Test the creation of a working copy.
	 *
	 * @return void
	 */
	public function test_create(): void {
		$attachment_id = $this->create_exported_image( 3000, 2000 );
		$url           = Files::get_instance()->get_file( $attachment_id )->get_url( true );

		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		$this->assertTrue( Working_Copies_Object::get_instance()->is_active( $attachment_id ) );

		// the image looks like a local image: local file, sizes in the metadata, original image.
		$file = (string) get_attached_file( $attachment_id );
		$this->assertFileExists( $file );
		$this->assertStringContainsString( '-scaled.jpg', $file );
		$this->assertFileExists( (string) wp_get_original_image_path( $attachment_id ) );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertNotEmpty( $metadata['sizes'] );
		foreach ( $metadata['sizes'] as $size ) {
			$this->assertFileExists( trailingslashit( dirname( $file ) ) . $size['file'] );
		}

		// it is still an exported file with its URL.
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertEquals( $url, Files::get_instance()->get_file( $attachment_id )->get_url( true ) );

		// saving its metadata (e.g. by an image optimizer) keeps the local files.
		wp_update_attachment_metadata( $attachment_id, $metadata );
		$this->assertFileExists( $file );

		// it is no local copy for the deletion of local copies.
		$this->assertFalse( Local_Copies::get_instance()->has_local_copy( $attachment_id ) );
	}

	/**
	 * Test to finish a working copy without changes: nothing is uploaded, the local files are removed.
	 *
	 * @return void
	 */
	public function test_finish_without_changes(): void {
		$attachment_id = $this->create_exported_image();
		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		$file  = (string) get_attached_file( $attachment_id );
		$sizes = wp_get_attachment_metadata( $attachment_id )['sizes'];

		// remember the times of the files on the external source.
		$times = array();
		foreach ( glob( $this->export_directory . '*' ) as $target ) {
			$times[ $target ] = filemtime( $target );
		}
		sleep( 1 );

		$this->assertTrue( Working_Copies_Object::get_instance()->finish( $attachment_id ) );

		// the files on the external source are unchanged.
		clearstatcache();
		foreach ( $times as $target => $time ) {
			$this->assertEquals( $time, filemtime( $target ) );
		}

		// the local files are removed, the metadata has no sizes, the working copy is finished.
		$this->assertFileDoesNotExist( $file );
		foreach ( $sizes as $size ) {
			$this->assertFileDoesNotExist( trailingslashit( dirname( $file ) ) . $size['file'] );
		}
		$this->assertArrayNotHasKey( 'sizes', (array) get_post_meta( $attachment_id, '_wp_attachment_metadata', true ) );
		$this->assertFalse( Working_Copies_Object::get_instance()->is_active( $attachment_id ) );
		$this->assertEquals( Files::get_instance()->get_file( $attachment_id )->get_url( true ), get_post_meta( $attachment_id, '_wp_attached_file', true ) );
	}

	/**
	 * Test to finish a working copy after an optimization: changed files, a new WebP file and a backup are exported.
	 *
	 * @return void
	 */
	public function test_finish_after_optimization(): void {
		$attachment_id = $this->create_exported_image();
		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		$file     = (string) get_attached_file( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$size     = trailingslashit( dirname( $file ) ) . $metadata['sizes']['medium']['file'];
		$thumb    = trailingslashit( dirname( $file ) ) . $metadata['sizes']['thumbnail']['file'];
		$name     = wp_basename( Files::get_instance()->get_file( $attachment_id )->get_url( true ) );
		$thumb_on_target = $this->target( wp_basename( $thumb ) );
		$thumb_time      = filemtime( $thumb_on_target );
		sleep( 1 );

		// simulate an image optimizer: backup, optimize the image and a size, create a WebP file.
		wp_mkdir_p( dirname( $this->get_backup_path( $file ) ) );
		copy( $file, $this->get_backup_path( $file ) );
		$this->optimize( $file );
		$this->optimize( $size );
		copy( $file, $file . '.webp' );
		$optimized      = md5_file( $file );
		$optimized_size = md5_file( $size );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		$this->assertTrue( Working_Copies_Object::get_instance()->finish( $attachment_id ) );

		// the optimized files are on the external source with their names, the unchanged size has not been uploaded.
		$this->assertEquals( $optimized, md5_file( $this->target( $name ) ) );
		$this->assertEquals( $optimized_size, md5_file( $this->target( wp_basename( $size ) ) ) );
		clearstatcache();
		$this->assertEquals( $thumb_time, filemtime( $thumb_on_target ) );

		// the WebP file and the backup (with another name) are on the external source.
		$this->assertFileExists( $this->target( $name . '.webp' ) );
		$info = pathinfo( $file );
		$this->assertFileExists( $this->target( $info['filename'] . '.test-backup.jpg' ) );
		$variants = Variants::get_instance()->get_variants( $attachment_id );
		$this->assertArrayHasKey( $info['filename'] . '.test-backup.jpg', $variants );
		$this->assertEquals( 'backup', $variants[ $info['filename'] . '.test-backup.jpg' ]['kind'] );

		// the local files are removed.
		$this->assertFileDoesNotExist( $file );
		$this->assertFileDoesNotExist( $size );
		$this->assertFileDoesNotExist( $file . '.webp' );
		$this->assertFileDoesNotExist( $this->get_backup_path( $file ) );

		// a second working copy saves the backup locally again, a removed backup is deleted on the external source.
		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		$this->assertFileExists( $this->get_backup_path( $file ) );
		wp_delete_file( $this->get_backup_path( $file ) );
		$this->assertTrue( Working_Copies_Object::get_instance()->finish( $attachment_id ) );
		$this->assertFileDoesNotExist( $this->target( $info['filename'] . '.test-backup.jpg' ) );
		$this->assertArrayNotHasKey( $info['filename'] . '.test-backup.jpg', Variants::get_instance()->get_variants( $attachment_id ) );
	}

	/**
	 * Test to finish a working copy after a conversion to another format (another name of the file).
	 *
	 * @return void
	 */
	public function test_finish_after_conversion(): void {
		$attachment_id = $this->create_exported_image();
		$old_url       = Files::get_instance()->get_file( $attachment_id )->get_url( true );
		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		$file = (string) get_attached_file( $attachment_id );

		// simulate a conversion: save the image under a new name and remove the former file.
		$new_file = trailingslashit( dirname( $file ) ) . pathinfo( $file, PATHINFO_FILENAME ) . '-converted.jpg';
		copy( $file, $new_file );
		wp_delete_file( $file );
		update_attached_file( $attachment_id, $new_file );
		$this->assertEquals( $new_file, get_attached_file( $attachment_id ) );

		$this->assertTrue( Working_Copies_Object::get_instance()->finish( $attachment_id ) );

		// the new file is used from the external source, the former file is deleted there.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertEquals( wp_basename( $new_file ), wp_basename( $external_file_obj->get_url( true ) ) );
		$this->assertFileExists( $this->target( wp_basename( $new_file ) ) );
		$this->assertFileDoesNotExist( $this->target( wp_basename( $old_url ) ) );
		$this->assertEquals( $external_file_obj->get_url( true ), get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertFileDoesNotExist( $new_file );
	}

	/**
	 * Test that the local files stay, if local files are kept after the export.
	 *
	 * @return void
	 */
	public function test_finish_keeps_local_files(): void {
		$attachment_id = $this->create_exported_image();
		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		$file = (string) get_attached_file( $attachment_id );
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		$this->optimize( $file );
		$optimized = md5_file( $file );

		$this->assertTrue( Working_Copies_Object::get_instance()->finish( $attachment_id ) );
		$this->assertFileExists( $file );
		$this->assertEquals( $optimized, md5_file( $this->target( wp_basename( Files::get_instance()->get_file( $attachment_id )->get_url( true ) ) ) ) );
	}

	/**
	 * Test the runs to create and to finish working copies and the end of the work reported by an image optimizer.
	 *
	 * @return void
	 */
	public function test_runs_and_reported_end(): void {
		$first  = $this->create_exported_image();
		$second = $this->create_exported_image();
		$this->assertEquals( 2, Working_Copies_Object::get_instance()->count_candidates() );

		// create the working copies via the run.
		$this->assertTrue( Import_Runs::get_instance()->start_working_copies()['success'] );
		$this->assertTrue( Working_Copies_Object::get_instance()->is_active( $first ) );
		$this->assertTrue( Working_Copies_Object::get_instance()->is_active( $second ) );
		$this->assertEquals( 0, Working_Copies_Object::get_instance()->count_candidates() );
		$this->assertEquals( 2, Working_Copies_Object::get_instance()->count_active() );

		// an image optimizer reports the end of its work for the first image: it is finished at the end of the request.
		Working_Copies_Object::get_instance()->finish_later( $first );
		$this->assertTrue( Working_Copies_Object::get_instance()->is_active( $first ) );
		Working_Copies_Object::get_instance()->finish_pending();
		$this->assertFalse( Working_Copies_Object::get_instance()->is_active( $first ) );

		// the second one is finished via the run.
		$this->assertTrue( Import_Runs::get_instance()->start_finish_working_copies()['success'] );
		$this->assertFalse( Working_Copies_Object::get_instance()->is_active( $second ) );
		$this->assertEquals( 0, Working_Copies_Object::get_instance()->count_active() );
		$this->assertFalse( Import_Runs::get_instance()->start_finish_working_copies()['success'] );
	}

	/**
	 * Test that no working copy is created on a copy of the website and for local files.
	 *
	 * @return void
	 */
	public function test_not_possible(): void {
		$attachment_id = $this->create_exported_image();
		add_filter( 'efml_is_website_copy', '__return_true' );
		$this->assertFalse( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		remove_filter( 'efml_is_website_copy', '__return_true' );

		$file  = trailingslashit( get_temp_dir() ) . 'efml-wc-local.jpg';
		$image = imagecreatetruecolor( 100, 100 );
		imagejpeg( $image, $file );
		$local = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertFalse( Working_Copies_Object::get_instance()->create( (int) $local ) );
		$this->assertFalse( Working_Copies_Object::get_instance()->is_active( (int) $local ) );
	}

	/**
	 * Test that the sizes of the image exported before stay on the external source, if their names differ.
	 *
	 * @return void
	 */
	public function test_exported_sizes_stay(): void {
		$attachment_id = $this->create_exported_image();
		$sizes         = Sizes::get_instance()->get_sizes( $attachment_id );
		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		$this->assertTrue( Working_Copies_Object::get_instance()->finish( $attachment_id ) );
		$this->assertEquals( array_keys( $sizes ), array_keys( Sizes::get_instance()->get_sizes( $attachment_id ) ) );
		foreach ( Sizes::get_instance()->get_sizes( $attachment_id ) as $size ) {
			$this->assertFileExists( $this->target( $size['file'] ) );
		}
	}

	/**
	 * Test that EWWW Image Optimizer sees exported images as offloaded files only in its column of the media library.
	 *
	 * @return void
	 */
	public function test_ewww_column(): void {
		$attachment_id = $this->create_exported_image();
		$ewww          = \ExternalFilesInMediaLibrary\ThirdParty\EWWW::get_instance();

		// outside the column (e.g. during its bulk scan) nothing is changed.
		$this->assertFalse( $ewww->is_remote_file( false, '', $attachment_id ) );

		// in the column it is an offloaded file, so EWWW shows its actions.
		$result = null;
		$check  = function ( $column_name, $id ) use ( $ewww, &$result ) {
			$result = $ewww->is_remote_file( false, '', $id );
		};
		add_action( 'manage_media_custom_column', $check, 10, 2 );
		do_action( 'manage_media_custom_column', 'ewww-image-optimizer', $attachment_id );
		$this->assertTrue( $result );

		// with working copy its local files are used.
		$this->assertTrue( Working_Copies_Object::get_instance()->create( $attachment_id ) );
		do_action( 'manage_media_custom_column', 'ewww-image-optimizer', $attachment_id );
		$this->assertFalse( $result );
		remove_action( 'manage_media_custom_column', $check );
	}
}
