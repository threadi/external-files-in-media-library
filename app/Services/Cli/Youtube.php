<?php
/**
 * This file extends the CLI commands with tasks for YouTube handlings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Cli;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for cli-commands.
 *
 * @noinspection PhpUnused
 */
class Youtube {
	/**
	 * Import videos from all or given YouTube channels via API.
	 *
	 * [<ChannelId>]
	 * : IDs of the channels to import (optional).
	 *
	 * @param array $channel_ids List of channel Ids.
	 *
	 * @return void
	 */
	public function import_youtube_channel_videos( array $channel_ids = array() ): void {
		// get YouTube object.
		$youtube_obj = \ExternalFilesInMediaLibrary\Services\Youtube::get_instance();

		// if list of channels is empty, get all ids from configuration.
		if ( empty( $channel_ids ) ) {
			$channel_ids = $youtube_obj->get_youtube_channels();
		}

		// loop through the configured channels and import them.
		foreach ( $youtube_obj->get_youtube_channels() as $channel_id ) {
			$youtube_obj->import_videos_from_channel( $channel_id );
		}
	}

	/**
	 * Add YouTube channels to configuration.
	 *
	 * <ChannelIds>
	 * : IDs of the channels to add (required).
	 *
	 * @param array $channel_ids The channels to add.
	 *
	 * @return void
	 */
	public function add_youtube_channels( array $channel_ids = array() ): void {
		// get YouTube object.
		$youtube_obj = \ExternalFilesInMediaLibrary\Services\Youtube::get_instance();

		// add the channels.
		foreach ( $channel_ids as $channel_id ) {
			// bail if entry is empty.
			if ( empty( $channel_id ) ) {
				continue;
			}

			// add entry as channel.
			$youtube_obj->add_channel( $channel_id );
		}
	}

	/**
	 * Delete all or given YouTube channel.
	 *
	 * [<ChannelId>]
	 * : IDs of the channels to delete (optional).
	 *
	 * @param array $channel_ids List of channel IDs to delete.
	 *
	 * @return void
	 */
	public function delete_youtube_channels( array $channel_ids = array() ): void {
		// if no IDs given, delete them all.
		if ( empty( $channel_ids ) ) {
			delete_option( 'eml_youtube_channels' );
			return;
		}

		// get YouTube object.
		$youtube_obj = \ExternalFilesInMediaLibrary\Services\Youtube::get_instance();

		// delete the given channels.
		foreach ( $channel_ids as $channel_id ) {
			$youtube_obj->delete_channel( $channel_id );
		}
	}
}
