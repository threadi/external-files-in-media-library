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
		return trailingslashit( plugin_dir_path( EFML_PLUGIN ) );
	}

	/**
	 * Get plugin URL of this plugin.
	 *
	 * @return string
	 */
	public static function get_plugin_url(): string {
		return trailingslashit( plugin_dir_url( EFML_PLUGIN ) );
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
	 * Return the URL of the configuration of this plugin.
	 *
	 * @return string
	 */
	public static function get_config_url(): string {
		return Settings::get_instance()->get_url();
	}

	/**
	 * Return the URL of the logs of this plugin.
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
				$role_obj->add_cap( EFML_CAP_NAME );
			} else {
				// remove capability.
				$role_obj->remove_cap( EFML_CAP_NAME );
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
		if ( 0 === $results->get_total() ) {
			return $user_id;
		}

		// get the results.
		$roles = $results->get_results();

		// bail if first entry does not exist.
		if ( empty( $roles[0] ) ) {
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
	 * Return true if the given mime_type is a video-mime-type.
	 *
	 * @param string $mime_type The mime-type to check.
	 *
	 * @return bool
	 */
	public static function is_video_by_mime_type( string $mime_type ): bool {
		return str_starts_with( $mime_type, 'video/' );
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
		if ( ! is_array( $list ) ) {
			return array();
		}

		// bail if list is empty.
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
		 *   $list[] = 'your/mime';
		 *  } );
		 *  ```
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
	 * @param int    $width    The width to use.
	 * @param int    $height   The height to use.
	 * @param string $extension Optionally set extension.
	 *
	 * @return string
	 */
	public static function generate_sizes_filename( string $filename, int $width, int $height, string $extension = '' ): string {
		$file_path_info = pathinfo( $filename );

		// bail if path info is not an array.
		if ( ! is_array( $file_path_info ) ) {
			return $filename;
		}

		// if no extension could be extracted get one from the files mime type.
		if ( empty( $file_path_info['extension'] ) ) {
			$file_path_info['extension'] = $extension;
		}

		// return concat string for the filename.
		return $file_path_info['filename'] . '-' . $width . 'x' . $height . '.' . $file_path_info['extension'];
	}

	/**
	 * Return the possible intervals as array.
	 *
	 * @return array
	 */
	public static function get_intervals(): array {
		// collect the list.
		$values = array();

		// add disable option first.
		$values['eml_disable_check'] = __( 'Disabled', 'external-files-in-media-library' );

		// loop through all possible intervals from WordPress and add them to the list.
		foreach ( wp_get_schedules() as $name => $interval ) {
			$values[ $name ] = $interval['display'];
		}

		// return the resulting list.
		return $values;
	}

	/**
	 * Return the mime types documentation URL.
	 *
	 * @return string
	 */
	public static function get_mimetypes_doc_url(): string {
		if ( Languages::get_instance()->is_german_language() ) {
			return 'https://github.com/threadi/external-files-in-media-library/blob/master/docs/MimeTypes_de.md';
		}
		return 'https://github.com/threadi/external-files-in-media-library/blob/master/docs/MimeTypes.md';
	}

	/**
	 * Return a shortened URL with domain and filename on base of given URL.
	 *
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	public static function shorten_url( string $url ): string {
		// get the parse URL.
		$parsed_url = wp_parse_url( $url );

		// bail if URL could not be parsed.
		if ( ! is_array( $parsed_url ) ) {
			return $url;
		}

		// collect the resulting URL.
		$shortened_url = '';

		// add protocol.
		if ( ! empty( $parsed_url['scheme'] ) ) {
			$shortened_url .= $parsed_url['scheme'] . '://';
		}

		// add host.
		if ( ! empty( $parsed_url['host'] ) ) {
			$shortened_url .= $parsed_url['host'];
		}

		// add the filename.
		if ( ! empty( $parsed_url['path'] ) ) {
			// get the potential filename.
			$filename = '/' . basename( $parsed_url['path'] );

			// if filename is not exact the path add the filename to the URL.
			if ( $filename !== $parsed_url['path'] ) {
				$shortened_url .= '/../' . basename( $parsed_url['path'] );
			}
		}

		// return thr shortened URL.
		return $shortened_url;
	}

	/**
	 * Return wikipedia link for GPRD.
	 *
	 * @return string
	 */
	public static function get_gprd_url(): string {
		if ( Languages::get_instance()->is_german_language() ) {
			return 'https://de.wikipedia.org/wiki/Datenschutz-Grundverordnung';
		}
		return 'https://en.wikipedia.org/wiki/General_Data_Protection_Regulation';
	}

	/**
	 * Return the media library URL.
	 *
	 * @return string
	 */
	public static function get_media_library_url(): string {
		return get_admin_url() . 'upload.php';
	}
}
