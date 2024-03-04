<?php
/**
 * This file contains the proxy-handling.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use finfo;
use threadi\eml\Transients;

/**
 * Initialize the proxy-handler.
 */
class Proxy {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Proxy
	 */
	private static ?Proxy $instance = null;

	/**
	 * The slug for the query-var the proxy is using.
	 *
	 * @var string
	 */
	private string $slug = 'emlproxy';

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
	 * @return Proxy
	 */
	public static function get_instance(): Proxy {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize the proxy.
	 *
	 * @return void
	 */
	public function init(): void {
		/**
		 * Main init on each request.
		 */
		add_action( 'init', array( $this, 'wp_init' ), 10, 0 );

		/**
		 * Whitelist parameter for query-vars.
		 */
		add_filter( 'query_vars', array( $this, 'set_query_vars' ), 10, 1 );

		/**
		 * Run proxy to show called file.
		 */
		add_action( 'template_include', array( $this, 'run' ), 10, 1 );

		/**
		 * Refresh rewrite-cache if requested.
		 */
		add_action( 'wp', array( $this, 'do_refresh' ) );
	}

	/**
	 * Add rewrite rule for proxy.
	 *
	 * @return void
	 */
	public function wp_init(): void {
		add_rewrite_rule( $this->get_slug() . '/([a-z0-9-.]+)?$', 'index.php?' . self::get_instance()->get_slug() . '=$matches[1]', 'top' );
	}

	/**
	 * Whitelist the proxy-slug parameter in query-vars.
	 *
	 * @param array $query_vars The query-vars of the actual request.
	 *
	 * @return array
	 */
	public function set_query_vars( array $query_vars ): array {
		$query_vars[] = self::get_instance()->get_slug();
		return $query_vars;
	}

	/**
	 * Run proxy to show the called file.
	 *
	 * Check if the given file is an external file. Only if it is valid, proxy this file.
	 * Otherwise, do nothing.
	 *
	 * @param string $template The template.
	 *
	 * @return string
	 */
	public function run( string $template ): string {
		if ( empty( get_query_var( self::get_instance()->get_slug() ) ) ) {
			return $template;
		}

		// get the query-value.
		$title = get_query_var( self::get_instance()->get_slug() );

		// get file object.
		$external_file_obj = External_Files::get_instance()->get_file_by_title( $title );

		// bail if no file object could be loaded or the loaded object is not valid.
		if ( false === $external_file_obj || ( $external_file_obj && false === $external_file_obj->is_valid() ) ) {
			return $template;
		}

		// get cached file and return it.
		if ( $external_file_obj->is_cached() ) {
			$this->return_binary( $external_file_obj->get_cache_file(), $external_file_obj->get_mime_type(), $external_file_obj->get_filesize(), $external_file_obj->get_url( true ) );
		}

		// get the external file.
		$response = wp_remote_get( $external_file_obj->get_url( true ) );

		// if response was successfully.
		if ( false === is_wp_error( $response ) ) {

			// compare the retrieved mime-type with the saved mime-type.
			$mime_type = wp_remote_retrieve_header( $response, 'content-type' );
			if ( $mime_type !== $external_file_obj->get_mime_type() ) {
				// other mime-type received => do not proxy this file.
				return $template;
			}

			// get file size.
			$file_size = wp_remote_retrieve_header( $response, 'content-length' );

			// get body.
			$body = wp_remote_retrieve_body( $response );
			if ( empty( $body ) ) {
				return $template;
			}

			// check mime-type of the binary-data and compare it with header-data.
			$binary_data_info = new finfo( FILEINFO_MIME_TYPE );
			$binary_mime_type = $binary_data_info->buffer( $body );
			if ( $binary_mime_type !== $mime_type ) {
				return $template;
			}

			// add file to cache.
			$external_file_obj->add_cache( $body );

			// output the file.
			$this->return_binary( $external_file_obj->get_cache_file(), $mime_type, $file_size, $external_file_obj->get_url( true ) );
		}

		// fallback to 404-template.
		return $template;
	}

	/**
	 * Return binary of a file.
	 *
	 * @param string $file The local path to the file.
	 * @param string $mime_type The mime-type to return.
	 * @param int    $file_size The file-size of the binary string.
	 * @param string $url The url of the file as base for filename.
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	private function return_binary( string $file, string $mime_type, int $file_size, string $url ): void {
		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// return header.
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: inline; filename="' . basename( $url ) . '"' );
		header( 'Content-Length: ' . $file_size );

		// return file content via WP filesystem.
		echo $wp_filesystem->get_contents( $file ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Set to refresh the rewrite rules on next request.
	 *
	 * @return void
	 */
	public function set_refresh(): void {
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_action( array( 'threadi\eml\Controller\Proxy', 'do_refresh' ) );
		$transient_obj->set_name( 'eml_refresh_rewrite_rules' );
		$transient_obj->save();
	}

	/**
	 * Update slugs on request.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public static function do_refresh(): void {
		flush_rewrite_rules();
	}

	/**
	 * Return the slug this proxy is using for URLs.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		$slug = $this->slug;

		/**
		 * Filter the slug for the proxy-URL.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param string $slug The slug.
		 */
		return apply_filters( 'eml_proxy_slug', $slug );
	}
}
