<?php
/**
 * Tests for local paths of files in archives.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Protocols;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Services\Zip;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionMethod;
use ZipArchive;

/**
 * Object to test the check of local paths of files in archives, e.g. "/path/archive.zip/images/a.jpg".
 *
 * Only the archive is a file in the file system and must be in the allowed base directory.
 * The rest of the path is only a name in the archive.
 */
class FileArchives extends externalFilesTests {
	/**
	 * The allowed base directory for local files.
	 *
	 * @var string
	 */
	private string $base = '';

	/**
	 * A directory outside the base directory.
	 *
	 * @var string
	 */
	private string $outside = '';

	/**
	 * Prepare each test: an archive in the base directory and one outside of it.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$suffix        = wp_generate_password( 8, false, false );
		$this->base    = (string) realpath( get_temp_dir() ) . '/efml-base-' . $suffix;
		$this->outside = (string) realpath( get_temp_dir() ) . '/efml-outside-' . $suffix;
		mkdir( $this->base ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		mkdir( $this->outside ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		$this->create_zip( $this->base . '/archive.zip' );
		$this->create_zip( $this->outside . '/archive.zip' );

		add_filter( 'efml_file_base', array( $this, 'get_base' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_base' ) );
		remove_filter( 'wp_doing_cron', '__return_true' );
		foreach ( array( $this->base, $this->outside ) as $directory ) {
			foreach ( (array) glob( $directory . '/{,.}*', GLOB_BRACE ) as $file ) {
				if ( is_link( (string) $file ) || is_file( (string) $file ) ) {
					unlink( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				} elseif ( is_dir( (string) $file ) && ! in_array( basename( (string) $file ), array( '.', '..' ), true ) ) {
					rmdir( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				}
			}
			rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		parent::tear_down();
	}

	/**
	 * Return the allowed base directory.
	 *
	 * @return string
	 */
	public function get_base(): string {
		return $this->base;
	}

	/**
	 * Create a ZIP with two images, one in a subdirectory.
	 *
	 * @param string $path The path of the ZIP.
	 *
	 * @return void
	 */
	private function create_zip( string $path ): void {
		$zip = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE );
		$zip->addFile( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg', 'example-red.jpg' );
		$zip->addFile( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-blue.png', 'images/example-blue.png' );
		$zip->close();
	}

	/**
	 * Return the checked local path for the given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	private function sanitize( string $url ): string {
		$method = new ReflectionMethod( File::class, 'sanitize_local_path' );
		return (string) $method->invoke( new File( $url ), $url );
	}

	/**
	 * Test the paths of files in an archive in the allowed base directory.
	 *
	 * @return void
	 */
	public function test_archive_in_base_directory(): void {
		$this->assertSame( 'file://' . $this->base . '/archive.zip/', $this->sanitize( 'file://' . $this->base . '/archive.zip/' ) );
		$this->assertSame( 'file://' . $this->base . '/archive.zip/images/example-blue.png', $this->sanitize( 'file://' . $this->base . '/archive.zip/images/example-blue.png' ) );

		// the path of the archive is resolved, also with ".." in it, if it stays in the base directory.
		mkdir( $this->base . '/sub' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		$this->assertSame( 'file://' . $this->base . '/archive.zip/example-red.jpg', $this->sanitize( 'file://' . $this->base . '/sub/../archive.zip/example-red.jpg' ) );
	}

	/**
	 * Test that archives outside the allowed base directory are not allowed, also via ".." or a symbolic link.
	 *
	 * @return void
	 */
	public function test_archive_outside_base_directory(): void {
		// directly.
		$this->assertSame( '', $this->sanitize( 'file://' . $this->outside . '/archive.zip/' ) );
		$this->assertSame( '', $this->sanitize( 'file://' . $this->outside . '/archive.zip/example-red.jpg' ) );

		// via "..".
		$this->assertSame( '', $this->sanitize( 'file://' . $this->base . '/../' . basename( $this->outside ) . '/archive.zip/example-red.jpg' ) );

		// via a symbolic link in the base directory.
		symlink( $this->outside . '/archive.zip', $this->base . '/link.zip' );
		$this->assertSame( '', $this->sanitize( 'file://' . $this->base . '/link.zip/example-red.jpg' ) );
	}

	/**
	 * Test manipulated paths in an archive.
	 *
	 * @return void
	 */
	public function test_manipulated_paths(): void {
		// a directory with the name of an archive is not an archive: the rest would be a real path then.
		mkdir( $this->base . '/directory.zip' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		$this->assertSame( '', $this->sanitize( 'file://' . $this->base . '/directory.zip/../../' . basename( $this->outside ) . '/archive.zip' ) );

		// a null byte does not reach the result (wp_parse_url() replaces it).
		$this->assertStringNotContainsString( "\0", $this->sanitize( 'file://' . $this->base . "/archive.zip/example-red.jpg\0.php" ) );

		// ".." after the archive stays a name in the archive: the archive does not contain such a file.
		$url = $this->sanitize( 'file://' . $this->base . '/archive.zip/../../' . basename( $this->outside ) . '/archive.zip/example-red.jpg' );
		$zip = \ExternalFilesInMediaLibrary\Services\Zip\Zip::get_instance();
		$zip->set_zip_file( $url );
		$this->assertSame( array(), @$zip->get_file_info_from_zip( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// it is also not a path in the file system.
		$this->assertFileDoesNotExist( substr( $url, strlen( 'file://' ) ) );
	}

	/**
	 * Test that paths without archive are checked like before.
	 *
	 * @return void
	 */
	public function test_paths_without_archive(): void {
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg', $this->base . '/example-red.jpg' );
		$this->assertSame( 'file://' . $this->base . '/example-red.jpg', $this->sanitize( 'file://' . $this->base . '/example-red.jpg' ) );
		$this->assertSame( 'file://' . $this->base . '/', $this->sanitize( 'file://' . $this->base . '/' ) );
		$this->assertSame( '', $this->sanitize( 'file://' . $this->base . '/not-existing.jpg' ) );
		$this->assertSame( '', $this->sanitize( 'file://' . $this->outside . '/archive.zip' ) );
	}

	/**
	 * Synchronize a local ZIP as external source via cron and return the URLs of its imported files.
	 *
	 * @param string $url The URL of the ZIP.
	 *
	 * @return array<int,string>
	 */
	private function sync( string $url ): array {
		wp_set_current_user( 0 );
		add_filter( 'wp_doing_cron', '__return_true' );
		Zip::get_instance()->init();

		$term_id = Taxonomy::get_instance()->add( 'zip', $url, array() );
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$ids  = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => -1,
				'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Taxonomy::get_instance()->get_name(),
						'terms'    => $term_id,
					),
				),
			)
		);
		$urls = array();
		foreach ( $ids as $id ) {
			$urls[] = Files::get_instance()->get_file( absint( $id ) )->get_url( true );
		}
		sort( $urls );
		return $urls;
	}

	/**
	 * Test the import of the files of a local ZIP in the allowed base directory.
	 *
	 * @return void
	 */
	public function test_import_from_local_archive(): void {
		$url = 'file://' . $this->base . '/archive.zip/';
		$this->assertSame( array( $url . 'example-red.jpg', $url . 'images/example-blue.png' ), $this->sync( $url ) );
	}

	/**
	 * Test that nothing is imported from a local ZIP outside the allowed base directory.
	 *
	 * @return void
	 */
	public function test_no_import_from_archive_outside_base_directory(): void {
		$this->assertSame( array(), $this->sync( 'file://' . $this->outside . '/archive.zip/' ) );
		$this->assertSame( array(), $this->sync( 'file://' . $this->base . '/../' . basename( $this->outside ) . '/archive.zip/' ) );
	}
}
