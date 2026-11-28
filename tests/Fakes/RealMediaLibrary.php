<?php
/**
 * Minimal version of the API of the plugin Real Media Library (Pro) for the tests (only in separate processes).
 *
 * It contains only the functions, which are used by this plugin, with the same signatures.
 *
 * @package external-files-in-media-library
 */

namespace MatthiasWeb\RealMediaLibrary {
	/**
	 * Minimal version of the main class.
	 */
	class Core {
		/**
		 * The folders with their ID as key.
		 *
		 * @var array<int,array{name: string, parent: int}>
		 */
		public static array $folders = array();

		/**
		 * The folder of each attachment.
		 *
		 * @var array<int,int>
		 */
		public static array $relations = array();

		/**
		 * Return the instance.
		 *
		 * @return Core
		 */
		public static function getInstance() {
			return new self();
		}

		/**
		 * Return whether this is the Pro version.
		 *
		 * @return bool
		 */
		public function isPro() {
			return true;
		}
	}
}

namespace {
	use MatthiasWeb\RealMediaLibrary\Core;

	/**
	 * Return the ID of the root folder.
	 *
	 * @return int
	 */
	function _wp_rml_root() {
		return -1;
	}

	/**
	 * Create a folder or return the ID of an existing folder.
	 *
	 * @param string $name The name.
	 * @param int    $parent The parent.
	 * @param int    $type The type.
	 * @param array  $restrictions The restrictions.
	 * @param bool   $supress_validation Whether to suppress the validation.
	 *
	 * @return int|string[]
	 */
	function wp_rml_create_or_return_existing_id( $name, $parent, $type, $restrictions = array(), $supress_validation = false ) {
		foreach ( Core::$folders as $id => $folder ) {
			if ( $folder['name'] === $name && $folder['parent'] === (int) $parent ) {
				return $id;
			}
		}
		$id                  = empty( Core::$folders ) ? 1 : max( array_keys( Core::$folders ) ) + 1;
		Core::$folders[ $id ] = array(
			'name'   => $name,
			'parent' => (int) $parent,
		);
		return $id;
	}

	/**
	 * Return the folder object (here: the data) for the given ID.
	 *
	 * @param int $id The ID.
	 *
	 * @return array<string,mixed>|null
	 */
	function wp_rml_get_object_by_id( $id ) {
		return Core::$folders[ (int) $id ] ?? null;
	}

	/**
	 * Return whether the given object is a folder.
	 *
	 * @param mixed $folder The object.
	 *
	 * @return bool
	 */
	function is_rml_folder( $folder ) {
		return is_array( $folder );
	}

	/**
	 * Move the attachments to the folder.
	 *
	 * @param int        $to The folder.
	 * @param array<int> $ids The attachments.
	 * @param bool       $supress_validation Whether to suppress the validation.
	 *
	 * @return bool
	 */
	function wp_rml_move( $to, $ids, $supress_validation = false ) {
		foreach ( $ids as $id ) {
			Core::$relations[ (int) $id ] = (int) $to;
		}
		return true;
	}
}
