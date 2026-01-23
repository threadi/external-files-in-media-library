<?php
/**
 * File for an object to handle single service objects, which could be installed as plugins.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle single service objects, which could be installed as plugins.
 */
class Service_Plugin_Base extends Service_Base {
	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Return the description with option to install the plugin.
	 *
	 * @return string
	 */
	public function get_description(): string {
		// bail if user has no permission to install plugins.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return '<span class="connect button button-secondary">' . __( 'Available as plugin', 'external-files-in-media-library' ) . '</span>';
		}

		// show other options if plugin is installed.
		if ( Helper::is_plugin_installed( $this->get_plugin_main_file() ) ) {
			// create the URL to activate this plugin.
			$activate_url = add_query_arg(
				array(
					'action' => 'efml_activate_plugin',
					'name'   => $this->get_name(),
					'nonce'  => wp_create_nonce( 'efml-activate-plugin' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create the dialog with the hint to activate this plugin.
			$dialog = array(
				'className' => 'efml',
				/* translators: %1$s will be replaced by a plugin title. */
				'title'     => sprintf( __( 'Activate %1$s', 'external-files-in-media-library' ), $this->get_plugin_label() ),
				'texts'     => array_merge(
					array(
						'<p><strong>' . __( 'Are you sure you want to activate this WordPress plugin?', 'external-files-in-media-library' ) . '</strong></p>',
					),
					$this->get_install_dialog_description()
				),
				'buttons'   => array(
					array(
						'action'  => 'efml_process_install_and_activate_service_plugin("' . $this->get_name() . '");',
						'variant' => 'primary',
						'text'    => __( 'Yes, activate it', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			// return the resulting description.
			return '<a href="' . esc_url( $activate_url ) . '" class="connect button button-secondary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Activate plugin', 'external-files-in-media-library' ) . '</a>';
		}

		// create the URL to install this plugin.
		$install_url = add_query_arg(
			array(
				'action' => 'efml_install_and_activate_plugin',
				'name'   => $this->get_name(),
				'nonce'  => wp_create_nonce( 'efml-install-plugin' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create the dialog with the hint to install this plugin.
		$dialog = array(
			'className' => 'efml',
			/* translators: %1$s will be replaced by a plugin title. */
			'title'     => sprintf( __( 'Install and activate %1$s', 'external-files-in-media-library' ), $this->get_plugin_label() ),
			'texts'     => array_merge(
				array(
					'<p><strong>' . __( 'Are you sure you want to install and activate this WordPress plugin?', 'external-files-in-media-library' ) . '</strong></p>',
				),
				$this->get_install_dialog_description()
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_process_install_and_activate_service_plugin("' . $this->get_name() . '");',
					'variant' => 'primary',
					'text'    => __( 'Yes, install and activate it', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// return the resulting description.
		return '<a href="' . esc_url( $install_url ) . '" class="connect button button-secondary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Install plugin', 'external-files-in-media-library' ) . '</a>';
	}

	/**
	 * Set this object to disabled to force the visibility of the description.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return true;
	}

	/**
	 * Return the label of the plugin.
	 *
	 * @return string
	 */
	public function get_plugin_label(): string {
		return '';
	}

	/**
	 * Return additional descriptions for the installation- and activation-dialog.
	 *
	 * @return array<int,string>
	 */
	protected function get_install_dialog_description(): array {
		return array();
	}

	/**
	 * Mark that these service plugin does not provide any editable permissions.
	 *
	 * @return bool
	 */
	public function has_no_editable_permissions(): bool {
		return true;
	}

	/**
	 * Return the permission name to use this listing.
	 *
	 * @return string
	 */
	public function get_permission_name(): string {
		return 'edit_posts';
	}
}
