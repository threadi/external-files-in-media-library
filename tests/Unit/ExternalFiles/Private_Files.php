<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Private_Files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Private_Files.
 */
class Private_Files extends externalFilesTests {
	/**
	 * Prepare a file.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// add the test URL in the media library.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );

		// disable the proxy for PDF files.
		update_option( 'eml_pdf_mode', 'external' );
		update_option( 'eml_pdf_proxy', 0 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'efml_private_file_signed_url' );
		delete_option( 'eml_private_files_lifetime' );
		update_option( 'eml_pdf_proxy', 0 );
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
	 * Return a fixed signed URL.
	 *
	 * @return string
	 */
	public function get_fixed_signed_url(): string {
		return 'https://signed.example.com/example.pdf?signature=abc';
	}

	/**
	 * Test the marker for private files and the resulting requirement of the proxy.
	 *
	 * @return void
	 */
	public function test_private_marker(): void {
		$file_obj = $this->get_file_object();

		// a public HTTP file does not require the proxy.
		$this->assertFalse( $file_obj->is_private() );
		$this->assertFalse( $file_obj->is_proxy_required() );

		// a private file requires the proxy.
		$file_obj->set_private( true );
		$this->assertTrue( $file_obj->is_private() );
		$this->assertTrue( $file_obj->is_proxy_required() );

		// its URL is the proxy URL, although the proxy is disabled for PDF files.
		$this->assertEquals( 'http://example.org/?emlproxy=' . basename( self::get_test_file( 'pdf', 'http' ) ), $file_obj->get_url() );

		// the proxy settings of the file type are not affected by the requirement.
		$this->assertTrue( $file_obj->get_file_type_obj()->is_proxy_enabled() );
		$this->assertFalse( $file_obj->get_file_type_obj()->is_proxy_enabled_by_settings() );

		// remove the marker.
		$file_obj->set_private( false );
		$this->assertFalse( $file_obj->is_private() );
		$this->assertFalse( $file_obj->is_proxy_required() );
	}

	/**
	 * Test that the proxy does not deliver a file, if its delivery is not allowed.
	 *
	 * @return void
	 */
	public function test_proxy_prevents_not_allowed_delivery(): void {
		$file_obj = $this->get_file_object();
		$file_obj->set_private( true );

		// prevent the delivery.
		add_filter( 'efml_proxy_allow_delivery', '__return_false' );

		// request the file via the proxy.
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), basename( self::get_test_file( 'pdf', 'http' ) ) );
		$this->assertEquals( 'my-template.php', \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) );

		// the file has not been cached.
		$this->assertFalse( $file_obj->is_cached() );

		// cleanup.
		remove_filter( 'efml_proxy_allow_delivery', '__return_false' );
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), '' );
	}

	/**
	 * Test the sanitizing of the lifetime setting.
	 *
	 * @return void
	 */
	public function test_sanitize_lifetime(): void {
		$private_files = \ExternalFilesInMediaLibrary\ExternalFiles\Private_Files::get_instance();
		$this->assertEquals( 1, $private_files->sanitize_lifetime( 0 ) );
		$this->assertEquals( 30, $private_files->sanitize_lifetime( '30' ) );
		$this->assertEquals( 5040, $private_files->sanitize_lifetime( 99999 ) );
		$this->assertEquals( 60, $private_files->sanitize_lifetime( 'abc' ) );
	}

	/**
	 * Test the time windows for signed URLs.
	 *
	 * @return void
	 */
	public function test_get_time_window(): void {
		$private_files = \ExternalFilesInMediaLibrary\ExternalFiles\Private_Files::get_instance();

		// use the default of 60 minutes.
		$window = $private_files->get_time_window( 36125 );
		$this->assertEquals( 36000, $window['start'] );
		$this->assertEquals( 36000 + 7200, $window['expires'] );
		$this->assertEquals( 3475, $window['max_age'] );

		// the same window is used during its time.
		$this->assertEquals( $window, $private_files->get_time_window( 36125 ) );
		$this->assertEquals( 36000, $private_files->get_time_window( 39599 )['start'] );
		$this->assertEquals( 39600, $private_files->get_time_window( 39600 )['start'] );

		// use 10 minutes.
		update_option( 'eml_private_files_lifetime', 10 );
		$window = $private_files->get_time_window( 36125 );
		$this->assertEquals( 36000, $window['start'] );
		$this->assertEquals( 37200, $window['expires'] );
		$this->assertEquals( 475, $window['max_age'] );
	}

	/**
	 * Test the URL for a redirect to a private file.
	 *
	 * @return void
	 */
	public function test_get_redirect_url(): void {
		$private_files = \ExternalFilesInMediaLibrary\ExternalFiles\Private_Files::get_instance();
		$file_obj      = $this->get_file_object();

		// return a signed URL for each request.
		add_filter( 'efml_private_file_signed_url', array( $this, 'get_fixed_signed_url' ) );

		// no redirect for public files.
		$this->assertEquals( '', $private_files->get_redirect_url( $file_obj ) );

		// redirect for private files.
		$file_obj->set_private( true );
		$this->assertEquals( $this->get_fixed_signed_url(), $private_files->get_redirect_url( $file_obj ) );

		// no redirect for specific sizes of images.
		$this->assertEquals( '', $private_files->get_redirect_url( $file_obj, array( 150, 150 ) ) );

		// no redirect if the proxy is enabled for the file type.
		update_option( 'eml_pdf_proxy', 1 );
		$this->assertEquals( '', $private_files->get_redirect_url( $file_obj ) );
	}

	/**
	 * Test that no redirect is used, if the protocol could not sign URLs.
	 *
	 * @return void
	 */
	public function test_get_redirect_url_without_signing(): void {
		$file_obj = $this->get_file_object();
		$file_obj->set_private( true );

		// the HTTP protocol could not sign URLs, so the cache is used.
		$this->assertEquals( '', \ExternalFilesInMediaLibrary\ExternalFiles\Private_Files::get_instance()->get_redirect_url( $file_obj ) );
	}

	/**
	 * Test the info in the dialog of a file.
	 *
	 * @return void
	 */
	public function test_show_private_state_in_info_dialog(): void {
		$file_obj = $this->get_file_object();
		$dialog   = array( 'texts' => array() );

		// no info for public files.
		$this->assertEquals( $dialog, \ExternalFilesInMediaLibrary\ExternalFiles\Private_Files::get_instance()->show_private_state_in_info_dialog( $dialog, $file_obj ) );

		// info for private files.
		$file_obj->set_private( true );
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Private_Files::get_instance()->show_private_state_in_info_dialog( $dialog, $file_obj );
		$this->assertCount( 1, $result['texts'] );
	}

	/**
	 * Test whether an export to an external source is private.
	 *
	 * @return void
	 */
	public function test_is_private_export(): void {
		// create a term to use.
		$term = wp_insert_term( 'Private test source', 'category' );
		$this->assertIsArray( $term );
		$term_id = absint( $term['term_id'] );

		// create export objects with and without support.
		$supported     = new class() extends \ExternalFilesInMediaLibrary\ExternalFiles\Export_Base {
			/**
			 * Return true as private files are supported.
			 *
			 * @return bool
			 */
			public function supports_private_files(): bool {
				return true;
			}
		};
		$not_supported = new class() extends \ExternalFilesInMediaLibrary\ExternalFiles\Export_Base {};

		$export = \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance();

		// without the setting the export is not private.
		$this->assertFalse( $export->is_private_export( $term_id, $supported ) );

		// with the setting the export is private, if the service supports it.
		update_term_meta( $term_id, 'efml_export_private', 1 );
		$this->assertTrue( $export->is_private_export( $term_id, $supported ) );
		$this->assertFalse( $export->is_private_export( $term_id, $not_supported ) );
	}

	/**
	 * Test that the HTTP protocol does not sign URLs.
	 *
	 * @return void
	 */
	public function test_protocol_base_get_signed_url(): void {
		$protocol = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http( self::get_test_file( 'pdf', 'http' ) );
		$this->assertEquals( '', $protocol->get_signed_url( self::get_test_file( 'pdf', 'http' ), time(), time() + 60 ) );
		$this->assertEquals( '', $protocol->get_signed_url( '', time(), time() + 60 ) );
	}

	/**
	 * Test the temporary links of DropBox from the cache.
	 *
	 * @return void
	 */
	public function test_dropbox_signed_url_from_cache(): void {
		$url      = 'dropbox/folder/example.pdf';
		$protocol = new \ExternalFilesInMediaLibrary\Services\DropBox\Protocol( $url );
		$protocol->set_fields( array() );

		// public URLs and too long validity are not supported.
		$this->assertEquals( '', $protocol->get_signed_url( 'https://example.com/example.pdf', time(), time() + 60 ) );
		$this->assertEquals( '', $protocol->get_signed_url( $url, time(), time() + 5 * HOUR_IN_SECONDS ) );

		// use a cached link.
		set_transient(
			'efml_dropbox_link_' . md5( $url . (string) wp_json_encode( $protocol->get_fields() ) ),
			array(
				'link'        => 'https://dl.dropboxusercontent.com/example.pdf',
				'valid_until' => time() + HOUR_IN_SECONDS,
			),
			HOUR_IN_SECONDS
		);
		$this->assertEquals( 'https://dl.dropboxusercontent.com/example.pdf', $protocol->get_signed_url( $url, time(), time() + 600 ) );
	}
}
