<?php
/**
 * File to handle any URL-specific errors after trying to import them.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Results;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Result_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle URL-specific error after trying to import it.
 */
class Url_Result extends Result_Base {
	/**
	 * The error text.
	 *
	 * @var string
	 */
	private string $error_text = '';

	/**
	 * The URL.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * The attachment ID.
	 *
	 * @var int
	 */
	private int $attachment_id = 0;

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
		// get the URL.
		$url = $this->get_url();

		// get the edit URL.
		$edit_url = '';
		if ( ! $this->is_error() && $this->get_attachment_id() > 0 ) {
			$edit_url = get_edit_post_link( $this->get_attachment_id() );
		} elseif ( $this->is_error() ) {
			// get the external file object for this URL.
			$external_file_obj = Files::get_instance()->get_file_by_url( $url );

			// bail if external file could not be loaded.
			if ( ! $external_file_obj ) {
				return $url . '<br>' . $this->get_result_text();
			}

			// if external file is valid, get its edit-URL.
			if ( $external_file_obj->is_valid() ) {
				$edit_url = get_edit_post_link( $external_file_obj->get_id() );
			}
		}

		// create link to edit this URL as attachment, if edit-URL is set.
		$edit_html = '';
		if ( ! empty( $edit_url ) ) {
			$edit_html = '<a href="' . esc_url( $edit_url ) . '" target="_blank" class="dashicons dashicons-edit"></a>';
		}

		// if string is not a valid URL just show it.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $url . ' ' . $edit_html . '<br>' . $this->get_result_text();
		}

		// otherwise link it.
		return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( Helper::shorten_url( $url ) ) . '</a> ' . $edit_html . '<br>' . wp_kses_post( $this->get_result_text() );
	}

	/**
	 * Return the error text.
	 *
	 * @return string
	 */
	private function get_result_text(): string {
		return $this->error_text;
	}

	/**
	 * Set the error text.
	 *
	 * @param string $error_text The error text.
	 *
	 * @return void
	 */
	public function set_result_text( string $error_text ): void {
		$this->error_text = $error_text;
	}

	/**
	 * Return the url.
	 *
	 * @return string
	 */
	private function get_url(): string {
		return $this->url;
	}

	/**
	 * Set the URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	public function set_url( string $url ): void {
		$this->url = $url;
	}

	/**
	 * Return the attachment ID.
	 *
	 * @return int
	 */
	private function get_attachment_id(): int {
		return $this->attachment_id;
	}

	/**
	 * Set the attachment ID.
	 *
	 * @param int $attachment_id The ID.
	 *
	 * @return void
	 */
	public function set_attachment_id( int $attachment_id ): void {
		$this->attachment_id = $attachment_id;
	}
}
