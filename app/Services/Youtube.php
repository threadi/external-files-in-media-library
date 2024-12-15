<?php
/**
 * File to handle support for YouTube videos.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Button;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Table;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Text;
use ExternalFilesInMediaLibrary\Plugin\Settings\Settings;
use ExternalFilesInMediaLibrary\Plugin\Templates;
use ExternalFilesInMediaLibrary\Plugin\Transients;

/**
 * Object to handle support for this video plattform.
 */
class Youtube {

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
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// add settings.
		add_action( 'init', array( $this, 'init_youtube' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_script' ) );

		// use our own hooks.
		add_filter( 'eml_filter_url_response', array( $this, 'get_video_data' ), 10, 2 );
		add_filter( 'eml_file_prevent_proxied_url', array( $this, 'prevent_proxied_url' ), 10, 2 );

		// change handling of media files.
		add_filter( 'render_block', array( $this, 'render_video_block' ), 10, 2 );
		add_filter( 'media_send_to_editor', array( $this, 'get_video_shortcode' ), 10, 2 );
		add_shortcode( 'eml_youtube', array( $this, 'render_video_shortcode' ) );

		// add AJAX-endpoints.
		add_action( 'wp_ajax_eml_youtube_add_channel', array( $this, 'add_channel_by_ajax' ) );

		// add action endpoints.
		add_action( 'admin_action_eml_youtube_delete_channel', array( $this, 'delete_channel_by_request' ) );
		add_action( 'admin_action_eml_youtube_import_channel', array( $this, 'import_channel_by_request' ) );

		// add WP CLI.
		add_action( 'cli_init', array( $this, 'add_cli' ) );
	}

	/**
	 * Add YouTube settings.
	 *
	 * @return void
	 */
	public function init_youtube(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// add Youtube tab in settings.
		$youtube_tab = $settings_obj->add_tab( 'youtube' );
		$youtube_tab->set_title( __( 'YouTube', 'external-files-in-media-library' ) );

		// add section for settings in this tab.
		$youtube_tab_api = $youtube_tab->add_section( 'section_youtube_api' );
		$youtube_tab_api->set_title( __( 'API Credentials', 'external-files-in-media-library' ) );

		// add field for API key.
		$setting = $settings_obj->add_setting( 'eml_youtube_api_key' );
		$setting->set_section( $youtube_tab_api );
		$setting->set_type( 'string' );
		$setting->set_help( __( 'Add the API key you want to use to get video data from YouTube channels. Get your API key <a href="https://developers.google.com/youtube/v3/getting-started" target="_blank">as described here (opens new window)</a>', 'external-files-in-media-library' ) );
		$field = new Text();
		$field->set_title( __( 'API key', 'external-files-in-media-library' ) );
		$field->set_description( $setting->get_help() );
		$setting->set_field( $field );

		// add section for channels in this tab.
		$youtube_tab_channels = $youtube_tab->add_section( 'section_youtube_channels' );
		$youtube_tab_channels->set_title( __( 'Channels', 'external-files-in-media-library' ) );

		// create dialog to add a channel.
		$dialog = array(
			'title'   => __( 'Add YouTube channel', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Add the channel ID you want to use to import videos:', 'external-files-in-media-library' ) . '</strong></p>',
				'<input type="text" id="youtube_channel_id" name="youtube_channel_id">',
			),
			'buttons' => array(
				array(
					'action'  => 'efml_add_youtube_videos();',
					'variant' => 'primary',
					'text'    => __( 'Add', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_youtube_add_channel' );
		$setting->set_section( $youtube_tab_channels );
		$setting->set_autoload( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Add YouTube channel', 'external-files-in-media-library' ) );
		$field->set_button_title( __( 'Add', 'external-files-in-media-library' ) );
		$field->add_class( ! empty( get_option( 'eml_youtube_api_key' ) ) ? 'easy-dialog-for-wordpress' : '' );
		$field->set_custom_attributes( array( 'data-dialog' => wp_json_encode( $dialog ) ) );
		$field->set_readonly( empty( get_option( 'eml_youtube_api_key' ) ) );
		$setting->set_field( $field );

		// define action list for entry in YouTube channel list.
		$youtube_channel_list_actions = array(
			array(
				'url'  => add_query_arg(
					array(
						'action' => 'eml_youtube_delete_channel',
						'nonce'  => wp_create_nonce( 'eml-youtube-delete-channel' ),
					),
					get_admin_url() . 'admin.php'
				),
				'icon' => '<span class="dashicons dashicons-trash" title="' . esc_attr__( 'Delete entry', 'external-files-in-media-library' ) . '"></span>',
			),
		);
		if ( ! empty( get_option( 'eml_youtube_api_key' ) ) ) {
			$youtube_channel_list_actions[] = array(
				'url'  => add_query_arg(
					array(
						'action' => 'eml_youtube_import_channel',
						'nonce'  => wp_create_nonce( 'eml-youtube-import-channel' ),
					),
					get_admin_url() . 'admin.php'
				),
				'icon' => '<span class="dashicons dashicons-database-import" title="' . esc_attr__( 'Import videos on this channel', 'external-files-in-media-library' ) . '"></span>',
			);
		}

		// show list of configured YouTube channels.
		$setting = $settings_obj->add_setting( 'eml_youtube_channels' );
		$setting->set_section( $youtube_tab_channels );
		$setting->set_type( 'array' );
		$field = new Table();
		$field->set_title( __( 'Youtube channels', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This YouTube channels will be used to import video URLs in your media library.', 'external-files-in-media-library' ) );
		$field->set_table_options( $youtube_channel_list_actions );
		$field->set_readonly( empty( get_option( 'eml_youtube_api_key' ) ) );
		$setting->set_field( $field );
	}

	/**
	 * Add import scripts.
	 *
	 * @return void
	 */
	public function add_script(): void {
		// backend-JS.
		wp_enqueue_script(
			'eml-youtube-admin',
			plugins_url( '/admin/youtube.js', EFML_PLUGIN ),
			array( 'jquery' ),
			filemtime( Helper::get_plugin_dir() . '/admin/youtube.js' ),
			true
		);

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-youtube-admin',
			'efmlYoutubeJsVars',
			array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'add_channel_nonce' => wp_create_nonce( 'eml-add-youtube-channel' ),
			)
		);
	}

	/**
	 * Check if given URL during import is a YouTube video and set its data.
	 *
	 * @param array  $results The result as array for file import.
	 * @param string $url The used URL.
	 *
	 * @return array
	 */
	public function get_video_data( array $results, string $url ): array {
		// bail if this is not a YouTube-URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $results;
		}

		// initialize basic array for file data.
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
	 * @param string $block_content The block content.
	 * @param array  $block The block configuration.
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
		$content = ob_get_contents();
		ob_end_clean();

		// return resulting output.
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
	 * @param array  $attributes List of attributes.
	 * @param string $url The given URL.
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
		$video_html = ob_get_contents();
		ob_end_clean();

		// return the HTML-code to output a YouTube Video.
		return $video_html;
	}

	/**
	 * Add custom CLI functions for YouTube handling.
	 *
	 * @return void
	 */
	public function add_cli(): void {
		\WP_CLI::add_command( 'eml', 'ExternalFilesInMediaLibrary\Services\Cli\Youtube' );
	}

	/**
	 * Add single channel to the list.
	 *
	 * @param string $channel_id The channel to add.
	 *
	 * @return void
	 */
	public function add_channel( string $channel_id ): void {
		// get actual list.
		$channels = $this->get_youtube_channels();

		// add channel ID to the list.
		$channels[] = $channel_id;

		// save the list.
		update_option( 'eml_youtube_channels', $channels );
	}

	/**
	 * Add new channel via AJAX.
	 *
	 * @return void
	 */
	public function add_channel_by_ajax(): void {
		// check referer.
		check_ajax_referer( 'eml-add-youtube-channel', 'nonce' );

		// create dialog for response.
		$dialog = array(
			'detail' => array(
				'title'   => __( 'Error during adding YouTube channel', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'The channel could not be added!', 'external-files-in-media-library' ) . '</strong></p>',
				),
				'buttons' => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			),
		);

		// get the channel ID from request.
		$channel_id = filter_input( INPUT_POST, 'channel_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no channel is given.
		if ( empty( $channel_id ) ) {
			wp_send_json( $dialog );
		}

		// add the channel to the list.
		$this->add_channel( $channel_id );

		// create return dialog.
		$dialog['detail']['title']                = __( 'YouTube channel added', 'external-files-in-media-library' );
		$dialog['detail']['texts'][0]             = '<p>' . __( 'The channel ID has been added to the list.', 'external-files-in-media-library' ) . '</p>';
		$dialog['detail']['buttons'][0]['action'] = 'location.reload();';

		// return the dialog.
		wp_send_json( $dialog );
	}

	/**
	 * Delete single channel from list.
	 *
	 * @param string $channel_id The channel to delete.
	 *
	 * @return void
	 */
	public function delete_channel( string $channel_id ): void {
		// get actual list.
		$channels = $this->get_youtube_channels();

		// get index of searched entry.
		$key = array_search( $channel_id, $channels, true );

		// bail if no entry could be found.
		if ( false === $key ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// delete the key.
		unset( $channels[ $key ] );

		// save the new list.
		update_option( 'eml_youtube_channels', $channels );
	}

	/**
	 * Delete the channel by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_channel_by_request(): void {
		// check referer.
		check_admin_referer( 'eml-youtube-delete-channel', 'nonce' );

		// get channel ID from request.
		$channel_id = filter_input( INPUT_GET, 'item', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no channel is given.
		if ( empty( $channel_id ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		$this->delete_channel( $channel_id );

		// redirect the user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Import videos of given channel by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function import_channel_by_request(): void {
		// check referer.
		check_admin_referer( 'eml-youtube-import-channel', 'nonce' );

		// get channel ID from request.
		$channel_id = filter_input( INPUT_GET, 'item', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no channel is given.
		if ( empty( $channel_id ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get transients object.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'eml_youtube_channel_import' );

		// run the import.
		if ( $this->import_videos_from_channel( $channel_id ) ) {
			$transient_obj->set_type( 'success' );
			$transient_obj->set_message( __( 'The videos of this YouTube channel has been imported. Take a look in the log for details.', 'external-files-in-media-library' ) );
		} else {
			$transient_obj->set_type( 'error' );
			$transient_obj->set_message( __( 'The videos of the YouTube channel could <strong>not</strong> be imported. Take a look in the log for details.', 'external-files-in-media-library' ) );
		}
		$transient_obj->save();

		// redirect the user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Import all videos from given YouTube channel in media library.
	 *
	 * @param string $channel_id The channel ID to use.
	 *
	 * @return bool
	 */
	public function import_videos_from_channel( string $channel_id ): bool {
		// set API key.
		$api_key = get_option( 'eml_youtube_api_key' );

		// bail if no API key is set.
		if ( empty( $api_key ) ) {
			return false;
		}

		// set max results.
		$max_results = 100;

		// create URL to request.
		$youtube_channel_search_url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=' . $channel_id . '&maxResults=' . $max_results . '&key=' . $api_key;

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// get the content from external URL.
		$video_list = $wp_filesystem->get_contents( $youtube_channel_search_url );

		// bail if list is empty.
		if ( empty( $video_list ) ) {
			return false;
		}

		// decode the results to get an array.
		$video_list = json_decode( $video_list, ARRAY_A );

		// bail if no pageInfo returned.
		if ( empty( $video_list['pageInfo'] ) ) {
			return false;
		}

		// bail if array is empty.
		if ( 0 === absint( $video_list['pageInfo']['totalResults'] ) ) {
			return false;
		}

		// get external files object.
		$external_files_obj = Files::get_instance();

		// loop through the results to add each video URL.
		foreach ( $video_list['items'] as $item ) {
			// bail if videoId is missing.
			if ( empty( $item['id']['videoId'] ) ) {
				continue;
			}

			// create URL.
			$url = 'https://www.youtube.com/watch?v=' . $item['id']['videoId'];

			// add this URL to the media library.
			$external_files_obj->add_url( $url );
		}

		// return true if import has been run.
		return true;
	}

	/**
	 * Return list of all configured YouTube channels.
	 *
	 * @return array
	 */
	public function get_youtube_channels(): array {
		// get the configured YouTube channels.
		$channels = get_option( 'eml_youtube_channels' );

		// bail if list is empty.
		if ( empty( $channels ) || empty( $channels[0] ) ) {
			return array();
		}

		// return list of channels.
		return $channels;
	}
}
