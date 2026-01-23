<?php
/**
 * File to add a hint for support of Hello Dolly placebo platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for this platform.
 */
class HelloDolly extends Service_Plugin_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'hello-dolly';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Hello Dolly';

	/**
	 * Set the configuration for the external source of this service plugin.
	 *
	 * @var array<string,string>
	 */
	protected array $source_config = array(
		'type'             => 'wordpress-repository',
		'plugin_slug'      => 'hello-dolly',
		'plugin_main_file' => 'hello-dolly/hello.php',
	);

	/**
	 * Instance of actual object.
	 *
	 * @var ?HelloDolly
	 */
	private static ?HelloDolly $instance = null;

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
	 * @return HelloDolly
	 */
	public static function get_instance(): HelloDolly {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
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
		return __( 'Hello Dolly Service Plugin Demo', 'external-files-in-media-library' );
	}

	/**
	 * Return additional descriptions for the installation- and activation-dialog.
	 *
	 * @return array<int,string>
	 */
	protected function get_install_dialog_description(): array {
		return array(
			'<p>' . __( 'This is only a demo for developers how a service plugin could be installed. You will not be able to use is as service.', 'external-files-in-media-library' ) . '</p>',
		);
	}
}
