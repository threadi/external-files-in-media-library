<?php
/**
 * Tests for variants of exported images in other formats (e.g. WebP), which image optimizers create.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Restore;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes;
use ExternalFilesInMediaLibrary\ExternalFiles\Variants as Variants_Object;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\ThirdParty\ConverterForMedia;
use ExternalFilesInMediaLibrary\ThirdParty\Imagify;
use ExternalFilesInMediaLibrary\ThirdParty\ModernImageFormats;
use ExternalFilesInMediaLibrary\ThirdParty\ShortPixel;
use ExternalFilesInMediaLibrary\ThirdParty\Smush;

/**
 * Object to test variants of exported images in other formats.
 */
class Variants extends externalFilesTests {

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

		// bail if WebP files could not be created.
		if ( ! function_exists( 'imagewebp' ) ) {
			$this->markTestSkipped( 'WebP is not supported.' );
		}

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// forget the file types of former tests, as attachment IDs could be used again after their rollback.
		$property = new \ReflectionProperty( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::class, 'files' );
		$property->setValue( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance(), array() );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-variants-' . uniqid() . '/';
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

		// use the variants of Imagify (e.g. "image.jpg.webp").
		add_filter( 'efml_image_variants', array( Imagify::get_instance(), 'add_variants' ), 10, 3 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		remove_all_filters( 'efml_image_variants' );
		unset( $_SERVER['HTTP_ACCEPT'] );
		Import_Runs::get_instance()->cancel_all();
		if ( '' !== $this->export_directory ) {
			\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
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
	 * Create a local image with a WebP variant of itself and of its medium size and return its ID.
	 *
	 * @return int
	 */
	private function create_image_with_variants(): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-variant-' . wp_generate_password( 6, false, false ) . '.jpg';
		$image = imagecreatetruecolor( 1200, 800 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );

		// create the variants like an image optimizer.
		$local = (string) get_attached_file( $attachment_id, true );
		$this->create_webp( $local . '.webp' );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$this->create_webp( trailingslashit( dirname( $local ) ) . $metadata['sizes']['medium']['file'] . '.webp' );

		return $attachment_id;
	}

	/**
	 * Create a WebP file at the given path.
	 *
	 * @param string $path The path.
	 *
	 * @return void
	 */
	private function create_webp( string $path ): void {
		$image = imagecreatetruecolor( 100, 80 );
		imagewebp( $image, $path );
		$this->assertFileExists( $path );
	}

	/**
	 * Test that variants are exported together with the image and its sizes, and their local files are removed.
	 *
	 * @return void
	 */
	public function test_variants_are_exported(): void {
		$attachment_id = $this->create_image_with_variants();
		$local         = (string) get_attached_file( $attachment_id, true );
		$medium        = wp_get_attachment_metadata( $attachment_id )['sizes']['medium']['file'];

		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );

		// both variants are saved with their files.
		$variants = Variants_Object::get_instance()->get_variants( $attachment_id );
		$this->assertCount( 2, $variants );
		$this->assertArrayHasKey( $medium . '.webp', $variants );
		$this->assertEquals( 'medium', $variants[ $medium . '.webp' ]['size'] );
		$this->assertEquals( $medium, $variants[ $medium . '.webp' ]['of'] );
		$this->assertEquals( 'image/webp', $variants[ $medium . '.webp' ]['mime-type'] );

		// they exist on the external source, not locally anymore.
		foreach ( $variants as $variant ) {
			$this->assertFileExists( $this->export_directory . $variant['file'] );
		}
		$this->assertFileDoesNotExist( $local . '.webp' );
		$this->assertFileDoesNotExist( trailingslashit( dirname( $local ) ) . $medium . '.webp' );

		// they are deleted together with the image.
		wp_delete_attachment( $attachment_id, true );
		foreach ( $variants as $variant ) {
			$this->assertFileDoesNotExist( $this->export_directory . $variant['file'] );
		}
	}

	/**
	 * Test the choice of the variant for the browser and its cache for the proxy.
	 *
	 * @return void
	 */
	public function test_variant_for_browser(): void {
		$attachment_id = $this->create_image_with_variants();
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$variants_obj = Variants_Object::get_instance();

		// a browser without WebP gets the image itself.
		$_SERVER['HTTP_ACCEPT'] = 'image/png,image/*;q=0.8';
		$this->assertEmpty( $variants_obj->get_variant_for_browser( $attachment_id, Variants_Object::FULL ) );
		$_SERVER['HTTP_ACCEPT'] = 'image/webp;q=0,image/*';
		$this->assertEmpty( $variants_obj->get_variant_for_browser( $attachment_id, Variants_Object::FULL ) );

		// a browser with WebP gets the variant, of the image and of the size.
		$_SERVER['HTTP_ACCEPT'] = 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8';
		$variant = $variants_obj->get_variant_for_browser( $attachment_id, Variants_Object::FULL );
		$this->assertEquals( Variants_Object::FULL, $variant['size'] );
		$medium = Sizes::get_instance()->get_sizes( $attachment_id )['medium'];
		$this->assertEquals( 'medium', $variants_obj->get_variant_for_browser( $attachment_id, 'size', $medium['file'] )['size'] );
		$this->assertEmpty( $variants_obj->get_variant_for_browser( $attachment_id, 'size', Sizes::get_instance()->get_sizes( $attachment_id )['thumbnail']['file'] ) );

		// the proxy loads it into its cache.
		$path = $variants_obj->cache( Files::get_instance()->get_file( $attachment_id ), $variant );
		$this->assertFileExists( $path );
		$this->assertEquals( 'image/webp', wp_get_image_mime( $path ) );
	}

	/**
	 * Test that the variants are saved at their local paths again during the restore of the image.
	 *
	 * @return void
	 */
	public function test_restore_with_variants(): void {
		$attachment_id = $this->create_image_with_variants();
		$local         = (string) get_attached_file( $attachment_id, true );
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$this->assertFileDoesNotExist( $local . '.webp' );

		$this->assertTrue( Restore::get_instance()->restore_file( $attachment_id ) );
		$this->assertFileExists( $local . '.webp' );
		$this->assertEquals( 'image/webp', wp_get_image_mime( $local . '.webp' ) );
		$this->assertEmpty( Variants_Object::get_instance()->get_variants( $attachment_id ) );
	}

	/**
	 * Test that variants follow their files, if these get new names (e.g. after a renaming).
	 *
	 * @return void
	 */
	public function test_variants_follow_renamed_files(): void {
		$attachment_id = $this->create_image_with_variants();
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$variants_obj = Variants_Object::get_instance();
		$old          = $variants_obj->get_variants( $attachment_id );

		// update them for renamed files.
		$sources = Sizes::get_instance()->get_export_sources_with_sizes( $attachment_id );
		$term_id = (int) array_key_last( $sources );
		$files   = array(
			Variants_Object::FULL => 'renamed.jpg',
			'medium'              => 'renamed-300x200.jpg',
		);
		$variants_obj->update( $attachment_id, $sources[ $term_id ], $term_id, trailingslashit( get_temp_dir() ) . 'efml-not-existing/', $this->export_directory, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id ), $files, false );

		// the variants have the new names, the former files are deleted.
		$new = $variants_obj->get_variants( $attachment_id );
		$this->assertArrayHasKey( 'renamed.jpg.webp', $new );
		$this->assertArrayHasKey( 'renamed-300x200.jpg.webp', $new );
		$this->assertCount( 2, $new );
		$this->assertFileExists( $this->export_directory . 'renamed.jpg.webp' );
		foreach ( $old as $variant ) {
			$this->assertFileDoesNotExist( $this->export_directory . $variant['file'] );
		}
	}

	/**
	 * Test the names of variants of renamed files.
	 *
	 * @return void
	 */
	public function test_renamed_file_names(): void {
		$variants_obj = Variants_Object::get_instance();
		$this->assertEquals( 'new.jpg.webp', $variants_obj->get_renamed_file( 'old.jpg.webp', 'old.jpg', 'new.jpg' ) );
		$this->assertEquals( 'new.webp', $variants_obj->get_renamed_file( 'old.webp', 'old.jpg', 'new.jpg' ) );
		$this->assertEquals( 'new-jpg.webp', $variants_obj->get_renamed_file( 'old-jpg.webp', 'old.jpg', 'new.jpg' ) );
		$this->assertEquals( '', $variants_obj->get_renamed_file( 'other.webp', 'old.jpg', 'new.jpg' ) );
	}

	/**
	 * Test that files of other attachments are never used as variants (e.g. "image.webp" beside "image.jpg").
	 *
	 * @return void
	 */
	public function test_file_of_other_attachment_is_no_variant(): void {
		remove_all_filters( 'efml_image_variants' );
		add_filter( 'efml_image_variants', array( ShortPixel::get_instance(), 'add_variants' ), 10, 3 );
		$attachment_id = $this->create_image_with_variants();
		$local         = (string) get_attached_file( $attachment_id, true );
		$other         = substr( $local, 0, -4 ) . '.webp';
		$this->create_webp( $other );
		$other_id = self::factory()->attachment->create_object( _wp_relative_upload_path( $other ), 0, array( 'post_mime_type' => 'image/webp' ) );
		update_post_meta( $other_id, '_wp_attached_file', _wp_relative_upload_path( $other ) );

		$variants = Variants_Object::get_instance()->get_local_variants( $attachment_id, trailingslashit( dirname( $local ) ), array( Variants_Object::FULL => wp_basename( $local ) ) );
		$this->assertArrayHasKey( wp_basename( $local ) . '.webp', $variants );
		$this->assertArrayNotHasKey( wp_basename( $other ), $variants );
		wp_delete_file( $other );
	}

	/**
	 * Test the variants of Modern Image Formats from the metadata and that it does not change the HTML of exported images.
	 *
	 * @return void
	 */
	public function test_modern_image_formats(): void {
		remove_all_filters( 'efml_image_variants' );
		add_filter( 'efml_image_variants', array( ModernImageFormats::get_instance(), 'add_variants' ), 10, 3 );
		$attachment_id = $this->create_image_with_variants();
		$local         = (string) get_attached_file( $attachment_id, true );

		// add the sources like the plugin.
		$metadata            = wp_get_attachment_metadata( $attachment_id );
		$metadata['sources'] = array(
			'image/jpeg' => array( 'file' => wp_basename( $local ) ),
			'image/webp' => array( 'file' => wp_basename( $local ) . '.webp' ),
		);
		update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );

		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$this->assertArrayHasKey( wp_basename( $local ) . '.webp', Variants_Object::get_instance()->get_variants( $attachment_id ) );

		// the HTML of the exported image is not changed.
		$this->assertEquals( array( 'image/jpeg' ), ModernImageFormats::get_instance()->prevent_replacement_in_content( array( 'image/webp', 'image/jpeg' ), $attachment_id ) );
		$this->assertEquals( array(), ModernImageFormats::get_instance()->prevent_picture_element( array( 'image/webp' ), $attachment_id ) );
		$this->assertEquals( array( 'image/webp' ), ModernImageFormats::get_instance()->prevent_picture_element( array( 'image/webp' ), 0 ) );
	}

	/**
	 * Test the variants of Converter for Media in its own directory.
	 *
	 * @return void
	 */
	public function test_converter_for_media(): void {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-variant-' . wp_generate_password( 6, false, false ) . '.jpg';
		$image = imagecreatetruecolor( 400, 300 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		$local = (string) get_attached_file( $attachment_id, true );

		// create the variant in the directory of the plugin.
		$uploads = wp_normalize_path( wp_get_upload_dir()['basedir'] );
		$path    = trailingslashit( dirname( $uploads ) ) . 'uploads-webpc/' . wp_basename( $uploads ) . substr( wp_normalize_path( $local ), strlen( $uploads ) ) . '.webp';
		wp_mkdir_p( dirname( $path ) );
		$this->create_webp( $path );

		// use the directories of this test installation.
		$root = trailingslashit( dirname( dirname( $uploads ) ) );
		$dirs = static function ( $value, $directory ) use ( $uploads, $root ) {
			$base = substr( dirname( $uploads ), strlen( $root ) );
			return 'webp' === $directory ? $base . '/uploads-webpc' : $base . '/' . wp_basename( $uploads );
		};
		$site_root = static function () use ( $root ) {
			return $root;
		};
		add_filter( 'webpc_dir_name', $dirs, 10, 2 );
		add_filter( 'webpc_site_root', $site_root );

		$variants = ConverterForMedia::get_instance()->add_variants( array(), $attachment_id, array( Variants_Object::FULL => $local ) );
		remove_filter( 'webpc_dir_name', $dirs );
		remove_filter( 'webpc_site_root', $site_root );
		wp_delete_file( $path );

		$this->assertCount( 1, $variants );
		$this->assertEquals( wp_normalize_path( $path ), wp_normalize_path( $variants[0]['path'] ) );
		$this->assertEquals( 'image/webp', $variants[0]['mime-type'] );
	}
	/**
	 * Test that the backup of Smush is exported and restored with the image, but never delivered.
	 *
	 * @return void
	 */
	public function test_smush_backup(): void {
		remove_all_filters( 'efml_image_variants' );
		add_filter( 'efml_image_variants', array( Smush::get_instance(), 'add_variants' ), 10, 3 );
		$file  = trailingslashit( get_temp_dir() ) . 'efml-variant-' . wp_generate_password( 6, false, false ) . '.jpg';
		$image = imagecreatetruecolor( 600, 400 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );

		// create the backup like Smush.
		$local  = (string) get_attached_file( $attachment_id, true );
		$backup = substr( $local, 0, -4 ) . '.bak.jpg';
		copy( $local, $backup );

		// it is exported, not local anymore and never delivered.
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		$this->assertFileExists( $this->export_directory . wp_basename( $backup ) );
		$this->assertFileDoesNotExist( $backup );
		$_SERVER['HTTP_ACCEPT'] = 'image/jpeg,image/*';
		$this->assertEmpty( Variants_Object::get_instance()->get_variant_for_browser( $attachment_id, Variants_Object::FULL ) );

		// it is restored with the image.
		$this->assertTrue( Restore::get_instance()->restore_file( $attachment_id ) );
		$this->assertFileExists( $backup );
	}

}
