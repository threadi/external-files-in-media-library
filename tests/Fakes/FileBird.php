<?php
/**
 * Minimal version of the folder model of the plugin FileBird for the tests (only in separate processes).
 *
 * It contains only the functions, which are used by this plugin, with the same signatures.
 *
 * @package external-files-in-media-library
 */

namespace FileBird\Model;

/**
 * Minimal version of \FileBird\Model\Folder.
 */
class Folder {
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
	 * @return array<int,object>
	 */
	public static function allFolders(): array {
		$list = array();
		foreach ( self::$folders as $id => $folder ) {
			$list[] = (object) array(
				'id'     => $id,
				'name'   => $folder['name'],
				'parent' => $folder['parent'],
			);
		}
		return $list;
	}

	/**
	 * Return the existing folder or create a new one.
	 *
	 * @param string $name   The name.
	 * @param int    $parent The parent.
	 * @param bool   $return_id_if_exist Whether to return the ID of an existing folder.
	 *
	 * @return array<string,mixed>|false
	 */
	public static function newOrGet( $name, $parent, $return_id_if_exist = true ) {
		foreach ( self::$folders as $id => $folder ) {
			if ( $folder['name'] === $name && $folder['parent'] === (int) $parent ) {
				return $return_id_if_exist ? array( 'id' => $id ) : false;
			}
		}
		$id                   = empty( self::$folders ) ? 1 : max( array_keys( self::$folders ) ) + 1;
		self::$folders[ $id ] = array(
			'name'   => $name,
			'parent' => (int) $parent,
		);
		return array(
			'id'    => $id,
			'title' => $name,
		);
	}

	/**
	 * Return whether the folder exists.
	 *
	 * @param int $folder_id The ID.
	 *
	 * @return bool
	 */
	public static function isFolderExist( $folder_id ) {
		return isset( self::$folders[ (int) $folder_id ] );
	}

	/**
	 * Assign the attachments to the folder.
	 *
	 * @param int        $folderId The folder.
	 * @param array<int> $attachmentIds The attachments.
	 * @param string     $lang The language.
	 *
	 * @return array<mixed>
	 */
	public static function assignFolder( int $folderId, array $attachmentIds, string $lang ) {
		foreach ( $attachmentIds as $attachment_id ) {
			self::$relations[ (int) $attachment_id ] = $folderId;
		}
		return array();
	}
}
