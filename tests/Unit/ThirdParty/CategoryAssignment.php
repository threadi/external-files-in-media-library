<?php
/**
 * Tests for the assignment of synchronized files to the chosen categories of folder plugins (without folder structure).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the assignment of files to the categories chosen for an external source.
 */
class CategoryAssignment extends externalFilesTests {
	/**
	 * Data provider: the plugins with a taxonomy and multiple categories per external source.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_plugins(): array {
		return array(
			'Folderly'                => array( \ExternalFilesInMediaLibrary\ThirdParty\Folderly::class, 'folderly_attachment', 'folderly_categories', 'move_file_to_categories' ),
			'Media Library Organizer' => array( \ExternalFilesInMediaLibrary\ThirdParty\MediaLibraryOrganizer::class, 'mlo-category', 'mlo_categories', 'move_file_to_categories' ),
			'Media Library Assistant' => array( \ExternalFilesInMediaLibrary\ThirdParty\MediaLibraryAssistant::class, 'attachment_category', 'mla_categories', 'move_file_to_categories' ),
			'Enhanced Media Library'  => array( \ExternalFilesInMediaLibrary\ThirdParty\EnhancedMediaLibrary::class, 'media_category', 'eml_categories', 'move_file_to_categories' ),
		);
	}

	/**
	 * Test that the categories chosen in the dialog are saved and assigned to the synchronized file: all of them and
	 * not the value of the checkboxes.
	 *
	 * @dataProvider provide_plugins
	 *
	 * @param string $class_name The class of the integration.
	 * @param string $taxonomy   The taxonomy.
	 * @param string $field      The name of the field and of the term meta.
	 * @param string $method     The method, which assigns the file during the synchronization.
	 *
	 * @return void
	 */
	public function test_chosen_categories_are_assigned( string $class_name, string $taxonomy, string $field, string $method ): void {
		register_taxonomy( $taxonomy, 'attachment', array( 'hierarchical' => true ) );
		try {
			// create the categories and the external source.
			$first   = wp_insert_term( 'First', $taxonomy );
			$second  = wp_insert_term( 'Second', $taxonomy );
			$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file:///tmp/efml-categories/', array() );
			$this->assertIsArray( $first );
			$this->assertIsArray( $second );

			// save the categories like the configuration dialog sends them (the ID as key, 1 as value).
			$_POST['fields'] = array(
				$field => array(
					$first['term_id']  => '1',
					$second['term_id'] => '1',
				),
			);
			$integration = $class_name::get_instance();
			$integration->save_sync_settings( array( 'term_id' => $term_id ) );
			unset( $_POST['fields'] );

			// assign a file during the synchronization of the external source.
			$attachment_id = self::factory()->attachment->create();
			$file          = new File( $attachment_id );
			$integration->add_action_before_sync( '', array(), $term_id );
			remove_all_actions( 'efml_after_file_save' );
			$integration->{$method}( $file );

			// the file is in both categories.
			$ids = wp_get_object_terms( $attachment_id, $taxonomy, array( 'fields' => 'ids' ) );
			sort( $ids );
			$this->assertSame( array( $first['term_id'], $second['term_id'] ), $ids );
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}
}
