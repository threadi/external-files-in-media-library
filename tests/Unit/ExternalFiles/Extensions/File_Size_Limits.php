<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\File_Size_Limits.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\File_Size_Limits as Limits;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\File_Size_Limits.
 */
class File_Size_Limits extends externalFilesTests {
	/**
	 * List of options, which the tests set.
	 *
	 * @var array<int,string>
	 */
	private array $options = array();

	/**
	 * Limits in bytes, which replace the limits from the settings, if set.
	 *
	 * @var array<string,int>|null
	 */
	private ?array $byte_limits = null;

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->options as $option ) {
			delete_option( $option );
		}
		$this->options     = array();
		$this->byte_limits = null;
		remove_filter( 'efml_file_size_limits', array( $this, 'set_byte_limits' ) );
		delete_option( 'efml_import_results_' . get_current_user_id() );
		parent::tear_down();
	}

	/**
	 * Set an option for this test.
	 *
	 * @param string $option The option name.
	 * @param int    $value The value.
	 *
	 * @return void
	 */
	private function set_option( string $option, int $value ): void {
		update_option( $option, $value );
		$this->options[] = $option;
	}

	/**
	 * Set limits in bytes, as the test files are smaller than the 1 MB of the settings.
	 *
	 * @param int $min The minimum size in bytes.
	 * @param int $max The maximum size in bytes.
	 *
	 * @return void
	 */
	private function use_byte_limits( int $min, int $max ): void {
		$this->byte_limits = array(
			'min' => $min,
			'max' => $max,
		);
		add_filter( 'efml_file_size_limits', array( $this, 'set_byte_limits' ) );
	}

	/**
	 * Replace the limits with the limits in bytes of this test.
	 *
	 * @param array<string,array<string,int|string>> $limits The limits.
	 *
	 * @return array<string,array<string,int|string>>
	 */
	public function set_byte_limits( array $limits ): array {
		if ( is_null( $this->byte_limits ) ) {
			return $limits;
		}
		return array(
			'min' => array(
				'size'   => $this->byte_limits['min'],
				'source' => 'test setting',
			),
			'max' => array(
				'size'   => $this->byte_limits['max'],
				'source' => 'test setting',
			),
		);
	}

	/**
	 * Return the texts of all results of the actual import.
	 *
	 * @return string
	 */
	private function get_result_texts(): string {
		$texts = '';
		foreach ( Results::get_instance()->get_results() as $result ) {
			$texts .= $result->get_text() . "\n";
		}
		return $texts;
	}

	/**
	 * Test that no limit is used without settings.
	 *
	 * @return void
	 */
	public function test_no_limits_without_settings(): void {
		$limits = Limits::get_instance()->get_limits( 'image/jpeg', 'ftp' );
		$this->assertSame( 0, $limits['min']['size'] );
		$this->assertSame( 0, $limits['max']['size'] );
		$this->assertSame( '', Limits::get_instance()->get_error( array( 'filesize' => 5 * MB_IN_BYTES, 'mime-type' => 'image/jpeg' ), 'ftp' ) );
	}

	/**
	 * Test that the global limits are used, if nothing else is set.
	 *
	 * @return void
	 */
	public function test_global_limits(): void {
		$this->set_option( 'eml_import_min_size', 1 );
		$this->set_option( 'eml_import_max_size', 50 );

		$limits = Limits::get_instance()->get_limits( 'image/jpeg', 'ftp' );
		$this->assertSame( MB_IN_BYTES, $limits['min']['size'] );
		$this->assertSame( 50 * MB_IN_BYTES, $limits['max']['size'] );
		$this->assertSame( 'global setting', $limits['max']['source'] );
	}

	/**
	 * Test that a limit of the file type replaces the global limit, also with a higher value.
	 *
	 * @return void
	 */
	public function test_file_type_replaces_global_limit(): void {
		$this->set_option( 'eml_import_max_size', 20 );
		$this->set_option( Limits::get_instance()->get_file_type_option( 'video', 'max' ), 500 );

		// videos use their own limit.
		$limits = Limits::get_instance()->get_limits( 'video/mp4', '' );
		$this->assertSame( 500 * MB_IN_BYTES, $limits['max']['size'] );
		$this->assertStringContainsString( 'setting for', $limits['max']['source'] );

		// images use the global limit.
		$limits = Limits::get_instance()->get_limits( 'image/jpeg', '' );
		$this->assertSame( 20 * MB_IN_BYTES, $limits['max']['size'] );
	}

	/**
	 * Test that a limit of the service replaces the global limit.
	 *
	 * @return void
	 */
	public function test_service_replaces_global_limit(): void {
		$this->set_option( 'eml_import_max_size', 20 );
		$this->set_option( Limits::get_instance()->get_service_option( 'ftp', 'max' ), 100 );

		$this->assertSame( 100 * MB_IN_BYTES, Limits::get_instance()->get_limits( 'image/jpeg', 'ftp' )['max']['size'] );
		$this->assertSame( 20 * MB_IN_BYTES, Limits::get_instance()->get_limits( 'image/jpeg', 'local' )['max']['size'] );
		$this->assertSame( 20 * MB_IN_BYTES, Limits::get_instance()->get_limits( 'image/jpeg', '' )['max']['size'] );
	}

	/**
	 * Test that the stricter limit is used, if the service and the file type have a limit.
	 *
	 * @return void
	 */
	public function test_stricter_limit_of_service_and_file_type(): void {
		$this->set_option( 'eml_import_max_size', 1000 );
		$this->set_option( Limits::get_instance()->get_service_option( 'ftp', 'max' ), 100 );
		$this->set_option( Limits::get_instance()->get_service_option( 'ftp', 'min' ), 2 );
		$this->set_option( Limits::get_instance()->get_file_type_option( 'video', 'max' ), 500 );
		$this->set_option( Limits::get_instance()->get_file_type_option( 'video', 'min' ), 5 );

		$limits = Limits::get_instance()->get_limits( 'video/mp4', 'ftp' );

		// the smaller maximum of the service is used.
		$this->assertSame( 100 * MB_IN_BYTES, $limits['max']['size'] );

		// the larger minimum of the file type is used.
		$this->assertSame( 5 * MB_IN_BYTES, $limits['min']['size'] );
	}

	/**
	 * Test the check of single files against the limits, including the boundaries.
	 *
	 * @return void
	 */
	public function test_error_for_single_files(): void {
		$this->set_option( 'eml_import_min_size', 1 );
		$this->set_option( 'eml_import_max_size', 2 );
		$obj = Limits::get_instance();

		// files within the limits (including the boundaries).
		$this->assertSame( '', $obj->get_error( array( 'filesize' => MB_IN_BYTES, 'mime-type' => 'image/jpeg' ), '' ) );
		$this->assertSame( '', $obj->get_error( array( 'filesize' => 2 * MB_IN_BYTES, 'mime-type' => 'image/jpeg' ), '' ) );

		// too large and too small files.
		$this->assertStringContainsString( 'at most 2 MB', $obj->get_error( array( 'filesize' => 3 * MB_IN_BYTES, 'title' => 'big.jpg', 'mime-type' => 'image/jpeg' ), '' ) );
		$this->assertStringContainsString( 'big.jpg', $obj->get_error( array( 'filesize' => 3 * MB_IN_BYTES, 'title' => 'big.jpg', 'mime-type' => 'image/jpeg' ), '' ) );
		$this->assertStringContainsString( 'at least 1 MB', $obj->get_error( array( 'filesize' => 1000, 'mime-type' => 'image/jpeg' ), '' ) );
	}

	/**
	 * Test that files with an unknown size are never blocked (e.g. from YouTube or Vimeo, which use 0 or 1 as size).
	 *
	 * @return void
	 */
	public function test_unknown_size_is_allowed(): void {
		$this->set_option( 'eml_import_min_size', 5 );
		$this->set_option( 'eml_import_max_size', 10 );

		$this->assertSame( '', Limits::get_instance()->get_error( array( 'filesize' => 0, 'mime-type' => 'video/mp4' ), '' ) );
		$this->assertSame( '', Limits::get_instance()->get_error( array( 'filesize' => 1, 'mime-type' => 'video/mp4' ), '' ) );
		$this->assertSame( '', Limits::get_instance()->get_error( array( 'mime-type' => 'video/mp4' ), '' ) );
	}

	/**
	 * Test that the size of the temporary file is used, if the size is not known otherwise.
	 *
	 * @return void
	 */
	public function test_size_of_temporary_file_is_used(): void {
		$this->use_byte_limits( 0, 10 );

		$tmp_file = wp_tempnam();
		file_put_contents( $tmp_file, str_repeat( 'x', 100 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$error = Limits::get_instance()->get_error( array( 'filesize' => 0, 'tmp-file' => $tmp_file, 'mime-type' => 'text/plain' ), '' );
		wp_delete_file( $tmp_file );

		$this->assertStringContainsString( 'has not been added', $error );
	}

	/**
	 * Test that a file within the limits is imported.
	 *
	 * @return void
	 */
	public function test_import_within_limits(): void {
		$this->use_byte_limits( 10, 100 * MB_IN_BYTES );

		$this->assertTrue( Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$this->assertEquals( 1, $this->get_media_file_count() );
	}

	/**
	 * Test that a too large file is not imported and the reason is shown.
	 *
	 * @return void
	 */
	public function test_import_of_too_large_file(): void {
		$this->use_byte_limits( 0, 10 );

		$this->assertFalse( Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$this->assertEquals( 0, $this->get_media_file_count() );

		// the reason is shown as error.
		$texts = $this->get_result_texts();
		$this->assertStringContainsString( 'has not been added to the media library', $texts );
		$this->assertStringContainsString( 'at most 10 B', $texts );
		$this->assertStringNotContainsString( 'No files to import were found', $texts );
	}

	/**
	 * Test that a too small file is not imported, with the real setting in MB.
	 *
	 * @return void
	 */
	public function test_import_of_too_small_file(): void {
		$this->set_option( 'eml_import_min_size', 1 );

		$this->assertFalse( Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$this->assertEquals( 0, $this->get_media_file_count() );
		$this->assertStringContainsString( 'at least 1 MB (global setting)', $this->get_result_texts() );
	}

	/**
	 * Test that a too large file, which would be saved locally, is not downloaded at all.
	 *
	 * @return void
	 */
	public function test_too_large_file_is_not_downloaded(): void {
		$this->use_byte_limits( 0, 10 );
		update_option( 'eml_pdf_mode', 'local' );
		$this->options[] = 'eml_pdf_mode';

		// count the downloads.
		$downloads = 0;
		$counter   = static function ( $response, array $parsed_args ) use ( &$downloads ) {
			if ( 'GET' === $parsed_args['method'] ) {
				++$downloads;
			}
			return $response;
		};
		add_filter( 'pre_http_request', $counter, 1, 2 );

		$result = Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );
		remove_filter( 'pre_http_request', $counter, 1 );

		$this->assertFalse( $result );
		$this->assertSame( 0, $downloads );
		$this->assertEquals( 0, $this->get_media_file_count() );
	}

	/**
	 * Test that the check before loading a file is only used during an import, not e.g. during the change of the hosting.
	 *
	 * @return void
	 */
	public function test_loading_is_only_prevented_during_import(): void {
		$this->use_byte_limits( 0, 10 );

		$this->assertFalse( Import::get_instance()->is_loading_files() );
		$this->assertFalse( Import::get_instance()->is_file_loading_prevented( array( 'url' => 'https://example.com/big.pdf', 'filesize' => 100, 'mime-type' => 'application/pdf' ) ) );
	}

	/**
	 * Test the marking of files in the directory listing.
	 *
	 * @return void
	 */
	public function test_marking_in_directory_listing(): void {
		$this->use_byte_limits( 10, 1000 );

		$list = array(
			'/root/' => array(
				'title' => 'root',
				'files' => array(
					array( 'title' => 'ok.jpg', 'filesize' => 500, 'mime-type' => 'image/jpeg' ),
					array( 'title' => 'big.jpg', 'filesize' => 5000, 'mime-type' => 'image/jpeg' ),
					array( 'title' => 'small.jpg', 'filesize' => 5, 'mime-type' => 'image/jpeg' ),
					array( 'title' => 'unknown.mp4', 'filesize' => 0, 'mime-type' => 'video/mp4' ),
				),
				'dirs'  => array(),
			),
			'completed' => true,
		);

		$result = Limits::get_instance()->mark_files_in_listing( $list, '/root/', 'ftp' );
		$files  = $result['/root/']['files'];

		$this->assertArrayNotHasKey( 'disabled', $files[0] );
		$this->assertTrue( $files[1]['disabled'] );
		$this->assertStringContainsString( 'Too large', $files[1]['notice'] );
		$this->assertTrue( $files[2]['disabled'] );
		$this->assertStringContainsString( 'Too small', $files[2]['notice'] );
		$this->assertArrayNotHasKey( 'disabled', $files[3] );
		$this->assertTrue( $result['completed'] );
	}

	/**
	 * Test the hint in the import dialog.
	 *
	 * @return void
	 */
	public function test_hint_in_dialog(): void {
		$dialog = array( 'texts' => array() );

		// no hint without limits.
		$this->assertSame( array(), Limits::get_instance()->add_hint_in_dialog( $dialog, array() )['texts'] );

		// hint with the global limit and the different limit of a file type.
		$this->set_option( 'eml_import_max_size', 20 );
		$this->set_option( Limits::get_instance()->get_file_type_option( 'video', 'max' ), 500 );
		$texts = implode( '', Limits::get_instance()->add_hint_in_dialog( $dialog, array() )['texts'] );
		$this->assertStringContainsString( 'up to 20 MB (global setting)', $texts );
		$this->assertStringContainsString( 'up to 500 MB', $texts );

		// hint with the limit of the service.
		$this->set_option( Limits::get_instance()->get_service_option( 'ftp', 'max' ), 100 );
		$texts = implode( '', Limits::get_instance()->add_hint_in_dialog( $dialog, array( 'service' => 'ftp' ) )['texts'] );
		$this->assertStringContainsString( 'up to 100 MB', $texts );
	}

	/**
	 * Test the import of a too large file from a real FTP server: it is not loaded and the reason is shown.
	 *
	 * @return void
	 */
	public function test_import_from_ftp_server(): void {
		$server = TestServers::get( 'ftp', $this );
		$this->use_byte_limits( 0, 100 );

		// import a file, which is larger than 100 bytes.
		$import = Import::get_instance();
		$import->set_fields( TestServers::get_fields( $server ) );
		$result = @$import->add_url( TestServers::get_url( $server, '/example-red.jpg' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$import->set_fields( array() );

		$this->assertFalse( $result );
		$this->assertEquals( 0, $this->get_media_file_count() );
		$this->assertStringContainsString( 'example-red.jpg', $this->get_result_texts() );
	}
}
