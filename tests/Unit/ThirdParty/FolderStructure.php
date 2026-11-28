<?php
/**
 * Tests for the folder structure in folder plugins during the synchronization of subdirectories.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Folder_Structure;
use ExternalFilesInMediaLibrary\ThirdParty\EnhancedMediaLibrary;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\SynchronizationTrait;

/**
 * Object to test the folder structure with the categories of Enhanced Media Library (a hierarchical taxonomy).
 */
class FolderStructure extends externalFilesTests {
	use SynchronizationTrait;

	/**
	 * The created directory.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Prepare the plugin Enhanced Media Library: its taxonomy and its integration.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// simulate the active plugin with its taxonomy.
		update_option( 'active_plugins', array( 'enhanced-media-library/enhanced-media-library.php' ) );
		register_taxonomy( 'media_category', 'attachment', array( 'hierarchical' => true ) );
		EnhancedMediaLibrary::get_instance()->init();

		// enable the synchronization with the extension for subdirectories.
		update_option( 'eml_sync_extensions', array( 'sync_subdirectories' ) );
		$this->enable_synchronization();
		Sync_Subdirectories::get_instance()->init();
	}

	/**
	 * Remove the directory and the taxonomy.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( '' !== $this->directory ) {
			$this->remove_directory( $this->directory );
			$this->directory = '';
		}
		unregister_taxonomy( 'media_category' );
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
	 * Create a local external source with files in "Holidays/2024" and in the root.
	 *
	 * @return array{url: string, term_id: int}
	 */
	private function create_source(): array {
		$this->directory = (string) realpath( get_temp_dir() ) . '/efml-folder-structure-' . uniqid();
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
	 * Return the names of the categories of the file with the given URL, with their parents (e.g. "Photos/Holidays").
	 *
	 * @param string $url The URL of the file.
	 *
	 * @return array<int,string>
	 */
	private function get_categories_of_file( string $url ): array {
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$names = array();
		foreach ( wp_get_object_terms( $external_file_obj->get_id(), 'media_category' ) as $term ) {
			$names[] = trim( (string) get_term_parents_list( $term->term_id, 'media_category', array( 'link' => false, 'separator' => '/' ) ), '/' );
		}
		return $names;
	}

	/**
	 * Test that the structure is created below the chosen category, also again after renaming and deleting.
	 *
	 * @return void
	 */
	public function test_folder_structure_below_chosen_category(): void {
		$photos = wp_insert_term( 'Photos', 'media_category' );
		$this->assertIsArray( $photos );
		$source = $this->create_source();
		update_term_meta( $source['term_id'], 'eml_categories', array( $photos['term_id'] => 1 ) );

		// the first synchronization creates the categories.
		$this->run_sync( 'cron', $source['url'], $source['term_id'] );
		$this->assertSame( array( 'Photos' ), $this->get_categories_of_file( $source['url'] . 'example-red.jpg' ) );
		$this->assertSame( array( 'Photos/Holidays' ), $this->get_categories_of_file( $source['url'] . 'Holidays/example-green.jpg' ) );
		$this->assertSame( array( 'Photos/Holidays/2024' ), $this->get_categories_of_file( $source['url'] . 'Holidays/2024/example-blue.png' ) );
		$count = absint( wp_count_terms( array( 'taxonomy' => 'media_category', 'hide_empty' => false ) ) );

		// a renamed category is used again, no category is created twice.
		$holidays = get_term_by( 'name', 'Holidays', 'media_category' );
		$this->assertInstanceOf( \WP_Term::class, $holidays );
		wp_update_term( $holidays->term_id, 'media_category', array( 'name' => 'Vacation' ) );
		$this->run_sync( 'cron', $source['url'], $source['term_id'] );
		$this->assertSame( array( 'Photos/Vacation' ), $this->get_categories_of_file( $source['url'] . 'Holidays/example-green.jpg' ) );
		$this->assertSame( $count, absint( wp_count_terms( array( 'taxonomy' => 'media_category', 'hide_empty' => false ) ) ) );

		// a deleted category is created again.
		$year = get_term_by( 'name', '2024', 'media_category' );
		$this->assertInstanceOf( \WP_Term::class, $year );
		wp_delete_term( $year->term_id, 'media_category' );
		$this->run_sync( 'cron', $source['url'], $source['term_id'] );
		$this->assertSame( array( 'Photos/Vacation/2024' ), $this->get_categories_of_file( $source['url'] . 'Holidays/2024/example-blue.png' ) );
	}

	/**
	 * Test that the structure is created on the top level, if no category is chosen, and not used for multiple categories.
	 *
	 * @return void
	 */
	public function test_folder_structure_without_and_with_multiple_categories(): void {
		$source = $this->create_source();

		// without chosen category: on the top level.
		$this->run_sync( 'cron', $source['url'], $source['term_id'] );
		$this->assertSame( array(), $this->get_categories_of_file( $source['url'] . 'example-red.jpg' ) );
		$this->assertSame( array( 'Holidays/2024' ), $this->get_categories_of_file( $source['url'] . 'Holidays/2024/example-blue.png' ) );

		// with multiple categories: no structure.
		$first  = wp_insert_term( 'First', 'media_category' );
		$second = wp_insert_term( 'Second', 'media_category' );
		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		update_term_meta(
			$source['term_id'],
			'eml_categories',
			array(
				$first['term_id']  => 1,
				$second['term_id'] => 1,
			)
		);
		$this->assertSame( -1, EnhancedMediaLibrary::get_instance()->get_target_folder( $source['term_id'] ) );
	}

	/**
	 * Test that nothing is changed without the setting on the external source.
	 *
	 * @return void
	 */
	public function test_without_setting(): void {
		$source = $this->create_source();
		delete_term_meta( $source['term_id'], Folder_Structure::SETTING );

		$this->run_sync( 'cron', $source['url'], $source['term_id'] );

		$this->assertSame( array(), $this->get_categories_of_file( $source['url'] . 'Holidays/2024/example-blue.png' ) );
		$this->assertSame( 0, absint( wp_count_terms( array( 'taxonomy' => 'media_category', 'hide_empty' => false ) ) ) );
	}

	/**
	 * Test the option in the configuration dialog and its saving.
	 *
	 * @return void
	 */
	public function test_option_in_dialog(): void {
		$source    = $this->create_source();
		$extension = Sync_Subdirectories::get_instance();

		$form = $extension->extend_sync_form_in_dialog( '', $source['term_id'] );
		$this->assertStringContainsString( 'id="sync_folder_structure"', $form );
		$this->assertStringContainsString( 'Enhanced Media Library', $form );

		$extension->save_sync_config( array( 'sync_subdirectories' => '' ), $source['term_id'] );
		$this->assertSame( '', (string) get_term_meta( $source['term_id'], Folder_Structure::SETTING, true ) );
		$extension->save_sync_config(
			array(
				'sync_subdirectories'   => Sync_Subdirectories::YES,
				'sync_folder_structure' => 1,
			),
			$source['term_id']
		);
		$this->assertSame( 1, absint( get_term_meta( $source['term_id'], Folder_Structure::SETTING, true ) ) );
	}

	/**
	 * Test the directory of DropBox files relative to their external source.
	 *
	 * @return void
	 */
	public function test_relative_directory_of_dropbox(): void {
		$protocol_obj = new \ExternalFilesInMediaLibrary\Services\DropBox\Protocol( 'DropBox/Photos' );
		$this->assertSame( '', $protocol_obj->get_relative_directory( 'dropbox/Photos/Red.jpg' ) );
		$this->assertSame( 'Sub/Deeper', $protocol_obj->get_relative_directory( 'dropbox/photos/Sub/Deeper/Blue.png' ) );
		$this->assertSame( '', $protocol_obj->get_relative_directory( 'dropbox/Other/Blue.png' ) );
		$this->assertSame( 'Photos', ( new \ExternalFilesInMediaLibrary\Services\DropBox\Protocol( 'DropBox/' ) )->get_relative_directory( 'dropbox/Photos/Red.jpg' ) );
	}
}
