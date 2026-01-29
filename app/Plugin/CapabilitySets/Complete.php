<?php
/**
 * File to handle the capability set where all roles gain any capability.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\CapabilitySets;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listings;
use ExternalFilesInMediaLibrary\ExternalFiles\Tools;
use ExternalFilesInMediaLibrary\Plugin\CapabilitySet_Base;

/**
 * Object for this capability set.
 */
class Complete extends CapabilitySet_Base {

	/**
	 * Name of this object.
	 *
	 * @var string
	 */
	protected string $name = 'complete';

	/**
	 * Initialize this object.
	 */
	public function __construct() {}

	/**
	 * Return the title of this object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Complete', 'external-files-in-media-library' );
	}

	/**
	 * Save the capabilities this set defines.
	 *
	 * @return void
	 */
	public function run(): void {
		// get user roles.
		$user_roles = array();
		if ( function_exists( 'wp_roles' ) && ! empty( wp_roles()->roles ) ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				$user_roles[] = $slug;
			}
		}

		// bail if no roles could be loaded.
		if ( empty( $user_roles ) ) {
			return;
		}

		// set capabilities for each tool to default.
		foreach ( Tools::get_instance()->get_tools_as_objects() as $tools_obj ) {
			// bail if this extension does not require capabilities.
			if ( ! $tools_obj->has_capability() ) {
				continue;
			}

			// set the default option.
			update_option( 'eml_tools_settings_tools_' . $tools_obj->get_name() . '_allowed_roles', $user_roles );
		}

		// set capabilities for each service to default.
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $service ) {
			// bail if this plugin does not require any permissions.
			if ( method_exists( $service, 'has_no_editable_permissions' ) && $service->has_no_editable_permissions() ) {
				continue;
			}

			// set the default option.
			update_option( 'eml_service_' . $service->get_name() . '_allowed_roles', $user_roles );
		}
	}
}
