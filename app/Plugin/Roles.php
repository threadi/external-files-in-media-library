<?php
/**
 * This file contains the object which handles the capabilities for this plugin on the roles.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
use WP_Role;

defined( 'ABSPATH' ) || exit;

/**
 * Object which handles the capabilities for this plugin on the roles.
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
	 * Set capabilities during plugin activation.
	 *
	 * @return void
	 */
	public function install(): void {
		$this->set( get_option( 'eml_allowed_roles' ) );
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

			// remove the capability.
			$role_obj->remove_cap( EFML_CAP_NAME );
		}
	}

	/**
	 * Set the capabilities for the given roles.
	 *
	 * @param array<string,mixed> $user_roles List of roles which will get our capability.
	 *
	 * @return void
	 */
	public function set( array $user_roles ): void {
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

			// check if given role is in list of on-install supported roles.
			if ( in_array( $slug, $user_roles, true ) ) {
				// add capability.
				$role_obj->add_cap( EFML_CAP_NAME );
			} else {
				// remove capability.
				$role_obj->remove_cap( EFML_CAP_NAME );
			}
		}
	}
}
