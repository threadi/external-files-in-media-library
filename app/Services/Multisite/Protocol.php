<?php
/**
 * File, which handles the Multisite support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Multisite;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Services\Multisite;
use WP_Filesystem_Base;
use WP_Post;
use WP_Query;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'multisite';

	/**
	 * Return whether the file using this protocol is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// get the fields.
		$fields = $this->get_fields();

		// bail if fields does not contain a website-entry for multisite-setting.
		if ( ! isset( $fields['website'] ) ) {
			return false;
		}

		// return true to mark this file as compatible with this protocol handler.
		return true;
	}

	/**
	 * Check format of given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_url( string $url ): bool {
		// bail if empty URL is given.
		if ( empty( $url ) ) {
			return false;
		}

		// return true as Multisite URLs are available.
		return true;
	}

	/**
	 * Return infos about single given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array<string,mixed>
	 */
	public function get_url_info( string $url ): array {
		// get the fields.
		$fields = $this->get_fields();

		// get the HTTP protocol handler.
		$http_protocol_handler = new Http( $url );
		$http_protocol_handler->set_fields( $fields );

		// get the info for the given URL.
		$url_info = $http_protocol_handler->get_url_info( $url );

		// add a marker for used service in the file infos.
		$url_info['service'] = $this->get_name();

		// get the blog ID.
		$blog_id = absint( $fields['website']['value'] );

		// bail if blog ID is not given.
		if ( 0 === $blog_id ) {
			return array();
		}

		// switch to the other site.
		switch_to_blog( $blog_id );

		/**
		 * Get the attachment ID for the given URL.
		 *
		 * Goal: attachment_url_to_postid( $url );
		 *
		 * But: does not work because of this Core bug: core.trac.wordpress.org/ticket/25650
		 *
		 * Our solution:
		 * 1. Get the file path without the domain.
		 * 2. Query for it in "_wp_attached_file" in the postmeta table.
		 */
		$path   = str_replace( trailingslashit( get_blogaddress_by_id( $blog_id ) ) . 'wp-content/uploads/', '', $url );
		$query  = array(
			'post_type'   => 'attachment',
			'post_status' => 'any',
			'meta_query'  => array(
				array(
					'key'     => '_wp_attached_file',
					'value'   => $path,
					'compare' => '=',
				),
			),
		);
		$result = new WP_Query( $query );

		// bail on no result.
		if ( 0 === $result->found_posts ) {
			// return to our blog.
			restore_current_blog();

			// return the collected URL info without optimized data.
			return $url_info;
		}

		// get the post object.
		$post_obj = $result->posts[0];

		// bail if post object could not be loaded.
		if ( ! $post_obj instanceof WP_Post ) {
			// return to our blog.
			restore_current_blog();

			// return the collected URL info without optimized data.
			return $url_info;
		}

		// use its title in URL infos.
		$url_info['title'] = $post_obj->post_title;

		// return to our blog.
		restore_current_blog();

		// return the URL info.
		return $url_info;
	}

	/**
	 * Return temp file from given URL.
	 *
	 * @param string             $url The given URL.
	 * @param WP_Filesystem_Base $filesystem The file system handler.
	 *
	 * @return bool|string
	 */
	public function get_temp_file( string $url, WP_Filesystem_Base $filesystem ): bool|string {
		// get the HTTP protocol handler.
		$http_protocol_handler = new Http( $url );
		$http_protocol_handler->set_fields( $this->get_fields() );

		// return the results from the HTTP handler.
		return $http_protocol_handler->get_temp_file( $url, $filesystem );
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>> List of files with its infos.
	 */
	public function get_url_infos(): array {
		// get the HTTP protocol handler.
		$http_protocol_handler = new Http( $this->get_url() );
		$http_protocol_handler->set_fields( $this->get_fields() );

		// return the results from the HTTP handler.
		return $http_protocol_handler->get_url_infos();
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
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return Multisite::get_instance()->get_label();
	}

	/**
	 * Return whether this URL could be checked for availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}
}
