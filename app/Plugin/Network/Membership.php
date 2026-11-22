<?php
/**
 * File to handle the membership of the websites in the groups of the network.
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
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Roles;
use WP_Query;
use WP_Role;
use WP_Site;

/**
 * Object, which connects the websites with the central media library of their group and separates them again.
 *
 * - A website, which joins a group, gets the central media library as target for its uploads and as external source.
 * - A website, which leaves a group, keeps its own uploads and the files used in its contents as local files (or
 *   loses them, if configured), then the connection is removed. This runs in the background.
 * - A new website joins the group configured for new websites.
 * - The uploads of a deleted website stay in the central media library (or are deleted, if configured).
 */
class Membership {
	/**
	 * Name of the cron event, which separates a website from its central media library.
	 */
	public const CRON_LEAVE = 'efml_network_leave';

	/**
	 * Name of the cron event, which continues the handling of the uploads of a deleted website.
	 */
	public const CRON_DELETED_SITE = 'efml_network_deleted_site';

	/**
	 * Option in a website, which leaves its group: the website of its former central media library.
	 */
	public const OPTION_LEAVING = 'efml_network_leaving';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Membership
	 */
	private static ?Membership $instance = null;

	/**
	 * Marker, whether a file of a central media library is saved as local file.
	 *
	 * @var bool
	 */
	private bool $making_local = false;

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
	 * @return Membership
	 */
	public static function get_instance(): Membership {
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
		// new and deleted websites.
		add_action( 'wp_initialize_site', array( $this, 'add_new_site' ), 100 );
		add_action( 'wp_delete_site', array( $this, 'handle_deleted_site' ) );

		// the background tasks.
		add_action( self::CRON_LEAVE, array( $this, 'run_leave_via_cron' ) );
		add_action( self::CRON_DELETED_SITE, array( $this, 'run_deleted_site_via_cron' ), 10, 3 );
	}

	/**
	 * Continue the separation of a website from its group via cron.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return void
	 */
	public function run_leave_via_cron( int $blog_id ): void {
		$this->run_leave( $blog_id );
	}

	/**
	 * Continue the handling of the uploads of a deleted website via cron.
	 *
	 * @param int    $central The website with the central media library.
	 * @param int    $blog_id The deleted website.
	 * @param string $mode    "keep" or "delete".
	 *
	 * @return void
	 */
	public function run_deleted_site_via_cron( int $central, int $blog_id, string $mode ): void {
		$this->run_deleted_site( $central, $blog_id, $mode );
	}

	/**
	 * Return the given groups with the given website in the given group (0 for none).
	 *
	 * @param array<int,array{name: string, central: int, sites: array<int,int>}> $groups   The groups.
	 * @param int                                                                 $blog_id  The website.
	 * @param int                                                                 $group_id The group (0 for none).
	 *
	 * @return array<int,array{name: string, central: int, sites: array<int,int>}>
	 */
	public function get_groups_with_site( array $groups, int $blog_id, int $group_id ): array {
		$new_groups = array();
		foreach ( $groups as $id => $group ) {
			$sites = array_values( array_diff( $group['sites'], array( $blog_id ) ) );
			if ( $id === $group_id ) {
				$sites[] = $blog_id;
			}
			$new_groups[ $id ] = array(
				'name'    => $group['name'],
				'central' => $group['central'],
				'sites'   => $sites,
			);
		}
		return $new_groups;
	}

	/**
	 * Return whether a file of a central media library is saved as local file in a website, which leaves its group.
	 *
	 * @return bool
	 */
	public function is_making_local(): bool {
		return $this->making_local;
	}

	/**
	 * Return whether the options of the plugin should be hidden in the websites, which use a central media library.
	 *
	 * @return bool
	 */
	private function is_hide_options(): bool {
		return 1 === absint( get_site_option( 'efml_hide_options', 0 ) );
	}

	/**
	 * Connect the actual website with the given central media library.
	 *
	 * Adds the central media library as target for the export and as source for the synchronization.
	 *
	 * @param int $central The website with the central media library.
	 *
	 * @return void
	 */
	public function connect_site( int $central ): void {
		// get the URL of the chosen site.
		$url = get_blogaddress_by_id( $central );

		// prepare the fields for export configuration.
		$fields = array(
			'website' => array(
				'value' => $central,
			),
		);

		// forget a running separation from this central media library (the website joins it again). A separation from
		// another central media library continues.
		if ( absint( get_option( self::OPTION_LEAVING, 0 ) ) === $central ) {
			delete_option( self::OPTION_LEAVING );
		}

		// enable the export tool.
		update_option( 'eml_export', 1 );

		// add the entry for the sync and export.
		$term_id = Taxonomy::get_instance()->add( 'multisite', $url, $fields );

		// bail if term_id could not be loaded.
		if ( 0 === $term_id ) {
			return;
		}

		// set the URL on the export.
		update_term_meta( $term_id, 'efml_export_url', $url );

		// set this as default export target in this site.
		Export::get_instance()->set_state_for_term( $term_id, 1 );

		// synchronize only daily, as new, changed and deleted files are passed to the websites immediately.
		if ( empty( get_term_meta( $term_id, 'interval', true ) ) ) {
			update_term_meta( $term_id, 'interval', '24hourly' );
		}

		// enable sync in this site.
		update_option( 'eml_sync', 1 );

		// enable the sync for this term.
		Synchronization::get_instance()->set_state( $term_id, 1 );

		// remove the capability 'eml_manage_files' from each role if this is requested.
		if ( $this->is_hide_options() ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				// get the role-object.
				$role_obj = get_role( $slug );

				// bail if role object could not be loaded.
				if ( ! $role_obj instanceof WP_Role ) {
					continue;
				}

				// remove capability.
				$role_obj->remove_cap( EFML_CAP_NAME );
			}
		} else {
			// reset the capability for 'eml_manage_files' in this blog.
			Roles::get_instance()->set_capabilities( get_option( 'eml_allowed_roles' ) );
		}
	}

	/**
	 * Return the external source of the given central media library in the actual website (0 if it does not exist).
	 *
	 * @param int $central The website with the central media library.
	 *
	 * @return int
	 */
	public function get_term_id( int $central ): int {
		$term = get_term_by( 'slug', md5( 'multisite' . get_blogaddress_by_id( $central ) ), Taxonomy::get_instance()->get_name() );
		return $term instanceof \WP_Term ? $term->term_id : 0;
	}

	/**
	 * Start the separation of the actual website from the given central media library.
	 *
	 * The export and the synchronization stop at once. The files are handled in the background.
	 *
	 * @param int $central The website with the former central media library.
	 *
	 * @return void
	 */
	public function leave_site( int $central ): void {
		// get the external source of the central media library.
		$term_id = $this->get_term_id( $central );

		// stop the export and the synchronization.
		if ( $term_id > 0 ) {
			Export::get_instance()->set_state_for_term( $term_id, 0 );
			Synchronization::get_instance()->set_state( $term_id, 0 );
		}
		update_option( 'eml_export', 0 );

		// reset the capability for 'eml_manage_files' in this blog.
		Roles::get_instance()->set_capabilities( get_option( 'eml_allowed_roles' ) );

		// mark the separation and handle the files in the background.
		update_option( self::OPTION_LEAVING, $central, false );
		wp_schedule_single_event( time(), self::CRON_LEAVE, array( get_current_blog_id() ) );
	}

	/**
	 * Handle the files of a website, which leaves its group, and remove the connection at the end.
	 *
	 * Runs via cron in parts. Own uploads and files used in contents become local files (if configured), the other
	 * files of the central media library are removed from the website. The files in the central media library are not
	 * changed, the own uploads belong to the central media library afterward.
	 *
	 * @param int $blog_id    The website.
	 * @param int $time_limit The time in seconds this run could use.
	 *
	 * @return bool True if the separation is complete.
	 */
	public function run_leave( int $blog_id, int $time_limit = 20 ): bool {
		// get the start time.
		$start = time();

		// switch to the website.
		switch_to_blog( $blog_id );

		// get the former central media library.
		$central = absint( get_option( self::OPTION_LEAVING, 0 ) );

		// bail if the website does not leave a group.
		if ( 0 === $central ) {
			restore_current_blog();
			return true;
		}

		// handle the files.
		$keep     = 'local' === Media_Library::get_instance()->get_setting( 'leave' );
		$complete = true;
		foreach ( $this->get_files_of_central( $central ) as $attachment_id ) {
			// continue via cron if the time is over.
			if ( time() - $start >= $time_limit ) {
				$complete = false;
				break;
			}

			// get the file in the central media library and whether it is an own upload.
			$central_id = $this->get_central_id_of( $attachment_id, $central );
			$own        = absint( get_post_meta( $attachment_id, 'efml_multisite_attachment_id', true ) ) > 0 && ! Media_Library::get_instance()->is_linked( $attachment_id );

			// keep own uploads and used files as local files. If this fails, they are kept as they are (the file in the
			// central media library is not deleted), so they are not lost.
			if ( $keep && ( $own || Usage::get_instance()->get_post_count( $attachment_id ) > 0 ) ) {
				if ( ! $this->make_local( $attachment_id ) ) {
					continue;
				}
				// the own upload belongs to the central media library afterward.
				if ( $own && $central_id > 0 ) {
					switch_to_blog( $central );
					delete_post_meta( $central_id, Media_Library::META_ORIGIN_BLOG );
					delete_post_meta( $central_id, Media_Library::META_ORIGIN_ATTACHMENT );
					restore_current_blog();
				}
				continue;
			}

			// remove the file from this website (not in the central media library).
			Propagation::get_instance()->delete_copy( $attachment_id );
		}

		// end the separation, if all files are handled: remove the external source.
		if ( $complete ) {
			$term_id = $this->get_term_id( $central );
			if ( $term_id > 0 ) {
				Taxonomy::get_instance()->delete( $term_id );
			}
			delete_option( self::OPTION_LEAVING );

			// log this event.
			/* translators: %1$s will be replaced by a URL. */
			Log::get_instance()->create( sprintf( __( 'This website does not use the central media library of %1$s anymore.', 'external-files-in-media-library' ), (string) get_blogaddress_by_id( $central ) ), '', 'info', 1 );
		}

		// switch back.
		restore_current_blog();

		// continue via cron, if not complete.
		if ( ! $complete ) {
			wp_schedule_single_event( time() + 10, self::CRON_LEAVE, array( $blog_id ) );
		}

		// return whether it is complete.
		return $complete;
	}

	/**
	 * Return the central media library, to which the given URL belongs (0 if none).
	 *
	 * The address of a website could be the beginning of the address of another one (e.g. in a network with
	 * directories), so the longest matching address is used.
	 *
	 * @param string $url     The URL.
	 * @param int    $central A central media library to check in addition to the actual ones (e.g. a former one).
	 *
	 * @return int
	 */
	public function get_central_of_url( string $url, int $central ): int {
		$found  = 0;
		$length = 0;
		foreach ( array_unique( array_merge( array( $central ), array_column( Media_Library::get_instance()->get_groups(), 'central' ) ) ) as $blog_id ) {
			$address = (string) get_blogaddress_by_id( absint( $blog_id ) );
			if ( strlen( $address ) > $length && str_starts_with( $url, $address ) ) {
				$found  = absint( $blog_id );
				$length = strlen( $address );
			}
		}
		return $found;
	}

	/**
	 * Return the files of the given central media library in the actual website.
	 *
	 * @param int $central The website with the central media library.
	 *
	 * @return array<int,int>
	 */
	private function get_files_of_central( int $central ): array {
		global $wpdb;

		// get the files with the ID of a central file or of the export of an own upload.
		$ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT m.`post_id` FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` IN (%s, %s)', array( $wpdb->postmeta, $wpdb->posts, 'attachment', Media_Library::META_CENTRAL_ID, 'efml_multisite_attachment_id' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// get the files of the external source without the ID (from older versions).
		$term_id = $this->get_term_id( $central );
		if ( $term_id > 0 ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => array( 'inherit', 'trash' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filter for term.
						array(
							'taxonomy' => Taxonomy::get_instance()->get_name(),
							'field'    => 'term_id',
							'terms'    => $term_id,
						),
					),
				)
			);
			foreach ( $query->get_posts() as $post ) {
				$ids[] = $post instanceof \WP_Post ? $post->ID : absint( $post );
			}
		}

		// return only external files of this central media library: files, which are already local, are handled, the
		// files of another central media library (after a change of the group) stay.
		$files = array();
		foreach ( array_unique( $ids ) as $attachment_id ) {
			$external_file_obj = Files::get_instance()->get_file( $attachment_id );
			if ( $external_file_obj->is_valid() && $this->get_central_of_url( $external_file_obj->get_url( true ), $central ) === $central ) {
				$files[] = $attachment_id;
			}
		}
		return $files;
	}

	/**
	 * Return the ID of the file in the given central media library for an attachment of the actual website.
	 *
	 * @param int $attachment_id The attachment ID in the actual website.
	 * @param int $central       The website with the central media library.
	 *
	 * @return int
	 */
	private function get_central_id_of( int $attachment_id, int $central ): int {
		// use the saved IDs.
		$central_id = absint( get_post_meta( $attachment_id, Media_Library::META_CENTRAL_ID, true ) );
		if ( 0 === $central_id ) {
			$central_id = absint( get_post_meta( $attachment_id, 'efml_multisite_attachment_id', true ) );
		}

		// bail if the central media library is not the one of the file.
		return str_starts_with( Files::get_instance()->get_file( $attachment_id )->get_url( true ), (string) get_blogaddress_by_id( $central ) ) ? $central_id : 0;
	}

	/**
	 * Save the given file of the central media library as local file in the actual website.
	 *
	 * The file is downloaded and saved like a normal upload, its connection to the central media library is removed.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function make_local( int $attachment_id ): bool {
		// get the external file.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if it is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return true;
		}

		// save it locally (this does not change the file in the central media library). Files of the central media
		// library could be saved locally only here.
		$this->making_local = true;
		try {
			$result = $external_file_obj->switch_to_local();
		} finally {
			$this->making_local = false;
		}

		// bail if it could not be saved.
		if ( ! $result ) {
			Log::get_instance()->create( __( 'The file of the central media library could not be saved as local file.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error' );
			return false;
		}

		// remove the connection to this plugin and the central media library: it is a normal local file now, so a
		// deletion does not delete it in the central media library.
		$external_file_obj->delete_cache();
		$external_file_obj->delete_thumbs();
		foreach ( array_unique( array_merge( Files::get_instance()->get_meta_keys(), array( Media_Library::META_CENTRAL_ID, Media_Library::META_OVERRIDES, 'efml_multisite_attachment_id', 'efml_former_export_sources', 'eml_synced', 'eml_synced_time' ) ) ) as $meta_key ) {
			delete_post_meta( $attachment_id, $meta_key );
		}
		wp_delete_object_term_relationships( $attachment_id, Taxonomy::get_instance()->get_name() );

		// return true as the file is local now.
		return true;
	}

	/**
	 * Apply changed groups: connect the websites with their new central media library and separate them from their
	 * former one.
	 *
	 * @param array<int,array{name: string, central: int, sites: array<int,int>}> $old_groups The former groups.
	 * @param array<int,array{name: string, central: int, sites: array<int,int>}> $new_groups The new groups.
	 *
	 * @return void
	 */
	public function apply_groups( array $old_groups, array $new_groups ): void {
		// bail if a central media library is moved at the moment: the groups are changed by the move.
		if ( Move::get_instance()->is_running() ) {
			Log::get_instance()->create( __( 'The groups could not be changed, as a central media library is moved at the moment.', 'external-files-in-media-library' ), '', 'error' );
			return;
		}

		// get the central media library of each website before and after.
		$old = $this->get_mapping( $old_groups );
		$new = $this->get_mapping( $new_groups );

		// save the new groups.
		Media_Library::get_instance()->set_groups( $new_groups );

		// save hash and size on the files of a new central media library in the background.
		$old_centrals = array_column( $old_groups, 'central' );
		foreach ( $new_groups as $group ) {
			if ( ! in_array( $group['central'], $old_centrals, true ) ) {
				delete_site_option( 'efml_network_file_info_migrated' );
				if ( ! wp_next_scheduled( Propagation::CRON_MIGRATE ) ) {
					wp_schedule_single_event( time() + 10, Propagation::CRON_MIGRATE );
				}
				break;
			}
		}

		// change each website, whose central media library has changed.
		foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $blog_id ) {
			$old_central = $old[ $blog_id ] ?? 0;
			$new_central = $new[ $blog_id ] ?? 0;

			// bail if nothing changed.
			if ( $old_central === $new_central ) {
				continue;
			}

			// bail if the website does not exist (anymore), e.g. as it has just been deleted.
			if ( ! get_site( $blog_id ) instanceof WP_Site ) {
				continue;
			}

			switch_to_blog( $blog_id );

			// leave the former central media library (a website, which becomes a central media library itself, too).
			if ( $old_central > 0 && $old_central !== $blog_id ) {
				$this->leave_site( $old_central );
			}

			// join the new one.
			if ( $new_central > 0 && $new_central !== $blog_id ) {
				$this->connect_site( $new_central );
			}

			restore_current_blog();
		}
	}

	/**
	 * Return the central media library of each website of the given groups (also of the central media libraries).
	 *
	 * @param array<int,array{name: string, central: int, sites: array<int,int>}> $groups The groups.
	 *
	 * @return array<int,int>
	 */
	private function get_mapping( array $groups ): array {
		$mapping = array();
		foreach ( $groups as $group ) {
			$mapping[ $group['central'] ] = $group['central'];
			foreach ( $group['sites'] as $blog_id ) {
				$mapping[ $blog_id ] = $group['central'];
			}
		}
		return $mapping;
	}

	/**
	 * Add a new website to the group configured for new websites.
	 *
	 * @param WP_Site $new_site The new website.
	 *
	 * @return void
	 */
	public function add_new_site( WP_Site $new_site ): void {
		// get the groups.
		$groups = Media_Library::get_instance()->get_groups();

		// bail if there are none.
		if ( empty( $groups ) ) {
			return;
		}

		// get the group for new websites.
		$group_id = absint( Media_Library::get_instance()->get_setting( 'new_sites' ) );
		if ( -1 === (int) Media_Library::get_instance()->get_setting( 'new_sites' ) ) {
			$group_id = absint( array_key_first( $groups ) );
		}

		// bail if new websites should not join a group or the group does not exist.
		if ( ! isset( $groups[ $group_id ] ) ) {
			return;
		}

		// add the website to the group.
		$this->apply_groups( $groups, $this->get_groups_with_site( $groups, absint( $new_site->blog_id ), $group_id ) );
	}

	/**
	 * Handle the uploads of a deleted website and remove it from its group.
	 *
	 * @param WP_Site $old_site The deleted website.
	 *
	 * @return void
	 */
	public function handle_deleted_site( WP_Site $old_site ): void {
		// get the website.
		$blog_id = absint( $old_site->blog_id );

		// get the groups.
		$groups = Media_Library::get_instance()->get_groups();

		// remove the website from the groups. A group without its central media library is removed.
		$central    = 0;
		$new_groups = array();
		foreach ( $groups as $group_id => $group ) {
			if ( $group['central'] === $blog_id ) {
				continue;
			}
			if ( in_array( $blog_id, $group['sites'], true ) ) {
				$central        = $group['central'];
				$group['sites'] = array_values( array_diff( $group['sites'], array( $blog_id ) ) );
			}
			$new_groups[ $group_id ] = $group;
		}

		// save them, if changed (the other websites of a removed group leave it).
		if ( $new_groups !== $groups ) {
			$this->apply_groups( $groups, $new_groups );
		}

		// handle the uploads of the website in its central media library.
		if ( $central > 0 ) {
			$this->run_deleted_site( $central, $blog_id, (string) Media_Library::get_instance()->get_setting( 'deleted_site' ) );
		}
	}

	/**
	 * Handle the uploads of a deleted website in its central media library: keep them (they belong to the central media
	 * library then) or delete them in the whole network.
	 *
	 * Runs in parts, continued via cron.
	 *
	 * @param int    $central    The website with the central media library.
	 * @param int    $blog_id    The deleted website.
	 * @param string $mode       "keep" or "delete".
	 * @param int    $time_limit The time in seconds this run could use.
	 *
	 * @return bool True if complete.
	 */
	public function run_deleted_site( int $central, int $blog_id, string $mode, int $time_limit = 20 ): bool {
		global $wpdb;

		// get the start time.
		$start = time();

		// switch to the central media library.
		switch_to_blog( $central );

		// get the uploads of the deleted website.
		$ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `meta_value` = %s', array( $wpdb->postmeta, Media_Library::META_ORIGIN_BLOG, (string) $blog_id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// handle each.
		$complete = true;
		foreach ( $ids as $attachment_id ) {
			// continue via cron if the time is over.
			if ( time() - $start >= $time_limit ) {
				$complete = false;
				break;
			}

			// delete it in the whole network.
			if ( 'delete' === $mode ) {
				wp_delete_attachment( $attachment_id, true );
				continue;
			}

			// keep it: it belongs to the central media library now.
			delete_post_meta( $attachment_id, Media_Library::META_ORIGIN_BLOG );
			delete_post_meta( $attachment_id, Media_Library::META_ORIGIN_ATTACHMENT );
		}

		// remove the deleted website from the availability of the files.
		if ( $complete ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s', array( $wpdb->postmeta, Media_Library::META_VISIBILITY ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			foreach ( (array) $rows as $row ) {
				$visibility = Media_Library::get_instance()->get_visibility( absint( $row['post_id'] ) );
				if ( in_array( $blog_id, $visibility['sites'], true ) ) {
					update_post_meta(
						absint( $row['post_id'] ),
						Media_Library::META_VISIBILITY,
						array(
							'mode'  => $visibility['mode'],
							'sites' => array_values( array_diff( $visibility['sites'], array( $blog_id ) ) ),
						)
					);
				}
			}
		}

		// switch back.
		restore_current_blog();

		// continue via cron, if not complete.
		if ( ! $complete ) {
			wp_schedule_single_event( time() + 10, self::CRON_DELETED_SITE, array( $central, $blog_id, $mode ) );
		}

		// return whether it is complete.
		return $complete;
	}
}
