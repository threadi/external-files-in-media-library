<?php
/**
 * File for an object to handle single service objects, which could be installed as plugins.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources_Base;
use ExternalFilesInMediaLibrary\Plugin\Admin\Plugins;
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
		// bail if user has no permission to install plugins, or the project is a WP-multisite.
		if ( ! current_user_can( 'install_plugins' ) || ( is_multisite() && ! is_network_admin() ) ) {
			// create the dialog with the hint to activate this plugin.
			$dialog = array(
				'className' => 'efml',
				'title'     => __( 'Available as plugin', 'external-files-in-media-library' ),
				'texts'     => array_merge(
					array(
						/* translators: %1$s will be replaced by a plugin title. */
						'<p>' . sprintf( __( 'This option is provided by the plugin %1$s.', 'external-files-in-media-library' ), '<em>' . $this->get_plugin_label() . '</em>' ) . '</p>',
						'<p><strong>' . __( 'You are not allowed to install plugins. Please contact your administrator.', 'external-files-in-media-library' ) . '</strong></p>',
					),
				),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			);
			return '<span class="connect button button-secondary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Available as plugin', 'external-files-in-media-library' ) . '</span>';
		}

		// show hint in multisite if plugin is installed.
		if ( is_multisite() && is_network_admin() && Helper::is_plugin_installed( $this->get_plugin_main_file() ) ) {
			// create the dialog with the hint to activate this plugin.
			$dialog = array(
				'className' => 'efml',
				'title'     => __( 'Installed in network', 'external-files-in-media-library' ),
				'texts'     => array_merge(
					array(
						'<p>' . __( 'The plugin is already installed in your multisite. Manage its activation in your network.', 'external-files-in-media-library' ) . '</p>',
					),
				),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			);
			return '<span class="connect button button-secondary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Installed in network', 'external-files-in-media-library' ) . '</span>';
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
					$this->get_source_info(),
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

		// show other text if we are in multisite.
		if ( is_multisite() ) {
			/* translators: %1$s will be replaced by a plugin title. */
			$dialog['title']              = sprintf( __( 'Install %1$s', 'external-files-in-media-library' ), $this->get_plugin_label() );
			$dialog['texts'][0]           = '<p><strong>' . __( 'Are you sure you want to install this WordPress plugin?', 'external-files-in-media-library' ) . '</strong> ' . __( 'You will be able to activate it on each website in your network.', 'external-files-in-media-library' ) . '</p>';
			$dialog['buttons'][0]['text'] = __( 'Yes, install it', 'external-files-in-media-library' );
		}

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

	/**
	 * Return info about the used plugin source.
	 *
	 * @return string
	 */
	private function get_source_info(): string {
		// get the source config.
		$source_config = $this->get_source_config();

		// bail if no source type is given in the config.
		if ( empty( $source_config['type'] ) ) {
			return '';
		}

		// get the source object by the given type name.
		$source_obj = Plugins::get_instance()->get_source_by_name( $source_config['type'] );

		// bail if source could not be loaded.
		if ( ! $source_obj instanceof Plugin_Sources_Base ) {
			return '';
		}

		// return the description for the given source.
		return $source_obj->get_description( $source_config );
	}
}
