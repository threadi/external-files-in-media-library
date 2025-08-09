<?php
/**
 * File to handle support for plugin "Exmage".
 *
 * @source https://wordpress.org/plugins/exmage-wp-image-links/
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Post;
use WP_Query;

/**
 * Object to handle support for this plugin.
 */
class Exmage extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Exmage
	 */
	private static ?Exmage $instance = null;

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
	 * @return Exmage
	 */
	public static function get_instance(): Exmage {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if Exmage is not active.
		if ( ! Helper::is_plugin_active( 'exmage-wp-image-links/exmage-wp-image-links.php' ) ) {
			return;
		}

		// misc.
		add_action( 'admin_init', array( $this, 'show_hint' ) );
		add_action( 'admin_action_eml_migrate_exmage', array( $this, 'migrate_per_request' ) );
		add_action(
			'cli_init',
			function () {
				\WP_CLI::add_command( 'eml', 'ExternalFilesInMediaLibrary\ThirdParty\Cli\Exmage' );
			}
		);
	}

	/**
	 * Show hint that Exmage is used and we could migrate.
	 *
	 * @return void
	 */
	public function show_hint(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// bail if no exmage files are found.
		if ( empty( $this->get_files() ) ) {
			$transients_obj->get_transient_by_name( 'eml_exmage_migrate' )->delete();
			return;
		}

		// create URL for migration per click.
		$url = add_query_arg(
			array(
				'action' => 'eml_migrate_exmage',
				'nonce'  => wp_create_nonce( 'eml-migrate-exmage' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$dialog = array(
			'title'   => __( 'Migrate from Exmage to External Files for Media Library', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Are you sure you want to migrate your files?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'After the migration you will not be able to use the Exmage functions on your files. But you could use the features of External Files for Media Library.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'Hint: create a backup before you run this migration.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes, migrate', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'No', 'external-files-in-media-library' ),
				),
			),
		);

		// prepare dialog for attribute.
		$dialog = wp_json_encode( $dialog );

		// bail if preparation does not worked.
		if ( ! $dialog ) {
			return;
		}

		// create hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'eml_exmage_migrate' );
		$transient_obj->set_message( __( '<strong>We detected that you are using Exmage - great!</strong> Click on the following button to migrate the Exmage-files.', 'external-files-in-media-library' ) . ' <a href="' . esc_url( $url ) . '" class="button button-primary easy-dialog-for-wordpress" data-dialog="' . esc_attr( $dialog ) . '">' . __( 'Migrate now', 'external-files-in-media-library' ) . '</a>' );
		$transient_obj->set_dismissible_days( 30 );
		$transient_obj->save();
	}

	/**
	 * Return list of all exmage files.
	 *
	 * @return array<int>
	 */
	public function get_files(): array {
		// get all Exmage files.
		$query   = array(
			'post_type'      => 'attachment',
			'post_status'    => array( 'inherit', 'trash' ),
			'meta_query'     => array(
				array(
					'key'     => '_exmage_external_url',
					'compare' => 'EXIST',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$results = new WP_Query( $query );

		// bail on no results.
		if ( 0 === $results->post_count ) {
			return array();
		}

		// create the resulting list to create a clean return array.
		$return_array = array();
		foreach ( $results->get_posts() as $post_id ) {
			if ( $post_id instanceof WP_Post ) {
				$post_id = $post_id->ID;
			}
			$return_array[] = $post_id;
		}
		return $return_array;
	}

	/**
	 * Migrate per request in backend.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function migrate_per_request(): void {
		// check nonce.
		check_admin_referer( 'eml-migrate-exmage', 'nonce' );

		// run the migration.
		$this->migrate();

		// get referer.
		$referer = wp_get_referer();

		// if referer is false, set empty string.
		if ( ! $referer ) {
			$referer = '';
		}

		// redirect user back.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function migrate(): void {
		// get the files.
		$posts = $this->get_files();

		// bail if no results found.
		if ( empty( $posts ) ) {
			Helper::is_cli() ? \WP_CLI::success( 'No exmage files found.' ) : '';
			return;
		}

		// show progress.
		$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( 'Migrate vom Exmage', count( $posts ) ) : false;

		// loop through them.
		foreach ( $posts as $post_id ) {
			// get the external URL.
			$url = get_post_meta( $post_id, '_exmage_external_url', true );

			// bail if URL is not given.
			if ( empty( $url ) ) {
				// show progress.
				$progress ? $progress->tick() : '';
				continue;
			}

			// bail if URL is not a string.
			if ( ! is_string( $url ) ) {
				// show progress.
				$progress ? $progress->tick() : '';
				continue;
			}

			// get external files object for this post.
			$external_files_obj = Files::get_instance()->get_file( $post_id );

			// set the URL.
			$external_files_obj->set_url( $url );

			// get the protocol handler for this file.
			$protocol_handler = $external_files_obj->get_protocol_handler_obj();

			// bail if protocol is not supported.
			if ( ! $protocol_handler ) {
				// show progress.
				$progress ? $progress->tick() : '';
				continue;
			}

			// check and set availability.
			$external_files_obj->set_availability( $protocol_handler->check_availability( $url ) );

			// set the meta-data for this file.
			$external_files_obj->set_metadata();

			// remove exmage marker from this item.
			delete_post_meta( $post_id, '_exmage_external_url' );

			// show progress.
			$progress ? $progress->tick() : '';
		}

		// finish progress.
		$progress ? $progress->finish() : '';
	}
}
