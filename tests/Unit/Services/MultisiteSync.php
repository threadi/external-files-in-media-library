<?php
/**
 * Tests for the synchronization of another website in a multisite.
 *
 * Runs only in a multisite: WP_MULTISITE=1 vendor/bin/phpunit --filter MultisiteSync
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Services\Multisite;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_HTTP_Requests_Response;

/**
 * Object to test the synchronization of the media library of another website in the multisite.
 */
class MultisiteSync extends externalFilesTests {
	/**
	 * The ID of the other website.
	 *
	 * @var int
	 */
	private int $blog_id = 0;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Runs only in a multisite (WP_MULTISITE=1).' );
		}

		$this->blog_id = absint( self::factory()->blog->create() );
		add_filter( 'pre_http_request', array( $this, 'answer_requests_to_blog' ), 5, 3 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'answer_requests_to_blog' ), 5 );
		remove_filter( 'wp_doing_cron', '__return_true' );
		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		parent::tear_down();
	}

	/**
	 * Answer the requests to the other website with its local files, as the test website is not reachable via HTTP.
	 *
	 * @param false|array<string,mixed>|\WP_Error $response The response.
	 * @param array<string,mixed>                 $parsed_args The arguments.
	 * @param string                              $url The URL.
	 *
	 * @return false|array<string,mixed>|\WP_Error
	 */
	public function answer_requests_to_blog( false|array|\WP_Error $response, array $parsed_args, string $url ): false|array|\WP_Error {
		$blog_url = get_blogaddress_by_id( $this->blog_id );
		if ( ! str_starts_with( $url, $blog_url ) ) {
			return $response;
		}

		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = 200;
		$requests_response->url         = $url;

		// a file of the website: return its content.
		$path = str_replace( $blog_url, ABSPATH, $url );
		if ( is_file( $path ) ) {
			$requests_response->headers = new \WpOrg\Requests\Response\Headers( array( 'content-type' => (string) wp_check_filetype( $path )['type'] ) );
			if ( ! empty( $parsed_args['filename'] ) ) {
				copy( $path, $parsed_args['filename'] );
			} else {
				$requests_response->body = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		} else {
			// the website itself.
			$requests_response->headers = new \WpOrg\Requests\Response\Headers( array( 'content-type' => 'text/html; charset=utf-8' ) );
			$requests_response->body    = '<!DOCTYPE html><html><body>Website</body></html>';
		}

		$http_response = new WP_HTTP_Requests_Response( $requests_response, isset( $parsed_args['filename'] ) ? $parsed_args['filename'] : null );
		return array_merge( $http_response->to_array(), array( 'http_response' => $http_response ) );
	}

	/**
	 * Add an image to the media library of the other website and return its title.
	 *
	 * @param string $file The test file in tests/Data/ftp.
	 *
	 * @return int
	 */
	private function add_image_to_blog( string $file ): int {
		switch_to_blog( $this->blog_id );
		$tmp = trailingslashit( get_temp_dir() ) . basename( $file );
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $file, $tmp );
		$attachment_id = absint( @self::factory()->attachment->create_upload_object( $tmp ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		restore_current_blog();
		return $attachment_id;
	}

	/**
	 * Return the URLs of the files assigned to the given external source.
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return array<int,string>
	 */
	private function get_files_of_source( int $term_id ): array {
		$ids = get_posts(
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
			$external_file_obj = Files::get_instance()->get_file( absint( $id ) );
			$this->assertInstanceOf( File::class, $external_file_obj );
			// without the number, which WordPress adds to file names, which already exist in the uploads directory.
			$urls[] = (string) preg_replace( '/-\d+(\.\w+)$/', '$1', basename( $external_file_obj->get_url( true ) ) );
		}
		sort( $urls );
		return $urls;
	}

	/**
	 * Test the synchronization of another website via cron, without a user.
	 *
	 * @return void
	 */
	public function test_synchronization_via_cron(): void {
		$this->add_image_to_blog( 'example-red.jpg' );
		$blue_id = $this->add_image_to_blog( 'example-blue.png' );
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );

		// run like a scheduled synchronization: via cron, without a user.
		wp_set_current_user( 0 );
		add_filter( 'wp_doing_cron', '__return_true' );
		Multisite::get_instance()->init();

		// the external source is the other website.
		$url     = get_blogaddress_by_id( $this->blog_id );
		$term_id = Taxonomy::get_instance()->add( 'multisite', $url, array( 'website' => array( 'value' => $this->blog_id ) ) );
		$this->assertGreaterThan( 0, $term_id );
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// the files of the other website are imported.
		$this->assertSame( array( 'example-blue.png', 'example-red.jpg' ), $this->get_files_of_source( $term_id ) );

		// a file deleted on the other website is deleted with the next synchronization.
		switch_to_blog( $this->blog_id );
		wp_delete_attachment( $blue_id, true );
		restore_current_blog();
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( array( 'example-red.jpg' ), $this->get_files_of_source( $term_id ) );
	}
}
