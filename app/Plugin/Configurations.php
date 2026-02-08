<?php
/**
 * This file controls the possible configurations for this plugin.
 *
 * A configuration defines, which options, services and tools are available. Along the way, you can limit the options
 * offered by the plugin to your own needs and requirements for the project without having to make all the
 * settings manually yourself.
 *
 * Executing a configuration save what is configured in them. The changed settings can be adjusted any time
 * after the execution.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;

/**
 * Handler controls the configurations for this plugin.
 */
class Configurations {
	/**
	 * Instance of actual object.
	 *
	 * @var Configurations|null
	 */
	private static ?Configurations $instance = null;

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
	 * @return Configurations
	 */
	public static function get_instance(): Configurations {
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
		add_action( 'admin_action_efml_set_configuration', array( $this, 'set_configuration_by_request' ) );
	}

	/**
	 * Return the list of available configurations.
	 *
	 * @return array<int,string>
	 */
	private function get_configurations(): array {
		$list = array(
			'\ExternalFilesInMediaLibrary\Plugin\Configurations\Standard',
			'\ExternalFilesInMediaLibrary\Plugin\Configurations\Minimal',
		);

		/**
		 * Filter the list of possible configurations.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $list List of configurations objects.
		 */
		return apply_filters( 'efml_configurations', $list );
	}

	/**
	 * Return a configuration by its name.
	 *
	 * @param string $set_name The given name.
	 *
	 * @return false|Configuration_Base
	 */
	private function get_configuration_by_name( string $set_name ): false|Configuration_Base {
		// prepare the return value.
		$result = false;

		// get the configuration by its name.
		foreach ( $this->get_configurations_as_objects() as $configuration_obj ) {
			// bail if name does not match.
			if ( $set_name !== $configuration_obj->get_name() ) {
				continue;
			}

			// secure this object as return value.
			$result = $configuration_obj;
		}

		// return the resulting value.
		return $result;
	}

	/**
	 * Return the list of configurations as objects.
	 *
	 * @return array<int,Configuration_Base>
	 */
	public function get_configurations_as_objects(): array {
		// create the list.
		$list = array();

		// install the schedules if they do not exist atm.
		foreach ( $this->get_configurations() as $obj_name ) {
			// bail if given object does not exist.
			if ( ! class_exists( $obj_name ) ) {
				continue;
			}

			// get the object.
			$obj = new $obj_name();

			// bail if this is not a "Configuration_Base" object.
			if ( ! $obj instanceof Configuration_Base ) {
				continue;
			}

			// add to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Show list of configurations with action buttons.
	 *
	 * @return void
	 */
	public function show_list(): void {
		// get the configurations.
		$configurations = self::get_instance()->get_configurations_as_objects();

		// bail if no sets are known.
		if ( empty( $configurations ) ) {
			return;
		}

		echo '<p>';

		// show the possible sets.
		foreach ( $configurations as $configuration_obj ) {
			// create the URL.
			$url = add_query_arg(
				array(
					'action'   => 'efml_set_configuration',
					'configuration_name' => $configuration_obj->get_name(),
					'nonce'    => wp_create_nonce( 'efml-set-configuration' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create the dialog.
			$dialog = array(
				'title'   => __( 'Set configuration', 'external-files-in-media-library' ),
				'texts'   => array_merge(
					array( '<p><strong>' . __( 'Are you sure you want to set this configuration for the plugin?', 'external-files-in-media-library' ) . '</strong>' ),
					$configuration_obj->get_dialog_hints(),
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
						'text'    => __( 'No', 'external-files-in-media-library' ),
					),
				),
			);

			// show the button.
			echo '<a href="' . esc_url( $url ) . '" class="button button-primary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . esc_html( $configuration_obj->get_title() ) . '</a> ';
		}

		echo '</p>';
	}

	/**
	 * Run a given configuration by request.
	 *
	 * @return void
	 */
	public function set_configuration_by_request(): void {
		// check nonce.
		check_admin_referer( 'efml-set-configuration', 'nonce' );

		// get the name of the set.
		$configuration_name = filter_input( INPUT_GET, 'configuration_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no name is given.
		if ( empty( $configuration_name ) ) {
			wp_safe_redirect( (string) wp_get_referer() );
			return;
		}

		// get the given set as object.
		$configuration_obj = $this->get_configuration_by_name( $configuration_name );

		// bail if no set could be found.
		if ( ! $configuration_obj instanceof Configuration_Base ) {
			wp_safe_redirect( (string) wp_get_referer() );
			return;
		}

		// run the set.
		$configuration_obj->run();

		// show ok message.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'success' );
		$transient_obj->set_name( 'efml_configuration_has_been_set' );
		$transient_obj->set_message( '<strong>' . __( 'The configuration has been set!', 'external-files-in-media-library' ) . '</strong> ' . __( 'Please check the settings of the plugin. You can change them at any time.', 'external-files-in-media-library' ) );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( (string) wp_get_referer() );
	}
}
