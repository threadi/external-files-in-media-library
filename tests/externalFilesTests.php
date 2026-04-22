<?php
/**
 * File to handle the main object for each test class.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests;

use WP_Error;
use WP_HTTP_Requests_Response;
use WP_Query;
use WP_UnitTestCase;

/**
 * Object to handle the preparations for each test class.
 */
abstract class externalFilesTests extends WP_UnitTestCase {
	/**
	 * List of test files we use in our tests.
	 *
	 * @var array
	 */
	private static array $test_files = array(
		'jpg' => array(
			'http' => 'https://example.com/example.jpg',
			'imgur' => 'https://i.imgur.com/example.jpg',
			'file' => '',
		),
		'pdf' => array(
			'http' => 'https://example.com/example.pdf',
			'file' => 'tests/Data/example.pdf'
		),
		'zip' => array(
			'http' => 'https://example.com/example.zip',
			'file' => 'tests/Data/example.zip'
		)
	);

	/**
	 * List of faulty test files we use in our tests.
	 *
	 * @var array
	 */
	private static array $test_faulty_files = array(
		'pdf' => array(
			'http' => 'example.com/faulty_example.pdf',
			'file' => 'tests/Data/faulty_example_en.pdf'
		),
		'zip' => array(
			'http' => 'example.com/faulty_example.zip',
			'file' => 'tests/Data/faulty_example.zip'
		)
	);

	/**
	 * Prepare the test environment for each test class.
	 *
	 * @return void
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// prepare to load just one time.
		if ( ! did_action('efml_test_preparation_loaded') ) {
			// enable error reporting.
			error_reporting( E_ALL );

			// enable the extended log mode.
			update_option( 'eml_log_mode', 2 );

			// initialize the plugin.
			\ExternalFilesInMediaLibrary\Plugin\Install::get_instance()->activation();

			// run initialization.
			do_action( 'init' );
			do_action( 'rest_api_init' );

			// prevent external requests from external sources.
			add_filter( 'pre_http_request', array( self::class, 'filter_http_requests' ), 10, 3 );
			add_filter( 'wp_mail', array( self::class, 'filter_wp_mail' ) );

			// mark as loaded.
			do_action('efml_test_preparation_loaded');
		}
	}

	/**
	 * Filter any HTTP request during the tests to load the local test files and prevent any external communications.
	 *
	 * @param false|array|WP_Error $false The return value.
	 * @param array $parsed_args The used parameters.
	 * @param string $url The requested URL.
	 *
	 * @return false|array|WP_Error
	 */
	public static function filter_http_requests( false|array|WP_Error $false, array $parsed_args, string $url ): false|array|WP_Error {
		// get file infos.
		$file_info = pathinfo( $url );

		// create a local response for the HEAD request on our test URL.
		if( 'HEAD' === $parsed_args['method'] && $url === self::get_test_file( $file_info['extension'], 'http' ) ) {
			// get the file size of our test file.
			$file_size = \ExternalFilesInMediaLibrary\Plugin\Helper::get_wp_filesystem()->size( self::get_test_file( $file_info['extension'], 'file' ) );

			// get file type infos.
			$file_type = wp_check_filetype( basename( $url ) );

			// create the response object.
			$requests_response = new \WpOrg\Requests\Response;
			$requests_response->status_code = 200;
			$requests_response->url = $url;
			$requests_response->headers = new \WpOrg\Requests\Response\Headers(
				array(
					'Content-Type' => $file_type['type'],
					'Content-Length' => $file_size,
					'Content-Disposition' => 'attachment; filename="' . $file_info['basename'] . '"',
				)
			);
			$requests_response->success = true;

			// create the header response.
			return array(
				'response' => array( 'code' => 200 ),
				'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] )
			);
		}

		// create a local response for the HEAD request on a faulty URL.
		if( 'HEAD' === $parsed_args['method'] && $url === self::get_faulty_test_file( $file_info['extension'], 'http' ) ) {
			// create the response object.
			$requests_response = new \WpOrg\Requests\Response;
			$requests_response->status_code = 404;
			$requests_response->url = $url;

			// create the header response.
			return array(
				'response' => array( 'code' => 404 ),
				'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] )
			);
		}

		// create a local response for the GET request.
		if( 'GET' === $parsed_args['method'] && ! empty( $parsed_args['filename'] ) && ! empty( $file_info['extension'] ) && $url === self::get_test_file( $file_info['extension'], 'http' ) ) {
			// get the "WP_Filesystem" handler.
			$wp_filesystem = \ExternalFilesInMediaLibrary\Plugin\Helper::get_wp_filesystem();

			// get the local test file.
			$content = $wp_filesystem->get_contents( self::get_test_file( $file_info['extension'], 'file' ) );

			// save in on given 'filename'
			$wp_filesystem->put_contents( $parsed_args['filename'], $content );

			// get the file size of our test file.
			$file_size = $wp_filesystem->size( $parsed_args['filename'] );

			// get file type infos.
			$file_type = wp_check_filetype( basename( $url ) );

			// create the response object.
			$requests_response = new \WpOrg\Requests\Response;
			$requests_response->status_code = 200;

			// create the header response.
			return array(
				'response' => array( 'code' => 200 ),
				'headers' => array(
					'content-type' => $file_type['type'],
					'Content-Length' => $file_size,
					'Content-Disposition' => 'attachment; filename="' . $file_info['basename'] . '"',
				),
				'http_response' => new WP_HTTP_Requests_Response( $requests_response, $parsed_args['filename'] ),
				'filename' => $parsed_args['filename']
			);
		}

		// show error as we do not want any external URL connection during PHP Unit Tests.
		self::fail( __( 'Trying an external URL connection, which should not happen during PHP Unit Tests. Used URL:', 'external-files-in-media-library' ) . ' ' . $url );
	}

	/**
	 * Filter any email communication from WordPress.
	 *
	 * @param array $args The used arguments.
	 *
	 * @return array
	 */
	public static function filter_wp_mail( array $args ): array {
		$args['to'] = 'info@example.com';
		return $args;
	}

	/**
	 * Return the requested test file.
	 *
	 * @param string $type     The type to test for.
	 * @param string $protocol The used protocol.
	 *
	 * @return string
	 */
	protected static function get_test_file( string $type, string $protocol ): string {
		$file = self::$test_files[ $type ][ $protocol ];

		// add the path before the file if protocol 'file' is requested.
		if( 'file' === $protocol ) {
			$file = 'file://' . \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . $file;
		}

		// return the resulting type.
		return $file;
	}

	/**
	 * Return the requested faulty test file.
	 *
	 * @param string $type The type to test for.
	 * @param string $protocol The used protocol.
	 *
	 * @return string
	 */
	protected static function get_faulty_test_file( string $type, string $protocol ): string {
		return self::$test_faulty_files[ $type ][ $protocol ];
	}

	/**
	 * Check for an array of specific object types.
	 *
	 * @param string $type The used type.
	 * @param array $array The array to check.
	 * @param string $message The message to check for.
	 *
	 * @return void
	 */
	public function assertArrayHasObjectOfType( string $type, array $array, string $message = '' ): void {
		$found = false;
		foreach( $array as $obj ) {
			if( get_class( $obj ) === $type ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, $message );
	}

	/**
	 * Return amount of files in media library.
	 *
	 * @return int
	 */
	protected function get_media_file_count(): int {
		$query = array(
			'posts_per_page' => -1,
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'fields'         => 'ids',
		);
		return ( new WP_Query( $query ) )->found_posts;
	}

	/**
	 * Login in this session as admin.
	 *
	 * @return void
	 */
	protected function do_login(): void {
		// login as admin.
		$user_id = 1;
		$user_login = 'admin';
		wp_set_current_user( $user_id, $user_login );
		wp_set_auth_cookie( $user_id );
		do_action('wp_login', $user_login );
	}

	/**
	 * Logout from this session.
	 *
	 * @return void
	 */
	protected function do_logout(): void {
		// logout.
		wp_set_current_user( 0 );
		wp_set_auth_cookie( 0 );
		do_action('wp_login' );
	}
}
