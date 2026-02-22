<?php
/**
 * File to handle the dialog, which configures the synchronization for a single external source.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use WP_Term;

/**
 * Object to handle the dialog, which configures the synchronization for a single external source
 */
class SynchronizationDialog {
	/**
	 * Instance of actual object.
	 *
	 * @var ?SynchronizationDialog
	 */
	private static ?SynchronizationDialog $instance = null;

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
	 * @return SynchronizationDialog
	 */
	public static function get_instance(): SynchronizationDialog {
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
		add_action( 'wp_ajax_efml_get_sync_config_dialog', array( $this, 'get_dialog' ) );
		add_action( 'wp_ajax_efml_sync_save_config', array( $this, 'save_via_ajax' ) );
	}

	/**
	 * Return list of enabled extensions from settings.
	 *
	 * @return array<int,string>
	 */
	public function get_enabled_extensions(): array {
		// get the value of the setting.
		$setting = get_option( 'eml_sync_extensions', array() );

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
		$list = array();

		/**
		 * Filter the list of default extensions.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $list List of names of the default extensions.
		 */
		return apply_filters( 'efml_sync_dialog_extensions_default', $list );
	}

	/**
	 * Return the sync configuration dialog.
	 *
	 * @return void
	 */
	public function get_dialog(): void {
		// check nonce.
		check_ajax_referer( 'efml-sync-config-nonce', 'nonce' );

		// create the dialog for failures.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Configuration could not be loaded', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The synchronization configuration for this target could not be loaded.', 'external-files-in-media-library' ) . '</strong></p>',
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

		// bail if the listing object does not support synchronizations.
		if ( ! method_exists( $listing_obj, 'is_sync_disabled' ) || $listing_obj->is_sync_disabled() ) { // @phpstan-ignore function.alreadyNarrowedType
			/* translators: %1$s will be replaced by a title. */
			$dialog['texts'][] = '<p><strong>' . sprintf( __( 'Synchronization to %1$s is not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the sync schedule object for this term_id.
		$sync_schedule_obj = Synchronization::get_instance()->get_schedule_by_term_id( $term_id );

		// get actual interval.
		$term_interval = $sync_schedule_obj ? $sync_schedule_obj->get_interval() : get_term_meta( $term_id, 'interval', true );
		if ( empty( $term_interval ) ) {
			$term_interval = get_option( 'eml_sync_interval' );
		}

		// create the interval field.
		$form = '<div><label for="interval">' . __( 'Choose an interval:', 'external-files-in-media-library' ) . '</label><select id="interval">';
		foreach ( Helper::get_intervals() as $name => $label ) {
			// bail if this is the disabled entry.
			if ( 'eml_disable_check' === $name ) {
				continue;
			}

			// add the entry.
			$form .= '<option value="' . $name . '"' . ( $term_interval === $name ? ' selected' : '' ) . '>' . $label . '</option>';
		}
		$form .= '</select><input type="hidden" id="term_id" value="' . absint( $term_id ) . '"></div>';

		/**
		 * Filter the form to configure this external directory.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $form The form HTML-code.
		 * @param int $term_id The term ID.
		 */
		$form = apply_filters( 'efml_sync_configure_form', $form, $term_id );

		// add privacy hint at the end of the form, if it is not disabled.
		if ( 1 !== absint( get_user_meta( get_current_user_id(), 'efml_no_privacy_hint', true ) ) ) {
			$form .= '<div><label for="privacy"><input type="checkbox" id="privacy" name="privacy" value="1" required> <strong>' . __( 'I confirm that I will respect the copyrights of these external files.', 'external-files-in-media-library' ) . '</strong></label></div>';
		}

		// create the dialog for sync config.
		$dialog = array(
			'className' => 'efml efml-sync-config',
			/* translators: %1$s will be replaced by a name. */
			'title'     => sprintf( __( 'Settings for this %1$s connection', 'external-files-in-media-library' ), $listing_obj->get_label() ),
			'texts'     => array(
				'<p><strong>' . __( 'Configure an interval, which will be used to automatically synchronize this external source with your media library.', 'external-files-in-media-library' ) . '</strong></p>',
				$form,
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_sync_save_config("' . $listing_obj->get_name() . '", ' . $term_id . ');',
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
		$dialog = apply_filters( 'efml_sync_config_dialog', $dialog, $term_id );

		// send the dialog.
		wp_send_json( array( 'detail' => $dialog ) );
	}

	/**
	 * Save new configuration for single synchronization schedule.
	 *
	 * @return void
	 */
	public function save_via_ajax(): void {
		// check referer.
		check_ajax_referer( 'efml-sync-save-config-nonce', 'nonce' );

		// create the dialog for failures.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Configuration not saved', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p>' . __( 'The configuration for this synchronization could not be saved.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get the fields.
		$fields = isset( $_POST['fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) ) : array();

		// bail if interval is not given.
		if ( empty( $fields['interval'] ) ) {
			$dialog['texts'][] = '<p>' . __( 'Interval has not been selected.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// bail if term ID is not given.
		if ( 0 === absint( $fields['term_id'] ) ) {
			$dialog['texts'][] = '<p>' . __( 'No external source chosen.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		$false = false;
		/**
		 * Run additional tasks to validate given values during saving a new sync configuration.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param bool $false True to prevent saving.
		 * @param array $fields List of fields.
		 * @param array $dialog The response dialog.
		 */
		if ( apply_filters( 'efml_sync_validate_config', $false, $fields, $dialog ) ) {
			return;
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// get the interval.
		$interval = $fields['interval'];

		// check if the given interval exists.
		$intervals = wp_get_schedules();
		if ( empty( $intervals[ $interval ] ) ) {
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// save this interval on the term as setting.
		update_term_meta( $term_id, 'interval', $interval );

		/**
		 * Run additional tasks during saving a new sync configuration.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $fields List of fields.
		 * @param int $term_id The term ID.
		 */
		do_action( 'efml_sync_save_config', $fields, $term_id );

		// create the dialog.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Configuration saved', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The new configuration for this synchronization has been saved.', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'The configurations you have made will now be taken into effect.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.reload();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get the sync schedule object for this term_id.
		$sync_schedule_obj = Synchronization::get_instance()->get_schedule_by_term_id( $term_id );

		// bail if no schedule found, but also send OK back.
		if ( ! $sync_schedule_obj instanceof \ExternalFilesInMediaLibrary\Plugin\Schedules\Synchronization ) {
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// set the new interval.
		$sync_schedule_obj->set_interval( $interval );

		// re-install schedule.
		$sync_schedule_obj->reset();

		// send ok.
		wp_send_json( array( 'detail' => $dialog ) );
	}
}
