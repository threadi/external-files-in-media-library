<?php
/**
 * This file contains a helper object for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

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
		return Settings::get_instance()->get_url();
	}

	/**
	 * Return the url of the logs of this plugin.
	 *
	 * @param string $url The URL to filter for.
	 *
	 * @return string
	 */
	public static function get_log_url( string $url = '' ): string {
		return Settings::get_instance()->get_url( 'eml_logs', $url );
	}

	/**
	 * Set capability for our own plugin on given roles.
	 *
	 * @param array $user_roles List of allowed user-roles.
	 *
	 * @return void
	 */
	public static function set_capabilities( array $user_roles ): void {
		if ( ! ( function_exists( 'wp_roles' ) && ! empty( wp_roles()->roles ) ) ) {
			return;
		}

		// set the capability 'eml_manage_files' for the given roles.
		foreach ( wp_roles()->roles as $slug => $role ) {
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
	 * @return int
	 */
	public static function get_first_administrator_user(): int {
		$user_id = 0;

		// get first admin-user.
		$query   = array(
			'role'   => 'administrator',
			'number' => 1,
		);
		$results = new WP_User_Query( $query );

		// bail on no results.
		if( 0 === $results->get_total() ) {
			return $user_id;
		}

		// get the results.
		$roles   = $results->get_results();

		// bail if first entry does not exist.
		if( empty( $roles[0] ) ) {
			return $user_id;
		}

		// return the ID of the first entry.
		return $roles[0]->ID;
	}

	/**
	 * Delete given directory recursively.
	 *
	 * @param string $dir The path to delete recursively.
	 * @return void
	 */
	public static function delete_directory_recursively( string $dir ): void {
		// bail if given string is not a directory.
		if ( ! is_dir( $dir ) ) {
			return;
		}

		// get all subdirectories and files in this directory.
		$objects = scandir( $dir );

		// loop through them.
		foreach ( $objects as $object ) {
			// bail on "."-entries.
			if ( '.' === $object || '..' === $object ) {
				continue;
			}

			if ( is_dir( $dir . DIRECTORY_SEPARATOR . $object ) && ! is_link( $dir . '/' . $object ) ) {
				self::delete_directory_recursively( $dir . DIRECTORY_SEPARATOR . $object );
			} else {
				wp_delete_file( $dir . DIRECTORY_SEPARATOR . $object );
			}
		}

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->delete( $dir );
	}

	/**
	 * Return the hook-documentation-URL.
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
		// read the mime type without charset.
		preg_match_all( '/^(.*);(.*)$/mi', $content_type, $matches );

		// get it.
		if ( ! empty( $matches[1] ) ) {
			$content_type = $matches[1][0];
		}

		// return it.
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

	/**
	 * Return true if the given mime_type is an image-mime-type.
	 *
	 * @param string $mime_type The mime-type to check.
	 *
	 * @return bool
	 */
	public static function is_image_by_mime_type( string $mime_type ): bool {
		return str_starts_with( $mime_type, 'image/' );
	}

	/**
	 * Get possible mime-types.
	 *
	 * These are the mime-types this plugin supports. Not the enabled mime-types!
	 *
	 * We do not use @get_allowed_mime_types() as there might be much more mime-types as our plugin
	 * could support.
	 *
	 * @return array
	 */
	public static function get_possible_mime_types(): array {
		$mime_types = array(
			'image/gif'       => array(
				'label' => __( 'GIF', 'external-files-in-media-library' ),
				'ext'   => 'gif',
			),
			'image/jpeg'      => array(
				'label' => __( 'JPG/JPEG', 'external-files-in-media-library' ),
				'ext'   => 'jpg',
			),
			'image/png'       => array(
				'label' => __( 'PNG', 'external-files-in-media-library' ),
				'ext'   => 'png',
			),
			'image/webp'      => array(
				'label' => __( 'WEBP', 'external-files-in-media-library' ),
				'ext'   => 'webp',
			),
			'application/pdf' => array(
				'label' => __( 'PDF', 'external-files-in-media-library' ),
				'ext'   => 'pdf',
			),
			'application/zip' => array(
				'label' => __( 'ZIP', 'external-files-in-media-library' ),
				'ext'   => 'zip',
			),
			'video/mp4'       => array(
				'label' => __( 'MP4 Video', 'external-files-in-media-library' ),
				'ext'   => 'mp4',
			),
		);

		/**
		 * Filter the possible mime types this plugin could support. This is the list used for the setting in backend.
		 *
		 * To add files of type "your/mime" with file extension ".yourmime" use this example:
		 *
		 * ```
		 * add_filter( 'eml_supported_mime_types', function( $list ) {
		 *  $list['your/mime'] = array(
		 *      'label' => 'Title of your mime',
		 *      'ext' => 'yourmime'
		 *  );
		 *  return $list;
		 * } );
		 * ```
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param array $mime_types List of supported mime types.
		 */
		return apply_filters( 'eml_supported_mime_types', $mime_types );
	}

	/**
	 * Return allowed content types.
	 *
	 * @return array
	 */
	public static function get_allowed_mime_types(): array {
		// get the list from settings.
		$list = get_option( 'eml_allowed_mime_types', array() );

		// bail if setting is not an array.
		if( ! is_array( $list ) ) {
			return array();
		}

		// is list is empty, return empty list.
		if ( empty( $list ) ) {
			return array();
		}

		/**
		 * Filter the list of possible mime types. This is the list used by the plugin during file-checks
		 * and is not visible or editable in backend.
		 *
		 * To add files of type "your/mime" with file extension ".yourmime" use this example:
		 *
		 *  ```
		 *  add_filter( 'eml_get_mime_types', function( $list ) {
		 *   $list['your/mime'] = array(
		 *       'label' => 'Title of your mime',
		 *       'ext' => 'yourmime'
		 *   );
		 *   return $list;
		 *  } );
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $list List of mime types.
		 */
		return apply_filters( 'eml_get_mime_types', $list );
	}

	/**
	 * Return URL to documentation to add URLs.
	 *
	 * @return string
	 */
	public static function get_support_url_for_urls(): string {
		if ( Languages::get_instance()->is_german_language() ) {
			return 'https://github.com/threadi/external-files-in-media-library/docs/quickstart_de.md';
		}
		return 'https://github.com/threadi/external-files-in-media-library/docs/quickstart.md';
	}

	/**
	 * Return our review URL.
	 *
	 * @return string
	 */
	public static function get_plugin_review_url(): string {
		if ( Languages::get_instance()->is_german_language() ) {
			return 'https://de.wordpress.org/plugins/external-files-in-media-library/#reviews';
		}
		return 'https://wordpress.org/plugins/external-files-in-media-library/#reviews';
	}

	/**
	 * Return our support forum URL.
	 *
	 * @return string
	 */
	public static function get_plugin_support_url(): string {
		return 'https://wordpress.org/support/plugin/external-files-in-media-library/';
	}

	/**
	 * Return ID of the current WP-user.
	 *
	 * @return int
	 */
	public static function get_current_user_id(): int {
		$user_id = get_current_user_id();

		// bail if ID is given.
		if ( $user_id > 0 ) {
			return $user_id;
		}

		// get user from setting.
		$user_id = absint( get_option( 'eml_user_assign', 0 ) );

		// check if user exists.
		$user_obj = get_user_by( 'ID', $user_id );

		// Fallback: search for an administrator.
		if ( false === $user_obj ) {
			return self::get_first_administrator_user();
		}

		// return resulting user ID.
		return $user_id;
	}

	/**
	 * Check if WP CLI has been called.
	 *
	 * @return bool
	 */
	public static function is_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Return URL where user can add files for media library.
	 *
	 * @return string
	 */
	public static function get_add_media_url(): string {
		return add_query_arg( array(), get_admin_url() . 'media-new.php' );
	}

	/**
	 * Generate a sizes filename.
	 *
	 * @param string $filename The original filename.
	 * @param int    $width The width to use.
	 * @param int    $height The height to use.
	 *
	 * @return string
	 */
	public static function generate_sizes_filename( string $filename, int $width, int $height ): string {
		$file_info = pathinfo( $filename );

		// bail if path info is not an array.
		if( ! is_array( $file_info ) ) {
			return $filename;
		}

		// return concat string for the filename.
		return $file_info['filename'] . '-' . $width . 'x' . $height . '.' . $file_info['extension'];
	}
}
