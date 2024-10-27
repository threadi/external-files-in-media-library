<?php
/**
 * This file contains the proxy-handling.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Transients;

/**
 * Object which handles all proxy tasks.
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
		// bail if proxy is not enabled.
		if ( 0 === absint( get_option( 'eml_proxy', 0 ) ) ) {
			return;
		}

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
		add_filter( 'template_include', array( $this, 'run' ), 10, 1 );

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
		// bail if this is not our proxy-slug.
		if ( empty( get_query_var( self::get_instance()->get_slug() ) ) ) {
			return $template;
		}

		// get the query-value.
		$title = get_query_var( self::get_instance()->get_slug() );

		// get file object.
		$external_file_obj = Files::get_instance()->get_file_by_title( $title );

		// bail if no file object could be loaded or the loaded object is not valid.
		if ( false === $external_file_obj || ( $external_file_obj && false === $external_file_obj->is_valid() ) ) {
			// fallback to 404.
			return $template;
		}

		// if file is not cached, do it now.
		if ( ! $external_file_obj->is_cached() ) {
			$external_file_obj->add_to_cache();
		}

		// output the cached file.
		$this->return_binary( $external_file_obj->get_cache_file(), $external_file_obj->get_mime_type(), $external_file_obj->get_filesize(), $external_file_obj->get_url( true ) );

		// fallback to 404.
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
		$transient_obj->set_action( array( 'ExternalFilesInMediaLibrary\ExternalFiles\Proxy', 'do_refresh' ) );
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

	/**
	 * Return the cache-directory for proxied external files.
	 * Handles also the existence of the directory.
	 *
	 * @return string
	 */
	public function get_cache_directory(): string {
		// create string with path for directory.
		$path = trailingslashit( WP_CONTENT_DIR ) . 'cache/eml/';

		// create it if necessary.
		$this->create_cache_directory( $path );

		// return path.
		return $path;
	}

	/**
	 * Create cache directory.
	 *
	 * @param string $path The path to the cache directory.
	 * @return void
	 */
	private function create_cache_directory( string $path ): void {
		// bail if file exist.
		if ( file_exists( $path ) ) {
			return;
		}

		// create directory and check response.
		if ( false === wp_mkdir_p( $path ) ) {
			Log::get_instance()->create( __( 'Error creating cache directory.', 'external-files-in-media-library' ), '', 'error', 0 );
		}
	}

	/**
	 * Delete the cache directory.
	 *
	 * @return void
	 */
	public function delete_cache_directory(): void {
		Helper::delete_directory_recursively( $this->get_cache_directory() );
	}
}
