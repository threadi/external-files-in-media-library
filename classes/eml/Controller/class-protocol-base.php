<?php
/**
 * File which provide the base functions for each protocol we support.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// Exit if accessed directly.
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Object to handle different protocols.
 */
class Protocol_Base {

	/**
	 * The given URL.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * List of supported tcp protocols.
	 *
	 * @var array
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
	 */
	public function __construct( string $url ) {
		$this->url = $url;
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
	 * @return array
	 */
	private function get_tcp_protocols(): array {
		$tcp_protocols = $this->tcp_protocols;

		/**
		 * Filter the tcp protocols.
		 *
		 * @since 1.4.0 Available since 1.4.0.
		 * @param array $tcp_protocols List of tcp protocol of this object (e.g. 'http').
		 */
		return apply_filters( 'eml_tcp_protocols', $tcp_protocols, $this );
	}

	/**
	 * Check if url is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		foreach( $this->get_tcp_protocols() as $tcp_protocol ) {
			if( str_starts_with( $this->get_url(), $tcp_protocol ) ) {
				return true;
			}
		}

		// return false if no protocol has been found.
		return false;
	}

	/**
	 * Check format of given URL.
	 *
	 * @return bool
	 */
	public function check_url(): bool {
		return false;
	}

	/**
	 * Check the availability of a given file-url.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability(): bool {
		return false;
	}

	/**
	 * Check the availability of a given file-url.
	 *
	 * @return array List of file-infos.
	 */
	public function get_external_file_infos(): array {
		return array(
			'filesize'  => 0,
			'mime-type' => '',
			'local'     => false,
		);
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
	protected function get_login(): string {
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
	protected function get_password(): string {
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
	 * @return bool
	 */
	protected function check_for_duplicate(): bool {
		$query   = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => EML_POST_META_URL,
					'value'   => $this->get_url(),
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
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return true;
	}
}
