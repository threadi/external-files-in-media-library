<?php
/**
 * Base class of the live tests with real accounts.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Live;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Abilities;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Service_Plugin_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Error;

/**
 * Base object for the live tests: helpers for the abilities, the uploads and the cleanup on the external sources.
 */
abstract class Live_Test_Base extends \WP_UnitTestCase {
	/**
	 * The ID of this run, part of the names of all files the tests create on the external sources.
	 *
	 * @var string
	 */
	protected static string $run_id = '';

	/**
	 * The attachments created by the actual test, which are deleted afterward (also on the external sources).
	 *
	 * @var array<int,int>
	 */
	private array $attachments = array();

	/**
	 * The external sources created by the actual test.
	 *
	 * @var array<int,int>
	 */
	private array $sources = array();

	/**
	 * Prepare the plugin once for all live tests: like a request of an administrator.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// prepare just one time.
		if ( did_action( 'efml_live_test_preparation_loaded' ) ) {
			return;
		}

		// the ID of this run.
		self::$run_id = 'efml-live-' . gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 4, false, false ) );

		// the functions for downloads and uploads.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// log everything, the errors are part of the messages of failed tests.
		update_option( 'eml_log_mode', 2 );

		// initialize the plugin.
		\ExternalFilesInMediaLibrary\Plugin\Install::get_instance()->activation();
		update_option( 'efmlVersion', EFML_PLUGIN_VERSION );
		Log::get_instance()->truncate_log();

		// enable the synchronization, its hooks are added during the initialization.
		update_option( 'eml_sync', 1 );

		// connect the services with OAuth.
		self::connect_dropbox();
		self::connect_google_drive();

		// administrators may use all services (the extensions add their capabilities on their activation) and the
		// tools for synchronization and export (they depend on the capability set of the website).
		$role = get_role( 'administrator' );
		if ( $role instanceof \WP_Role ) {
			foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $listing_obj ) {
				$role->add_cap( 'efml_cap_' . $listing_obj->get_name() );
			}
			foreach ( array( EFML_CAP_NAME, 'efml_cap_tools_sync', 'efml_cap_tools_export' ) as $capability ) {
				$role->add_cap( $capability );
			}
		}

		// requests via HTTPS: Dropbox is usable only with SSL (necessary for OAuth).
		$_SERVER['HTTPS'] = 'on';

		// run the initialization like a request of an administrator, so all services add their hooks.
		wp_set_current_user( 1 );
		do_action( 'init' );
		do_action( 'rest_api_init' );

		do_action( 'efml_live_test_preparation_loaded' );
	}

	/**
	 * Save the connection to Dropbox from the refresh token in ".env.live": the plugin requests a new access token.
	 *
	 * @return void
	 */
	private static function connect_dropbox(): void {
		$refresh_token = Env::get_instance()->get( 'EFML_LIVE_DROPBOX_REFRESH_TOKEN' );
		if ( '' === $refresh_token ) {
			return;
		}

		$data = array(
			'access_token'  => '',
			'refresh_token' => $refresh_token,
			'expires'       => 0,
		);
		update_option( 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( (string) wp_json_encode( $data ), DropBox::ACCESS_TOKENS_OPTION_CONTEXT ) );
	}

	/**
	 * Save the connection to Google Drive from the refresh token in ".env.live": the extension requests a new access
	 * token (via its own OAuth client, if its credentials are given, otherwise via the service of the extension).
	 *
	 * @return void
	 */
	private static function connect_google_drive(): void {
		$refresh_token = Env::get_instance()->get( 'EFML_LIVE_GOOGLE_DRIVE_REFRESH_TOKEN' );
		if ( '' === $refresh_token ) {
			return;
		}

		update_option( 'eml_google-drive_credentials_vault', Env::get_instance()->has( 'EFML_GOOGLE_DRIVE_CLIENT_ID' ) ? 'direct_input' : 'global' );
		update_option(
			'eml_google_drive_access_tokens',
			array(
				'access_token'  => 'expired',
				'refresh_token' => $refresh_token,
				'created'       => 1,
				'expires_in'    => 1,
			)
		);
	}

	/**
	 * Prepare each test: run as administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$_SERVER['HTTPS']  = 'on';
		$this->attachments = array();
		$this->sources     = array();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Cleanup after each test: delete the created files (incl. their copies on the external sources) and sources.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_set_current_user( 1 );

		// delete the files: exported files are deleted on their external source, too.
		foreach ( array_reverse( $this->attachments ) as $attachment_id ) {
			if ( get_post( $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}

		// disable the export and delete the external sources.
		foreach ( $this->sources as $term_id ) {
			Export::get_instance()->set_state_for_term( $term_id, 0 );
			Taxonomy::get_instance()->delete( $term_id );
		}
		update_option( 'eml_export', 0 );
		update_option( 'eml_export_local_files', 0 );
		update_option( 'eml_export_main_source', 0 );

		// forget the file type objects, as they are cached per attachment ID.
		$property = new \ReflectionProperty( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::class, 'files' );
		$property->setAccessible( true );
		$property->setValue( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance(), array() );

		parent::tear_down();
	}

	/**
	 * Return the configured services as data for the tests.
	 *
	 * @param string $feature Only services with this feature ("listing", "export" or "private"), empty for all.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function get_services( string $feature = '' ): array {
		$services = array();
		foreach ( Env::get_instance()->get_configured_services() as $service ) {
			if ( '' !== $feature && empty( $service[ $feature ] ) ) {
				continue;
			}
			$services[ (string) $service['label'] ] = array( (string) $service['name'] );
		}

		// without any service PHPUnit needs one entry, the test is skipped then.
		return empty( $services ) ? array( 'none' => array( '' ) ) : $services;
	}

	/**
	 * Return the definition of the given service from "services.json".
	 *
	 * @param string $name The name of the service.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_definition( string $name ): array {
		foreach ( Env::get_service_definitions() as $service ) {
			if ( $name === $service['name'] ) {
				return $service;
			}
		}
		return array();
	}

	/**
	 * Return the service object, skip the test if it is not available (e.g. its extension is not installed).
	 *
	 * @param string $name The name of the service.
	 *
	 * @return Service_Base
	 */
	protected function get_service( string $name ): Service_Base {
		if ( '' === $name ) {
			$this->markTestSkipped( 'No service with this feature is configured in .env.live.' );
		}

		// get the object like the export does it (e.g. the local directory listing is replaced by the service).
		$service_obj = apply_filters( 'efml_export_object', Services::get_instance()->get_service_by_name( $name ) );
		if ( ! $service_obj instanceof Service_Base || $service_obj instanceof Service_Plugin_Base ) {
			$this->markTestSkipped( 'The service ' . $name . ' is not available: its extension ' . ( $this->get_definition( $name )['plugin'] ?? '' ) . ' is not installed (composer test-install-live).' );
		}

		// fail with the reason, if the service is disabled (e.g. Dropbox without a valid connection).
		$listing_obj = Services::get_instance()->get_service_by_name( $name );
		if ( $listing_obj instanceof \easyDirectoryListingForWordPress\Directory_Listing_Base && $listing_obj->is_disabled() ) {
			$hints = array(
				'dropbox'      => ' The refresh token in EFML_LIVE_DROPBOX_REFRESH_TOKEN could not be used: it must belong to the app of EFML_DROPBOX_API_KEY and EFML_DROPBOX_API_SECRET.',
				'google-drive' => ' The refresh token in EFML_LIVE_GOOGLE_DRIVE_REFRESH_TOKEN could not be used.',
			);
			$this->fail( 'The service ' . $name . ' is disabled on this website.' . ( $hints[ $name ] ?? '' ) . $this->get_log_errors() );
		}

		return $service_obj;
	}

	/**
	 * Run an ability of the plugin and return its result, fail with the errors of the log on errors.
	 *
	 * @param string $name  The name of the ability without the prefix of the plugin.
	 * @param mixed  $input The input.
	 *
	 * @return array<string,mixed>
	 */
	protected function run_ability( string $name, mixed $input = null ): array {
		$result = $this->run_ability_raw( $name, $input );
		if ( $result instanceof WP_Error ) {
			$this->fail( 'The ability ' . $name . ' failed: ' . $result->get_error_message() . $this->get_log_errors() );
		}

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Run an ability of the plugin and return its result as it is.
	 *
	 * @param string $name  The name of the ability without the prefix of the plugin.
	 * @param mixed  $input The input.
	 *
	 * @return mixed
	 */
	protected function run_ability_raw( string $name, mixed $input = null ): mixed {
		$ability = wp_get_ability( Abilities::PREFIX . $name );
		$this->assertInstanceOf( \WP_Ability::class, $ability, 'Ability ' . $name . ' is missing.' );
		return $ability->execute( $input );
	}

	/**
	 * Return the newest errors of the log as text for the messages of failed tests.
	 *
	 * @return string
	 */
	protected function get_log_errors(): string {
		$errors = array();
		foreach ( Log::get_instance()->get_logs( '', 'error' ) as $entry ) {
			$errors[] = '- ' . wp_strip_all_tags( (string) ( $entry['log'] ?? '' ) ) . ( empty( $entry['url'] ) ? '' : ' (' . $entry['url'] . ')' );
			if ( count( $errors ) >= 5 ) {
				break;
			}
		}

		return empty( $errors ) ? '' : PHP_EOL . 'Newest errors in the log:' . PHP_EOL . implode( PHP_EOL, $errors );
	}

	/**
	 * Save an external source of the given service with the credentials of ".env.live" and return its ID.
	 *
	 * @param string $name The name of the service.
	 *
	 * @return int
	 */
	protected function add_source( string $name ): int {
		$input = array(
			'service' => $name,
			'fields'  => array(),
		);

		// the local directory is given as path.
		$definition = $this->get_definition( $name );
		if ( ! empty( $definition['directory'] ) ) {
			$input['directory'] = 'file://' . trailingslashit( wp_normalize_path( Env::get_instance()->get( (string) $definition['directory'] ) ) );
		}

		$result  = $this->run_ability( 'add-source', $input );
		$term_id = absint( $result['id'] ?? 0 );
		$this->assertGreaterThan( 0, $term_id, 'The external source could not be saved.' . $this->get_log_errors() );
		$this->sources[] = $term_id;

		return $term_id;
	}

	/**
	 * Return the files of an external source: of its directory and, if it contains no files, of its subdirectories
	 * (up to two levels).
	 *
	 * @param int    $term_id   The ID of the external source.
	 * @param string $directory The directory (empty for the directory of the source).
	 * @param int    $level     The level of the directory.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_files( int $term_id, string $directory = '', int $level = 0 ): array {
		$input = array( 'source_id' => $term_id );
		if ( '' !== $directory ) {
			$input['directory'] = $directory;
		}
		$listing = $this->run_ability( 'list-directory', $input );
		$files   = isset( $listing['files'] ) && is_array( $listing['files'] ) ? $listing['files'] : array();

		// use the files of the subdirectories, if this directory contains no files.
		if ( empty( $files ) && $level < 2 && ! empty( $listing['directories'] ) && is_array( $listing['directories'] ) ) {
			foreach ( $listing['directories'] as $subdirectory ) {
				$path  = is_array( $subdirectory ) ? (string) ( $subdirectory['directory'] ?? '' ) : (string) $subdirectory;
				$files = '' === $path ? array() : $this->get_files( $term_id, $path, $level + 1 );
				if ( ! empty( $files ) ) {
					break;
				}
			}
		}

		return $files;
	}

	/**
	 * Remember an attachment, which is deleted after the test.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	protected function remember_attachment( int $attachment_id ): void {
		$this->attachments[] = $attachment_id;
	}

	/**
	 * Upload a new image (800 x 600 pixel, so WordPress creates sizes) with the ID of this run in its name.
	 *
	 * @param string $name Part of the file name, e.g. the name of the service.
	 *
	 * @return array{id: int, md5: string}
	 */
	protected function upload_image( string $name ): array {
		// create the image.
		$file  = trailingslashit( get_temp_dir() ) . self::$run_id . '-' . sanitize_file_name( $name ) . '.jpg';
		$image = imagecreatetruecolor( 800, 600 );
		imagefill( $image, 0, 0, (int) imagecolorallocate( $image, wp_rand( 0, 255 ), wp_rand( 0, 255 ), wp_rand( 0, 255 ) ) );
		imagejpeg( $image, $file, 85 );
		$md5 = (string) md5_file( $file );

		// upload it.
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		$this->assertIsInt( $attachment_id );
		$this->remember_attachment( $attachment_id );

		return array(
			'id'  => $attachment_id,
			'md5' => $md5,
		);
	}

	/**
	 * Load the given external file like the proxy does it for a visitor and return its content, false on errors.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string|false
	 */
	protected function load_as_visitor( int $attachment_id ): string|false {
		$user_id = get_current_user_id();
		wp_set_current_user( 0 );
		try {
			$external_file_obj = Files::get_instance()->get_file( $attachment_id );
			$protocol_obj      = $external_file_obj->get_protocol_handler_obj();
			$tmp_file          = $protocol_obj instanceof Protocol_Base ? $protocol_obj->get_temp_file( $external_file_obj->get_url( true ), Helper::get_wp_filesystem() ) : false;
		} finally {
			wp_set_current_user( $user_id );
		}

		return $this->read_and_delete( $tmp_file );
	}

	/**
	 * Load the given URL of an external source with its credentials, false on errors (e.g. if it does not exist).
	 *
	 * @param string $url     The URL.
	 * @param int    $term_id The ID of the external source.
	 *
	 * @return string|false
	 */
	protected function load_url( string $url, int $term_id ): string|false {
		$entry        = Taxonomy::get_instance()->get_entry( $term_id );
		$fields       = isset( $entry['fields'] ) && is_array( $entry['fields'] ) ? $entry['fields'] : array();
		$protocol_obj = Protocols::get_instance()->get_protocol_object_for_url( $url, $fields );
		if ( ! $protocol_obj instanceof Protocol_Base ) {
			return false;
		}
		$protocol_obj->set_fields( $fields );

		try {
			$tmp_file = @$protocol_obj->get_temp_file( $url, Helper::get_wp_filesystem() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) {
			$tmp_file = false;
		}

		return $this->read_and_delete( $tmp_file );
	}

	/**
	 * Return the content of the given temporary file and delete it.
	 *
	 * @param mixed $tmp_file The path of the temporary file.
	 *
	 * @return string|false
	 */
	private function read_and_delete( mixed $tmp_file ): string|false {
		if ( ! is_string( $tmp_file ) || '' === $tmp_file || ! file_exists( $tmp_file ) ) {
			return false;
		}
		$content = (string) file_get_contents( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_delete_file( $tmp_file );
		return $content;
	}

	/**
	 * Return a user with the given role without the capability for the given service, but with the capability to
	 * add external files.
	 *
	 * @param string $role    The role.
	 * @param string $service The name of the service.
	 *
	 * @return int
	 */
	protected function create_user_without_service( string $role, string $service ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( EFML_CAP_NAME );
		$user->add_cap( 'upload_files' );
		$user->add_cap( 'efml_cap_' . $service, false );
		return $user_id;
	}
}
