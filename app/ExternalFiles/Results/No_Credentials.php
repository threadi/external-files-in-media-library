<?php
/**
 * File to handle the error if no credentials has been given.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Results;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Result_Base;

/**
 * Object to handle error if not credentials has been given.
 */
class No_Credentials extends Result_Base {
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
		return '<strong>' . __( 'No credentials are given!', 'external-files-in-media-library' ) . '</strong> ' . __( 'You indicated that you would provide login details, but did not do so.', 'external-files-in-media-library' );
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
