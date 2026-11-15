<?php
/**
 * File to export the sizes of images together with them and to deliver them from the external sources.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;
use WP_REST_Response;

/**
 * Object to handle the sizes of exported images.
 *
 * The sizes of an image (e.g. "image-300x200.jpg") and its original image (for scaled images) are exported to the
 * same folder as the image itself, if the service supports it (see Export_Base::supports_sizes()). They are saved in
 * the post meta "efml_export_sizes" for each external source.
 *
 * The delivery follows the proxy setting of images exactly: with enabled proxy the sizes are delivered via the proxy
 * (which loads them from the external source instead of creating them from the original image), without proxy via
 * their URLs on the external source. Private files are always delivered via the proxy, also their sizes: the proxy
 * loads the exported size via the API of the service into its cache (or creates it from the original image, if that fails).
 */
class Sizes {
	/**
	 * Name of the post meta with the exported sizes.
	 *
	 * Structure: array( 'main' => term ID, 'terms' => array( term ID => array( size name => array( 'file', 'width',
	 * 'height', 'mime-type', 'url', 'target' ) ) ) ). The original image uses the size name "_original".
	 */
	public const META = 'efml_export_sizes';

	/**
	 * Name of the entry of the original image in the list of sizes.
	 */
	public const ORIGINAL = '_original';

	/**
	 * Name of the WP-Cron hook, which exports the sizes of an image again, if some of them could not be exported.
	 *
	 * @var string
	 */
	public const RETRY_HOOK = 'efml_retry_export_of_sizes';

	/**
	 * Name of the post meta with the number of attempts to export the sizes of an image again.
	 *
	 * @var string
	 */
	public const RETRY_META = 'efml_sizes_retries';

	/**
	 * The attachments, whose sizes could not be exported completely in this request (attachment ID => true).
	 *
	 * @var array<int,bool>
	 */
	private array $failed = array();

	/**
	 * The attachments, whose next attempt has been planned in this request (attachment ID => true).
	 *
	 * @var array<int,bool>
	 */
	private array $retry_planned = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Sizes
	 */
	private static ?Sizes $instance = null;

	/**
	 * URLs for the srcset of attachments in this request (attachment ID => width => URL), or "local" for contents.
	 *
	 * @var array<int,array<int,string>|string>
	 */
	private array $srcset_urls = array();

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
	 * @return Sizes
	 */
	public static function get_instance(): Sizes {
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
		// detect the dimensions of exported sizes in contents (for "width", "height" and "srcset").
		add_filter( 'wp_image_src_get_dimensions', array( $this, 'get_dimensions_of_src' ), 10, 4 );

		// add the srcset to exported images in contents, as WordPress needs sizes in their metadata for it.
		add_filter( 'wp_content_img_tag', array( $this, 'add_srcset_to_content_image' ), 10, 3 );

		// start the export of the sizes of exported images by request.
		add_action( 'admin_action_efml_export_sizes', array( $this, 'export_sizes_via_request' ) );

		// add the sizes of external images to the REST API, which the block editor uses for the choice of the size.
		add_filter( 'rest_prepare_attachment', array( $this, 'add_sizes_to_rest_response' ), 10, 2 );

		// export the sizes again, if some of them could not be exported.
		add_action( self::RETRY_HOOK, array( $this, 'retry_export' ) );
	}

	/**
	 * Add the sizes of external images to the response of the REST API for media.
	 *
	 * WordPress reads the sizes from the metadata, which has no sizes for external images, as their files are not
	 * local. Without them the block editor shows no choice of the size ("Resolution") for these images. The sizes
	 * are added with the URLs WordPress uses for them (via the proxy or from the external source, as configured).
	 *
	 * @param WP_REST_Response $response The response.
	 * @param WP_Post          $post     The attachment.
	 *
	 * @return WP_REST_Response
	 */
	public function add_sizes_to_rest_response( WP_REST_Response $response, WP_Post $post ): WP_REST_Response {
		// get the data.
		$data = $response->get_data();

		// bail if the response contains no media details or already has sizes of the metadata.
		if ( ! is_array( $data ) || ! isset( $data['media_details'] ) || ( is_array( $data['media_details'] ) && ! empty( $data['media_details']['sizes'] ) && ! is_object( $data['media_details']['sizes'] ) ) ) {
			return $response;
		}

		// bail if this is not an image.
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $response;
		}

		// bail if this is not an external file.
		if ( ! Files::get_instance()->get_file( $post->ID )->is_valid() ) {
			return $response;
		}

		// get the full image.
		$full = wp_get_attachment_image_src( $post->ID, 'full' );

		// bail if it could not be loaded.
		if ( ! is_array( $full ) || empty( $full[0] ) ) {
			return $response;
		}

		// collect the sizes, which have their own file (not the full image).
		$sizes = array();
		foreach ( array_keys( wp_get_registered_image_subsizes() ) as $size_name ) {
			$image = wp_get_attachment_image_src( $post->ID, $size_name );
			if ( ! is_array( $image ) || empty( $image[0] ) || $image[0] === $full[0] ) {
				continue;
			}
			$sizes[ $size_name ] = array(
				'file'       => wp_basename( (string) $image[0] ),
				'width'      => absint( $image[1] ),
				'height'     => absint( $image[2] ),
				'mime_type'  => $post->post_mime_type,
				'source_url' => (string) $image[0],
			);
		}

		// bail if no size has its own file.
		if ( empty( $sizes ) ) {
			return $response;
		}

		// add the full image as WordPress does it.
		$sizes['full'] = array(
			'file'       => wp_basename( (string) $full[0] ),
			'width'      => absint( $full[1] ),
			'height'     => absint( $full[2] ),
			'mime_type'  => $post->post_mime_type,
			'source_url' => (string) $full[0],
		);

		// set the sizes.
		if ( ! is_array( $data['media_details'] ) ) {
			$data['media_details'] = (array) $data['media_details'];
		}
		$data['media_details']['sizes'] = $sizes;
		$response->set_data( $data );

		// return the response.
		return $response;
	}

	/**
	 * Return the dialog to start the export of the sizes of exported images.
	 *
	 * @return array<string,mixed>
	 */
	public function get_dialog(): array {
		// create the URL.
		$url = add_query_arg(
			array(
				'action' => 'efml_export_sizes',
				'nonce'  => wp_create_nonce( 'efml-export-sizes' ),
			),
			get_admin_url() . 'admin.php'
		);

		// return the dialog.
		return array(
			'className' => 'efml',
			'title'     => __( 'Export sizes of exported images', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Do you want to export the sizes of your exported images?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'Images, which have been exported without their sizes, get them on their external sources: the sizes are created from the local copy or from the image downloaded from its external source.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'Afterward the sizes are delivered like the images themselves: via the proxy of your website, if it is enabled for images, otherwise from the external sources. The proxy loads them from there and must not create them anymore.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'The images are processed one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes, export the sizes', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Start the export of the sizes of exported images by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function export_sizes_via_request(): void {
		// check nonce.
		check_admin_referer( 'efml-export-sizes', 'nonce' );

		// get referer.
		$referer = (string) wp_get_referer();

		// bail if cap is missing.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// start the run.
		$result = Import_Runs::get_instance()->start_sizes();

		// show the result (not, if all images have been processed: the hint of the finished run shows it then).
		if ( '' !== $result['message'] ) {
			$transient_obj = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->add();
			$transient_obj->set_name( 'efml_export_sizes' );
			$transient_obj->set_type( $result['success'] ? 'success' : 'error' );
			$transient_obj->set_message( '<strong>' . esc_html( $result['message'] ) . '</strong>' );
			$transient_obj->save();
		}

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Return whether exported images without exported sizes exist.
	 *
	 * @return bool
	 */
	public function has_images_without_sizes(): bool {
		return ! empty( $this->get_images_without_sizes( 0, 1 ) );
	}

	/**
	 * Export the sizes and the original image of the given local image to the given external source.
	 *
	 * Must be called after the export of the image itself and before its local files are deleted.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param Export_Base         $export_obj    The export object of the service.
	 * @param int                 $term_id       The ID of the external source.
	 * @param string              $export_path   The target of the image itself.
	 * @param array<string,mixed> $credentials   The credentials of the external source.
	 *
	 * @return void
	 */
	public function export_sizes( int $attachment_id, Export_Base $export_obj, int $term_id, string $export_path, array $credentials ): void {
		// bail if the service does not support sizes or this is not an image.
		if ( ! $export_obj->supports_sizes() || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		// get the local file of the image.
		$file = get_attached_file( $attachment_id, true );

		// bail if it is not a local file.
		if ( ! is_string( $file ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $file ) ) {
			return;
		}

		// export the files.
		$local_files = $this->get_local_files( $attachment_id );
		$this->export_files( $attachment_id, $export_obj, $term_id, trailingslashit( dirname( $file ) ), $local_files, trailingslashit( dirname( $export_path ) ), $credentials );

		// export the variants of the image and its sizes in other formats (e.g. WebP), which image optimizers created.
		Variants::get_instance()->export( $attachment_id, $export_obj, $term_id, trailingslashit( dirname( $file ) ), trailingslashit( dirname( $export_path ) ), $credentials, array( Variants::FULL => wp_basename( $file ) ) + wp_list_pluck( $local_files, 'file' ), wp_basename( $export_path ) );

		// try it again later, if some sizes could not be exported.
		if ( isset( $this->failed[ $attachment_id ] ) ) {
			$this->schedule_retry( $attachment_id );
		}
	}

	/**
	 * Plan to export the sizes of the given image again, as some of them could not be exported (at most 3 times).
	 *
	 * Meanwhile, the proxy creates the missing sizes from the image as before.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	private function schedule_retry( int $attachment_id ): void {
		// bail if the next attempt has been planned in this request already (e.g. for another external source).
		if ( isset( $this->retry_planned[ $attachment_id ] ) ) {
			return;
		}
		$this->retry_planned[ $attachment_id ] = true;

		// count this attempt.
		$attempts = absint( get_post_meta( $attachment_id, self::RETRY_META, true ) ) + 1;

		/**
		 * Filter the maximum number of attempts to export the sizes of an image again.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $max_attempts  The maximum number of attempts.
		 * @param int $attachment_id The attachment ID.
		 */
		$max_attempts = absint( apply_filters( 'efml_sizes_retry_max_attempts', 3, $attachment_id ) );

		// bail if the maximum has been reached.
		if ( $attempts > $max_attempts ) {
			delete_post_meta( $attachment_id, self::RETRY_META );
			Log::get_instance()->create( __( 'The sizes of the image could not be exported completely, also after several attempts. Use "Export sizes now" in the export settings to try it again.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'error' );
			return;
		}

		// save the attempts and plan the next one.
		update_post_meta( $attachment_id, self::RETRY_META, $attempts );
		if ( ! wp_next_scheduled( self::RETRY_HOOK, array( $attachment_id ) ) ) {
			wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, self::RETRY_HOOK, array( $attachment_id ) );
		}

		// log this event.
		Log::get_instance()->create( __( 'Some sizes of the image could not be exported. They will be exported again in 10 minutes.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'info', 1 );
	}

	/**
	 * Plan to export the files of the given image again later, as some of them could not be exported (e.g. a
	 * variant in another format, see Variants).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function retry_later( int $attachment_id ): void {
		$this->failed[ $attachment_id ] = true;
		$this->schedule_retry( $attachment_id );
	}

	/**
	 * Export the sizes of the given image again (via WP-Cron), as some of them could not be exported.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function retry_export( int $attachment_id ): void {
		// bail if no attempt is planned (e.g. the image has been deleted).
		if ( 0 === absint( get_post_meta( $attachment_id, self::RETRY_META, true ) ) ) {
			return;
		}

		// export the sizes again, they are created from the image.
		unset( $this->failed[ $attachment_id ], $this->retry_planned[ $attachment_id ] );
		$this->export_sizes_of_exported_image( $attachment_id );

		// plan the next attempt, if some sizes could not be exported again.
		if ( isset( $this->failed[ $attachment_id ] ) ) {
			$this->schedule_retry( $attachment_id );
			return;
		}

		// end the attempts.
		delete_post_meta( $attachment_id, self::RETRY_META );
	}

	/**
	 * Export the given files of an image from the given directory to the given external source and save them.
	 *
	 * @param int                                $attachment_id The attachment ID.
	 * @param Export_Base                        $export_obj    The export object of the service.
	 * @param int                                $term_id       The ID of the external source.
	 * @param string                             $directory     The local directory of the files, with trailing slash.
	 * @param array<string,array<string,mixed>>  $files         The files (size name => file, width, height, mime-type).
	 * @param string                             $target_dir    The target directory, with trailing slash.
	 * @param array<string,mixed>                $credentials   The credentials of the external source.
	 * @param bool                               $keep_former   True to keep replaced files as former files (instead of forgetting them).
	 *
	 * @return int The number of exported files.
	 */
	public function export_files( int $attachment_id, Export_Base $export_obj, int $term_id, string $directory, array $files, string $target_dir, array $credentials, bool $keep_former = false ): int {
		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// log this event.
		/* translators: %1$s will be replaced by a list of file names. */
		Log::get_instance()->create( sprintf( __( 'Exporting the sizes of the image: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( implode( ', ', wp_list_pluck( $files, 'file' ) ) ) . '</code>' ), $target_dir, 'info', 2 );

		// export each file.
		$exported = array();
		foreach ( $files as $size_name => $size ) {
			// get the local file.
			$source = $directory . $size['file'];

			// get the target.
			$target = $target_dir . $size['file'];

			// bail if it does not exist.
			if ( ! $wp_filesystem->exists( $source ) ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The size %1$s of the image does not exist locally and could not be exported.', 'external-files-in-media-library' ), '<code>' . esc_html( (string) $size['file'] ) . '</code>' ), $source, 'info', 1 );
				continue;
			}

			// export it.
			try {
				$url = $export_obj->export_size( $attachment_id, $source, $target, $credentials );
			} catch ( \Throwable $e ) {
				/* translators: %1$s will be replaced by an error message. */
				Log::get_instance()->create( sprintf( __( 'Error during the export of a size of the image: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $e->getMessage() ) . '</code>' ), $target, 'error' );
				$url = false;
			}

			// bail if it could not be exported: this size is created by the proxy as before and exported again later.
			if ( ! is_string( $url ) || '' === $url ) {
				$this->failed[ $attachment_id ] = true;
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The size %1$s of the image could not be exported. It is created by the proxy as before.', 'external-files-in-media-library' ), '<code>' . esc_html( (string) $size['file'] ) . '</code>' ), $target, 'error' );
				continue;
			}

			// add it to the list.
			$exported[ $size_name ] = array_merge(
				$size,
				array(
					'url'    => $url,
					'target' => $target,
				)
			);
		}

		// save them.
		if ( ! empty( $exported ) ) {
			$this->save( $attachment_id, $term_id, $exported, $keep_former );

			// log this event.
			/* translators: %1$d will be replaced by a number. */
			Log::get_instance()->create( sprintf( __( '%1$d sizes of the image have been exported.', 'external-files-in-media-library' ), count( $exported ) ), $target_dir, 'info', 2 );
		}

		// return the number of exported files.
		return count( $exported );
	}

	/**
	 * Update the files of an image on the given external source to the given new files.
	 *
	 * Each new file is uploaded, if it exists locally. Otherwise, a file with the same name, which exists already on
	 * the external source (also as former file), is used again (e.g. after "Restore image" in the image editor).
	 * Files, which are not used anymore, are kept as former files or deleted on the external source.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int                               $attachment_id The attachment ID.
	 * @param int                               $term_id       The ID of the external source.
	 * @param Export_Base                       $export_obj    The export object of the service.
	 * @param array<string,mixed>               $credentials   The credentials of the external source.
	 * @param string                            $directory     The local directory of the files, with trailing slash.
	 * @param string                            $target_dir    The target directory, with trailing slash.
	 * @param array<string,array<string,mixed>> $files         The new files (size name => file, width, height, mime-type).
	 * @param bool                              $keep_replaced True to keep files, which are not used anymore, as former files.
	 *
	 * @return int The number of uploaded files.
	 */
	public function update_files( int $attachment_id, int $term_id, Export_Base $export_obj, array $credentials, string $directory, string $target_dir, array $files, bool $keep_replaced ): int {
		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the actual data.
		$data    = $this->get_data( $attachment_id );
		$current = $data['terms'][ $term_id ] ?? array();
		$former  = array_values( $data['former'][ $term_id ] ?? array() );

		// collect the resulting files.
		$result   = array();
		$uploaded = 0;
		foreach ( $files as $size_name => $size ) {
			// get the local file.
			$file   = (string) $size['file'];
			$source = $directory . $file;
			$target = $target_dir . $file;

			// use the file on the external source, if it exists there already and has not been changed (the original image).
			$known = $this->find_file( $current, $file );
			if ( self::ORIGINAL === $size_name && ! empty( $known ) ) {
				$result[ $size_name ] = $known;
				continue;
			}

			// upload the local file.
			if ( $wp_filesystem->exists( $source ) ) {
				try {
					$url = $export_obj->export_size( $attachment_id, $source, $target, $credentials );
				} catch ( \Throwable $e ) {
					/* translators: %1$s will be replaced by an error message. */
					Log::get_instance()->create( sprintf( __( 'Error during the export of a size of the image: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $e->getMessage() ) . '</code>' ), $target, 'error' );
					$url = false;
				}

				// add it, if it has been uploaded.
				if ( is_string( $url ) && '' !== $url ) {
					$result[ $size_name ] = array_merge(
						$size,
						array(
							'url'    => $url,
							'target' => $target,
						)
					);
					++$uploaded;
					continue;
				}

				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The size %1$s of the image could not be exported. It is created by the proxy as before.', 'external-files-in-media-library' ), '<code>' . esc_html( $file ) . '</code>' ), $target, 'error' );
				$this->failed[ $attachment_id ] = true;
				continue;
			}

			// use the file, which exists already on the external source.
			if ( ! empty( $known ) ) {
				$result[ $size_name ] = array_merge( $known, $size );
				continue;
			}

			// use a former file with this name again.
			foreach ( $former as $index => $entry ) {
				if ( ( $entry['file'] ?? '' ) === $file ) {
					$result[ $size_name ] = array_merge( $entry, $size );
					unset( $former[ $index ] );
					break;
				}
			}
		}

		// handle the files, which are not used anymore (several sizes could use the same file).
		$used = wp_list_pluck( $result, 'file' );
		foreach ( $current as $entry ) {
			// bail if it is still used or has been handled already.
			if ( in_array( $entry['file'] ?? '', $used, true ) ) {
				continue;
			}
			$used[] = $entry['file'] ?? '';

			// keep it as former file.
			if ( $keep_replaced ) {
				$former[] = $entry;
				continue;
			}

			// delete it on the external source.
			try {
				$deleted = $export_obj->delete_size( $attachment_id, (string) ( $entry['url'] ?? '' ), (string) ( $entry['target'] ?? '' ), $credentials );
			} catch ( \Throwable $e ) {
				$deleted = false;
			}

			// keep it as former file, if it could not be deleted, so it will be deleted together with the image.
			if ( ! $deleted ) {
				$former[] = $entry;
			}
		}

		// save the data.
		$data['terms'][ $term_id ] = $result;
		if ( empty( $data['main'] ) ) {
			$data['main'] = $term_id;
		}
		$data['former'][ $term_id ] = array_values( $former );
		if ( empty( $data['former'][ $term_id ] ) ) {
			unset( $data['former'][ $term_id ] );
		}
		if ( empty( $data['former'] ) ) {
			unset( $data['former'] );
		}
		update_post_meta( $attachment_id, self::META, $data );

		// try it again later, if some sizes could not be exported.
		if ( isset( $this->failed[ $attachment_id ] ) ) {
			$this->schedule_retry( $attachment_id );
		}

		// return the number of uploaded files.
		return $uploaded;
	}

	/**
	 * Forget the files of the given attachment on the given external source (e.g. after they have been deleted there).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $term_id       The ID of the external source.
	 *
	 * @return void
	 */
	public function forget_source( int $attachment_id, int $term_id ): void {
		// get the data.
		$data = $this->get_data( $attachment_id );

		// remove the files of this external source.
		unset( $data['terms'][ $term_id ], $data['former'][ $term_id ], $data['variants'][ $term_id ] );

		// save the data or remove it, if no files are left.
		$this->save_data( $attachment_id, $data );
	}

	/**
	 * Return the entry of the given file name in the given list of files of an external source.
	 *
	 * @param array<string|int,array<string,mixed>> $files The files.
	 * @param string                                $file  The file name.
	 *
	 * @return array<string,mixed>
	 */
	private function find_file( array $files, string $file ): array {
		foreach ( $files as $entry ) {
			if ( ( $entry['file'] ?? '' ) === $file ) {
				return $entry;
			}
		}
		return array();
	}

	/**
	 * Add the given file as former file of the given external source, so it is deleted together with the image.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param int                 $term_id       The ID of the external source.
	 * @param array<string,mixed> $entry         The file (file, url, target).
	 *
	 * @return void
	 */
	public function add_former_file( int $attachment_id, int $term_id, array $entry ): void {
		$data                         = $this->get_data( $attachment_id );
		$data['former'][ $term_id ][] = $entry;
		update_post_meta( $attachment_id, self::META, $data );
	}

	/**
	 * Remove the former file with the given name of the given external source and return it.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param int    $term_id       The ID of the external source.
	 * @param string $file          The file name.
	 *
	 * @return array<string,mixed> The file or an empty array, if it does not exist.
	 */
	public function take_former_file( int $attachment_id, int $term_id, string $file ): array {
		$data = $this->get_data( $attachment_id );
		foreach ( $data['former'][ $term_id ] ?? array() as $index => $entry ) {
			if ( ( $entry['file'] ?? '' ) !== $file ) {
				continue;
			}
			unset( $data['former'][ $term_id ][ $index ] );
			$data['former'][ $term_id ] = array_values( $data['former'][ $term_id ] );
			update_post_meta( $attachment_id, self::META, $data );
			return $entry;
		}
		return array();
	}

	/**
	 * Return the local files of the sizes and the original image of the given image (size name => data).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_local_files( int $attachment_id ): array {
		// get the metadata (unfiltered and unchecked, as it could have been changed by other plugins).
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		// bail if no metadata exist.
		if ( ! is_array( $metadata ) ) {
			return array();
		}

		// return the files of the metadata.
		return $this->get_files_of_metadata( $metadata, $attachment_id );
	}

	/**
	 * Return the files of the sizes and the original image in the given metadata of an image (size name => data).
	 *
	 * @param array<string,mixed> $metadata      The metadata (could have been changed by other plugins, so it is checked).
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_files_of_metadata( array $metadata, int $attachment_id ): array {
		// collect the files.
		$files = array();

		// add the sizes (the metadata could have been changed by other plugins, so it is checked).
		$sizes = isset( $metadata['sizes'] ) ? (array) $metadata['sizes'] : array();
		foreach ( $sizes as $size_name => $size ) {
			// bail if the data is not usable.
			$size = (array) $size;
			if ( empty( $size['file'] ) || ! is_string( $size['file'] ) ) {
				continue;
			}

			// add it.
			$files[ (string) $size_name ] = array(
				'file'      => wp_basename( $size['file'] ),
				'width'     => isset( $size['width'] ) ? absint( $size['width'] ) : 0,
				'height'    => isset( $size['height'] ) ? absint( $size['height'] ) : 0,
				'mime-type' => isset( $size['mime-type'] ) ? (string) $size['mime-type'] : '',
			);
		}

		// add the original image of a scaled or rotated image.
		$original_image = isset( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ? $metadata['original_image'] : '';
		if ( '' !== $original_image ) {
			$files[ self::ORIGINAL ] = array(
				'file'      => wp_basename( $original_image ),
				'width'     => 0,
				'height'    => 0,
				'mime-type' => (string) get_post_mime_type( $attachment_id ),
			);
		}

		// return the list.
		return $files;
	}

	/**
	 * Save the given exported files for the given external source.
	 *
	 * The external source is used as main source, as the image is assigned to the last external source of its
	 * export (the main export source).
	 *
	 * @param int                               $attachment_id The attachment ID.
	 * @param int                               $term_id       The ID of the external source.
	 * @param array<string,array<string,mixed>> $files         The exported files.
	 * @param bool                              $keep_former   True to keep the replaced files as former files.
	 *
	 * @return void
	 */
	private function save( int $attachment_id, int $term_id, array $files, bool $keep_former = false ): void {
		// get the actual data.
		$data = $this->get_data( $attachment_id );

		// keep the files of the former sizes, which are not replaced by a file with the same name.
		if ( $keep_former && ! empty( $data['terms'][ $term_id ] ) ) {
			$new_files = wp_list_pluck( $files, 'file' );
			foreach ( $data['terms'][ $term_id ] as $size_name => $size ) {
				// keep the original image, if it has not been exported again.
				if ( self::ORIGINAL === $size_name && ! isset( $files[ self::ORIGINAL ] ) ) {
					$files[ self::ORIGINAL ] = $size;
					continue;
				}

				// bail if a new file with this name exists (it has been overwritten).
				if ( in_array( $size['file'] ?? '', $new_files, true ) ) {
					continue;
				}

				// remember it (once, as several sizes could use the same file).
				if ( empty( $this->find_file( $data['former'][ $term_id ] ?? array(), (string) ( $size['file'] ?? '' ) ) ) ) {
					$data['former'][ $term_id ][] = $size;
				}
			}
		}

		// add the files of this external source.
		$data['terms'][ $term_id ] = $files;
		$data['main']              = $term_id;

		// save them.
		$this->save_data( $attachment_id, $data );
	}

	/**
	 * Return the saved data of the given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * "former" contains files of sizes, which have been replaced (e.g. after the sizes have been created again with
	 * other dimensions): they are kept on the external sources, as contents could still use them, and are deleted
	 * together with the image.
	 *
	 * "variants" contains the files of the image and its sizes in other formats (e.g. WebP), see Variants.
	 *
	 * @return array{main: int, terms: array<int,array<string,array<string,mixed>>>, former: array<int,array<int,array<string,mixed>>>, variants: array<int,array<string,array<string,mixed>>>}
	 */
	public function get_data( int $attachment_id ): array {
		// get the meta.
		$data = get_post_meta( $attachment_id, self::META, true );

		// return a default, if it is not usable.
		if ( ! is_array( $data ) || ! isset( $data['terms'] ) || ! is_array( $data['terms'] ) ) {
			return array(
				'main'     => 0,
				'terms'    => array(),
				'former'   => array(),
				'variants' => array(),
			);
		}

		// return the data.
		return array(
			'main'     => absint( $data['main'] ?? 0 ),
			'terms'    => $data['terms'],
			'former'   => isset( $data['former'] ) && is_array( $data['former'] ) ? $data['former'] : array(),
			'variants' => isset( $data['variants'] ) && is_array( $data['variants'] ) ? $data['variants'] : array(),
		);
	}

	/**
	 * Save the given data of the given attachment (removes empty parts and the post meta, if nothing is left).
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param array<string,mixed> $data          The data.
	 *
	 * @return void
	 */
	public function save_data( int $attachment_id, array $data ): void {
		// remove empty parts.
		foreach ( array( 'former', 'variants' ) as $key ) {
			if ( empty( $data[ $key ] ) ) {
				unset( $data[ $key ] );
			}
		}

		// remove the post meta, if no files are left.
		if ( empty( $data['terms'] ) && empty( $data['former'] ) && empty( $data['variants'] ) ) {
			delete_post_meta( $attachment_id, self::META );
			return;
		}

		// save the data.
		update_post_meta( $attachment_id, self::META, $data );
	}

	/**
	 * Return the exported files of the main external source of the given attachment (size name => data).
	 *
	 * @param int  $attachment_id    The attachment ID.
	 * @param bool $include_original True to include the original image.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_sizes( int $attachment_id, bool $include_original = false ): array {
		// get the data.
		$data = $this->get_data( $attachment_id );

		// get the files of the main external source.
		$files = $data['terms'][ $data['main'] ] ?? array();

		// remove the original image, if requested.
		if ( ! $include_original ) {
			unset( $files[ self::ORIGINAL ] );
		}

		// return the list.
		return $files;
	}

	/**
	 * Return the former files of sizes of the main external source of the given attachment.
	 *
	 * These are sizes, which have been replaced after the sizes have been created again with other dimensions.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_former_sizes( int $attachment_id ): array {
		// get the data.
		$data = $this->get_data( $attachment_id );

		// return the former files of the main external source.
		return array_values( $data['former'][ $data['main'] ] ?? array() );
	}

	/**
	 * Return whether the given attachment has exported sizes.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function has_sizes( int $attachment_id ): bool {
		return ! empty( $this->get_sizes( $attachment_id ) );
	}

	/**
	 * Delete the exported files of the given attachment on the given external source.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param int                 $term_id       The ID of the external source.
	 * @param Export_Base         $export_obj    The export object of the service.
	 * @param array<string,mixed> $credentials   The credentials of the external source.
	 *
	 * @return bool True if all files have been deleted.
	 */
	public function delete_sizes( int $attachment_id, int $term_id, Export_Base $export_obj, array $credentials ): bool {
		// get the data.
		$data = $this->get_data( $attachment_id );

		// bail if no files exist for this external source.
		if ( empty( $data['terms'][ $term_id ] ) && empty( $data['former'][ $term_id ] ) && empty( $data['variants'][ $term_id ] ) ) {
			return true;
		}

		// delete each file (several sizes could use the same file, it is deleted only once).
		$success       = true;
		$deleted_files = array();
		foreach ( $data['terms'][ $term_id ] ?? array() as $size_name => $size ) {
			// bail if this file has been deleted already.
			if ( in_array( (string) ( $size['target'] ?? $size['file'] ?? '' ), $deleted_files, true ) ) {
				unset( $data['terms'][ $term_id ][ $size_name ] );
				continue;
			}

			try {
				$deleted = $export_obj->delete_size( $attachment_id, (string) ( $size['url'] ?? '' ), (string) ( $size['target'] ?? '' ), $credentials );
			} catch ( \Throwable $e ) {
				$deleted = false;
			}

			// bail if it could not be deleted.
			if ( ! $deleted ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The exported size %1$s could not be deleted (maybe it does not exist).', 'external-files-in-media-library' ), '<code>' . esc_html( (string) ( $size['file'] ?? '' ) ) . '</code>' ), (string) ( $size['target'] ?? '' ), 'error' );
				$success = false;
				continue;
			}

			// remove it from the list.
			unset( $data['terms'][ $term_id ][ $size_name ] );
			$deleted_files[] = (string) ( $size['target'] ?? $size['file'] ?? '' );
		}

		// delete the former files on this external source.
		foreach ( $data['former'][ $term_id ] ?? array() as $index => $size ) {
			// bail if this file has been deleted already.
			if ( in_array( (string) ( $size['target'] ?? $size['file'] ?? '' ), $deleted_files, true ) ) {
				unset( $data['former'][ $term_id ][ $index ] );
				continue;
			}

			try {
				$deleted = $export_obj->delete_size( $attachment_id, (string) ( $size['url'] ?? '' ), (string) ( $size['target'] ?? '' ), $credentials );
			} catch ( \Throwable $e ) {
				$deleted = false;
			}

			// bail if it could not be deleted.
			if ( ! $deleted ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The exported size %1$s could not be deleted (maybe it does not exist).', 'external-files-in-media-library' ), '<code>' . esc_html( (string) ( $size['file'] ?? '' ) ) . '</code>' ), (string) ( $size['target'] ?? '' ), 'error' );
				$success = false;
				continue;
			}

			// remove it from the list.
			unset( $data['former'][ $term_id ][ $index ] );
			$deleted_files[] = (string) ( $size['target'] ?? $size['file'] ?? '' );
		}

		// delete the variants in other formats on this external source.
		foreach ( $data['variants'][ $term_id ] ?? array() as $name => $variant ) {
			try {
				$deleted = $export_obj->delete_size( $attachment_id, (string) ( $variant['url'] ?? '' ), (string) ( $variant['target'] ?? '' ), $credentials );
			} catch ( \Throwable $e ) {
				$deleted = false;
			}

			// bail if it could not be deleted.
			if ( ! $deleted ) {
				$success = false;
				continue;
			}

			// remove it from the list.
			unset( $data['variants'][ $term_id ][ $name ] );
		}
		if ( empty( $data['variants'][ $term_id ] ) ) {
			unset( $data['variants'][ $term_id ] );
		}

		// remove the external source, if all files have been deleted.
		if ( empty( $data['terms'][ $term_id ] ) ) {
			unset( $data['terms'][ $term_id ] );
		}
		if ( empty( $data['former'][ $term_id ] ) ) {
			unset( $data['former'][ $term_id ] );
		}
		if ( empty( $data['former'] ) ) {
			unset( $data['former'] );
		}

		// save the data.
		$this->save_data( $attachment_id, $data );

		// return the result.
		return $success;
	}

	/**
	 * Return whether the exported sizes of the given file could be delivered.
	 *
	 * Private files are usable, too: their sizes are always delivered via the proxy, as their URLs are not public.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return bool
	 */
	public function is_usable( File $external_file_obj ): bool {
		return $external_file_obj->is_valid() && ! $external_file_obj->is_locally_saved() && $this->has_sizes( $external_file_obj->get_id() );
	}

	/**
	 * Return the exported size, which matches the given requested size, or an empty array.
	 *
	 * @param int                   $attachment_id The attachment ID.
	 * @param array<int,int>|string $size          The requested size (name or width and height).
	 *
	 * @return array<string,mixed>
	 */
	public function find_size( int $attachment_id, array|string $size ): array {
		// get the sizes.
		$sizes = $this->get_sizes( $attachment_id );

		// use the size with the given name.
		if ( is_string( $size ) ) {
			return $sizes[ $size ] ?? array();
		}

		// get the requested dimensions.
		$width  = absint( $size[0] ?? 0 );
		$height = absint( $size[1] ?? 0 );

		// bail if no width is requested.
		if ( 0 === $width ) {
			return array();
		}

		// use the size with exactly these dimensions.
		foreach ( $sizes as $entry ) {
			if ( absint( $entry['width'] ?? 0 ) === $width && absint( $entry['height'] ?? 0 ) === $height ) {
				return $entry;
			}
		}

		// otherwise use the smallest size with the same ratio, which is at least as large.
		$result = array();
		foreach ( $sizes as $entry ) {
			$entry_width  = absint( $entry['width'] ?? 0 );
			$entry_height = absint( $entry['height'] ?? 0 );
			if ( $entry_width < $width || ( $height > 0 && ! wp_image_matches_ratio( $width, $height, $entry_width, $entry_height ) ) ) {
				continue;
			}
			if ( empty( $result ) || $entry_width < absint( $result['width'] ) ) {
				$result = $entry;
			}
		}

		// return the result.
		return $result;
	}

	/**
	 * Return the URL, under which the given exported size of the given file is delivered.
	 *
	 * Follows the proxy setting of the file exactly: the proxy URL of the size, if the file is delivered via
	 * the proxy, otherwise the URL of the size on the external source.
	 *
	 * @param File                $external_file_obj The external file object.
	 * @param array<string,mixed> $size              The exported size.
	 *
	 * @return string
	 */
	public function get_delivery_url( File $external_file_obj, array $size ): string {
		// get the URL of the file itself.
		$url = $external_file_obj->get_url();

		// use the URL on the external source, if the file is not delivered via the proxy (its public URL, e.g. for
		// files exported via FTP or to a local directory). Private files are always delivered via the proxy.
		if ( ! $external_file_obj->is_private() && $url === $external_file_obj->get_public_url() ) {
			return $external_file_obj->get_public_url( (string) ( $size['url'] ?? '' ) );
		}

		// get the title, which is the end of the proxy URL.
		$title = $external_file_obj->get_title();

		// bail if the proxy URL has an unknown format.
		if ( '' === $title || ! str_ends_with( $url, $title ) ) {
			return '';
		}

		// return the proxy URL of the size.
		return substr( $url, 0, -strlen( $title ) ) . Helper::generate_sizes_filename( $title, absint( $size['width'] ?? 0 ), absint( $size['height'] ?? 0 ), $external_file_obj->get_file_extension() );
	}

	/**
	 * Return the data of the given requested size of the given file for "image_downsize", or false.
	 *
	 * @param File                  $external_file_obj The external file object.
	 * @param array<int,int>|string $size              The requested size.
	 *
	 * @return array<int,mixed>|false
	 */
	public function image_downsize( File $external_file_obj, array|string $size ): array|false {
		// bail if the sizes could not be used.
		if ( ! $this->is_usable( $external_file_obj ) ) {
			return false;
		}

		// get the matching size.
		$entry = $this->find_size( $external_file_obj->get_id(), $size );

		// bail if no size matches.
		if ( empty( $entry ) ) {
			return false;
		}

		// get its URL.
		$url = $this->get_delivery_url( $external_file_obj, $entry );

		// bail if no URL could be detected.
		if ( '' === $url ) {
			return false;
		}

		// return the data.
		return array(
			$url,
			absint( $entry['width'] ),
			absint( $entry['height'] ),
			true,
		);
	}

	/**
	 * Save the exported size of the given file with the given dimensions in the cache of the proxy.
	 *
	 * Returns true, if the size is in the cache then. The proxy delivers it instead of creating it from the
	 * original image.
	 *
	 * @param File                  $external_file_obj The external file object.
	 * @param array<int,int|string> $dimensions        The requested width and height.
	 *
	 * @return bool
	 */
	public function cache_size( File $external_file_obj, array $dimensions ): bool {
		// bail if the sizes could not be used.
		if ( ! $this->is_usable( $external_file_obj ) ) {
			return false;
		}

		// bail if the dimensions are incomplete.
		if ( ! isset( $dimensions[0], $dimensions[1] ) ) {
			return false;
		}

		// get the requested dimensions as numbers.
		$dimensions = array( (int) $dimensions[0], (int) $dimensions[1] );

		// get the size with exactly these dimensions.
		$entry = $this->find_by_dimensions( $external_file_obj->get_id(), $dimensions );

		// bail if no size matches.
		if ( empty( $entry ) || empty( $entry['url'] ) ) {
			return false;
		}

		// get the cache file.
		$cache_file = $external_file_obj->get_cache_file( $dimensions );

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// bail if it is already cached.
		if ( $wp_filesystem->exists( $cache_file ) ) {
			return true;
		}

		// get the protocol handler of the file.
		$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

		// bail if it could not be loaded.
		if ( ! $protocol_handler_obj ) {
			return false;
		}

		// download the size.
		add_filter( 'efml_http_header_args', array( $external_file_obj, 'disable_check_for_unsafe_urls' ) );
		try {
			$tmp_file = $protocol_handler_obj->get_temp_file( (string) $entry['url'], $wp_filesystem );
		} catch ( \Throwable $e ) {
			$tmp_file = false;
		}
		remove_filter( 'efml_http_header_args', array( $external_file_obj, 'disable_check_for_unsafe_urls' ) );

		// bail if it could not be downloaded or is no image.
		if ( ! is_string( $tmp_file ) || ! $wp_filesystem->exists( $tmp_file ) || false === wp_get_image_mime( $tmp_file ) ) {
			if ( is_string( $tmp_file ) ) {
				$wp_filesystem->delete( $tmp_file );
			}
			Log::get_instance()->create( __( 'The exported size of the image could not be loaded for the proxy. It is created from the original image.', 'external-files-in-media-library' ), (string) $entry['url'], 'error' );
			return false;
		}

		// move it into the cache.
		wp_mkdir_p( dirname( $cache_file ) );
		if ( ! $wp_filesystem->move( $tmp_file, $cache_file, true ) ) {
			$wp_filesystem->delete( $tmp_file );
			return false;
		}

		// log this event.
		Log::get_instance()->create( __( 'The proxy has loaded the exported size of the image into its cache.', 'external-files-in-media-library' ), (string) $entry['url'], 'info', 2 );

		// return true as the size is cached now.
		return true;
	}

	/**
	 * Return the exported size of the given image with exactly the given dimensions.
	 *
	 * The actual sizes are searched first, then the former sizes, which contents could still use after the sizes
	 * have been created again with other dimensions.
	 *
	 * @param int                   $attachment_id The attachment ID.
	 * @param array<int,int|string> $dimensions    The dimensions (width, height).
	 *
	 * @return array<string,mixed> The size or an empty array.
	 */
	public function find_by_dimensions( int $attachment_id, array $dimensions ): array {
		// bail if the dimensions are incomplete.
		if ( ! isset( $dimensions[0], $dimensions[1] ) ) {
			return array();
		}

		// search the size.
		foreach ( array_merge( array_values( $this->get_sizes( $attachment_id ) ), $this->get_former_sizes( $attachment_id ) ) as $size ) {
			if ( absint( $size['width'] ?? 0 ) === absint( $dimensions[0] ) && absint( $size['height'] ?? 0 ) === absint( $dimensions[1] ) ) {
				return $size;
			}
		}

		// return an empty array as no size matches.
		return array();
	}

	/**
	 * Add the exported sizes to the metadata, which is used to calculate the srcset of the given image.
	 *
	 * For images in contents with their local URL, the local names are used: their URLs in the srcset are replaced
	 * during the output like all other local URLs (see Local_Urls). Otherwise the names of the delivered URLs are used
	 * and the URLs of the srcset are replaced via get_srcset().
	 *
	 * @param array<string,mixed> $image_meta        The metadata.
	 * @param string              $image_src         The URL of the image.
	 * @param File                $external_file_obj The external file object.
	 *
	 * @return array<string,mixed>
	 */
	public function add_sizes_to_srcset_meta( array $image_meta, string $image_src, File $external_file_obj ): array {
		// get the attachment ID.
		$attachment_id = $external_file_obj->get_id();

		// forget a former result for this image.
		unset( $this->srcset_urls[ $attachment_id ] );

		// bail if the sizes could not be used.
		if ( ! $this->is_usable( $external_file_obj ) ) {
			return $image_meta;
		}

		// get the sizes.
		$sizes = $this->get_sizes( $attachment_id );

		// get the local path of the image.
		$local_paths = Local_Urls::get_instance()->get_local_paths( $attachment_id );

		// use the local names for an image with its local URL (in contents).
		if ( ! empty( $local_paths ) && '' !== Local_Urls::get_instance()->get_relative_path( $image_src ) ) {
			$image_meta['file']  = $local_paths[0];
			$image_meta['sizes'] = array();
			foreach ( $sizes as $size_name => $size ) {
				$image_meta['sizes'][ $size_name ] = array(
					'file'      => (string) $size['file'],
					'width'     => absint( $size['width'] ),
					'height'    => absint( $size['height'] ),
					'mime-type' => (string) $size['mime-type'],
				);
			}
			$this->srcset_urls[ $attachment_id ] = 'local';
			return $image_meta;
		}

		// use the names of the delivered URLs otherwise.
		$main_url            = $external_file_obj->get_url();
		$image_meta['file']  = wp_basename( $main_url );
		$image_meta['sizes'] = array();
		$urls                = array( absint( $image_meta['width'] ?? 0 ) => $main_url );
		foreach ( $sizes as $size_name => $size ) {
			// get the URL of this size.
			$url = $this->get_delivery_url( $external_file_obj, $size );

			// bail if it is unknown.
			if ( '' === $url ) {
				continue;
			}

			// add it.
			$image_meta['sizes'][ $size_name ] = array(
				'file'      => wp_basename( $url ),
				'width'     => absint( $size['width'] ),
				'height'    => absint( $size['height'] ),
				'mime-type' => (string) $size['mime-type'],
			);
			$urls[ absint( $size['width'] ) ]  = $url;
		}
		$this->srcset_urls[ $attachment_id ] = $urls;

		// return the changed metadata.
		return $image_meta;
	}

	/**
	 * Return the sources of the srcset of the given image with the URLs of its exported sizes.
	 *
	 * Returns false, if the image has no exported sizes, which could be used.
	 *
	 * @param array<int,array<string,mixed>> $sources       The sources calculated by WordPress.
	 * @param int                            $attachment_id The attachment ID.
	 *
	 * @return array<int,array<string,mixed>>|false
	 */
	public function get_srcset( array $sources, int $attachment_id ): array|false {
		// bail if no sizes are known for this image.
		if ( ! isset( $this->srcset_urls[ $attachment_id ] ) ) {
			return false;
		}

		// get the URLs.
		$urls = $this->srcset_urls[ $attachment_id ];

		// use the local URLs in contents, they are replaced during the output.
		if ( 'local' === $urls ) {
			return $sources;
		}

		// replace the URLs.
		foreach ( $sources as $width => $source ) {
			// remove sources without known URL.
			if ( empty( $urls[ absint( $width ) ] ) ) {
				unset( $sources[ $width ] );
				continue;
			}

			// set the URL.
			$sources[ $width ]['url'] = $urls[ absint( $width ) ];
		}

		// return the sources.
		return $sources;
	}

	/**
	 * Add the srcset to the given exported image in a content, if it has exported sizes.
	 *
	 * WordPress adds the srcset only for images with sizes in their metadata, which exported images do not have.
	 * The srcset contains the local URLs like the image itself; they are replaced during the output (see Local_Urls).
	 *
	 * @param string $filtered_image The HTML of the image.
	 * @param string $context        The context.
	 * @param int    $attachment_id  The attachment ID.
	 *
	 * @return string
	 */
	public function add_srcset_to_content_image( string $filtered_image, string $context, int $attachment_id ): string {
		// bail if no attachment is known or the image has a srcset already.
		if ( $attachment_id <= 0 || str_contains( $filtered_image, ' srcset=' ) ) {
			return $filtered_image;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if its sizes could not be used.
		if ( ! $this->is_usable( $external_file_obj ) ) {
			return $filtered_image;
		}

		// get the source and the dimensions.
		$image_src = preg_match( '/src="([^"]+)"/', $filtered_image, $match_src ) ? (string) strtok( $match_src[1], '?' ) : '';
		$width     = preg_match( '/ width="([0-9]+)"/', $filtered_image, $match_width ) ? (int) $match_width[1] : 0;
		$height    = preg_match( '/ height="([0-9]+)"/', $filtered_image, $match_height ) ? (int) $match_height[1] : 0;

		// bail if they are unknown.
		if ( '' === $image_src || 0 === $width || 0 === $height ) {
			return $filtered_image;
		}

		// get the metadata.
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $image_meta ) ) {
			return $filtered_image;
		}

		// calculate the srcset (the metadata gets the exported sizes via the filter of the plugin).
		$size_array = array( $width, $height );
		$srcset     = wp_calculate_image_srcset( $size_array, $image_src, $image_meta, $attachment_id );

		// bail if no srcset could be calculated.
		if ( ! is_string( $srcset ) || '' === $srcset ) {
			return $filtered_image;
		}

		// get the sizes attribute.
		$attr  = sprintf( ' srcset="%s"', esc_attr( $srcset ) );
		$sizes = str_contains( $filtered_image, ' sizes=' ) ? false : wp_calculate_image_sizes( $size_array, $image_src, $image_meta, $attachment_id );
		if ( is_string( $sizes ) && '' !== $sizes ) {
			$attr .= sprintf( ' sizes="%s"', esc_attr( $sizes ) );
		}

		// add the attributes to the image.
		return (string) preg_replace( '/<img ([^>]+?)[\/ ]*>/', '<img $1' . $attr . ' />', $filtered_image, 1 );
	}

	/**
	 * Return the dimensions of an exported size of an image in contents, which WordPress could not detect.
	 *
	 * @param array<int,int>|false $dimensions    The dimensions.
	 * @param string               $image_src     The URL of the image.
	 * @param mixed                $image_meta    The metadata.
	 * @param int                  $attachment_id The attachment ID.
	 *
	 * @return array<int,int>|false
	 */
	public function get_dimensions_of_src( array|false $dimensions, string $image_src, mixed $image_meta, int $attachment_id ): array|false {
		// bail if dimensions are known or no attachment is given.
		if ( false !== $dimensions || $attachment_id <= 0 ) {
			return $dimensions;
		}

		// get the file name.
		$file_name = wp_basename( (string) strtok( $image_src, '?#' ) );

		// search it in the exported sizes.
		foreach ( $this->get_sizes( $attachment_id ) as $size ) {
			if ( $file_name === $size['file'] ) {
				return array( absint( $size['width'] ), absint( $size['height'] ) );
			}
		}

		// return the unchanged value.
		return $dimensions;
	}

	/**
	 * Return the IDs of the next exported images without exported sizes after the given attachment ID.
	 *
	 * @param int $after_id The attachment ID to start after.
	 * @param int $limit    The maximum number of images.
	 *
	 * @return array<int,int>
	 */
	public function get_images_without_sizes( int $after_id, int $limit ): array {
		global $wpdb;

		// get the images.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT p.`ID` FROM %i AS p INNER JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND p.`post_mime_type` LIKE %s AND p.`ID` > %d AND NOT EXISTS ( SELECT 1 FROM %i AS s WHERE s.`post_id` = p.`ID` AND s.`meta_key` IN ( %s, %s ) ) ORDER BY p.`ID` ASC LIMIT %d', $wpdb->posts, $wpdb->postmeta, 'eml_exported_file', 'attachment', 'image/%', $after_id, $wpdb->postmeta, self::META, Migrations::BACKUP_META, $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// return the list.
		return array_map( 'absint', $ids );
	}

	/**
	 * Return the number of exported images without exported sizes.
	 *
	 * @return int
	 */
	public function count_images_without_sizes(): int {
		global $wpdb;

		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i AS p INNER JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND p.`post_mime_type` LIKE %s AND NOT EXISTS ( SELECT 1 FROM %i AS s WHERE s.`post_id` = p.`ID` AND s.`meta_key` IN ( %s, %s ) )', $wpdb->posts, $wpdb->postmeta, 'eml_exported_file', 'attachment', 'image/%', $wpdb->postmeta, self::META, Migrations::BACKUP_META ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return whether the sizes of exported images could be exported at the moment.
	 *
	 * This is the case, if at least one usable external source with enabled export supports sizes.
	 *
	 * @return bool
	 */
	public function is_export_of_sizes_possible(): bool {
		// get the export object.
		$export_obj = Export::get_instance();

		// check the usable external sources.
		foreach ( $export_obj->get_usable_export_terms() as $term_id ) {
			$listing_obj = $export_obj->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );
			if ( $listing_obj instanceof \ExternalFilesInMediaLibrary\Services\Service_Base && $listing_obj->get_export_object() instanceof Export_Base && $listing_obj->get_export_object()->supports_sizes() ) {
				return true;
			}
		}

		// return false as no external source supports sizes.
		return false;
	}

	/**
	 * Create and export the sizes of the given exported image, which has been exported without them.
	 *
	 * The sizes are created from the local copy of the image, if it has been kept, otherwise from the image
	 * downloaded from its external source. The original image is exported, if its local copy exists.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool True if sizes have been exported.
	 */
	public function export_sizes_of_exported_image( int $attachment_id ): bool {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get its URL for the log.
		$url = $external_file_obj->get_url( true );

		// bail if this is not an exported image.
		if ( ! $external_file_obj->is_valid() || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		// get the external sources of the export, which support sizes (the main export source as last one).
		$sources = $this->get_export_sources_with_sizes( $attachment_id );

		// bail if no external source supports sizes.
		if ( empty( $sources ) ) {
			Log::get_instance()->create( __( 'The sizes of the image could not be exported, as none of its external sources supports sizes at the moment.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get the original paths of the image.
		$paths = Restore::get_instance()->get_original_paths( $attachment_id );

		// bail if they are unknown.
		if ( '' === $paths['file'] ) {
			Log::get_instance()->create( __( 'The sizes of the image could not be exported, as its original path is unknown.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// create a temporary directory.
		$directory = trailingslashit( get_temp_dir() ) . 'efml-sizes-' . wp_generate_password( 12, false ) . '/';
		wp_mkdir_p( $directory );

		// get the local files of the image, if they have been kept.
		$base_dir      = trailingslashit( wp_get_upload_dir()['basedir'] );
		$local_file    = $base_dir . $paths['file'];
		$local_orig    = '' !== $paths['original'] ? $base_dir . $paths['original'] : '';
		$source_name   = '' !== $paths['original'] ? wp_basename( $paths['original'] ) : wp_basename( $paths['file'] );
		$source        = $directory . $source_name;
		$files         = array();
		$has_original  = '' !== $local_orig && $wp_filesystem->exists( $local_orig );
		$has_local     = $wp_filesystem->exists( $local_file );

		// get the image to create the sizes from: the local original image, the local image or the download.
		if ( $has_original ) {
			$wp_filesystem->copy( $local_orig, $source, true );
		} elseif ( $has_local ) {
			$wp_filesystem->copy( $local_file, $source, true );
		} elseif ( ! Restore::get_instance()->download( $external_file_obj, $source ) ) {
			Helper::delete_directory_recursively( $directory );
			return false;
		}

		// create the sizes with their former names (they could differ by one pixel, if created from the scaled image).
		add_filter( 'big_image_size_threshold', '__return_false', PHP_INT_MAX );
		$editor = wp_get_image_editor( $source );
		$sizes  = is_wp_error( $editor ) ? array() : $editor->multi_resize( wp_get_registered_image_subsizes() );
		remove_filter( 'big_image_size_threshold', '__return_false', PHP_INT_MAX );
		$sizes = Restore::get_instance()->rename_sizes( $sizes, Local_Urls::get_instance()->get_local_sizes( $attachment_id ), $directory );
		foreach ( $sizes as $size_name => $size ) {
			$size = (array) $size;
			if ( empty( $size['file'] ) ) {
				continue;
			}
			$files[ (string) $size_name ] = array(
				'file'      => wp_basename( (string) $size['file'] ),
				'width'     => isset( $size['width'] ) ? absint( $size['width'] ) : 0,
				'height'    => isset( $size['height'] ) ? absint( $size['height'] ) : 0,
				'mime-type' => isset( $size['mime-type'] ) ? (string) $size['mime-type'] : '',
			);
		}

		// add the original image, if its local copy exists.
		if ( $has_original ) {
			$files[ self::ORIGINAL ] = array(
				'file'      => $source_name,
				'width'     => 0,
				'height'    => 0,
				'mime-type' => (string) get_post_mime_type( $attachment_id ),
			);
		}

		// export them to each external source.
		$exported = 0;
		foreach ( $sources as $term_id => $export_obj ) {
			// get the credentials.
			$credentials            = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id );
			$credentials['private'] = $external_file_obj->is_private();

			// get the folder of the image on this external source.
			$folders = get_post_meta( $attachment_id, 'efml_export_folders', true );
			$folder  = is_array( $folders ) && ! empty( $folders[ $term_id ] ) ? trailingslashit( (string) $folders[ $term_id ] ) : '';

			// export the files.
			$exported += $this->export_files( $attachment_id, $export_obj, $term_id, $directory, $files, trailingslashit( (string) get_term_meta( $term_id, 'path', true ) ) . $folder, $credentials );

			// export the variants in other formats, which still exist locally (e.g. their export has failed before).
			$exported += Variants::get_instance()->export( $attachment_id, $export_obj, $term_id, trailingslashit( dirname( $local_file ) ), trailingslashit( (string) get_term_meta( $term_id, 'path', true ) ) . $folder, $credentials, $this->get_local_base_files( $attachment_id, $paths ), (string) get_post_meta( $attachment_id, 'efml_export_filename', true ) );
		}

		// remove the local files of the exported variants.
		Variants::get_instance()->delete_local_files( $attachment_id );

		// remove the temporary files.
		Helper::delete_directory_recursively( $directory );

		// remove the sizes in the cache of the proxy, so the exported sizes are used.
		if ( $exported > 0 ) {
			$external_file_obj->delete_thumbs();
		}

		// return the result.
		return $exported > 0;
	}

	/**
	 * Return the names of the local files of an exported image before its export (size name or "full" => file name).
	 *
	 * @param int                                   $attachment_id The attachment ID.
	 * @param array{file: string, original: string} $paths         The original paths relative to the uploads directory.
	 *
	 * @return array<string,string>
	 */
	private function get_local_base_files( int $attachment_id, array $paths ): array {
		// add the image itself.
		$files = array( Variants::FULL => wp_basename( $paths['file'] ) );

		// add the sizes with their local names.
		foreach ( Local_Urls::get_instance()->get_local_sizes( $attachment_id ) as $size_name => $file ) {
			$files[ (string) $size_name ] = wp_basename( (string) $file );
		}

		// add the original image.
		if ( '' !== $paths['original'] ) {
			$files[ self::ORIGINAL ] = wp_basename( $paths['original'] );
		}

		// return the list.
		return $files;
	}

	/**
	 * Return the usable external sources of the given exported file, whose services support sizes.
	 *
	 * The main export source of the file is the last one in the list (term ID => export object).
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $require_sizes True to return only external sources, whose services support sizes.
	 *
	 * @return array<int,Export_Base>
	 */
	public function get_export_sources_with_sizes( int $attachment_id, bool $require_sizes = true ): array {
		// get the external sources of the export.
		$term_ids = get_post_meta( $attachment_id, 'efml_export_sources', true );
		if ( ! is_array( $term_ids ) ) {
			$term_ids = array();
		}

		// get the main external source of the file (the one it is assigned to).
		$main_terms = wp_get_object_terms( $attachment_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(), array( 'fields' => 'ids' ) );
		$main       = is_array( $main_terms ) && ! empty( $main_terms ) ? absint( $main_terms[0] ) : 0;

		// collect the usable ones.
		$export_obj = Export::get_instance();
		$sources    = array();
		foreach ( array_unique( array_map( 'absint', $term_ids ) ) as $term_id ) {
			// bail if it is not usable.
			if ( ! $export_obj->is_export_source_usable( $term_id ) ) {
				continue;
			}

			// get its export object.
			$listing_obj = $export_obj->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );
			if ( ! $listing_obj instanceof \ExternalFilesInMediaLibrary\Services\Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base || ( $require_sizes && ! $listing_obj->get_export_object()->supports_sizes() ) ) {
				continue;
			}

			// add it.
			$sources[ $term_id ] = $listing_obj->get_export_object();
		}

		// move the main export source to the end.
		if ( isset( $sources[ $main ] ) ) {
			$main_obj = $sources[ $main ];
			unset( $sources[ $main ] );
			$sources[ $main ] = $main_obj;
		}

		// return the list.
		return $sources;
	}
}
