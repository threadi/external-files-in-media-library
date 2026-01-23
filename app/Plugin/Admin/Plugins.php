<?php
/**
 * File for an object to handle service plugins.
 *
 * Service plugins add support for other platforms to use as source for external files.
 * They will be hosted on GitHub or in the WordPress Repository.
 * This object handles their installation and activation after request from the user.
 *
 * The plugin can also be installed manually and be detected automatically.
 *
 * Each plugin is handled as normal WordPress plugin, this object is just a helper.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Upgrader;

/**
 * Object to handle services plugin.
 */
class Plugins {
	/**
	 * Instance of this object.
	 *
	 * @var ?Plugins
	 */
	private static ?Plugins $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Plugins {
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
		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// add actions.
		add_action( 'admin_action_efml_install_and_activate_plugin', array( $this, 'install_and_activate_plugin_by_request' ) );
		add_action( 'admin_action_efml_activate_plugin', array( $this, 'activate_plugin_by_request' ) );

		// add AJAX hooks.
		add_action( 'wp_ajax_efml_install_and_activate_plugin', array( $this, 'install_and_activate_plugin_by_ajax' ) );
		add_action( 'wp_ajax_efml_get_info_about_install_and_activate_service_plugin', array( $this, 'get_info_about_install_and_activate_service_plugin_via_ajax' ) );
	}

	/**
	 * Add settings for the intro.
	 * *
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get settings object.
		$settings_obj = \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance();

		// get the hidden section.
		$hidden_section = Settings::get_instance()->get_hidden_section();

		// bail if hidden section could not be loaded.
		if ( ! $hidden_section instanceof Section ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_service_plugins_ia' );
		$setting->set_section( $hidden_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->prevent_export( true );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_service_plugins_ia_count' );
		$setting->set_section( $hidden_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->prevent_export( true );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_service_plugins_ia_max' );
		$setting->set_section( $hidden_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->prevent_export( true );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_service_plugins_ia_title' );
		$setting->set_section( $hidden_section );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$setting->prevent_export( true );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_service_plugins_ia_result' );
		$setting->set_section( $hidden_section );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$setting->prevent_export( true );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_service_plugins_ia_name' );
		$setting->set_section( $hidden_section );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$setting->prevent_export( true );
	}

	/**
	 * Install a plugin by request.
	 *
	 * @return void
	 */
	public function install_and_activate_plugin_by_request(): void {
		// check nonce.
		check_admin_referer( 'efml-install-plugin', 'nonce' );

		// bail if user has not the required permissions.
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// get the requested service name.
		$service_name = filter_input( INPUT_GET, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// get service object by this given name.
		$service_obj = $this->get_service_by_name( $service_name );

		// bail if the service could not be loaded.
		if ( ! $service_obj instanceof Service_Base ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// get the source configuration for the plugin.
		$source_config = $service_obj->get_source_config();

		// select the handler for the given source.
		$source = false;
		foreach ( $this->get_source_types_as_object() as $plugin_source_type ) {
			// bail if it does not match.
			if ( $plugin_source_type->get_name() !== $source_config['type'] ) {
				continue;
			}

			// set this source type.
			$source = $plugin_source_type;
		}

		// bail if no source type could be found.
		if ( ! $source instanceof Plugin_Sources_Base ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// get the file from the external source, save the absolute local path.
		$plugin_zip = $source->get_file( $source_config );

		// bail if no plugin zip could be loaded.
		if ( empty( $plugin_zip ) ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// install it.
		$this->install_and_activate_plugin( $plugin_zip, $service_obj->get_plugin_slug(), $service_obj->get_plugin_main_file() );

		// trigger hint, if the installation was not successfully.
		if ( ! Helper::is_plugin_active( $service_obj->get_plugin_main_file() ) ) {
			// show ok message.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_type( 'error' );
			$transient_obj->set_name( 'efml_error_installing_plugin' );
			$transient_obj->set_message( '<strong>' . __( 'The plugin could not be installed and activated!', 'external-files-in-media-library' ) . '</strong>' );
			$transient_obj->save();

			// forward user.
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// forward user to the service page.
		wp_safe_redirect( Directory_Listing::get_instance()->get_view_directory_url( $service_obj ) );
	}

	/**
	 * Install and activate the given plugin by its absolute path and slug.
	 *
	 * @param string $path        The absolute path to the plugin to install.
	 * @param string $plugin_slug The plugin slug.
	 * @param string $plugin_main_file The plugin main file.
	 *
	 * @return void
	 */
	private function install_and_activate_plugin( string $path, string $plugin_slug, string $plugin_main_file ): void {
		// set the plugin directory for this plugin.
		$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

		// bail if this directory does already exist.
		$wp_filesystem = Helper::get_wp_filesystem();
		if ( $wp_filesystem->exists( $plugin_dir ) ) {
			// check if it is active.
			if ( ! Helper::is_plugin_active( $plugin_main_file ) ) {
				// set the progress title.
				update_option( 'eml_service_plugins_ia_title', __( 'Activating the plugin', 'external-files-in-media-library' ) );

				// set the actual step.
				update_option( 'eml_service_plugins_ia_count', 4 );

				// activate the plugin.
				require_once ABSPATH . 'wp-admin/includes/admin.php';
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				activate_plugin( $plugin_main_file );
			}

			// do nothing more.
			return;
		}

		// set the progress title.
		update_option( 'eml_service_plugins_ia_title', __( 'Installing the plugin', 'external-files-in-media-library' ) );

		// set the actual step.
		update_option( 'eml_service_plugins_ia_count', 3 );

		// unpack this package in the plugin directory.
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader       = new WP_Upgrader( new Upgrader_Skin() );
		$install_result = $upgrader->run(
			array(
				'package'     => $path,
				'destination' => $plugin_dir,
			)
		);

		// bail if the unpacking was not successfully.
		if ( is_wp_error( $install_result ) ) {
			return;
		}

		// bail if the required plugin dir does not exist.
		if ( ! $wp_filesystem->exists( $plugin_dir ) ) { // @phpstan-ignore booleanNot.alwaysTrue
			return;
		}

		// set the progress title.
		update_option( 'eml_service_plugins_ia_title', __( 'Activating the plugin', 'external-files-in-media-library' ) ); // @phpstan-ignore deadCode.unreachable

		// set the actual step.
		update_option( 'eml_service_plugins_ia_count', 4 );

		// activate the plugin.
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		activate_plugin( $plugin_main_file );
	}

	/**
	 * Return the list of possible plugin source types as array.
	 *
	 * @return array<int,Plugin_Sources_Base>
	 */
	private function get_source_types_as_object(): array {
		// create the list of objects.
		$list = array();

		// create an object for each type.
		foreach ( $this->get_source_types() as $source_type_name ) {
			// bail if class name does not exist.
			if ( ! class_exists( $source_type_name ) ) {
				continue;
			}

			// get the object.
			$obj = new $source_type_name();

			// bail if object is not "Schedules_Base".
			if ( ! $obj instanceof Plugin_Sources_Base ) {
				continue;
			}

			// add this object to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return list of possible plugin sources for services of this plugin.
	 *
	 * @return array<int,string>
	 */
	private function get_source_types(): array {
		$list = array(
			'\ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources\GitHub',
			'\ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources\WordPressRepository',
		);

		/**
		 * Filter the list of possible plugin sources for services of this plugin.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $list List of plugin sources.
		 */
		return apply_filters( 'efml_plugin_sources', $list );
	}

	/**
	 * Activate a plugin by request.
	 *
	 * @return void
	 */
	public function activate_plugin_by_request(): void {
		// check nonce.
		check_admin_referer( 'efml-activate-plugin', 'nonce' );

		// bail if user has not the required permissions.
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// get the requested service name.
		$service_name = filter_input( INPUT_GET, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// get service object by this given name.
		$service_obj = $this->get_service_by_name( $service_name );

		// bail if the service could not be loaded.
		if ( ! $service_obj instanceof Service_Base ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// get the source configuration for the plugin.
		$source_config = $service_obj->get_source_config();

		// bail if no plugin mail file is set.
		if ( empty( $source_config['plugin_main_file'] ) ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// activate the plugin.
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		activate_plugin( $source_config['plugin_main_file'] );

		// trigger hint, if the activation was not successfully.
		if ( ! Helper::is_plugin_active( $service_obj->get_plugin_main_file() ) ) {
			// show ok message.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_type( 'error' );
			$transient_obj->set_name( 'efml_error_activation_plugin' );
			$transient_obj->set_message( '<strong>' . __( 'The plugin could not be activated!', 'external-files-in-media-library' ) . '</strong>' );
			$transient_obj->save();

			// forward user.
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// forward user to the service page.
		wp_safe_redirect( Directory_Listing::get_instance()->get_view_directory_url( $service_obj ) );
	}

	/**
	 * Return the service object by given name incl. check if it is a plugin object.
	 *
	 * @param mixed $service_name The given service name.
	 *
	 * @return Service_Base|false
	 */
	private function get_service_by_name( mixed $service_name ): Service_Base|false {
		// bail if given name is not a string.
		if ( ! is_string( $service_name ) ) {
			return false;
		}

		// get the service object.
		$service_obj = Services::get_instance()->get_service_by_name( $service_name );

		// bail if service could not be loaded.
		if ( ! $service_obj instanceof Service_Base ) {
			return false;
		}

		// bail if service is not a plugin.
		if ( ! $service_obj->is_plugin() ) {
			return false;
		}

		// return the service object.
		return $service_obj;
	}

	/**
	 * Installation and activate a service plugin by AJAX request.
	 *
	 * This also activated a service plugin that is already installed.
	 *
	 * @return void
	 */
	public function install_and_activate_plugin_by_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-install-and-activate-plugin', 'nonce' );

		// mark as running.
		update_option( 'eml_service_plugins_ia', time() );

		// set max count of steps.
		update_option( 'eml_service_plugins_ia_max', 4 );

		// set the actual step.
		update_option( 'eml_service_plugins_ia_count', 1 );

		// reset the result.
		update_option( 'eml_service_plugins_ia_result', array() );

		// set the progress title.
		update_option( 'eml_service_plugins_ia_title', __( 'Checking environment', 'external-files-in-media-library' ) );

		// bail if user has not the capability to install plugins.
		if ( ! current_user_can( 'install_plugins' ) ) {
			// set the result.
			update_option(
				'eml_service_plugins_ia_result',
				array(
					'<p>' . __( 'You are not allowed to install plugins. Please contact your administrator.', 'external-files-in-media-library' ) . '</p>',
				)
			);

			// delete running marker.
			update_option( 'eml_service_plugins_ia', 0 );

			// send error.
			wp_send_json_error();
		}

		// get the requested service name.
		$service_name = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// get service object by this given name.
		$service_obj = $this->get_service_by_name( $service_name );

		// bail if the service could not be loaded.
		if ( ! $service_obj instanceof Service_Base ) {
			// set the result.
			update_option(
				'eml_service_plugins_ia_result',
				array(
					'<p>' . __( 'The requested service is not valid.', 'external-files-in-media-library' ) . '</p>',
				)
			);

			// delete running marker.
			update_option( 'eml_service_plugins_ia', 0 );

			// send error.
			wp_send_json_error();
		}

		// get the source configuration for the plugin.
		$source_config = $service_obj->get_source_config();

		// secure the main plugin file name.
		$plugin_mail_file = $service_obj->get_plugin_main_file();
		update_option( 'eml_service_plugins_ia_name', $plugin_mail_file );

		// select the handler for the given source.
		$source = false;
		foreach ( $this->get_source_types_as_object() as $plugin_source_type ) {
			// bail if it does not match.
			if ( $plugin_source_type->get_name() !== $source_config['type'] ) {
				continue;
			}

			// set this source type.
			$source = $plugin_source_type;
		}

		// bail if no source type could be found.
		if ( ! $source instanceof Plugin_Sources_Base ) {
			// set the result.
			update_option(
				'eml_service_plugins_ia_result',
				array(
					'<p>' . __( 'The requested source is unknown.', 'external-files-in-media-library' ) . '</p>',
				)
			);

			// delete running marker.
			update_option( 'eml_service_plugins_ia', 0 );

			// send error.
			wp_send_json_error();
		}

		// check if plugin is installed, but not activated.
		if ( ! Helper::is_plugin_installed( $source_config['plugin_main_file'] ) ) {

			// set the progress title.
			update_option( 'eml_service_plugins_ia_title', __( 'Downloading plugin release', 'external-files-in-media-library' ) );

			// set the actual step.
			update_option( 'eml_service_plugins_ia_count', 2 );

			// get the file from the external source, save the absolute local path.
			$plugin_zip = $source->get_file( $source_config );

			// bail if no plugin zip could be loaded.
			if ( empty( $plugin_zip ) ) {
				// set the result.
				update_option(
					'eml_service_plugins_ia_result',
					array(
						'<p>' . __( 'Could not download the plugin release.', 'external-files-in-media-library' ) . '</p>',
					)
				);

				// delete running marker.
				update_option( 'eml_service_plugins_ia', 0 );

				// send error.
				wp_send_json_error();
			}
		} else {
			// set the path to the installed, but not activated plugin file.
			$plugin_zip = WP_PLUGIN_DIR . '/' . $source_config['plugin_main_file'];
		}

		// install it.
		$this->install_and_activate_plugin( $plugin_zip, $service_obj->get_plugin_slug(), $plugin_mail_file );

		// trigger hint, if the installation was not successfully.
		if ( ! Helper::is_plugin_active( $plugin_mail_file ) ) {
			// set the result.
			update_option(
				'eml_service_plugins_ia_result',
				array(
					'<p>' . __( 'Could not activate the plugin.', 'external-files-in-media-library' ) . '</p>',
				)
			);

			// delete running marker.
			update_option( 'eml_service_plugins_ia', 0 );

			// send error.
			wp_send_json_error();
		}

		// delete running marker.
		update_option( 'eml_service_plugins_ia', 0 );

		// send ok.
		wp_send_json_success();
	}

	/**
	 * Return info about state of installing and activating a plugin via AJAX.
	 *
	 * @return void
	 */
	public function get_info_about_install_and_activate_service_plugin_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-get-install-and-activate-plugin-info-nonce', 'nonce' );

		// get the running marker.
		$running = absint( get_option( 'eml_service_plugins_ia' ) );

		// if installation and activation is not running anymore, build the dialog for the response.
		$dialog = array();
		if ( 0 === $running ) {
			// set the plugin name for response.
			$plugin_name = '';

			// get the plugin data.
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . get_option( 'eml_service_plugins_ia_name', '' ), false, false );

			// get the plugin name from its data array.
			if ( ! empty( $plugin_data['Name'] ) ) {
				$plugin_name = $plugin_data['Name'];
			}

			// set default text.
			$texts = array(
				/* translators: %1$s will be replaced by the plugin name. */
				'<p>' . sprintf( __( 'The plugin %1$s has been installed and activated.', 'external-files-in-media-library' ), '<em>' . $plugin_name . '</em>' ) . '</p>',
			);

			// set default button.
			$buttons = array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'Done', 'external-files-in-media-library' ),
				),
			);

			// get the requested service name.
			$service_name = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			// Get the service object of the plugin, which has just been installed and activated.
			// It is now a normal service, not a plugin service anymore.
			$service_obj = Services::get_instance()->get_service_by_name( $service_name );

			// get the result, if set, and replace the default text with its contents.
			$result = get_option( 'eml_service_plugins_ia_result' );
			if ( is_array( $result ) && ! empty( $result ) ) {
				$texts = $result;
			}

			// set the button as link if service could be loaded.
			if ( empty( $result ) && $service_obj instanceof Service_Base ) {
				$buttons[0]['action'] = 'location.href="' . Directory_Listing::get_instance()->get_view_directory_url( $service_obj ) . '"';
			}

			// create the dialog.
			$dialog = array(
				'detail' => array(
					'className' => 'efml',
					'title'     => __( 'Installation and activation has been executed', 'external-files-in-media-library' ),
					'texts'     => $texts,
					'buttons'   => $buttons,
				),
			);
		}

		// return import info.
		wp_send_json(
			array(
				absint( get_option( 'eml_service_plugins_ia_count', 0 ) ),
				absint( get_option( 'eml_service_plugins_ia_max', 0 ) ),
				$running,
				wp_kses_post( get_option( 'eml_service_plugins_ia_title', '' ) ),
				$dialog,
			)
		);
	}
}
