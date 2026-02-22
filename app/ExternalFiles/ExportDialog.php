<?php
/**
 * File to handle the dialog, which configures an export for a single external source.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the dialog, which configures an export for a single external source
 */
class ExportDialog {
	/**
	 * Instance of actual object.
	 *
	 * @var ?ExportDialog
	 */
	private static ?ExportDialog $instance = null;

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
	 * @return ExportDialog
	 */
	public static function get_instance(): ExportDialog {
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
		add_action( 'wp_ajax_efml_get_export_config_dialog', array( $this, 'get_export_config_dialog' ) );
		add_action( 'wp_ajax_efml_save_export_config', array( $this, 'save_export_config_via_ajax' ) );
	}

	/**
	 * Return list of enabled extensions from settings.
	 *
	 * @return array<int,string>
	 */
	public function get_enabled_extensions(): array {
		// get the value of the setting.
		$setting = get_option( 'eml_export_extensions', array() );

		// if it is not an array, return an empty one.
		if ( ! is_array( $setting ) ) {
			return array();
		}

		// return the setting.
		return $setting;
	}

	/**
	 * Return list of names of the default extensions for the export dialog.
	 *
	 * @return array<int,string>
	 */
	public function get_default_extensions(): array {
		$list = array(
			'export_by_file_type',
			'export_by_size',
		);

		/**
		 * Filter the list of default extensions.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $list List of names of the default extensions.
		 */
		return apply_filters( 'efml_export_dialog_extensions_default', $list );
	}

	/**
	 * Return the export configuration dialog for a given source term.
	 *
	 * @return void
	 */
	public function get_export_config_dialog(): void {
		// check nonce.
		check_ajax_referer( 'efml-export-config-nonce', 'nonce' );

		// create the dialog for failures.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Configuration could not be loaded', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The export configuration for this target could not be loaded.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get term ID from request.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no term ID is given.
		if ( 0 === $term_id ) {
			$dialog['texts'][] = '<p>' . __( 'No target specified.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the term.
		$term = get_term_by( 'term_id', $term_id, Taxonomy::get_instance()->get_name() );

		// bail if no term could be found.
		if ( ! $term instanceof WP_Term ) {
			$dialog['texts'][] = '<p>' . __( 'No target specified.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the listings object.
		$listing_obj = Export::get_instance()->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base ) {
			$dialog['texts'][] = '<p><strong>' . __( 'Export to this external source is not supported.', 'external-files-in-media-library' ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// bail if no object could be found.
		if ( ! $listing_obj->get_export_object() instanceof Export_Base ) {
			/* translators: %1$s will be replaced by a title. */
			$dialog['texts'][] = '<p><strong>' . sprintf( __( 'Export to %1$s is not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get actual state.
		$enabled = absint( get_term_meta( $term_id, 'efml_export', true ) );

		// get URL.
		$url = get_term_meta( $term_id, 'efml_export_url', true );

		// Create the form:
		// -> first the state.
		$form = '<div><label for="enable">' . __( 'Enable:', 'external-files-in-media-library' ) . '</label><input type="checkbox" name="enable" id="enable" value="1"' . ( $enabled > 0 ? ' checked="checked"' : '' ) . '></div>';

		// -> marker for main export source, if user has the capability for it.
		if ( current_user_can( 'efml_cap_tools_export' ) ) {
			/* translators: %1$s will be replaced by a URL. */
			$description = sprintf( __( 'Manage this setting <a href="%1$s">here</a>.', 'external-files-in-media-library' ), Settings::get_instance()->get_url( 'eml_export' ) );
			$form       .= '<div><label for="main_export">' . __( 'Main export:', 'external-files-in-media-library' ) . '</label><input type="checkbox" name="main_export" id="main_export" value="1"' . ( absint( get_option( 'eml_export_main_source' ) ) === $term_id ? ' checked="checked"' : '' ) . '> ' . $description . '</div>';
		}

		// -> then the URL, if required.
		if ( $listing_obj->get_export_object()->is_url_required() ) {
			$form .= '<div><label for="url">' . __( 'URL:', 'external-files-in-media-library' ) . '</label><input type="url" placeholder="https://example.com" name="url" id="url" value="' . esc_url( $url ) . '">' . __( 'This must be the URL where files uploaded to this external source are available.', 'external-files-in-media-library' ) . '</div>';
		}

		// -> the term ID.
		$form .= '<input type="hidden" name="term_id" value="' . $term_id . '">';

		// create the dialog for sync config.
		$dialog = array(
			'className' => 'efml efml-export-config',
			/* translators: %1$s will be replaced by a name. */
			'title'     => sprintf( __( 'Export settings for %1$s', 'external-files-in-media-library' ), $term->name ),
			'texts'     => array(
				'<p><strong>' . __( 'Configure this external source as the destination for files newly added to the media library.', 'external-files-in-media-library' ) . '</strong> ' . __( 'This allows you to use this external directory to store all your files.', 'external-files-in-media-library' ) . '</p>',
				$form,
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_export_save_config(' . $term_id . ');',
					'variant' => 'primary',
					'text'    => __( 'Save', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		/**
		 * Filter the dialog to configure an export.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $dialog The dialog.
		 * @param int $term_id The term ID.
		 */
		$dialog = apply_filters( 'efml_export_config_dialog', $dialog, $term_id );

		// send the dialog.
		wp_send_json( array( 'detail' => $dialog ) );
	}

	/**
	 * Save the export settings.
	 *
	 * @return void
	 */
	public function save_export_config_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-export-save-config-nonce', 'nonce' );

		// create the dialog for any failures.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Configuration could not be saved', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The export configuration for this target could not be saved.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get term ID from request.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no term ID is given.
		if ( 0 === $term_id ) {
			$dialog['texts'][] = '<p>' . __( 'No target specified.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the listings object.
		$listing_obj = Export::get_instance()->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base ) {
			$dialog['texts'][] = '<p><strong>' . __( 'Export to this external source is not supported.', 'external-files-in-media-library' ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// bail if no object could be found.
		if ( ! $listing_obj->get_export_object() instanceof Export_Base ) {
			/* translators: %1$s will be replaced by a title. */
			$dialog['texts'][] = '<p><strong>' . sprintf( __( 'Export to %1$s is not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the URL, if required.
		if ( $listing_obj->get_export_object()->is_url_required() ) {
			$url = filter_input( INPUT_POST, 'url', FILTER_SANITIZE_URL );

			// bail if URL is not given.
			if ( empty( $url ) ) {
				$dialog['texts'][] = '<p>' . __( 'No URL specified.', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// bail if given URL is not valid.
			if ( ! wp_http_validate_url( $url ) ) {
				$dialog['texts'][] = '<p>' . __( 'Given URL cannot be used.', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// send a test request to this URL.
			$response = wp_safe_remote_head( $url );

			// bail on any error.
			if ( is_wp_error( $response ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Given URL could not be reached! Error:', 'external-files-in-media-library' ) . '<em>' . wp_json_encode( $response ) . '</em>', $url, 'error' );

				// configure the dialog and return it.
				$dialog['texts'][] = '<p>' . __( 'Given URL could not be reached!', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// get the HTTP status for this URL.
			$response_code = wp_remote_retrieve_response_code( $response );

			// check the HTTP status, should be 200, 403 or 404.
			$result = in_array( $response_code, array( 200, 403, 404 ), true );

			/**
			 * Filter the possible HTTP states for export URLs during export configuration.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param bool   $result The check result.
			 * @param string $url    The given URL.
			 */
			if ( ! apply_filters( 'efml_export_configuration_url_state', $result, $url ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Given URL is returning a not allowed HTTP state:', 'external-files-in-media-library' ) . ' <code>' . $response_code . '</code>', $url, 'info' );

				// configure the dialog and return it.
				$dialog['texts'][] = '<p>' . __( 'Given URL is returning a not allowed HTTP state!', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// save URL.
			update_term_meta( $term_id, 'efml_export_url', $url );
		}

		// get the state.
		$enabled = absint( filter_input( INPUT_POST, 'enable', FILTER_SANITIZE_NUMBER_INT ) );

		// enable the export state for this term.
		Export::get_instance()->set_state_for_term( $term_id, $enabled );

		// get the "main_export" marker.
		$main_export = absint( filter_input( INPUT_POST, 'main_export', FILTER_SANITIZE_NUMBER_INT ) );

		// if this export is enabled, and the main export is set, set this export as main export.
		if ( 1 === $enabled && 1 === $main_export ) {
			update_option( 'eml_export_main_source', $term_id );
		}

		/**
		 * Run additional tasks after saving an export dialog.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param int $term_id The used term ID.
		 */
		do_action( 'efml_export_save_config', $term_id );

		// create the dialog.
		$dialog = array(
			'className' => 'efml efml-export-config',
			'title'     => __( 'Configuration saved', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The configuration has been saved successfully.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.reload();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// show hint if enabled.
		if ( $enabled ) {
			$dialog['texts'][] = '<p>' . __( 'As soon as you upload new files to your media library, they will be stored in this external storage.', 'external-files-in-media-library' ) . '</p>';
		}

		/**
		 * Filter the dialog after saving an updated export configuration.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $dialog The dialog.
		 * @param int $term_id The term ID.
		 */
		$dialog = apply_filters( 'efml_export_save_config_dialog', $dialog, $term_id );

		// send the dialog.
		wp_send_json( array( 'detail' => $dialog ) );
	}
}
