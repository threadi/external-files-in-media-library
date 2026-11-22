<?php
/**
 * File to handle the rights of the websites to delete files of the central media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Post;

/**
 * Object, which handles the rights of the websites to delete files of the central media library.
 *
 * A file of the central media library is deleted in the whole network. Depending on the right of a website its users
 * could delete:
 * - none: no file of the central media library,
 * - own: only the files uploaded in this website (like in versions before 6.1.0),
 * - all: every file of the central media library.
 *
 * Before such a deletion the user has to confirm it, the confirmation names the websites and posts using the file.
 */
class Permissions {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Permissions
	 */
	private static ?Permissions $instance = null;

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
	 * @return Permissions
	 */
	public static function get_instance(): Permissions {
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
		// check the right before a deletion and delete the central file for a deletion in a website.
		add_filter( 'pre_delete_attachment', array( $this, 'check_deletion' ), 5, 2 );
		add_filter( 'efml_sync_prevent_deletion', array( $this, 'allow_deletion_of_synced_file' ), 10, 2 );
		add_action( 'delete_attachment', array( $this, 'delete_central_file' ), 1 );

		// show the deletion only if it is allowed, and mark deletions in the whole network for the confirmation.
		add_filter( 'media_row_actions', array( $this, 'change_row_actions' ), 20, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'change_attachment_for_js' ), 20, 2 );

		// add the script for the confirmation.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_script' ) );
		add_action( 'wp_enqueue_media', array( $this, 'add_script' ) );
	}

	/**
	 * Return whether the actual deletion is done by the system (e.g. by the synchronization, via cron or WP CLI).
	 *
	 * These deletions only remove files in the actual website, they are not checked and not passed to the network.
	 *
	 * @return bool
	 */
	private function is_system_deletion(): bool {
		// get the requested action.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only used to detect the type of the request.

		return Propagation::get_instance()->is_running()
			|| defined( 'EFML_DEINSTALLATION_RUNNING' )
			|| wp_doing_cron()
			|| Helper::is_cli()
			|| Synchronization::get_instance()->is_running()
			// the deletion of an external source with its files.
			|| ! is_null( filter_input( INPUT_POST, 'tag_ID', FILTER_SANITIZE_NUMBER_INT ) )
			|| ! is_null( filter_input( INPUT_GET, 'tag_ID', FILTER_SANITIZE_NUMBER_INT ) )
			// the deletion of the synchronized files of an external source.
			|| in_array( $action, array( 'efml_delete_synced_files', 'efml_delete_synced_from_directory' ), true );
	}

	/**
	 * Return whether the actual user could delete the given attachment of the actual website regarding the rules of
	 * the central media library.
	 *
	 * Returns null if the rules do not apply to this attachment (e.g. a file, which is not from the central media
	 * library, or a file in the central media library itself).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool|null
	 */
	public function may_delete( int $attachment_id ): ?bool {
		// bail if this website does not use a central media library.
		if ( ! Media_Library::get_instance()->is_using_central() ) {
			return null;
		}

		// get the right of this website.
		$right = Media_Library::get_instance()->get_delete_right( get_current_blog_id() );

		// an own upload could be deleted, except the right is "none".
		if ( Media_Library::get_instance()->is_own_upload( $attachment_id ) ) {
			return 'none' !== $right;
		}

		// bail if this is not a file of the central media library.
		if ( 0 === Media_Library::get_instance()->get_central_id( $attachment_id ) ) {
			return null;
		}

		// other files of the central media library could only be deleted with the right "all".
		return 'all' === $right;
	}

	/**
	 * Return whether the deletion of the given attachment of the actual website deletes the file in the whole network.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_network_deletion( int $attachment_id ): bool {
		// each file in the central media library is deleted in the whole network.
		if ( Media_Library::get_instance()->is_central() ) {
			return true;
		}

		// in the other websites it depends on the right.
		return true === $this->may_delete( $attachment_id );
	}

	/**
	 * Prevent the deletion of a file of the central media library, if the website does not have the right for it.
	 *
	 * @param WP_Post|false|null $delete The marker whether the file should be deleted.
	 * @param WP_Post            $post   The attachment.
	 *
	 * @return WP_Post|false|null
	 */
	public function check_deletion( WP_Post|false|null $delete, WP_Post $post ): WP_Post|false|null {
		// bail if this is a deletion by the system.
		if ( $this->is_system_deletion() ) {
			return $delete;
		}

		// prevent the deletion, if it is not allowed.
		if ( false === $this->may_delete( $post->ID ) ) {
			return false;
		}

		// return the given value.
		return $delete;
	}

	/**
	 * Allow the deletion of a synchronized file of the central media library, if the website has the right for it.
	 *
	 * @param bool    $prevent True if the deletion is prevented.
	 * @param WP_Post $post    The attachment.
	 *
	 * @return bool
	 */
	public function allow_deletion_of_synced_file( bool $prevent, WP_Post $post ): bool {
		// allow the deletion by the propagation of a deletion in the central media library.
		if ( Propagation::get_instance()->is_running() ) {
			return false;
		}

		// allow it, if the website has the right.
		if ( ! $this->is_system_deletion() && true === $this->may_delete( $post->ID ) ) {
			return false;
		}

		// return the given value.
		return $prevent;
	}

	/**
	 * Delete the file in the central media library, if its copy is deleted in a website with the right "all".
	 *
	 * The deletion in the central media library deletes the file in all other websites. The own uploads of a website
	 * are deleted in the central media library by their export (see Services\Multisite\Export).
	 *
	 * @param int $attachment_id The attachment ID in the actual website.
	 *
	 * @return void
	 */
	public function delete_central_file( int $attachment_id ): void {
		// bail if this is a deletion by the system.
		if ( $this->is_system_deletion() ) {
			return;
		}

		// bail if this website does not use a central media library.
		if ( ! Media_Library::get_instance()->is_using_central() ) {
			return;
		}

		// bail for own uploads: their export deletes them in the central media library.
		if ( Media_Library::get_instance()->is_own_upload( $attachment_id ) ) {
			return;
		}

		// bail if the website does not have the right.
		if ( true !== $this->may_delete( $attachment_id ) ) {
			return;
		}

		// get the file in the central media library.
		$central_id = Media_Library::get_instance()->get_central_id( $attachment_id );

		// bail if it is unknown.
		if ( 0 === $central_id ) {
			return;
		}

		// do not delete this copy again during the propagation of the deletion.
		Propagation::get_instance()->mark_as_deleting( $attachment_id );

		// delete the file in the central media library.
		switch_to_blog( Media_Library::get_instance()->get_central_site_id() );
		if ( 'attachment' === get_post_type( $central_id ) ) {
			wp_delete_attachment( $central_id, true );
		}
		restore_current_blog();
	}

	/**
	 * Remove the action to delete a file, if it is not allowed, and mark a deletion in the whole network.
	 *
	 * @param array<string,string> $actions The actions.
	 * @param WP_Post              $post    The attachment.
	 *
	 * @return array<string,string>
	 */
	public function change_row_actions( array $actions, WP_Post $post ): array {
		// bail if no deletion is offered.
		if ( ! isset( $actions['delete'] ) ) {
			return $actions;
		}

		// remove the deletion, if it is not allowed.
		if ( false === $this->may_delete( $post->ID ) ) {
			unset( $actions['delete'] );
			return $actions;
		}

		// mark a deletion in the whole network for the confirmation.
		if ( $this->is_network_deletion( $post->ID ) ) {
			$actions['delete'] = (string) preg_replace( '/^<a /', '<a data-efml-network-delete="' . absint( $post->ID ) . '" ', $actions['delete'] );
		}

		// return the actions.
		return $actions;
	}

	/**
	 * Remove the deletion of a file in the media library, if it is not allowed, and mark a deletion in the whole
	 * network for the confirmation.
	 *
	 * @param array<string,mixed> $response   The data of the attachment.
	 * @param WP_Post             $attachment The attachment.
	 *
	 * @return array<string,mixed>
	 */
	public function change_attachment_for_js( array $response, WP_Post $attachment ): array {
		// bail if no central media library is used.
		if ( ! Media_Library::get_instance()->is_active() ) {
			return $response;
		}

		// remove the deletion, if it is not allowed (without nonce WordPress does not show the button).
		if ( false === $this->may_delete( $attachment->ID ) ) {
			if ( isset( $response['nonces'] ) && is_array( $response['nonces'] ) ) {
				unset( $response['nonces']['delete'] );
			}
			return $response;
		}

		// mark a deletion in the whole network.
		$response['efmlNetworkDelete'] = $this->is_network_deletion( $attachment->ID );

		// return the data.
		return $response;
	}

	/**
	 * Add the script for the confirmation of deletions in the whole network and for the usage of files.
	 *
	 * @param string $hook The actual page (only for "admin_enqueue_scripts").
	 *
	 * @return void
	 */
	public function add_script( string $hook = '' ): void {
		// bail on other pages than the media library, the edit page of a file, the form for the availability of
		// multiple files and pages with the media dialog.
		if ( 'admin_enqueue_scripts' === current_action() && ! in_array( $hook, array( 'upload.php', 'post.php', Visibility::get_instance()->get_page_hook() ), true ) ) {
			return;
		}

		// bail if no central media library is used.
		if ( ! Media_Library::get_instance()->is_active() ) {
			return;
		}

		// bail if already added.
		if ( wp_script_is( 'efml-network', 'enqueued' ) ) {
			return;
		}

		// get the actual attachment on its edit page.
		$attachment_id = 0;
		if ( 'post.php' === $hook ) {
			$attachment_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! $this->is_network_deletion( $attachment_id ) ) {
				$attachment_id = 0;
			}
		}

		// add the script.
		wp_enqueue_script(
			'efml-network',
			Helper::get_plugin_url() . 'admin/network.js',
			array( 'jquery' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/network.js' ),
			true
		);

		// add the variables.
		wp_localize_script(
			'efml-network',
			'efmlNetworkVars',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'efml-network' ),
				'attachment_id' => $attachment_id,
				'confirm'       => __( 'This file is deleted in all websites of the network. Do you really want to delete it?', 'external-files-in-media-library' ),
				'loading'       => __( 'Loading …', 'external-files-in-media-library' ),
				'error'         => __( 'The usage could not be loaded.', 'external-files-in-media-library' ),
				'error_delete'  => __( 'An error occurred while deleting the file.', 'external-files-in-media-library' ),
				'saved'         => __( 'The availability has been saved.', 'external-files-in-media-library' ),
				'error_save'    => __( 'The availability could not be saved.', 'external-files-in-media-library' ),
			)
		);
	}
}
