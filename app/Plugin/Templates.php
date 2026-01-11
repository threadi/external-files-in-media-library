<?php
/**
 * This file contains the template object for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Initialize the plugin, connect all together.
 */
class Templates {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Templates
	 */
	private static ?Templates $instance = null;

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
	 * @return Templates
	 */
	public static function get_instance(): Templates {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Return path to single template.
	 *
	 * Also load the requested file if it is located in the /wp-content/themes/xy/external-files-in-media-library/ directory.
	 *
	 * @param string $template The template to use.
	 *
	 * @return string
	 */
	public function get_template( string $template ): string {
		if ( is_embed() ) {
			return $template;
		}

		// check if requested template exist in the theme.
		$theme_template = locate_template( trailingslashit( basename( dirname( EFML_PLUGIN ) ) ) . $template );
		if ( $theme_template ) {
			return $theme_template;
		}

		// set the directory for the template to use.
		$directory = EFML_PLUGIN;

		// show deprecated warning for the old hook name.
		$directory = apply_filters_deprecated( 'eml_set_template_directory', array( $directory ), '5.0.0', 'efml_set_template_directory' );

		/**
		 * Set template directory.
		 *
		 * Defaults to our own plugin-directory.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 *
		 * @param string $directory The directory to use.
		 */
		$plugin_template = plugin_dir_path( apply_filters( 'efml_set_template_directory', $directory ) ) . 'templates/' . $template;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		// return template from light-plugin.
		return plugin_dir_path( EFML_PLUGIN ) . 'templates/' . $template;
	}
}
