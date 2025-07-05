<?php
/**
 * File which handle different file types.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Plugin\Transients;

/**
 * Object to handle different file types.
 */
class File_Types {

	/**
	 * Instance of actual object.
	 *
	 * @var ?File_Types
	 */
	private static ?File_Types $instance = null;

	/**
	 * List of files.
	 *
	 * @var array<int,File_Types_Base>
	 */
	private array $files = array();

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
	 * @return File_Types
	 */
	public static function get_instance(): File_Types {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'add_settings' ), 20 );
	}

	/**
	 * Add settings for file types.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get settings object.
		$settings_obj = \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance();

		// get main settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add file types tab.
		$file_types_tab = $settings_page->add_tab( 'eml_file_types', 30 );
		$file_types_tab->set_title( __( 'File types', 'external-files-in-media-library' ) );
		$file_types_tab->set_hide_save( true );

		// the audio tab.
		$audio_tab = $file_types_tab->add_tab( 'eml_audio', 10 );
		$audio_tab->set_title( __( 'Audio', 'external-files-in-media-library' ) );

		// the images tab.
		$images_tab = $file_types_tab->add_tab( 'eml_images', 20 );
		$images_tab->set_title( __( 'Images', 'external-files-in-media-library' ) );
		$file_types_tab->set_default_tab( $images_tab );

		// the video tab.
		$video_tab = $file_types_tab->add_tab( 'eml_video', 30 );
		$video_tab->set_title( __( 'Videos', 'external-files-in-media-library' ) );

		// the audio section.
		$audio_tab_audios = $audio_tab->add_section( 'settings_section_audio', 10 );
		$audio_tab_audios->set_title( __( 'Audio Settings', 'external-files-in-media-library' ) );
		$audio_tab_audios->set_callback( array( Settings::get_instance(), 'show_protocol_hint' ) );
		$audio_tab_audios->set_setting( $settings_obj );

		// the images section.
		$images_tab_images = $images_tab->add_section( 'settings_section_images', 10 );
		$images_tab_images->set_title( __( 'Images Settings', 'external-files-in-media-library' ) );
		$images_tab_images->set_callback( array( Settings::get_instance(), 'show_protocol_hint' ) );
		$images_tab_images->set_setting( $settings_obj );

		// the videos section.
		$videos_tab_videos = $video_tab->add_section( 'settings_section_images', 10 );
		$videos_tab_videos->set_title( __( 'Video Settings', 'external-files-in-media-library' ) );
		$videos_tab_videos->set_callback( array( Settings::get_instance(), 'show_protocol_hint' ) );
		$videos_tab_videos->set_setting( $settings_obj );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_images_mode' );
		$setting->set_section( $images_tab_images );
		$setting->set_type( 'string' );
		$setting->set_default( 'external' );
		$field = new Select();
		$field->set_title( __( 'Mode for image handling', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how external images are handled.', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				'external' => __( 'host them extern', 'external-files-in-media-library' ),
				'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_audio_mode' );
		$setting->set_section( $audio_tab_audios );
		$setting->set_type( 'string' );
		$setting->set_default( 'external' );
		$field = new Select();
		$field->set_title( __( 'Mode for audio handling', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how external audios are handled.', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				'external' => __( 'host them extern', 'external-files-in-media-library' ),
				'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_audio_proxy' );
		$setting->set_section( $audio_tab_audios );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable proxy for audios', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This option is only available if audios are hosted external. If this option is disabled, external audios will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the audios locally.', 'external-files-in-media-library' ) );
		$field->set_readonly( 'external' !== get_option( 'eml_video_mode', '' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_audio_proxy_max_age' );
		$setting->set_section( $audio_tab_audios );
		$setting->set_type( 'integer' );
		$setting->set_default( 24 );
		$field = new Number();
		$field->set_title( __( 'Max age for cached audio in proxy in hours', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how long audios, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_proxy' );
		$setting->set_section( $images_tab_images );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$setting->set_save_callback( array( $this, 'update_proxy_setting' ) );
		$field = new Checkbox();
		$field->set_title( __( 'Enable proxy for images', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This option is only available if images are hosted external. If this option is disabled, external images will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the images locally.', 'external-files-in-media-library' ) );
		$field->set_readonly( 'external' !== get_option( 'eml_images_mode', '' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_proxy_max_age' );
		$setting->set_section( $images_tab_images );
		$setting->set_type( 'integer' );
		$setting->set_default( 24 );
		$field = new Number();
		$field->set_title( __( 'Max age for cached images in proxy in hours', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how long images, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_video_mode' );
		$setting->set_section( $videos_tab_videos );
		$setting->set_type( 'string' );
		$setting->set_default( 'external' );
		$field = new Select();
		$field->set_title( __( 'Mode for video handling', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how external video are handled.', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				'external' => __( 'host them extern', 'external-files-in-media-library' ),
				'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_video_proxy' );
		$setting->set_section( $videos_tab_videos );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable proxy for videos', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This option is only available if videos are hosted external. If this option is disabled, external videos will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the videos locally.', 'external-files-in-media-library' ) );
		$field->set_readonly( 'external' !== get_option( 'eml_video_mode', '' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_video_proxy_max_age' );
		$setting->set_section( $videos_tab_videos );
		$setting->set_type( 'integer' );
		$setting->set_default( 24 * 7 );
		$field = new Number();
		$field->set_title( __( 'Max age for cached video in proxy in hours', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how long videos, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );
	}

	/**
	 * Return list of supported file types.
	 *
	 * @return array<string>
	 */
	public function get_file_types(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Audio',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\File',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Image',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Video',
		);

		/**
		 * Filter the list of available file types.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string> $list List of file type handler.
		 */
		return apply_filters( 'eml_file_types', $list );
	}

	/**
	 * Return the file handler for the given file object.
	 *
	 * This can be used before an external file object for this URL exist.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return File_Types_Base
	 */
	public function get_type_object_for_file_obj( File $external_file_obj ): File_Types_Base {
		// use cached object.
		if ( ! empty( $this->files[ $external_file_obj->get_id() ] ) ) {
			return $this->files[ $external_file_obj->get_id() ];
		}

		// get the file type object.
		return $this->get_type_object_by_mime_type( $external_file_obj->get_mime_type(), $external_file_obj );
	}

	/**
	 * Get the file type object by given URL and mime type.
	 *
	 * @param string     $mime_type The mime type.
	 * @param false|File $external_file_obj The external file object or simply false.
	 *
	 * @return File_Types_Base
	 */
	public function get_type_object_by_mime_type( string $mime_type, false|File $external_file_obj = false ): File_Types_Base {
		// bail with default file object if mime type is not given.
		if ( empty( $mime_type ) ) {
			// return the default file object if nothing matches.
			return new File_Types\File( $external_file_obj );
		}

		// use cached object.
		if ( $external_file_obj && ! empty( $this->files[ $external_file_obj->get_id() ] ) ) {
			return $this->files[ $external_file_obj->get_id() ];
		}

		// check each file type for compatibility with the given file.
		foreach ( $this->get_file_types() as $file_type ) {
			// bail if object does not exist.
			if ( ! class_exists( $file_type ) ) {
				continue;
			}

			// get the object.
			$file_type_obj = new $file_type( $external_file_obj );

			// bail if object is not of File_Types_Base.
			if ( ! $file_type_obj instanceof File_Types_Base ) {
				continue;
			}

			// set the mime type.
			$file_type_obj->set_mime_type( $mime_type );

			// bail if given file does not match.
			if ( ! $file_type_obj->is_file_compatible() ) {
				continue;
			}

			// log this event.
			/* translators: %1$s will be replaced by the type name (e.g. "Images"). */
			Log::get_instance()->create( sprintf( __( 'File under this URL has the type %1$s.', 'external-files-in-media-library' ), '<i>' . $file_type_obj->get_name() . '</i>' ), $external_file_obj ? $external_file_obj->get_url( true ) : '', 'info', 2 );

			// add to the list.
			if ( $external_file_obj ) {
				$this->files[ $external_file_obj->get_id() ] = $file_type_obj;
			}

			// return this object.
			return $file_type_obj;
		}

		// log this event.
		Log::get_instance()->create( __( 'File type could not be detected. Fallback to general file.', 'external-files-in-media-library' ), $external_file_obj ? $external_file_obj->get_url( true ) : '', 'info', 1 );

		// get object.
		$file_type_obj = new File_Types\File( $external_file_obj );

		// add to the cache list.
		if ( $external_file_obj ) {
			$this->files[ $external_file_obj->get_id() ] = $file_type_obj;
		}

		// return the default file object if nothing matches.
		return $file_type_obj;
	}

	/**
	 * Check the change of proxy-setting.
	 *
	 * @param string $new_value The old value.
	 * @param string $old_value The new value.
	 *
	 * @return int
	 */
	public function update_proxy_setting( string $new_value, string $old_value ): int {
		// convert the values.
		$new_value_int = absint( $new_value );
		$old_value_int = absint( $old_value );

		// bail if value has not been changed.
		if ( $new_value_int === $old_value_int ) {
			return $old_value_int;
		}

		// show hint to reset the proxy-cache.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'eml_proxy_changed' );
		$transient_obj->set_message( '<strong>' . __( 'The proxy state has been changed.', 'external-files-in-media-library' ) . '</strong> ' . __( 'We recommend emptying the cache of the proxy. Click on the button below to do this.', 'external-files-in-media-library' ) . '<br><a href="#" class="button button-primary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Settings::get_instance()->get_proxy_reset_dialog() ) . '">' . esc_html__( 'Reset now', 'external-files-in-media-library' ) . '</a>' );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// return the new value.
		return $new_value_int;
	}
}
