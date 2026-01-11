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
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		return $this->fields;
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
			'meta_query'     => array(
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
	 * Return whether the file using this protocol is available.
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
