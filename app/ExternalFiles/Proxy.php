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
use ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files;

/**
 * Object, which handles all proxy tasks.
 */
class Proxy {
	/**
	 * Marker if cache directory has been checked.
	 *
	 * @var bool
	 */
	private bool $cache_directory_checked = false;

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
		add_filter( 'efml_site_health_endpoints', array( $this, 'add_site_health_endpoint' ) );
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

		// remove the filter.
		remove_filter( 'efml_http_header_args', array( $external_file_obj, 'disable_check_for_unsafe_urls' ) );

		// get cached file path.
		$cached_file_path = $external_file_obj->get_cache_file( $dimensions );

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $cached_file_path ) ) {
			// log this event.
			/* translators: %1$s will be replaced by the detected filename. */
			Log::get_instance()->create( sprintf( __( 'The requested file %1$s for the proxy does not exist.', 'external-files-in-media-library' ), '<code>' . $external_file_obj->get_cache_file() . '</code>' ), $external_file_obj->get_url( true ), 'error' );

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
		// check only once per request.
		if ( $this->cache_directory_checked ) {
			return;
		}
		$this->cache_directory_checked = true;

		// get the WP_Filesystem handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// create the directory if it does not exist yet.
		if ( ! $wp_filesystem->exists( $path ) && false === $wp_filesystem->mkdir( $path ) ) {
			/* translators: %1$s will be replaced by the path. */
			Log::get_instance()->create( sprintf( __( 'Proxy could not create cache directory %1$s.', 'external-files-in-media-library' ), $path ), '', 'error' );

			// do nothing more.
			return;
		}

		// run securing the cache directory.
		$this->secure_cache_directory( $path );
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

		// check capability.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			wp_send_json( array() );
		}

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

	/**
	 * Secure the cache directory.
	 *
	 * We add files there depending on the used web-server.
	 *
	 * @param string $path The path to the directory.
	 *
	 * @return void
	 */
	public function secure_cache_directory( string $path ): void {
		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get file system permissions as int.
		$mode = 0644;
		/**
		 * Filter the filesystem permissions.
		 *
		 * @since 5.2.0 Available since 5.2.0.
		 * @param int $mode The mode as int, e.g., 0644.
		 */
		$mode = apply_filters( 'efml_fs_chmod', $mode );

		// add "index.php" to prevent directoryListing on each webserver.
		if ( ! $wp_filesystem->exists( $path . 'index.php' ) ) {
			// create the default content for this file.
			$index_php = '.';

			/**
			 * Change content of index.php to prevent access of cache file directory.
			 *
			 * @since 5.2.0 Available since 5.2.0.
			 * @param string $index_php The content of the file.
			 */
			$index_php = apply_filters( 'efml_cache_file_index_php', $index_php );

			// save the file.
			$wp_filesystem->put_contents( $path . 'index.php', $index_php, $mode );
		}

		// create a .htaccess with the required entries also if Apache is not used (could be nginx with .htaccess-support).
		if ( ! $wp_filesystem->exists( $path . '.htaccess' ) ) {
			// create the default content for this file.
			$htaccess = 'Require all denied';

			/**
			 * Change content of .htaccess to prevent access of cache files.
			 *
			 * @since 5.2.0 Available since 5.2.0.
			 * @param string $htaccess The content of the file.
			 */
			$htaccess = apply_filters( 'efml_cache_file_htaccess', $htaccess );

			// save the file.
			$wp_filesystem->put_contents( $path . '.htaccess', $htaccess, $mode );
		}

		// add "web.config" to prevent all requests for IIS webserver.
		if ( ! $wp_filesystem->exists( $path . 'web.config' ) ) {
			// create the default content for this file.
			$web_config = "<configuration>\n<system.webServer>\n<authorization>\n<deny users=\"*\" />\n</authorization>\n</system.webServer>\n</configuration>\n";

			/**
			 * Change content of web.config to prevent access of cache files.
			 *
			 * @since 5.2.0 Available since 5.2.0.
			 * @param string $htaccess The content of the file.
			 */
			$web_config = apply_filters( 'efml_cache_file_web_config', $web_config );

			// save the file.
			$wp_filesystem->put_contents( $path . 'web.config', $web_config, $mode );
		}

		// add the probe file used to check the public availability of this directory.
		if ( ! $wp_filesystem->exists( $path . $this->get_probe_file_name() ) ) {
			$wp_filesystem->put_contents( $path . $this->get_probe_file_name(), wp_generate_password( 32, false ), $mode );
		}
	}

	/**
	 * Add a custom endpoint for site health.
	 *
	 * @param array<int,array<string,mixed>> $endpoints List of endpoints.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function add_site_health_endpoint( array $endpoints ): array {
		// add the endpoint.
		$endpoints[] = array(
			'label'     => Helper::get_plugin_name() . ' ' . __( 'Proxy', 'external-files-in-media-library' ),
			'namespace' => 'efml/v1',
			'route'     => '/proxy/',
			'callback'  => array( $this, 'check_proxy' ),
			'args'      => array(),
		);

		// return the resulting list of endpoints.
		return $endpoints;
	}

	/**
	 * Return result after checking cronjob-states.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnused
	 */
	public function check_proxy(): array {
		// define default results.
		$result = array(
			'label'       => __( 'Proxy cache directory is protected', 'external-files-in-media-library' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'External Files in Media Library', 'external-files-in-media-library' ),
				'color' => 'gray',
			),
			'description' => __( 'We check the protection of the proxy cache directory.<br><strong>All ok with the directory!</strong>', 'external-files-in-media-library' ),
			'actions'     => '',
			'test'        => 'efml_test_proxy',
		);

		// get the state of the cache directory.
		$is_public = $this->is_cache_directory_public();

		// show a hint if the check could not be run.
		if ( is_null( $is_public ) ) {
			$result['label']       = __( 'Protection of the proxy cache directory could not be checked', 'external-files-in-media-library' );
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . __( 'We tried to request a test file from the proxy cache directory of <em>External Files in Media Library</em>, but the request did not reach your site. This often happens if loopback requests are blocked, which is common on local or firewalled installations.', 'external-files-in-media-library' ) . '</p><p>' . __( 'This is not an error by itself. It only means we cannot tell you whether cached files are reachable from the outside.', 'external-files-in-media-library' ) . '</p>';

			// return this result.
			return $result;
		}

		// return the good result if the directory is protected.
		if ( ! $is_public ) {
			return $result;
		}

		// the directory is public - explain the consequence and how to fix it.
		$result['label']  = __( 'Proxy cache directory is publicly reachable', 'external-files-in-media-library' );
		$result['status'] = 'recommended';

		$result['description']  = '<p>' . __( 'Files cached by the proxy of <em>External Files in Media Library</em> can be requested directly through your web server, bypassing the proxy. Your web server appears to ignore the <code>.htaccess</code> file we placed in the cache directory, which is normal for nginx and Caddy.', 'external-files-in-media-library' ) . '</p>';
		$result['description'] .= '<p>' . __( 'Anyone who learns a cache URL can keep using it.', 'external-files-in-media-library' ) . '</p>';
		/* translators: %1$s will be replaced by the path of the cache directory. */
		$result['description'] .= '<p>' . sprintf( __( 'To close this gap, deny access to <code>%1$s</code> in your web server configuration. For nginx, add the following inside your server block and reload the configuration:', 'external-files-in-media-library' ), esc_html( $this->get_cache_url() ) ) . '</p>';
		$result['description'] .= '<code>location ^~ ' . esc_html( (string) wp_parse_url( $this->get_cache_url(), PHP_URL_PATH ) ) . " {\n    deny all;\n}</code>";
		$result['description'] .= '<p>' . __( 'If you cannot change the server configuration, ask your hosting provider to add this rule for you.', 'external-files-in-media-library' ) . '</p>';

		/* translators: %1$s will be replaced by a URL. */
		$result['actions'] = '<p><a href="' . esc_url( Helper::get_plugin_support_url() ) . '" target="_blank">' . __( 'Ask for help in our support forum', 'external-files-in-media-library' ) . '</a></p>';

		// return the result.
		return $result;
	}

	/**
	 * Return the public URL of the cache directory.
	 *
	 * @return string Empty string if the directory is not inside the content directory.
	 */
	public function get_cache_url(): string {
		// get the absolute paths, normalized for a reliable comparison.
		$cache_path   = wp_normalize_path( $this->get_cache_directory() );
		$content_path = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		// bail if the cache directory is outside the content directory - it has no public URL then.
		if ( ! str_starts_with( $cache_path, $content_path ) ) {
			return '';
		}

		// replace the path part with the matching URL.
		return trailingslashit( content_url() ) . substr( $cache_path, strlen( $content_path ) );
	}

	/**
	 * Return the name of the file used to check the public availability of the cache directory.
	 *
	 * @return string
	 */
	private function get_probe_file_name(): string {
		return 'efml-probe.txt';
	}

	/**
	 * Return whether the cache directory is publicly reachable via HTTP.
	 *
	 * @return bool|null True if reachable, false if blocked, null if it could not be checked.
	 */
	public function is_cache_directory_public(): ?bool {
		// get the URL of the cache directory.
		$cache_url = $this->get_cache_url();

		// bail if no public URL could be determined.
		if ( '' === $cache_url ) {
			return null;
		}

		// get the probe file.
		$probe_file = $this->get_cache_directory() . $this->get_probe_file_name();

		// bail if the probe file does not exist.
		if ( ! file_exists( $probe_file ) ) {
			return null;
		}

		// get its content, which is the token we expect in the response.
		$wp_filesystem = Helper::get_wp_filesystem();
		$token         = trim( (string) $wp_filesystem->get_contents( $probe_file ) );

		// bail if the token could not be read.
		if ( '' === $token ) {
			return null;
		}

		// request the probe file.
		$response = wp_remote_get(
			$cache_url . $this->get_probe_file_name(),
			array(
				'timeout'   => 5,
				'sslverify' => false,
			)
		);

		// bail if the request itself failed - we cannot tell anything then.
		if ( is_wp_error( $response ) ) {
			return null;
		}

		// bail if the response is not a 200.
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		// compare the body with the token to rule out soft-404s and caching layers.
		return trim( wp_remote_retrieve_body( $response ) ) === $token;
	}
}
