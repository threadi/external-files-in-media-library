<?php
/**
 * Tests for the info box of external files: export targets with folders and links to the platform of the file.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the info box of external files.
 */
class FileInfoBox extends externalFilesTests {
	/**
	 * Prepare a file.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'efml_file_platform_url' );
		$this->do_logout();
		parent::tear_down();
	}

	/**
	 * Return the file object of the test URL.
	 *
	 * @return \ExternalFilesInMediaLibrary\ExternalFiles\File
	 */
	private function get_file_object(): \ExternalFilesInMediaLibrary\ExternalFiles\File {
		$file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file_obj );
		return $file_obj;
	}

	/**
	 * Test that the export info shows the used folder of the external source.
	 *
	 * @return void
	 */
	public function test_export_info_shows_folder(): void {
		$this->do_login();
		$file_obj = $this->get_file_object();

		// create two external sources.
		$taxonomy   = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name();
		$term_id    = self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy,
				'name'     => 'DropBox/screenshots/',
			)
		);
		$term_id_2  = self::factory()->term->create(
			array(
				'taxonomy' => $taxonomy,
				'name'     => 'Other source',
			)
		);

		// mark the file as exported to both, with a folder only on the first.
		update_post_meta( $file_obj->get_id(), 'eml_exported_file', time() );
		update_post_meta( $file_obj->get_id(), 'efml_export_sources', array( $term_id, $term_id_2 ) );
		update_post_meta( $file_obj->get_id(), 'efml_export_folders', array( $term_id => '2026/11' ) );

		// get the output.
		ob_start();
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->add_info_about_export( $file_obj );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<em>DropBox/screenshots/2026/11</em>', $output );
		$this->assertStringContainsString( '<em>Other source</em>', $output );
	}

	/**
	 * Test the link to the platform of the file in the info box.
	 *
	 * @return void
	 */
	public function test_platform_link_in_info_box(): void {
		$this->do_login();
		$file_obj = $this->get_file_object();

		// a file without platform shows no link.
		$this->assertStringNotContainsString( 'dashicons-external', $this->get_info_box( $file_obj ) );

		// a file of DropBox shows the link to DropBox.
		$file_obj->set_service_name( 'dropbox' );
		$output = $this->get_info_box( $file_obj );
		$this->assertStringContainsString( 'href="https://www.dropbox.com"', $output );
		$this->assertStringContainsString( 'Go to DropBox', $output );

		// the link could be changed via hook.
		add_filter( 'efml_file_platform_url', array( $this, 'get_fixed_platform_url' ) );
		$this->assertStringContainsString( 'href="https://platform.example.com/"', $this->get_info_box( $file_obj ) );
	}

	/**
	 * Return a fixed platform URL.
	 *
	 * @return string
	 */
	public function get_fixed_platform_url(): string {
		return 'https://platform.example.com/';
	}

	/**
	 * Return the output of the info box for the given file.
	 *
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\File $file_obj The file.
	 *
	 * @return string
	 */
	private function get_info_box( \ExternalFilesInMediaLibrary\ExternalFiles\File $file_obj ): string {
		ob_start();
		\ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->add_media_box_with_file_info( get_post( $file_obj->get_id() ) );
		return (string) ob_get_clean();
	}

	/**
	 * Test the platform URLs of the services.
	 *
	 * @return void
	 */
	public function test_get_platform_url(): void {
		$this->assertEquals( 'https://www.dropbox.com', \ExternalFilesInMediaLibrary\Services\DropBox::get_instance()->get_platform_url() );
		$this->assertEquals( 'https://www.youtube.com/', \ExternalFilesInMediaLibrary\Services\Youtube::get_instance()->get_platform_url() );
		$this->assertEquals( '', \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_platform_url() );

		// the directory listing of YouTube links to YouTube.
		$method = new \ReflectionMethod( \ExternalFilesInMediaLibrary\Services\Youtube::get_instance(), 'get_global_actions' );
		$labels = array_column( $method->invoke( \ExternalFilesInMediaLibrary\Services\Youtube::get_instance() ), 'label' );
		$this->assertContains( 'Go to YouTube', $labels );
	}
}
