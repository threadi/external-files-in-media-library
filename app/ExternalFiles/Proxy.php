<?php
/**
 * This file contains the proxy tasks.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object, which handles all proxy tasks.
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
		// misc.
		add_action( 'wp_ajax_eml_reset_proxy', array( $this, 'reset_via_ajax' ) );

		// bail if no proxy is enabled.
		if ( ! $this->is_any_proxy_enabled() ) {
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

		// misc.
		add_filter( 'efml_file_prevent_proxied_url', array( $this, 'prevent_proxied_url' ), 10, 2 );
		add_filter( 'efml_table_column_file_source_dialog', array( $this, 'show_cache_state_in_info_dialog' ), 10, 2 );
	}

	/**
	 * Add rewrite rule for proxy.
	 *
	 * @return void
	 */
	public function wp_init(): void {
		add_rewrite_rule( $this->get_slug() . '/([a-zA-Z0-9-_.]+)?$', 'index.php?' . $this->get_slug() . '=$matches[1]', 'top' );
	}

	/**
	 * Whitelist the proxy-slug parameter in query-vars.
	 *
	 * @param array<string> $query_vars The query-vars of the actual request.
	 *
	 * @return array<string>
	 */
	public function set_query_vars( array $query_vars ): array {
		$query_vars[] = $this->get_slug();
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
		if ( empty( get_query_var( $this->get_slug() ) ) ) {
			return $template;
		}

		// get the query-value.
		$title = get_query_var( $this->get_slug() );

		// get basename from request for sized images depending on its dimensions.
		$dimensions = array();
		if ( 1 === preg_match( '/(.*)-(.*)x(.*)\.(.*)/', $title, $matches ) ) {
			$dimensions = array(
				absint( $matches[2] ),
				absint( $matches[3] ),
			);
			$title      = $matches[1] . '.' . $matches[4];
		}

		// log this event.
		/* translators: %1$s will be replaced by the detected filename. */
		Log::get_instance()->create( sprintf( __( 'Proxy tries to load the filename %1$s.', 'external-files-in-media-library' ), '<code>' . $title . '</code>' ), '', 'info', 2 );

		// get file object.
		$external_file_obj = Files::get_instance()->get_file_by_title( $title );

		// bail if no file object could be loaded, or the loaded object is not valid.
		if ( ! $external_file_obj || ! ( $external_file_obj instanceof File && $external_file_obj->is_valid() ) ) {
			// log this event.
			/* translators: %1$s will be replaced by the detected filename. */
			Log::get_instance()->create( sprintf( __( 'Proxy could not load the filename %1$s as external file.', 'external-files-in-media-library' ), '<code>' . $title . '</code>' ), '', 'error' );

			// fallback to 404.
			return $template;
		}

		/**
		 * Run additional tasks before proxy tries to load a cached external file.
		 */
		do_action( 'efml_proxy_before', $external_file_obj );

		// if original file is not cached, do it now.
		if ( ! $external_file_obj->is_cached() ) {
			// log this event.
			Log::get_instance()->create( __( 'The proxy creates a cache for the file.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );

			// add it to cache.
			$external_file_obj->add_to_proxy();
		}

		// get cached file path.
		$cached_file_path = $external_file_obj->get_cache_file( $dimensions );

		// bail if file does not exist.
		if ( ! file_exists( $cached_file_path ) ) {
			// log this event.
			/* translators: %1$s will be replaced by the detected filename. */
			Log::get_instance()->create( sprintf( __( 'The requested file %1$s for proxy does not exist.', 'external-files-in-media-library' ), '<code>' . $external_file_obj->get_cache_file() . '</code>' ), $external_file_obj->get_url( true ), 'error' );

			// return the template.
			return $template;
		}

		// get the object of this file type.
		$file_type_obj = File_Types::get_instance()->get_type_object_by_mime_type( $external_file_obj->get_mime_type(), $external_file_obj );
		$file_type_obj->set_dimensions( $dimensions );

		// log this event.
		Log::get_instance()->create( __( 'Proxy will now output the cached filed.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );

		// output the proxied file.
		$file_type_obj->get_proxied_file();

		// fallback to 404.
		return $template;
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
		// flush the permalinks.
		flush_rewrite_rules();

		// delete marker.
		Transients::get_instance()->get_transient_by_name( 'eml_refresh_rewrite_rules' )->delete();
	}

	/**
	 * Return the slug this proxy is using for URLs.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		$slug = $this->slug;

		// show deprecated warning for the old hook name.
		$slug = apply_filters_deprecated( 'eml_proxy_slug', array( $slug ), '5.0.0', 'efml_proxy_slug' );

		/**
		 * Filter the slug for the proxy-URL.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param string $slug The slug.
		 */
		return apply_filters( 'efml_proxy_slug', $slug );
	}

	/**
	 * Return the cache-directory for proxied external files.
	 * Handles also the existence of the directory.
	 *
	 * @return string
	 */
	public function get_cache_directory(): string {
		// get setting for the proxy path.
		$path_part = get_option( 'eml_proxy_path', 'cache/eml/' );

		// create string with the path for the directory.
		$path = trailingslashit( WP_CONTENT_DIR ) . $path_part;

		// show deprecated warning for the old hook name.
		$path = apply_filters_deprecated( 'eml_proxy_path', array( $path ), '5.0.0', 'efml_proxy_path' );

		/**
		 * Filter the cache directory.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $path The absolute path to the directory.
		 */
		$path = apply_filters( 'efml_proxy_path', $path );

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
		// bail if path exist.
		if ( file_exists( $path ) ) {
			return;
		}

		// create the directory and check response.
		if ( false === wp_mkdir_p( $path ) ) {
			/* translators: %1$s will be replaced by the path. */
			Log::get_instance()->create( sprintf( __( 'Proxy could not create cache directory %1$s.', 'external-files-in-media-library' ), $path ), '', 'error' );
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

	/**
	 * Reset the proxy via AJAX request.
	 *
	 * @return void
	 */
	public function reset_via_ajax(): void {
		// check referer.
		check_ajax_referer( 'eml-reset-proxy-nonce', 'nonce' );

		// reset by deleting the directory.
		$this->delete_cache_directory();

		// create answer dialog.
		$dialog = array(
			'detail' => array(
				'className' => 'efml',
				'title'     => __( 'Proxy has been reset', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p>' . __( 'The proxy has been reset.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			),
		);

		// log this event.
		Log::get_instance()->create( __( 'The proxy cache has been reset via AJAX.', 'external-files-in-media-library' ), '', 'info', 2 );

		// response with the dialog.
		wp_send_json( $dialog );
	}

	/**
	 * Return true if any proxy for any file is enabled.
	 *
	 * @return bool
	 */
	public function is_any_proxy_enabled(): bool {
		// check each supported file type.
		foreach ( File_Types::get_instance()->get_file_types() as $file_type ) {
			// bail if object does not exist.
			if ( ! class_exists( $file_type ) ) {
				continue;
			}

			// get the object.
			$file_type_obj = new $file_type( false );

			// bail if object is not a file type base object.
			if ( ! $file_type_obj instanceof File_Types_Base ) {
				continue;
			}

			// bail if proxy for this file type is not enabled.
			if ( ! $file_type_obj->is_proxy_enabled() ) {
				continue;
			}

			// return true if proxy is enabled.
			return true;
		}

		// return false if no proxy is enabled.
		return false;
	}

	/**
	 * Return whether proxy is enabled for single external file depending on its file type settings.
	 *
	 * @param bool $result               The result.
	 * @param File $external_file_object The file object.
	 *
	 * @return bool
	 */
	public function prevent_proxied_url( bool $result, File $external_file_object ): bool {
		// bail if result is already false.
		if ( ! $result ) {
			return false;
		}

		// return the result of proxy setting on file-type of this file.
		return $external_file_object->get_file_type_obj()->is_proxy_enabled();
	}

	/**
	 * Add info about proxy cache usage in info dialog for single external file.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param File                $external_file The external file object.
	 *
	 * @return array<string,mixed>
	 */
	public function show_cache_state_in_info_dialog( array $dialog, File $external_file ): array {
		$dialog['texts'][] = '<p><strong>' . __( 'Proxied', 'external-files-in-media-library' ) . ':</strong> ' . ( $external_file->is_cached() ? __( 'will be used.', 'external-files-in-media-library' ) : __( 'will not be used.', 'external-files-in-media-library' ) ) . '</p>';
		return $dialog;
	}
}
