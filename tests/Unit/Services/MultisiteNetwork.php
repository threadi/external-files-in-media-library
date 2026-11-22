<?php
/**
 * Tests for the central media library of a multisite network.
 *
 * Runs only in a multisite: WP_MULTISITE=1 vendor/bin/phpunit --filter MultisiteNetwork
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Network\Addresses;
use ExternalFilesInMediaLibrary\Plugin\Network\Api;
use ExternalFilesInMediaLibrary\Plugin\Network\Digest;
use ExternalFilesInMediaLibrary\Plugin\Network\Duplicates;
use ExternalFilesInMediaLibrary\Plugin\Network\Existing;
use ExternalFilesInMediaLibrary\Plugin\Network\Move;
use ExternalFilesInMediaLibrary\Plugin\Network\Unused;
use ExternalFilesInMediaLibrary\Plugin\Network\Media_Library;
use ExternalFilesInMediaLibrary\Plugin\Network\Membership;
use ExternalFilesInMediaLibrary\Plugin\Network\Overview;
use ExternalFilesInMediaLibrary\Plugin\Network\Quota;
use ExternalFilesInMediaLibrary\Plugin\Network\Upload;
use ExternalFilesInMediaLibrary\Plugin\Network\Metadata;
use ExternalFilesInMediaLibrary\Plugin\Network\Propagation;
use ExternalFilesInMediaLibrary\Plugin\Network\Settings;
use ExternalFilesInMediaLibrary\Plugin\Network\Usage;
use ExternalFilesInMediaLibrary\Services\Multisite;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\SynchronizationTrait;
use WP_HTTP_Requests_Response;

/**
 * Object to test the central media library of a multisite network.
 */
class MultisiteNetwork extends externalFilesTests {
	use SynchronizationTrait;

	/**
	 * The ID of the central media library.
	 *
	 * @var int
	 */
	private int $central = 0;

	/**
	 * The ID of the first website, which uses the central media library.
	 *
	 * @var int
	 */
	private int $site_a = 0;

	/**
	 * The ID of the second website, which uses the central media library.
	 *
	 * @var int
	 */
	private int $site_b = 0;

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

		// run as super administrator, like in the media library.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		// create the websites.
		$this->central = get_current_blog_id();
		$this->site_a  = absint( self::factory()->blog->create() );
		$this->site_b  = absint( self::factory()->blog->create() );

		// answer the requests to the websites with their local files.
		add_filter( 'pre_http_request', array( $this, 'answer_requests_to_blogs' ), 5, 3 );

		// register the hooks of the service like a run via cron does it.
		add_filter( 'wp_doing_cron', '__return_true' );
		Multisite::get_instance()->init();
		remove_filter( 'wp_doing_cron', '__return_true' );

		// install the tables of the plugin in the websites, like the activation does it.
		foreach ( array( $this->site_a, $this->site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->install();
			\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->install();
			\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->install();
			\ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->save_missing_defaults();
			restore_current_blog();
		}

		// use the central media library.
		Media_Library::get_instance()->reset_sites();
		Settings::get_instance()->apply( $this->central, 0 );

		// use the synchronization with the settings of a new installation in all websites and its hooks.
		foreach ( array( $this->site_a, $this->site_b ) as $blog_id ) {
			switch_to_blog( $blog_id );
			update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
			update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
			restore_current_blog();
		}
		$this->enable_synchronization();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// pass the remaining changes, so they are not used in the next test.
		Propagation::get_instance()->process();

		remove_filter( 'pre_http_request', array( $this, 'answer_requests_to_blogs' ), 5 );
		remove_filter( 'wp_doing_cron', '__return_true' );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		unset( $_REQUEST['action'] );
		delete_site_option( 'efml_media_library_site_id' );
		delete_site_option( Media_Library::OPTION_RULES );
		delete_site_option( Media_Library::OPTION_GROUPS );
		delete_site_option( Media_Library::OPTION_SETTINGS );
		delete_site_option( 'upload_space_check_disabled' );
		delete_site_option( Move::OPTION );
		Media_Library::get_instance()->reset_sites();
		parent::tear_down();
	}

	/**
	 * Answer the requests to the websites with their local files, as the test websites are not reachable via HTTP.
	 *
	 * @param false|array<string,mixed>|\WP_Error $response    The response.
	 * @param array<string,mixed>                 $parsed_args The arguments.
	 * @param string                              $url         The URL.
	 *
	 * @return false|array<string,mixed>|\WP_Error
	 */
	public function answer_requests_to_blogs( false|array|\WP_Error $response, array $parsed_args, string $url ): false|array|\WP_Error {
		// get the website with the longest address matching the URL.
		$blog_url = '';
		foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
			$address = (string) get_blogaddress_by_id( absint( $site->blog_id ) );
			if ( str_starts_with( $url, $address ) && strlen( $address ) > strlen( $blog_url ) ) {
				$blog_url = $address;
			}
		}

		// bail if it is not a website of the network.
		if ( empty( $blog_url ) ) {
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
	 * Add an image to the media library of the given website and return its attachment ID.
	 *
	 * @param int    $blog_id The website.
	 * @param string $file    The test file in tests/Data/ftp.
	 *
	 * @return int
	 */
	private function add_image( int $blog_id, string $file ): int {
		switch_to_blog( $blog_id );
		$tmp = trailingslashit( get_temp_dir() ) . basename( $file );
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $file, $tmp );
		$attachment_id = absint( @self::factory()->attachment->create_upload_object( $tmp ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		restore_current_blog();
		return $attachment_id;
	}

	/**
	 * Run the synchronization of the central media library in the given website, like a scheduled one.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return void
	 */
	private function sync( int $blog_id ): void {
		switch_to_blog( $blog_id );
		$term_id = Media_Library::get_instance()->get_central_term_id();
		$this->assertGreaterThan( 0, $term_id );
		$this->run_sync( 'cron', (string) get_blogaddress_by_id( $this->central ), $term_id );
		restore_current_blog();
	}

	/**
	 * Return the copies of the given file of the central media library in the given website.
	 *
	 * @param int $blog_id    The website.
	 * @param int $central_id The attachment ID in the central media library.
	 *
	 * @return array<int,int>
	 */
	private function get_copies( int $blog_id, int $central_id ): array {
		switch_to_blog( $this->central );
		$urls = array( Media_Library::get_instance()->get_file_url( $central_id ) );
		restore_current_blog();

		switch_to_blog( $blog_id );
		$copies = array_filter(
			Media_Library::get_instance()->find_copies( $central_id, $urls, $blog_id ),
			static fn( int $id ): bool => 'attachment' === get_post_type( $id )
		);
		restore_current_blog();
		return array_values( $copies );
	}

	/**
	 * Export an upload of the given website to the central media library and return the ID of the central file.
	 *
	 * @param int $blog_id       The website.
	 * @param int $attachment_id The attachment in this website.
	 *
	 * @return int
	 */
	private function export( int $blog_id, int $attachment_id ): int {
		switch_to_blog( $blog_id );
		$this->assertTrue( @Export::get_instance()->export_file( $attachment_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$central_id = absint( get_post_meta( $attachment_id, 'efml_multisite_attachment_id', true ) );
		restore_current_blog();
		$this->assertGreaterThan( 0, $central_id );
		return $central_id;
	}

	/**
	 * Delete an attachment like a user in the media library.
	 *
	 * @param int $blog_id       The website.
	 * @param int $attachment_id The attachment.
	 *
	 * @return mixed
	 */
	private function delete_as_user( int $blog_id, int $attachment_id ): mixed {
		switch_to_blog( $blog_id );
		$result = @wp_delete_attachment( $attachment_id, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		restore_current_blog();
		return $result;
	}

	/**
	 * Test that a deleted file of the central media library is deleted in all websites.
	 *
	 * @return void
	 */
	public function test_deletion_in_central_deletes_copies(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		$this->sync( $this->site_b );
		$this->assertCount( 1, $this->get_copies( $this->site_a, $central_id ) );
		$this->assertCount( 1, $this->get_copies( $this->site_b, $central_id ) );

		// the deletion is passed to the websites at the end of the request.
		$this->delete_as_user( $this->central, $central_id );
		Propagation::get_instance()->process();
		$this->assertCount( 0, $this->get_copies( $this->site_a, $central_id ) );
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test that the origin of an upload is saved in the central media library, and that its deletion in the central
	 * media library deletes it in the website, from which it comes.
	 *
	 * @return void
	 */
	public function test_deletion_in_central_deletes_upload_of_origin(): void {
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$central_id = $this->export( $this->site_a, $upload_id );

		// the origin is saved.
		switch_to_blog( $this->central );
		$this->assertSame( $this->site_a, Media_Library::get_instance()->get_origin_blog( $central_id ) );
		$this->assertSame( $upload_id, absint( get_post_meta( $central_id, Media_Library::META_ORIGIN_ATTACHMENT, true ) ) );
		restore_current_blog();

		// the other website gets the file via synchronization.
		$this->sync( $this->site_b );
		$this->assertCount( 1, $this->get_copies( $this->site_b, $central_id ) );

		// the deletion in the central media library deletes it in both websites.
		$this->delete_as_user( $this->central, $central_id );
		Propagation::get_instance()->process();
		switch_to_blog( $this->site_a );
		$this->assertNull( get_post( $upload_id ) );
		restore_current_blog();
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test that the synchronization deletes copies of deleted files also if the deletion has not been passed (e.g.
	 * for the own upload, which the synchronization itself does not delete, and if the central media library is
	 * empty, which stops the deletion of the synchronization).
	 *
	 * @return void
	 */
	public function test_synchronization_deletes_files_missing_in_central(): void {
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$central_id = $this->export( $this->site_a, $upload_id );
		$this->sync( $this->site_b );
		$this->assertCount( 1, $this->get_copies( $this->site_b, $central_id ) );

		// delete the file in the central media library without passing it to the websites.
		remove_action( 'delete_attachment', array( Propagation::get_instance(), 'add_deletion' ), 20 );
		$this->delete_as_user( $this->central, $central_id );
		add_action( 'delete_attachment', array( Propagation::get_instance(), 'add_deletion' ), 20 );

		// the synchronization deletes the own upload and the copy, although the central media library is empty now.
		$this->sync( $this->site_a );
		$this->sync( $this->site_b );
		switch_to_blog( $this->site_a );
		$this->assertNull( get_post( $upload_id ) );
		restore_current_blog();
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test the default right: a website could delete its own uploads (in the whole network), but not other files.
	 *
	 * @return void
	 */
	public function test_right_own(): void {
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$central_id = $this->export( $this->site_a, $upload_id );
		$this->sync( $this->site_b );
		$copies = $this->get_copies( $this->site_b, $central_id );
		$this->assertCount( 1, $copies );

		// the other website could not delete the file.
		$this->assertFalse( $this->delete_as_user( $this->site_b, $copies[0] ) );

		// the website, from which it comes, could delete it: it is deleted in the central media library and the other website.
		$this->assertInstanceOf( \WP_Post::class, $this->delete_as_user( $this->site_a, $upload_id ) );
		Propagation::get_instance()->process();
		switch_to_blog( $this->central );
		$this->assertNull( get_post( $central_id ) );
		restore_current_blog();
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test the right "none": a website could not delete its own uploads.
	 *
	 * @return void
	 */
	public function test_right_none(): void {
		Media_Library::get_instance()->set_rules( 'own', 'all', array( $this->site_a => array( 'delete' => 'none' ) ) );
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$central_id = $this->export( $this->site_a, $upload_id );

		$this->assertFalse( $this->delete_as_user( $this->site_a, $upload_id ) );
		switch_to_blog( $this->central );
		$this->assertInstanceOf( \WP_Post::class, get_post( $central_id ) );
		restore_current_blog();

		// the deletion of the action is not shown.
		switch_to_blog( $this->site_a );
		$this->assertTrue( 'attachment' === get_post_type( $upload_id ) );
		$actions = apply_filters( 'media_row_actions', array( 'delete' => '<a href="#">Delete</a>' ), get_post( $upload_id ), false );
		$this->assertArrayNotHasKey( 'delete', $actions );
		restore_current_blog();
	}

	/**
	 * Test the right "all": a website could delete every file, which deletes it in the whole network.
	 *
	 * @return void
	 */
	public function test_right_all(): void {
		Media_Library::get_instance()->set_rules( 'own', 'all', array( $this->site_b => array( 'delete' => 'all' ) ) );
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		$this->sync( $this->site_b );
		$copies = $this->get_copies( $this->site_b, $central_id );
		$this->assertCount( 1, $copies );

		// the deletion is marked as deletion in the whole network for the confirmation.
		switch_to_blog( $this->site_b );
		$actions = apply_filters( 'media_row_actions', array( 'delete' => '<a href="#">Delete</a>' ), get_post( $copies[0] ), false );
		$this->assertStringContainsString( 'data-efml-network-delete', $actions['delete'] );
		restore_current_blog();

		// the deletion deletes the file in the central media library and in the other website.
		$this->assertInstanceOf( \WP_Post::class, $this->delete_as_user( $this->site_b, $copies[0] ) );
		Propagation::get_instance()->process();
		switch_to_blog( $this->central );
		$this->assertNull( get_post( $central_id ) );
		restore_current_blog();
		$this->assertCount( 0, $this->get_copies( $this->site_a, $central_id ) );
	}

	/**
	 * Test that a website loses a file, which it could not use anymore, also if it uses it.
	 *
	 * @return void
	 */
	public function test_visibility_except(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		$this->sync( $this->site_b );
		$copies = $this->get_copies( $this->site_b, $central_id );

		// the file is used in the website.
		switch_to_blog( $this->site_b );
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $copies[0] );
		restore_current_blog();

		// exclude the website.
		switch_to_blog( $this->central );
		Media_Library::get_instance()->set_visibility( $central_id, 'except', array( $this->site_b ) );
		restore_current_blog();
		Propagation::get_instance()->process();
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
		$this->assertCount( 1, $this->get_copies( $this->site_a, $central_id ) );

		// the synchronization does not add it again.
		$this->sync( $this->site_b );
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test that a file only for some websites is removed from the others and added to the selected ones.
	 *
	 * @return void
	 */
	public function test_visibility_only(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		Propagation::get_instance()->process();

		// only for the second website.
		switch_to_blog( $this->central );
		Media_Library::get_instance()->set_visibility( $central_id, 'only', array( $this->site_b ) );
		restore_current_blog();
		Propagation::get_instance()->process();
		$this->assertCount( 0, $this->get_copies( $this->site_a, $central_id ) );

		// the second website gets it via cron.
		$this->assertNotFalse( wp_next_scheduled( Propagation::CRON_IMPORT, array( $central_id, array( $this->site_b ) ) ) );
		add_filter( 'wp_doing_cron', '__return_true' );
		Propagation::get_instance()->run_import( $central_id, array( $this->site_b ) );
		remove_filter( 'wp_doing_cron', '__return_true' );
		$this->assertCount( 1, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test that new uploads are only available for the website, from which they come, if this is the default.
	 *
	 * @return void
	 */
	public function test_default_visibility_own(): void {
		Media_Library::get_instance()->set_rules( 'own', 'own', array() );
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$central_id = $this->export( $this->site_a, $upload_id );

		switch_to_blog( $this->central );
		$this->assertSame(
			array(
				'mode'  => 'only',
				'sites' => array( $this->site_a ),
			),
			Media_Library::get_instance()->get_visibility( $central_id )
		);
		restore_current_blog();

		$this->sync( $this->site_b );
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test that a new website is connected with the central media library.
	 *
	 * @return void
	 */
	public function test_new_site_is_connected(): void {
		$blog_id = absint( self::factory()->blog->create() );

		switch_to_blog( $blog_id );
		$this->assertGreaterThan( 0, Media_Library::get_instance()->get_central_term_id() );
		$this->assertSame( 1, absint( get_option( 'eml_sync' ) ) );
		$this->assertSame( 1, absint( get_option( 'eml_export' ) ) );
		restore_current_blog();
	}

	/**
	 * Test the usage of a file in the network.
	 *
	 * @return void
	 */
	public function test_usage(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		$this->sync( $this->site_b );
		$copies = $this->get_copies( $this->site_a, $central_id );

		// use it in the central media library in a content and in the first website as featured image.
		self::factory()->post->create( array( 'post_content' => '<!-- wp:image {"id":' . $central_id . '} --><figure class="wp-block-image"><img src="x.jpg" class="wp-image-' . $central_id . '"/></figure><!-- /wp:image -->' ) );
		switch_to_blog( $this->site_a );
		set_post_thumbnail( self::factory()->post->create(), $copies[0] );
		restore_current_blog();

		$usage = Usage::get_instance()->get_usage( $central_id );
		$this->assertSame( 1, $usage[ $this->central ]['posts'] );
		$this->assertSame( 1, $usage[ $this->site_a ]['posts'] );
		$this->assertSame( 0, $usage[ $this->site_b ]['posts'] );
	}

	/**
	 * Test that a new file of the central media library is added to the websites via cron.
	 *
	 * @return void
	 */
	public function test_new_file_is_added_to_websites(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		Propagation::get_instance()->process();

		$this->assertNotFalse( wp_next_scheduled( Propagation::CRON_IMPORT, array( $central_id, array( $this->site_a, $this->site_b ) ) ) );
		add_filter( 'wp_doing_cron', '__return_true' );
		Propagation::get_instance()->run_import( $central_id, array( $this->site_a, $this->site_b ) );
		remove_filter( 'wp_doing_cron', '__return_true' );

		foreach ( array( $this->site_a, $this->site_b ) as $blog_id ) {
			$copies = $this->get_copies( $blog_id, $central_id );
			$this->assertCount( 1, $copies );
			switch_to_blog( $blog_id );
			$this->assertSame( $central_id, absint( get_post_meta( $copies[0], Media_Library::META_CENTRAL_ID, true ) ) );
			$this->assertGreaterThan( 0, absint( get_post_meta( $copies[0], 'eml_synced_time', true ) ) );
			restore_current_blog();
		}

		// the synchronization does not add it a second time.
		$this->sync( $this->site_a );
		$this->assertCount( 1, $this->get_copies( $this->site_a, $central_id ) );
	}

	/**
	 * Test that title, caption and alternative text are passed to the websites, and that the own changes of a website
	 * are kept until they are discarded.
	 *
	 * @return void
	 */
	public function test_metadata(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		update_post_meta( $central_id, '_wp_attachment_image_alt', 'Central alt' );
		wp_update_post(
			array(
				'ID'           => $central_id,
				'post_excerpt' => 'Central caption',
			)
		);
		Propagation::get_instance()->process();
		$this->sync( $this->site_a );
		$copy_id = $this->get_copies( $this->site_a, $central_id )[0];

		// the values of the central media library are used.
		switch_to_blog( $this->site_a );
		$values = Metadata::get_instance()->get_values( $copy_id );
		$this->assertSame( 'Central caption', $values['caption'] );
		$this->assertSame( 'Central alt', $values['alt'] );
		restore_current_blog();

		// a user changes the caption in the website.
		switch_to_blog( $this->site_a );
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'save-attachment';
		wp_update_post(
			array(
				'ID'           => $copy_id,
				'post_excerpt' => 'Own caption',
			)
		);
		remove_filter( 'wp_doing_ajax', '__return_true' );
		unset( $_REQUEST['action'] );
		restore_current_blog();

		// the central file is changed.
		update_post_meta( $central_id, '_wp_attachment_image_alt', 'New central alt' );
		wp_update_post(
			array(
				'ID'           => $central_id,
				'post_excerpt' => 'New central caption',
			)
		);
		Propagation::get_instance()->process();

		// the own caption is kept, the alternative text is updated, also after the next synchronization.
		$this->sync( $this->site_a );
		switch_to_blog( $this->site_a );
		$values = Metadata::get_instance()->get_values( $copy_id );
		$this->assertSame( 'Own caption', $values['caption'] );
		$this->assertSame( 'New central alt', $values['alt'] );

		// the own change is discarded.
		Metadata::get_instance()->reset( $copy_id );
		$this->assertSame( 'New central caption', Metadata::get_instance()->get_values( $copy_id )['caption'] );
		restore_current_blog();
	}

	/**
	 * Test that a changed file of the central media library (e.g. after editing the image) is used in the websites.
	 *
	 * @return void
	 */
	public function test_changed_file_is_used(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		Propagation::get_instance()->process();
		$copy_id = $this->get_copies( $this->site_a, $central_id )[0];

		// change the file like the image editor does it: a new file name.
		$file     = (string) get_attached_file( $central_id );
		$new_file = str_replace( '.jpg', '-e123.jpg', $file );
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-green.jpg', $new_file );
		update_attached_file( $central_id, $new_file );
		wp_update_attachment_metadata( $central_id, (array) wp_get_attachment_metadata( $central_id ) );
		Propagation::get_instance()->process();

		switch_to_blog( $this->site_a );
		$this->assertStringEndsWith( '-e123.jpg', Files::get_instance()->get_file( $copy_id )->get_url( true ) );
		restore_current_blog();
	}

	/**
	 * Test the migration of the origin of uploads, which have been exported before the origin has been saved.
	 *
	 * @return void
	 */
	public function test_migration_of_origins(): void {
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$central_id = $this->export( $this->site_a, $upload_id );

		// remove the origin like in older versions.
		delete_post_meta( $central_id, Media_Library::META_ORIGIN_BLOG );
		delete_post_meta( $central_id, Media_Library::META_ORIGIN_ATTACHMENT );
		delete_site_option( 'efml_network_origins_migrated' );

		$this->assertTrue( Media_Library::get_instance()->migrate_origins() );
		$this->assertSame( $this->site_a, Media_Library::get_instance()->get_origin_blog( $central_id ) );
		$this->assertSame( 1, absint( get_site_option( 'efml_network_origins_migrated' ) ) );
	}

	/**
	 * Test that the title of a copy is its file name (the proxy uses it in the URL), also if the central file has
	 * another title, and that a former title of a copy is kept for the proxy.
	 *
	 * @return void
	 */
	public function test_title_of_copies_is_file_name(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		wp_update_post(
			array(
				'ID'         => $central_id,
				'post_title' => 'Picsum ID: 1032',
			)
		);
		$file_name = basename( (string) get_attached_file( $central_id ) );
		Propagation::get_instance()->process();

		// via the synchronization.
		$this->sync( $this->site_a );
		$copy_id = $this->get_copies( $this->site_a, $central_id )[0];
		switch_to_blog( $this->site_a );
		$external_file_obj = Files::get_instance()->get_file( $copy_id );
		$this->assertSame( $file_name, get_the_title( $copy_id ) );
		$this->assertStringEndsWith( '/' . $file_name, $external_file_obj->get_url() );
		restore_current_blog();

		// via the import of a new file.
		add_filter( 'wp_doing_cron', '__return_true' );
		Propagation::get_instance()->run_import( $central_id, array( $this->site_b ) );
		remove_filter( 'wp_doing_cron', '__return_true' );
		$copy_id = $this->get_copies( $this->site_b, $central_id )[0];
		switch_to_blog( $this->site_b );
		$this->assertSame( $file_name, get_the_title( $copy_id ) );

		// a copy of an older version with the title of the central file keeps it as former title.
		wp_update_post(
			array(
				'ID'         => $copy_id,
				'post_title' => 'example-red',
			)
		);
		restore_current_blog();
		$this->sync( $this->site_b );
		switch_to_blog( $this->site_b );
		$this->assertSame( $file_name, get_the_title( $copy_id ) );
		$this->assertContains( 'example-red', get_post_meta( $copy_id, Files::FORMER_TITLES_META, false ) );
		restore_current_blog();
	}

	/**
	 * Test the bulk action for the availability: it forwards to the form, and the saving of the form does not use the
	 * name of the bulk action (WordPress runs "admin_action_<action>" also for the bulk action, which would check the
	 * nonce of the form and stop with "The link you followed has expired").
	 *
	 * @return void
	 */
	public function test_bulk_action_for_visibility(): void {
		$visibility = \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::get_instance();

		// the bulk action is offered in the central media library.
		$this->assertArrayHasKey( \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::BULK_ACTION, apply_filters( 'bulk_actions-upload', array() ) );

		// it forwards to the form for the selected files.
		$url = $visibility->handle_bulk_action( admin_url( 'upload.php' ), \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::BULK_ACTION, array( 12, 34 ) );
		$this->assertStringStartsWith( admin_url( 'admin.php' ), $url );
		$this->assertStringContainsString( 'page=efml-network-visibility', $url );
		$this->assertStringContainsString( 'ids=12,34', $url );

		// the form is a page of the backend.
		$visibility->add_page();
		$this->assertNotNull( get_plugin_page_hook( 'efml-network-visibility', 'admin.php' ) );

		// it loads the script, which shows the websites for the selected mode (only our callback is used, as the
		// others of WordPress need a screen of the backend; the hooks are restored after the test).
		wp_dequeue_script( 'efml-network' );
		remove_all_actions( 'admin_enqueue_scripts' );
		add_action( 'admin_enqueue_scripts', array( \ExternalFilesInMediaLibrary\Plugin\Network\Permissions::get_instance(), 'add_script' ) );
		do_action( 'admin_enqueue_scripts', $visibility->get_page_hook() );
		$this->assertTrue( wp_script_is( 'efml-network', 'enqueued' ) );
		wp_dequeue_script( 'efml-network' );

		// no action of the admin runs for the bulk action, only for the saving of the form.
		$this->assertFalse( has_action( 'admin_action_' . \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::BULK_ACTION ) );
		$this->assertNotFalse( has_action( 'admin_action_' . \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::SAVE_ACTION ) );
	}

	/**
	 * Test that the synchronization does not load the files of the central media library via HTTP: they are on the
	 * same server.
	 *
	 * @return void
	 */
	public function test_sync_does_not_download_files(): void {
		$this->add_image( $this->central, 'example-red.jpg' );
		$this->add_image( $this->central, 'example-blue.png' );

		// count the downloads.
		$downloads = 0;
		$counter   = static function ( $response, array $parsed_args ) use ( &$downloads ) {
			if ( ! empty( $parsed_args['filename'] ) ) {
				++$downloads;
			}
			return $response;
		};
		add_filter( 'pre_http_request', $counter, 1, 2 );
		$this->sync( $this->site_a );
		remove_filter( 'pre_http_request', $counter, 1 );

		$this->assertSame( 0, $downloads );
	}

	/**
	 * Test that the synchronization skips unchanged files, but still adds new and deletes removed ones.
	 *
	 * @return void
	 */
	public function test_sync_skips_unchanged_files(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		$copies = $this->get_copies( $this->site_a, $central_id );
		$this->assertCount( 1, $copies );

		// count the imported files in the second synchronization.
		$imported = 0;
		$counter  = static function () use ( &$imported ) {
			++$imported;
		};
		add_action( 'efml_after_file_save', $counter );
		$this->sync( $this->site_a );
		remove_action( 'efml_after_file_save', $counter );

		$this->assertSame( 0, $imported );
		$this->assertSame( $copies, $this->get_copies( $this->site_a, $central_id ) );

		// a removed file is still deleted, although all other files are skipped.
		$second_id = $this->add_image( $this->central, 'example-blue.png' );
		$this->sync( $this->site_a );
		$this->assertCount( 1, $this->get_copies( $this->site_a, $second_id ) );
		remove_action( 'delete_attachment', array( Propagation::get_instance(), 'add_deletion' ), 20 );
		$this->delete_as_user( $this->central, $second_id );
		add_action( 'delete_attachment', array( Propagation::get_instance(), 'add_deletion' ), 20 );
		$this->sync( $this->site_a );
		$this->assertCount( 0, $this->get_copies( $this->site_a, $second_id ) );
		$this->assertCount( 1, $this->get_copies( $this->site_a, $central_id ) );
	}

	/**
	 * Test that a new external source of the central media library is synchronized daily.
	 *
	 * @return void
	 */
	public function test_daily_interval(): void {
		switch_to_blog( $this->site_a );
		$this->assertSame( '24hourly', get_term_meta( Media_Library::get_instance()->get_central_term_id(), 'interval', true ) );
		restore_current_blog();
	}

	/**
	 * Test that an upload with the same content as an existing file of the central media library uses this file.
	 *
	 * @return void
	 */
	public function test_duplicate_upload_uses_existing_file(): void {
		$central_id = $this->export( $this->site_a, $this->add_image( $this->site_a, 'example-blue.png' ) );

		// make it available only for the first website: the second one gets it with its upload.
		switch_to_blog( $this->central );
		Media_Library::get_instance()->set_visibility( $central_id, 'only', array( $this->site_a ) );
		$count = absint( wp_count_posts( 'attachment' )->inherit );
		restore_current_blog();

		$upload_id = $this->add_image( $this->site_b, 'example-blue.png' );
		$this->assertSame( $central_id, $this->export( $this->site_b, $upload_id ) );

		switch_to_blog( $this->central );
		$this->assertSame( $count, absint( wp_count_posts( 'attachment' )->inherit ) );
		$this->assertTrue( Media_Library::get_instance()->is_visible_for( $central_id, $this->site_b ) );
		restore_current_blog();

		// it is not an own upload: its deletion does not delete the existing file.
		switch_to_blog( $this->site_b );
		$this->assertTrue( Media_Library::get_instance()->is_linked( $upload_id ) );
		$this->assertFalse( Media_Library::get_instance()->is_own_upload( $upload_id ) );
		restore_current_blog();

		// without the option the file is added again.
		Media_Library::get_instance()->set_settings( array( 'dedupe' => 0 ) );
		$this->assertNotSame( $central_id, $this->export( $this->site_b, $this->add_image( $this->site_b, 'example-blue.png' ) ) );
	}

	/**
	 * Test that the uploads of a deleted website are kept by default and belong to the central media library.
	 *
	 * @return void
	 */
	public function test_deleted_site_keeps_uploads(): void {
		$central_id = $this->export( $this->site_a, $this->add_image( $this->site_a, 'example-blue.png' ) );
		wp_delete_site( $this->site_a );

		switch_to_blog( $this->central );
		$this->assertSame( 'attachment', get_post_type( $central_id ) );
		$this->assertSame( 0, Media_Library::get_instance()->get_origin_blog( $central_id ) );
		restore_current_blog();
		$this->assertNotContains( $this->site_a, Media_Library::get_instance()->get_groups()[1]['sites'] );
	}

	/**
	 * Test that the uploads of a deleted website are deleted, if configured.
	 *
	 * @return void
	 */
	public function test_deleted_site_deletes_uploads(): void {
		Media_Library::get_instance()->set_settings( array( 'deleted_site' => 'delete' ) );
		$central_id = $this->export( $this->site_a, $this->add_image( $this->site_a, 'example-blue.png' ) );
		$this->sync( $this->site_b );
		$this->assertCount( 1, $this->get_copies( $this->site_b, $central_id ) );

		wp_delete_site( $this->site_a );
		Propagation::get_instance()->process();

		switch_to_blog( $this->central );
		$this->assertNull( get_post( $central_id ) );
		restore_current_blog();
		$this->assertCount( 0, $this->get_copies( $this->site_b, $central_id ) );
	}

	/**
	 * Test that a website, which leaves its group, keeps its own uploads and used files as local files and loses the
	 * other files of the central media library.
	 *
	 * @return void
	 */
	public function test_leaving_keeps_own_and_used_files(): void {
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$own_id     = $this->export( $this->site_a, $upload_id );
		$used_id    = $this->add_image( $this->central, 'example-red.jpg' );
		$unused_id  = $this->add_image( $this->central, 'example-green.jpg' );
		$this->sync( $this->site_a );
		$used_copy = $this->get_copies( $this->site_a, $used_id )[0];
		switch_to_blog( $this->site_a );
		set_post_thumbnail( self::factory()->post->create(), $used_copy );
		restore_current_blog();
		$this->assertCount( 1, $this->get_copies( $this->site_a, $unused_id ) );

		$this->assertTrue( Api::get_instance()->set_group( $this->site_a, 0 ) );
		$this->assertTrue( Membership::get_instance()->run_leave( $this->site_a, 60 ) );

		switch_to_blog( $this->site_a );
		$this->assertFalse( Files::get_instance()->get_file( $upload_id )->is_valid() );
		$this->assertFileExists( (string) get_attached_file( $upload_id ) );
		$this->assertFalse( Files::get_instance()->get_file( $used_copy )->is_valid() );
		$this->assertFileExists( (string) get_attached_file( $used_copy ) );
		$this->assertSame( 0, Media_Library::get_instance()->get_central_term_id() );
		restore_current_blog();
		$this->assertCount( 0, $this->get_copies( $this->site_a, $unused_id ) );

		// the files of the central media library are kept, the own upload belongs to it now.
		switch_to_blog( $this->central );
		$this->assertSame( 'attachment', get_post_type( $own_id ) );
		$this->assertSame( 'attachment', get_post_type( $unused_id ) );
		$this->assertSame( 0, Media_Library::get_instance()->get_origin_blog( $own_id ) );
		restore_current_blog();
	}

	/**
	 * Test two groups: each website uses only the files of the central media library of its group.
	 *
	 * @return void
	 */
	public function test_groups(): void {
		$second_central = absint( self::factory()->blog->create() );
		$groups         = Media_Library::get_instance()->get_groups();
		$new_groups     = $groups;
		$new_groups[1]['sites'] = array_values( array_diff( $new_groups[1]['sites'], array( $this->site_b, $second_central ) ) );
		$new_groups[2]  = array(
			'name'    => 'Second',
			'central' => $second_central,
			'sites'   => array( $this->site_b ),
		);
		Membership::get_instance()->apply_groups( $groups, $new_groups );

		$this->assertSame( $this->central, Media_Library::get_instance()->get_central_for( $this->site_a ) );
		$this->assertSame( $second_central, Media_Library::get_instance()->get_central_for( $this->site_b ) );
		$this->assertTrue( Media_Library::get_instance()->is_central( $second_central ) );
		$this->assertSame( array( $this->site_b ), array_keys( Media_Library::get_instance()->get_sites( $second_central ) ) );

		// the files of each central media library are passed only to the websites of its group.
		$first_id  = $this->add_image( $this->central, 'example-red.jpg' );
		$second_id = $this->add_image( $second_central, 'example-blue.png' );
		$this->sync( $this->site_a );
		switch_to_blog( $this->site_b );
		$this->run_sync( 'cron', (string) get_blogaddress_by_id( $second_central ), Media_Library::get_instance()->get_central_term_id() );
		$this->assertSame( 0, count( Media_Library::get_instance()->find_copies( $first_id, array(), 0 ) ) );
		restore_current_blog();
		$this->assertCount( 1, $this->get_copies( $this->site_a, $first_id ) );

		switch_to_blog( $second_central );
		$second_url = Media_Library::get_instance()->get_file_url( $second_id );
		restore_current_blog();
		switch_to_blog( $this->site_b );
		$this->assertCount( 1, Media_Library::get_instance()->find_copies( $second_id, array( $second_url ), 0 ) );
		restore_current_blog();

		// the deletion in the second central media library is passed only to its group.
		$this->delete_as_user( $second_central, $second_id );
		Propagation::get_instance()->process();
		switch_to_blog( $this->site_b );
		$this->assertCount( 0, array_filter( Media_Library::get_instance()->find_copies( $second_id, array( $second_url ), 0 ), static fn( int $id ): bool => 'attachment' === get_post_type( $id ) ) );
		restore_current_blog();
	}

	/**
	 * Test that the central media library of older versions is used as first group.
	 *
	 * @return void
	 */
	public function test_groups_of_older_versions(): void {
		delete_site_option( Media_Library::OPTION_GROUPS );
		update_site_option( 'efml_media_library_site_id', $this->central );
		Media_Library::get_instance()->reset_sites();

		$groups = Media_Library::get_instance()->get_groups();
		$this->assertCount( 1, $groups );
		$this->assertSame( $this->central, $groups[1]['central'] );
		$this->assertContains( $this->site_a, $groups[1]['sites'] );
		$this->assertContains( $this->site_b, $groups[1]['sites'] );
		$this->assertSame( $this->central, Settings::get_instance()->get_main_media_library_site_id() );
	}

	/**
	 * Test the chosen availability of new uploads.
	 *
	 * @return void
	 */
	public function test_upload_visibility_choice(): void {
		update_user_meta( get_current_user_id(), Upload::META, 'own' );
		$central_id = $this->export( $this->site_a, $this->add_image( $this->site_a, 'example-blue.png' ) );
		delete_user_meta( get_current_user_id(), Upload::META );

		switch_to_blog( $this->central );
		$this->assertTrue( Media_Library::get_instance()->is_visible_for( $central_id, $this->site_a ) );
		$this->assertFalse( Media_Library::get_instance()->is_visible_for( $central_id, $this->site_b ) );
		restore_current_blog();
	}

	/**
	 * Test that only super administrators, administrators and the chosen roles could choose the availability of
	 * their uploads. The uploads of the other users get the setting of the network.
	 *
	 * @return void
	 */
	public function test_upload_visibility_choice_by_role(): void {
		$editor_id = self::factory()->user->create();
		add_user_to_blog( $this->site_a, $editor_id, 'editor' );
		update_user_meta( $editor_id, Upload::META, 'own' );
		$admin_id = get_current_user_id();

		// an editor could not choose it: the setting of the network is used.
		wp_set_current_user( $editor_id );
		switch_to_blog( $this->site_a );
		$this->assertFalse( Upload::get_instance()->may_choose() );
		$this->assertSame( 'all', Upload::get_instance()->get_choice() );
		ob_start();
		Upload::get_instance()->show_choice();
		$this->assertSame( '', ob_get_clean() );
		restore_current_blog();
		$upload_id = $this->add_image( $this->site_a, 'example-blue.png' );
		wp_set_current_user( $admin_id );
		$central_id = $this->export( $this->site_a, $upload_id );
		switch_to_blog( $this->central );
		$this->assertTrue( Media_Library::get_instance()->is_visible_for( $central_id, $this->site_b ) );
		restore_current_blog();

		// an editor of the central media library could not change the availability of its files either.
		add_user_to_blog( $this->central, $editor_id, 'editor' );
		wp_set_current_user( $editor_id );
		$this->assertArrayNotHasKey( 'efml_network_visibility', \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::get_instance()->add_field( array(), get_post( $central_id ) ) );
		$this->assertArrayNotHasKey( \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::BULK_ACTION, \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::get_instance()->add_bulk_action( array() ) );
		$this->assertFalse( Api::get_instance()->can_edit_file( $this->central, $central_id ) );
		wp_set_current_user( $admin_id );
		$this->assertArrayHasKey( 'efml_network_visibility', \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::get_instance()->add_field( array(), get_post( $central_id ) ) );

		// the role is allowed in the settings of the network.
		Upload::get_instance()->set_roles( array( 'editor' ) );
		wp_set_current_user( $editor_id );
		switch_to_blog( $this->site_a );
		$this->assertTrue( Upload::get_instance()->may_choose() );
		$this->assertSame( 'own', Upload::get_instance()->get_choice() );
		restore_current_blog();
		$this->assertArrayHasKey( 'efml_network_visibility', \ExternalFilesInMediaLibrary\Plugin\Network\Visibility::get_instance()->add_field( array(), get_post( $central_id ) ) );

		// an own choice of a website: not in the first website, still by default in the central media library.
		Upload::get_instance()->set_roles( array( 'editor' ), array( $this->site_a => array() ) );
		switch_to_blog( $this->site_a );
		$this->assertFalse( Upload::get_instance()->may_choose() );
		restore_current_blog();
		$this->assertTrue( Upload::get_instance()->may_choose() );

		// back to the default.
		Upload::get_instance()->set_roles( array( 'editor' ), array( $this->site_a => null ) );
		switch_to_blog( $this->site_a );
		$this->assertTrue( Upload::get_instance()->may_choose() );
		restore_current_blog();
		wp_set_current_user( $admin_id );
		delete_site_option( Upload::OPTION_ROLES );
	}

	/**
	 * Test the filter of the media library for own uploads and files of the central media library.
	 *
	 * @return void
	 */
	public function test_media_filter(): void {
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$this->export( $this->site_a, $upload_id );
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		$copy_id = $this->get_copies( $this->site_a, $central_id )[0];

		switch_to_blog( $this->site_a );
		$this->assertArrayHasKey( 'efml-network-own', Upload::get_instance()->add_filter_options( array() ) );
		foreach ( array(
			'efml-network-own'     => $upload_id,
			'efml-network-central' => $copy_id,
		) as $filter => $expected ) {
			$query = new \WP_Query(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'fields'      => 'ids',
					'meta_query'  => Upload::get_instance()->get_meta_query( $filter ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			);
			$this->assertSame( array( $expected ), array_map( 'absint', $query->get_posts() ), $filter );
		}
		restore_current_blog();
	}

	/**
	 * Test that changes of the website, from which a file comes, apply to all websites, if enabled.
	 *
	 * @return void
	 */
	public function test_origin_edits(): void {
		$upload_id  = $this->add_image( $this->site_a, 'example-blue.png' );
		$central_id = $this->export( $this->site_a, $upload_id );
		$this->sync( $this->site_b );

		// change the caption like a user in the media library.
		$change = function ( string $caption ) use ( $upload_id ) {
			add_filter( 'wp_doing_ajax', '__return_true' );
			$_REQUEST['action'] = 'save-attachment';
			switch_to_blog( $this->site_a );
			wp_update_post(
				array(
					'ID'           => $upload_id,
					'post_excerpt' => $caption,
				)
			);
			restore_current_blog();
			unset( $_REQUEST['action'] );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			Propagation::get_instance()->process();
		};

		// disabled by default: only the website itself uses it.
		$change( 'Own caption' );
		switch_to_blog( $this->central );
		$this->assertNotSame( 'Own caption', get_post( $central_id )->post_excerpt );
		restore_current_blog();

		// enabled: all websites use it.
		Media_Library::get_instance()->set_settings( array( 'origin_edits' => 1 ) );
		$change( 'Caption for all' );
		switch_to_blog( $this->central );
		$this->assertSame( 'Caption for all', get_post( $central_id )->post_excerpt );
		restore_current_blog();
		$copy_id = $this->get_copies( $this->site_b, $central_id )[0];
		switch_to_blog( $this->site_b );
		$this->assertSame( 'Caption for all', get_post( $copy_id )->post_excerpt );
		restore_current_blog();
	}

	/**
	 * Test the state of the websites in the overview.
	 *
	 * @return void
	 */
	public function test_overview(): void {
		$this->export( $this->site_a, $this->add_image( $this->site_a, 'example-blue.png' ) );
		$this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );

		$state = Overview::get_instance()->get_site_state( $this->site_a );
		$this->assertSame( 2, $state['files'] );
		$this->assertSame( 1, $state['own'] );
		$this->assertFalse( $state['leaving'] );
	}

	/**
	 * Test that the uploads of a website in the central media library count for its upload space, if enabled.
	 *
	 * @return void
	 */
	public function test_quota(): void {
		update_site_option( 'upload_space_check_disabled', 0 );
		$central_id = $this->export( $this->site_a, $this->add_image( $this->site_a, 'example-blue.png' ) );
		switch_to_blog( $this->central );
		$bytes = absint( get_post_meta( $central_id, Media_Library::META_BYTES, true ) );
		restore_current_blog();
		$this->assertGreaterThan( 0, $bytes );

		switch_to_blog( $this->site_a );
		$this->assertEqualsWithDelta( $bytes / MB_IN_BYTES, Quota::get_instance()->get_space_used( false ) - (float) get_dirsize( wp_upload_dir()['basedir'] ) / MB_IN_BYTES, 0.0001 );

		// disabled: WordPress measures it.
		Media_Library::get_instance()->set_settings( array( 'quota' => 0 ) );
		$this->assertFalse( Quota::get_instance()->get_space_used( false ) );
		restore_current_blog();
	}

	/**
	 * Test the functions for WP-CLI, REST API and Abilities API.
	 *
	 * @return void
	 */
	public function test_api(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );

		// the state.
		$status = Api::get_instance()->get_status();
		$this->assertSame( $this->central, $status[0]['central'] );
		$this->assertContains( $this->site_a, array_column( $status[0]['sites'], 'blog_id' ) );

		// the availability only in the central media library.
		$this->assertInstanceOf( \WP_Error::class, Api::get_instance()->set_visibility( $this->site_a, $central_id, 'all', array() ) );
		$this->assertTrue( Api::get_instance()->set_visibility( $this->central, $central_id, 'only', array( $this->site_b ) ) );
		$this->assertFalse( Media_Library::get_instance()->is_visible_for( $central_id, $this->site_a ) );

		// via REST API.
		Api::get_instance()->register_endpoints();
		$request = new \WP_REST_Request( 'POST', '/efml/v1/network/' . $this->central . '/file/' . $central_id . '/visibility' );
		$request->set_param( 'mode', 'all' );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertTrue( Media_Library::get_instance()->is_visible_for( $central_id, $this->site_a ) );

		// not for users without the capability.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/efml/v1/network' ) )->get_status() );

		// the abilities.
		$this->assertArrayHasKey( 'network-set-visibility', Api::get_instance()->add_abilities( array() ) );
	}

	/**
	 * Test the transfer of the local files of a website, which it had before it joined its group.
	 *
	 * @return void
	 */
	public function test_transfer_local_files(): void {
		$local_id = $this->add_image( $this->site_a, 'example-blue.png' );
		$this->assertSame( 1, Existing::get_instance()->count( $this->site_a ) );

		$this->assertIsString( Existing::get_instance()->start( $this->site_a ) );
		switch_to_blog( $this->site_a );
		@\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->process(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$central_id = absint( get_post_meta( $local_id, 'efml_multisite_attachment_id', true ) );
		$this->assertTrue( Files::get_instance()->get_file( $local_id )->is_valid() );
		restore_current_blog();

		$this->assertGreaterThan( 0, $central_id );
		$this->assertSame( 0, Existing::get_instance()->count( $this->site_a ) );
		switch_to_blog( $this->central );
		$this->assertSame( $this->site_a, Media_Library::get_instance()->get_origin_blog( $central_id ) );
		restore_current_blog();

		// not for websites without central media library.
		$this->assertInstanceOf( \WP_Error::class, Existing::get_instance()->start( $this->central ) );
	}

	/**
	 * Test the search for files, which no website uses.
	 *
	 * @return void
	 */
	public function test_unused_files(): void {
		$used_id   = $this->add_image( $this->central, 'example-red.jpg' );
		$unused_id = $this->add_image( $this->central, 'example-blue.png' );
		$this->sync( $this->site_a );
		switch_to_blog( $this->site_a );
		set_post_thumbnail( self::factory()->post->create(), $this->get_copies( $this->site_a, $used_id )[0] );
		restore_current_blog();

		// not checked yet: nothing is shown as unused.
		$this->assertSame( array(), Unused::get_instance()->get_unused() );

		$this->assertTrue( Unused::get_instance()->run( 60 ) );
		$this->assertSame( array( $unused_id ), Unused::get_instance()->get_unused() );
		$this->assertNotFalse( wp_next_scheduled( Unused::CRON ) );
		$this->assertArrayHasKey( 'efml-network-unused', Unused::get_instance()->add_filter_option( array() ) );

		// not in the websites.
		switch_to_blog( $this->site_a );
		$this->assertTrue( Unused::get_instance()->run( 60 ) );
		$this->assertArrayNotHasKey( 'efml-network-unused', Unused::get_instance()->add_filter_option( array() ) );
		restore_current_blog();
	}

	/**
	 * Test the merging of files with the same content: the websites use the kept file and keep their attachment.
	 *
	 * @return void
	 */
	public function test_merge_duplicates(): void {
		Media_Library::get_instance()->set_settings( array( 'dedupe' => 0 ) );
		$keep_id   = $this->add_image( $this->central, 'example-blue.png' );
		$upload_id = $this->add_image( $this->site_a, 'example-blue.png' );
		$dup_id    = $this->export( $this->site_a, $upload_id );
		$this->sync( $this->site_b );
		$copy_id = $this->get_copies( $this->site_b, $dup_id )[0];
		switch_to_blog( $this->site_b );
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $copy_id );
		restore_current_blog();

		$this->assertSame( array( array( $keep_id, $dup_id ) ), Duplicates::get_instance()->get_groups() );

		// not if the duplicate is used in the central media library.
		$central_post = self::factory()->post->create();
		set_post_thumbnail( $central_post, $dup_id );
		$this->assertInstanceOf( \WP_Error::class, Duplicates::get_instance()->merge( $keep_id, $dup_id ) );
		delete_post_thumbnail( $central_post );

		$this->assertTrue( Duplicates::get_instance()->merge( $keep_id, $dup_id ) );
		Propagation::get_instance()->process();

		// the duplicate is deleted, the kept file exists.
		$this->assertNull( get_post( $dup_id ) );
		$this->assertSame( 'attachment', get_post_type( $keep_id ) );
		$this->assertSame( array(), Duplicates::get_instance()->get_groups() );

		// the websites keep their attachments with the kept file.
		$keep_url = Media_Library::get_instance()->get_file_url( $keep_id );
		switch_to_blog( $this->site_b );
		$this->assertSame( $copy_id, get_post_thumbnail_id( $post_id ) );
		$this->assertSame( $keep_url, Files::get_instance()->get_file( $copy_id )->get_url( true ) );
		$this->assertSame( $keep_id, absint( get_post_meta( $copy_id, Media_Library::META_CENTRAL_ID, true ) ) );
		restore_current_blog();
		switch_to_blog( $this->site_a );
		$this->assertSame( 'attachment', get_post_type( $upload_id ) );
		$this->assertSame( $keep_url, Files::get_instance()->get_file( $upload_id )->get_url( true ) );
		$this->assertTrue( Media_Library::get_instance()->is_linked( $upload_id ) );
		restore_current_blog();
	}

	/**
	 * Test the move of a central media library to a website of its group.
	 *
	 * @return void
	 */
	public function test_move_central(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$upload_id  = $this->add_image( $this->site_b, 'example-blue.png' );
		$own_id     = $this->export( $this->site_b, $upload_id );
		$this->sync( $this->site_a );
		$this->sync( $this->site_b );
		$copy_a = $this->get_copies( $this->site_a, $central_id )[0];
		$own_a  = $this->get_copies( $this->site_a, $own_id )[0];
		$copy_b = $this->get_copies( $this->site_b, $central_id )[0];
		switch_to_blog( $this->site_a );
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $copy_a );
		restore_current_blog();

		// move it to the first website.
		$this->assertInstanceOf( \WP_Error::class, Move::get_instance()->start( 1, $this->central ) );
		$this->assertTrue( Move::get_instance()->start( 1, $this->site_a ) );
		$this->assertInstanceOf( \WP_Error::class, Move::get_instance()->start( 1, $this->site_b ) );
		add_filter( 'wp_doing_cron', '__return_true' );
		$this->assertTrue( Move::get_instance()->run( 600 ) );
		remove_filter( 'wp_doing_cron', '__return_true' );
		$this->assertFalse( Move::get_instance()->is_running() );

		// the first website is the central media library: its copies are its own files, their usage is kept.
		$this->assertSame( $this->site_a, Media_Library::get_instance()->get_central_for( $this->site_b ) );
		$this->assertTrue( Media_Library::get_instance()->is_central( $this->site_a ) );
		$this->assertSame( 0, Media_Library::get_instance()->get_group_id( $this->central ) );
		switch_to_blog( $this->site_a );
		$this->assertFalse( Files::get_instance()->get_file( $copy_a )->is_valid() );
		$this->assertFileExists( (string) get_attached_file( $copy_a ) );
		$this->assertSame( $copy_a, get_post_thumbnail_id( $post_id ) );
		$this->assertSame( $this->site_b, Media_Library::get_instance()->get_origin_blog( $own_a ) );
		$new_url = Media_Library::get_instance()->get_file_url( $copy_a );
		restore_current_blog();

		// the second website uses the files of the new central media library.
		switch_to_blog( $this->site_b );
		$this->assertSame( $new_url, Files::get_instance()->get_file( $copy_b )->get_url( true ) );
		$this->assertSame( $copy_a, absint( get_post_meta( $copy_b, Media_Library::META_CENTRAL_ID, true ) ) );
		$this->assertSame( $own_a, absint( get_post_meta( $upload_id, 'efml_multisite_attachment_id', true ) ) );
		$this->assertTrue( Media_Library::get_instance()->is_own_upload( $upload_id ) );
		$this->assertSame( 0, Membership::get_instance()->get_term_id( $this->central ) );
		$this->assertGreaterThan( 0, Media_Library::get_instance()->get_central_term_id() );
		restore_current_blog();

		// the files of the former central media library are kept.
		$this->assertSame( 'attachment', get_post_type( $central_id ) );
		$this->assertSame( 'attachment', get_post_type( $own_id ) );

		// the synchronization of the second website does not add the files again.
		switch_to_blog( $this->site_b );
		$this->run_sync( 'cron', (string) get_blogaddress_by_id( $this->site_a ), Media_Library::get_instance()->get_central_term_id() );
		$this->assertCount( 1, Media_Library::get_instance()->find_copies( $copy_a, array( $new_url ), 0 ) );
		restore_current_blog();
	}

	/**
	 * Test the check and the fix of addresses, e.g. after a change of the domain.
	 *
	 * @return void
	 */
	public function test_addresses(): void {
		$central_id = $this->add_image( $this->central, 'example-red.jpg' );
		$this->sync( $this->site_a );
		$copy_id = $this->get_copies( $this->site_a, $central_id )[0];
		$this->assertSame( 0, Addresses::get_instance()->count_wrong( $this->site_a ) );

		// a former domain.
		switch_to_blog( $this->site_a );
		$url = Files::get_instance()->get_file( $copy_id )->get_url( true );
		update_post_meta( $copy_id, EFML_POST_META_URL, str_replace( 'http://example.org/', 'https://old.example/', $url ) );
		restore_current_blog();

		$this->assertSame( 1, Addresses::get_instance()->count_wrong( $this->site_a ) );
		$this->assertSame( 'critical', Addresses::get_instance()->run_site_health_test()['status'] );
		$this->assertSame( 1, Addresses::get_instance()->fix( $this->site_a, true ) );
		$this->assertSame( 1, Addresses::get_instance()->fix( $this->site_a ) );
		$this->assertSame( 0, Addresses::get_instance()->count_wrong( $this->site_a ) );
		switch_to_blog( $this->site_a );
		$this->assertSame( $url, Files::get_instance()->get_file( $copy_id )->get_url( true ) );
		restore_current_blog();
	}

	/**
	 * Test the weekly summary via e-mail.
	 *
	 * @return void
	 */
	public function test_digest(): void {
		// disabled by default.
		$this->assertFalse( Digest::get_instance()->send() );
		switch_to_blog( get_main_site_id() );
		$this->assertFalse( wp_next_scheduled( Digest::CRON ) );
		restore_current_blog();

		// enabled, but without problems: nothing is sent.
		Media_Library::get_instance()->set_settings( array( 'digest' => 1 ) );
		switch_to_blog( get_main_site_id() );
		$this->assertNotFalse( wp_next_scheduled( Digest::CRON ) );
		restore_current_blog();
		$this->assertFalse( Digest::get_instance()->send() );

		// a problem is sent: WP Cron does not run in a website.
		switch_to_blog( $this->site_a );
		wp_schedule_single_event( time() - DAY_IN_SECONDS, 'efml_test_overdue' );
		restore_current_blog();
		reset_phpmailer_instance();
		$this->assertTrue( Digest::get_instance()->send() );
		$this->assertStringContainsString( 'WP Cron does not run', tests_retrieve_phpmailer_instance()->get_sent()->body );

		// disabled again.
		Media_Library::get_instance()->set_settings( array( 'digest' => 0 ) );
		switch_to_blog( get_main_site_id() );
		$this->assertFalse( wp_next_scheduled( Digest::CRON ) );
		restore_current_blog();
	}

	/**
	 * Test that a website, which joins its group with files, which the central media library has already, keeps them
	 * with their IDs instead of getting them again (e.g. the former central media library after a move).
	 *
	 * @return void
	 */
	public function test_joining_website_links_its_local_files(): void {
		$first_id  = $this->add_image( $this->central, 'example-red.jpg' );
		$second_id = $this->add_image( $this->central, 'example-blue.png' );
		switch_to_blog( $this->central );
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $first_id );
		wp_update_post(
			array(
				'ID'           => $second_id,
				'post_excerpt' => 'Own caption',
			)
		);
		restore_current_blog();

		// move the central media library to the first website: the former one has its files as local files.
		$this->assertTrue( Move::get_instance()->start( 1, $this->site_a ) );
		$this->assertTrue( Move::get_instance()->run( 600 ) );
		switch_to_blog( $this->site_a );
		$new_first  = Move::get_instance()->get_moved_id( $first_id );
		$new_second = Move::get_instance()->get_moved_id( $second_id );
		wp_update_post(
			array(
				'ID'           => $new_second,
				'post_excerpt' => 'Central caption',
			)
		);
		restore_current_blog();

		// the former central media library joins the group again.
		$groups                = Media_Library::get_instance()->get_groups();
		$new_groups            = $groups;
		$new_groups[1]['sites'][] = $this->central;
		Membership::get_instance()->apply_groups( $groups, $new_groups );
		switch_to_blog( $this->central );
		$count = absint( wp_count_posts( 'attachment' )->inherit );
		$this->run_sync( 'cron', (string) get_blogaddress_by_id( $this->site_a ), Media_Library::get_instance()->get_central_term_id() );

		// no file has been added again: the local files use the files of the central media library with their IDs.
		$this->assertSame( $count, absint( wp_count_posts( 'attachment' )->inherit ) );
		switch_to_blog( $this->site_a );
		$first_url = Media_Library::get_instance()->get_file_url( $new_first );
		restore_current_blog();
		$this->assertSame( $first_url, Files::get_instance()->get_file( $first_id )->get_url( true ) );
		$this->assertSame( $new_first, absint( get_post_meta( $first_id, Media_Library::META_CENTRAL_ID, true ) ) );
		$this->assertSame( $first_id, get_post_thumbnail_id( $post_id ) );
		$this->assertTrue( Media_Library::get_instance()->is_linked( $first_id ) );

		// the own caption is kept.
		$this->assertSame( 'Own caption', get_post( $second_id )->post_excerpt );

		// a second synchronization does not change anything.
		$this->run_sync( 'cron', (string) get_blogaddress_by_id( $this->site_a ), Media_Library::get_instance()->get_central_term_id() );
		$this->assertSame( $count, absint( wp_count_posts( 'attachment' )->inherit ) );
		$this->assertSame( 'attachment', get_post_type( $first_id ) );
		restore_current_blog();

		// deleting it in the website does not delete the file of the central media library.
		$this->delete_as_user( $this->central, $first_id );
		Propagation::get_instance()->process();
		switch_to_blog( $this->site_a );
		$this->assertSame( 'attachment', get_post_type( $new_first ) );
		restore_current_blog();
	}
}
