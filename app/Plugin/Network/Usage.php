<?php
/**
 * File to show where files of the central media library are used in the network.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Post;

/**
 * Object, which shows where files of the central media library are used in the network.
 *
 * A file counts as used in a post if it is its featured image or if the content contains it as image or in a block
 * with its ID (e.g. image, gallery, cover, file, video). The usage is loaded on demand, as it checks every website.
 */
class Usage {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Usage
	 */
	private static ?Usage $instance = null;

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
	 * @return Usage
	 */
	public static function get_instance(): Usage {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_field' ), 30, 2 );
		add_action( 'wp_ajax_efml_network_usage', array( $this, 'get_usage_via_ajax' ) );
	}

	/**
	 * Return the count of posts in the actual website, which use the given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function get_post_count( int $attachment_id ): int {
		global $wpdb;

		// search for the ID in the contents (images, blocks) and as featured image.
		return absint(
			$wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT p.`ID`) FROM %i AS p WHERE p.`post_type` NOT IN (%s, %s, %s) AND p.`post_status` NOT IN (%s, %s, %s) AND ( p.`post_content` LIKE %s OR p.`post_content` LIKE %s OR p.`post_content` LIKE %s OR p.`post_content` LIKE %s OR p.`ID` IN ( SELECT m.`post_id` FROM %i AS m WHERE m.`meta_key` = %s AND m.`meta_value` = %s ) )',
					array(
						$wpdb->posts,
						'attachment',
						'revision',
						'nav_menu_item',
						'trash',
						'auto-draft',
						'inherit',
						'%' . $wpdb->esc_like( 'wp-image-' . $attachment_id . '"' ) . '%',
						'%' . $wpdb->esc_like( 'wp-image-' . $attachment_id . ' ' ) . '%',
						'%' . $wpdb->esc_like( '"id":' . $attachment_id . ',' ) . '%',
						'%' . $wpdb->esc_like( '"id":' . $attachment_id . '}' ) . '%',
						$wpdb->postmeta,
						'_thumbnail_id',
						(string) $attachment_id,
					)
				)
			)
		);
	}

	/**
	 * Return the usage of the given file of the central media library in the network.
	 *
	 * @param int $central_id The attachment ID in the central media library.
	 *
	 * @return array<int,array{label: string, attachment_id: int, posts: int, edit_url: string}>
	 */
	public function get_usage( int $central_id ): array {
		// collect the usage.
		$usage = array();

		// get the data of the file.
		switch_to_blog( Media_Library::get_instance()->get_central_site_id() );
		$exists = 'attachment' === get_post_type( $central_id );
		$urls   = $exists ? array( Media_Library::get_instance()->get_file_url( $central_id ), (string) wp_get_attachment_url( $central_id ) ) : array();
		$origin = Media_Library::get_instance()->get_origin_blog( $central_id );
		if ( $exists ) {
			$usage[ get_current_blog_id() ] = array(
				'label'         => Media_Library::get_instance()->get_site_label( get_current_blog_id() ),
				'attachment_id' => $central_id,
				'posts'         => $this->get_post_count( $central_id ),
				'edit_url'      => current_user_can( 'edit_post', $central_id ) ? (string) get_edit_post_link( $central_id, 'raw' ) : '',
			);
		}
		restore_current_blog();

		// bail if the file does not exist.
		if ( ! $exists ) {
			return array();
		}

		// check each website.
		foreach ( array_keys( Media_Library::get_instance()->get_sites() ) as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( Media_Library::get_instance()->find_copies( $central_id, $urls, $origin ) as $copy_id ) {
				$usage[ $blog_id ] = array(
					'label'         => Media_Library::get_instance()->get_site_label( $blog_id ),
					'attachment_id' => $copy_id,
					'posts'         => ( $usage[ $blog_id ]['posts'] ?? 0 ) + $this->get_post_count( $copy_id ),
					'edit_url'      => current_user_can( 'edit_post', $copy_id ) ? (string) get_edit_post_link( $copy_id, 'raw' ) : '',
				);
			}
			restore_current_blog();
		}

		// return the usage.
		return $usage;
	}

	/**
	 * Return the usage of the given file as HTML list.
	 *
	 * @param array<int,array{label: string, attachment_id: int, posts: int, edit_url: string}> $usage The usage.
	 *
	 * @return string
	 */
	private function get_usage_html( array $usage ): string {
		// bail if the file is not used.
		if ( empty( $usage ) ) {
			return '<p>' . esc_html__( 'The file is not used in any website.', 'external-files-in-media-library' ) . '</p>';
		}

		// create the list.
		$html = '<ul class="efml-network-usage-list">';
		foreach ( $usage as $site ) {
			/* translators: %1$d will be replaced by a number. */
			$posts = sprintf( _n( 'used in %1$d post', 'used in %1$d posts', $site['posts'], 'external-files-in-media-library' ), $site['posts'] );
			$label = empty( $site['edit_url'] ) ? esc_html( $site['label'] ) : '<a href="' . esc_url( $site['edit_url'] ) . '" target="_blank">' . esc_html( $site['label'] ) . '</a>';
			$html .= '<li>' . $label . ': ' . esc_html( $posts ) . '</li>';
		}
		$html .= '</ul>';

		// return the list.
		return $html;
	}

	/**
	 * Add the field with the usage to the fields of a file of the central media library.
	 *
	 * The usage is loaded via AJAX on request, as it checks every website.
	 *
	 * @param array<string,array<string,mixed>> $form_fields The fields.
	 * @param WP_Post                           $post        The attachment.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_field( array $form_fields, WP_Post $post ): array {
		// bail if this is not a file of the central media library.
		if ( ! Media_Library::get_instance()->is_central() && 0 === absint( get_post_meta( $post->ID, Media_Library::META_CENTRAL_ID, true ) ) && ! Media_Library::get_instance()->is_own_upload( $post->ID ) ) {
			return $form_fields;
		}

		// add the field.
		$form_fields['efml_network_usage'] = array(
			'label' => __( 'Usage in the network', 'external-files-in-media-library' ),
			'input' => 'html',
			'html'  => '<div class="efml-network-usage" data-id="' . absint( $post->ID ) . '"><button type="button" class="button efml-network-usage-load">' . esc_html__( 'Show usage', 'external-files-in-media-library' ) . '</button><div class="efml-network-usage-result"></div></div>',
		);

		// return the fields.
		return $form_fields;
	}

	/**
	 * Return the usage of the requested files via AJAX: as list and as text for the confirmation of their deletion.
	 *
	 * @return void
	 */
	public function get_usage_via_ajax(): void {
		// check the nonce.
		check_ajax_referer( 'efml-network', 'nonce' );

		// bail if no central media library is used.
		if ( ! Media_Library::get_instance()->is_active() ) {
			wp_send_json_error();
		}

		// get the requested attachments.
		$ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['attachment_ids'] ) ) : array();

		// collect the usage.
		$sites = array();
		$posts = 0;
		$html  = '';
		$files = 0;
		foreach ( array_unique( array_filter( $ids ) ) as $attachment_id ) {
			// bail if the user could not edit this file.
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}

			// get the file in the central media library.
			$central_id = Media_Library::get_instance()->is_central() ? $attachment_id : Media_Library::get_instance()->get_central_id( $attachment_id );
			if ( 0 === $central_id ) {
				continue;
			}

			// get its usage.
			$usage = $this->get_usage( $central_id );
			foreach ( $usage as $blog_id => $site ) {
				$sites[ $blog_id ] = true;
				$posts            += $site['posts'];
			}
			$html .= $this->get_usage_html( $usage );
			++$files;
		}

		// bail if no file could be checked.
		if ( 0 === $files ) {
			wp_send_json_error();
		}

		// create the text for the confirmation.
		if ( 1 === $files ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$message = sprintf( _n( 'This file is deleted in all websites of the network. It is available in %1$d website and used there in %2$d posts.', 'This file is deleted in all websites of the network. It is available in %1$d websites and used there in %2$d posts.', count( $sites ), 'external-files-in-media-library' ), count( $sites ), $posts );
		} else {
			/* translators: %1$d, %2$d and %3$d will be replaced by numbers. */
			$message = sprintf( _n( 'These %1$d files are deleted in all websites of the network. They are available in %2$d website and used there in %3$d posts.', 'These %1$d files are deleted in all websites of the network. They are available in %2$d websites and used there in %3$d posts.', count( $sites ), 'external-files-in-media-library' ), $files, count( $sites ), $posts );
		}
		$message .= "\n\n" . ( 1 === $files ? __( 'Do you really want to delete it? This cannot be undone.', 'external-files-in-media-library' ) : __( 'Do you really want to delete them? This cannot be undone.', 'external-files-in-media-library' ) );

		// return the result.
		wp_send_json_success(
			array(
				'html'    => $html,
				'message' => $message,
				'sites'   => count( $sites ),
				'posts'   => $posts,
			)
		);
	}
}
