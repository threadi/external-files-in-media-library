<?php
/**
 * This file contains the object, which handles the capabilities for this plugin on the roles.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\MultiSelect;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use WP_Role;
use WP_User;

/**
 * Object, which handles the capabilities for this plugin on the roles.
 */
class Roles {
	/**
	 * Own instance
	 *
	 * @var Roles|null
	 */
	private static ?Roles $instance = null;

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
	 * @return Roles
	 */
	public static function get_instance(): Roles {
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
		add_action( 'init', array( $this, 'init_settings' ), 30 );
		add_filter( 'user_has_cap', array( $this, 'check_user_cap' ), 10, 2 );
	}

	/**
	 * Add the role settings.
	 *
	 * @return void
	 */
	public function init_settings(): void {
		// get the settings object.
		$settings_obj = \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( 'eml_settings' );

		// bail if page could not be found.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get the permission tab.
		$permissions_tab = $settings_page->get_tab( 'eml_permissions' );

		// bail if tab could not be found.
		if ( ! $permissions_tab instanceof Tab ) {
			return;
		}

		// get user roles.
		$user_roles = array();
		if ( function_exists( 'wp_roles' ) && ! empty( wp_roles()->roles ) ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				$user_roles[ $slug ] = $role['name'];
			}
		}

		// add the files section.
		$permissions_tab_files = $permissions_tab->add_section( 'settings_section_add_files', 10 );
		$permissions_tab_files->set_title( __( 'Permissions for external files', 'external-files-in-media-library' ) );
		$permissions_tab_files->set_setting( $settings_obj );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_allowed_roles' );
		$setting->set_section( $permissions_tab_files );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator', 'editor' ) );
		$setting->set_save_callback( array( $this, 'set_capabilities' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Add external files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select roles, which should be allowed to add external files.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// get list of users for setting.
		$users_for_setting = $this->get_user_for_settings( true );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_user_forbidden' );
		$setting->set_section( $permissions_tab_files );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		if ( 0 === count( $users_for_setting ) ) {
			$field = new TextInfo();
			$field->set_title( __( 'Prevent access for these users', 'external-files-in-media-library' ) );
			$field->set_description( __( 'No users can be configured here at this moment.', 'external-files-in-media-library' ) );
		} else {
			$field = new MultiSelect();
			$field->set_title( __( 'Prevent access for these users', 'external-files-in-media-library' ) );
			$field->set_description( __( 'Users selected on this list are not allowed to use external files in media library regardless of their role.', 'external-files-in-media-library' ) );
			$field->set_options( $users_for_setting );
		}
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add the tools section.
		$permissions_tab_tools = $permissions_tab->add_section( 'settings_section_tools', 20 );
		$permissions_tab_tools->set_title( __( 'Permissions for tools', 'external-files-in-media-library' ) );
		$permissions_tab_tools->set_setting( $settings_obj );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_user_settings_allowed_roles' );
		$setting->set_section( $permissions_tab_tools );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator', 'editor' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Use custom settings', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select roles, which should be allowed to use custom settings for the import of external URLs. Users with these role can edit their settings on their own profile page in WordPress backend.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_tools_settings_tools_import_allowed_roles' );
		$setting->set_section( $permissions_tab_tools );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator' ) );
		$setting->set_save_callback( array( $this, 'save_capabilities_for_tools' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Import files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select roles, which should be allowed to import already existing external files in the media library.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_tools_settings_tools_export_allowed_roles' );
		$setting->set_section( $permissions_tab_tools );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator' ) );
		$setting->set_save_callback( array( $this, 'save_capabilities_for_tools' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Export files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select roles, which should be allowed to configure and use export of files to external sources. This does not influence the automatic export of files to external sources through them.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_tools_settings_tools_sync_allowed_roles' );
		$setting->set_section( $permissions_tab_tools );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator' ) );
		$setting->set_save_callback( array( $this, 'save_capabilities_for_tools' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Synchronisation of files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Choose the roles that should be allowed to configure the synchronize files from external sources. This does not influence the automatic synchronisation of files from external sources.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_tools_settings_tools_zip_allowed_roles' );
		$setting->set_section( $permissions_tab_tools );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator' ) );
		$setting->set_save_callback( array( $this, 'save_capabilities_for_tools' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Unzip of files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Choose the roles that should be allowed to unzip files in your media library.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add the service section.
		$permissions_tab_source = $permissions_tab->add_section( 'settings_section_use_services', 30 );
		$permissions_tab_source->set_title( __( 'Permissions for external sources', 'external-files-in-media-library' ) );
		$permissions_tab_source->set_callback( array( $this, 'show_service_permission_hint' ) );
		$permissions_tab_source->set_setting( $settings_obj );

		// add settings for each service.
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $service ) {
			// add setting.
			$setting = $settings_obj->add_setting( 'eml_service_' . $service->get_name() . '_allowed_roles' );
			$setting->set_section( $permissions_tab_source );
			$setting->set_type( 'array' );
			$setting->set_default( array( 'administrator', 'editor' ) );
			$setting->set_save_callback( array( $this, 'save_capabilities_for_service' ) );
			$field = new MultiSelect();
			$field->set_title( $service->get_label() );
			$field->set_options( $user_roles );
			$setting->set_field( $field );
		}
	}

	/**
	 * Set capabilities during plugin activation.
	 *
	 * @return void
	 */
	public function install(): void {
		// the global setting.
		$this->set( get_option( 'eml_allowed_roles', array( 'administrator', 'editor' ) ), EFML_CAP_NAME );
	}

	/**
	 * Remove capabilities during plugin deinstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if ( ! ( function_exists( 'wp_roles' ) && ! empty( wp_roles()->roles ) ) ) {
			return;
		}

		// set the capability 'eml_manage_files' for the given roles.
		foreach ( wp_roles()->roles as $slug => $role ) {
			// get the role-object.
			$role_obj = get_role( $slug );

			// bail if role object could not be loaded.
			if ( ! $role_obj instanceof WP_Role ) {
				continue;
			}

			// remove the capabilities we set.
			$role_obj->remove_cap( EFML_CAP_NAME );
			$role_obj->remove_cap( 'efml_cap_tools_export' );
			$role_obj->remove_cap( 'efml_cap_tools_import' );
			$role_obj->remove_cap( 'efml_cap_tools_sync' );
			$role_obj->remove_cap( 'efml_cap_tools_zip' );

			// remove the caps for each service.
			foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $service ) {
				$role_obj->remove_cap( 'efml_cap_' . $service->get_name() );
			}

			// compatibility with < 5.0.0.
			$role_obj->remove_cap( 'efml_sync' );
		}
	}

	/**
	 * Set a given capability for the given roles.
	 *
	 * First remove the capability from all roles.
	 * Then set the capability on the list of given roles.
	 *
	 * @param array<string|int,mixed> $user_roles List of roles, which will get our capability.
	 * @param string                  $cap The capability to set.
	 *
	 * @return void
	 */
	public function set( array $user_roles, string $cap ): void {
		if ( ! ( function_exists( 'wp_roles' ) && ! empty( wp_roles()->roles ) ) ) {
			return;
		}

		// set the capability 'eml_manage_files' for the given roles.
		foreach ( wp_roles()->roles as $slug => $role ) {
			// get the role-object.
			$role_obj = get_role( $slug );

			// bail if role object could not be loaded.
			if ( ! $role_obj instanceof WP_Role ) {
				continue;
			}

			// check if given role is in list of the roles were we want to add the capability.
			if ( in_array( $slug, $user_roles, true ) ) {
				// add capability.
				$role_obj->add_cap( $cap );
			} else {
				// remove capability.
				$role_obj->remove_cap( $cap );
			}
		}
	}

	/**
	 * Set the capabilities for the given roles.
	 *
	 * @param array<int,string>|null $roles_to_set The roles to set.
	 * @param array<int,string>      $old_value The old value.
	 * @param string                 $option The used option.
	 *
	 * @return array<int,string>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function save_capabilities_for_service( array|null $roles_to_set, array $old_value, string $option ): array {
		// bail if null.
		if ( is_null( $roles_to_set ) ) {
			return array();
		}

		// get the service name from option name.
		$service = str_replace( array( 'eml_service_', '_allowed_roles' ), '', $option );

		// create the capability name.
		$capability = 'efml_cap_' . $service;

		// set the capability to the roles.
		$this->set( $roles_to_set, $capability );

		// return the settings.
		return $roles_to_set;
	}

	/**
	 * Set the capabilities for the given roles.
	 *
	 * @param array<int,string>|null $roles_to_set The roles to set.
	 * @param array<int,string>      $old_value The old value.
	 * @param string                 $option The used option.
	 *
	 * @return array<int,string>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function save_capabilities_for_tools( array|null $roles_to_set, array $old_value, string $option ): array {
		// bail if null.
		if ( is_null( $roles_to_set ) ) {
			return array();
		}

		// get the tool name from option name.
		$tool = str_replace( array( 'eml_tools_settings_', '_allowed_roles' ), '', $option );

		// create the capability name.
		$capability = 'efml_cap_' . $tool;

		// set the capability to the roles.
		$this->set( $roles_to_set, $capability );

		// return the settings.
		return $roles_to_set;
	}

	/**
	 * Return list of users for settings.
	 *
	 * @param bool $ignore_actual_user True if the actual user should not be included in list.
	 *
	 * @return array<int,string>
	 */
	public function get_user_for_settings( bool $ignore_actual_user = false ): array {
		// load user-list only on specific requests.
		$users = array();
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || 'eml_settings' === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
			foreach ( get_users() as $user ) {
				// bail if user is not a "WP_User" object.
				if ( ! $user instanceof WP_User ) {
					continue;
				}

				// bail if this is the actual user, it this is enabled.
				if ( $ignore_actual_user && get_current_user_id() === $user->ID ) {
					continue;
				}

				// add to the list.
				$users[ $user->ID ] = $user->display_name;
			}
		}

		// return list of users.
		return $users;
	}

	/**
	 * Check if current user it not on the forbidden list to access the external files.
	 *
	 * @param array<string,mixed> $allcaps List of all caps.
	 * @param array<string,mixed> $caps List of requested caps.
	 *
	 * @return array<string,mixed>
	 */
	public function check_user_cap( array $allcaps, array $caps ): array {
		// bail if our cap is not in caps list.
		if ( ! in_array( EFML_CAP_NAME, $caps, true ) ) {
			return $allcaps;
		}

		// get user, which should not have access to external files.
		$forbidden_users = get_option( 'eml_user_forbidden' );
		if ( ! is_array( $forbidden_users ) ) {
			$forbidden_users = array();
		}

		// bail if current user is not in this list.
		if ( ! in_array( get_current_user_id(), array_map( 'absint', $forbidden_users ), true ) ) {
			return $allcaps;
		}

		// remove the cap from list.
		unset( $allcaps[ EFML_CAP_NAME ] );

		// return the resulting list.
		return $allcaps;
	}

	/**
	 * Set capabilities after saving settings.
	 *
	 * @param array<string>|null $values The setting.
	 *
	 * @return array<string>
	 * @noinspection PhpUnused
	 */
	public function set_capabilities( ?array $values ): array {
		// check if value is not an array.
		if ( ! is_array( $values ) ) {
			$values = array();
		}

		// set capabilities.
		$this->set( $values, EFML_CAP_NAME );

		// return given value.
		return $values;
	}

	/**
	 * Show hint for service permissions.
	 *
	 * @return void
	 */
	public function show_service_permission_hint(): void {
		echo esc_html__( 'Select roles, which should be allowed to use these services.', 'external-files-in-media-library' );
	}
}
