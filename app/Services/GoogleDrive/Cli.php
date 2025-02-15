<?php
/**
 * This file contains WP CLI options for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleDrive;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleDrive;
use Google\Service\Exception;
use WP_User;
use function ExtendBuilder\colibri_blog_posts_normal_item;

/**
 * Handle external files via WP CLI.
 *
 * @noinspection PhpUnused
 */
class Cli {
	/**
	 * Check the Google Drive connection for each saved access token.
	 *
	 * @return void
	 */
	public function google_drive_check_connection(): void {
		// get access tokens.
		$access_tokens = get_option( 'eml_google_drive_access_tokens' );

		// bail if no access token is available.
		if ( empty( $access_tokens ) ) {
			\WP_CLI::error( __( 'No access tokens found for any user!', 'external-files-in-media-library' ) );
		}

		// bail if access token is not an array.
		if ( ! is_array( $access_tokens ) ) {
			\WP_CLI::error( __( 'Wrong format for access tokens!', 'external-files-in-media-library' ) );
		}

		// collect the results.
		$check_results = array();

		// loop through the list of tokens.
		foreach ( $access_tokens as $user_id => $access_token ) {
			// get user as object.
			$user = get_user_by( 'ID', $user_id );

			// bail if user could not be found.
			if ( ! $user instanceof WP_User ) {
				/* translators: %1$s will be replaced by an ID. */
				$check_results[] = sprintf( __( 'User with ID %1$d could not be found!', 'external-files-in-media-library' ), $user_id );
				continue;
			}

			// get the client.
			$client_obj = new Client( $access_token );
			$client     = $client_obj->get_client();

			// bail if client is not a Client object.
			if ( ! $client instanceof \Google\Client ) {
				continue;
			}

			// connect to Google Drive.
			$service = new \Google\Service\Drive( $client );

			// collect the request query.
			$query = array(
				'fields'   => 'files(capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,starred,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey),nextPageToken',
				'pageSize' => 1000,
				'orderBy'  => 'name_natural',
			);

			// get the files.
			try {
				$results = $service->files->listFiles( $query );
			} catch ( Exception $e ) {
				// log event.
				/* translators: %1$s will be replaced by the username. */
				Log::get_instance()->create( sprintf( __( 'List of files could not be loaded from Google Drive for user %1$s. Error:', 'external-files-in-media-library' ), $user->display_name ), esc_url( $this->get_url() ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', 'error' );

				// do not continue with this user.
				continue;
			}

			// get list of files.
			$files = $results->getFiles();

			// show hint if no files could be loaded.
			if ( empty( $files ) ) {
				/* translators: %1$s will be replaced by the username. */
				$check_results[] = sprintf( __( 'No files found for user %1$s!', 'external-files-in-media-library' ), $user->display_name );
			}

			// show hint if files could be loaded.
			/* translators: %1$d will be replaced by a number. */
			$check_results[] = sprintf( __( '%1$d files found for user %2$s.', 'external-files-in-media-library' ), count( $files ), $user->display_name );
		}

		// show results.
		foreach ( $check_results as $check_result ) {
			\WP_CLI::success( $check_result );
		}
	}

	/**
	 * Refresh the token to access Google Drive, if set and if necessary.
	 *
	 * @return void
	 */
	public function google_drive_refresh_token(): void {
		// get access token.
		$access_tokens = get_option( 'eml_google_drive_access_tokens' );

		// bail if no access token is available.
		if ( empty( $access_tokens ) ) {
			\WP_CLI::error( __( 'No access token found for any user!', 'external-files-in-media-library' ) );
		}

		// collect the results.
		$check_results = array();

		// loop through the list of tokens.
		foreach ( $access_tokens as $user_id => $access_token ) {
			// get user as object.
			$user = get_user_by( 'ID', $user_id );

			// bail if user could not be found.
			if ( ! $user instanceof WP_User ) {
				/* translators: %1$s will be replaced by the User ID. */
				$check_results[] = sprintf( __( 'User with ID %1$d could not be found!', 'external-files-in-media-library' ), $user_id );
				continue;
			}

			// get the client, which refreshes the token if necessary.
			$client_obj = new Client( $access_token );
			$client_obj->get_client( $user_id );

			// check if token has been refreshed.
			if ( $client_obj->has_token_refreshed() ) {
				/* translators: %1$s will be replaced by the username. */
				$check_results[] = sprintf( __( 'Token has been refreshed for user %1$s.', 'external-files-in-media-library' ), $user->display_name );
			} else {
				// show hint if token has not been refreshed.
				/* translators: %1$s will be replaced by the username. */
				$check_results[] = sprintf( __( 'Token has not been refreshed for user %1$s.', 'external-files-in-media-library' ), $user->display_name );
			}
		}

		// show results.
		foreach ( $check_results as $check_result ) {
			\WP_CLI::success( $check_result );
		}
	}
}
