<?php
/**
 * This file contains a helper object for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_Filesystem_Base;
use WP_Filesystem_Direct;
use WP_Query;
use WP_User;

/**
 * Initialize the helper for this plugin.
 */
class Helper {

	/**
	 * Get plugin dir of this plugin with trailing slash.
	 *
	 * @return string
	 */
	public static function get_plugin_dir(): string {
		return trailingslashit( plugin_dir_path( EFML_PLUGIN ) );
	}

	/**
	 * Get plugin URL of this plugin with trailing slash.
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
		// get the date from gmt date.
		$dt = get_date_from_gmt( $date );

		// get the date format.
		$date_format = self::get_as_string( get_option( 'date_format' ) );

		// get the time format.
		$time_format = self::get_as_string( get_option( 'time_format' ) );

		// return the formatted datetime string.
		return date_i18n( $date_format . ' ' . $time_format, strtotime( $dt ) );
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
		return Settings::get_instance()->get_url( 'eml_logs', '', $url );
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
		$wp_filesystem = self::get_wp_filesystem();
		$wp_filesystem->delete( $dir );
	}

	/**
	 * Return real content type from string.
	 *
	 * @param string $content_type The content type string.
	 *
	 * @return string
	 */
	public static function get_content_type_from_string( string $content_type ): string {
		// read the mime type without charset.
		preg_match_all( '/^(.*);(.*)$/m', $content_type, $matches );

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
	 * Get possible mime-types.
	 *
	 * These are the mime-types this plugin supports. Not the enabled mime-types!
	 *
	 * We do not use @get_allowed_mime_types() as there might be much more mime-types as our plugin
	 * could support.
	 *
	 * @return array<string,array<string,string>>
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
			'image/svg+xml'   => array(
				'label' => __( 'SVG', 'external-files-in-media-library' ),
				'ext'   => 'svg',
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

		// show deprecated warning for old hook name.
		$mime_types = apply_filters_deprecated( 'eml_supported_mime_types', array( $mime_types ), '5.0.0', 'efml_supported_mime_types' );

		/**
		 * Filter the possible mime types this plugin could support. This is the list used for the setting in backend.
		 *
		 * To add files of type "your/mime" with file extension ".yourmime" use this example:
		 *
		 * ```
		 * add_filter( 'efml_supported_mime_types', function( $list ) {
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
		 * @param array<string,array<string,string>> $mime_types List of supported mime types.
		 */
		return apply_filters( 'efml_supported_mime_types', $mime_types );
	}

	/**
	 * Return allowed content types.
	 *
	 * @return array<string>
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

		// show deprecated warning for old hook name.
		$list = apply_filters_deprecated( 'eml_get_mime_types', array( $list ), '5.0.0', 'efml_get_mime_types' );

		/**
		 * Filter the list of possible mime types. This is the list used by the plugin during file-checks
		 * and is not visible or editable in backend.
		 *
		 * To add files of type "your/mime" with file extension ".yourmime" use this example:
		 *
		 *  ```
		 *  add_filter( 'efml_get_mime_types', function( $list ) {
		 *   $list[] = 'your/mime';
		 *  } );
		 *  ```
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string> $list List of mime types.
		 */
		return apply_filters( 'efml_get_mime_types', $list );
	}

	/**
	 * Return URL to documentation to add URLs.
	 *
	 * @return string
	 */
	public static function get_support_url_for_urls(): string {
		if ( Languages::get_instance()->is_german_language() ) {
			return 'https://github.com/threadi/external-files-in-media-library/blob/master/docs/quickstart_de.md';
		}
		return 'https://github.com/threadi/external-files-in-media-library/blob/master/docs/quickstart.md';
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
		if ( ! $user_obj instanceof WP_User ) {
			return Users::get_instance()->get_first_administrator_user();
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
		return add_query_arg(
			array(
				'page' => 'efml_local_directories',
			),
			get_admin_url() . 'upload.php'
		);
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
		if ( ! is_array( $file_path_info ) ) { // @phpstan-ignore function.alreadyNarrowedType
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
	 * @return array<string>
	 */
	public static function get_intervals(): array {
		// collect the list.
		$values = array();

		// add disable option first.
		$values['eml_disable_check'] = __( 'Disabled', 'external-files-in-media-library' );

		// loop through all possible intervals from WordPress and add them to the list.
		foreach ( wp_get_schedules() as $name => $interval ) {
			$true = str_starts_with( (string) $name, 'efml_' );
			/**
			 * Disable all schedules, not only our own.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 * @param bool $true Set to "false", to use all schedules.
			 * @param string $name The name of the schedule.
			 * @param array<string,mixed> $interval The schedule configuration.
			 */
			if ( ! apply_filters( 'efml_own_cron_schedules', $true, (string) $name, $interval ) ) {
				continue;
			}

			// add the schedule to the list.
			$values[ (string) $name ] = $interval['display'];
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
			} else {
				$shortened_url .= $filename;
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

	/**
	 * Return the plugin slug.
	 *
	 * @return string
	 */
	public static function get_plugin_slug(): string {
		return plugin_basename( EFML_PLUGIN );
	}

	/**
	 * Add new entry with its key on specific position in array.
	 *
	 * @param array<int,mixed>|null $fields The array we want to change.
	 * @param int                   $position The position where the new array should be added.
	 * @param array<int,mixed>      $array_to_add The new array which should be added.
	 *
	 * @return array<int,mixed>
	 */
	public static function add_array_in_array_on_position( array|null $fields, int $position, array $array_to_add ): array {
		if ( is_null( $fields ) ) {
			return array();
		}
		return array_slice( $fields, 0, $position, true ) + $array_to_add + array_slice( $fields, $position, null, true );
	}

	/**
	 * Return 404 site with template.
	 *
	 * @return string
	 */
	public static function get_404_template(): string {
		global $wp_query;

		// bail if wp_query is not WP_Query.
		if ( ! $wp_query instanceof WP_Query ) {
			return '';
		}

		// set header.
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		// return the template.
		return get_404_template();
	}

	/**
	 * Return the WP Filesystem object.
	 *
	 * @param string $type The type to force.
	 *
	 * @return WP_Filesystem_Base
	 */
	public static function get_wp_filesystem( string $type = '' ): WP_Filesystem_Base {
		// get WP Filesystem-handler for local files if requested.
		if ( ! empty( $type ) && 'direct' === $type ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php'; // @phpstan-ignore requireOnce.fileNotFound
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php'; // @phpstan-ignore requireOnce.fileNotFound

			return new WP_Filesystem_Direct( false );
		}

		// get global WP Filesystem handler.
		require_once ABSPATH . '/wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
		\WP_Filesystem();
		global $wp_filesystem;

		// bail if wp_filesystem is not of "WP_Filesystem_Base".
		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php'; // @phpstan-ignore requireOnce.fileNotFound
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php'; // @phpstan-ignore requireOnce.fileNotFound
			return new WP_Filesystem_Direct( false );
		}

		// return local object on any error.
		if ( $wp_filesystem->errors->has_errors() ) {
			// embed the local directory object.
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php'; // @phpstan-ignore requireOnce.fileNotFound
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php'; // @phpstan-ignore requireOnce.fileNotFound

			return new WP_Filesystem_Direct( false );
		}

		// return the requested filesystem object.
		return $wp_filesystem;
	}

	/**
	 * Return a value as string, if it is a string.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return string
	 */
	public static function get_as_string( mixed $value ): string {
		// bail if $value is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// simply return the now checked string value.
		return $value;
	}

	/**
	 * Return a DB result list as array with unique datatype format.
	 *
	 * @param mixed $results The results from $wpdb->get_results().
	 *
	 * @return array<int,array<string>>
	 */
	public static function get_db_results( mixed $results ): array {
		// bail if results are not an array.
		if ( ! is_array( $results ) ) {
			return array();
		}

		// return the resulting array.
		return $results;
	}

	/**
	 * Return a DB single result as array with string as datatype.
	 *
	 * @param mixed $results The results from $wpdb->get_row().
	 *
	 * @return array<string>
	 */
	public static function get_db_result( mixed $results ): array {
		// bail if results are not an array.
		if ( ! is_array( $results ) ) {
			return array();
		}

		// return the resulting array.
		return $results;
	}

	/**
	 * Create JSON from given array.
	 *
	 * @param array<string|int,mixed>|WP_Error $source The source array.
	 * @param int                              $flag Flags to use for this JSON.
	 *
	 * @return string
	 */
	public static function get_json( array|WP_Error $source, int $flag = 0 ): string {
		// create JSON.
		$json = wp_json_encode( $source, $flag );

		// bail if creating the JSON failed.
		if ( ! $json ) {
			return '';
		}

		// return resulting JSON-string.
		return $json;
	}

	/**
	 * Get the name for a given interval in seconds.
	 *
	 * @param int $interval The interval in seconds.
	 *
	 * @return string
	 */
	public static function get_interval_by_time( int $interval ): string {
		foreach ( wp_get_schedules() as $name => $schedule ) {
			// bail if interval does not match.
			if ( $interval !== $schedule['interval'] ) {
				continue;
			}

			// return the name of this schedule.
			return (string) $name;
		}

		// return empty string if none has been found.
		return '';
	}

	/**
	 * Map the old interval name to the new.
	 *
	 * @param string $old_interval_name The old interval name.
	 *
	 * @return string
	 */
	public static function map_old_to_new_interval( string $old_interval_name ): string {
		$new_interval_name = 'efml_hourly';
		switch ( $old_interval_name ) {
			case 'daily':
				$new_interval_name = 'efml_24hourly';
				break;
			case 'twicedaily':
				$new_interval_name = 'efml_12hourly';
				break;
			case 'weekly':
				$new_interval_name = 'efml_weekly';
				break;
		}
		return $new_interval_name;
	}

	/**
	 * Return true if the current user has the given role.
	 *
	 * @param string $role The role to check.
	 * @return bool
	 */
	public static function has_current_user_role( string $role ): bool {
		// necessary to use logged in check.
		include_once ABSPATH . 'wp-includes/pluggable.php'; // @phpstan-ignore includeOnce.fileNotFound

		// bail if user is not logged in.
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// get the user object.
		$user = wp_get_current_user();

		// bail if object could not be loaded.
		if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
			return false;
		}

		// return if role is in list of user-roles.
		return in_array( $role, $user->roles, true );
	}

	/**
	 * Return whether this WordPress runs in development mode (which is available since WordPress 6.3).
	 *
	 * @return bool
	 */
	public static function is_development_mode(): bool {
		return (
			function_exists( 'wp_is_development_mode' ) && false !== wp_is_development_mode( 'plugin' )
		)
		|| ! function_exists( 'wp_is_development_mode' );
	}

	/**
	 * Return the plugin name.
	 *
	 * @return string
	 */
	public static function get_plugin_name(): string {
		$plugin_data = get_plugin_data( EFML_PLUGIN, false, false );
		if ( ! empty( $plugin_data['Name'] ) ) {
			return $plugin_data['Name'];
		}
		return '';
	}

	/**
	 * Return the absolute local filesystem-path (already trailed with slash) to the plugin.
	 *
	 * @return string
	 */
	public static function get_plugin_path(): string {
		return trailingslashit( plugin_dir_path( EFML_PLUGIN ) );
	}

	/**
	 * Return whether block support is available in this WordPress project.
	 *
	 * @return bool
	 */
	public static function is_block_support_enabled(): bool {
		return class_exists( 'WP_Block_Type_Registry' );
	}

	/**
	 * Return the version of the given file.
	 *
	 * With WP_DEBUG or plugin-debug enabled its @filemtime().
	 * Without this it's the plugin-version.
	 *
	 * @param string $filepath The absolute path to the requested file.
	 *
	 * @return string
	 */
	public static function get_file_version( string $filepath ): string {
		// check for WP_DEBUG.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return (string) filemtime( $filepath );
		}

		$plugin_version = EFML_PLUGIN;

		/**
		 * Filter the used file version (for JS- and CSS-files which get enqueued).
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param string $plugin_version The plugin-version.
		 * @param string $filepath The absolute path to the requested file.
		 */
		return apply_filters( 'efml_enqueued_file_version', $plugin_version, $filepath );
	}
}
