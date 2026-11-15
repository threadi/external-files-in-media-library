<?php
/**
 * File to handle support for the plugin "Imagify".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Delayed_Uploads;

/**
 * Object to handle support for this plugin.
 *
 * It optimizes the local files of uploads after the upload via its API, so their export waits until it reports the
 * end of the optimization. Its variants in other formats are named like "image.jpg.webp" and "image.jpg.avif".
 */
class Imagify extends Image_Optimizer_Base {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Imagify
	 */
	private static ?Imagify $instance = null;

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
	 * @return Imagify
	 */
	public static function get_instance(): Imagify {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the main files of the plugin, relative to the plugin directory.
	 *
	 * @return array<int,string>
	 */
	protected function get_plugin_files(): array {
		return array( 'imagify/imagify.php' );
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		parent::add_hooks();

		// wait for the end of the optimization of new uploads via the API of Imagify.
		add_action( 'imagify_after_auto_optimization_init', array( $this, 'start' ) );
		add_action( 'imagify_after_optimize', array( $this, 'end' ) );
	}

	/**
	 * Mark an upload as busy, as Imagify will optimize it.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function start( int $attachment_id ): void {
		Delayed_Uploads::get_instance()->set_busy( $attachment_id );
	}

	/**
	 * Mark the end of the optimization of an upload, so its export could run.
	 *
	 * @param mixed $process The optimization process of Imagify.
	 *
	 * @return void
	 */
	public function end( mixed $process ): void {
		// get the media of the process.
		$media = is_object( $process ) && method_exists( $process, 'get_media' ) ? $process->get_media() : false;

		// bail if it is not usable.
		if ( ! is_object( $media ) || ! method_exists( $media, 'get_id' ) ) {
			return;
		}

		// mark the end.
		Delayed_Uploads::get_instance()->done( absint( $media->get_id() ) );
	}

}
