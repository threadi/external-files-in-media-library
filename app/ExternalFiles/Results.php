<?php
/**
 * This file contains an object, which handles the results of any import.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object, which handles the results of any import.
 */
class Results {

	/**
	 * List of objects for results of imports.
	 *
	 * @var array<string,string>
	 */
	private array $result_object_map = array();

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
		// get the actual import results from the database.
		$results = get_option( 'efml_import_results_' . $this->get_user_id(), array() );

		// add this result to the list.
		$results[] = $result_obj->get_state();

		// save the list.
		update_option( 'efml_import_results_' . $this->get_user_id(), $results );
	}

	/**
	 * Return the collected results.
	 *
	 * @return array<int,Result_Base>
	 */
	public function get_results(): array {
		// get the actual import results from the database.
		$results = get_option( 'efml_import_results_' . $this->get_user_id(), array() );

		// bail if results is not an array.
		if ( ! is_array( $results ) ) {
			return array();
		}

		// create the objects.
		$resulting_objects = array();
		foreach ( $results as $result ) {
			// bail if this is already a "Result_Base" (for backwards compatibility).
			if ( $result instanceof Result_Base ) {
				$resulting_objects[] = $result;
				continue;
			}

			// bail if no name is given.
			if ( empty( $result['name'] ) ) {
				continue;
			}

			// create the object.
			$obj = $this->get_result_object_by_name( $result['name'] );

			// bail if no object could be loaded.
			if ( ! $obj instanceof Result_Base ) {
				continue;
			}

			// set the state.
			$obj->set_state( $result );

			// add the object to the list.
			$resulting_objects[] = $obj;
		}

		// return the results.
		return $resulting_objects;
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

	/**
	 * Return the list of possible result objects for imports.
	 *
	 * @return array<int,string>
	 */
	private function get_result_objects(): array {
		// list of result objects.
		$list = array(
			'\ExternalFilesInMediaLibrary\ExternalFiles\Results\No_Credentials',
			'\ExternalFilesInMediaLibrary\ExternalFiles\Results\No_Urls',
			'\ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result',
		);

		/**
		 * Filter the list of possible result objects.
		 *
		 * @since 5.4.0 Available since 5.4.0.
		 * @param array<int,string> $list List of result objects.
		 */
		return apply_filters( 'efml_result_objects', $list );
	}

	/**
	 * Return a result object for a given name.
	 *
	 * @param string $name The given name.
	 *
	 * @return Result_Base|false
	 */
	private function get_result_object_by_name( string $name ): Result_Base|false {
		// build the map on first usage.
		if ( empty( $this->result_object_map ) ) {
			foreach ( $this->get_result_objects() as $class_name ) {
				if ( ! class_exists( $class_name ) ) {
					continue;
				}

				// get the object.
				$obj = new $class_name();

				// bail if object is not "Result_Base".
				if ( ! $obj instanceof Result_Base ) {
					continue;
				}

				// add this object to the list of all result objects.
				$this->result_object_map[ $obj->get_name() ] = $class_name;
			}
		}

		// bail if the name is unknown.
		if ( empty( $this->result_object_map[ $name ] ) ) {
			return false;
		}

		// get the class name.
		$class_name = $this->result_object_map[ $name ];

		// get the object.
		$obj = new $class_name();

		// bail if object is not "Result_Base".
		if ( ! $obj instanceof Result_Base ) {
			return false;
		}

		// return the object.
		return $obj;
	}
}
