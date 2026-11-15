<?php
/**
 * File to handle the migration of files, which have been offloaded by other plugins.
 *
 * The files stay where they are: each attachment is changed into an external file, which uses the object in the
 * bucket. Local files and the data of the other plugin are kept, so a switch back is possible.
 *
 * Contents of posts still contain the local URLs of these files, as the other plugins change them only during
 * the output. This is done here as well, but only for the files, which have been taken over.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations\Migration_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use Throwable;
use WP_Admin_Bar;
use WP_Error;
use WP_Post;

/**
 * Object to handle the migrations.
 */
class Migrations {
	/**
	 * The name of the option with the running migration.
	 */
	public const RUN_OPTION = 'efml_migration_run';

	/**
	 * The name of the option with the amount of migrated files.
	 */
	public const COUNT_OPTION = 'efml_migrated_files';

	/**
	 * The name of the cron event to process the migration in the background.
	 */
	public const CRON_HOOK = 'efml_migration_run';

	/**
	 * The name of the post meta with the data of the attachment before its migration.
	 */
	public const BACKUP_META = 'efml_migration';

	/**
	 * The name of the post meta with the former local files of the attachment (relative to the uploads directory).
	 */
	public const FILE_META = 'efml_migrated_file';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Migrations
	 */
	private static ?Migrations $instance = null;

	/**
	 * Marker whether the migration is started in this request (its result is shown there).
	 *
	 * @var bool
	 */
	private bool $starting = false;

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
	 * @return Migrations
	 */
	public static function get_instance(): Migrations {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// process the migration in the background.
		add_action( self::CRON_HOOK, array( $this, 'process' ) );

		// show the progress in the admin bar.
		add_action( 'admin_bar_menu', array( $this, 'add_progress_to_admin_bar' ), 401 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_bar_style' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_admin_bar_style' ) );

		// the local URLs of migrated files are replaced in the output via Local_Urls.
	}

	/**
	 * Return the list of migrations as objects.
	 *
	 * @return array<int,Migration_Base>
	 */
	public function get_migrations(): array {
		// define the list.
		$list = array(
			'\ExternalFilesInMediaLibrary\ExternalFiles\Migrations\WpOffloadMedia',
			'\ExternalFilesInMediaLibrary\ExternalFiles\Migrations\AdvancedMediaOffloader',
			'\ExternalFilesInMediaLibrary\ExternalFiles\Migrations\Stowaway',
			'\ExternalFilesInMediaLibrary\ExternalFiles\Migrations\OffloadMediaCloudStorage',
		);

		/**
		 * Filter the list of migrations from other offload plugins.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $list List of class names of objects, which extend Migration_Base.
		 */
		$list = apply_filters( 'efml_migrations', $list );

		// create the objects.
		$migrations = array();
		foreach ( $list as $class_name ) {
			// bail if the class does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// create the object.
			$obj = new $class_name();

			// bail if this is not a migration.
			if ( ! $obj instanceof Migration_Base ) {
				continue;
			}

			// add it.
			$migrations[] = $obj;
		}

		// return the list.
		return $migrations;
	}

	/**
	 * Return the migration with the given name.
	 *
	 * @param string $name The name.
	 *
	 * @return Migration_Base|null
	 */
	public function get_migration( string $name ): ?Migration_Base {
		foreach ( $this->get_migrations() as $migration ) {
			if ( $name === $migration->get_name() ) {
				return $migration;
			}
		}
		return null;
	}

	/**
	 * Return the groups of offloaded files of all other plugins.
	 *
	 * Each group contains the files of one plugin, which are saved by one provider in one bucket.
	 *
	 * @return array<string,array{id: string, migration: string, label: string, provider: string, bucket: string, region: string, count: int, service: string, plugin_file: string}>
	 */
	public function get_sources(): array {
		// collect the groups.
		$list = array();
		foreach ( $this->get_migrations() as $migration ) {
			try {
				foreach ( $migration->get_sources() as $source ) {
					// bail if no files are known.
					if ( $source['count'] <= 0 ) {
						continue;
					}

					// create the ID.
					$id = md5( $migration->get_name() . '|' . $source['provider'] . '|' . $source['bucket'] . '|' . $source['region'] );

					// add the group.
					$list[ $id ] = array(
						'id'          => $id,
						'migration'   => $migration->get_name(),
						'label'       => $migration->get_label(),
						'provider'    => $source['provider'],
						'bucket'      => $source['bucket'],
						'region'      => $source['region'],
						'count'       => $source['count'],
						'service'     => $migration->get_service_name( $source ),
						'plugin_file' => $migration->get_active_plugin_file(),
					);
				}
			} catch ( Throwable $e ) {
				// log this event.
				/* translators: %1$s will be replaced by the name of a plugin. */
				Log::get_instance()->create( sprintf( __( 'The data of %1$s could not be read:', 'external-files-in-media-library' ), $migration->get_label() ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', '', 'error' );
			}
		}

		// return the list.
		return $list;
	}

	/**
	 * Return the group with the given ID.
	 *
	 * @param string $id The ID.
	 *
	 * @return array{id: string, migration: string, label: string, provider: string, bucket: string, region: string, count: int, service: string, plugin_file: string}|null
	 */
	public function get_source( string $id ): ?array {
		$sources = $this->get_sources();
		return $sources[ $id ] ?? null;
	}

	/**
	 * Return the values for the fields of the service of the given group, as far as they are known.
	 *
	 * @param array<string,mixed> $source The group.
	 *
	 * @return array<string,string>
	 */
	public function get_fields_of_source( array $source ): array {
		// get the migration.
		$migration = $this->get_migration( isset( $source['migration'] ) && is_string( $source['migration'] ) ? $source['migration'] : '' );

		// bail if the migration is unknown.
		if ( ! $migration instanceof Migration_Base ) {
			return array();
		}

		// return the fields.
		return $migration->get_fields( $this->get_source_for_migration( $source ) );
	}

	/**
	 * Return the given group in the format of the migrations.
	 *
	 * @param array<string,mixed> $source The group.
	 *
	 * @return array{provider: string, bucket: string, region: string, count: int}
	 */
	private function get_source_for_migration( array $source ): array {
		return array(
			'provider' => isset( $source['provider'] ) && is_string( $source['provider'] ) ? $source['provider'] : '',
			'bucket'   => isset( $source['bucket'] ) && is_string( $source['bucket'] ) ? $source['bucket'] : '',
			'region'   => isset( $source['region'] ) && is_string( $source['region'] ) ? $source['region'] : '',
			'count'    => isset( $source['count'] ) && is_numeric( $source['count'] ) ? absint( $source['count'] ) : 0,
		);
	}

	/**
	 * Start the migration of the given group to the given external source.
	 *
	 * The first files are migrated directly, the rest in the background.
	 *
	 * @param array<string,mixed> $source  The group.
	 * @param int                 $term_id The ID of the external source.
	 *
	 * @return array{done: int, failed: int, total: int, finished: bool}
	 */
	public function start( array $source, int $term_id ): array {
		// save the run.
		$run = array(
			'source'  => $source,
			'term_id' => $term_id,
			'last_id' => 0,
			'total'   => isset( $source['count'] ) && is_numeric( $source['count'] ) ? absint( $source['count'] ) : 0,
			'done'    => 0,
			'skipped' => 0,
			'failed'  => 0,
			'user_id' => get_current_user_id(),
			'started' => time(),
		);
		update_option( self::RUN_OPTION, $run, false );

		// log this event.
		/* translators: %1$s will be replaced by the name of a plugin. */
		Log::get_instance()->create( sprintf( __( 'The migration of the files of %1$s has been started.', 'external-files-in-media-library' ), isset( $source['label'] ) && is_string( $source['label'] ) ? $source['label'] : '' ), '', 'info' );

		// migrate the first files directly.
		$this->starting = true;
		$finished       = $this->work( Import_Runs::get_instance()->get_deadline() );
		$this->starting = false;

		// continue in the background, if not finished.
		if ( ! $finished ) {
			$this->schedule();
		}

		// get the run.
		$run = $finished ? $this->get_last_result() : $this->get_run();

		// return the progress.
		return array(
			'done'     => absint( $run['done'] ?? 0 ),
			'failed'   => absint( $run['failed'] ?? 0 ),
			'total'    => absint( $run['total'] ?? 0 ),
			'finished' => $finished,
		);
	}

	/**
	 * Return the running migration.
	 *
	 * @return array<string,mixed>
	 */
	public function get_run(): array {
		$run = get_option( self::RUN_OPTION, array() );
		return is_array( $run ) ? $run : array();
	}

	/**
	 * Return the result of the last finished migration.
	 *
	 * @return array<string,mixed>
	 */
	public function get_last_result(): array {
		$result = get_option( self::RUN_OPTION . '_result', array() );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Plan the processing of a running migration again (e.g. after the activation of this plugin).
	 *
	 * @return void
	 */
	public function resume(): void {
		if ( ! empty( $this->get_run() ) ) {
			$this->schedule();
		}
	}

	/**
	 * Schedule the processing in the background.
	 *
	 * @return void
	 */
	private function schedule(): void {
		// bail if the event is already scheduled.
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// schedule it.
		wp_schedule_single_event( time(), self::CRON_HOOK );
	}

	/**
	 * Process the running migration in the background.
	 *
	 * @return void
	 */
	public function process(): void {
		// get the run.
		$run = $this->get_run();

		// bail if no migration is running.
		if ( empty( $run ) ) {
			return;
		}

		// use the user, who has started the migration, as the services check its permissions.
		if ( 0 === get_current_user_id() && ! empty( $run['user_id'] ) ) {
			wp_set_current_user( absint( $run['user_id'] ) );
		}

		// work on it.
		if ( ! $this->work( Import_Runs::get_instance()->get_deadline() ) ) {
			// continue with the next request.
			$this->schedule();
		}
	}

	/**
	 * Work on the running migration until it is finished or the deadline is reached.
	 *
	 * @param float $deadline The time until the work could run.
	 *
	 * @return bool True, if the migration is finished.
	 */
	public function work( float $deadline ): bool {
		// get the run.
		$run = $this->get_run();

		// bail if no migration is running.
		if ( empty( $run ) ) {
			return true;
		}

		// get the migration and the group.
		$source    = isset( $run['source'] ) && is_array( $run['source'] ) ? $run['source'] : array();
		$migration = $this->get_migration( isset( $source['migration'] ) && is_string( $source['migration'] ) ? $source['migration'] : '' );
		$term_id   = absint( $run['term_id'] ?? 0 );

		// get the service with the configuration of the external source.
		$service_obj = $this->get_service_for_term( $term_id );

		// cancel the migration, if it could not be run anymore.
		if ( ! $migration instanceof Migration_Base || ! $service_obj instanceof Service_Base ) {
			Log::get_instance()->create( __( 'The migration has been cancelled, as its plugin, its service or its external source is not available anymore.', 'external-files-in-media-library' ), '', 'error' );
			$run['cancelled'] = true;
			$this->finish( $run );
			return true;
		}

		// get the fields of the external source.
		$fields = $this->get_fields_of_term( $term_id );

		// configure the service.
		$service_obj->set_fields( $fields );

		/**
		 * Filter the amount of files, which are loaded from the other plugin at once.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $limit The amount.
		 */
		$limit = max( 1, absint( apply_filters( 'efml_migration_batch_size', 50 ) ) );

		try {
			// migrate the files until the deadline.
			while ( microtime( true ) < $deadline ) {
				// get the next files.
				$items = $migration->get_items( $this->get_source_for_migration( $source ), absint( $run['last_id'] ), $limit );

				// finish the migration, if there are no more files.
				if ( empty( $items ) ) {
					$this->finish( $run );
					return true;
				}

				// migrate each file.
				foreach ( $items as $item ) {
					// migrate the file.
					$result = $this->migrate_file( $migration, $service_obj, $term_id, $fields, $item );

					// count the result.
					$run[ $result ] = absint( $run[ $result ] ?? 0 ) + 1;

					// save the progress.
					$run['last_id'] = $item['attachment_id'];

					// stop at the deadline.
					if ( microtime( true ) >= $deadline ) {
						break;
					}
				}

				// save the progress.
				update_option( self::RUN_OPTION, $run, false );
			}
		} finally {
			// remove the credentials from the service object.
			$service_obj->set_fields( array() );
		}

		// the migration is not finished.
		return false;
	}

	/**
	 * Return the service object for the given external source.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return Service_Base|false
	 */
	private function get_service_for_term( int $term_id ): Service_Base|false {
		// make sure the services are usable (e.g. in a cron request without a user).
		Abilities::get_instance()->init_services();

		// get the service.
		$listing_obj = Services::get_instance()->get_service_by_name( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if the service is unknown.
		if ( ! $listing_obj instanceof Directory_Listing_Base ) {
			return false;
		}

		// return the service object.
		return Services::get_instance()->get_service_for_listing( $listing_obj );
	}

	/**
	 * Return the fields of the given external source.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return array<string,mixed>
	 */
	private function get_fields_of_term( int $term_id ): array {
		$term_data = Taxonomy::get_instance()->get_entry( $term_id );
		return ! empty( $term_data['fields'] ) && is_array( $term_data['fields'] ) ? $term_data['fields'] : array();
	}

	/**
	 * Migrate a single file: the attachment becomes an external file, which uses the object of the other plugin.
	 *
	 * @param Migration_Base                                        $migration   The migration.
	 * @param Service_Base                                          $service_obj The service with the configuration of the external source.
	 * @param int                                                   $term_id     The ID of the external source.
	 * @param array<string,mixed>                                   $fields      The fields of the external source.
	 * @param array{attachment_id: int, key: string, private: bool} $item        The file.
	 *
	 * @return string "done", "skipped" or "failed".
	 */
	public function migrate_file( Migration_Base $migration, Service_Base $service_obj, int $term_id, array $fields, array $item ): string {
		// get the attachment.
		$attachment_id = $item['attachment_id'];
		$post          = get_post( $attachment_id );

		// bail if the attachment does not exist anymore.
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return 'skipped';
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if the file is already an external file (e.g. from an earlier run).
		if ( $external_file_obj->is_valid() ) {
			return 'skipped';
		}

		// bail if no object is known.
		if ( '' === $item['key'] ) {
			/* translators: %1$d will be replaced by an ID. */
			Log::get_instance()->create( sprintf( __( 'The file of attachment %1$d could not be migrated, as the name of its object is unknown.', 'external-files-in-media-library' ), $attachment_id ), '', 'error' );
			return 'failed';
		}

		// get the URL of the object.
		$url = $service_obj->get_url_of_offloaded_object( $item['key'], $item['private'] );

		// bail if the object could not be used.
		if ( $url instanceof WP_Error ) {
			/* translators: %1$d will be replaced by an ID, %2$s by the name of an object. */
			Log::get_instance()->create( sprintf( __( 'The file of attachment %1$d could not be migrated, as its object %2$s could not be used:', 'external-files-in-media-library' ), $attachment_id, '<code>' . esc_html( $item['key'] ) . '</code>' ) . ' ' . esc_html( $url->get_error_message() ), '', 'error' );
			return 'failed';
		}

		// get the actual data of the attachment.
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$metadata      = wp_get_attachment_metadata( $attachment_id, true );

		// save them to be able to revert the migration. This also prevents the deletion of the local files.
		update_post_meta(
			$attachment_id,
			self::BACKUP_META,
			array(
				'migration'     => $migration->get_name(),
				'attached_file' => $attached_file,
				'metadata'      => is_array( $metadata ) ? $metadata : array(),
				'object_key'    => $service_obj->get_offloaded_object_meta_key(),
				'time'          => time(),
			)
		);

		// save the former local files to find them in contents.
		delete_post_meta( $attachment_id, self::FILE_META );
		foreach ( $this->get_local_files( $attached_file, is_array( $metadata ) ? $metadata : array() ) as $file ) {
			add_post_meta( $attachment_id, self::FILE_META, $file );
		}

		// save the name of the object like the service does it for exported files.
		if ( '' !== $service_obj->get_offloaded_object_meta_key() ) {
			update_post_meta( $attachment_id, $service_obj->get_offloaded_object_meta_key(), $item['key'] );
		}

		// save the attachment as exported external file.
		Export::get_instance()->save_as_exported( $external_file_obj, $url, $service_obj->get_name(), wp_basename( $item['key'] ), $item['private'], $fields, $term_id );

		// count the migrated files.
		update_option( self::COUNT_OPTION, absint( get_option( self::COUNT_OPTION ) ) + 1 );

		// log this event.
		/* translators: %1$s will be replaced by the name of a plugin. */
		Log::get_instance()->create( sprintf( __( 'The file has been taken over from %1$s.', 'external-files-in-media-library' ), $migration->get_label() ), $url, 'success', 2 );

		// return the success.
		return 'done';
	}

	/**
	 * Return the local files of an attachment relative to the uploads directory: the file, its original and its sizes.
	 *
	 * @param string              $attached_file The attached file.
	 * @param array<string,mixed> $metadata      The metadata.
	 *
	 * @return array<int,string>
	 */
	private function get_local_files( string $attached_file, array $metadata ): array {
		// bail if this is not a relative path.
		if ( '' === $attached_file || str_contains( $attached_file, '://' ) || str_starts_with( $attached_file, '/' ) ) {
			return array();
		}

		// get the directory.
		$directory = dirname( $attached_file );
		$directory = '.' === $directory ? '' : trailingslashit( $directory );

		// collect the files.
		$files = array( $attached_file );
		if ( ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ) {
			$files[] = $directory . $metadata['original_image'];
		}
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) && is_string( $size['file'] ) ) {
					$files[] = $directory . $size['file'];
				}
			}
		}

		// return the list without duplicates.
		return array_values( array_unique( $files ) );
	}

	/**
	 * Finish the given migration.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function finish( array $run ): void {
		// remove the run.
		delete_option( self::RUN_OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );

		// save the result.
		$run['finished'] = time();
		update_option( self::RUN_OPTION . '_result', $run, false );

		// get the name of the plugin.
		$label = isset( $run['source']['label'] ) && is_string( $run['source']['label'] ) ? $run['source']['label'] : '';

		// check whether there have been problems.
		$cancelled = ! empty( $run['cancelled'] );
		$problems  = $cancelled || absint( $run['failed'] ?? 0 ) > 0;

		// create the message.
		if ( $cancelled ) {
			/* translators: %1$s will be replaced by the name of a plugin, %2$d, %3$d and %4$d by numbers. */
			$message = sprintf( __( 'The migration of the files of %1$s has been cancelled, as its plugin, its service or its external source is not available anymore: %2$d files taken over, %3$d skipped, %4$d failed.', 'external-files-in-media-library' ), $label, absint( $run['done'] ?? 0 ), absint( $run['skipped'] ?? 0 ), absint( $run['failed'] ?? 0 ) );
		} else {
			/* translators: %1$s will be replaced by the name of a plugin, %2$d, %3$d and %4$d by numbers. */
			$message = sprintf( __( 'The migration of the files of %1$s has been completed: %2$d files taken over, %3$d skipped, %4$d failed.', 'external-files-in-media-library' ), $label, absint( $run['done'] ?? 0 ), absint( $run['skipped'] ?? 0 ), absint( $run['failed'] ?? 0 ) );
		}

		// log it.
		Log::get_instance()->create( $message, '', $problems ? 'error' : 'success' );

		// show it as hint, if it has been finished in the background.
		if ( ! $this->starting ) {
			// add the link to the log, where the reasons are listed, if there have been problems.
			$details = '';
			if ( $problems ) {
				/* translators: %1$s will be replaced by a URL. */
				$details = ' ' . sprintf( __( 'The reasons are listed in the <a href="%1$s">log</a>. Files, which have not been taken over, are unchanged. You can start the migration again via the quickstart, files already taken over are skipped.', 'external-files-in-media-library' ), esc_url( Helper::get_log_url() ) );
			}

			// add the hint.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_type( $problems ? 'error' : 'success' );
			$transient_obj->set_name( 'efml_migration_finished' );
			$transient_obj->set_message( '<strong>' . esc_html( $message ) . '</strong>' . $details );
			$transient_obj->save();
		}
	}

	/**
	 * Cancel the running migration.
	 *
	 * @return void
	 */
	public function cancel(): void {
		delete_option( self::RUN_OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Replace the local URLs of migrated files in the given content with their actual URLs.
	 *
	 * Other offload plugins keep the local URLs in contents and change them only during the output. This is done
	 * for the files, which have been taken over from them, together with the exported files via Local_Urls.
	 *
	 * @param mixed $content The content.
	 *
	 * @return mixed
	 */
	public function replace_urls( mixed $content ): mixed {
		return Local_Urls::get_instance()->replace_urls( $content );
	}

	/**
	 * Return the actual URL of a former local file of a migrated attachment.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $file          The file relative to the uploads directory.
	 *
	 * @return string
	 */
	public function get_url_of_local_file( int $attachment_id, string $file ): string {
		// get the data before the migration.
		$backup = get_post_meta( $attachment_id, self::BACKUP_META, true );

		// bail if the attachment is not migrated anymore.
		if ( ! is_array( $backup ) ) {
			return '';
		}

		// get the URL of a size.
		$name = wp_basename( $file );
		if ( ! empty( $backup['metadata']['sizes'] ) && is_array( $backup['metadata']['sizes'] ) ) {
			foreach ( $backup['metadata']['sizes'] as $size ) {
				// bail if this is not the size of this file.
				if ( ! is_array( $size ) || empty( $size['file'] ) || $name !== $size['file'] ) {
					continue;
				}

				// get the URL for this size.
				$image = wp_get_attachment_image_src( $attachment_id, array( absint( $size['width'] ?? 0 ), absint( $size['height'] ?? 0 ) ) );
				if ( is_array( $image ) && ! empty( $image[0] ) ) {
					return (string) $image[0];
				}
			}
		}

		// return the URL of the file.
		return (string) wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Revert the migration of the given attachment: it is used with its local file and by the other plugin again.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function revert( int $attachment_id ): bool {
		// get the data before the migration.
		$backup = get_post_meta( $attachment_id, self::BACKUP_META, true );

		// bail if the attachment has not been migrated.
		if ( ! is_array( $backup ) ) {
			return false;
		}

		// remove the data of the external file.
		foreach ( Files::get_instance()->get_meta_keys() as $meta_key ) {
			delete_post_meta( $attachment_id, $meta_key );
		}
		if ( ! empty( $backup['object_key'] ) && is_string( $backup['object_key'] ) ) {
			delete_post_meta( $attachment_id, $backup['object_key'] );
		}
		wp_delete_object_term_relationships( $attachment_id, Taxonomy::get_instance()->get_name() );

		// restore the data of the attachment without any filters.
		update_post_meta( $attachment_id, '_wp_attached_file', isset( $backup['attached_file'] ) && is_string( $backup['attached_file'] ) ? $backup['attached_file'] : '' );
		update_post_meta( $attachment_id, '_wp_attachment_metadata', isset( $backup['metadata'] ) && is_array( $backup['metadata'] ) ? $backup['metadata'] : array() );

		// count the migrated files.
		update_option( self::COUNT_OPTION, max( 0, absint( get_option( self::COUNT_OPTION ) ) - 1 ) );

		// return the success.
		return true;
	}

	/**
	 * Return the IDs of the migrated attachments.
	 *
	 * @return array<int,int>
	 */
	public function get_migrated_attachments(): array {
		global $wpdb;

		// get the IDs.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s', $wpdb->postmeta, self::BACKUP_META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery

		// return them as integers.
		return array_map( 'absint', $ids );
	}

	/**
	 * Return whether the progress should be shown in the admin bar.
	 *
	 * @return bool
	 */
	private function is_progress_visible(): bool {
		return is_admin_bar_showing() && ! empty( $this->get_run() ) && current_user_can( 'manage_options' );
	}

	/**
	 * Show the progress of the running migration in the admin bar.
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar.
	 *
	 * @return void
	 */
	public function add_progress_to_admin_bar( WP_Admin_Bar $admin_bar ): void {
		// bail if the progress should not be shown.
		if ( ! $this->is_progress_visible() ) {
			return;
		}

		// get the progress.
		$run   = $this->get_run();
		$done  = absint( $run['done'] ?? 0 ) + absint( $run['skipped'] ?? 0 ) + absint( $run['failed'] ?? 0 );
		$total = max( $done, absint( $run['total'] ?? 0 ) );

		// calculate the percentage.
		$percentage = $total > 0 ? (int) floor( $done / $total * 100 ) : 0;

		// add the entry.
		$admin_bar->add_menu(
			array(
				'id'     => 'efml-migration-progress',
				'parent' => null,
				'group'  => null,
				'title'  => '<span class="ab-label">' . esc_html__( 'Migration:', 'external-files-in-media-library' ) . '</span> <progress value="' . absint( $percentage ) . '" max="100"></progress>',
				'href'   => \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_logs' ),
				'meta'   => array(
					/* translators: %1$d and %2$d will be replaced by numbers. */
					'title' => sprintf( __( '%1$d of %2$d files taken over', 'external-files-in-media-library' ), $done, $total ),
				),
			)
		);
	}

	/**
	 * Add the style for the progress in the admin bar.
	 *
	 * @return void
	 */
	public function add_admin_bar_style(): void {
		// bail if the progress should not be shown.
		if ( ! $this->is_progress_visible() ) {
			return;
		}

		// add the style.
		wp_add_inline_style( 'admin-bar', '#wp-admin-bar-efml-migration-progress progress { height: 12px; width: 80px; vertical-align: middle; } #wp-admin-bar-efml-migration-progress progress::-moz-progress-bar { background-color: var(--wp-admin-theme-color, #3858e9); } #wp-admin-bar-efml-migration-progress progress::-webkit-progress-value { background-color: var(--wp-admin-theme-color, #3858e9); }' );
	}

	/**
	 * Deactivate the other plugin of the given group.
	 *
	 * @param array<string,mixed> $source The group.
	 *
	 * @return bool True, if the plugin is not active anymore.
	 */
	public function deactivate_plugin( array $source ): bool {
		// get the migration.
		$migration = $this->get_migration( isset( $source['migration'] ) && is_string( $source['migration'] ) ? $source['migration'] : '' );

		// bail if the migration is unknown.
		if ( ! $migration instanceof Migration_Base ) {
			return false;
		}

		// get the active plugin file.
		$plugin_file = $migration->get_active_plugin_file();

		// bail if the plugin is not active.
		if ( '' === $plugin_file ) {
			return true;
		}

		// deactivate it.
		deactivate_plugins( $plugin_file );

		// return whether it is not active anymore.
		return '' === $migration->get_active_plugin_file();
	}

	/**
	 * Remove the data of the migrations during uninstallation.
	 *
	 * The data saved on the attachments stay, as they are needed to revert a migration.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		$this->cancel();
		delete_option( self::RUN_OPTION . '_result' );
		delete_option( self::COUNT_OPTION );
	}
}
