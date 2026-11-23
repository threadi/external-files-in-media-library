<?php
/**
 * File to pass changes of the central media library to the websites of the network.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;
use WP_Query;

/**
 * Object, which passes changes of the central media library to the websites of the network.
 *
 * - A deleted file is deleted in every website (also the upload of the website, from which it comes).
 * - A new or changed file is added to or updated in every website, which could use it.
 * - A file, which a website could not use anymore, is deleted in this website, also if it is used there.
 *
 * Changes are collected during the request and passed at its end. What does not fit into the time limit is continued
 * via WP Cron. New files are always added via WP Cron, as their import loads the file.
 */
class Propagation {
	/**
	 * Name of the cron event to continue a deletion or update.
	 */
	public const CRON_PROPAGATE = 'efml_network_propagate';

	/**
	 * Name of the cron event to add a new file in websites.
	 */
	public const CRON_IMPORT = 'efml_network_import';

	/**
	 * Name of the cron event to continue the migration of the origins.
	 */
	public const CRON_MIGRATE = 'efml_network_migrate_origins';

	/**
	 * List of deleted files of the central media library in this request.
	 *
	 * @var array<int,array{id: int, central: int, urls: array<int,string>, origin: int, skip: array<int,string>}>
	 */
	private array $deletions = array();

	/**
	 * List of changed files of the central media libraries in this request: website of the central media library =>
	 * ID => whether the file itself changed.
	 *
	 * @var array<int,array<int,bool>>
	 */
	private array $updates = array();

	/**
	 * List of attachments, which are deleted at this moment ("<blog_id>:<attachment_id>").
	 *
	 * @var array<int,string>
	 */
	private array $deleting = array();

	/**
	 * Counter of running changes by this object in websites (to detect them in the hooks).
	 *
	 * @var int
	 */
	private int $running = 0;

	/**
	 * Marker whether the processing at the end of the request is registered.
	 *
	 * @var bool
	 */
	private bool $shutdown_registered = false;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Propagation
	 */
	private static ?Propagation $instance = null;

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
	 * @return Propagation
	 */
	public static function get_instance(): Propagation {
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
		// collect changes in the central media library.
		add_action( 'delete_attachment', array( $this, 'add_deletion' ), 20 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'add_file_change' ), 999, 2 );
		add_action( 'attachment_updated', array( $this, 'add_post_change' ), 20 );
		add_action( 'added_post_meta', array( $this, 'add_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'add_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'add_meta_change' ), 10, 3 );

		// continue via cron.
		add_action( self::CRON_PROPAGATE, array( $this, 'run_scheduled' ) );
		add_action( self::CRON_IMPORT, array( $this, 'run_import' ), 10, 2 );
		add_action( self::CRON_MIGRATE, array( $this, 'run_migration' ) );

		// handle files of the central media library in the websites.
		add_action( 'efml_after_file_save', array( $this, 'mark_copy' ), 10, 2 );
		add_filter( 'efml_file_import_attachment', array( $this, 'keep_former_title' ), 20, 3 );
		add_action( 'efml_sync_completed', array( $this, 'cleanup_after_sync' ), 10, 3 );
	}

	/**
	 * Return whether this object changes something in a website at this moment.
	 *
	 * @return bool
	 */
	public function is_running(): bool {
		return $this->running > 0;
	}

	/**
	 * Return whether the given attachment of the actual website is deleted at this moment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_deleting( int $attachment_id ): bool {
		return in_array( get_current_blog_id() . ':' . $attachment_id, $this->deleting, true );
	}

	/**
	 * Mark the given attachment of the actual website as deleted at this moment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function mark_as_deleting( int $attachment_id ): void {
		$this->deleting[] = get_current_blog_id() . ':' . $attachment_id;
	}

	/**
	 * Collect a deleted file of the central media library.
	 *
	 * The data of the file is saved now, as it does not exist anymore at the end of the request.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function add_deletion( int $attachment_id ): void {
		// bail if this is not the central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return;
		}

		// bail if this is not an attachment.
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		// collect the data.
		$this->deletions[] = array(
			'id'      => $attachment_id,
			'central' => get_current_blog_id(),
			'urls'    => array_values( array_unique( array_filter( array( Media_Library::get_instance()->get_file_url( $attachment_id ), (string) wp_get_attachment_url( $attachment_id ) ) ) ) ),
			'origin'  => Media_Library::get_instance()->get_origin_blog( $attachment_id ),
			'skip'    => $this->deleting,
		);

		// forget the changes of this file.
		unset( $this->updates[ get_current_blog_id() ][ $attachment_id ] );

		// pass it at the end of the request.
		$this->register_shutdown();
	}

	/**
	 * Collect a file of the central media library, whose file has been added or changed.
	 *
	 * @param array<string,mixed>|mixed $data          The metadata.
	 * @param int                       $attachment_id The attachment ID.
	 *
	 * @return array<string,mixed>|mixed
	 */
	public function add_file_change( mixed $data, int $attachment_id ): mixed {
		// collect it, if this is the central media library.
		if ( Media_Library::get_instance()->is_central() ) {
			$this->add_update( $attachment_id, true );
		}

		// return the metadata unchanged.
		return $data;
	}

	/**
	 * Collect a file of the central media library, whose data (caption, description) has been changed.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function add_post_change( int $attachment_id ): void {
		// collect it, if this is the central media library.
		if ( Media_Library::get_instance()->is_central() ) {
			$this->add_update( $attachment_id );
		}
	}

	/**
	 * Collect a file of the central media library, whose alternative text has been changed.
	 *
	 * @param int|array<int,int> $meta_id   The meta ID(s).
	 * @param int                $object_id The attachment ID.
	 * @param string             $meta_key  The meta key.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_meta_change( int|array $meta_id, int $object_id, string $meta_key ): void {
		// bail if this is not the alternative text.
		if ( '_wp_attachment_image_alt' !== $meta_key ) {
			return;
		}

		// collect it, if this is the central media library.
		if ( Media_Library::get_instance()->is_central() ) {
			$this->add_update( $object_id );
		}
	}

	/**
	 * Collect a changed file of the central media library.
	 *
	 * Must be called in the central media library (it is the actual website).
	 *
	 * @param int  $attachment_id The attachment ID in the central media library.
	 * @param bool $file_changed  True if the file itself has been changed (not only its data).
	 *
	 * @return void
	 */
	public function add_update( int $attachment_id, bool $file_changed = false ): void {
		// get the central media library.
		$central = get_current_blog_id();

		// bail if the file is deleted in this request.
		foreach ( $this->deletions as $deletion ) {
			if ( $deletion['id'] === $attachment_id && $deletion['central'] === $central ) {
				return;
			}
		}

		// collect it.
		$this->updates[ $central ][ $attachment_id ] = $file_changed || ( $this->updates[ $central ][ $attachment_id ] ?? false );

		// pass it at the end of the request.
		$this->register_shutdown();
	}

	/**
	 * Register the processing at the end of the request.
	 *
	 * @return void
	 */
	private function register_shutdown(): void {
		// bail if already registered.
		if ( $this->shutdown_registered ) {
			return;
		}

		// register it.
		add_action( 'shutdown', array( $this, 'process' ) );
		$this->shutdown_registered = true;
	}

	/**
	 * Return the time in seconds, which the passing of changes could use in a request.
	 *
	 * @return int
	 */
	private function get_time_limit(): int {
		$time_limit = 10;

		/**
		 * Filter the time in seconds, which the passing of changes of the central media library to the websites could
		 * use in a request. What does not fit is continued via WP Cron.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param int $time_limit The time in seconds.
		 */
		return absint( apply_filters( 'efml_network_time_limit', $time_limit ) );
	}

	/**
	 * Pass the collected changes to the websites.
	 *
	 * Runs at the end of the request. Called directly e.g. by tests.
	 *
	 * @return void
	 */
	public function process(): void {
		// get the start time.
		$start = time();

		// get the collected changes and reset the lists, so they are not processed twice.
		$deletions                 = $this->deletions;
		$updates                   = $this->updates;
		$this->deletions           = array();
		$this->updates             = array();
		$this->shutdown_registered = false;
		remove_action( 'shutdown', array( $this, 'process' ) );

		// pass the deletions.
		foreach ( $deletions as $deletion ) {
			$this->run_deletion( $deletion, 0, $start );
		}

		// pass the changes.
		foreach ( $updates as $central => $files ) {
			foreach ( $files as $attachment_id => $file_changed ) {
				$this->run_update( $central, $attachment_id, $file_changed, 0, $start );
			}
		}
	}

	/**
	 * Continue a deletion or update via cron.
	 *
	 * @param array<string,mixed> $task The task.
	 *
	 * @return void
	 */
	public function run_scheduled( array $task ): void {
		// get the start time.
		$start = time();

		// get the website, after which the task continues.
		$after = absint( $task['after'] ?? 0 );

		// run the deletion.
		if ( 'delete' === ( $task['type'] ?? '' ) && is_array( $task['data'] ?? null ) ) {
			$data = $task['data'];
			$this->run_deletion(
				array(
					'id'      => absint( $data['id'] ?? 0 ),
					'central' => absint( $data['central'] ?? 0 ),
					'urls'    => isset( $data['urls'] ) && is_array( $data['urls'] ) ? array_map( 'strval', $data['urls'] ) : array(),
					'origin'  => absint( $data['origin'] ?? 0 ),
					'skip'    => isset( $data['skip'] ) && is_array( $data['skip'] ) ? array_map( 'strval', $data['skip'] ) : array(),
				),
				$after,
				$start
			);
			return;
		}

		// run the update.
		if ( 'update' === ( $task['type'] ?? '' ) ) {
			$this->run_update( absint( $task['central'] ?? 0 ), absint( $task['id'] ?? 0 ), ! empty( $task['file'] ), $after, $start );
		}
	}

	/**
	 * Schedule the continuation of a task.
	 *
	 * @param array<string,mixed> $task The task.
	 *
	 * @return void
	 */
	private function schedule( array $task ): void {
		wp_schedule_single_event( time(), self::CRON_PROPAGATE, array( $task ) );
	}

	/**
	 * Delete the copies of a deleted file of the central media library in each website.
	 *
	 * @param array{id: int, central: int, urls: array<int,string>, origin: int, skip: array<int,string>} $deletion The data of the file.
	 * @param int                                                                                         $after    The website, after which the deletion starts.
	 * @param int                                                                                         $start    The start time of this run.
	 *
	 * @return void
	 */
	private function run_deletion( array $deletion, int $after, int $start ): void {
		// bail if no file is given.
		if ( 0 === $deletion['id'] || 0 === $deletion['central'] ) {
			return;
		}

		// check each website.
		foreach ( array_keys( Media_Library::get_instance()->get_sites( $deletion['central'] ) ) as $blog_id ) {
			// bail if this website has already been checked.
			if ( $blog_id <= $after ) {
				continue;
			}

			// continue via cron if the time is over.
			if ( time() - $start >= $this->get_time_limit() ) {
				$this->schedule(
					array(
						'type'  => 'delete',
						'data'  => $deletion,
						'after' => $after,
					)
				);
				return;
			}

			// delete the copies in this website.
			switch_to_blog( $blog_id );
			foreach ( Media_Library::get_instance()->find_copies( $deletion['id'], $deletion['urls'], $deletion['origin'] ) as $copy_id ) {
				// bail if this attachment is deleted at this moment (e.g. the deletion has been started there).
				if ( in_array( $blog_id . ':' . $copy_id, $deletion['skip'], true ) || $this->is_deleting( $copy_id ) ) {
					continue;
				}

				// delete it.
				$this->delete_copy( $copy_id );
			}
			restore_current_blog();

			// mark this website as checked.
			$after = $blog_id;
		}
	}

	/**
	 * Delete a copy of a file of the central media library in the actual website.
	 *
	 * The file in the central media library is not touched.
	 *
	 * @param int $attachment_id The attachment ID in the actual website.
	 *
	 * @return void
	 */
	public function delete_copy( int $attachment_id ): void {
		// get the URL for the log.
		$url = (string) wp_get_attachment_url( $attachment_id );

		// mark the deletion.
		++$this->running;
		$this->mark_as_deleting( $attachment_id );

		// delete the attachment.
		try {
			wp_delete_attachment( $attachment_id, true );
		} finally {
			--$this->running;
		}

		// log this event.
		Log::get_instance()->create( __( 'File has been deleted, as it is not available in the central media library for this website.', 'external-files-in-media-library' ), $url, 'info', 2 );
	}

	/**
	 * Pass a changed file of the central media library to each website.
	 *
	 * @param int  $central      The website of the central media library.
	 * @param int  $central_id   The attachment ID in the central media library.
	 * @param bool $file_changed True if the file itself has been changed.
	 * @param int  $after        The website, after which the update starts.
	 * @param int  $start        The start time of this run.
	 *
	 * @return void
	 */
	private function run_update( int $central, int $central_id, bool $file_changed, int $after, int $start ): void {
		// bail if the central media library is unknown.
		if ( 0 === $central ) {
			return;
		}

		// get the data of the file in the central media library.
		$file = $this->get_central_file_data( $central_id, $central );

		// bail if the file does not exist (anymore).
		if ( empty( $file ) ) {
			return;
		}

		// collect the websites, which should get the new file.
		$imports = array();

		// check each website.
		foreach ( array_keys( Media_Library::get_instance()->get_sites( $central ) ) as $blog_id ) {
			// bail if this website has already been checked.
			if ( $blog_id <= $after ) {
				continue;
			}

			// continue via cron if the time is over.
			if ( time() - $start >= $this->get_time_limit() ) {
				$this->schedule(
					array(
						'type'    => 'update',
						'central' => $central,
						'id'      => $central_id,
						'file'    => $file_changed,
						'after'   => $after,
					)
				);
				break;
			}

			// update this website.
			switch_to_blog( $blog_id );
			$copies = Media_Library::get_instance()->find_copies( $central_id, $file['urls'], $file['origin'] );
			if ( empty( $file['visible'][ $blog_id ] ) ) {
				// delete the copies, as this website could not use the file anymore.
				foreach ( $copies as $copy_id ) {
					if ( ! $this->is_deleting( $copy_id ) ) {
						$this->delete_copy( $copy_id );
					}
				}
			} elseif ( ! empty( $copies ) ) {
				// update the copies.
				foreach ( $copies as $copy_id ) {
					$this->update_copy( $copy_id, $central_id, $file, $file_changed );
				}
			} elseif ( $blog_id !== $file['origin'] && Media_Library::get_instance()->get_central_term_id() > 0 ) {
				// add the file to this website.
				$imports[] = $blog_id;
			}
			restore_current_blog();

			// mark this website as checked.
			$after = $blog_id;
		}

		// add the file to the websites via cron, as it loads the file for each website.
		if ( ! empty( $imports ) ) {
			wp_schedule_single_event( time(), self::CRON_IMPORT, array( $central_id, $imports ) );

			// start the cron now, if we are not already in it.
			if ( ! wp_doing_cron() && function_exists( 'spawn_cron' ) ) {
				spawn_cron();
			}
		}
	}

	/**
	 * Return the data of the given file of the central media library, which is used to update the websites.
	 *
	 * @param int $central_id The attachment ID in the central media library.
	 * @param int $central    The website of the central media library (0 for the one of the actual website).
	 *
	 * @return array{url: string, urls: array<int,string>, origin: int, values: array<string,string>, metadata: array<string,int>, visible: array<int,bool>}|array{}
	 */
	private function get_central_file_data( int $central_id, int $central = 0 ): array {
		// get the central media library.
		if ( 0 === $central ) {
			$central = Media_Library::get_instance()->get_central_site_id();
		}

		// bail if it is unknown.
		if ( 0 === $central ) {
			return array();
		}

		// switch to the central media library.
		switch_to_blog( $central );

		// get the attachment.
		$post = get_post( $central_id );

		// bail if it does not exist.
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			restore_current_blog();
			return array();
		}

		// get the URL.
		$url = Media_Library::get_instance()->get_file_url( $central_id );

		// get the size of the file.
		$metadata     = wp_get_attachment_metadata( $central_id );
		$file_details = array();
		if ( is_array( $metadata ) ) {
			// not every file has all of them (e.g. a PDF has no width).
			foreach ( array_intersect_key( $metadata, array_flip( array( 'width', 'height', 'filesize' ) ) ) as $key => $value ) {
				$file_details[ $key ] = absint( $value );
			}
		}

		// get the websites, which could use this file.
		$visible = array();
		foreach ( array_keys( Media_Library::get_instance()->get_sites( $central ) ) as $blog_id ) {
			$visible[ $blog_id ] = Media_Library::get_instance()->is_visible_for( $central_id, $blog_id );
		}

		// collect the data.
		$data = array(
			'url'      => $url,
			'urls'     => array_values( array_unique( array_filter( array( $url, (string) wp_get_attachment_url( $central_id ) ) ) ) ),
			'origin'   => Media_Library::get_instance()->get_origin_blog( $central_id ),
			'values'   => Metadata::get_instance()->get_values( $central_id ),
			'metadata' => $file_details,
			'visible'  => $visible,
		);

		// switch back.
		restore_current_blog();

		// return the data.
		return $data;
	}

	/**
	 * Update a copy of a file of the central media library in the actual website.
	 *
	 * @param int                                                                           $copy_id      The attachment ID in the actual website.
	 * @param int                                                                           $central_id   The attachment ID in the central media library.
	 * @param array{url: string, values: array<string,string>, metadata: array<string,int>} $file      The data of the central file.
	 * @param bool                                                                          $file_changed True if the file itself has been changed.
	 *
	 * @return void
	 */
	private function update_copy( int $copy_id, int $central_id, array $file, bool $file_changed ): void {
		// mark the change.
		++$this->running;

		try {
			// save the ID of the central file (for copies of older versions).
			update_post_meta( $copy_id, Media_Library::META_CENTRAL_ID, $central_id );

			// update caption, description and alternative text.
			Metadata::get_instance()->apply( $copy_id, $file['values'] );

			// update the file, if it has been changed (e.g. replaced or edited).
			if ( $file_changed ) {
				$this->refresh_copy( $copy_id, $file );
			}
		} finally {
			--$this->running;
		}
	}

	/**
	 * Use the changed file of the central media library for the copy in the actual website.
	 *
	 * @param int                                             $copy_id The attachment ID in the actual website.
	 * @param array{url: string, metadata: array<string,int>} $file    The data of the central file.
	 *
	 * @return void
	 */
	private function refresh_copy( int $copy_id, array $file ): void {
		// get the external file.
		$external_file_obj = Files::get_instance()->get_file( $copy_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return;
		}

		// use the new URL, if it changed (e.g. after the image has been edited).
		if ( ! empty( $file['url'] ) && $external_file_obj->get_url( true ) !== $file['url'] ) {
			$external_file_obj->set_url( $file['url'] );
		}

		// remove the cached file and its sizes, so the changed file is loaded again.
		$external_file_obj->delete_cache();
		$external_file_obj->delete_thumbs();

		// use the size of the new file.
		if ( isset( $file['metadata']['filesize'] ) ) {
			$external_file_obj->set_filesize( $file['metadata']['filesize'] );
		}
		$metadata = wp_get_attachment_metadata( $copy_id );
		if ( is_array( $metadata ) && ! empty( $file['metadata'] ) ) {
			wp_update_attachment_metadata( $copy_id, array_merge( $metadata, $file['metadata'] ) );
		}
	}

	/**
	 * Add a new file of the central media library to the given websites.
	 *
	 * Runs via cron.
	 *
	 * @param int            $central_id The attachment ID in the central media library.
	 * @param array<int,int> $blog_ids   The websites.
	 *
	 * @return void
	 */
	public function run_import( int $central_id, array $blog_ids ): void {
		// get the start time.
		$start = time();

		// add the file to each website.
		$blog_ids = array_values( array_map( 'absint', $blog_ids ) );
		foreach ( $blog_ids as $index => $blog_id ) {
			// continue via cron if the time is over.
			if ( $index > 0 && time() - $start >= $this->get_time_limit() ) {
				wp_schedule_single_event( time(), self::CRON_IMPORT, array( $central_id, array_slice( $blog_ids, $index ) ) );
				return;
			}

			// add the file to this website.
			switch_to_blog( $blog_id );
			$this->import_file( $central_id );
			restore_current_blog();
		}
	}

	/**
	 * Add a file of the central media library to the actual website.
	 *
	 * @param int $central_id The attachment ID in the central media library.
	 *
	 * @return bool
	 */
	public function import_file( int $central_id ): bool {
		// get the external source of the central media library in this website.
		$term_id = Media_Library::get_instance()->get_central_term_id();

		// bail if it does not exist.
		if ( 0 === $term_id ) {
			return false;
		}

		// bail if the file exists already in this website.
		$file = $this->get_central_file_data( $central_id );
		if ( empty( $file ) || ! empty( Media_Library::get_instance()->find_copies( $central_id, $file['urls'], $file['origin'] ) ) ) {
			return false;
		}

		// bail if this website could not use the file.
		if ( empty( $file['visible'][ get_current_blog_id() ] ) ) {
			return false;
		}

		// get the import object.
		$import = Import::get_instance();

		// save the actual settings of the import object.
		$previous_fields  = $import->get_fields();
		$previous_term_id = $import->get_term_id();

		// use the external source of the central media library.
		$term_data = Taxonomy::get_instance()->get_entry( $term_id );
		$import->set_fields( isset( $term_data['fields'] ) && is_array( $term_data['fields'] ) ? $term_data['fields'] : array() );
		$import->set_term_id( $term_id );

		// import the file.
		++$this->running;
		try {
			$result = $import->add_url( $file['url'] );
		} finally {
			--$this->running;
			$import->set_fields( $previous_fields );
			$import->set_term_id( $previous_term_id );
		}

		// bail if the file could not be imported.
		$external_file_obj = Files::get_instance()->get_file_by_url( $file['url'] );
		if ( ! $result || ! $external_file_obj ) {
			Log::get_instance()->create( __( 'The new file of the central media library could not be added to this website.', 'external-files-in-media-library' ), $file['url'], 'error' );
			return false;
		}

		// mark it as file of the central media library and as synchronized file of its external source.
		update_post_meta( $external_file_obj->get_id(), Media_Library::META_CENTRAL_ID, $central_id );
		wp_set_object_terms( $external_file_obj->get_id(), $term_id, Taxonomy::get_instance()->get_name() );
		Synchronization::get_instance()->mark_as_synced( $external_file_obj );

		// use caption, description and alternative text of the central file.
		++$this->running;
		try {
			Metadata::get_instance()->apply( $external_file_obj->get_id(), $file['values'] );
		} finally {
			--$this->running;
		}

		// return true as the file has been added.
		return true;
	}

	/**
	 * Save the title of a copy as former title, if the synchronization changes it.
	 *
	 * The title of a copy is its file name. Copies of versions before 6.1.0 used the title of the central file, which
	 * the proxy used in their URL (e.g. "/emlproxy/image"). With the former title these URLs in contents still work.
	 *
	 * @param array<string,mixed> $post_array The attachment settings.
	 * @param string              $file_url   The URL of the file.
	 * @param array<string,mixed> $file_data  The data of the file during the import.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function keep_former_title( array $post_array, string $file_url, array $file_data ): array {
		// bail if this is not an update of a copy of a file of the central media library.
		if ( empty( $post_array['ID'] ) || empty( $file_data['efml-central-id'] ) || ! isset( $file_data['title'] ) ) {
			return $post_array;
		}

		// get the actual title.
		$attachment_id = absint( $post_array['ID'] );
		$post          = get_post( $attachment_id );
		$title         = $post instanceof \WP_Post ? $post->post_title : '';

		// save it as former title, if it changes and could be used in a URL of the proxy.
		if ( $title !== $file_data['title'] && 1 === preg_match( '/^[a-zA-Z0-9-_.]+$/', $title ) && ! in_array( $title, (array) get_post_meta( $attachment_id, Files::FORMER_TITLES_META, false ), true ) ) {
			add_post_meta( $attachment_id, Files::FORMER_TITLES_META, $title );
		}

		// return the settings unchanged.
		return $post_array;
	}

	/**
	 * Mark a file, which has been imported from the central media library (e.g. by the synchronization), as copy.
	 *
	 * @param File                $external_file_obj The external file.
	 * @param array<string,mixed> $file_data         The data of the file during the import.
	 *
	 * @return void
	 */
	public function mark_copy( File $external_file_obj, array $file_data ): void {
		// bail if the file is not from the central media library.
		if ( empty( $file_data['efml-central-id'] ) || ! Media_Library::get_instance()->is_using_central() ) {
			return;
		}

		// save the ID of the central file.
		update_post_meta( $external_file_obj->get_id(), Media_Library::META_CENTRAL_ID, absint( $file_data['efml-central-id'] ) );

		// bail if the data of the central file is not given.
		if ( empty( $file_data['efml-central-values'] ) || ! is_array( $file_data['efml-central-values'] ) ) {
			return;
		}

		// use caption, description and alternative text of the central file (and the own changes of them).
		++$this->running;
		try {
			Metadata::get_instance()->apply( $external_file_obj->get_id(), array_map( 'strval', $file_data['efml-central-values'] ) );
		} finally {
			--$this->running;
		}
	}

	/**
	 * Delete the files of the central media library in the actual website after its synchronization, which do not
	 * exist anymore in the central media library or which this website could not use anymore.
	 *
	 * This also covers the own uploads, which are exported to the central media library (the synchronization does not
	 * delete them) and the deletion of the last file of the central media library (the synchronization does not delete
	 * anything if the list of files is empty).
	 *
	 * @param string              $url       The URL of the external source.
	 * @param array<string,mixed> $term_data The data of the external source.
	 * @param int                 $term_id   The ID of the external source.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function cleanup_after_sync( string $url, array $term_data, int $term_id ): void {
		// bail if this is not the external source of the central media library in this website.
		if ( 0 === $term_id || Media_Library::get_instance()->get_central_term_id() !== $term_id ) {
			return;
		}

		// get the files of the central media library in this website.
		$copies = $this->get_copies_in_website( $term_id );

		// bail if there are none.
		if ( empty( $copies ) ) {
			return;
		}

		// get the existing files in the central media library and whether this website could use them.
		$blog_id   = get_current_blog_id();
		$available = array();
		switch_to_blog( Media_Library::get_instance()->get_central_site_id() );
		foreach ( array_unique( array_values( $copies ) ) as $central_id ) {
			$available[ $central_id ] = $central_id > 0 && 'attachment' === get_post_type( $central_id ) && Media_Library::get_instance()->is_visible_for( $central_id, $blog_id );
		}
		restore_current_blog();

		// delete the copies of files, which are not available.
		foreach ( $copies as $copy_id => $central_id ) {
			if ( ! $available[ $central_id ] && ! $this->is_deleting( $copy_id ) ) {
				$this->delete_copy( $copy_id );
			}
		}
	}

	/**
	 * Return the copies of files of the central media library in the actual website as list of attachment ID and
	 * the ID of the central file.
	 *
	 * @param int $term_id The ID of the external source of the central media library.
	 *
	 * @return array<int,int>
	 */
	private function get_copies_in_website( int $term_id ): array {
		global $wpdb;

		// get the files with the ID of the central file.
		$copies = array();
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT m.`post_id`, m.`meta_key`, m.`meta_value` FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` IN (%s, %s)', array( $wpdb->postmeta, $wpdb->posts, 'attachment', Media_Library::META_CENTRAL_ID, 'efml_multisite_attachment_id' ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $rows as $row ) {
			$post_id = absint( $row['post_id'] );

			// use the ID of an export only for own uploads to the central media library.
			if ( 'efml_multisite_attachment_id' === $row['meta_key'] && ( isset( $copies[ $post_id ] ) || ! Media_Library::get_instance()->is_own_upload( $post_id ) ) ) {
				continue;
			}

			// add it.
			$copies[ $post_id ] = absint( $row['meta_value'] );
		}

		// get the files of the external source without the ID (from older versions).
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => array( 'inherit', 'trash' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array_keys( $copies ),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filter for term.
					array(
						'taxonomy' => Taxonomy::get_instance()->get_name(),
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);
		foreach ( $query->get_posts() as $post_id ) {
			$central_id = Media_Library::get_instance()->get_central_id( absint( $post_id ) );

			// bail if it is not found in the central media library: the synchronization itself deletes it.
			// It is not deleted here, as a file of an older version might not be found only because of its URL.
			if ( 0 === $central_id ) {
				continue;
			}

			// add it.
			$copies[ absint( $post_id ) ] = $central_id;
		}

		// return the list.
		return $copies;
	}

	/**
	 * Run the migration of the origins and continue it via cron, if it is not complete.
	 *
	 * @param int $time_limit The time in seconds this run could use.
	 *
	 * @return void
	 */
	public function run_migration( int $time_limit = 20 ): void {
		// run the migration and continue it, if it is not complete.
		$complete = Media_Library::get_instance()->migrate_origins( $time_limit );
		if ( $complete ) {
			$complete = Media_Library::get_instance()->migrate_file_info( $time_limit );
		}
		if ( ! $complete && ! wp_next_scheduled( self::CRON_MIGRATE ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_MIGRATE );
		}
	}
}
