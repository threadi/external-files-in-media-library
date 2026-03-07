<?php
/**
 * File to add a hint for support of Google Drive platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Forms;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this platform.
 */
class GoogleDrive extends Service_Plugin_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'google-drive';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Google Drive';

	/**
	 * Set the configuration for the external source of this service plugin.
	 *
	 * @var array<string,string>
	 */
	protected array $source_config = array(
		'type'             => 'github',
		'github_user'      => 'threadi',
		'github_slug'      => 'external-files-from-google-drive',
		'plugin_slug'      => 'external-files-from-google-drive',
		'plugin_main_file' => 'external-files-from-google-drive/external-files-from-google-drive.php',
	);

	/**
	 * Instance of actual object.
	 *
	 * @var ?GoogleDrive
	 */
	private static ?GoogleDrive $instance = null;

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
	 * @return GoogleDrive
	 */
	public static function get_instance(): GoogleDrive {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		parent::init();
		add_action( 'admin_init', array( $this, 'check_for_old_google_drive_usage' ) );
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Return the label of the plugin.
	 *
	 * @return string
	 */
	public function get_plugin_label(): string {
		return __( 'External Files from Google Drive in Media Library', 'external-files-in-media-library' );
	}

	/**
	 * Return additional descriptions for the installation- and activation-dialog.
	 *
	 * @return array<int,string>
	 */
	public function get_install_dialog_description(): array {
		return array(
			'<p>' . __( 'It will enable you to use files from Google Drive in your WordPress, export them there, and synchronize them.', 'external-files-in-media-library' ) . '</p>',
			'<p>' . __( 'You can deactivate and uninstall the plugin at any time. However, this will remove the associated files from your media library.', 'external-files-in-media-library' ) . '</p>',
		);
	}

	/**
	 * Check if Google Drive tokens are set and "eml_google_drive_limit" does not exist.
	 * If yes, show hint for user to install the separate Google Drive plugin.
	 */
	public function check_for_old_google_drive_usage(): void {
		// bail if marker is not set.
		if ( empty( get_option( 'eml_google_drive_access_tokens' ) ) ) {
			return;
		}

		// bail if marker for our own plugin exist.
		if ( ! empty( get_option( 'eml_google_drive_limit' ) ) ) {
			return;
		}

		// bail if plugin is installed.
		if ( Helper::is_plugin_installed( $this->get_plugin_main_file() ) ) {
			return;
		}

		// create the URL to activate this plugin.
		$activate_url = add_query_arg(
			array(
				'action' => 'efml_activate_plugin',
				'name'   => $this->get_name(),
				'nonce'  => wp_create_nonce( 'efml-activate-plugin' ),
			),
			get_admin_url() . 'admin.php'
		);

		// embed our JS-files.
		Forms::get_instance()->add_styles_and_js_admin( 'media-new.php' );

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

		// show hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'hint' );
		$transient_obj->set_name( 'eml_hint_for_old_google_drive_usage' );
		$transient_obj->set_message( '<strong>' . __( 'You have used the Google Drive integration with our plugin before version 5.0.0!', 'external-files-in-media-library' ) . '</strong><br><br>' . __( 'Support for Google Drive has been moved to a separate plugin for licensing reasons. To continue using Google Drive as external source for your files, you have to install and activate this plugin. To do so, click on the following button.', 'external-files-in-media-library' ) . '<br><br><a href="' . esc_url( $activate_url ) . '" class="button button-primary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Install and activate External Files from Google Drive in Media Library', 'external-files-in-media-library' ) . '</a>' );
		$transient_obj->save();
	}

	/**
	 * Run additional tasks during plugin uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		delete_option( 'eml_google_drive_access_tokens' );
	}
}
