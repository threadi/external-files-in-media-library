<?php
/**
 * File to handle support for the plugin "Instant Images".
 *
 * Instant Images downloads images from public platforms (like Unsplash or Pexels) into the media library.
 * With this support, these images are switched to external hosted files right after the download.
 * Instant Images handles the request and its response completely by itself, we only use its hook
 * "instant_images_after_upload", which runs after the attachment has been created.
 *
 * Only platforms which allow the usage of their image URLs on websites (hotlinking) are supported:
 * - Unsplash: hotlinking is required by the API guidelines, the proxy must not be used.
 * - GIPHY: the media URLs must be used directly, the proxy must not be used.
 * - Pexels: hotlinking is not forbidden.
 * Pixabay forbids permanent hotlinking, so its images remain local files.
 *
 * This support must be enabled explicitly by choosing the platforms in the settings. Until then, administrators
 * see a dismissible hint about it. If enabled, the admin page of Instant Images shows a notice about it.
 *
 * @source https://wordpress.org/plugins/instant-images/
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Fields\MultiSelect;
use easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;

/**
 * Object to handle support for this plugin.
 */
class InstantImages extends ThirdParty_Base implements ThirdParty {

	/**
	 * Name of the post meta, which marks a file as one of Instant Images with its provider.
	 *
	 * @var string
	 */
	public const META_PROVIDER = 'efml_instant_images_provider';

	/**
	 * Name of the hint for administrators, that this support could be enabled.
	 *
	 * @var string
	 */
	public const HINT_NAME = 'eml_instant_images_hint';

	/**
	 * ID of the admin page of Instant Images, where images could be added.
	 *
	 * @var string
	 */
	public const SCREEN_ID = 'media_page_instant-images';

	/**
	 * Instance of actual object.
	 *
	 * @var ?InstantImages
	 */
	private static ?InstantImages $instance = null;

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
	 * @return InstantImages
	 */
	public static function get_instance(): InstantImages {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// the proxy must stay disabled for already switched files, even if Instant Images is not active anymore.
		add_filter( 'efml_image_proxy_enabled', array( $this, 'disable_proxy' ), 10, 2 );

		// bail if Instant Images is not active, and remove the hint about this support.
		if ( ! Helper::is_plugin_active( 'instant-images/instant-images.php' ) ) {
			add_action( 'admin_init', array( $this, 'remove_hint' ) );
			return;
		}

		// add settings.
		add_action( 'init', array( $this, 'init_instant_images' ) );

		// use the hook of Instant Images after an image has been saved in the media library.
		add_action( 'instant_images_after_upload', array( $this, 'switch_to_external' ) );

		// show hints about this support.
		add_action( 'admin_init', array( $this, 'show_hint' ) );
		add_action( 'admin_notices', array( $this, 'show_notice_on_instant_images_page' ) );
	}

	/**
	 * Show a hint for administrators, that images of Instant Images could be used as external files.
	 *
	 * The hint is only shown as long as no platform is enabled and could be dismissed for 30 days.
	 *
	 * @return void
	 */
	public function show_hint(): void {
		// get the transients object.
		$transients_obj = Transients::get_instance();

		// remove the hint if any platform is enabled.
		if ( ! empty( $this->get_enabled_providers() ) ) {
			$transients_obj->get_transient_by_name( self::HINT_NAME )->delete();
			return;
		}

		// bail if the user is not allowed to change the settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// bail if the hint is already set.
		if ( $transients_obj->get_transient_by_name( self::HINT_NAME )->is_set() ) {
			return;
		}

		// create the hint.
		$transient_obj = $transients_obj->add();
		$transient_obj->set_name( self::HINT_NAME );
		/* translators: %1$s will be replaced by a list of platforms like "Unsplash and Pexels", %2$s by a URL. */
		$transient_obj->set_message( sprintf( __( '<strong>We detected that you are using Instant Images!</strong> Images from %1$s, which you add with Instant Images, could be used as external files: they are loaded directly from the platform and not saved in your hosting. This is disabled until you choose the platforms <a href="%2$s">in the settings</a>.', 'external-files-in-media-library' ), esc_html( wp_sprintf( '%l', array_values( $this->get_possible_providers() ) ) ), esc_url( Settings::get_instance()->get_url( 'instant_images' ) ) ) );
		$transient_obj->set_dismissible_days( 30 );
		$transient_obj->save();
	}

	/**
	 * Remove the hint for administrators.
	 *
	 * @return void
	 */
	public function remove_hint(): void {
		Transients::get_instance()->get_transient_by_name( self::HINT_NAME )->delete();
	}

	/**
	 * Show a notice on the admin page of Instant Images, if its images are used as external files.
	 *
	 * Only for users whose images are switched, as the switch requires the capability to use external files.
	 *
	 * @return void
	 */
	public function show_notice_on_instant_images_page(): void {
		// bail if this is not the page of Instant Images.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof \WP_Screen || self::SCREEN_ID !== $screen->id ) {
			return;
		}

		// get the notice.
		$notice = $this->get_notice_for_instant_images_page();

		// bail if no notice should be shown.
		if ( '' === $notice ) {
			return;
		}

		// show the notice.
		echo '<div class="notice notice-info"><p>' . wp_kses_post( $notice ) . '</p></div>';
	}

	/**
	 * Return the text of the notice for the admin page of Instant Images.
	 *
	 * Returns an empty string if no notice should be shown.
	 *
	 * @return string
	 */
	public function get_notice_for_instant_images_page(): string {
		// get the enabled platforms.
		$providers = $this->get_enabled_providers();

		// bail if no platform is enabled.
		if ( empty( $providers ) ) {
			return '';
		}

		// bail if the images of this user are not switched.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return '';
		}

		// bail if the settings for images require local saved files.
		if ( File_Types::get_instance()->get_type_object_by_mime_type( 'image/jpeg' )->is_local() ) {
			return '';
		}

		// get the labels of the platforms.
		$possible_providers = $this->get_possible_providers();
		$labels             = array();
		foreach ( $providers as $provider ) {
			$labels[] = $possible_providers[ $provider ];
		}

		// create the text.
		/* translators: %1$s will be replaced by a list of platforms like "Unsplash and Pexels". */
		$notice = sprintf( __( '<strong>Hint:</strong> Images from %1$s, which you add here, are used as external files. They are loaded directly from the platform and are not saved in your hosting.', 'external-files-in-media-library' ), esc_html( wp_sprintf( '%l', $labels ) ) );

		// add the link to the settings for administrators.
		if ( current_user_can( 'manage_options' ) ) {
			$notice .= ' <a href="' . esc_url( Settings::get_instance()->get_url( 'instant_images' ) ) . '">' . __( 'Change this in the settings.', 'external-files-in-media-library' ) . '</a>';
		}

		// return the resulting text.
		return $notice;
	}

	/**
	 * Add the settings for this support.
	 *
	 * @return void
	 */
	public function init_instant_images(): void {
		// get settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add settings tab for Instant Images as sub-tab of the integrations tab.
		$tab = Settings::get_instance()->add_sub_tab( 'eml_integrations', 'instant_images', 30 );

		// bail if tab could not be added.
		if ( ! $tab instanceof \easySettingsForWordPress\Tab ) {
			return;
		}
		$tab->set_title( __( 'Instant Images', 'external-files-in-media-library' ) );

		// add a section for the settings.
		$section = $tab->add_section( 'eml_instant_images_settings', 10 );
		$section->set_title( __( 'Instant Images', 'external-files-in-media-library' ) );

		// add setting for the platforms.
		$setting = $settings_obj->add_setting( 'eml_instant_images_providers' );
		$setting->set_section( $section );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$setting->set_show_in_rest( array( 'schema' => array( 'items' => array( 'type' => 'string' ) ) ) );
		$field = new MultiSelect( $settings_obj );
		$field->set_title( __( 'Use images from these platforms as external files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Images you add with Instant Images from the selected platforms will be used as external files: they are loaded directly from the platform and not saved in your hosting. Pixabay is not available, as it does not allow to use its image URLs permanently. Images from Unsplash and GIPHY are never delivered via the proxy, as these platforms require the usage of their own URLs. Hint: the setting for images must allow external hosting.', 'external-files-in-media-library' ) );
		$field->set_options( $this->get_possible_providers() );
		$setting->set_field( $field );
	}

	/**
	 * Return the list of platforms, which could be used as external source.
	 *
	 * @return array<string,string>
	 */
	public function get_possible_providers(): array {
		$list = array(
			'unsplash' => 'Unsplash',
			'pexels'   => 'Pexels',
			'giphy'    => 'GIPHY',
		);

		/**
		 * Filter the list of platforms of Instant Images, which could be used as external source.
		 *
		 * Only add platforms which allow the permanent usage of their image URLs.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,string> $list List of platforms (slug => label).
		 */
		return apply_filters( 'efml_instant_images_possible_providers', $list );
	}

	/**
	 * Return the list of platforms, which must not be used with the proxy.
	 *
	 * @return array<int,string>
	 */
	public function get_providers_without_proxy(): array {
		$list = array( 'unsplash', 'giphy' );

		/**
		 * Filter the list of platforms of Instant Images, whose images must not be delivered via proxy.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $list List of platform slugs.
		 */
		return apply_filters( 'efml_instant_images_providers_without_proxy', $list );
	}

	/**
	 * Return the list of enabled platforms.
	 *
	 * @return array<int,string>
	 */
	public function get_enabled_providers(): array {
		// get the setting.
		$enabled = get_option( 'eml_instant_images_providers', array() );

		// bail if setting is not a list.
		if ( ! is_array( $enabled ) ) {
			return array();
		}

		// return only the possible platforms.
		return array_values( array_intersect( array_map( 'strval', $enabled ), array_keys( $this->get_possible_providers() ) ) );
	}

	/**
	 * Switch an image, which has been saved by Instant Images, to an external file.
	 *
	 * If anything is not as expected, the image simply remains a local file as Instant Images has saved it.
	 *
	 * @param mixed $data The data from Instant Images.
	 *
	 * @return void
	 */
	public function switch_to_external( mixed $data ): void {
		// bail if data is not an array.
		if ( ! is_array( $data ) ) {
			return;
		}

		// get the values we need.
		$attachment_id = isset( $data['attachment_id'] ) ? absint( $data['attachment_id'] ) : 0;
		$url           = isset( $data['original_url'] ) && is_string( $data['original_url'] ) ? $data['original_url'] : '';
		$provider      = isset( $data['provider'] ) && is_string( $data['provider'] ) ? $data['provider'] : '';

		// bail if any value is missing.
		if ( 0 === $attachment_id || '' === $url || '' === $provider ) {
			return;
		}

		// bail if this platform is not enabled.
		if ( ! in_array( $provider, $this->get_enabled_providers(), true ) ) {
			return;
		}

		// bail if the URL is not a valid HTTPS-URL.
		if ( ! str_starts_with( $url, 'https://' ) || ! wp_http_validate_url( $url ) ) {
			return;
		}

		// bail if the user is not allowed to use external files.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// bail if the attachment is not an attachment.
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is already an external file.
		if ( $external_file_obj->is_valid() ) {
			return;
		}

		// get the local file.
		$local_file = get_attached_file( $attachment_id, true );

		// bail if the local file does not exist.
		if ( ! is_string( $local_file ) || ! file_exists( $local_file ) ) {
			return;
		}

		// get the mime type.
		$mime_type = (string) get_post_mime_type( $attachment_id );

		// bail if the settings for this file type require local saved files.
		if ( File_Types::get_instance()->get_type_object_by_mime_type( $mime_type, $url )->is_local() ) {
			return;
		}

		// get the protocol handler for the URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $url );

		// bail if the URL could not be used with external hosting.
		if ( ! $protocol_handler_obj || ! $protocol_handler_obj->can_change_hosting() ) {
			return;
		}

		// mark this platform on the file (before the switch, as the proxy depends on it).
		update_post_meta( $attachment_id, self::META_PROVIDER, $provider );

		// mark the attachment as external file, which is saved locally at the moment.
		$external_file_obj->set_url( $url );
		$external_file_obj->set_availability( true );
		$external_file_obj->set_filesize( absint( filesize( $local_file ) ) );
		$external_file_obj->set_is_local_saved( true );
		$external_file_obj->set_date();
		$external_file_obj->set_service_name( $protocol_handler_obj->get_name() );

		// switch to external hosting: this deletes the local files and uses the external URL.
		if ( ! $external_file_obj->switch_to_external() ) {
			// log this event.
			Log::get_instance()->create( __( 'The image from Instant Images could not be switched to an external file. It remains saved locally.', 'external-files-in-media-library' ), $url, 'error' );

			// do nothing more.
			return;
		}

		// update the metadata as the local files do not exist anymore.
		$this->update_metadata( $external_file_obj );

		// log this event.
		/* translators: %1$s will be replaced by the name of a platform like Unsplash. */
		Log::get_instance()->create( sprintf( __( 'Image from %1$s via Instant Images is now used as external file.', 'external-files-in-media-library' ), '<em>' . esc_html( $this->get_possible_providers()[ $provider ] ?? $provider ) . '</em>' ), $url, 'success' );
	}

	/**
	 * Update the metadata of the switched file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	private function update_metadata( File $external_file_obj ): void {
		// with proxy, the metadata incl. the sizes is created for the proxy.
		if ( $external_file_obj->get_file_type_obj()->is_proxy_enabled() ) {
			$external_file_obj->set_metadata();
			return;
		}

		// get the metadata.
		$meta_data = wp_get_attachment_metadata( $external_file_obj->get_id(), true );
		if ( ! is_array( $meta_data ) ) {
			$meta_data = array();
		}

		// remove the sizes and the original image, as their local files have been deleted.
		// ("original_image" is set by WordPress for scaled images, but missing in the array shape of the stubs).
		$meta_data = array_diff_key( $meta_data, array_flip( array( 'sizes', 'original_image' ) ) );

		// use the external URL as file.
		$meta_data['file'] = $external_file_obj->get_url( true );

		// save the metadata.
		wp_update_attachment_metadata( $external_file_obj->get_id(), $meta_data );
	}

	/**
	 * Disable the proxy for images of platforms, which require the usage of their own URLs.
	 *
	 * @param bool       $enabled           True if the proxy is enabled.
	 * @param File|false $external_file_obj The external file object, if known.
	 *
	 * @return bool
	 */
	public function disable_proxy( bool $enabled, File|false $external_file_obj ): bool {
		// bail if proxy is already disabled.
		if ( ! $enabled ) {
			return false;
		}

		// bail if no file is given.
		if ( ! $external_file_obj instanceof File ) {
			return true;
		}

		// get the platform of this file.
		$provider = get_post_meta( $external_file_obj->get_id(), self::META_PROVIDER, true );

		// bail if this is not a file of Instant Images.
		if ( empty( $provider ) || ! is_string( $provider ) ) {
			return true;
		}

		// return false if the proxy must not be used for this platform.
		return ! in_array( $provider, $this->get_providers_without_proxy(), true );
	}
}
