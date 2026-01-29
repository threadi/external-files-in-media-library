<?php
/**
 * File to handle the capability-sets.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;

/**
 * The object, which handles the capability sets.
 */
class CapabilitySets {
	/**
	 * Instance of this object.
	 *
	 * @var ?CapabilitySets
	 */
	private static ?CapabilitySets $instance = null;

	/**
	 * Constructor for Schedules-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): CapabilitySets {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_action_efml_use_capability_set', array( $this, 'use_capability_set_by_request' ) );
	}

	/**
	 * Return the list of available capability sets.
	 *
	 * @return array<int,string>
	 */
	private function get_capability_sets(): array {
		$list = array(
			'\ExternalFilesInMediaLibrary\Plugin\CapabilitySets\Standard',
			'\ExternalFilesInMediaLibrary\Plugin\CapabilitySets\Minimal',
			'\ExternalFilesInMediaLibrary\Plugin\CapabilitySets\Admin_Dominion',
			'\ExternalFilesInMediaLibrary\Plugin\CapabilitySets\Complete',
		);

		/**
		 * Filter the list of possible capability sets.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $list List of capability set objects.
		 */
		return apply_filters( 'efml_capability_sets', $list );
	}

	/**
	 * Return a capability set by its name.
	 *
	 * @param string $set_name The given name.
	 *
	 * @return false|CapabilitySet_Base
	 */
	private function get_capability_set_by_name( string $set_name ): false|CapabilitySet_Base {
		// prepare the return value.
		$result = false;

		// get the capability by its name.
		foreach ( $this->get_capability_sets_as_objects() as $capability_set_obj ) {
			// bail if name does not match.
			if ( $set_name !== $capability_set_obj->get_name() ) {
				continue;
			}

			// secure this object as return value.
			$result = $capability_set_obj;
		}

		// return the resulting value.
		return $result;
	}

	/**
	 * Return the list of capability sets as objects.
	 *
	 * @return array<int,CapabilitySet_Base>
	 */
	public function get_capability_sets_as_objects(): array {
		// create the list.
		$list = array();

		// install the schedules if they do not exist atm.
		foreach ( $this->get_capability_sets() as $obj_name ) {
			// bail if given object does not exist.
			if ( ! class_exists( $obj_name ) ) {
				continue;
			}

			// get the object.
			$obj = new $obj_name();

			// bail if this is not a "CapabilitySet_Base" object.
			if ( ! $obj instanceof CapabilitySet_Base ) {
				continue;
			}

			// add to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Show list of capability sets with action buttons.
	 *
	 * @return void
	 */
	public function show_list(): void {
		// get the capability sets.
		$capability_sets = self::get_instance()->get_capability_sets_as_objects();

		// bail if no sets are known.
		if ( empty( $capability_sets ) ) {
			return;
		}

		echo '<p>';

		// show the possible sets.
		foreach ( $capability_sets as $set_obj ) {
			// create the URL.
			$url = add_query_arg(
				array(
					'action'   => 'efml_use_capability_set',
					'set_name' => $set_obj->get_name(),
					'nonce'    => wp_create_nonce( 'efml-use-capability-set' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create the dialog.
			$dialog = array(
				'title'   => __( 'Set capabilities', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Are you sure you want to set the capabilities to this set?', 'external-files-in-media-library' ) . '</strong>',
				),
				'buttons' => array(
					array(
						'action'  => 'location.href="' . $url . '";',
						'variant' => 'primary',
						'text'    => __( 'Yes', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			// show the button.
			echo '<a href="' . esc_url( $url ) . '" class="button button-primary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . esc_html( $set_obj->get_title() ) . '</a> ';
		}

		echo '</p>';
	}

	/**
	 * Run a given capability set by request.
	 *
	 * @return void
	 */
	public function use_capability_set_by_request(): void {
		// check nonce.
		check_admin_referer( 'efml-use-capability-set', 'nonce' );

		// get the name of the set.
		$set_name = filter_input( INPUT_GET, 'set_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no name is given.
		if ( empty( $set_name ) ) {
			wp_safe_redirect( (string) wp_get_referer() );
			return;
		}

		// get the given set as object.
		$set_obj = $this->get_capability_set_by_name( $set_name );

		// bail if no set could be found.
		if ( ! $set_obj instanceof CapabilitySet_Base ) {
			wp_safe_redirect( (string) wp_get_referer() );
			return;
		}

		// run the set.
		$set_obj->run();

		// show ok message.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'success' );
		$transient_obj->set_name( 'efml_capability_set' );
		$transient_obj->set_message( '<strong>' . __( 'The capabilities have been set!', 'external-files-in-media-library' ) . '</strong> ' . __( 'Please check the settings bellow.', 'external-files-in-media-library' ) );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( (string) wp_get_referer() );
	}
}
