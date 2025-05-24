<?php
/**
 * File to handle the local support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;

/**
 * Object to handle local import support.
 */
class Local implements Service {

	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'local';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Local
	 */
	private static ?Local $instance = null;

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
	 * @return Local
	 */
	public static function get_instance(): Local {
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
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );
		add_filter( 'eml_import_fields', array( $this, 'add_option_for_local_import' ) );
	}

	/**
	 * Update the local service with our custom action for each file.
	 *
	 * @param array<Directory_Listing_Base> $directory_listing_objects List of directory listing objects.
	 *
	 * @return array<Directory_Listing_Base>
	 * @noinspection PhpArrayAccessCanBeReplacedWithForeachValueInspection
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		foreach ( $directory_listing_objects as $i => $obj ) {
			if ( ! $obj instanceof \easyDirectoryListingForWordPress\Listings\Local ) {
				continue;
			}

			// set actions for the local object.
			$directory_listing_objects[ $i ]->set_actions( $this->get_actions() );
			$directory_listing_objects[ $i ]->add_global_action( $this->get_global_actions() );
		}

		// return resulting list of objects.
		return $directory_listing_objects;
	}

	/**
	 * Add option to import from local directory.
	 *
	 * @param array<int,string> $fields List of import options.
	 *
	 * @return array<int,string>
	 */
	public function add_option_for_local_import( array $fields ): array {
		$fields[] = '<details><summary>' . __( 'Or add from local server directory', 'external-files-in-media-library' ) . '</summary><div><label for="eml_local"><a href="' . Directory_Listing::get_instance()->get_view_directory_url( \easyDirectoryListingForWordPress\Listings\Local::get_instance() ) . '" class="button button-secondary">' . esc_html__( 'Add from local server directory', 'external-files-in-media-library' ) . '</a></label></div></details>';
		return $fields;
	}

	/**
	 * Return the actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		return array(
			array(
				'action' => 'efml_import_url( file.file, "", "", [], term );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_global_actions(): array {
		return array(
			array(
				'action' => 'efml_import_url( actualDirectoryPath, "", "", [], config.term );',
				'label'  => __( 'Import active directory now', 'external-files-in-media-library' ),
			),
			array(
				'action' => 'let config = { "add_to_queue": 1 };efml_import_url( actualDirectoryPath + "/", "", "", config );',
				'label'  => __( 'Import active directory via queue', 'external-files-in-media-library' ),
			),
			array(
				'action' => 'efml_save_as_directory( "local", actualDirectoryPath + "/", "", "", "" );',
				'label'  => __( 'Save active directory as directory archive', 'external-files-in-media-library' ),
			),
		);
	}
}
