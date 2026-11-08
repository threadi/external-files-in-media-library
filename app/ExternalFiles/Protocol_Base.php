<?php
/**
 * File, which provide the base functions for each protocol we support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Filesystem_Base;
use WP_Query;

/**
 * Object to handle base functions for each protocol.
 */
class Protocol_Base {

	/**
	 * Internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * The given URL.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * List of supported tcp protocols with their ports.
	 *
	 * @var array<string,int>
	 */
	protected array $tcp_protocols = array();

	/**
	 * The fields.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $fields = array();

	/**
	 * Constructor, not used as this a Singleton object.
	 *
	 * @param string $url The URL to use.
	 */
	public function __construct( string $url ) {
		$this->url = $url;
	}

	/**
	 * Return the internal name of this protocol object.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Return the URL used by this object.
	 *
	 * @return string
	 */
	public function get_url(): string {
		return $this->url;
	}

	/**
	 * Return the tcp protocols of this protocol object.
	 *
	 * @return array<string,int>
	 */
	private function get_tcp_protocols(): array {
		$tcp_protocols = $this->tcp_protocols;

		$instance = $this;

		// show deprecated hint for the old hook.
		$tcp_protocols = apply_filters_deprecated( 'eml_tcp_protocols', array( $tcp_protocols, $instance ), '5.0.0', 'efml_tcp_protocols' );

		/**
		 * Filter the protocols.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,int> $tcp_protocols List of protocols of this object (e.g., 'http' or 'ftp').
		 * @param Protocol_Base $instance The actual object.
		 */
		return apply_filters( 'efml_tcp_protocols', $tcp_protocols, $instance );
	}

	/**
	 * Return the default port of the used protocol.
	 *
	 * @param string $tcp_protocol The protocol (e.g. 'http' or 'ftp').
	 *
	 * @return int
	 */
	protected function get_port_by_protocol( string $tcp_protocol ): int {
		// get list of protocols.
		$tcp_protocols = $this->get_tcp_protocols();

		// bail if list is empty.
		if ( empty( $tcp_protocols ) ) {
			return 0;
		}

		// bail if protocol is unknown.
		if ( empty( $tcp_protocols[ $tcp_protocol ] ) ) {
			return 0;
		}

		// return the port.
		return $tcp_protocols[ $tcp_protocol ];
	}

	/**
	 * Return the port for a parsed URL: the port given in the URL or the default port of its protocol.
	 *
	 * @param array<string,mixed> $parse_url The result of wp_parse_url() for the URL.
	 *
	 * @return int
	 */
	protected function get_port_by_url( array $parse_url ): int {
		// use the port from the URL, if it is a valid one.
		if ( isset( $parse_url['port'] ) && absint( $parse_url['port'] ) > 0 && absint( $parse_url['port'] ) <= 65535 ) {
			return absint( $parse_url['port'] );
		}

		// bail if no scheme is given.
		if ( empty( $parse_url['scheme'] ) || ! is_string( $parse_url['scheme'] ) ) {
			return 0;
		}

		// return the default port of the protocol.
		return $this->get_port_by_protocol( $parse_url['scheme'] );
	}

	/**
	 * Check if URL is compatible with the given protocol by comparing the protocol handler
	 * and the start of the given URL with the supported protocols of this protocol handler
	 * (e.g., 'http' or 'ftp').
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		foreach ( $this->get_tcp_protocols() as $tcp_protocol => $port ) {
			if ( str_starts_with( $this->get_url(), $tcp_protocol ) ) {
				return true;
			}
		}

		// return false if no compatible protocol has been found.
		return false;
	}

	/**
	 * Check format of given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_url( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Check the availability of a given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>> List of files with its infos.
	 */
	public function get_url_infos(): array {
		return array();
	}

	/**
	 * Return whether the file should be saved local (true) or not (false).
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return false;
	}

	/**
	 * Return the fields.
	 *
	 * Values of fields, which are defined via constants for the service using this protocol, replace
	 * the given values, if the URL of this object is matching the server of these constants.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		return $this->get_fields_for_url( $this->get_url() );
	}

	/**
	 * Return the fields to use for a connection to the given URL.
	 *
	 * Values from constants are only used if the URL is matching the server of these constants.
	 *
	 * @param string $url The URL.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_fields_for_url( string $url ): array {
		return Services::get_instance()->apply_constants_to_fields( $this->get_fields_without_constants(), $this->get_service_names_for_constants(), $url ); // @phpstan-ignore return.type
	}

	/**
	 * Return the fields as they are set, without values from constants.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_fields_without_constants(): array {
		return $this->fields;
	}

	/**
	 * Return the names of the services, whose constants should be used for the fields of this protocol.
	 *
	 * @return array<int,string>
	 */
	protected function get_service_names_for_constants(): array {
		return Services::get_instance()->get_service_names_for_constants( $this->get_name() );
	}

	/**
	 * Return the names of the services, to which the URL of this protocol belongs.
	 *
	 * @return array<int,string>
	 */
	protected function get_service_names_for_blocking(): array {
		return Services::get_instance()->get_service_names_for_blocking( $this->get_name() );
	}

	/**
	 * Return the service, which prevents the usage of the URL of this object, as its credentials are defined
	 * via constants for another server. Returns false if the URL could be used.
	 *
	 * @return Service_Base|false
	 */
	public function get_service_blocking_by_constants(): Service_Base|false {
		foreach ( array_unique( $this->get_service_names_for_blocking() ) as $service_name ) {
			// get the service object.
			$service_obj = Services::get_instance()->get_service_by_name( $service_name );

			// bail if this is not a service object.
			if ( ! $service_obj instanceof Service_Base ) {
				continue;
			}

			// return the service, if it blocks the URL.
			if ( $service_obj->is_target_blocked_by_constants( $this->get_url(), $this->get_fields_without_constants() ) ) {
				return $service_obj;
			}
		}

		// return false as the URL could be used.
		return false;
	}

	/**
	 * Set the fields.
	 *
	 * @param array<string,array<string,mixed>> $fields The fields.
	 *
	 * @return void
	 */
	public function set_fields( array $fields ): void {
		$this->fields = $fields;
	}

	/**
	 * Return whether this object has credentials set on its fields.
	 *
	 * @return bool
	 */
	protected function has_fields_with_credentials(): bool {
		// count the fields and the fields with credentials.
		$fields             = 0;
		$credentials_fields = 0;

		// loop through the fields.
		foreach ( $this->get_fields() as $settings ) {
			++$fields;
			if ( ! empty( $settings['value'] ) ) {
				++$credentials_fields;
			}
		}

		// return true if all credentials fields have values set.
		return $fields === $credentials_fields;
	}

	/**
	 * Return whether given URL is already exists in media library, it is then a duplicate.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if duplicate has been found.
	 */
	public function check_for_duplicate( string $url ): bool {
		// URLs are saved decoded in media library, so compare the decoded form.
		$url = urldecode( $url );

		// show deprecated hint for the old hook.
		$false = apply_filters_deprecated( 'eml_duplicate_check', array( false, $url ), '5.0.0', 'efml_duplicate_check' );

		/**
		 * Filter to prevent duplicate check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $false Must be true to prevent check.
		 * @param string $url The used URL.
		 */
		if ( apply_filters( 'efml_duplicate_check', $false, $url ) ) {
			return false;
		}

		// query for the file with same URL.
		$query   = array(
			'post_type'      => 'attachment',
			'post_status'    => array( 'inherit', 'trash' ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
				array(
					'key'     => EFML_POST_META_URL,
					'value'   => $url,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		$results = new WP_Query( $query );

		// return true if any file with this URL has been found.
		return $results->post_count > 0;
	}

	/**
	 * Return whether this URL could be checked for availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return true;
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * It is impossible if the file has credentials set.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return ! $this->has_fields_with_credentials();
	}

	/**
	 * Return whether this protocol is available in this hosting.
	 *
	 * This depends on the hosting, e.g., if necessary libraries are available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return false;
	}

	/**
	 * Return the link to the given URL.
	 *
	 * @return string
	 */
	public function get_link(): string {
		// get shorter URL to show (only protocol and host) to save space.
		$parsed_url = wp_parse_url( $this->get_url() );
		if ( ! empty( $parsed_url['scheme'] ) && ! empty( $parsed_url['host'] ) ) {
			return $parsed_url['scheme'] . '://' . $parsed_url['host'] . '..';
		}

		// return the plain URL.
		return $this->get_url();
	}

	/**
	 * Return temp file from given URL.
	 *
	 * @param string             $url The given URL.
	 * @param WP_Filesystem_Base $filesystem The file system handler.
	 *
	 * @return bool|string
	 */
	public function get_temp_file( string $url, WP_Filesystem_Base $filesystem ): bool|string {
		// bail if url is empty.
		if ( empty( $url ) ) {
			return false;
		}

		// bail if no filesystem is given.
		if ( empty( $filesystem ) ) { // @phpstan-ignore empty.variable
			return false;
		}

		// return false in any other case.
		return false;
	}

	/**
	 * Cleanup temporary files.
	 *
	 * @param string $file The path to the file.
	 *
	 * @return void
	 */
	public function cleanup_temp_file( string $file ): void {
		// bail if string is empty.
		if ( empty( $file ) ) {
			return;
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $file ) ) {
			return;
		}

		// delete the temporary file.
		$wp_filesystem->delete( $file );
	}

	/**
	 * Return a valid connection on WP_Filesystem_Base.
	 *
	 * @param string $url The URL to use for the connection.
	 *
	 * @return false|WP_Filesystem_Base
	 */
	public function get_connection( string $url ): false|WP_Filesystem_Base {
		if ( empty( $url ) ) {
			return false;
		}

		return false;
	}

	/**
	 * Return infos about single given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array<string,mixed>
	 */
	public function get_url_info( string $url ): array {
		if ( empty( $url ) ) {
			return array();
		}
		return array();
	}

	/**
	 * Check whether given content type is one where multiple files could be.
	 *
	 * @param string $mime_type The given content type.
	 * @param string $url       The used URL.
	 *
	 * @return bool
	 */
	protected function is_content_type_for_multiple_files( string $mime_type, string $url ): bool {
		$false = 'text/html' === $mime_type;

		// show deprecated hint for the old hook.
		$false = apply_filters_deprecated( 'eml_mime_type_for_multiple_files', array( $false, $mime_type, $url ), '5.0.0', 'efml_mime_type_for_multiple_files' );

		/**
		 * Filter whether the given mime type could provide multiple files.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 *
		 * @param bool   $false     Set to true for URL with multiple files.
		 * @param string $mime_type The given mime type.
		 * @param string $url       The used URL.
		 */
		return apply_filters( 'efml_mime_type_for_multiple_files', $false, $mime_type, $url );
	}

	/**
	 * Return whether URLs with this protocol are reachable via HTTP.
	 *
	 * This is not the availability of the URL.
	 *
	 * @return bool
	 */
	public function is_url_reachable(): bool {
		return true;
	}
}
