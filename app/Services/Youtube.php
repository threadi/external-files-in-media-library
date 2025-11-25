<?php
/**
 * File to handle support for YouTube videos.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Password;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Templates;
use JsonException;
use WP_Error;
use WP_User;

/**
 * Object to handle support for this video platform.
 */
class Youtube extends Service_Base implements Service {

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
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_youtube';

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
		// use parent initialization.
		parent::init();

		// add settings.
		add_action( 'init', array( $this, 'init_youtube' ), 30 );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title for service.
		$this->title = __( 'Choose video(s) from a Youtube channel', 'external-files-in-media-library' );

		// use our own hooks to allow import of YouTube videos and channels.
		add_filter( 'efml_filter_url_response', array( $this, 'get_video_data' ), 10, 2 );
		add_filter( 'efml_file_prevent_proxied_url', array( $this, 'prevent_proxied_url' ), 10, 2 );
		add_filter( 'efml_http_states', array( $this, 'allow_http_states' ), 10, 2 );
		add_filter( 'eml_http_check_content_type', array( $this, 'do_not_check_content_type' ), 10, 2 );
		add_filter( 'efml_external_files_infos', array( $this, 'import_videos_from_channel_by_import_obj' ), 10, 2 );
		add_filter( 'efml_http_save_local', array( $this, 'do_not_save_local' ), 10, 2 );
		add_filter( 'efml_save_temp_file', array( $this, 'do_not_save_as_temp_file' ), 10, 2 );
		add_filter( 'efml_import_no_external_file', array( $this, 'prevent_local_save_during_import' ), 10, 2 );

		// change handling of media files.
		add_shortcode( 'eml_youtube', array( $this, 'render_video_shortcode' ) );
		add_filter( 'render_block', array( $this, 'render_video_block' ), 10, 2 );
		add_filter( 'media_send_to_editor', array( $this, 'get_video_shortcode' ), 10, 2 );

		// misc.
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );
	}

	/**
	 * Add settings for YouTube support.
	 *
	 * @return void
	 */
	public function init_youtube(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// add new tab for settings.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// add section for file statistics.
		$section = $tab->get_section( 'section_' . $this->get_name() . '_main' );

		// bail if tab does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add setting.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || 'global' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			// add setting.
			$setting = $settings_obj->add_setting( 'eml_youtube_channel_id' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$field = new Text();
			$field->set_title( __( 'Channel ID', 'external-files-in-media-library' ) );
			$field->set_placeholder( __( 'Your Channel ID', 'external-files-in-media-library' ) );
			$setting->set_field( $field );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_youtube_api_key' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Password();
			$field->set_title( __( 'API Key', 'external-files-in-media-library' ) );
			$field->set_placeholder( __( 'Your API Key', 'external-files-in-media-library' ) );
			$setting->set_field( $field );
		}

		// show hint for user settings.
		if ( 'user' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			$setting = $settings_obj->add_setting( 'eml_youtube_credential_location_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo();
			$field->set_title( __( 'Hint', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() ) );
			$setting->set_field( $field );
		}
	}

	/**
	 * Check if given URL during import is a YouTube video and set its data.
	 *
	 * @param array<int,array<string,mixed>> $results The result as array for file import.
	 * @param string                         $url     The used URL.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_video_data( array $results, string $url ): array {
		// get service from request.
		$service = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not set.
		if ( is_null( $service ) ) {
			return $results;
		}

		// bail if service is not ours.
		if ( $this->get_name() !== $service ) {
			return $results;
		}

		// bail if this is not a YouTube-URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $results;
		}

		// bail if given URL is a YouTube channel (we do not import complete channels, just videos).
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
	 * @return array<int,array<string,mixed>>
	 */
	private function get_basic_url_info_for_video( string $url ): array {
		return array(
			array(
				'title'     => basename( $url ),
				'filesize'  => 1,
				'mime-type' => 'video/mp4',
				'local'     => false,
				'url'       => $url,
				'tmp-file'  => '',
			),
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
		if ( ! $external_file_object->is_valid() ) {
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
		if ( ! $external_file_obj->is_valid() ) {
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
		// get fields.
		$fields = $this->get_fields();

		// set the channel ID.
		$directory = $fields['channel_id']['value'];

		// set API key.
		$api_key = $fields['api_key']['value'];

		// set max results.
		$max_results = 100;

		// create URL to request.
		$youtube_channel_search_url = $this->get_api_url() . $directory . '&maxResults=' . $max_results . '&key=' . $api_key;

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the content from external URL.
		$video_list = $wp_filesystem->get_contents( $youtube_channel_search_url );

		// bail if result is "false".
		if ( false === $video_list ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'API Key or channel is unknown or error on YouTube API!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// bail if list is empty.
		if ( empty( $video_list ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'Got empty response from YouTube API.', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// do nothing more.
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

		// bail if no items are returned.
		if ( empty( $video_list['items'] ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'Got no videos from YouTube API.', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// do nothing more.
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
				'last-modified' => Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( strtotime( $item['snippet']['publishedAt'] ) ) ) ),
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file, "fields": config.fields, "term": term } );',
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
					'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": "' . $this->get_channel_url() . '" + url, "fields": config.fields, "term": config.term } );',
					'label'  => __( 'Import all videos', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
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
		// get fields.
		$fields = $this->get_fields();

		// bail if no ID (as directory) is given.
		if ( empty( $fields['channel_id']['value'] ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'Channel ID missing for Youtube channel', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// bail if no key is given.
		if ( empty( $fields['api_key']['value'] ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_youtube', __( 'API Key missing for Youtube channel', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// url to request.
		$youtube_channel_search_url = $this->get_api_url() . $fields['channel_id']['value'] . '&maxResults=1&key=' . $fields['api_key']['value'];

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
	 * @param array<int,array<string,mixed>> $files      The list of files to import.
	 * @param Protocol_Base                  $import_obj The import object.
	 *
	 * @return array<int,array<string,mixed>>
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

		// get fields.
		$fields = $this->get_fields();

		// empty the list of files as we do not import the channel itself as file.
		$files = array();

		// get channel ID.
		$channel_id = $fields['channel_id']['value'];

		// get API key.
		$api_key = $fields['api_key']['value'];

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
			$files[] = $this->get_basic_url_info_for_video( $url )[0];
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

		// show deprecated warning for old hook name.
		$api_url = apply_filters_deprecated( 'eml_youtube_api_url', array( $api_url ), '5.0.0', 'efml_youtube_api_url' );

		/**
		 * Filter the YouTube API URL to use.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $api_url The API URL.
		 */
		return apply_filters( 'efml_youtube_api_url', $api_url );
	}

	/**
	 * Return the YouTube channel URL to use for requests.
	 *
	 * @return string
	 */
	private function get_channel_url(): string {
		$channel_url = $this->channel_url;

		// show deprecated warning for old hook name.
		$channel_url = apply_filters_deprecated( 'eml_youtube_channel_url', array( $channel_url ), '5.0.0', 'efml_youtube_channel_url' );

		/**
		 * Filter the YouTube channel URL to use.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param string $channel_url The API URL.
		 */
		return apply_filters( 'efml_youtube_channel_url', $channel_url );
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

	/**
	 * Prevent local save of YouTube videos during import.
	 *
	 * @param bool   $no_external_object The marker.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function prevent_local_save_during_import( bool $no_external_object, string $url ): bool {
		// bail if used URL is not from YouTube.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $no_external_object;
		}

		// import YouTube videos local.
		return false;
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			// get the prepared values for the fields.
			$values = $this->get_field_values();

			// set the fields.
			$this->fields = array(
				'channel_id' => array(
					'name'        => 'channel_id',
					'type'        => 'text',
					'label'       => __( 'Channel ID', 'external-files-in-media-library' ),
					'placeholder' => __( 'Your channel ID', 'external-files-in-media-library' ),
					'credential'  => true,
					'value'       => $values['channel_id'],
					'readonly'    => ! empty( $values['channel_id'] ),
				),
				'api_key'    => array(
					'name'        => 'api_key',
					'type'        => 'password',
					'label'       => __( 'API Key', 'external-files-in-media-library' ),
					'placeholder' => __( 'Your API Key', 'external-files-in-media-library' ),
					'credential'  => true,
					'value'       => $values['api_key'],
					'readonly'    => ! empty( $values['api_key'] ),
				),
			);
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		// bail if credentials are set.
		if ( $this->has_credentials_set() ) {
			return __( 'Connect to your YouTube Channel', 'external-files-in-media-library' );
		}

		// return the default title.
		return __( 'Enter your credentials', 'external-files-in-media-library' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		// get the fields.
		$has_credentials_set = $this->has_credentials_set();

		// if access token is set in plugin settings.
		if ( $this->is_mode( 'global' ) ) {
			if ( $has_credentials_set && ! current_user_can( 'manage_options' ) ) {
				return __( 'The credentials has already been set by an administrator in the plugin settings. Just connect for show the files.', 'external-files-in-media-library' );
			}

			if ( ! $has_credentials_set && ! current_user_can( 'manage_options' ) ) {
				return __( 'The credentials must be set by an administrator in the plugin settings.', 'external-files-in-media-library' );
			}

			if ( ! $has_credentials_set ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your credentials <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your credentials are already set <a href="%1$s">here</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		// if authentication JSON is set per user.
		if ( $this->is_mode( 'user' ) ) {
			if ( ! $has_credentials_set ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your credentials <a href="%1$s">in your profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your credentials are already set <a href="%1$s">in your profile</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		return __( 'Enter your WebDAV credentials in this form.', 'external-files-in-media-library' );
	}

	/**
	 * Return the values depending on actual mode.
	 *
	 * @return array<string,mixed>
	 */
	private function get_field_values(): array {
		// prepare the return array.
		$values = array(
			'channel_id' => '',
			'api_key'    => '',
		);

		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			$values['channel_id'] = get_option( 'eml_youtube_channel_id', '' );
			$values['api_key']    = Crypt::get_instance()->decrypt( get_option( 'eml_youtube_api_key', '' ) );
		}

		// save it user-specific, if this is enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get the user set on object.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) {
				return array();
			}

			// get the values.
			$values['channel_id'] = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_youtube_channel_id', true ) );
			$values['api_key']    = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_youtube_api_key', true ) );
		}

		// return the resulting list of values.
		return $values;
	}

	/**
	 * Return whether credentials are set in the fields.
	 *
	 * @return bool
	 */
	private function has_credentials_set(): bool {
		// get the fields.
		$fields = $this->get_fields();

		// return whether both credentials are set.
		return ! empty( $fields['channel_id'] ) && ! empty( $fields['api_key'] );
	}

	/**
	 * Show option to connect to WebDav on the user profile.
	 *
	 * @param WP_User $user The WP_User object for the actual user.
	 *
	 * @return void
	 */
	public function add_user_settings( WP_User $user ): void {
		// bail if settings are not user-specific.
		if ( 'user' !== get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		?>
		<h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'YouTube', 'external-files-in-media-library' ); ?></h3>
		<div class="efml-user-settings">
			<?php

			// show settings table.
			$this->get_user_settings_table( absint( $user->ID ) );

			?>
		</div>
		<?php
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array {
		$list = array(
			'youtube_channel_id' => array(
				'label'       => __( 'Channel ID', 'external-files-in-media-library' ),
				'field'       => 'text',
				'placeholder' => __( 'Your Channel ID', 'external-files-in-media-library' ),
			),
			'youtube_api_key'    => array(
				'label'       => __( 'API Key', 'external-files-in-media-library' ),
				'field'       => 'password',
				'placeholder' => __( 'Your API Key', 'external-files-in-media-library' ),
			),
		);

		/**
		 * Filter the list of possible user settings for YouTube.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 */
		return apply_filters( 'efml_service_youtube_user_settings', $list );
	}
}
