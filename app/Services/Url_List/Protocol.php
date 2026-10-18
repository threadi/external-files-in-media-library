<?php
/**
 * File, which handles the addresses of lists with URLs (like CSV or XML files) as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Url_List;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\Services\Url_List_Base;
use WP_Filesystem_Base;

/**
 * Object to handle the addresses of lists with URLs.
 *
 * The protocol is only used for addresses of lists, which are given with the fields of a list service
 * (see @Url_List_Base). The files of the list are handled by the protocols for their URLs (e.g. HTTP).
 */
class Protocol extends Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'url_list';

	/**
	 * Return whether this protocol is available in this hosting.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Return the list service for the fields of this object.
	 *
	 * @return Url_List_Base|false
	 */
	private function get_service(): Url_List_Base|false {
		// get the fields.
		$fields = $this->get_fields();

		// bail if no list type is set in fields.
		if ( empty( $fields['list_type']['value'] ) || ! is_string( $fields['list_type']['value'] ) ) {
			return false;
		}

		// get the service.
		$service_obj = Services::get_instance()->get_service_by_name( $fields['list_type']['value'] );

		// bail if this is not a list service.
		if ( ! $service_obj instanceof Url_List_Base ) {
			return false;
		}

		// bail if the service could not be used in this request.
		if ( ! $service_obj->is_usable_for_import() ) {
			return false;
		}

		// return the service.
		return $service_obj;
	}

	/**
	 * Check if URL is compatible with this protocol: it must be an address of the list from the fields.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// get the service.
		$service_obj = $this->get_service();

		// bail if no service could be found.
		if ( ! $service_obj ) {
			return false;
		}

		// return whether the URL is an address of the list.
		return false !== $service_obj->parse_address( $this->get_url(), $this->get_fields() );
	}

	/**
	 * Return infos to each file of the list.
	 *
	 * @return array<int|string,array<string,mixed>> List of files with its infos.
	 */
	public function get_url_infos(): array {
		// get the service.
		$service_obj = $this->get_service();

		// bail if no service could be found.
		if ( ! $service_obj ) {
			return array();
		}

		// return the files of the list.
		return $service_obj->get_file_infos( $this->get_url(), $this->get_fields() ); // @phpstan-ignore return.type
	}

	/**
	 * Return temp file from given URL.
	 *
	 * Lists are not saved as temp files, their files are handled by their own protocols.
	 *
	 * @param string             $url The given URL.
	 * @param WP_Filesystem_Base $filesystem The file system handler.
	 *
	 * @return bool|string
	 */
	public function get_temp_file( string $url, WP_Filesystem_Base $filesystem ): bool|string {
		return false;
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return false;
	}

	/**
	 * Return whether this URL could be checked for availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		// get the service.
		$service_obj = $this->get_service();

		// return its label or a general title.
		return $service_obj ? $service_obj->get_label() : __( 'List of URLs', 'external-files-in-media-library' );
	}
}
