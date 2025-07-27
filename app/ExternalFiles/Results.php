<?php
/**
 * This file contains an object which handles the results of any import.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object which handles the results of any import.
 */
class Results {

	/**
	 * Instance of actual object.
	 *
	 * @var Results|null
	 */
	private static ?Results $instance = null;

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
	 * @return Results
	 */
	public static function get_instance(): Results {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Adds a new result.
	 *
	 * @param Result_Base $result_obj The result object, which holds the infos.
	 *
	 * @return void
	 */
	public function add( Result_Base $result_obj ): void {
		// get the actual import results from DB.
		$results = get_option( 'efml_import_results_' . $this->get_user_id(), array() );

		// add this result to the list.
		$results[] = $result_obj;

		// save the list.
		update_option( 'efml_import_results_' . $this->get_user_id(), $results );
	}

	/**
	 * Return the collected results.
	 *
	 * @return array<int,Result_Base>
	 */
	public function get_results(): array {
		// get the actual import results from DB.
		$results = get_option( 'efml_import_results_' . $this->get_user_id(), array() );

		// bail if results is not an array.
		if ( ! is_array( $results ) ) {
			return array();
		}

		// return the results.
		return $results;
	}

	/**
	 * Prepare the result list.
	 *
	 * @return void
	 */
	public function prepare(): void {
		// delete the existing entry.
		delete_option( 'efml_import_results_' . $this->get_user_id() );

		// create a new one.
		add_option( 'efml_import_results_' . $this->get_user_id(), array(), '', false );
	}

	/**
	 * Return the ID of the actual user.
	 *
	 * @return int
	 */
	private function get_user_id(): int {
		return get_current_user_id();
	}
}
