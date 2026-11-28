<?php
/**
 * This file controls the option to synchronize also the files of subdirectories.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Fields\Checkbox;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Folder_Structure;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\ExternalFiles\SynchronizationDialog;
use ExternalFilesInMediaLibrary\Plugin\Settings;

/**
 * Handler controls the option to synchronize also the files of subdirectories.
 *
 * Without this extension, each service uses its default during the synchronization: e.g. FTP, SFTP and local
 * directories without subdirectories, DropBox and archives with them. The extension allows to change this globally
 * and for each external source.
 */
class Sync_Subdirectories extends Extension_Base {
	/**
	 * Name of the term meta and of the option with the setting.
	 */
	public const SETTING = 'efml_sync_subdirectories';

	/**
	 * Value for "use subdirectories".
	 */
	public const YES = 'yes';

	/**
	 * Value for "do not use subdirectories".
	 */
	public const NO = 'no';

	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'sync_subdirectories';

	/**
	 * The extension types.
	 *
	 * @var array<int,string>
	 */
	protected array $extension_types = array( 'sync_dialog' );

	/**
	 * The synchronized term ID.
	 *
	 * @var int
	 */
	private int $synced_term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var Sync_Subdirectories|null
	 */
	private static ?Sync_Subdirectories $instance = null;

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
	 * @return Sync_Subdirectories
	 */
	public static function get_instance(): Sync_Subdirectories {
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
		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// bail if this extension for sync is not enabled.
		if ( ! in_array( $this->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'extend_sync_form_in_dialog' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_config' ), 10, 2 );
		add_action( 'efml_before_sync', array( $this, 'add_hooks_before_sync' ), 10, 3 );
		add_action( 'efml_after_sync', array( $this, 'remove_hooks_after_sync' ), 10, 0 );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Synchronize subdirectories', 'external-files-in-media-library' );
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( $settings_obj->get_menu_slug() );
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get the synchronization tab.
		$sync_tab = $settings_page->get_tab( 'synchronization' );
		if ( ! $sync_tab instanceof Tab ) {
			return;
		}

		// add a section.
		$section = $sync_tab->add_section( 'sync_subdirectories', 20 );
		$section->set_title( __( 'Synchronization of subdirectories', 'external-files-in-media-library' ) );
		$section->set_hidden( ! in_array( $this->get_name(), (array) get_option( 'eml_sync_extensions' ), true ) );

		// add setting.
		$setting = $settings_obj->add_setting( self::SETTING );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Synchronize subdirectories', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled, the files of subdirectories are synchronized for each external source, whose service supports this. If disabled, the default of each service is used: e.g. DropBox and archives with subdirectories, FTP, SFTP and local directories without them. You can change this for each external source in its configuration of the synchronization.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
	}

	/**
	 * Return the setting of the given external source: "yes", "no" or empty for the default.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return string
	 */
	public function get_term_setting( int $term_id ): string {
		// get the value.
		$value = (string) get_term_meta( $term_id, self::SETTING, true );

		// return only valid values.
		return in_array( $value, array( self::YES, self::NO ), true ) ? $value : '';
	}

	/**
	 * Return whether the files of subdirectories should be used for the given external source.
	 *
	 * The setting of the external source is used first, then the global setting and then the default of its service.
	 *
	 * @param int  $term_id         The ID of the external source.
	 * @param bool $service_default The default of the service.
	 *
	 * @return bool
	 */
	public function is_enabled_for_term( int $term_id, bool $service_default ): bool {
		// get the setting of the external source.
		$setting = $this->get_term_setting( $term_id );

		// use the setting of the external source, if one is set.
		if ( self::YES === $setting ) {
			return true;
		}
		if ( self::NO === $setting ) {
			return false;
		}

		// use the global setting, if it is enabled.
		if ( 1 === absint( get_option( self::SETTING ) ) ) {
			return true;
		}

		// use the default of the service.
		return $service_default;
	}

	/**
	 * Extend the config dialog for each external source with the setting for subdirectories.
	 *
	 * The setting is only shown, if the service of the external source supports subdirectories.
	 *
	 * @param string $form The HTML code for the form.
	 * @param int    $term_id The used term ID.
	 *
	 * @return string
	 */
	public function extend_sync_form_in_dialog( string $form, int $term_id ): string {
		// bail if the external source does not support subdirectories.
		if ( ! Synchronization::get_instance()->supports_subdirectories( $term_id ) ) {
			return $form;
		}

		// get the actual setting for this term.
		$setting = $this->get_term_setting( $term_id );

		// get the label of the default.
		$default_label = 1 === absint( get_option( self::SETTING ) ) || Synchronization::get_instance()->get_subdirectories_default( $term_id ) ? __( 'Yes', 'external-files-in-media-library' ) : __( 'No', 'external-files-in-media-library' );

		// create the list of options.
		$options      = array(
			/* translators: %1$s will be replaced by "Yes" or "No". */
			''        => sprintf( __( 'Default (%1$s)', 'external-files-in-media-library' ), $default_label ),
			self::YES => __( 'Yes', 'external-files-in-media-library' ),
			self::NO  => __( 'No', 'external-files-in-media-library' ),
		);
		$options_html = '';
		foreach ( $options as $value => $label ) {
			$options_html .= '<option value="' . esc_attr( $value ) . '"' . selected( $setting, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		// add the field to the dialog.
		$form .= '<div><label for="sync_subdirectories">' . esc_html__( 'Synchronize subdirectories:', 'external-files-in-media-library' ) . '</label><select name="sync_subdirectories" id="sync_subdirectories">' . $options_html . '</select> ' . esc_html__( 'If you disable this later, the files of the subdirectories are deleted during the next synchronization, if unused files are deleted after the synchronization.', 'external-files-in-media-library' ) . '</div>';

		// add the option for the folder structure, if a supported folder plugin is active.
		$integrations = Folder_Structure::get_instance()->get_integrations();
		if ( ! empty( $integrations ) ) {
			// get the titles of the folder plugins.
			$titles = array();
			foreach ( $integrations as $integration ) {
				$titles[] = $integration->get_folder_structure_title();
			}

			// add the field.
			$checked = 1 === absint( get_term_meta( $term_id, Folder_Structure::SETTING, true ) ) ? ' checked' : '';
			/* translators: %1$s will be replaced by the names of folder plugins. */
			$form .= '<div><label for="sync_folder_structure"><input type="checkbox" name="sync_folder_structure" id="sync_folder_structure" value="1"' . $checked . '> ' . esc_html( sprintf( __( 'Take over the folder structure in %1$s', 'external-files-in-media-library' ), implode( ', ', $titles ) ) ) . '</label> ' . esc_html__( 'Only used, if subdirectories are synchronized. The folders are created below the folder chosen for this external source and are never deleted automatically.', 'external-files-in-media-library' ) . '</div>';
		}

		// return the resulting form.
		return $form;
	}

	/**
	 * Save our custom configuration for subdirectories on a term.
	 *
	 * @param array<string,mixed> $fields List of fields from request.
	 * @param int                 $term_id The used term ID.
	 *
	 * @return void
	 */
	public function save_sync_config( array $fields, int $term_id ): void {
		// bail if the field is not given (e.g. as the service does not support subdirectories).
		if ( ! isset( $fields['sync_subdirectories'] ) ) {
			return;
		}

		// save the option for the folder structure.
		if ( ! empty( $fields['sync_folder_structure'] ) ) {
			update_term_meta( $term_id, Folder_Structure::SETTING, 1 );
		} else {
			delete_term_meta( $term_id, Folder_Structure::SETTING );
		}

		// get the value.
		$value = (string) $fields['sync_subdirectories'];

		// remove the setting to use the default.
		if ( ! in_array( $value, array( self::YES, self::NO ), true ) ) {
			delete_term_meta( $term_id, self::SETTING );
			return;
		}

		// save it on the term.
		update_term_meta( $term_id, self::SETTING, $value );
	}

	/**
	 * Add hooks before sync of single URL is starting.
	 *
	 * @param string               $url       The used URL.
	 * @param array<string,string> $term_data The term data.
	 * @param int                  $term_id   The used term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hooks_before_sync( string $url, array $term_data, int $term_id ): void {
		// remove the hooks of a previous synchronization in the same request.
		$this->remove_hooks_after_sync();

		// secure the used term ID.
		$this->synced_term_id = $term_id;

		// add the hook to set whether subdirectories are used.
		add_filter( 'efml_include_subdirectories', array( $this, 'include_subdirectories' ), 10, 1 );
	}

	/**
	 * Remove our hooks after the synchronization of a single external source has been run.
	 *
	 * Without this, the settings of the last synchronized external source would also be used
	 * for other imports or synchronizations in the same request.
	 *
	 * @return void
	 */
	public function remove_hooks_after_sync(): void {
		// reset the used term ID.
		$this->synced_term_id = 0;

		// remove our filter.
		remove_filter( 'efml_include_subdirectories', array( $this, 'include_subdirectories' ) );
	}

	/**
	 * Return whether the files of subdirectories should be used for the actual synchronized external source.
	 *
	 * @param bool $service_default The default of the service.
	 *
	 * @return bool
	 */
	public function include_subdirectories( bool $service_default ): bool {
		// bail if term ID is missing.
		if ( 0 === $this->synced_term_id ) {
			return $service_default;
		}

		// return the setting for the external source.
		return $this->is_enabled_for_term( $this->synced_term_id, $service_default );
	}

	/**
	 * Show info about disabled settings.
	 *
	 * @return void
	 */
	public function show_info(): void {
		// bail if extension is enabled.
		if ( in_array( $this->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return;
		}

		// show hint to enable the extension.
		echo esc_html__( 'Enable the extension in the settings above to use these options.', 'external-files-in-media-library' );
	}
}
