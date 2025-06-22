<?php
/**
 * File which provide the base functions for each result object.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object serving the base result object.
 */
class Result_Base {
	/**
	 * Whether this is an error object or not.
	 *
	 * @var bool
	 */
	private bool $error = true;

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Return the text of this result object.
	 *
	 * @return string
	 */
	public function get_text(): string {
		return '';
	}

	/**
	 * Mark this as error or not.
	 *
	 * @param bool $is_error The mark (true = error, false = no error).
	 *
	 * @return void
	 */
	public function set_error( bool $is_error ): void {
		$this->error = $is_error;
	}

	/**
	 * Return whether this is an error.
	 *
	 * @return bool
	 */
	public function is_error(): bool {
		return $this->error;
	}
}
