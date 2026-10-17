<?php
/**
 * Tests for the synchronization of a YouTube channel, with the YouTube API simulated by the HTTP test server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Services\Youtube;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;
use WP_HTTP_Requests_Response;

/**
 * Object to test the synchronization of a YouTube channel.
 */
class YoutubeSync extends externalFilesTests {
	/**
	 * The URL of the simulated YouTube API.
	 *
	 * @var string
	 */
	private string $api_url = '';

	/**
	 * Prepare each test: the credentials of the channel and the simulated YouTube API.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'eml_youtube_credentials_vault', 'global' );
		update_option( 'eml_youtube_channel_id', 'UCtestchannel' );
		update_option( 'eml_youtube_api_key', Crypt::get_instance()->encrypt( 'test-key' ) );
		$this->reset_fields();

		add_filter( 'efml_youtube_api_url', array( $this, 'get_api_url' ) );
		add_filter( 'pre_http_request', array( $this, 'answer_youtube_requests' ), 5, 3 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_youtube_api_url', array( $this, 'get_api_url' ) );
		remove_filter( 'pre_http_request', array( $this, 'answer_youtube_requests' ), 5 );
		remove_filter( 'wp_doing_cron', '__return_true' );
		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		delete_option( 'eml_youtube_channel_id' );
		delete_option( 'eml_youtube_api_key' );
		$this->reset_fields();
		parent::tear_down();
	}

	/**
	 * Reset the fields of the service, which are loaded only once.
	 *
	 * @return void
	 */
	private function reset_fields(): void {
		$fields = new \ReflectionProperty( Youtube::get_instance(), 'fields' );
		$fields->setValue( Youtube::get_instance(), array() );
	}

	/**
	 * Return the URL of the simulated YouTube API.
	 *
	 * @return string
	 */
	public function get_api_url(): string {
		return $this->api_url;
	}

	/**
	 * Answer requests to YouTube like YouTube: with HTML.
	 *
	 * @param false|array<string,mixed>|\WP_Error $response The response.
	 * @param array<string,mixed>                 $parsed_args The arguments.
	 * @param string                              $url The URL.
	 *
	 * @return false|array<string,mixed>|\WP_Error
	 */
	public function answer_youtube_requests( false|array|\WP_Error $response, array $parsed_args, string $url ): false|array|\WP_Error {
		if ( 'www.youtube.com' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return $response;
		}

		$requests_response              = new \WpOrg\Requests\Response();
		$requests_response->status_code = 200;
		$requests_response->url         = $url;
		$requests_response->headers     = new \WpOrg\Requests\Response\Headers( array( 'content-type' => 'text/html; charset=utf-8' ) );
		$requests_response->body        = '<!DOCTYPE html><html><body>YouTube</body></html>';
		$http_response                  = new WP_HTTP_Requests_Response( $requests_response, isset( $parsed_args['filename'] ) ? $parsed_args['filename'] : null );
		return array_merge( $http_response->to_array(), array( 'http_response' => $http_response ) );
	}

	/**
	 * Save the list of videos, which the simulated YouTube API returns for the channel.
	 *
	 * @param array<int,string> $video_ids List of video IDs.
	 *
	 * @return void
	 */
	private function set_videos( array $video_ids ): void {
		$server = TestServers::get( 'http', $this );
		if ( empty( $this->api_url ) ) {
			$directory     = TestServers::create_directory( $server );
			$this->api_url = TestServers::get_url( $server, '/files' . $directory . 'search.json' ) . '?channelId=';
		}

		$items = array();
		foreach ( $video_ids as $video_id ) {
			$items[] = array( 'id' => array( 'videoId' => $video_id ) );
		}
		$path = $server['root'] . wp_parse_url( $this->api_url, PHP_URL_PATH );
		file_put_contents( str_replace( '/files/', '/', $path ), wp_json_encode( array( 'pageInfo' => array( 'totalResults' => count( $items ) ), 'items' => $items ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Return the URLs of the videos assigned to the given external source.
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return array<int,string>
	 */
	private function get_videos_of_source( int $term_id ): array {
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
			$this->assertFalse( $external_file_obj->is_locally_saved() );
			$urls[] = $external_file_obj->get_url( true );
		}
		sort( $urls );
		return $urls;
	}

	/**
	 * Test the synchronization of a YouTube channel via cron, without a user.
	 *
	 * @return void
	 */
	public function test_synchronization_via_cron(): void {
		$this->set_videos( array( 'video000001', 'video000002' ) );
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );

		// run like a scheduled synchronization: via cron, without a user.
		wp_set_current_user( 0 );
		add_filter( 'wp_doing_cron', '__return_true' );
		Youtube::get_instance()->init();

		// the external source is the channel, its URL is created like the schedule does it.
		$term_id = Taxonomy::get_instance()->add( 'youtube', 'UCtestchannel', array() );
		$this->assertGreaterThan( 0, $term_id );
		$url = Youtube::get_instance()->get_url( 'UCtestchannel' );
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// the videos of the channel are imported as external files.
		$this->assertSame( array( 'https://www.youtube.com/watch?v=video000001', 'https://www.youtube.com/watch?v=video000002' ), $this->get_videos_of_source( $term_id ) );

		// a removed video is deleted with the next synchronization, a new one is imported.
		$this->set_videos( array( 'video000002', 'video000003' ) );
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( array( 'https://www.youtube.com/watch?v=video000002', 'https://www.youtube.com/watch?v=video000003' ), $this->get_videos_of_source( $term_id ) );
	}
}
