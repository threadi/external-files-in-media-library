<?php
/**
 * File to handle the complete connection test of services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Object to handle the complete connection test of services, which support it (e.g. to export files).
 *
 * @since 6.0.0 Available since 6.0.0 (before only in the extension for AWS S3).
 */
class Connection_Test {
	/**
	 * The name of the AJAX action and the nonce.
	 *
	 * @var string
	 */
	private const ACTION = 'efml_connection_test';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Connection_Test
	 */
	private static ?Connection_Test $instance = null;

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
	 * @return Connection_Test
	 */
	public static function get_instance(): Connection_Test {
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
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'run_via_ajax' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_script' ) );
	}

	/**
	 * Add the script for the connection test in the backend.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_script( string $hook ): void {
		// bail if page is used where we do not use it.
		if ( ! in_array( $hook, array( 'media_page_efml_local_directories', 'upload.php', 'settings_page_eml_settings', 'options-general.php', 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		// add the script.
		wp_enqueue_script(
			'efml-connection-test',
			Helper::get_plugin_url() . 'admin/connection-test.js',
			array( 'jquery' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/connection-test.js' ),
			true
		);

		// add the styles for the results.
		wp_enqueue_style(
			'efml-connection-test',
			Helper::get_plugin_url() . 'admin/connection-test.css',
			array( 'dashicons' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/connection-test.css' )
		);

		// add the variables for the script.
		wp_localize_script(
			'efml-connection-test',
			'efmlConnectionTestVars',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::ACTION ),
				'title'    => __( 'Connection test is running', 'external-files-in-media-library' ),
				'text'     => __( 'Please wait a moment.', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Return the service with the given name, if it supports the connection test.
	 *
	 * @param string $name The name of the service.
	 *
	 * @return Service_Base|false
	 */
	public function get_service_by_name( string $name ): Service_Base|false {
		// check each service.
		foreach ( Services::get_instance()->get_services_as_objects() as $service_obj ) {
			// bail if this is not the searched one.
			if ( ! $service_obj instanceof Service_Base || $service_obj->get_name() !== $name ) {
				continue;
			}

			// get the actual instance of the service.
			$callback = array( get_class( $service_obj ), 'get_instance' );
			if ( is_callable( $callback ) ) {
				$service_obj = call_user_func( $callback );
			}

			// bail if the service does not support the connection test.
			if ( ! $service_obj instanceof Service_Base || ! $service_obj->supports_connection_test() ) {
				return false;
			}

			// return the service.
			return $service_obj;
		}

		// return false as the service could not be found.
		return false;
	}

	/**
	 * Run the connection test via AJAX request and return a dialog with the results.
	 *
	 * @return void
	 */
	public function run_via_ajax(): void {
		// check nonce.
		check_ajax_referer( self::ACTION, 'nonce' );

		// create initial response dialog.
		$dialog = array(
			'detail' => array(
				'className' => 'efml',
				'title'     => __( 'Connection test', 'external-files-in-media-library' ),
				'texts'     => array(),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			),
		);

		// get the service.
		$type    = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$service = $this->get_service_by_name( $type );

		// bail if service is unknown or the user is not allowed to use it.
		if ( ! $service instanceof Service_Base || ! current_user_can( 'efml_cap_' . $service->get_name() ) ) {
			$dialog['detail']['texts'][] = '<p>' . __( 'The connection test could not be run.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( $dialog );
		}

		// get the fields from the request.
		$fields = isset( $_POST['fields'] ) ? map_deep( wp_unslash( $_POST['fields'] ), 'sanitize_text_field' ) : array();

		// get the actual directory from the request.
		$directory = isset( $_POST['directory'] ) ? sanitize_text_field( wp_unslash( $_POST['directory'] ) ) : '';

		// get the fields from the used external source.
		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( $term_id > 0 ) {
			// bail if the actual user is not allowed to use this external source.
			if ( ! Directory_Listing::get_instance()->is_source_allowed_for_current_user( $term_id ) ) {
				$dialog['detail']['texts'][] = '<p><strong>' . __( 'Access not allowed!', 'external-files-in-media-library' ) . '</strong></p>';
				wp_send_json( $dialog );
			}

			// get the fields of the external source.
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );
			if ( ! empty( $term_data['fields'] ) ) {
				$fields = $term_data['fields'];
			}

			// use the directory of the external source.
			if ( ! empty( $term_data['directory'] ) && is_string( $term_data['directory'] ) ) {
				$directory = $term_data['directory'];
			}
		}

		// set the fields (if none are given, the fields from the settings or the profile are used).
		$service->set_fields( is_array( $fields ) ? $fields : array() );

		// set the directory, in which the test file should be saved.
		$service->set_connection_test_directory( $directory );

		// run the test.
		$steps = $service->run_connection_test();

		// show the results.
		$result = $this->get_steps_list( $steps );

		// set the title and the texts.
		$dialog['detail']['title']   = $result['success'] ? __( 'Connection test successful', 'external-files-in-media-library' ) : __( 'Connection test failed', 'external-files-in-media-library' );
		$dialog['detail']['texts'][] = $result['html'];
		/* translators: %1$s will be replaced by a URL. */
		$dialog['detail']['texts'][] = '<p>' . sprintf( __( 'All steps are logged with their details in the <a href="%1$s">log</a>. Use them if you contact the support or the provider of your storage.', 'external-files-in-media-library' ), esc_url( Settings::get_instance()->get_url( 'eml_logs' ) ) ) . '</p>';

		// return the dialog.
		wp_send_json( $dialog );
	}

	/**
	 * Return the steps of a connection test as HTML list and whether all steps have been successful.
	 *
	 * @param array<int,array<string,mixed>> $steps The steps with "title", "success", "skipped" and "message".
	 *
	 * @return array{html: string, success: bool}
	 */
	public function get_steps_list( array $steps ): array {
		$all_successful = true;
		$list           = '';
		foreach ( $steps as $step ) {
			// get the state with its icon and its text for screen readers.
			if ( $step['skipped'] ) {
				$state = 'skipped';
				$icon  = 'dashicons-minus';
				$label = __( 'Skipped', 'external-files-in-media-library' );
			} elseif ( $step['success'] ) {
				$state = 'success';
				$icon  = 'dashicons-yes-alt';
				$label = __( 'Successful', 'external-files-in-media-library' );
			} else {
				$state          = 'failed';
				$icon           = 'dashicons-dismiss';
				$label          = __( 'Failed', 'external-files-in-media-library' );
				$all_successful = false;
			}

			// add the entry: the icon as bullet, the title and the explanation beside it.
			$list .= '<li class="efml-connection-test-' . esc_attr( $state ) . '">'
				. '<span class="dashicons ' . esc_attr( $icon ) . '" title="' . esc_attr( $label ) . '" aria-hidden="true"></span>'
				. '<div><strong>' . esc_html( $step['title'] ) . '</strong><span class="screen-reader-text">' . esc_html( $label ) . '</span>'
				. ( '' !== $step['message'] ? '<p>' . esc_html( $step['message'] ) . '</p>' : '' )
				. '</div></li>';
		}

		// return the list and the result.
		return array(
			'html'    => '<ul class="efml-connection-test-steps">' . $list . '</ul>',
			'success' => $all_successful,
		);
	}
}
