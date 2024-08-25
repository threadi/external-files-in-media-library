<?php
/**
 * This file contains a helper class for this plugin.
 *
 * @package thread\eml
 */

namespace threadi\eml;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_User_Query;

/**
 * Initialize the helper for this plugin.
 */
class Helper {

	/**
	 * Get plugin dir of this plugin.
	 *
	 * @return string
	 */
	public static function get_plugin_dir(): string {
		return plugin_dir_path( EML_PLUGIN );
	}

	/**
	 * Format a given datetime with WP-settings and functions.
	 *
	 * @param string $date  A date as string.
	 * @return string
	 */
	public static function get_format_date_time( string $date ): string {
		$dt = get_date_from_gmt( $date );
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dt ) );
	}

	/**
	 * Return the url of the configuration of this plugin.
	 *
	 * @return string
	 */
	public static function get_config_url(): string {
		return add_query_arg(
			array(
				'page' => 'eml_settings',
			),
			'options-general.php'
		);
	}

	/**
	 * Return the url of the logs of this plugin.
	 *
	 * @return string
	 */
	public static function get_log_url(): string {
		return add_query_arg(
			array(
				'page' => 'eml_settings',
				'tab'  => 'logs',
			),
			'options-general.php'
		);
	}

	/**
	 * Set capability for our own plugin on given roles.
	 *
	 * @param array $user_roles List of allowed user-roles.
	 *
	 * @return void
	 */
	public static function set_capabilities( array $user_roles ): void {
		global $wp_roles;

		// set the capability 'eml_manage_files' for the given roles.
		foreach ( $wp_roles->roles as $slug => $role ) {
			// get the role-object.
			$role_obj = get_role( $slug );

			// check if given role is in list of on-install supported roles.
			if ( in_array( $slug, $user_roles, true ) ) {
				// add capability.
				$role_obj->add_cap( EML_CAP_NAME );
			} else {
				// remove capability.
				$role_obj->remove_cap( EML_CAP_NAME );
			}
		}
	}

	/**
	 * Get the ID of the first administrator user.
	 *
	 * @return int|null
	 */
	public static function get_first_administrator_user(): ?int {
		$user_id = 0;

		// get first admin-user.
		$query   = array(
			'role'   => 'administrator',
			'number' => 1,
		);
		$results = new WP_User_Query( $query );
		$roles   = $results->get_results();
		if ( ! empty( $roles ) ) {
			$user_id = $roles[0]->ID;
		}

		return $user_id;
	}

	/**
	 * Delete given directory recursively.
	 *
	 * @param string $dir The path to delete recursively.
	 * @return void
	 */
	public static function delete_directory_recursively( string $dir ): void {
		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( '.' !== $object && '..' !== $object ) {
					if ( is_dir( $dir . DIRECTORY_SEPARATOR . $object ) && ! is_link( $dir . '/' . $object ) ) {
						self::delete_directory_recursively( $dir . DIRECTORY_SEPARATOR . $object );
					} else {
						wp_delete_file( $dir . DIRECTORY_SEPARATOR . $object );
					}
				}
			}

			// get WP Filesystem-handler.
			require_once ABSPATH . '/wp-admin/includes/file.php';
			\WP_Filesystem();
			global $wp_filesystem;
			$wp_filesystem->delete( $dir );
		}
	}

	/**
	 * Return the hook-url.
	 *
	 * @return string
	 */
	public static function get_hook_url(): string {
		return 'https://github.com/threadi/external-files-in-media-library/blob/master/docs/hooks.md';
	}

	/**
	 * Get real content type from string.
	 *
	 * @param string $content_type The content type string.
	 *
	 * @return string
	 */
	public static function get_content_type_from_string( string $content_type ): string {
		preg_match_all( '/^(.*);(.*)$/mi', $content_type, $matches );
		if ( ! empty( $matches[1] ) ) {
			$content_type = $matches[1][0];
		}
		return $content_type;
	}

	/**
	 * Checks whether a given plugin is active.
	 *
	 * Used because WP's own function is_plugin_active() is not accessible everywhere.
	 *
	 * @param string $plugin Path to the plugin.
	 * @return bool
	 */
	public static function is_plugin_active( string $plugin ): bool {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
	}
}
