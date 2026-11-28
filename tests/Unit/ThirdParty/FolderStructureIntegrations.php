<?php
/**
 * Tests for the folder structure with each supported folder plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Folder_Structure;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\SynchronizationTrait;
use ExternalFilesInMediaLibrary\ThirdParty\Folder_Structure_Interface;

/**
 * Object to test the folder structure with the integrations of the folder plugins.
 *
 * The plugins with a taxonomy are simulated with their taxonomy. FileBird and CatFolders are simulated with
 * minimal versions of their classes (see tests/Fakes/), in separate processes.
 */
class FolderStructureIntegrations extends externalFilesTests {
	use SynchronizationTrait;

	/**
	 * The created directory.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Remove the directory.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( '' !== $this->directory ) {
			$this->remove_directory( $this->directory );
			$this->directory = '';
		}
		parent::tear_down();
	}

	/**
	 * Remove the given directory with its contents.
	 *
	 * @param string $directory The directory.
	 *
	 * @return void
	 */
	private function remove_directory( string $directory ): void {
		foreach ( (array) scandir( $directory ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$path = $directory . '/' . $name;
			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );
				continue;
			}
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Enable the plugin with the given file and the synchronization with subdirectories.
	 *
	 * @param string $plugin_file The file of the plugin.
	 * @param string $class_name  The class of the integration.
	 *
	 * @return Folder_Structure_Interface
	 */
	private function enable( string $plugin_file, string $class_name ): Folder_Structure_Interface {
		update_option( 'active_plugins', array( $plugin_file ) );
		$integration = $class_name::get_instance();
		$integration->init();
		$this->assertInstanceOf( Folder_Structure_Interface::class, $integration );
		$this->assertTrue( $integration->is_folder_structure_available() );

		update_option( 'eml_sync_extensions', array( 'sync_subdirectories' ) );
		$this->enable_synchronization();
		Sync_Subdirectories::get_instance()->init();

		return $integration;
	}

	/**
	 * Create a local external source with files in "Holidays/2024" and in the root.
	 *
	 * @return array{url: string, term_id: int}
	 */
	private function create_source(): array {
		$this->directory = (string) realpath( get_temp_dir() ) . '/efml-folder-plugins-' . uniqid();
		mkdir( $this->directory . '/Holidays/2024', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		$data = trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/';
		copy( $data . 'example-red.jpg', $this->directory . '/example-red.jpg' );
		copy( $data . 'example-green.jpg', $this->directory . '/Holidays/example-green.jpg' );
		copy( $data . 'example-blue.png', $this->directory . '/Holidays/2024/example-blue.png' );

		$path    = trailingslashit( $this->directory );
		$term_id = Taxonomy::get_instance()->add( 'local', 'file://' . $path, array( 'path' => array( 'value' => $path ) ) );
		$this->assertGreaterThan( 0, $term_id );
		update_term_meta( $term_id, Sync_Subdirectories::SETTING, Sync_Subdirectories::YES );
		update_term_meta( $term_id, Folder_Structure::SETTING, 1 );

		return array(
			'url'     => 'file://' . $path,
			'term_id' => $term_id,
		);
	}

	/**
	 * Return the ID of the attachment with the given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return int
	 */
	private function get_attachment_id( string $url ): int {
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		return $external_file_obj->get_id();
	}

	/**
	 * Data provider: the folder plugins with a taxonomy.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public function provide_taxonomy_plugins(): array {
		return array(
			'Folderly'                => array( 'folderly/folderly.php', \ExternalFilesInMediaLibrary\ThirdParty\Folderly::class, 'folderly_attachment', 'folderly_categories', 'keys' ),
			'Media Library Organizer' => array( 'media-library-organizer/media-library-organizer.php', \ExternalFilesInMediaLibrary\ThirdParty\MediaLibraryOrganizer::class, 'mlo-category', 'mlo_categories', 'values' ),
			'Media Library Assistant' => array( 'media-library-assistant/index.php', \ExternalFilesInMediaLibrary\ThirdParty\MediaLibraryAssistant::class, 'attachment_category', 'mla_categories', 'values' ),
			'Enhanced Media Library'  => array( 'enhanced-media-library/enhanced-media-library.php', \ExternalFilesInMediaLibrary\ThirdParty\EnhancedMediaLibrary::class, 'media_category', 'eml_categories', 'keys' ),
		);
	}

	/**
	 * Test the structure below the chosen folder with each folder plugin with a taxonomy.
	 *
	 * @dataProvider provide_taxonomy_plugins
	 *
	 * @param string $plugin_file The file of the plugin.
	 * @param string $class_name  The class of the integration.
	 * @param string $taxonomy    The taxonomy of the folders.
	 * @param string $meta_key    The term meta with the chosen folder.
	 * @param string $format      The format of the chosen folder: "single", "keys" or "values".
	 *
	 * @return void
	 */
	public function test_taxonomy_plugin( string $plugin_file, string $class_name, string $taxonomy, string $meta_key, string $format ): void {
		register_taxonomy( $taxonomy, 'attachment', array( 'hierarchical' => true ) );
		try {
			$integration = $this->enable( $plugin_file, $class_name );
			$target      = wp_insert_term( 'Photos', $taxonomy );
			$this->assertIsArray( $target );
			$target_id = absint( $target['term_id'] );
			$source    = $this->create_source();
			$value     = array(
				'single' => $target_id,
				'keys'   => array( $target_id => 1 ),
				'values' => array( 1 => $target_id ),
			);
			update_term_meta( $source['term_id'], $meta_key, $value[ $format ] );
			$this->assertSame( $target_id, $integration->get_target_folder( $source['term_id'] ) );

			// synchronize twice: the folders are created only once.
			$this->run_sync( 'cron', $source['url'], $source['term_id'] );
			$this->run_sync( 'cron', $source['url'], $source['term_id'] );
			$this->assertSame( 3, absint( wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) ) );

			// each file is only in the folder of its directory.
			$holidays = get_term_by( 'name', 'Holidays', $taxonomy );
			$year     = get_term_by( 'name', '2024', $taxonomy );
			$this->assertInstanceOf( \WP_Term::class, $holidays );
			$this->assertInstanceOf( \WP_Term::class, $year );
			$this->assertSame( $target_id, $holidays->parent );
			$this->assertSame( $holidays->term_id, $year->parent );
			$this->assertSame( array( $target_id ), wp_get_object_terms( $this->get_attachment_id( $source['url'] . 'example-red.jpg' ), $taxonomy, array( 'fields' => 'ids' ) ) );
			$this->assertSame( array( $holidays->term_id ), wp_get_object_terms( $this->get_attachment_id( $source['url'] . 'Holidays/example-green.jpg' ), $taxonomy, array( 'fields' => 'ids' ) ) );
			$this->assertSame( array( $year->term_id ), wp_get_object_terms( $this->get_attachment_id( $source['url'] . 'Holidays/2024/example-blue.png' ), $taxonomy, array( 'fields' => 'ids' ) ) );

			// the plugin Folders needs its data on each folder.
			if ( 'media_folder' === $taxonomy ) {
				$this->assertNotEmpty( get_term_meta( $year->term_id, 'wcp_custom_order', true ) );
			}
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}

	/**
	 * Test the structure with Folders (with a minimal version of its main class for the flat assignment).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_folders(): void {
		require_once trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Fakes/Folders.php';
		$this->test_taxonomy_plugin( 'folders/folders.php', \ExternalFilesInMediaLibrary\ThirdParty\Folders::class, 'media_folder', 'folders_folder', 'single' );
	}

	/**
	 * Test the structure with FileBird (simulated with a minimal version of its class).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_filebird(): void {
		require_once trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Fakes/FileBird.php';
		$this->run_fake_plugin_test( 'filebird/filebird.php', \ExternalFilesInMediaLibrary\ThirdParty\Filebird::class, 'fildbirdfolder', \FileBird\Model\Folder::class );
	}

	/**
	 * Test the structure with CatFolders (simulated with a minimal version of its class).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_catfolders(): void {
		require_once trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Fakes/CatFolders.php';
		$this->run_fake_plugin_test( 'catfolders/catfolders.php', \ExternalFilesInMediaLibrary\ThirdParty\CatFolders::class, 'catfolder', \CatFolders\Models\FolderModel::class );
	}

	/**
	 * Test the structure with Real Media Library Pro (simulated with a minimal version of its API).
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_real_media_library(): void {
		require_once trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Fakes/RealMediaLibrary.php';
		$this->run_fake_plugin_test( 'real-media-library-lite/index.php', \ExternalFilesInMediaLibrary\ThirdParty\RealMediaLibrary::class, 'rml_folder', \MatthiasWeb\RealMediaLibrary\Core::class );
	}

	/**
	 * Run the test for a folder plugin, which is simulated by the given fake class.
	 *
	 * @param string $plugin_file The file of the plugin.
	 * @param string $class_name  The class of the integration.
	 * @param string $meta_key    The term meta with the chosen folder.
	 * @param string $fake_class  The fake class of the plugin (with the static properties $folders and $relations).
	 *
	 * @return void
	 */
	private function run_fake_plugin_test( string $plugin_file, string $class_name, string $meta_key, string $fake_class ): void {
		$integration = $this->enable( $plugin_file, $class_name );
		$fake_class::$folders = array(
			7 => array(
				'name'   => 'Photos',
				'parent' => 0,
			),
		);
		$source = $this->create_source();
		update_term_meta( $source['term_id'], $meta_key, 7 );
		$this->assertSame( 7, $integration->get_target_folder( $source['term_id'] ) );

		// synchronize twice: the folders are created only once.
		$this->run_sync( 'cron', $source['url'], $source['term_id'] );
		$this->run_sync( 'cron', $source['url'], $source['term_id'] );
		$this->assertCount( 3, $fake_class::$folders );

		// each file is only in the folder of its directory.
		$names = array();
		foreach ( $fake_class::$folders as $id => $folder ) {
			$names[ $folder['name'] ] = array( $id, $folder['parent'] );
		}
		$this->assertSame( 7, $names['Holidays'][1] );
		$this->assertSame( $names['Holidays'][0], $names['2024'][1] );
		$this->assertSame( 7, $fake_class::$relations[ $this->get_attachment_id( $source['url'] . 'example-red.jpg' ) ] );
		$this->assertSame( $names['Holidays'][0], $fake_class::$relations[ $this->get_attachment_id( $source['url'] . 'Holidays/example-green.jpg' ) ] );
		$this->assertSame( $names['2024'][0], $fake_class::$relations[ $this->get_attachment_id( $source['url'] . 'Holidays/2024/example-blue.png' ) ] );
	}
}
