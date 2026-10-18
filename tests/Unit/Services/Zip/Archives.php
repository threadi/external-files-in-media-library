<?php
/**
 * Tests for the archive handlers in ExternalFilesInMediaLibrary\Services\Zip.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services\Zip;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use PharData;
use ZipArchive;

/**
 * Object to test the archive handlers Zip, TarGzip and Gzip.
 */
class Archives extends externalFilesTests {
	/**
	 * The directory with the test archives.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// create a directory for the test archives.
		$this->directory = trailingslashit( get_temp_dir() ) . 'efml-archives-' . wp_generate_password( 8, false );
		wp_mkdir_p( $this->directory );

		// allow local files in this directory.
		add_filter( 'efml_file_base', array( $this, 'get_directory' ) );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_directory' ) );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		delete_option( 'eml_zip_import_limit' );
		delete_option( 'eml_zip_max_file_size' );
		remove_all_filters( 'efml_zip_max_file_size' );
		remove_filter( 'efml_get_mime_types', array( $this, 'allow_text' ) );
		parent::tear_down();
	}

	/**
	 * Return the directory with the test archives.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return $this->directory;
	}

	/**
	 * Create a ZIP with the given files.
	 *
	 * @param array<string,string> $files List of files with their contents.
	 *
	 * @return string The path to the ZIP.
	 */
	private function create_zip( array $files ): string {
		$path = $this->directory . '/test.zip';
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		foreach ( $files as $name => $content ) {
			$zip->addFromString( $name, $content );
		}
		$zip->close();
		return $path;
	}

	/**
	 * Create a .tar.gz with the given files.
	 *
	 * @param array<string,string> $files List of files with their contents.
	 *
	 * @return string The path to the archive.
	 */
	private function create_tar_gz( array $files ): string {
		$tar = new PharData( $this->directory . '/test.tar' );
		foreach ( $files as $name => $content ) {
			$tar->addFromString( $name, $content );
		}
		$tar->compress( \Phar::GZ );
		return $this->directory . '/test.tar.gz';
	}

	/**
	 * Return the test cases for the compatibility checks.
	 *
	 * @return array<string,array<int,string|bool>>
	 */
	public function provide_compatibility(): array {
		return array(
			'zip'                       => array( 'https://example.com/a.zip', true, false, false ),
			'file in zip'               => array( 'https://example.com/a.zip/b.pdf', true, false, false ),
			'tar.gz'                    => array( 'https://example.com/a.tar.gz', false, true, false ),
			'gz'                        => array( 'https://example.com/a.pdf.gz', false, false, true ),
			'local gz'                  => array( '/var/www/a.pdf.gz', false, false, true ),
			'zip on host with gzip'     => array( 'https://cdn.gzip.example.com/a.zip', true, false, false ),
			'pdf on host with zip'      => array( 'https://zip.example.com/a.pdf', false, false, false ),
			'pdf on host with tar.gz'   => array( 'https://example.tar.gz.example.com/a.pdf', false, false, false ),
		);
	}

	/**
	 * Test which archive handler is compatible with which URL.
	 *
	 * @dataProvider provide_compatibility
	 *
	 * @param string $url The URL.
	 * @param bool   $zip Expected result for ZIP.
	 * @param bool   $tar_gz Expected result for TAR.GZ.
	 * @param bool   $gzip Expected result for GZIP.
	 *
	 * @return void
	 */
	public function test_is_compatible( string $url, bool $zip, bool $tar_gz, bool $gzip ): void {
		$this->assertSame( $zip, \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance()->is_compatible( $url ), 'zip' );
		$this->assertSame( $tar_gz, \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance()->is_compatible( $url ), 'tar.gz' );
		$this->assertSame( $gzip, \ExternalFilesInMediaLibrary\Services\Zip\Gzip::get_instance()->is_compatible( $url ), 'gzip' );
	}

	/**
	 * Test if all files of a ZIP are returned and not allowed file types are ignored.
	 *
	 * @return void
	 */
	public function test_zip_files_are_listed(): void {
		$path = $this->create_zip(
			array(
				'a.pdf'    => '%PDF-1.4 a',
				'b.pdf'    => '%PDF-1.4 b',
				'evil.php' => '<?php echo 1;',
			)
		);

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$files = $obj->get_files_from_zip();

		$titles = wp_list_pluck( $files, 'title' );
		sort( $titles );
		$this->assertSame( array( 'a.pdf', 'b.pdf' ), $titles );

		// each tmp file must be located in the temp directory.
		foreach ( $files as $file ) {
			$this->assertStringStartsWith( untrailingslashit( get_temp_dir() ), $file['tmp-file'] );
		}
	}

	/**
	 * Test if files in subdirectories of a ZIP are found and extracted with their content.
	 *
	 * @return void
	 */
	public function test_zip_files_in_subdirectories(): void {
		$path = $this->create_zip(
			array(
				'a.pdf'              => '%PDF-1.4 a',
				'docs/b.pdf'         => '%PDF-1.4 b',
				'docs/more/c.pdf'    => '%PDF-1.4 c',
				'docs/more/evil.php' => '<?php echo 1;',
			)
		);

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$files = $obj->get_files_from_zip();

		// all allowed files are found, with their content.
		$contents = array();
		foreach ( $files as $file ) {
			$contents[ $file['title'] ] = file_get_contents( $file['tmp-file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		ksort( $contents );
		$this->assertSame(
			array(
				'a.pdf' => '%PDF-1.4 a',
				'b.pdf' => '%PDF-1.4 b',
				'c.pdf' => '%PDF-1.4 c',
			),
			$contents
		);
	}

	/**
	 * Test if a single file could be loaded from a ZIP, also from a subdirectory.
	 *
	 * @return void
	 */
	public function test_zip_single_file(): void {
		$path = $this->create_zip(
			array(
				'a.pdf'      => '%PDF-1.4 a',
				'docs/b.pdf' => '%PDF-1.4 b',
				'evil.php'   => '<?php echo 1;',
			)
		);

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path );

		// a file in the root of the ZIP.
		$file = @$obj->get_file_info_from_zip( 'file://' . $path . '/a.pdf' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( 'a.pdf', $file['title'] );
		$this->assertSame( '%PDF-1.4 a', file_get_contents( $file['tmp-file'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// a file in a subdirectory.
		$file = @$obj->get_file_info_from_zip( 'file://' . $path . '/docs/b.pdf' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( 'b.pdf', $file['title'] );
		$this->assertSame( '%PDF-1.4 b', file_get_contents( $file['tmp-file'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// a not existing file.
		$this->assertSame( array(), @$obj->get_file_info_from_zip( 'file://' . $path . '/not-existing.pdf' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// a not allowed file.
		$this->assertSame( array(), @$obj->get_file_info_from_zip( 'file://' . $path . '/evil.php' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Test if files in subdirectories of a .tar.gz are found and extracted with their content.
	 *
	 * @return void
	 */
	public function test_tar_gz_files_in_subdirectories(): void {
		$path = $this->create_tar_gz(
			array(
				'a.pdf'           => '%PDF-1.4 a',
				'docs/more/c.pdf' => '%PDF-1.4 c',
			)
		);

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$contents = array();
		$urls     = array();
		foreach ( $obj->get_files_from_zip() as $file ) {
			$contents[ $file['title'] ] = file_get_contents( $file['tmp-file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$urls[ $file['title'] ]     = $file['url'];
		}

		// the URL of a file in a subdirectory contains its path in the archive and can be used to load it again.
		$this->assertSame( 'file://' . $path . '/docs/more/c.pdf', $urls['c.pdf'] );
		$obj->set_zip_file( $urls['c.pdf'] );
		$single = $obj->get_files_from_zip();
		$this->assertCount( 1, $single );
		$this->assertSame( '%PDF-1.4 c', file_get_contents( $single[0]['tmp-file'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$obj->set_zip_file( 'file://' . $path . '/' );
		ksort( $contents );
		$this->assertSame(
			array(
				'a.pdf' => '%PDF-1.4 a',
				'c.pdf' => '%PDF-1.4 c',
			),
			$contents
		);
	}

	/**
	 * Test if a ZIP with path traversal in its entries does not write files outside the temp directory.
	 *
	 * @return void
	 */
	public function test_zip_with_path_traversal(): void {
		$path = $this->create_zip(
			array(
				'../../evil.pdf' => '%PDF-1.4 evil',
			)
		);

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		foreach ( $obj->get_files_from_zip() as $file ) {
			$this->assertEquals( 'evil.pdf', $file['title'] );
			$this->assertStringStartsWith( untrailingslashit( get_temp_dir() ), $file['tmp-file'] );
		}
		$this->assertFileDoesNotExist( dirname( $this->directory, 2 ) . '/evil.pdf' );
	}

	/**
	 * Test if the limit for loaded files in AJAX-requests is respected.
	 *
	 * @return void
	 */
	public function test_zip_import_limit_in_ajax(): void {
		$files = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$files[ 'file-limit-' . $i . '.pdf' ] = '%PDF-1.4 ' . $i;
		}
		$path = $this->create_zip( $files );

		update_option( 'eml_zip_import_limit', 2 );
		add_filter( 'wp_doing_ajax', '__return_true' );

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$results = $obj->get_files_from_zip();

		$this->assertArrayHasKey( 'load_more', $results );
		unset( $results['load_more'] );
		$this->assertCount( 2, $results );

		// a limit of 0 must still load one file per request (otherwise the dialog would request endlessly).
		update_option( 'eml_zip_import_limit', 0 );
		$results = $obj->get_files_from_zip();
		unset( $results['load_more'] );
		$this->assertCount( 1, $results );
	}

	/**
	 * Test if broken ZIP files do not result in an error.
	 *
	 * @return void
	 */
	public function test_broken_zip(): void {
		// create a valid ZIP and cut it.
		$path    = $this->create_zip( array( 'a.pdf' => str_repeat( '%PDF-1.4 a', 500 ) ) );
		$content = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		file_put_contents( $path, substr( $content, 0, (int) ( strlen( $content ) / 2 ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$this->assertSame( array(), $obj->get_files_from_zip() );
		$this->assertFalse( $obj->can_file_be_opened() );

		// a file which is not a ZIP.
		file_put_contents( $path, 'no zip' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertSame( array(), $obj->get_files_from_zip() );
	}

	/**
	 * Test if each file of a .tar.gz is returned with its own URL and not allowed file types are ignored.
	 *
	 * @return void
	 */
	public function test_tar_gz_files_are_listed(): void {
		$path = $this->create_tar_gz(
			array(
				'a.pdf'    => '%PDF-1.4 a',
				'b.pdf'    => '%PDF-1.4 b',
				'evil.php' => '<?php echo 1;',
			)
		);

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$files = $obj->get_files_from_zip();

		$titles = wp_list_pluck( $files, 'title' );
		sort( $titles );
		$this->assertSame( array( 'a.pdf', 'b.pdf' ), $titles );

		// each file must have its own URL.
		$urls = wp_list_pluck( $files, 'url' );
		$this->assertCount( 2, array_unique( $urls ) );
		$this->assertContains( 'file://' . $path . '/a.pdf', $urls );
	}

	/**
	 * Test if a single file could be loaded from a .tar.gz.
	 *
	 * @return void
	 */
	public function test_tar_gz_single_file(): void {
		$path = $this->create_tar_gz(
			array(
				'a.pdf'    => '%PDF-1.4 a',
				'evil.php' => '<?php echo 1;',
			)
		);

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance();

		// an allowed file.
		$obj->set_zip_file( 'file://' . $path . '/a.pdf' );
		$files = $obj->get_files_from_zip();
		$this->assertCount( 1, $files );
		$this->assertEquals( 'a.pdf', $files[0]['title'] );
		$this->assertEquals( '%PDF-1.4 a', file_get_contents( $files[0]['tmp-file'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// a not allowed file.
		$obj->set_zip_file( 'file://' . $path . '/evil.php' );
		$this->assertSame( array(), $obj->get_files_from_zip() );

		// a not existing file.
		$obj->set_zip_file( 'file://' . $path . '/not-existing.pdf' );
		$this->assertSame( array(), $obj->get_files_from_zip() );
	}

	/**
	 * Test if a broken .tar.gz does not result in an exception.
	 *
	 * @return void
	 */
	public function test_broken_tar_gz(): void {
		$path = $this->directory . '/broken.tar.gz';
		file_put_contents( $path, 'no archive' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$this->assertSame( array(), $obj->get_files_from_zip() );
		$this->assertFalse( $obj->can_file_be_opened() );
	}

	/**
	 * Test if reading a .gz file does not close output buffers it does not own.
	 *
	 * @return void
	 */
	public function test_gzip_keeps_output_buffers(): void {
		$path = $this->directory . '/example.pdf.gz';
		file_put_contents( $path, (string) gzencode( '%PDF-1.4 gzip' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$level = ob_get_level();

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Gzip::get_instance();
		$obj->set_zip_file( 'file://' . $path );
		$listing = $obj->get_directory_listing();
		$this->assertIsArray( $listing );
		$this->assertNotEmpty( $listing );

		$this->assertSame( $level, ob_get_level() );
	}

	/**
	 * Test if the .gz mime type is removed, if .gz files are not allowed, also if it is the first entry.
	 *
	 * @return void
	 */
	public function test_change_enabled_mime_types(): void {
		$obj = \ExternalFilesInMediaLibrary\Services\Zip::get_instance();

		update_option( 'eml_zip_allow_gz', 0 );
		$this->assertSame( array( 'image/png' ), array_values( $obj->change_enabled_mime_types( array( 'application/x-gzip', 'image/png' ) ) ) );
		$this->assertSame( array( 'image/png' ), array_values( $obj->change_enabled_mime_types( array( 'image/png', 'application/x-gzip' ) ) ) );
		$this->assertSame( array( 'image/png' ), array_values( $obj->change_enabled_mime_types( array( 'image/png' ) ) ) );

		update_option( 'eml_zip_allow_gz', 1 );
		$this->assertSame( array( 'application/x-gzip', 'image/png' ), $obj->change_enabled_mime_types( array( 'application/x-gzip', 'image/png' ) ) );
	}

	/**
	 * Use the given maximum size in bytes for files in archives.
	 *
	 * @param int $max_size The maximum size in bytes.
	 *
	 * @return void
	 */
	private function set_max_file_size( int $max_size ): void {
		add_filter(
			'efml_zip_max_file_size',
			static function () use ( $max_size ): int {
				return $max_size;
			}
		);
	}

	/**
	 * Test the maximum size: from the setting in MB or the maximum upload size of WordPress.
	 *
	 * @return void
	 */
	public function test_max_file_size(): void {
		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();

		// without setting the maximum upload size of WordPress is used.
		delete_option( 'eml_zip_max_file_size' );
		$this->assertSame( absint( wp_max_upload_size() ), $obj->get_max_file_size() );

		// with setting.
		update_option( 'eml_zip_max_file_size', 3 );
		$this->assertSame( 3 * MB_IN_BYTES, $obj->get_max_file_size() );
	}

	/**
	 * Test that files in a ZIP, which are larger than the maximum size, are not extracted.
	 *
	 * @return void
	 */
	public function test_zip_files_larger_than_max_size(): void {
		$path = $this->create_zip(
			array(
				'small.pdf' => '%PDF-1.4 small',
				'large.pdf' => '%PDF-1.4 ' . str_repeat( 'x', 1000 ),
			)
		);
		$this->set_max_file_size( 100 );

		// only the small file is extracted.
		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$this->assertSame( array( 'small.pdf' ), wp_list_pluck( @$obj->get_files_from_zip(), 'title' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// the large file can not be extracted as single file.
		$obj->set_zip_file( 'file://' . $path );
		$this->assertSame( array(), @$obj->get_file_info_from_zip( 'file://' . $path . '/large.pdf' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertNotEmpty( @$obj->get_file_info_from_zip( 'file://' . $path . '/small.pdf' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Test that files in a .tar.gz, which are larger than the maximum size, are not extracted.
	 *
	 * @return void
	 */
	public function test_tar_gz_files_larger_than_max_size(): void {
		$path = $this->create_tar_gz(
			array(
				'small.pdf' => '%PDF-1.4 small',
				'large.pdf' => '%PDF-1.4 ' . str_repeat( 'x', 1000 ),
			)
		);
		$this->set_max_file_size( 100 );

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance();
		$obj->set_zip_file( 'file://' . $path . '/' );
		$this->assertSame( array( 'small.pdf' ), wp_list_pluck( $obj->get_files_from_zip(), 'title' ) );

		// the large file can not be extracted as single file.
		$obj->set_zip_file( 'file://' . $path . '/large.pdf' );
		$this->assertSame( array(), $obj->get_files_from_zip() );
	}

	/**
	 * Test that a .gz file, which is larger than the maximum size after extraction, is not extracted.
	 *
	 * The format does not contain a reliable size, so the extracted data is counted.
	 *
	 * @return void
	 */
	public function test_gzip_larger_than_max_size(): void {
		// 100 KB of the same character result in a small .gz file.
		$path = $this->directory . '/large.pdf.gz';
		file_put_contents( $path, (string) gzencode( '%PDF-1.4 ' . str_repeat( 'x', 100 * KB_IN_BYTES ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertLessThan( 1000, filesize( $path ) );

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Gzip::get_instance();
		$obj->set_zip_file( 'file://' . $path );

		// with a lower maximum size it is not extracted.
		$this->set_max_file_size( 10 * KB_IN_BYTES );
		$this->assertSame( array(), @$obj->get_directory_listing() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// with a higher maximum size it is extracted.
		remove_all_filters( 'efml_zip_max_file_size' );
		$this->set_max_file_size( 200 * KB_IN_BYTES );
		$this->assertNotEmpty( @$obj->get_directory_listing() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Return the archive types for tests, which run for ZIP and .tar.gz.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_archive_types(): array {
		return array(
			'zip'    => array( 'zip' ),
			'tar.gz' => array( 'tar.gz' ),
		);
	}

	/**
	 * Create an archive of the given type and return its object for this archive.
	 *
	 * @param string               $type The type ("zip" or "tar.gz").
	 * @param array<string,string> $files List of files with their contents.
	 *
	 * @return array{0: \ExternalFilesInMediaLibrary\Services\Zip\Zip_Base, 1: string} The object and the URL of the archive.
	 */
	private function create_archive( string $type, array $files ): array {
		if ( 'zip' === $type ) {
			$url = 'file://' . $this->create_zip( $files );
			$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		} else {
			$url = 'file://' . $this->create_tar_gz( $files );
			$obj = \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance();
		}
		$obj->set_zip_file( $url );
		return array( $obj, $url );
	}

	/**
	 * Test the directory listing of an archive with files in the root, in directories and in subdirectories.
	 *
	 * @dataProvider provide_archive_types
	 *
	 * @param string $type The archive type.
	 *
	 * @return void
	 */
	public function test_directory_listing( string $type ): void {
		list( $obj, $url ) = $this->create_archive(
			$type,
			array(
				'a.pdf'               => '%PDF-1.4 a',
				'docs/b.pdf'          => '%PDF-1.4 bb',
				'docs/more/c.pdf'     => '%PDF-1.4 ccc',
				'docs/readme.unknown' => 'not allowed',
				'images/d.png'        => 'png',
				'evil.php'            => '<?php echo 1;',
			)
		);
		$listing = $obj->get_directory_listing();
		$this->assertTrue( $listing['completed'] );

		// the root contains its files and all its directories, files with not allowed types are missing.
		$this->assertSame( basename( $url ), $listing[ $url ]['title'] );
		$this->assertSame( array( 'a.pdf' ), array_column( $listing[ $url ]['files'], 'title' ) );
		$root_dirs = array_keys( $listing[ $url ]['dirs'] );
		sort( $root_dirs );
		$this->assertSame( array( $url . '/docs/', $url . '/images/' ), $root_dirs );
		$this->assertSame( 'docs', $listing[ $url ]['dirs'][ $url . '/docs/' ]['title'] );

		// each directory has its own entry with its files and subdirectories.
		$this->assertSame( array( 'b.pdf' ), array_column( $listing[ $url . '/docs/' ]['files'], 'title' ) );
		$this->assertSame( array( $url . '/docs/more/' ), array_keys( $listing[ $url . '/docs/' ]['dirs'] ) );
		$this->assertSame( array( 'c.pdf' ), array_column( $listing[ $url . '/docs/more/' ]['files'], 'title' ) );
		$this->assertSame( array(), $listing[ $url . '/docs/more/' ]['dirs'] );
		$this->assertSame( array( 'd.png' ), array_column( $listing[ $url . '/images/' ]['files'], 'title' ) );

		// the entry of a file contains its URL in the archive, size and type.
		$file = $listing[ $url . '/docs/' ]['files'][0];
		$this->assertSame( $url . '/docs/b.pdf', $file['file'] );
		$this->assertSame( strlen( '%PDF-1.4 bb' ), $file['filesize'] );
		$this->assertSame( 'application/pdf', $file['mime-type'] );
	}

	/**
	 * Test the directory listing of an archive with an explicit, empty directory entry (only possible in ZIP).
	 *
	 * @return void
	 */
	public function test_zip_directory_listing_with_empty_directory(): void {
		$path = $this->create_zip( array( 'a.pdf' => '%PDF-1.4 a' ) );
		$zip  = new ZipArchive();
		$zip->open( $path );
		$zip->addEmptyDir( 'empty' );
		$zip->close();

		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$obj->set_zip_file( 'file://' . $path );
		$listing = $obj->get_directory_listing();

		$this->assertSame( array( 'file://' . $path . '/empty/' ), array_keys( $listing[ 'file://' . $path ]['dirs'] ) );
		$this->assertSame( array(), $listing[ 'file://' . $path . '/empty/' ]['files'] );
	}

	/**
	 * Test the directory listing of a file, which is not a valid archive.
	 *
	 * @dataProvider provide_archive_types
	 *
	 * @param string $type The archive type.
	 *
	 * @return void
	 */
	public function test_directory_listing_of_broken_archive( string $type ): void {
		$path = $this->directory . '/broken.' . $type;
		file_put_contents( $path, 'this is not an archive' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$obj = 'zip' === $type ? \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance() : \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance();
		$obj->set_zip_file( 'file://' . $path );

		$this->assertSame( array(), @$obj->get_directory_listing() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( @$obj->can_file_be_opened() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Create a .gz file with a PDF and return its URL.
	 *
	 * @return string
	 */
	private function create_gz(): string {
		$path = $this->directory . '/a.pdf.gz';
		file_put_contents( $path, (string) gzencode( '%PDF-1.4 ' . str_repeat( 'gz', 500 ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return 'file://' . $path;
	}

	/**
	 * Test the directory listing of a .gz file: it contains the single file with its uncompressed size.
	 *
	 * @return void
	 */
	public function test_gzip_directory_listing(): void {
		$url = $this->create_gz();
		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Gzip::get_instance();
		$obj->set_zip_file( $url );

		$listing = $obj->get_directory_listing();
		$this->assertTrue( $listing['completed'] );
		$this->assertCount( 1, $listing[ $url ]['files'] );
		$file = $listing[ $url ]['files'][0];
		$this->assertSame( 'a.pdf', $file['title'] );
		$this->assertSame( $url . '/a.pdf', $file['file'] );
		$this->assertSame( 'application/pdf', $file['mime-type'] );
		$this->assertSame( strlen( '%PDF-1.4 ' . str_repeat( 'gz', 500 ) ), $file['filesize'] );
	}

	/**
	 * Test the extraction of the file of a .gz: for the import of the .gz and of the file from its listing.
	 *
	 * @return void
	 */
	public function test_gzip_extraction(): void {
		$url     = $this->create_gz();
		$content = '%PDF-1.4 ' . str_repeat( 'gz', 500 );
		$obj     = \ExternalFilesInMediaLibrary\Services\Zip\Gzip::get_instance();

		// the import of the .gz itself.
		$obj->set_zip_file( $url );
		$files = $obj->get_files_from_zip();
		$this->assertCount( 1, $files );
		$this->assertSame( 'a.pdf', $files[0]['title'] );
		$this->assertSame( $url, $files[0]['url'] );
		$this->assertSame( 'application/pdf', $files[0]['mime-type'] );
		$this->assertSame( strlen( $content ), $files[0]['filesize'] );
		$this->assertTrue( $files[0]['local'] );
		$this->assertStringEqualsFile( $files[0]['tmp-file'], $content );

		// the import of the file from the listing (its URL is the URL of the .gz with the file name).
		$obj->set_zip_file( $url . '/a.pdf' );
		$file = $obj->get_file_info_from_zip( $url . '/a.pdf' );
		$this->assertSame( 'a.pdf', $file['title'] );
		$this->assertSame( $url . '/a.pdf', $file['url'] );
		$this->assertSame( 'application/pdf', $file['mime-type'] );
		$this->assertSame( strlen( $content ), $file['filesize'] );
		$this->assertStringEqualsFile( $file['tmp-file'], $content );
	}

	/**
	 * Return the test cases for the check whether a URL is a file in an archive.
	 *
	 * @return array<string,array<int,string|bool>>
	 */
	public function provide_is_file_in_zip(): array {
		return array(
			'zip'            => array( 'zip', 'https://example.com/a.zip', false ),
			'file in zip'    => array( 'zip', 'https://example.com/a.zip/docs/b.pdf', true ),
			'tar.gz'         => array( 'tar.gz', 'https://example.com/a.tar.gz', false ),
			'file in tar.gz' => array( 'tar.gz', 'https://example.com/a.tar.gz/b.pdf', true ),
			'gz'             => array( 'gz', 'https://example.com/a.pdf.gz', false ),
			'file in gz'     => array( 'gz', 'https://example.com/a.pdf.gz/a.pdf', true ),
		);
	}

	/**
	 * Test the check whether a URL is a file in an archive (and not the archive itself).
	 *
	 * @dataProvider provide_is_file_in_zip
	 *
	 * @param string $type The archive type.
	 * @param string $url The URL.
	 * @param bool   $expected The expected result.
	 *
	 * @return void
	 */
	public function test_is_file_in_zip( string $type, string $url, bool $expected ): void {
		$objects = array(
			'zip'    => \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance(),
			'tar.gz' => \ExternalFilesInMediaLibrary\Services\Zip\TarGzip::get_instance(),
			'gz'     => \ExternalFilesInMediaLibrary\Services\Zip\Gzip::get_instance(),
		);
		$objects[ $type ]->set_zip_file( $url );
		$this->assertSame( $expected, $objects[ $type ]->is_file_in_zip() );
	}

	/**
	 * Allow text files.
	 *
	 * @param array<int,string> $mime_types List of mime types.
	 *
	 * @return array<int,string>
	 */
	public function allow_text( array $mime_types ): array {
		$mime_types[] = 'text/plain';
		return $mime_types;
	}

	/**
	 * Test that only files with a type allowed in the settings are listed and extracted (not every type known to WordPress).
	 *
	 * @dataProvider provide_archive_types
	 *
	 * @param string $type The archive type.
	 *
	 * @return void
	 */
	public function test_only_allowed_types( string $type ): void {
		list( $obj, $url ) = $this->create_archive(
			$type,
			array(
				'a.pdf'          => '%PDF-1.4 a',
				'notes.txt'      => 'text',
				'docs/notes.txt' => 'text',
			)
		);
		$this->assertNotContains( 'text/plain', \ExternalFilesInMediaLibrary\Plugin\Helper::get_allowed_mime_types() );

		// not allowed: the text files are not listed and not extracted.
		$listing = $obj->get_directory_listing();
		$this->assertSame( array( 'a.pdf' ), array_column( $listing[ $url ]['files'], 'title' ) );
		$this->assertArrayNotHasKey( $url . '/docs/', $listing );
		$obj->set_zip_file( $url . '/' );
		$this->assertSame( array( 'a.pdf' ), array_column( $obj->get_files_from_zip(), 'title' ) );
		$this->assertSame( array(), @$obj->get_file_info_from_zip( $url . '/notes.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// allowed: they are listed and extracted.
		add_filter( 'efml_get_mime_types', array( $this, 'allow_text' ) );
		$obj->set_zip_file( $url );
		$listing = $obj->get_directory_listing();
		$titles  = array_column( $listing[ $url ]['files'], 'title' );
		sort( $titles );
		$this->assertSame( array( 'a.pdf', 'notes.txt' ), $titles );
		$this->assertSame( array( 'notes.txt' ), array_column( $listing[ $url . '/docs/' ]['files'], 'title' ) );
		$obj->set_zip_file( $url . '/' );
		$this->assertCount( 3, $obj->get_files_from_zip() );
		$info = @$obj->get_file_info_from_zip( $url . '/notes.txt' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( 'notes.txt', isset( $info['title'] ) ? $info['title'] : $info[0]['title'] ); // .tar.gz returns a list with the file.
		remove_filter( 'efml_get_mime_types', array( $this, 'allow_text' ) );
	}

	/**
	 * Test that the file of a .gz archive is only listed and extracted, if its type is allowed in the settings.
	 *
	 * @return void
	 */
	public function test_gzip_only_allowed_type(): void {
		$path = $this->directory . '/notes.txt.gz';
		file_put_contents( $path, (string) gzencode( 'only text' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$obj = \ExternalFilesInMediaLibrary\Services\Zip\Gzip::get_instance();
		$obj->set_zip_file( 'file://' . $path );

		$this->assertSame( array(), $obj->get_directory_listing() );
		$this->assertSame( array(), $obj->get_files_from_zip() );

		add_filter( 'efml_get_mime_types', array( $this, 'allow_text' ) );
		$this->assertCount( 1, $obj->get_files_from_zip() );
		remove_filter( 'efml_get_mime_types', array( $this, 'allow_text' ) );
	}
}
