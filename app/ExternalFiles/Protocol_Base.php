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
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Filesystem_Base;

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
	 * The external file this object is used for, if known.
	 *
	 * @var File|null
	 */
	private ?File $external_file = null;

	/**
	 * Cache for the complete list of files of a directory during this request.
	 *
	 * @var array<string,array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}>>
	 */
	private static array $file_list_cache = array();

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
	 * Return whether this protocol could list the files of a directory page by page, without loading them.
	 *
	 * Protocols with this support import directories via import runs: the files are listed first and then
	 * imported one by one, in the background for large directories.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function supports_file_list(): bool {
		return false;
	}

	/**
	 * Return whether the URL of this object is a directory, whose files could be listed via get_file_list().
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function is_directory(): bool {
		return false;
	}

	/**
	 * Return one page of the files in the directory of the URL of this object, without loading the files.
	 *
	 * The list contains the same files, which the import of the directory via get_url_infos() would import.
	 * Each entry contains the URL to import the single file and optional its size and its modification time
	 * (as timestamp). If the import saves the file with another URL in the media library (e.g. with its public
	 * URL), this URL is given as "media-url", so files, which are already in the media library, are detected.
	 *
	 * The cursor is empty for the first page. The result contains the cursor for the next page, which is empty
	 * if this was the last page. On errors the method returns false and logs the error.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $cursor The cursor for the requested page.
	 *
	 * @return array{files: array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}>, cursor: string}|false
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_file_list( string $cursor ): array|false { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array(
			'files'  => array(),
			'cursor' => '',
		);
	}

	/**
	 * Return the maximum count of files per page of get_file_list().
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return int
	 */
	protected function get_file_list_page_size(): int {
		$instance = $this;

		/**
		 * Filter the maximum count of files, which are listed per request to the external source during an import run.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $page_size The page size.
		 * @param Protocol_Base $instance The protocol object.
		 */
		return max( 1, absint( apply_filters( 'efml_file_list_page_size', 500, $instance ) ) );
	}

	/**
	 * Return the complete list of files of the directory of this object for get_file_list(), loaded only once per
	 * request (e.g. for protocols, which could only load the complete list of a directory with one request).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param callable $loader Callback, which loads the list of files (or returns false on errors).
	 * @param bool     $reload True to load the list again (e.g. for the first page).
	 *
	 * @return array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}>|false
	 */
	protected function get_cached_file_list( callable $loader, bool $reload = false ): array|false {
		// get the key for this directory (with the depth of subdirectories, as the list depends on it).
		$key = static::class . '|' . $this->get_url() . '|' . ( $this->is_subdirectories_enabled() ? $this->get_subdirectories_max_depth() : 0 );

		// load the list, if it is not cached or the first page is requested (e.g. by a new run).
		if ( $reload || ! isset( self::$file_list_cache[ $key ] ) ) {
			$files = $loader();

			// bail on errors.
			if ( ! is_array( $files ) ) {
				return false;
			}

			// cache only one list to limit the used memory.
			self::$file_list_cache = array( $key => $files );
		}

		// return the list.
		return self::$file_list_cache[ $key ];
	}

	/**
	 * Return the given list of files as page for get_file_list(), starting at the position in the given cursor.
	 *
	 * Helper for protocols, which load the complete list of a directory with one request (e.g. local, FTP).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}> $files  The complete list of files.
	 * @param string                                                                                 $cursor The cursor with the position.
	 *
	 * @return array{files: array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}>, cursor: string}
	 */
	protected function get_file_list_page( array $files, string $cursor ): array {
		// get the position.
		$offset = absint( $cursor );

		// get the page.
		$page = array_slice( array_values( $files ), $offset, $this->get_file_list_page_size() );

		// get the position of the next page.
		$next = $offset + count( $page );

		// return the page with the cursor for the next page, if there are more files.
		return array(
			'files'  => $page,
			'cursor' => $next < count( $files ) ? (string) $next : '',
		);
	}

	/**
	 * Return whether this protocol could use the files of subdirectories during a synchronization.
	 *
	 * Only then the option for subdirectories is shown in the configuration of the synchronization.
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @return bool
	 */
	public function supports_subdirectories(): bool {
		return false;
	}

	/**
	 * Return whether this protocol uses the files of subdirectories by default.
	 *
	 * This is the behavior of the protocol without any setting, also for the import of a directory. Services, which
	 * use subdirectories since ever (e.g. Dropbox), return true.
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @return bool
	 */
	public function get_subdirectories_default(): bool {
		return false;
	}

	/**
	 * Return whether the files of subdirectories should be used for the URL of this object.
	 *
	 * Without the synchronization this is always the default of the protocol (see get_subdirectories_default()).
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @return bool
	 */
	public function is_subdirectories_enabled(): bool {
		// get the default of this protocol.
		$default = $this->get_subdirectories_default();

		// bail if this protocol does not support subdirectories.
		if ( ! $this->supports_subdirectories() ) {
			return $default;
		}

		$url      = $this->get_url();
		$instance = $this;

		/**
		 * Filter whether the files of subdirectories should be used.
		 *
		 * The setting of the synchronization uses this filter during a synchronization.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param bool $default True to use the files of subdirectories (the default of the protocol or the service).
		 * @param string $url The URL of the directory.
		 * @param Protocol_Base|null $instance The protocol object, null if the files are not listed via a protocol (e.g. in archives).
		 */
		return (bool) apply_filters( 'efml_include_subdirectories', $default, $url, $instance );
	}

	/**
	 * Return the maximum depth of subdirectories, whose files are used.
	 *
	 * The depth 1 means the direct subdirectories of the directory.
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @return int
	 */
	public function get_subdirectories_max_depth(): int {
		$instance = $this;

		/**
		 * Filter the maximum depth of subdirectories, whose files are used.
		 *
		 * If the depth is lowered, files in deeper subdirectories are deleted during the next synchronization,
		 * if unused files are deleted after the synchronization.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param int $depth The maximum depth (1 for the direct subdirectories).
		 * @param Protocol_Base $instance The protocol object.
		 */
		return max( 1, absint( apply_filters( 'efml_subdirectories_max_depth', 10, $instance ) ) );
	}

	/**
	 * Return the names of the directories, which are never used as subdirectories (e.g. "node_modules").
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @return array<int,string>
	 */
	public function get_excluded_directories(): array {
		$list = array(
			'node_modules',
			'vendor',
			'tmp',
			'.git',
			'.svn',
		);

		$instance = $this;

		/**
		 * Filter the names of the directories, which are never used as subdirectories.
		 *
		 * The list is used for each protocol (e.g. FTP, SFTP or local directories). Use the protocol object or its
		 * name to change the list for a single service.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param array<int,string> $list List of directory names.
		 * @param Protocol_Base $instance The protocol object.
		 */
		$list = apply_filters( 'efml_subdirectories_excluded', $list, $instance );

		// return the list.
		return array_values( array_map( 'strval', (array) $list ) );
	}

	/**
	 * Return the path of the directory of the given file URL relative to the URL of this object.
	 *
	 * The result is empty for files directly in the directory of this object or for files outside of it. The parts
	 * of the path are separated by "/".
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @param string $file_url The URL of the file.
	 *
	 * @return string
	 */
	public function get_relative_directory( string $file_url ): string {
		// get the base URL and the URL of the file in the same form.
		$base = trailingslashit( $this->normalize_url_for_relative_directory( $this->get_url() ) );
		$file = $this->normalize_url_for_relative_directory( $file_url );

		// bail if the file is not in the directory.
		if ( ! str_starts_with( $file, $base ) ) {
			return '';
		}

		// get the directory of the file relative to the base.
		$directory = dirname( substr( $file, strlen( $base ) ) );

		// return the directory, empty for files directly in the base directory.
		return '.' === $directory ? '' : $directory;
	}

	/**
	 * Return the given URL in the form, which is used to compare the URLs of files with the URL of this object.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	protected function normalize_url_for_relative_directory( string $url ): string {
		return $url;
	}

	/**
	 * Return the entries of the given directory, with the entries of its subdirectories, if they should be used.
	 *
	 * The loader returns the list of a single directory in the form of WP_Filesystem_Base::dirlist() (the names as
	 * keys, the data with "type", "size" and "lastmodunix" as values) or false on errors. It gets the path and true
	 * as second parameter for subdirectories. The result contains only
	 * files, with their path relative to the given directory as key (e.g. "subdirectory/file.jpg"), sorted by the
	 * directories and the names, so the list is the same on each request.
	 *
	 * Subdirectories, which could not be read, are skipped (see skip_subdirectory()). Subdirectories, which are links
	 * (if the callback for links is given), which are in the list of excluded directories or which are deeper than the
	 * maximum depth, are not used.
	 *
	 * @param string        $path     The path of the directory for the loader.
	 * @param callable      $loader   The callback to load the entries of a directory by its path.
	 * @param callable|null $is_link  Optional callback, which returns whether the given path is a link.
	 * @param string        $base_url The URL of the directory, with which the URLs of its files start (default: the URL of this object).
	 *
	 * @return array<string,array<string,mixed>>|false
	 */
	protected function get_directory_entries( string $path, callable $loader, ?callable $is_link = null, string $base_url = '' ): array|false {
		// get the entries of the directory itself.
		$entries = $loader( $path );

		// bail if the directory could not be read.
		if ( ! is_array( $entries ) ) {
			return false;
		}

		// return the entries with the entries of the subdirectories.
		return $this->collect_directory_entries( $entries, $path, '' !== $base_url ? $base_url : $this->get_url(), '', 1, $loader, $is_link, $this->is_subdirectories_enabled() );
	}

	/**
	 * Collect the files of the given entries of a directory and of its subdirectories.
	 *
	 * @param array<int|string,mixed> $entries   The entries of the directory.
	 * @param string                  $path      The path of the directory for the loader.
	 * @param string                  $base_url  The URL of the main directory.
	 * @param string                  $prefix    The path of the directory relative to the main directory (with trailing slash).
	 * @param int                     $depth     The depth of the subdirectories of this directory.
	 * @param callable                $loader    The callback to load the entries of a directory by its path.
	 * @param callable|null           $is_link   Optional callback, which returns whether the given path is a link.
	 * @param bool                    $recursive Whether the subdirectories should be used.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function collect_directory_entries( array $entries, string $path, string $base_url, string $prefix, int $depth, callable $loader, ?callable $is_link, bool $recursive ): array {
		// sort the entries by their names, so the list is the same on each request.
		ksort( $entries, SORT_STRING );

		// collect the files and the subdirectories.
		$files       = array();
		$directories = array();
		foreach ( $entries as $name => $settings ) {
			// get the name.
			$name = (string) $name;

			// bail for the entries of the directory itself.
			if ( '' === $name || '.' === $name || '..' === $name ) {
				continue;
			}

			// not all servers return all data for each file.
			$settings = wp_parse_args(
				is_array( $settings ) ? $settings : array(),
				array(
					'type'        => 'f',
					'size'        => 0,
					'lastmodunix' => 0,
				)
			);

			// remember subdirectories.
			if ( 'd' === $settings['type'] ) {
				$directories[] = $name;
				continue;
			}

			// add the file.
			$files[ $prefix . $name ] = $settings;
		}

		// bail if subdirectories should not be used.
		if ( ! $recursive || empty( $directories ) ) {
			return $files;
		}

		// bail if the maximum depth is reached.
		if ( $depth > $this->get_subdirectories_max_depth() ) {
			return $files;
		}

		// get the excluded directories.
		$excluded = $this->get_excluded_directories();

		// add the files of the subdirectories.
		foreach ( $directories as $name ) {
			// bail if this directory is excluded.
			if ( in_array( $name, $excluded, true ) ) {
				continue;
			}

			// get the path of the subdirectory.
			$sub_path = trailingslashit( $path ) . $name;

			// bail if the subdirectory is a link (links are never followed).
			if ( is_callable( $is_link ) && $is_link( $sub_path ) ) {
				continue;
			}

			// get the entries of the subdirectory (the loader gets true as second parameter for subdirectories).
			$sub_entries = $loader( $sub_path, true );

			// skip the subdirectory, if it could not be read.
			if ( ! is_array( $sub_entries ) ) {
				$this->skip_subdirectory( trailingslashit( $base_url ) . $prefix . $name );
				continue;
			}

			// add its files.
			$files = array_merge( $files, $this->collect_directory_entries( $sub_entries, $sub_path, $base_url, $prefix . $name . '/', $depth + 1, $loader, $is_link, true ) );
		}

		// return the list of files.
		return $files;
	}

	/**
	 * Mark the given subdirectory as skipped, as it could not be read.
	 *
	 * During a synchronization, the files in skipped subdirectories are not deleted.
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @param string $url The URL of the subdirectory.
	 *
	 * @return void
	 */
	protected function skip_subdirectory( string $url ): void {
		// log this event.
		Log::get_instance()->create( __( 'The subdirectory could not be read and has been skipped.', 'external-files-in-media-library' ), $url, 'info', 1, Import::get_instance()->get_identifier() );

		$instance = $this;

		/**
		 * Run action if a subdirectory has been skipped, as it could not be read.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param string $url The URL of the subdirectory.
		 * @param Protocol_Base $instance The protocol object.
		 */
		do_action( 'efml_subdirectory_skipped', $url, $instance );
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
	 * Set the external file this object is used for.
	 *
	 * Protocols could use it to get the access data of the file (e.g. the connection of the owner of its external
	 * source), independent of the actual user.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param File $external_file The external file.
	 *
	 * @return void
	 */
	public function set_external_file( File $external_file ): void {
		$this->external_file = $external_file;
	}

	/**
	 * Return the external file this object is used for, if known.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return File|null
	 */
	public function get_external_file(): ?File {
		return $this->external_file;
	}

	/**
	 * Return whether files could be imported with this protocol in the actual request.
	 *
	 * Protocols of services are also registered for visitors (e.g. to deliver private files via the proxy), so
	 * their imports must check the capability of the service: protocols of services override this method.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function is_import_allowed_for_user(): bool {
		return true;
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

		// return true if any file with this URL has been found (via the index of the database).
		return Url_Index::get_instance()->find( $url ) > 0;
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
	 * Return a signed URL for the given URL of a private file, which is valid in the given time range.
	 *
	 * Used by the proxy to redirect to private files, which are not delivered from the cache, and for downloads
	 * of WooCommerce products. The URL must be valid from the start to the expiration time. Services, which can
	 * sign URLs without a request to their API, should create the same URL for the same time range, so browsers and
	 * caches can reuse it. Return an empty string if this is not possible, then the file is delivered from the cache.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url     The URL of the file (as saved on the external file).
	 * @param int    $start   The start of the validity as timestamp.
	 * @param int    $expires The end of the validity as timestamp.
	 *
	 * @return string
	 */
	public function get_signed_url( string $url, int $start, int $expires ): string {
		// bail if url is empty.
		if ( empty( $url ) ) {
			return '';
		}

		// bail if time range is not valid.
		if ( $expires <= $start ) {
			return '';
		}

		// return empty string as this protocol could not sign URLs.
		return '';
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
