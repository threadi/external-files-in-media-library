<?php
/**
 * File to handle support for the plugin "Prevent Direct Access".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class PreventDirectAccess extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?PreventDirectAccess
	 */
	private static ?PreventDirectAccess $instance = null;

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
	 * @return PreventDirectAccess
	 */
	public static function get_instance(): PreventDirectAccess {
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
		// bail if plugin is not installed.
		if ( ! Helper::is_plugin_active( 'prevent-direct-access/prevent-direct-access.php' ) ) {
			return;
		}

		add_action( 'admin_head', array( $this, 'add_style' ) );
	}

	/**
	 * Hide options of this plugin for external files in the media table.
	 *
	 * @return void
	 */
	public function add_style(): void {
		// get external files as list.
		$external_files = Files::get_instance()->get_files();

		// output the custom CSS.
		echo '<style>';
		foreach ( $external_files as $extern_file_obj ) {
			// hide the column of this plugin in the table.
			?>
			#pda-v3-column_<?php echo absint( $extern_file_obj->get_id() ); ?> { display: none; }
			<?php
		}
		echo '</style>';
	}
}
