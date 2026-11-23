<?php
/**
 * Tests for the support of the plugins "Prevent Direct Access" and "Prevent Files / Folders Access", and for the
 * image of the watermark of "Image Watermark", which is not exported.
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
 * Object to test the support of the plugins "Prevent Direct Access" and "Prevent Files / Folders Access", and the
 * image of the watermark of "Image Watermark".
 */
class ProtectionPlugins extends externalFilesTests {

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
	 * Test that files protected by Prevent Direct Access are not exported.
	 *
	 * @return void
	 */
	public function test_prevent_direct_access(): void {
		$obj = \ExternalFilesInMediaLibrary\ThirdParty\PreventDirectAccess::get_instance();
		$obj->add_hooks();

		// test 1: not protected files are exported.
		$attachment_id = $this->create_image();
		$this->assertFalse( $obj->is_protected( $attachment_id ) );
		$this->assertTrue( Export::get_instance()->is_exportable( $attachment_id ) );

		// test 2: protected files are not exported.
		update_post_meta( $attachment_id, '_pda_protection', '1' );
		$this->assertTrue( $obj->is_protected( $attachment_id ) );
		$this->assertFalse( Export::get_instance()->is_exportable( $attachment_id ) );
		$this->assertFalse( Export::get_instance()->export_file( $attachment_id ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertStringContainsString( 'Prevent Direct Access', $obj->get_prevent_reason( '', Files::get_instance()->get_file( $attachment_id ) ) );
		$this->assertSame( 'other', $obj->get_prevent_reason( 'other', Files::get_instance()->get_file( $attachment_id ) ) );

		// test 3: also files in the protected folder.
		delete_post_meta( $attachment_id, '_pda_protection' );
		$file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		update_post_meta( $attachment_id, '_wp_attached_file', '_pda/' . $file );
		$this->assertTrue( $obj->is_protected( $attachment_id ) );
		update_post_meta( $attachment_id, '_wp_attached_file', $file );

		// test 4: the marker in the media library.
		update_post_meta( $attachment_id, '_pda_protection', '1' );
		ob_start();
		$obj->show_marker( $attachment_id );
		$this->assertStringContainsString( 'dashicons-lock', (string) ob_get_clean() );

		// test 5: the column of the plugin is hidden for external files only.
		list( $exported_id ) = $this->create_exported_image();
		ob_start();
		$obj->hide_column_for_external_files( 'direct_access', $exported_id );
		$this->assertStringContainsString( '#pda-v3-column_' . $exported_id, (string) ob_get_clean() );
		ob_start();
		$obj->hide_column_for_external_files( 'direct_access', $attachment_id );
		$obj->hide_column_for_external_files( 'other', $exported_id );
		$this->assertSame( '', ob_get_clean() );
		remove_filter( 'efml_prevent_export', array( $obj, 'prevent_export' ) );
	}

	/**
	 * Test that files of the types protected by Prevent Files / Folders Access are not exported.
	 *
	 * @return void
	 */
	public function test_prevent_file_access(): void {
		$obj = \ExternalFilesInMediaLibrary\ThirdParty\PreventFileAccess::get_instance();
		$obj->add_hooks();
		$attachment_id = $this->create_image();

		// test 1: without the restriction, files are exported.
		update_option( 'mo_enable_media_restriction', 0 );
		$this->assertEquals( array(), $obj->get_protected_extensions() );
		$this->assertTrue( Export::get_instance()->is_exportable( $attachment_id ) );

		// test 2: with the restriction, the default types are protected.
		update_option( 'mo_enable_media_restriction', 1 );
		delete_option( 'mo_media_restriction_file_types' );
		$this->assertContains( 'jpg', $obj->get_protected_extensions() );
		$this->assertFalse( Export::get_instance()->is_exportable( $attachment_id ) );
		$this->assertStringContainsString( 'Prevent Files / Folders Access', $obj->get_prevent_reason( '', Files::get_instance()->get_file( $attachment_id ) ) );

		// test 3: only the configured types are protected.
		update_option( 'mo_media_restriction_file_types', 'pdf|docx' );
		$this->assertEquals( array( 'pdf', 'docx' ), $obj->get_protected_extensions() );
		$this->assertTrue( Export::get_instance()->is_exportable( $attachment_id ) );
		update_option( 'mo_media_restriction_file_types', 'png|JPG' );
		$this->assertFalse( Export::get_instance()->is_exportable( $attachment_id ) );

		// test 4: the marker in the media library.
		ob_start();
		$obj->show_marker( $attachment_id );
		$this->assertStringContainsString( 'dashicons-lock', (string) ob_get_clean() );

		delete_option( 'mo_enable_media_restriction' );
		delete_option( 'mo_media_restriction_file_types' );
		remove_filter( 'efml_prevent_export', array( $obj, 'prevent_export' ) );
	}

	/**
	 * Test that the image of the watermark of Image Watermark is not exported and restored, if it has been exported.
	 *
	 * @return void
	 */
	public function test_image_watermark_image(): void {
		// add only the hooks for the image of the watermark (the other hooks would delay uploads in other tests).
		$obj = \ExternalFilesInMediaLibrary\ThirdParty\ImageWatermark::get_instance();
		add_filter( 'efml_prevent_export', array( $obj, 'prevent_export_of_watermark_image' ), 10, 2 );
		add_action( 'add_option_image_watermark_options', array( $obj, 'restore_watermark_image' ) );
		add_action( 'update_option_image_watermark_options', array( $obj, 'restore_watermark_image' ) );
		$attachment_id = $this->create_image();

		// test 1: without settings or with a text as watermark, images are exported.
		delete_option( 'image_watermark_options' );
		$this->assertSame( 0, $obj->get_watermark_image_id() );
		update_option( 'image_watermark_options', array( 'watermark_image' => array( 'type' => 'text', 'url' => $attachment_id ) ) );
		$this->assertSame( 0, $obj->get_watermark_image_id() );
		$this->assertTrue( Export::get_instance()->is_exportable( $attachment_id ) );

		// test 2: the image of the watermark is not exported (the plugin uses an image by default).
		update_option( 'image_watermark_options', array( 'watermark_image' => array( 'url' => $attachment_id ) ) );
		$this->assertSame( $attachment_id, $obj->get_watermark_image_id() );
		$this->assertFalse( Export::get_instance()->is_exportable( $attachment_id ) );
		$this->assertFalse( Export::get_instance()->export_file( $attachment_id ) );
		$this->assertStringContainsString( 'Image Watermark', $obj->get_prevent_reason( '', Files::get_instance()->get_file( $attachment_id ) ) );

		// test 3: an exported image is restored as local file, when it is chosen as watermark.
		list( $exported_id, $files ) = $this->create_exported_image();
		$this->assertFileDoesNotExist( $files[0] );
		update_option( 'image_watermark_options', array( 'watermark_image' => array( 'type' => 'image', 'url' => $exported_id ) ) );
		$this->assertSame( 0, absint( get_post_meta( $exported_id, 'eml_exported_file', true ) ) );
		$this->assertFileExists( (string) get_attached_file( $exported_id ) );
		$this->assertFalse( Files::get_instance()->get_file( $exported_id )->is_valid() );

		remove_action( 'add_option_image_watermark_options', array( $obj, 'restore_watermark_image' ) );
		remove_action( 'update_option_image_watermark_options', array( $obj, 'restore_watermark_image' ) );
		delete_option( 'image_watermark_options' );
		remove_filter( 'efml_prevent_export', array( $obj, 'prevent_export_of_watermark_image' ) );
	}
}
