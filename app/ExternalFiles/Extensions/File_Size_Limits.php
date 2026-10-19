<?php
/**
 * This file controls the limits for the file size of external files.
 *
 * Limits can be set globally, per service and per file type:
 * - the global limit is used, if no other limit is set.
 * - a limit of the service or the file type replaces the global limit.
 * - if the service and the file type have a limit, the stricter one is used.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easySettingsForWordPress\Fields\Number;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Handler for the limits of the file size of external files.
 */
class File_Size_Limits extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'file_size_limits';

	/**
	 * The extension types.
	 *
	 * @var array<int,string>
	 */
	protected array $extension_types = array( 'import' );

	/**
	 * Cache for the file type of each mime type.
	 *
	 * @var array<string,File_Types_Base>
	 */
	private array $file_types = array();

	/**
	 * Instance of actual object.
	 *
	 * @var File_Size_Limits|null
	 */
	private static ?File_Size_Limits $instance = null;

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
	 * @return File_Size_Limits
	 */
	public static function get_instance(): File_Size_Limits {
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
		// add settings (after the settings of the file types have been added).
		add_action( 'init', array( $this, 'add_settings' ), 30 );
		add_action( 'efml_service_main_settings', array( $this, 'add_service_settings' ), 10, 2 );

		// use our own hooks.
		add_filter( 'efml_file_import_error', array( $this, 'check_file' ), 10, 3 );
		add_filter( 'efml_add_dialog', array( $this, 'add_hint_in_dialog' ), 150, 2 );
		add_filter( 'efml_directory_listing_before_tree_building', array( $this, 'mark_files_in_listing' ), 10, 3 );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Limits for the file size', 'external-files-in-media-library' );
	}

	/**
	 * Add the global settings and the settings for each file type.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add the global settings in the main section of the general settings.
		$general_tab = $settings_page->get_tab( 'eml_general' );
		if ( $general_tab instanceof Tab ) {
			$section = $general_tab->get_section( 'settings_section_main' );
			if ( $section instanceof Section ) {
				$this->add_size_fields(
					$section,
					'eml_import_min_size',
					/* translators: %1$s will be replaced by the hint how the setting is used. */
					sprintf( __( 'External files, which are smaller, will not be added to the media library. Use this, for example, to add only large files as external files. %1$s', 'external-files-in-media-library' ), $this->get_global_hint() ),
					'eml_import_max_size',
					/* translators: %1$s will be replaced by the hint how the setting is used, %2$s by the maximum upload size of WordPress. */
					sprintf( __( 'External files, which are larger, will not be added to the media library. %1$s For comparison: WordPress allows uploads up to %2$s.', 'external-files-in-media-library' ), $this->get_global_hint(), size_format( wp_max_upload_size() ) ),
					false
				);
			}
		}

		// get the tab for the file types.
		$file_types_tab = $settings_page->get_tab( 'eml_file_types' );
		if ( ! $file_types_tab instanceof Tab ) {
			return;
		}

		// add the settings for each file type.
		foreach ( File_Types::get_instance()->get_file_types_as_objects() as $file_type_obj ) {
			// get the internal name.
			$name = strtolower( $file_type_obj->get_name() );

			// get the tab of this file type.
			$tab = $file_types_tab->get_tab( $name );
			if ( ! $tab instanceof Tab ) {
				continue;
			}

			// get the section of this file type.
			$section = $tab->get_section( 'settings_section_' . $name );
			if ( ! $section instanceof Section ) {
				continue;
			}

			// add the settings.
			$this->add_size_fields(
				$section,
				$this->get_file_type_option( $name, 'min' ),
				/* translators: %1$s will be replaced by the file type title (e.g., "Images"), %2$s by the hint how the setting is used. */
				sprintf( __( '%1$s, which are smaller, will not be added to the media library. %2$s', 'external-files-in-media-library' ), $file_type_obj->get_title(), $this->get_specific_hint( 'min', __( 'the service', 'external-files-in-media-library' ) ) ),
				$this->get_file_type_option( $name, 'max' ),
				/* translators: %1$s will be replaced by the file type title (e.g., "Images"), %2$s by the hint how the setting is used. */
				sprintf( __( '%1$s, which are larger, will not be added to the media library. %2$s', 'external-files-in-media-library' ), $file_type_obj->get_title(), $this->get_specific_hint( 'max', __( 'the service', 'external-files-in-media-library' ) ) ),
				false
			);
		}
	}

	/**
	 * Add the settings for a single service in its main section.
	 *
	 * @param Section      $section The main section of the service.
	 * @param Service_Base $service_obj The service.
	 *
	 * @return void
	 */
	public function add_service_settings( Section $section, Service_Base $service_obj ): void {
		// bail if files of this service do not have a file size.
		if ( in_array( $service_obj->get_name(), $this->get_services_without_file_size(), true ) ) {
			return;
		}

		// add the settings.
		$this->add_size_fields(
			$section,
			$this->get_service_option( $service_obj->get_name(), 'min' ),
			/* translators: %1$s will be replaced by the service title (e.g. FTP), %2$s by the hint how the setting is used. */
			sprintf( __( 'Files from %1$s, which are smaller, will not be added to the media library. %2$s', 'external-files-in-media-library' ), $service_obj->get_label(), $this->get_specific_hint( 'min', __( 'the file type', 'external-files-in-media-library' ) ) ),
			$this->get_service_option( $service_obj->get_name(), 'max' ),
			/* translators: %1$s will be replaced by the service title (e.g. FTP), %2$s by the hint how the setting is used. */
			sprintf( __( 'Files from %1$s, which are larger, will not be added to the media library. %2$s', 'external-files-in-media-library' ), $service_obj->get_label(), $this->get_specific_hint( 'max', __( 'the file type', 'external-files-in-media-library' ) ) ),
			$service_obj->is_disabled()
		);
	}

	/**
	 * Add the fields for the minimum and maximum file size in the given section.
	 *
	 * @param Section $section The section.
	 * @param string  $min_option The option name for the minimum size.
	 * @param string  $min_description The description for the minimum size.
	 * @param string  $max_option The option name for the maximum size.
	 * @param string  $max_description The description for the maximum size.
	 * @param bool    $is_readonly Whether the fields are readonly.
	 *
	 * @return void
	 */
	private function add_size_fields( Section $section, string $min_option, string $min_description, string $max_option, string $max_description, bool $is_readonly ): void {
		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// add setting for the minimum size.
		$setting = $settings_obj->add_setting( $min_option );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'Minimum file size (in MB)', 'external-files-in-media-library' ) );
		$field->set_description( $min_description );
		$field->set_min( 0 );
		$field->set_readonly( $is_readonly );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $min_description . '</p>' );

		// add setting for the maximum size.
		$setting = $settings_obj->add_setting( $max_option );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'Maximum file size (in MB)', 'external-files-in-media-library' ) );
		$field->set_description( $max_description );
		$field->set_min( 0 );
		$field->set_readonly( $is_readonly );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $max_description . '</p>' );
	}

	/**
	 * Return the hint how the global setting is used.
	 *
	 * @return string
	 */
	private function get_global_hint(): string {
		return __( 'A setting for a service or a file type replaces this value. Files whose size is not known (e.g. videos from YouTube) are always added. If set to 0, there is no limit.', 'external-files-in-media-library' );
	}

	/**
	 * Return the hint how the setting for a service or file type is used.
	 *
	 * @param string $bound "min" or "max".
	 * @param string $other The other kind of limit (the service or the file type).
	 *
	 * @return string
	 */
	private function get_specific_hint( string $bound, string $other ): string {
		// get the global value as text.
		$global = absint( get_option( 'min' === $bound ? 'eml_import_min_size' : 'eml_import_max_size' ) );
		$global = $global > 0 ? (string) size_format( $global * MB_IN_BYTES ) : __( 'no limit', 'external-files-in-media-library' );

		/* translators: %1$s will be replaced by the global value (e.g. "50 MB"), %2$s by the other kind of limit (e.g. "the file type"), %3$s by the URL of the global setting. */
		return sprintf( __( 'If set to 0, the <a href="%3$s">global setting</a> is used (actually: %1$s). If %2$s also has a limit, the stricter one is used.', 'external-files-in-media-library' ), $global, $other, esc_url( Settings::get_instance()->get_url( 'eml_general' ) ) );
	}

	/**
	 * Return the option name for the limit of a service.
	 *
	 * @param string $service The service name.
	 * @param string $bound "min" or "max".
	 *
	 * @return string
	 */
	public function get_service_option( string $service, string $bound ): string {
		return 'eml_service_' . sanitize_key( $service ) . '_import_' . $bound . '_size';
	}

	/**
	 * Return the option name for the limit of a file type.
	 *
	 * @param string $file_type The internal name of the file type (e.g. "images").
	 * @param string $bound "min" or "max".
	 *
	 * @return string
	 */
	public function get_file_type_option( string $file_type, string $bound ): string {
		return 'eml_file_type_' . sanitize_key( $file_type ) . '_import_' . $bound . '_size';
	}

	/**
	 * Return the services, whose files do not have a file size. They do not get a setting.
	 *
	 * @return array<int,string>
	 */
	private function get_services_without_file_size(): array {
		$list = array( 'youtube', 'vimeo' );

		/**
		 * Filter the list of services, whose files do not have a file size. They do not get settings for the
		 * limits of the file size.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param array<int,string> $list List of service names.
		 */
		return apply_filters( 'efml_file_size_limits_services_without_size', $list );
	}

	/**
	 * Return the file type object for the given mime type, without any log entries.
	 *
	 * @param string $mime_type The mime type.
	 *
	 * @return File_Types_Base|false
	 */
	private function get_file_type( string $mime_type ): File_Types_Base|false {
		// bail if mime type is unknown.
		if ( empty( $mime_type ) ) {
			return false;
		}

		// use the cached object.
		if ( isset( $this->file_types[ $mime_type ] ) ) {
			return $this->file_types[ $mime_type ];
		}

		// check each file type, the general file type is the fallback.
		$result = false;
		foreach ( File_Types::get_instance()->get_file_types_as_objects() as $file_type_obj ) {
			$file_type_obj->set_mime_type( $mime_type );
			if ( $file_type_obj->is_file_compatible() ) {
				$result = $file_type_obj;
				break;
			}
			if ( 'file' === strtolower( $file_type_obj->get_name() ) ) {
				$result = $file_type_obj;
			}
		}

		// bail if no file type could be found.
		if ( ! $result instanceof File_Types_Base ) {
			return false;
		}

		// add to the cache and return the object.
		$this->file_types[ $mime_type ] = $result;
		return $result;
	}

	/**
	 * Return the service object for the given name.
	 *
	 * @param string $service The service name.
	 *
	 * @return Directory_Listing_Base|false
	 */
	private function get_service( string $service ): Directory_Listing_Base|false {
		// bail if no service is given.
		if ( empty( $service ) ) {
			return false;
		}

		// return the object.
		return Services::get_instance()->get_service_by_name( $service );
	}

	/**
	 * Return the name of the service used for the actual import.
	 *
	 * The import dialog sends the service with the request. A synchronization (also via cron or WP CLI)
	 * sets the external source on the import object instead, which has the service as type.
	 *
	 * @return string
	 */
	private function get_actual_service(): string {
		// use the service from the request, if given.
		$service = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_string( $service ) && ! empty( $service ) ) {
			return sanitize_key( $service );
		}

		// use the service of the external source, if one is used.
		$term_id = Import::get_instance()->get_term_id();
		if ( $term_id > 0 ) {
			return sanitize_key( (string) get_term_meta( $term_id, 'type', true ) );
		}

		// no service is used.
		return '';
	}

	/**
	 * Return the limits for a file with the given mime type from the given service.
	 *
	 * Each returned limit contains its size in bytes ("size", 0 if there is no limit) and
	 * a description of where it is set ("source", e.g. "setting for Images").
	 *
	 * @param string                     $mime_type The mime type (empty if not known).
	 * @param string                     $service The service name (empty if no service is used).
	 * @param File_Types_Base|false|null $file_type_obj The file type, if it is already known (false for none).
	 *
	 * @return array{min: array{size: int, source: string}, max: array{size: int, source: string}}
	 */
	public function get_limits( string $mime_type, string $service, File_Types_Base|false|null $file_type_obj = null ): array {
		// collect the specific limits.
		$specific = array();

		// add the limits of the service.
		$service_obj = $this->get_service( $service );
		if ( $service_obj instanceof Directory_Listing_Base && ! in_array( $service, $this->get_services_without_file_size(), true ) ) {
			$specific[] = array(
				'min'    => absint( get_option( $this->get_service_option( $service, 'min' ) ) ) * MB_IN_BYTES,
				'max'    => absint( get_option( $this->get_service_option( $service, 'max' ) ) ) * MB_IN_BYTES,
				/* translators: %1$s will be replaced by the service title (e.g. FTP). */
				'source' => sprintf( __( 'setting for %1$s', 'external-files-in-media-library' ), $service_obj->get_label() ),
			);
		}

		// add the limits of the file type.
		if ( is_null( $file_type_obj ) ) {
			$file_type_obj = $this->get_file_type( $mime_type );
		}
		if ( $file_type_obj instanceof File_Types_Base ) {
			$name       = strtolower( $file_type_obj->get_name() );
			$specific[] = array(
				'min'    => absint( get_option( $this->get_file_type_option( $name, 'min' ) ) ) * MB_IN_BYTES,
				'max'    => absint( get_option( $this->get_file_type_option( $name, 'max' ) ) ) * MB_IN_BYTES,
				/* translators: %1$s will be replaced by the file type title (e.g., "Images"). */
				'source' => sprintf( __( 'setting for %1$s', 'external-files-in-media-library' ), $file_type_obj->get_title() ),
			);
		}

		// get the global limits.
		$global_limits = array(
			'min'    => absint( get_option( 'eml_import_min_size' ) ) * MB_IN_BYTES,
			'max'    => absint( get_option( 'eml_import_max_size' ) ) * MB_IN_BYTES,
			'source' => __( 'global setting', 'external-files-in-media-library' ),
		);

		// get the limits.
		$limits = array(
			'min' => $this->get_limit( 'min', $specific, $global_limits ),
			'max' => $this->get_limit( 'max', $specific, $global_limits ),
		);

		/**
		 * Filter the limits for the file size of a single file.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param array{min: array{size: int, source: string}, max: array{size: int, source: string}} $limits The limits (in bytes) with their source.
		 * @param string $mime_type The mime type of the file (empty if not known).
		 * @param string $service The name of the used service (empty if no service is used).
		 */
		return apply_filters( 'efml_file_size_limits', $limits, $mime_type, $service );
	}

	/**
	 * Return a single limit: the stricter of the specific limits or, if none is set, the global limit.
	 *
	 * @param string                                               $bound "min" or "max".
	 * @param array<int,array{min: int, max: int, source: string}> $specific The specific limits.
	 * @param array{min: int, max: int, source: string}            $global_limits The global limits.
	 *
	 * @return array{size: int, source: string}
	 */
	private function get_limit( string $bound, array $specific, array $global_limits ): array {
		// use no limit as default.
		$limit = array(
			'size'   => 0,
			'source' => '',
		);

		// use the stricter of the specific limits.
		foreach ( $specific as $entry ) {
			// get the size of this limit.
			$size = 'min' === $bound ? $entry['min'] : $entry['max'];

			// bail if this limit is not set.
			if ( 0 === $size ) {
				continue;
			}

			// use this limit, if it is the first or stricter than the actual one.
			if ( 0 === $limit['size'] || ( 'max' === $bound && $size < $limit['size'] ) || ( 'min' === $bound && $size > $limit['size'] ) ) {
				$limit = array(
					'size'   => $size,
					'source' => $entry['source'],
				);
			}
		}

		// use the global limit, if no specific limit is set.
		$size = 'min' === $bound ? $global_limits['min'] : $global_limits['max'];
		if ( 0 === $limit['size'] && $size > 0 ) {
			$limit = array(
				'size'   => $size,
				'source' => $global_limits['source'],
			);
		}

		// return the resulting limit.
		return $limit;
	}

	/**
	 * Return the reason why the given file does not match the limits, or an empty string if it matches.
	 *
	 * @param array<string,mixed> $file_data The file data.
	 * @param string              $service The service name (empty if no service is used).
	 * @param bool                $short True to get the short text for the directory listing.
	 *
	 * @return string
	 */
	public function get_error( array $file_data, string $service, bool $short = false ): string {
		// get the file size.
		$file_size = $this->get_file_size( $file_data );

		// get the limits.
		$limits = $this->get_limits( (string) ( $file_data['mime-type'] ?? '' ), $service );

		// bail if no limit is set.
		if ( 0 === $limits['min']['size'] && 0 === $limits['max']['size'] ) {
			return '';
		}

		// get the file name.
		$title = (string) ( $file_data['title'] ?? '' );
		if ( empty( $title ) ) {
			$title = basename( (string) ( $file_data['url'] ?? '' ) );
		}

		// bail if the file size is not known, as we could not compare it then.
		if ( $file_size <= 1 ) {
			// log this event.
			if ( ! $short ) {
				Log::get_instance()->create( __( 'The size of this file is not known, so the limits for the file size could not be checked. The file will be added.', 'external-files-in-media-library' ), (string) ( $file_data['url'] ?? '' ), 'info', 1 );
			}
			return '';
		}

		// check the maximum size.
		if ( $limits['max']['size'] > 0 && $file_size > $limits['max']['size'] ) {
			if ( $short ) {
				/* translators: %1$s will be replaced by the file size, %2$s by the maximum size, %3$s by the source of the limit (e.g. "setting for Images"). */
				return sprintf( __( 'Too large: %1$s, allowed are at most %2$s (%3$s).', 'external-files-in-media-library' ), size_format( $file_size, 1 ), size_format( $limits['max']['size'] ), $limits['max']['source'] );
			}

			/* translators: %1$s will be replaced by the file name, %2$s by the file size, %3$s by the maximum size, %4$s by the source of the limit (e.g. "setting for Images"). */
			return sprintf( __( 'The file %1$s is %2$s and has not been added to the media library. Allowed are at most %3$s (%4$s).', 'external-files-in-media-library' ), '<em>' . esc_html( $title ) . '</em>', size_format( $file_size, 1 ), size_format( $limits['max']['size'] ), $limits['max']['source'] );
		}

		// check the minimum size.
		if ( $limits['min']['size'] > 0 && $file_size < $limits['min']['size'] ) {
			if ( $short ) {
				/* translators: %1$s will be replaced by the file size, %2$s by the minimum size, %3$s by the source of the limit (e.g. "setting for Images"). */
				return sprintf( __( 'Too small: %1$s, required are at least %2$s (%3$s).', 'external-files-in-media-library' ), size_format( $file_size, 1 ), size_format( $limits['min']['size'] ), $limits['min']['source'] );
			}

			/* translators: %1$s will be replaced by the file name, %2$s by the file size, %3$s by the minimum size, %4$s by the source of the limit (e.g. "setting for Images"). */
			return sprintf( __( 'The file %1$s is %2$s and has not been added to the media library. Required are at least %3$s (%4$s).', 'external-files-in-media-library' ), '<em>' . esc_html( $title ) . '</em>', size_format( $file_size, 1 ), size_format( $limits['min']['size'] ), $limits['min']['source'] );
		}

		// the file matches the limits.
		return '';
	}

	/**
	 * Return the size of the file from its data, or from its temporary file, if the size is not known otherwise.
	 *
	 * @param array<string,mixed> $file_data The file data.
	 *
	 * @return int
	 */
	private function get_file_size( array $file_data ): int {
		// get the size from the data.
		$file_size = absint( $file_data['filesize'] ?? 0 );

		// use the size of the temporary file, if the size is not known.
		if ( $file_size <= 1 && ! empty( $file_data['tmp-file'] ) && is_string( $file_data['tmp-file'] ) && file_exists( $file_data['tmp-file'] ) ) {
			$file_size = absint( Helper::get_wp_filesystem()->size( $file_data['tmp-file'] ) );
		}

		// return the size.
		return $file_size;
	}

	/**
	 * Check a single file before it is loaded or saved during an import.
	 *
	 * @param string              $error The error text from other checks.
	 * @param array<string,mixed> $file_data The file data.
	 * @param string              $url The URL used for the import.
	 *
	 * @return string
	 */
	public function check_file( string $error, array $file_data, string $url ): string {
		// bail if an error is already given.
		if ( ! empty( $error ) ) {
			return $error;
		}

		// return the result of our check.
		return $this->get_error( $file_data, $this->get_actual_service() );
	}

	/**
	 * Mark the files in the directory listing of a service, which do not match the limits.
	 *
	 * @param array<int|string,mixed> $directory_list The list of directories with their files.
	 * @param string                  $directory The requested directory.
	 * @param string                  $name The service name.
	 *
	 * @return array<int|string,mixed>
	 */
	public function mark_files_in_listing( array $directory_list, string $directory, string $name ): array {
		// check each directory.
		foreach ( $directory_list as $path => $entry ) {
			// bail if this entry has no files.
			if ( ! is_array( $entry ) || empty( $entry['files'] ) || ! is_array( $entry['files'] ) ) {
				continue;
			}

			// check each file.
			foreach ( $entry['files'] as $index => $file ) {
				// bail if file is not an array.
				if ( ! is_array( $file ) ) {
					continue;
				}

				// get the error.
				$error = $this->get_error( $file, $name, true );

				// bail if file matches the limits.
				if ( empty( $error ) ) {
					continue;
				}

				// mark the file.
				$directory_list[ $path ]['files'][ $index ]['notice']   = $error;
				$directory_list[ $path ]['files'][ $index ]['disabled'] = true;
			}
		}

		// return the resulting list.
		return $directory_list;
	}

	/**
	 * Return the text which describes the allowed sizes.
	 *
	 * @param array{size: int, source: string} $min The minimum limit.
	 * @param array{size: int, source: string} $max The maximum limit.
	 * @param bool                             $with_source Whether to add the source of the limits.
	 *
	 * @return string
	 */
	private function get_limits_text( array $min, array $max, bool $with_source = true ): string {
		// remove the sources, if they should not be shown.
		if ( ! $with_source ) {
			// both limits are set.
			if ( $min['size'] > 0 && $max['size'] > 0 ) {
				/* translators: %1$s will be replaced by the minimum size, %2$s by the maximum size. */
				return sprintf( __( 'from %1$s to %2$s', 'external-files-in-media-library' ), size_format( $min['size'] ), size_format( $max['size'] ) );
			}

			// only the maximum is set.
			if ( $max['size'] > 0 ) {
				/* translators: %1$s will be replaced by the maximum size. */
				return sprintf( __( 'up to %1$s', 'external-files-in-media-library' ), size_format( $max['size'] ) );
			}

			// only the minimum is set.
			if ( $min['size'] > 0 ) {
				/* translators: %1$s will be replaced by the minimum size. */
				return sprintf( __( 'from %1$s', 'external-files-in-media-library' ), size_format( $min['size'] ) );
			}

			// no limit is set.
			return '';
		}

		// both limits are set.
		if ( $min['size'] > 0 && $max['size'] > 0 ) {
			if ( $min['source'] === $max['source'] ) {
				/* translators: %1$s will be replaced by the minimum size, %2$s by the maximum size, %3$s by the source of the limit (e.g. "global setting"). */
				return sprintf( __( 'from %1$s to %2$s (%3$s)', 'external-files-in-media-library' ), size_format( $min['size'] ), size_format( $max['size'] ), $min['source'] );
			}

			/* translators: %1$s will be replaced by the minimum size, %2$s by its source, %3$s by the maximum size, %4$s by its source. */
			return sprintf( __( 'from %1$s (%2$s) to %3$s (%4$s)', 'external-files-in-media-library' ), size_format( $min['size'] ), $min['source'], size_format( $max['size'] ), $max['source'] );
		}

		// only the maximum is set.
		if ( $max['size'] > 0 ) {
			/* translators: %1$s will be replaced by the maximum size, %2$s by the source of the limit (e.g. "global setting"). */
			return sprintf( __( 'up to %1$s (%2$s)', 'external-files-in-media-library' ), size_format( $max['size'] ), $max['source'] );
		}

		// only the minimum is set.
		if ( $min['size'] > 0 ) {
			/* translators: %1$s will be replaced by the minimum size, %2$s by the source of the limit (e.g. "global setting"). */
			return sprintf( __( 'from %1$s (%2$s)', 'external-files-in-media-library' ), size_format( $min['size'] ), $min['source'] );
		}

		// no limit is set.
		return '';
	}

	/**
	 * Show the limits for the file size in the import dialog.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_hint_in_dialog( array $dialog, array $settings ): array {
		// get the used service.
		$service = ! empty( $settings['service'] ) && is_string( $settings['service'] ) ? sanitize_key( $settings['service'] ) : '';

		// get the limits without file type, they are used for all files.
		$limits = $this->get_limits( '', $service, false );

		// collect the texts.
		$texts = array();

		// add the general limits.
		$text = $this->get_limits_text( $limits['min'], $limits['max'] );
		if ( ! empty( $text ) ) {
			/* translators: %1$s will be replaced by the allowed sizes (e.g. "up to 50 MB (global setting)"). */
			$texts[] = sprintf( __( 'Files are only added with a size %1$s.', 'external-files-in-media-library' ), $text );
		}

		// add the file types, whose limits are different.
		$exceptions = array();
		foreach ( File_Types::get_instance()->get_file_types_as_objects() as $file_type_obj ) {
			// get the limits for this file type.
			$type_limits = $this->get_limits( '', $service, $file_type_obj );

			// bail if the limits are the same as the general limits.
			if ( $type_limits['min']['size'] === $limits['min']['size'] && $type_limits['max']['size'] === $limits['max']['size'] ) {
				continue;
			}

			// get the text for the limits of this file type.
			$type_text = $this->get_limits_text( $type_limits['min'], $type_limits['max'], false );
			if ( empty( $type_text ) ) {
				$type_text = __( 'without limit', 'external-files-in-media-library' );
			}

			// add it to the list.
			$exceptions[] = $file_type_obj->get_title() . ' ' . $type_text;
		}
		if ( ! empty( $exceptions ) ) {
			if ( empty( $texts ) ) {
				/* translators: %1$s will be replaced by a list of file types with their allowed sizes. */
				$texts[] = sprintf( __( 'Limits for the file size: %1$s.', 'external-files-in-media-library' ), implode( ', ', $exceptions ) );
			} else {
				/* translators: %1$s will be replaced by a list of file types with their allowed sizes. */
				$texts[] = sprintf( __( 'Different for: %1$s.', 'external-files-in-media-library' ), implode( ', ', $exceptions ) );
			}
		}

		// bail if no limits are set.
		if ( empty( $texts ) ) {
			return $dialog;
		}

		// add a link to the settings, if the user could change them.
		$link = '';
		if ( current_user_can( Settings::get_instance()->get_settings_obj()->get_capability() ) ) {
			$service_obj = $this->get_service( $service );
			$url         = Settings::get_instance()->get_url( 'eml_general' );
			if ( $service_obj instanceof Service_Base && 0 < absint( get_option( $this->get_service_option( $service, 'min' ) ) ) + absint( get_option( $this->get_service_option( $service, 'max' ) ) ) ) {
				$url = Settings::get_instance()->get_url( 'services', 'eml_' . $service );
			}
			$link = ' <a href="' . esc_url( $url ) . '" target="_blank" title="' . esc_attr__( 'Go to the settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
		}

		// add the hint.
		$dialog['texts'][] = '<p class="efml-file-size-limits"><span class="dashicons dashicons-info-outline"></span> ' . esc_html( implode( ' ', $texts ) ) . $link . '</p>';

		// return the resulting dialog.
		return $dialog;
	}
}
