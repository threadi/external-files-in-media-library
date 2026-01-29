<?php
/**
 * File to handle the minimal capability set.
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
class Minimal extends CapabilitySet_Base {

	/**
	 * Name of this object.
	 *
	 * @var string
	 */
	protected string $name = 'minimal';

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
		return __( 'Minimal settings', 'external-files-in-media-library' );
	}

	/**
	 * Save the capabilities this set defines.
	 *
	 * @return void
	 */
	public function run(): void {
		// set capabilities for each tool to default.
		foreach ( Tools::get_instance()->get_tools_as_objects() as $tools_obj ) {
			// bail if this extension does not require capabilities.
			if ( ! $tools_obj->has_capability() ) {
				continue;
			}

			// set the default option.
			update_option( 'eml_tools_settings_tools_' . $tools_obj->get_name() . '_allowed_roles', array() );
		}

		// set capabilities for each service to default.
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $service ) {
			// bail if this plugin does not require any permissions.
			if ( method_exists( $service, 'has_no_editable_permissions' ) && $service->has_no_editable_permissions() ) {
				continue;
			}

			// set the default option.
			update_option( 'eml_service_' . $service->get_name() . '_allowed_roles', array() );
		}
	}
}
