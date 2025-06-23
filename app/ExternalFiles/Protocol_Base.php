<?php
/**
 * File which provide the base functions for each protocol we support.
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
	 * The login.
	 *
	 * @var string
	 */
	private string $login = '';

	/**
	 * The password.
	 *
	 * @var string
	 */
	private string $password = '';

	/**
	 * Constructor, not used as this a Singleton object.
	 *
	 * @param string $url The URL to use.
	 */
	public function __construct( string $url ) {
		$this->url = $url;
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
		/**
		 * Filter the tcp protocols.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,int> $tcp_protocols List of tcp protocol of this object (e.g. 'http').
		 * @param Protocol_Base $instance The actual object.
		 */
		return apply_filters( 'eml_tcp_protocols', $tcp_protocols, $instance );
	}

	/**
	 * Return the default port of the used protocol.
	 *
	 * @param string $tcp_protocol The protocol (e.g. "ftps").
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
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		foreach ( $this->get_tcp_protocols() as $tcp_protocol => $port ) {
			if ( str_starts_with( $this->get_url(), $tcp_protocol ) ) {
				return true;
			}
		}

		// return false if no protocol has been found.
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
	 * @return array<int,array<string,mixed>> List of files with its infos.
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
	 * Return the login.
	 *
	 * @return string
	 */
	public function get_login(): string {
		return $this->login;
	}

	/**
	 * Set the login.
	 *
	 * @param string $login The login.
	 *
	 * @return void
	 */
	public function set_login( string $login ): void {
		$this->login = $login;
	}

	/**
	 * Return the password.
	 *
	 * @return string
	 */
	public function get_password(): string {
		return $this->password;
	}

	/**
	 * Set the password.
	 *
	 * @param string $password The password.
	 *
	 * @return void
	 */
	public function set_password( string $password ): void {
		$this->password = $password;
	}

	/**
	 * Check given URL for duplicate.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_for_duplicate( string $url ): bool {
		$false = false;
		/**
		 * Filter to prevent duplicate check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $false Must be true to prevent check.
		 * @param string $url The used URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( apply_filters( 'eml_duplicate_check', $false, $url ) ) {
			return false;
		}

		// query for file with same URL.
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
	 * It is not possible if the file has credentials set.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return empty( $this->get_login() ) && empty( $this->get_password() );
	}

	/**
	 * Return whether this protocol could be used.
	 *
	 * This depends on the hosting, e.g. if necessary libraries are available.
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
	 * Get temp file from given URL.
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

		// bail if file does not exist.
		if ( ! file_exists( $file ) ) {
			return;
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

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
	 * Get infos from single given URL.
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
		/**
		 * Filter whether the given mime type could provide multiple files.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 *
		 * @param bool   $false     Set to true for URL with multiple files.
		 * @param string $mime_type The given mime type.
		 * @param string $url       The used URL.
		 */
		return apply_filters( 'eml_mime_type_for_multiple_files', $false, $mime_type, $url );
	}
}
