<?php
/**
 * File to handle support for YouTube videos.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Templates;
use JsonException;
use WP_Error;

/**
 * Object to handle support for this video plattform.
 */
class Youtube extends Directory_Listing_Base implements Service {

	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'youtube';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'YouTube';

	/**
	 * Marker if simple API login is required.
	 *
	 * @var bool
	 */
	protected bool $requires_simple_api = true;

	/**
	 * The API URL.
	 *
	 * @var string
	 */
	private string $api_url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=';

	/**
	 * The YouTube channel URL.
	 *
	 * @var string
	 */
	private string $channel_url = 'https://www.youtube.com/channel/';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Youtube
	 */
	private static ?Youtube $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Youtube
	 */
	public static function get_instance(): Youtube {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// set title for service.
		$this->title = __( 'Choose video from a Youtube channel', 'external-files-in-media-library' );

		// add service.
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );
		add_filter( 'eml_import_fields', array( $this, 'add_option_for_local_import' ) );

		// use our own hooks to allow import of YouTube videos and channels.
		add_filter( 'eml_filter_url_response', array( $this, 'get_video_data' ), 10, 2 );
		add_filter( 'eml_file_prevent_proxied_url', array( $this, 'prevent_proxied_url' ), 10, 2 );
		add_filter( 'eml_http_states', array( $this, 'allow_http_states' ), 10, 2 );
		add_filter( 'eml_http_check_content_type', array( $this, 'do_not_check_content_type' ), 10, 2 );
		add_filter( 'eml_external_files_infos', array( $this, 'import_videos_from_channel_by_import_obj' ), 10, 2 );
		add_filter( 'eml_http_save_local', array( $this, 'do_not_save_local' ), 10, 2 );
		add_filter( 'eml_save_temp_file', array( $this, 'do_not_save_as_temp_file' ), 10, 2 );

		// change handling of media files.
		add_filter( 'render_block', array( $this, 'render_video_block' ), 10, 2 );
		add_filter( 'media_send_to_editor', array( $this, 'get_video_shortcode' ), 10, 2 );
		add_shortcode( 'eml_youtube', array( $this, 'render_video_shortcode' ) );
	}

	/**
	 * Add this object to the list of listing objects.
	 *
	 * @param array<Directory_Listing_Base> $directory_listing_objects List of directory listing objects.
	 *
	 * @return array<Directory_Listing_Base>
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		$directory_listing_objects[] = $this;
		return $directory_listing_objects;
	}

	/**
	 * Add option to import from local directory.
	 *
	 * @param array<int,string> $fields List of import options.
	 *
	 * @return array<int,string>
	 */
	public function add_option_for_local_import( array $fields ): array {
		$fields[] = '<details><summary>' . __( 'Or add from YouTube channel', 'external-files-in-media-library' ) . '</summary><div><label for="eml_youtube"><a href="' . Directory_Listing::get_instance()->get_view_directory_url( $this ) . '" class="button button-secondary">' . esc_html__( 'Add from your YouTube channel', 'external-files-in-media-library' ) . '</a></label></div></details>';
		return $fields;
	}

	/**
	 * Check if given URL during import is a YouTube video and set its data.
	 *
	 * @param array<string,mixed> $results The result as array for file import.
	 * @param string              $url     The used URL.
	 *
	 * @return array<string,mixed>
	 */
	public function get_video_data( array $results, string $url ): array {
		// bail if this is not a YouTube-URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $results;
		}

		// check if given URL is a YouTube channel.
		if ( $this->is_youtube_channel( $url ) ) {
			return $results;
		}

		// initialize basic array for file data.
		return $this->get_basic_url_info_for_video( $url );
	}

	/**
	 * Get the basic URL info for single YouTube-video URL.
	 *
	 * @param string $url The given URL.
	 *
	 * @return array<string,mixed>
	 */
	private function get_basic_url_info_for_video( string $url ): array {
		return array(
			'title'     => basename( $url ),
			'filesize'  => 1,
			'mime-type' => 'video/mp4',
			'local'     => false,
			'url'       => $url,
			'tmp-file'  => '',
		);
	}

	/**
	 * Prevent usage of proxied URL for YouTube URLs.
	 *
	 * @param bool $result The result.
	 * @param File $external_file_object The file object.
	 *
	 * @return bool
	 */
	public function prevent_proxied_url( bool $result, File $external_file_object ): bool {
		// bail if file is not a YouTube-video.
		if ( ! $this->is_youtube_video( $external_file_object->get_url( true ) ) ) {
			return $result;
		}

		// return false to prevent the usage of proxied URLs for this file.
		return false;
	}

	/**
	 * Check if given URL is a YouTube-video.
	 *
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	private function is_youtube_video( string $url ): bool {
		return str_contains( $url, 'youtube.com' );
	}

	/**
	 * Check if given URL is a YouTube-channel.
	 *
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	private function is_youtube_channel( string $url ): bool {
		return str_contains( $url, 'youtube.com' ) && str_contains( $url, '/channel/' );
	}

	/**
	 * Get embed URL for given YouTube URL.
	 *
	 * @source https://stackoverflow.com/questions/19050890/find-youtube-link-in-php-string-and-convert-it-into-embed-code
	 * @param string $youtube_url The given YouTube-URL.
	 *
	 * @return string
	 */
	private function get_embed_url( string $youtube_url ): string {
		// define the regex.
		$short_url_regex = '/youtu.be\/([a-zA-Z0-9_-]+)\??/i';
		$long_url_regex  = '/youtube.com\/((?:embed)|(?:watch))((?:\?v\=)|(?:\/))([a-zA-Z0-9_-]+)/i';

		$youtube_id = false;
		if ( preg_match( $long_url_regex, $youtube_url, $matches ) ) {
			$youtube_id = $matches[ count( $matches ) - 1 ];
		}

		if ( preg_match( $short_url_regex, $youtube_url, $matches ) ) {
			$youtube_id = $matches[ count( $matches ) - 1 ];
		}

		// bail if YouTube ID could not be determined.
		if ( ! $youtube_id ) {
			return '';
		}

		// return the embed URL.
		return '//www.youtube.com/embed/' . $youtube_id;
	}

	/**
	 * Render the Video block to show an external filed YouTube video.
	 *
	 * @param string              $block_content The block content.
	 * @param array<string,mixed> $block The block configuration.
	 *
	 * @return string
	 */
	public function render_video_block( string $block_content, array $block ): string {
		// bail if this is not core/video.
		if ( 'core/video' !== $block['blockName'] ) {
			return $block_content;
		}

		// bail if id is not given.
		if ( empty( $block['attrs']['id'] ) ) {
			return $block_content;
		}

		// get the attachment ID.
		$attachment_id = absint( $block['attrs']['id'] );

		// bail if ID is not given.
		if ( 0 === $attachment_id ) {
			return $block_content;
		}

		// get external file object for this ID.
		$external_file_object = Files::get_instance()->get_file( $attachment_id );

		// bail if file is not an external file.
		if ( ! $external_file_object ) {
			return $block_content;
		}

		// bail if file is not a YouTube file.
		if ( ! $this->is_youtube_video( $external_file_object->get_url( true ) ) ) {
			return $block_content;
		}

		// get embed URL.
		$url = $this->get_embed_url( $external_file_object->get_url( true ) );

		// set sizes.
		$size_w = 560;
		$size_h = 320;

		// get output.
		ob_start();
		?><figure>
		<?php
		require_once Templates::get_instance()->get_template( 'youtube.php' );
		?>
		</figure>
		<?php
		$content = ob_get_clean();

		if ( ! $content ) {
			return '';
		}
		return $content;
	}

	/**
	 * Change return value for Youtube-videos chosen from media library.
	 *
	 * @param string $html The output.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_video_shortcode( string $html, int $attachment_id ): string {
		// get external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj ) {
			return $html;
		}

		// bail if this is not a YouTube video.
		if ( ! $this->is_youtube_video( $external_file_obj->get_url( true ) ) ) {
			return $html;
		}

		// return the YouTube Shortcode.
		return '[eml_youtube]' . $external_file_obj->get_url( true ) . '[/eml_youtube]';
	}

	/**
	 * Render the shortcode to output YouTube videos generated by our own plugin.
	 *
	 * @param array<string,mixed> $attributes List of attributes.
	 * @param string              $url The given URL.
	 *
	 * @return string
	 */
	public function render_video_shortcode( array $attributes, string $url ): string {
		// get external file object by given URL.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );

		// bail if this is not an external file.
		if ( ! $external_file_obj ) {
			return '';
		}

		// get width.
		$size_w = 560;
		if ( ! empty( $attributes['width'] ) ) {
			$size_w = $attributes['width'];
		}

		// get height.
		$size_h = 315;
		if ( ! empty( $attributes['height'] ) ) {
			$size_h = $attributes['height'];
		}

		// get the URL.
		$url = $external_file_obj->get_url( true );

		// get the output of the template.
		ob_start();
		require_once Templates::get_instance()->get_template( 'youtube.php' );
		$content = ob_get_clean();

		if ( ! $content ) {
			return '';
		}
		return $content;
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 * @throws JsonException Could throw exception.
	 */
	public function get_directory_listing( string $directory ): array {
		// set API key.
		$api_key = $this->get_api_key();

		// set max results.
		$max_results = 100;

		// create URL to request.
		$youtube_channel_search_url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=' . $directory . '&maxResults=' . $max_results . '&key=' . $api_key;

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the content from external URL.
		$video_list = $wp_filesystem->get_contents( $youtube_channel_search_url );

		// bail if list is empty.
		if ( empty( $video_list ) ) {
			return array();
		}

		// decode the results to get an array.
		$video_list = json_decode( $video_list, true, 512, JSON_THROW_ON_ERROR );

		// bail if no pageInfo returned.
		if ( empty( $video_list['pageInfo'] ) ) {
			return array();
		}

		// bail if array is empty.
		if ( 0 === absint( $video_list['pageInfo']['totalResults'] ) ) {
			return array();
		}

		// collect the entries for the list.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// loop through the results to add each video URL.
		foreach ( $video_list['items'] as $item ) {

			// bail if videoId is missing.
			if ( empty( $item['id']['videoId'] ) ) {
				continue;
			}

			// create URL.
			$url = 'https://www.youtube.com/watch?v=' . $item['id']['videoId'];

			// set thumbnail to empty as we have none for YouTube channels.
			$thumbnail = '';

			// collect the entry.
			$entry = array(
				'title'         => $item['id']['videoId'],
				'file'          => $url,
				'filesize'      => 0,
				'mime-type'     => 'video/mp4',
				'icon'          => '<span class="dashicons dashicons-youtube"></span>',
				'last-modified' => Helper::get_format_date_time( $item['snippet']['publishedAt'] ),
				'preview'       => $thumbnail,
			);

			// add the entry to the list.
			$listing['files'][] = $entry;
		}

		// return true if import has been run.
		return $listing;
	}

	/**
	 * Return the actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		return array(
			array(
				'action' => 'efml_import_url( file.file, login, password, [], term );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		return array_merge(
			parent::get_global_actions(),
			array(
				array(
					'action' => 'efml_import_url( "' . $this->get_channel_url() . '" + url, url, apiKey, [], config.term );',
					'label'  => __( 'Import all videos', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "youtube", actualDirectoryPath, url, "", apiKey );',
					'label'  => __( 'Save active directory as directory archive', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Check if login with given credentials is valid.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// bail if no ID (as directory) is given.
		if ( empty( $directory ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'Channel ID missing for Youtube channel', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// bail if no key is given.
		if ( empty( $this->get_api_key() ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'API Key missing for Youtube channel', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// url to request.
		$youtube_channel_search_url = $this->get_api_url() . $this->get_login() . '&maxResults=1&key=' . $this->get_api_key();

		// send request to the URL.
		$response = wp_safe_remote_get( $youtube_channel_search_url );

		// get http status.
		$http_status = absint( wp_remote_retrieve_response_code( $response ) );

		// bail if status is not 200.
		if ( 200 !== $http_status ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'The given API credentials are wrong.', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// return true if all is ok.
		return true;
	}

	/**
	 * Extend list of allowed HTTP-states for YouTube URLs.
	 *
	 * @param array<int> $state_list The list of allowed HTTP-states.
	 * @param string     $url The given URL.
	 *
	 * @return array<int>
	 */
	public function allow_http_states( array $state_list, string $url ): array {
		// bail if this is not a YouTube-URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $state_list;
		}

		// add 302 to the list.
		$state_list[] = 302;

		// return the list.
		return $state_list;
	}

	/**
	 * Prevent check for content type.
	 *
	 * @param bool   $return_value The result (true for "check it", false for not).
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	public function do_not_check_content_type( bool $return_value, string $url ): bool {
		// bail if this is not a YouTube-URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $return_value;
		}

		// prevent check for content type.
		return false;
	}

	/**
	 * Import videos from YouTube channel via import object.
	 *
	 * @param array<array<string,mixed>> $files      The list of files to import.
	 * @param Protocol_Base              $import_obj The import object.
	 *
	 * @return array<array<string,mixed>>
	 * @throws JsonException Could throw exception.
	 */
	public function import_videos_from_channel_by_import_obj( array $files, Protocol_Base $import_obj ): array {
		// bail if import object is not HTTP.
		if ( ! $import_obj instanceof HTTP ) {
			return $files;
		}

		// get the used URL.
		$url = $import_obj->get_url();

		// bail if this is not a YouTube channel.
		if ( ! $this->is_youtube_channel( $url ) ) {
			return $files;
		}

		// empty the list of files as we do not import the channel itself as file.
		$files = array();

		// get channel ID.
		$channel_id = $import_obj->get_login();

		// get API key.
		$api_key = $import_obj->get_password();

		// create URL to request.
		$youtube_channel_search_url = $this->get_api_url() . $channel_id . '&maxResults=100&key=' . $api_key;

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the content from external URL.
		$video_list = $wp_filesystem->get_contents( $youtube_channel_search_url );

		// bail if list is empty.
		if ( empty( $video_list ) ) {
			return array();
		}

		// decode the results to get an array.
		$video_list = json_decode( $video_list, true, 512, JSON_THROW_ON_ERROR );

		// bail if no pageInfo returned.
		if ( empty( $video_list['pageInfo'] ) ) {
			return array();
		}

		// bail if array is empty.
		if ( 0 === absint( $video_list['pageInfo']['totalResults'] ) ) {
			return array();
		}

		// loop through the results to add each video URL to the resulting list.
		foreach ( $video_list['items'] as $item ) {
			// bail if videoId is missing.
			if ( empty( $item['id']['videoId'] ) ) {
				continue;
			}

			// create URL.
			$url = 'https://www.youtube.com/watch?v=' . $item['id']['videoId'];

			// bail on duplicate.
			if ( $import_obj->check_for_duplicate( $url ) ) {
				continue;
			}

			// add this file to the list to import.
			$files[] = $this->get_basic_url_info_for_video( $url );
		}

		// return resulting list of files.
		return $files;
	}

	/**
	 * Return the YouTube API URL to use for requests.
	 *
	 * @return string
	 */
	private function get_api_url(): string {
		$api_url = $this->api_url;

		/**
		 * Filter the YouTube API URL to use.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $api_url The API URL.
		 */
		return apply_filters( 'eml_youtube_api_url', $api_url );
	}

	/**
	 * Return the YouTube channel URL to use for requests.
	 *
	 * @return string
	 */
	private function get_channel_url(): string {
		$channel_url = $this->channel_url;

		/**
		 * Filter the YouTube channel URL to use.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param string $channel_url The API URL.
		 */
		return apply_filters( 'eml_youtube_channel_url', $channel_url );
	}

	/**
	 * Return the URL. Possibility to complete it depending on listing method.
	 *
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	public function get_url( string $url ): string {
		return $this->get_channel_url() . $url;
	}

	/**
	 * Return the login from entry config.
	 *
	 * @param array<string,mixed> $config The entry config.
	 *
	 * @return string
	 */
	public function get_login_from_archive_entry( array $config ): string {
		return $config['directory'];
	}

	/**
	 * Return the password from entry config.
	 *
	 * @param array<string,mixed> $config The entry config.
	 *
	 * @return string
	 */
	public function get_password_from_archive_entry( array $config ): string {
		return $config['api_key'];
	}

	/**
	 * Prevent local saving of YouTube URLs.
	 *
	 * @param bool   $result The result.
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	public function do_not_save_local( bool $result, string $url ): bool {
		// bail if given URL is not a YouTube URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $result;
		}

		// return false to prevent local usage.
		return false;
	}

	/**
	 * Prevent saving of YouTube video as temp file.
	 *
	 * @param bool   $result The result.
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	public function do_not_save_as_temp_file( bool $result, string $url ): bool {
		// bail if given URL is not a YouTube URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $result;
		}

		// return false to prevent local usage.
		return false;
	}
}
