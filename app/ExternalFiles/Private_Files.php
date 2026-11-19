<?php
/**
 * File to handle the delivery of private files.
 *
 * Private files are saved on external sources, which do not allow public access to them (e.g. a private bucket).
 * They are always delivered via the proxy of this plugin:
 * - from its cache, if the proxy is enabled for their file type in the settings, or
 * - via a redirect to a signed URL with limited validity, if the protocol of the file supports this.
 *
 * Signed URLs are created for fixed time windows, so the same URL is used during a time window. This allows
 * browsers and caches to reuse it.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Fields\Number;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Setting;
use easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle the delivery of private files.
 */
class Private_Files {

	/**
	 * The default lifetime of signed URLs in minutes.
	 *
	 * @var int
	 */
	private const DEFAULT_LIFETIME = 60;

	/**
	 * The maximum lifetime of signed URLs in minutes.
	 *
	 * The signed URLs are valid for two time windows. Many services allow a validity of max. 7 days.
	 *
	 * @var int
	 */
	private const MAX_LIFETIME = 5040;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Private_Files
	 */
	private static ?Private_Files $instance = null;

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
	 * @return Private_Files
	 */
	public static function get_instance(): Private_Files {
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
		add_filter( 'efml_table_column_file_source_dialog', array( $this, 'show_private_state_in_info_dialog' ), 10, 2 );
	}

	/**
	 * Add the setting for the lifetime of signed URLs to the given section of the export settings.
	 *
	 * @param Settings $settings_obj   The settings object.
	 * @param Section  $section        The section to use.
	 * @param Setting  $setting_export The setting to enable the export.
	 *
	 * @return void
	 */
	public function add_settings( Settings $settings_obj, Section $section, Setting $setting_export ): void {
		// create the description.
		$description = __( 'Applies to external sources, which save files privately. If the proxy is not enabled for the file type, your website redirects to a link with limited validity for these files. The link stays the same for this period of time, so browsers can cache the files. Search engines and visitors always see the URL of your website.', 'external-files-in-media-library' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_private_files_lifetime' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( self::DEFAULT_LIFETIME );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'Validity of links to private files (in minutes)', 'external-files-in-media-library' ) );
		$field->set_description( $description );
		$field->set_min( 1 );
		$field->set_sanitize_callback( array( $this, 'sanitize_lifetime' ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_setting( $setting );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $description . '</p>' );
	}

	/**
	 * Sanitize the lifetime setting.
	 *
	 * @param mixed $value The given value.
	 *
	 * @return int
	 */
	public function sanitize_lifetime( mixed $value ): int {
		// get the value as integer.
		$value = is_numeric( $value ) ? absint( $value ) : self::DEFAULT_LIFETIME;

		// return the value within the allowed range.
		return max( 1, min( self::MAX_LIFETIME, $value ) );
	}

	/**
	 * Return the lifetime of signed URLs in seconds.
	 *
	 * This is the length of a time window. A signed URL is valid for two time windows, so it is valid at least for
	 * the lifetime after each redirect.
	 *
	 * @return int
	 */
	public function get_lifetime(): int {
		// get the setting in minutes.
		$lifetime = $this->sanitize_lifetime( get_option( 'eml_private_files_lifetime', self::DEFAULT_LIFETIME ) ) * MINUTE_IN_SECONDS;

		/**
		 * Filter the lifetime of signed URLs for private files in seconds.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $lifetime The lifetime in seconds.
		 */
		$lifetime = absint( apply_filters( 'efml_private_files_lifetime', $lifetime ) );

		// return the lifetime within the allowed range.
		return max( MINUTE_IN_SECONDS, min( self::MAX_LIFETIME * MINUTE_IN_SECONDS, $lifetime ) );
	}

	/**
	 * Return the time window for the given timestamp.
	 *
	 * Returns the start of the actual time window, the expiration of signed URLs created in it (end of the next time
	 * window) and the remaining seconds of the actual time window (which could be used for caching).
	 *
	 * @param int $now The timestamp.
	 *
	 * @return array{start: int, expires: int, max_age: int}
	 */
	public function get_time_window( int $now ): array {
		// get the lifetime.
		$lifetime = $this->get_lifetime();

		// get the start of the actual time window.
		$start = (int) ( floor( $now / $lifetime ) * $lifetime );

		// return the resulting values.
		return array(
			'start'   => $start,
			'expires' => $start + 2 * $lifetime,
			'max_age' => max( 0, $start + $lifetime - $now ),
		);
	}

	/**
	 * Return a signed URL for the given external file, valid in the given time range.
	 *
	 * Returns an empty string if the protocol of the file could not sign URLs.
	 *
	 * @param File $external_file_obj The external file object.
	 * @param int  $start             The start of the validity as timestamp.
	 * @param int  $expires           The end of the validity as timestamp.
	 *
	 * @return string
	 */
	public function get_signed_url( File $external_file_obj, int $start, int $expires ): string {
		// get the protocol handler for this file.
		$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

		// bail if no protocol handler could be loaded.
		if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
			return '';
		}

		// set the fields.
		$protocol_handler_obj->set_fields( $external_file_obj->get_fields() );

		// get the signed URL.
		$url = $protocol_handler_obj->get_signed_url( $external_file_obj->get_url( true ), $start, $expires );

		/**
		 * Filter the signed URL for a private file.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $url The signed URL (empty, if not available).
		 * @param File $external_file_obj The external file object.
		 * @param int $start The start of the validity as timestamp.
		 * @param int $expires The end of the validity as timestamp.
		 */
		return (string) apply_filters( 'efml_private_file_signed_url', $url, $external_file_obj, $start, $expires );
	}

	/**
	 * Return the URL to redirect to for the given external file, if it should be delivered via a redirect.
	 *
	 * This is the case for private files, which are not delivered from the cache, as the proxy is not enabled for
	 * their file type in the settings. Returns an empty string if the file should be delivered from the cache.
	 *
	 * @param File           $external_file_obj The external file object.
	 * @param array<int,int> $dimensions        The requested dimensions of an image.
	 *
	 * @return string
	 */
	public function get_redirect_url( File $external_file_obj, array $dimensions = array() ): string {
		// bail if the file is not private.
		if ( ! $external_file_obj->is_private() || $external_file_obj->is_locally_saved() ) {
			return '';
		}

		// bail if a specific size of an image is requested, as only the cache could deliver it.
		if ( ! empty( $dimensions ) ) {
			return '';
		}

		// bail if the proxy is enabled for this file type in the settings.
		if ( $external_file_obj->get_file_type_obj()->is_proxy_enabled_by_settings() ) {
			return '';
		}

		// get the actual time window.
		$window = $this->get_time_window( time() );

		// return the signed URL for this time window.
		return $this->get_signed_url( $external_file_obj, $window['start'], $window['expires'] );
	}

	/**
	 * Redirect to the given signed URL and end the request.
	 *
	 * The redirect could be cached until the end of the actual time window, as the signed URL is valid
	 * at least one more time window.
	 *
	 * @param string $url The signed URL.
	 *
	 * @return void
	 */
	public function redirect( string $url ): void {
		// get the actual time window.
		$window = $this->get_time_window( time() );

		// log this event.
		Log::get_instance()->create( __( 'Proxy redirects to a signed URL for this private file.', 'external-files-in-media-library' ), '', 'info', 2 );

		// allow caching of the redirect only by the browser of the visitor until the end of the actual time window.
		header( 'Cache-Control: private, max-age=' . $window['max_age'] );

		// redirect to the signed URL.
		wp_redirect( $url, 302, 'External Files in Media Library' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Add the info about a private file in the info dialog for a single external file.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param File                $external_file_obj The external file object.
	 *
	 * @return array<string,mixed>
	 */
	public function show_private_state_in_info_dialog( array $dialog, File $external_file_obj ): array {
		// bail if file is not private.
		if ( ! $external_file_obj->is_private() ) {
			return $dialog;
		}

		// add the info.
		$dialog['texts'][] = '<p><strong>' . __( 'Private', 'external-files-in-media-library' ) . ':</strong> ' . __( 'The file is saved privately on the external source and delivered via your website.', 'external-files-in-media-library' ) . '</p>';

		// return the resulting dialog.
		return $dialog;
	}
}
