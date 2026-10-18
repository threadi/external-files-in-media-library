<?php
/**
 * File to handle the import of URLs from CSV files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Services\Url_List\Csv_Parser;
use ExternalFilesInMediaLibrary\Services\Url_List\Parser_Base;

/**
 * Object to handle the import of URLs from CSV files.
 */
class Csv extends Url_List_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'csv';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'CSV';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_csv';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Csv
	 */
	private static ?Csv $instance = null;

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
	 * @return Csv
	 */
	public static function get_instance(): Csv {
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
		// set title.
		$this->title = __( 'Import URLs from a CSV file', 'external-files-in-media-library' );

		// use parent initialization.
		parent::init();
	}

	/**
	 * Return the parser for CSV files.
	 *
	 * @return Parser_Base
	 */
	protected function get_parser(): Parser_Base {
		return new Csv_Parser();
	}

	/**
	 * Return the possible file extensions for CSV files.
	 *
	 * @return array<int,string>
	 */
	protected function get_extensions(): array {
		return array( 'csv' );
	}

	/**
	 * Return the possible mime types for CSV files.
	 *
	 * @return array<int,string>
	 */
	protected function get_list_mime_types(): array {
		return array( 'text/csv', 'application/csv', 'text/comma-separated-values' );
	}

	/**
	 * Initialize the WP CLI support for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}
}
