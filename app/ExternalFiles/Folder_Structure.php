<?php
/**
 * File to rebuild the structure of subdirectories in folder plugins during the synchronization.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\ThirdParty\Folder_Structure_Interface;

/**
 * Object to rebuild the structure of the subdirectories of an external source in folder plugins.
 *
 * During the synchronization of an external source with the option "Take over the folder structure", each file is
 * assigned to the folder of its subdirectory below the folder chosen for the external source. The folders are created,
 * if they do not exist. Their IDs are saved per external source and folder plugin, so they are found again during the
 * next synchronization, also if they have been renamed in the plugin. Folders are never deleted.
 *
 * Folder plugins are added via the hook "efml_folder_structure_integrations" (see Folder_Structure_Interface).
 */
class Folder_Structure {
	/**
	 * Name of the term meta with the setting.
	 */
	public const SETTING = 'efml_sync_folder_structure';

	/**
	 * Prefix of the term meta with the IDs of the folders per path.
	 */
	public const FOLDERS_META_PREFIX = 'efml_folder_structure_';

	/**
	 * The ID of the actual synchronized external source.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * The protocol object of the actual synchronized external source.
	 *
	 * @var Protocol_Base|false
	 */
	private Protocol_Base|false $protocol_obj = false;

	/**
	 * Instance of actual object.
	 *
	 * @var Folder_Structure|null
	 */
	private static ?Folder_Structure $instance = null;

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
	 * @return Folder_Structure
	 */
	public static function get_instance(): Folder_Structure {
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
		add_action( 'efml_before_sync', array( $this, 'start' ), 10, 3 );
		add_action( 'efml_after_sync', array( $this, 'stop' ), 10, 0 );
	}

	/**
	 * Return the integrations of folder plugins, which are available.
	 *
	 * @return array<int,Folder_Structure_Interface>
	 */
	public function get_integrations(): array {
		$list = array();

		/**
		 * Filter the integrations of folder plugins, which could take over the structure of subdirectories.
		 *
		 * @since 6.1.0 Available since 6.1.0.
		 * @param array<int,mixed> $list List of integrations (objects with Folder_Structure_Interface).
		 */
		$list = (array) apply_filters( 'efml_folder_structure_integrations', $list );

		// return only the available integrations.
		$integrations = array();
		foreach ( $list as $integration ) {
			if ( $integration instanceof Folder_Structure_Interface && $integration->is_folder_structure_available() ) {
				$integrations[] = $integration;
			}
		}
		return $integrations;
	}

	/**
	 * Return whether the folder structure should be used for the given external source.
	 *
	 * It requires the enabled extension for subdirectories and the setting on the external source.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return bool
	 */
	public function is_enabled_for_term( int $term_id ): bool {
		// bail if the extension for subdirectories is not enabled.
		if ( ! in_array( Sync_Subdirectories::get_instance()->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return false;
		}

		// return the setting of the external source.
		return 1 === absint( get_term_meta( $term_id, self::SETTING, true ) );
	}

	/**
	 * Start the use of the folder structure for the synchronization of the given external source.
	 *
	 * @param string              $url       The URL of the external source.
	 * @param array<string,mixed> $term_data The data of the external source.
	 * @param int                 $term_id   The ID of the external source.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function start( string $url, array $term_data, int $term_id ): void {
		// remove the hook of a previous synchronization in the same request.
		$this->stop();

		// bail if the folder structure should not be used.
		if ( ! $this->is_enabled_for_term( $term_id ) || empty( $this->get_integrations() ) ) {
			return;
		}

		// get the protocol object of the external source.
		$protocol_obj = Synchronization::get_instance()->get_protocol_of_source( $term_id );
		if ( ! $protocol_obj ) {
			return;
		}

		// remember the external source.
		$this->term_id      = $term_id;
		$this->protocol_obj = $protocol_obj;

		// assign each file after the folder plugins have assigned it to their chosen folder.
		add_action( 'efml_after_file_save', array( $this, 'assign_file' ), 20 );
	}

	/**
	 * Stop the use of the folder structure after the synchronization.
	 *
	 * @return void
	 */
	public function stop(): void {
		$this->term_id      = 0;
		$this->protocol_obj = false;
		remove_action( 'efml_after_file_save', array( $this, 'assign_file' ), 20 );
	}

	/**
	 * Assign the given file to the folder of its subdirectory in each available folder plugin.
	 *
	 * @param File $external_file_obj The external file.
	 *
	 * @return void
	 */
	public function assign_file( File $external_file_obj ): void {
		// bail if no external source is synchronized.
		if ( 0 === $this->term_id || ! $this->protocol_obj ) {
			return;
		}

		// get the directory of the file relative to the external source.
		$directory = $this->protocol_obj->get_relative_directory( $external_file_obj->get_url( true ) );

		// assign it in each folder plugin.
		foreach ( $this->get_integrations() as $integration ) {
			// get the chosen folder of the external source.
			$target = $integration->get_target_folder( $this->term_id );

			// bail if the structure could not be used for this external source.
			if ( $target < 0 ) {
				continue;
			}

			// get the folder of the directory.
			$folder_id = $this->get_folder_for_directory( $integration, $target, $directory );

			// bail if no folder is used (files on the top level without chosen folder or on errors).
			if ( $folder_id <= 0 ) {
				continue;
			}

			// assign the file.
			$integration->assign_file( $external_file_obj->get_id(), $folder_id );
		}
	}

	/**
	 * Return the ID of the folder for the given directory below the given target folder in the given folder plugin.
	 *
	 * Missing folders are created. The IDs are saved on the external source per target folder and path.
	 *
	 * @param Folder_Structure_Interface $integration The integration of the folder plugin.
	 * @param int                        $target      The chosen folder of the external source (0 for the top level).
	 * @param string                     $directory   The directory relative to the external source (parts separated by "/").
	 *
	 * @return int The ID of the folder, the target folder for files without directory, 0 on errors.
	 */
	public function get_folder_for_directory( Folder_Structure_Interface $integration, int $target, string $directory ): int {
		// files without directory are assigned to the target folder.
		if ( '' === $directory ) {
			return $target;
		}

		// get the saved folders.
		$meta_key = self::FOLDERS_META_PREFIX . $integration->get_folder_structure_name();
		$folders  = get_term_meta( $this->term_id, $meta_key, true );
		if ( ! is_array( $folders ) ) {
			$folders = array();
		}

		// get or create the folder of each part of the path.
		$parent  = $target;
		$path    = '';
		$changed = false;
		foreach ( explode( '/', $directory ) as $name ) {
			// bail on empty parts.
			if ( '' === $name ) {
				continue;
			}

			// get the path up to this part.
			$path = '' === $path ? $name : $path . '/' . $name;
			$key  = $target . ':' . $path;

			// use the saved folder, if it still exists (also if it has been renamed).
			$folder_id = absint( $folders[ $key ] ?? 0 );
			if ( $folder_id > 0 && $integration->folder_exists( $folder_id ) ) {
				$parent = $folder_id;
				continue;
			}

			// get or create the folder.
			$folder_id = $integration->get_or_create_folder( $parent, $name );

			// bail on errors.
			if ( $folder_id <= 0 ) {
				/* translators: %1$s will be replaced by a folder name, %2$s by the name of a plugin. */
				Log::get_instance()->create( sprintf( __( 'The folder %1$s could not be created in %2$s.', 'external-files-in-media-library' ), '<code>' . esc_html( $path ) . '</code>', esc_html( $integration->get_folder_structure_title() ) ), '', 'error' );
				$parent = 0;
				break;
			}

			// save it.
			$folders[ $key ] = $folder_id;
			$changed         = true;
			$parent          = $folder_id;
		}

		// save the folders, if they have been changed.
		if ( $changed ) {
			update_term_meta( $this->term_id, $meta_key, $folders );
		}

		// return the folder of the directory.
		return $parent;
	}

	/**
	 * Set the actual synchronized external source (e.g. for tests or other tools).
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return void
	 */
	public function set_term_id( int $term_id ): void {
		$this->term_id = $term_id;
	}
}
