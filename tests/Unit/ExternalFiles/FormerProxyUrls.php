<?php
/**
 * Tests for the redirect of proxy URLs of files, which are no external files anymore.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Real_Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Former_Proxy_Urls;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the redirect of proxy URLs of files, which are no external files anymore.
 */
class FormerProxyUrls extends externalFilesTests {

	/**
	 * Prepare each test: as administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		set_query_var( Proxy::get_instance()->get_slug(), '' );
		remove_all_filters( 'efml_former_proxy_url_redirect' );
		parent::tear_down();
	}

	/**
	 * Create a local image with sizes, which is used as external file with the given title.
	 *
	 * @param string $title The title (the name in the proxy).
	 *
	 * @return int
	 */
	private function create_external_image( string $title ): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-former-proxy-url.jpg';
		$image = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		wp_update_post(
			array(
				'ID'         => $attachment_id,
				'post_title' => $title,
			)
		);
		Files::get_instance()->get_file( $attachment_id )->set_url( 'https://example.com/' . $title );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		return $attachment_id;
	}

	/**
	 * Test that the proxy URL of a file redirects to its local URL after its real import.
	 *
	 * @return void
	 */
	public function test_redirect_after_real_import(): void {
		// import an external file.
		$this->assertTrue( Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$external_file_obj = Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );
		$attachment_id = $external_file_obj->get_id();
		$title         = $external_file_obj->get_title();

		// without real import nothing is saved.
		$this->assertEquals( 0, Former_Proxy_Urls::get_instance()->find( $title ) );

		// import it as real local file.
		$method = new \ReflectionMethod( Real_Import::class, 'import_local' );
		$this->assertTrue( $method->invoke( Real_Import::get_instance(), $external_file_obj ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// its former name in the proxy leads to its local URL.
		$this->assertEquals( $attachment_id, Former_Proxy_Urls::get_instance()->find( $title ) );
		$local_url = wp_get_attachment_url( $attachment_id );
		$this->assertIsString( $local_url );
		$this->assertStringContainsString( '/wp-content/uploads/', $local_url );
		$this->assertEquals( $local_url, Former_Proxy_Urls::get_instance()->get_redirect_url( $title ) );

		// the proxy redirects to it (the filter of wp_redirect() stops the request before the exit).
		set_query_var( Proxy::get_instance()->get_slug(), $title );
		$redirect = '';
		add_filter(
			'wp_redirect',
			static function ( string $location ) {
				throw new \RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		);
		try {
			Proxy::get_instance()->run( 'my-template.php' );
		} catch ( \RuntimeException $e ) {
			$redirect = $e->getMessage();
		}
		remove_all_filters( 'wp_redirect' );
		$this->assertEquals( $local_url, $redirect );
	}

	/**
	 * Test the redirect of sizes and former titles of an image, which is no external file anymore.
	 *
	 * @return void
	 */
	public function test_sizes_and_former_titles(): void {
		$attachment_id = $this->create_external_image( 'efml-former-image.jpg' );
		add_post_meta( $attachment_id, Files::FORMER_TITLES_META, 'efml-former-image-before.jpg' );

		// remove its URL (e.g. via the restore of an exported file).
		Files::get_instance()->get_file( $attachment_id )->remove_url();

		// its name and its former name lead to the local file.
		$local_url = wp_get_attachment_url( $attachment_id );
		$this->assertEquals( $local_url, Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-former-image.jpg' ) );
		$this->assertEquals( $local_url, Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-former-image-before.jpg' ) );

		// a size leads to the local file of this size.
		$thumbnail = wp_get_attachment_image_src( $attachment_id, array( 150, 150 ) );
		$this->assertIsArray( $thumbnail );
		$this->assertStringEndsWith( '-150x150.jpg', $thumbnail[0] );
		$this->assertEquals( $thumbnail[0], Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-former-image-150x150.jpg' ) );

		// unknown names lead nowhere.
		$this->assertEquals( '', Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-unknown-image.jpg' ) );
		$this->assertEquals( '', Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-unknown-image-150x150.jpg' ) );

		// the redirect could be prevented.
		add_filter( 'efml_former_proxy_url_redirect', '__return_empty_string' );
		$this->assertEquals( '', Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-former-image.jpg' ) );
		remove_filter( 'efml_former_proxy_url_redirect', '__return_empty_string' );

		// files in trash are not used.
		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $attachment_id ) );
		$this->assertEquals( '', Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-former-image.jpg' ) );
	}

	/**
	 * Test that a file, which is external again under the same name, is not redirected (the proxy delivers it).
	 *
	 * @return void
	 */
	public function test_no_redirect_for_external_file(): void {
		$attachment_id = $this->create_external_image( 'efml-former-again.jpg' );
		Files::get_instance()->get_file( $attachment_id )->remove_url();
		$this->assertNotEquals( '', Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-former-again.jpg' ) );

		// it is an external file again.
		Files::get_instance()->get_file( $attachment_id )->set_url( 'https://example.com/efml-former-again.jpg' );
		$this->assertEquals( '', Former_Proxy_Urls::get_instance()->get_redirect_url( 'efml-former-again.jpg' ) );
	}

	/**
	 * Test that nothing is saved for deleted files and that the uninstallation removes all names.
	 *
	 * @return void
	 */
	public function test_delete_and_uninstall(): void {
		global $wpdb;

		// a deleted external file leaves nothing.
		$attachment_id = $this->create_external_image( 'efml-former-deleted.jpg' );
		wp_delete_attachment( $attachment_id, true );
		$this->assertEquals( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `post_id` = %d', $wpdb->postmeta, $attachment_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// the uninstallation removes all names.
		$attachment_id = $this->create_external_image( 'efml-former-uninstall.jpg' );
		Files::get_instance()->get_file( $attachment_id )->remove_url();
		$this->assertEquals( $attachment_id, Former_Proxy_Urls::get_instance()->find( 'efml-former-uninstall.jpg' ) );
		Former_Proxy_Urls::get_instance()->uninstall();
		wp_cache_flush();
		$this->assertEquals( 0, Former_Proxy_Urls::get_instance()->find( 'efml-former-uninstall.jpg' ) );
	}
}
