<?php
/**
 * File which handle the different file types.
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

/**
 * Object to handle the different file types.
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

		// add settings for each supported file type.
		foreach ( $this->get_file_types_as_objects() as $index => $file_type_obj ) {
			// get the internal name.
			$name = strtolower( $file_type_obj->get_name() );

			// add the sub tab for the file type settings.
			$tab = $file_types_tab->add_tab( $name, $index * 10 );
			$tab->set_title( $file_type_obj->get_title() );

			// set images as default.
			if ( 'images' === $name ) {
				$file_types_tab->set_default_tab( $tab );
			}

			// add the settings section.
			$section = $tab->add_section( 'settings_section_' . $name, 10 );
			/* translators: %1$s will be replaced by the file type title (e.g. "Images"). */
			$section->set_title( sprintf( __( '%1$s settings', 'external-files-in-media-library' ), $file_type_obj->get_title() ) );
			$section->set_callback( array( Settings::get_instance(), 'show_protocol_hint' ) );
			$section->set_setting( $settings_obj );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_' . $name . '_mode' );
			$setting->set_section( $section );
			$setting->set_type( 'string' );
			$setting->set_default( 'external' );
			$field = new Select();
			/* translators: %1$s will be replaced by the file type title (e.g. "Images"). */
			$field->set_title( sprintf( __( 'Mode for %1$s handling', 'external-files-in-media-library' ), $file_type_obj->get_title() ) );
			/* translators: %1$s will be replaced by the file type title (e.g. "Images"). */
			$field->set_description( sprintf( __( 'Defines how external %1$s are handled.', 'external-files-in-media-library' ), $file_type_obj->get_title() ) );
			$field->set_options(
				array(
					'external' => __( 'host them extern', 'external-files-in-media-library' ),
					'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
				)
			);
			$setting->set_field( $field );
			$setting->set_help( '<p>' . $field->get_description() . '</p>' );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_' . $name . '_proxy' );
			$setting->set_section( $section );
			$setting->set_type( 'integer' );
			$setting->set_default( $file_type_obj->is_proxy_default_enabled() ? 1 : 0 );
			$field = new Checkbox();
			/* translators: %1$s will be replaced by the file type title (e.g. "Images"). */
			$field->set_title( sprintf( __( 'Enable proxy for %1$s', 'external-files-in-media-library' ), $file_type_obj->get_title() ) );
			$field->set_description( __( 'This option is only available if these files are hosted external. If this option is disabled, external files of this type will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load these files locally.', 'external-files-in-media-library' ) );
			$field->set_readonly( 'external' !== get_option( 'eml_' . $name . '_mode', '' ) );
			$setting->set_field( $field );
			$setting->set_help( '<p>' . $field->get_description() . '</p>' );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_' . $name . '_proxy_max_age' );
			$setting->set_section( $section );
			$setting->set_type( 'integer' );
			$setting->set_default( $file_type_obj->get_default_proxy_max_age() );
			$field = new Number();
			/* translators: %1$s will be replaced by the file type title (e.g. "Images"). */
			$field->set_title( sprintf( __( 'Max age for cached %1$s in proxy in hours', 'external-files-in-media-library' ), $file_type_obj->get_title() ) );
			$field->set_description( __( 'Defines how long these files, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
			$setting->set_field( $field );
			$setting->set_help( '<p>' . $field->get_description() . '</p>' );
		}
	}

	/**
	 * Return list of supported file types.
	 *
	 * @return array<int,string>
	 */
	public function get_file_types(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Audio',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\File',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Image',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Pdf',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Video',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Zip',
		);

		/**
		 * Filter the list of supported file types.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<int,string> $list List of class names for the file type handlers.
		 */
		return apply_filters( 'eml_file_types', $list );
	}

	/**
	 * Return file types as objects.
	 *
	 * @param false|File|string $external_file_obj The external file object.
	 *
	 * @return array<int,File_Types_Base>
	 */
	private function get_file_types_as_objects( false|File|string $external_file_obj = false ): array {
		// create the list.
		$list = array();

		// add each file type to the list.
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

			// add this file type to the list.
			$list[] = $file_type_obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the file type object by given URL and mime type.
	 *
	 * @param string     $mime_type The mime type.
	 * @param false|File|string $external_file_obj The external file object or simply false.
	 *
	 * @return File_Types_Base
	 */
	public function get_type_object_by_mime_type( string $mime_type, false|File|string $external_file_obj = false ): File_Types_Base {
		// bail with default file object if mime type is not given.
		if ( empty( $mime_type ) ) {
			// return the default file object if nothing matches.
			return new File_Types\File( $external_file_obj );
		}

		// use cached object.
		if ( $external_file_obj instanceof File && ! empty( $this->files[ $external_file_obj->get_id() ] ) ) {
			return $this->files[ $external_file_obj->get_id() ];
		}

		// get the file URL for logging.
		$url = $external_file_obj instanceof File ? $external_file_obj->get_url( true ) : $external_file_obj;

		// check each file type for compatibility with the given file.
		foreach ( $this->get_file_types_as_objects( $external_file_obj ) as $file_type_obj ) {
			// set the mime type.
			$file_type_obj->set_mime_type( $mime_type );

			// bail if given file does not match.
			if ( ! $file_type_obj->is_file_compatible() ) {
				continue;
			}

			// log this event.
			/* translators: %1$s will be replaced by the type name (e.g. "Images"). */
			Log::get_instance()->create( sprintf( __( 'File under this URL has the type %1$s.', 'external-files-in-media-library' ), '<i>' . $file_type_obj->get_name() . '</i>' ), $url, 'info', 2 );

			// add to the list.
			if ( $external_file_obj instanceof File ) {
				$this->files[ $external_file_obj->get_id() ] = $file_type_obj;
			}

			// return this object.
			return $file_type_obj;
		}

		// log this event.
		Log::get_instance()->create( __( 'File type could not be detected. Fallback to general file.', 'external-files-in-media-library' ), $url, 'info', 1 );

		// get the default file object.
		$file_type_obj = new File_Types\File( $external_file_obj );

		// add to the cache list.
		if ( $external_file_obj instanceof File ) {
			$this->files[ $external_file_obj->get_id() ] = $file_type_obj;
		}

		// return the default file object if nothing matches.
		return $file_type_obj;
	}
}
