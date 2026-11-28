<?php
/**
 * Minimal version of the folder model of the plugin CatFolders for the tests (only in separate processes).
 *
 * It contains only the functions, which are used by this plugin, with the same signatures.
 *
 * @package external-files-in-media-library
 */

namespace CatFolders\Models;

/**
 * Minimal version of \CatFolders\Models\FolderModel.
 */
class FolderModel {
	/**
	 * The folders with their ID as key.
	 *
	 * @var array<int,array{name: string, parent: int}>
	 */
	public static array $folders = array();

	/**
	 * The folder of each attachment.
	 *
	 * @var array<int,int>
	 */
	public static array $relations = array();

	/**
	 * Return all folders.
	 *
	 * @param mixed $order The order.
	 * @param bool  $flat  Whether to return a flat list.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all( $order = null, $flat = false ) {
		$list = array();
		foreach ( self::$folders as $id => $folder ) {
			$list[] = array(
				'id'     => $id,
				'title'  => $folder['name'],
				'parent' => $folder['parent'],
			);
		}
		return $list;
	}

	/**
	 * Return the ID of the existing folder or create a new one.
	 *
	 * @param string $name   The name.
	 * @param int    $parent The parent.
	 *
	 * @return int
	 */
	public static function create_or_get( $name, $parent ) {
		foreach ( self::$folders as $id => $folder ) {
			if ( $folder['name'] === $name && $folder['parent'] === (int) $parent ) {
				return $id;
			}
		}
		$id                   = empty( self::$folders ) ? 1 : max( array_keys( self::$folders ) ) + 1;
		self::$folders[ $id ] = array(
			'name'   => $name,
			'parent' => (int) $parent,
		);
		return $id;
	}

	/**
	 * Return whether the folder exists.
	 *
	 * @param int $folderId The ID.
	 *
	 * @return bool
	 */
	public static function isFolderExist( $folderId ) {
		return isset( self::$folders[ (int) $folderId ] );
	}

	/**
	 * Assign the attachments to the folder.
	 *
	 * @param int        $folderId The folder.
	 * @param array<int> $imgIds The attachments.
	 * @param bool       $getCounter Whether to return the counters.
	 *
	 * @return void
	 */
	public static function set_attachments( $folderId, $imgIds, $getCounter = true ) {
		foreach ( $imgIds as $attachment_id ) {
			self::$relations[ (int) $attachment_id ] = (int) $folderId;
		}
	}
}
