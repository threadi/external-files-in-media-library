<?php
/**
 * File to handle the error if no URLs has been given.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Results;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Result_Base;

/**
 * Object to handle error if no URLs has been given.
 */
class No_Urls extends Result_Base {
	/**
	 * Constructor for this object.
	 */
	public function __construct() {}

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'No URLs given!', 'external-files-in-media-library' );
	}

	/**
	 * Return the text of this result object.
	 *
	 * @return string
	 */
	public function get_text(): string {
		return $this->get_title();
	}
}
