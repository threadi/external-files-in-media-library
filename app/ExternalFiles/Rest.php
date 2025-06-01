<?php
/**
 * This file contains a controller-object to handle REST API support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Server;

/**
 * Controller for REST API support.
 */
class Rest {
	/**
	 * Instance of actual object.
	 *
	 * @var Rest|null
	 */
	private static ?Rest $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Rest
	 */
	public static function get_instance(): Rest {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register REST endpoints.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			'efml/v1',
			'/file/',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_file' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_rest_route(
			'efml/v1',
			'/file/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_file' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_rest_route(
			'efml/v1',
			'/file/',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_file' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Add external file via REST API request.
	 *
	 * Hint: "url" must be submitted as body parameter.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array<string,mixed>
	 */
	public function add_file( WP_REST_Request $request ): array {
		// get the params from request.
		$params = $request->get_params();

		// bail if params does not contain "url".
		if ( empty( $params['url'] ) ) {
			return array(
				'result' => 'error',
			);
		}

		// get the import object.
		$import_obj = Import::get_instance();

		// add login, if set.
		if ( isset( $params['login'] ) ) {
			$import_obj->set_login( $params['login'] );
		}
		// add password, if set.
		if ( isset( $params['password'] ) ) {
			$import_obj->set_password( $params['password'] );
		}

		// add the given URL and return success if it was successfully.
		if ( $import_obj->add_url( $params['url'] ) ) {
			return array(
				'result' => 'success',
			);
		}

		// return error.
		return array(
			'result' => 'error',
		);
	}

	/**
	 * Delete an external file via REST API.
	 *
	 * Hint: "url" must be submitted as GET-param.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array<string,mixed>
	 */
	public function delete_file( WP_REST_Request $request ): array {
		// get the params from request.
		$params = $request->get_params();

		// bail if params does not contain "url".
		if ( empty( $params['url'] ) ) {
			return array(
				'result' => 'error',
			);
		}

		// get the external file object of the given URL.
		$external_file_obj = Files::get_instance()->get_file_by_url( $params['url'] );

		// bail if no external file could be found for the given URL.
		if ( ! $external_file_obj ) {
			return array(
				'result' => 'error',
			);
		}

		// delete it.
		$external_file_obj->delete();

		// return success.
		return array(
			'result' => 'success',
		);
	}

	/**
	 * Return info if an external file is known via REST API.
	 *
	 * Hint: "url" must be submitted as GET-param.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return array<string,mixed>
	 */
	public function get_file( WP_REST_Request $request ): array {
		// get the params from request.
		$params = $request->get_params();

		// bail if params does not contain "url".
		if ( empty( $params['url'] ) ) {
			return array(
				'result' => 'error',
			);
		}

		// get the external file object of the given URL.
		$external_file_obj = Files::get_instance()->get_file_by_url( $params['url'] );

		// bail if no external file could be found for the given URL.
		if ( ! $external_file_obj ) {
			return array(
				'result' => 'error',
			);
		}

		// return success.
		return array(
			'result' => 'success',
		);
	}
}
