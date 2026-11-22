<?php
/**
 * This file contains the move of a central media library to another website.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Error;
use WP_Post;

/**
 * Object to move the central media library of a group to another website.
 *
 * Runs in the background in three phases:
 * 1. copy: the files are added to the new website (its copies of them become its own files). The old central media
 *    library stays the central one, its files are not changed.
 * 2. switch: the new website becomes the central media library of the group. The other websites export new uploads
 *    to it.
 * 3. sites: the copies in the websites use the files of the new central media library. Their attachments stay, so
 *    their usage in contents is kept.
 *
 * The files in the former central media library are kept. It does not belong to the group afterward.
 */
class Move {
	/**
	 * Name of the site option with the state of the running move.
	 */
	public const OPTION = 'efml_network_move';

	/**
	 * Name of the cron event, which continues the move.
	 */
	public const CRON = 'efml_network_move';

	/**
	 * Meta key on a file of the new central media library: its ID in the former central media library.
	 */
	public const META_MOVED_FROM = 'efml_network_moved_from';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Move
	 */
	private static ?Move $instance = null;

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
	 * @return Move
	 */
	public static function get_instance(): Move {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::CRON, array( $this, 'run_via_cron' ) );
	}

	/**
	 * Return the state of the running move (empty if none is running).
	 *
	 * @return array{group: int, from: int, to: int, phase: string, after: int, site: int, sites: array<int,int>}|array{}
	 */
	public function get_state(): array {
		$state = get_site_option( self::OPTION, array() );
		if ( ! is_array( $state ) || empty( $state['phase'] ) ) {
			return array();
		}
		return array(
			'group' => absint( $state['group'] ?? 0 ),
			'from'  => absint( $state['from'] ?? 0 ),
			'to'    => absint( $state['to'] ?? 0 ),
			'phase' => (string) $state['phase'],
			'after' => absint( $state['after'] ?? 0 ),
			'site'  => absint( $state['site'] ?? 0 ),
			'sites' => array_values( array_map( 'absint', (array) ( $state['sites'] ?? array() ) ) ),
		);
	}

	/**
	 * Return whether a move is running.
	 *
	 * @return bool
	 */
	public function is_running(): bool {
		return ! empty( $this->get_state() );
	}

	/**
	 * Start the move of the central media library of the given group to the given website.
	 *
	 * @param int $group_id The group.
	 * @param int $to       The website, which becomes the central media library.
	 *
	 * @return true|WP_Error
	 */
	public function start( int $group_id, int $to ): true|WP_Error {
		$media_library = Media_Library::get_instance();
		$groups        = $media_library->get_groups();

		// bail if a move is running.
		if ( $this->is_running() ) {
			return new WP_Error( 'efml_network_move_running', __( 'A central media library is moved at the moment. Wait until it is complete.', 'external-files-in-media-library' ) );
		}

		// bail if the group does not exist.
		if ( ! isset( $groups[ $group_id ] ) ) {
			return new WP_Error( 'efml_network_unknown_group', __( 'The group does not exist.', 'external-files-in-media-library' ) );
		}

		// bail if the website does not exist, is the actual central media library or belongs to another group.
		$from = $groups[ $group_id ]['central'];
		if ( ! get_site( $to ) || $to === $from || ! in_array( $media_library->get_group_id( $to ), array( 0, $group_id ), true ) ) {
			return new WP_Error( 'efml_network_move_target', __( 'Choose a website of the group or a website without group.', 'external-files-in-media-library' ) );
		}

		// stop the export and the synchronization of the new website with the actual central media library: it gets
		// the files directly.
		if ( $media_library->get_group_id( $to ) === $group_id ) {
			switch_to_blog( $to );
			$term_id = Membership::get_instance()->get_term_id( $from );
			if ( $term_id > 0 ) {
				Export::get_instance()->set_state_for_term( $term_id, 0 );
				Synchronization::get_instance()->set_state( $term_id, 0 );
			}
			update_option( 'eml_export', 0 );
			restore_current_blog();
		}

		// save the state.
		update_site_option(
			self::OPTION,
			array(
				'group' => $group_id,
				'from'  => $from,
				'to'    => $to,
				'phase' => 'copy',
				'after' => 0,
				'site'  => 0,
				'sites' => array_values( array_diff( $groups[ $group_id ]['sites'], array( $to ) ) ),
			)
		);

		// log this event.
		/* translators: %1$s and %2$s will be replaced by URLs. */
		Log::get_instance()->create( sprintf( __( 'The central media library of %1$s is moved to %2$s.', 'external-files-in-media-library' ), (string) get_blogaddress_by_id( $from ), (string) get_blogaddress_by_id( $to ) ), '', 'info', 1 );

		// continue in the background.
		wp_schedule_single_event( time(), self::CRON );
		return true;
	}

	/**
	 * Continue the move via cron.
	 *
	 * @return void
	 */
	public function run_via_cron(): void {
		$this->run();
	}

	/**
	 * Continue the move. Returns true if it is complete.
	 *
	 * @param int $time_limit The time in seconds, which could be used.
	 *
	 * @return bool
	 */
	public function run( int $time_limit = 20 ): bool {
		// get the start time.
		$start = time();

		// handle the phases until the time is over.
		while ( time() - $start < $time_limit ) {
			// get the state.
			$state = $this->get_state();

			// bail if no move is running.
			if ( empty( $state ) ) {
				return true;
			}

			// handle the phase.
			if ( 'copy' === $state['phase'] ) {
				$state = $this->run_copy( $state );
			} elseif ( 'switch' === $state['phase'] ) {
				$state = $this->run_switch( $state );
			} elseif ( 'sites' === $state['phase'] ) {
				$state = $this->run_sites( $state );
			} else {
				$state['phase'] = 'done';
			}

			// end the move.
			if ( 'done' === $state['phase'] ) {
				delete_site_option( self::OPTION );

				/* translators: %1$s will be replaced by a URL. */
				Log::get_instance()->create( sprintf( __( 'The central media library has been moved to %1$s.', 'external-files-in-media-library' ), (string) get_blogaddress_by_id( $state['to'] ) ), '', 'info', 1 );
				return true;
			}

			// save the state.
			update_site_option( self::OPTION, $state );
		}

		// continue via cron.
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + 10, self::CRON );
		}
		return false;
	}

	/**
	 * Add the next files of the former central media library to the new one.
	 *
	 * @param array{group: int, from: int, to: int, phase: string, after: int, site: int, sites: array<int,int>} $state The state.
	 *
	 * @return array{group: int, from: int, to: int, phase: string, after: int, site: int, sites: array<int,int>}
	 */
	private function run_copy( array $state ): array {
		global $wpdb;

		// get the next files of the former central media library.
		switch_to_blog( $state['from'] );
		$ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( 'SELECT `ID` FROM %i WHERE `post_type` = %s AND `post_status` = %s AND `ID` > %d ORDER BY `ID` ASC LIMIT 10', array( $wpdb->posts, 'attachment', 'inherit', $state['after'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		restore_current_blog();

		// switch the central media library, if all files have been added.
		if ( empty( $ids ) ) {
			$state['phase'] = 'switch';
			return $state;
		}

		// add each.
		foreach ( $ids as $attachment_id ) {
			$this->transfer( $state['from'], $state['to'], $attachment_id );
			$state['after'] = $attachment_id;
		}

		// return the state.
		return $state;
	}

	/**
	 * Return the ID of the file in the new central media library, which has been added for the given file of the
	 * former one (0 if none exists).
	 *
	 * Must be called in the new central media library.
	 *
	 * @param int $from_id The attachment ID in the former central media library.
	 *
	 * @return int
	 */
	public function get_moved_id( int $from_id ): int {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT m.`post_id` FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` = %s AND m.`meta_value` = %s LIMIT 1', array( $wpdb->postmeta, $wpdb->posts, 'attachment', self::META_MOVED_FROM, (string) $from_id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Add the given file of the former central media library to the new one and return its ID there (0 on error).
	 *
	 * A copy of the file in the new central media library becomes its own file, so its usage in contents is kept.
	 *
	 * @param int $from    The former central media library.
	 * @param int $to      The new central media library.
	 * @param int $from_id The attachment ID in the former central media library.
	 *
	 * @return int
	 */
	public function transfer( int $from, int $to, int $from_id ): int {
		$media_library = Media_Library::get_instance();

		// get the data of the file in the former central media library.
		switch_to_blog( $from );
		$post       = get_post( $from_id );
		$path       = (string) get_attached_file( $from_id );
		$urls       = array( $media_library->get_file_url( $from_id ), (string) wp_get_attachment_url( $from_id ) );
		$values     = Metadata::get_instance()->get_values( $from_id );
		$origin     = $media_library->get_origin_blog( $from_id );
		$origin_id  = absint( get_post_meta( $from_id, Media_Library::META_ORIGIN_ATTACHMENT, true ) );
		$visibility = get_post_meta( $from_id, Media_Library::META_VISIBILITY, true );
		restore_current_blog();

		// bail if the file does not exist.
		if ( ! $post instanceof WP_Post || ! is_file( $path ) ) {
			return 0;
		}

		// switch to the new central media library.
		switch_to_blog( $to );

		// bail if the file has already been added.
		$new_id = $this->get_moved_id( $from_id );
		if ( $new_id > 0 ) {
			restore_current_blog();
			return $new_id;
		}

		// use a copy of the file, which exists already in the new central media library.
		foreach ( $media_library->find_copies( $from_id, $urls, $origin === $to ? $to : 0 ) as $copy_id ) {
			if ( Membership::get_instance()->make_local( $copy_id ) ) {
				$new_id = $copy_id;
				break;
			}
		}

		// otherwise add the file.
		if ( 0 === $new_id ) {
			$new_id = $this->add_file( $path, $post );
		}

		// bail on error.
		if ( 0 === $new_id ) {
			restore_current_blog();
			Log::get_instance()->create( __( 'The file could not be added to the new central media library.', 'external-files-in-media-library' ), $urls[0], 'error' );
			return 0;
		}

		// save the reference to the former file, its origin and its availability.
		update_post_meta( $new_id, self::META_MOVED_FROM, $from_id );
		if ( $origin > 0 && $origin !== $to ) {
			update_post_meta( $new_id, Media_Library::META_ORIGIN_BLOG, $origin );
			update_post_meta( $new_id, Media_Library::META_ORIGIN_ATTACHMENT, $origin_id );
		}
		if ( is_array( $visibility ) ) {
			update_post_meta( $new_id, Media_Library::META_VISIBILITY, $visibility );
		}

		// use caption, description and alternative text of the former file.
		Metadata::get_instance()->apply( $new_id, $values );

		// save hash and size.
		$media_library->update_file_info( $new_id );

		// switch back.
		restore_current_blog();

		// return the ID.
		return $new_id;
	}

	/**
	 * Add the given file to the media library of the actual website and return its attachment ID (0 on error).
	 *
	 * @param string  $path The path of the file.
	 * @param WP_Post $post The attachment in the former central media library.
	 *
	 * @return int
	 */
	private function add_file( string $path, WP_Post $post ): int {
		// copy the file to a temporary file, as the upload moves it.
		$tmp_file = wp_tempnam( basename( $path ) );
		if ( ! copy( $path, $tmp_file ) ) {
			wp_delete_file( $tmp_file );
			return 0;
		}

		// add it without exporting it.
		$export_obj = Export::get_instance();
		$export_obj->prevent_export_checks_on_local_switch();
		try {
			$attachment_id = media_handle_sideload(
				array(
					'name'     => basename( $path ),
					'tmp_name' => $tmp_file,
				),
				0,
				null,
				array(
					'post_title'  => $post->post_title,
					'post_date'   => $post->post_date,
					'post_author' => Helper::get_current_user_id(),
				)
			);
		} finally {
			$export_obj->allow_export_checks_after_local_switch();
		}

		// remove the temporary file on error.
		if ( ! is_int( $attachment_id ) ) {
			wp_delete_file( $tmp_file );
			return 0;
		}

		// return the ID.
		return $attachment_id;
	}

	/**
	 * Use the new website as central media library of the group and connect the websites with it.
	 *
	 * @param array{group: int, from: int, to: int, phase: string, after: int, site: int, sites: array<int,int>} $state The state.
	 *
	 * @return array{group: int, from: int, to: int, phase: string, after: int, site: int, sites: array<int,int>}
	 */
	private function run_switch( array $state ): array {
		// add the files, which have been added since the last run. Switch only, if there are none.
		$state['phase'] = 'copy';
		$state          = $this->run_copy( $state );
		if ( 'switch' !== $state['phase'] ) {
			$state['phase'] = 'switch';
			return $state;
		}

		// use the new central media library in the group. The former one does not belong to it anymore.
		$groups = Media_Library::get_instance()->get_groups();
		if ( isset( $groups[ $state['group'] ] ) ) {
			$groups[ $state['group'] ] = array(
				'name'    => $groups[ $state['group'] ]['name'],
				'central' => $state['to'],
				'sites'   => array_values( array_diff( $groups[ $state['group'] ]['sites'], array( $state['to'], $state['from'] ) ) ),
			);
			Media_Library::get_instance()->set_groups( $groups );
		}

		// the users of the new central media library get the options of this plugin again.
		switch_to_blog( $state['to'] );
		\ExternalFilesInMediaLibrary\Plugin\Roles::get_instance()->set_capabilities( get_option( 'eml_allowed_roles' ) );
		restore_current_blog();

		// connect the websites with the new central media library: new uploads are exported to it at once, the
		// synchronization waits until the copies use the new files.
		foreach ( $state['sites'] as $blog_id ) {
			switch_to_blog( $blog_id );
			$old_term = Membership::get_instance()->get_term_id( $state['from'] );
			if ( $old_term > 0 ) {
				Export::get_instance()->set_state_for_term( $old_term, 0 );
				Synchronization::get_instance()->set_state( $old_term, 0 );
			}
			Membership::get_instance()->connect_site( $state['to'] );
			$new_term = Membership::get_instance()->get_term_id( $state['to'] );
			if ( $new_term > 0 ) {
				Synchronization::get_instance()->set_state( $new_term, 0 );
			}
			restore_current_blog();
		}

		// continue with the websites.
		$state['phase'] = 'sites';
		$state['site']  = 0;
		$state['after'] = 0;
		return $state;
	}

	/**
	 * Use the files of the new central media library for the next copies in the actual website of the move.
	 *
	 * @param array{group: int, from: int, to: int, phase: string, after: int, site: int, sites: array<int,int>} $state The state.
	 *
	 * @return array{group: int, from: int, to: int, phase: string, after: int, site: int, sites: array<int,int>}
	 */
	private function run_sites( array $state ): array {
		global $wpdb;

		// end the move, if all websites have been handled.
		if ( ! isset( $state['sites'][ $state['site'] ] ) ) {
			$state['phase'] = 'done';
			return $state;
		}

		// get the website.
		$blog_id = $state['sites'][ $state['site'] ];
		switch_to_blog( $blog_id );

		// get the next files of the central media library in this website.
		$ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT m.`post_id` FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` IN (%s, %s) AND m.`post_id` > %d ORDER BY m.`post_id` ASC LIMIT 50', array( $wpdb->postmeta, $wpdb->posts, 'attachment', Media_Library::META_CENTRAL_ID, 'efml_multisite_attachment_id', $state['after'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// finish this website, if all files have been handled.
		if ( empty( $ids ) ) {
			$this->finish_site( $state['from'], $state['to'] );
			restore_current_blog();
			++$state['site'];
			$state['after'] = 0;
			return $state;
		}

		// use the files of the new central media library.
		$new_term = Membership::get_instance()->get_term_id( $state['to'] );
		foreach ( $ids as $attachment_id ) {
			$state['after'] = $attachment_id;

			// bail if this is not a file of the former central media library.
			$external_file_obj = Files::get_instance()->get_file( $attachment_id );
			if ( ! $external_file_obj->is_valid() || Membership::get_instance()->get_central_of_url( $external_file_obj->get_url( true ), $state['from'] ) !== $state['from'] ) {
				continue;
			}

			// get the ID in the former central media library.
			$old_id = absint( get_post_meta( $attachment_id, Media_Library::META_CENTRAL_ID, true ) );
			if ( 0 === $old_id ) {
				$old_id = absint( get_post_meta( $attachment_id, 'efml_multisite_attachment_id', true ) );
			}

			// get the file in the new central media library.
			switch_to_blog( $state['to'] );
			$new_id  = $this->get_moved_id( $old_id );
			$new_url = $new_id > 0 ? Media_Library::get_instance()->get_file_url( $new_id ) : '';
			restore_current_blog();

			// bail if it does not exist: the copy keeps the former file, which is kept.
			if ( 0 === $new_id ) {
				continue;
			}

			// use it.
			Media_Library::get_instance()->repoint_copy( $attachment_id, $old_id, $new_id, $new_url );
			if ( $new_term > 0 ) {
				wp_set_object_terms( $attachment_id, $new_term, Taxonomy::get_instance()->get_name() );
			}
		}

		// switch back.
		restore_current_blog();

		// return the state.
		return $state;
	}

	/**
	 * Finish the move in the actual website: start the synchronization with the new central media library and remove
	 * the external source of the former one, if no file uses it anymore.
	 *
	 * @param int $from The former central media library.
	 * @param int $to   The new central media library.
	 *
	 * @return void
	 */
	private function finish_site( int $from, int $to ): void {
		// start the synchronization.
		$new_term = Membership::get_instance()->get_term_id( $to );
		if ( $new_term > 0 ) {
			Synchronization::get_instance()->set_state( $new_term, 1 );
		}

		// remove the external source of the former central media library, if no file uses it anymore.
		$old_term = Membership::get_instance()->get_term_id( $from );
		if ( $old_term > 0 ) {
			$files = get_objects_in_term( $old_term, Taxonomy::get_instance()->get_name() );
			if ( is_array( $files ) && empty( $files ) ) {
				Taxonomy::get_instance()->delete( $old_term );
			}
		}
	}
}
