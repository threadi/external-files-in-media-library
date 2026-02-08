<?php
/**
 * File to handle the minimal mode.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Configurations;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Tools;
use ExternalFilesInMediaLibrary\Plugin\Configuration_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Term_Query;

/**
 * Object for the minimal mode.
 */
class Minimal extends Configuration_Base {

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
		return __( 'Minimal', 'external-files-in-media-library' );
	}

	/**
	 * Return additional hints for the dialog to set this mode.
	 *
	 * @return array<int,string>
	 */
	public function get_dialog_hints(): array {
		return array(
			'<p>' . __( 'This will disable all services you actually do not use.', 'external-files-in-media-library' ) . '<br />' . __( 'This will also disable any tools the plugin provides, and the user specific settings for imports.', 'external-files-in-media-library' ) . '</p>'
		);
	}

	/**
	 * Save the configuration this mode defines.
	 *
	 * @return void
	 */
	public function run(): void {
		/**
		 * Services:
		 * Disable not used services via capability.
		 * First check, which services are been used via external files and external sources.
		 * If no service is used, do nothing.
		 */
		// Prepare list of service names.
		$used_services = array();

		// 1. Get external files.
		$files = Files::get_instance()->get_files();

		// 2. Check their sources.
		foreach ( $files as $file ) {
			// get the source.
			$service_name = $file->get_service_name();

			// bail if service name is empty.
			if( empty( $service_name ) ) {
				continue;
			}

			// add the name to the list.
			$used_services[ $service_name ] = $service_name;
		}

		// 3. Check the external sources.
		$query = array(
			'taxonomy'   => Taxonomy::get_instance()->get_name(),
			'hide_empty' => false,
			'count'      => false,
		);
		$terms = new WP_Term_Query( $query );
		if ( is_array( $terms->terms ) ) { // @phpstan-ignore function.alreadyNarrowedType
			foreach ( $terms->terms as $term ) {
				// get the used service name.
				$service_name = get_term_meta( $term->term_id, 'type', true );

				// bail if service name is empty.
				if( empty( $service_name ) ) {
					continue;
				}

				// add the name to the list.
				$used_services[ $service_name ] = $service_name;
			}
		}

		// 4. Get all services and disable the ones, which are not in our list.
		foreach( Services::get_instance()->get_services_as_objects() as $service_obj ) {
			// bail if method for the name does not exist.
			if( ! method_exists( $service_obj, 'get_name' ) ) {
				continue;
			}

			// bail if this service is in the list.
			if( isset( $used_services[ $service_obj->get_name() ] ) ) {
				continue;
			}

			// remove any capability to use this service to hide it.
			update_option( 'eml_service_' . $service_obj->get_name() . '_allowed_roles', array() );
		}

		/**
		 * Configuration:
		 * Change settings to hide some options.
		 */
		// disable hints for plugins.
		update_option( 'eml_disable_plugin_hints', 1 );

		// disable all options.
		update_option( 'eml_import_extensions', array() );

		// disable user specific settings.
		update_option( 'eml_user_settings', 0 );

		// disable job-link on each file.
		update_option( 'eml_job_show_link', 0 );

		/**
		 * Tools:
		 * Disable all tools via capability if they are used.
		 */
		foreach ( Tools::get_instance()->get_tools_as_objects() as $tools_obj ) {
			// bail if tool is being not used.
			if( $tools_obj->is_in_use() ) {
				continue;
			}

			// run tasks to disable this tool.
			$tools_obj->disable();
		}
	}
}
