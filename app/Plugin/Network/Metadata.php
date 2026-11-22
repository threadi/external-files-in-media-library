<?php
/**
 * File to pass caption, description and alternative text of the central media library to the websites.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Post;

/**
 * Object, which handles caption, description and alternative text of the files of the central media library.
 *
 * The values of the central media library are used in every website. A website could change them for itself: these
 * changes are saved on the copy and kept, also if the central file is changed or synchronized again. They could be
 * discarded on the copy, then the values of the central media library are used again.
 */
class Metadata {
	/**
	 * The fields, which are passed to the websites, with the field of the post (or the meta key).
	 *
	 * The title is not passed: the title of an external file is its file name, which its URL in the proxy uses
	 * (e.g. "/emlproxy/image.jpg"). A title like "Image: 1" would result in a URL, which the proxy could not deliver.
	 */
	private const FIELDS = array(
		'caption'     => 'post_excerpt',
		'description' => 'post_content',
		'alt'         => '_wp_attachment_image_alt',
	);

	/**
	 * Marker whether this object changes values at this moment.
	 *
	 * @var bool
	 */
	private bool $applying = false;

	/**
	 * List of copies, whose values should get the values of the central media library at the end of the request.
	 *
	 * @var array<int,int>
	 */
	private array $resets = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Metadata
	 */
	private static ?Metadata $instance = null;

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
	 * @return Metadata
	 */
	public static function get_instance(): Metadata {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// save the own changes of the websites.
		add_action( 'attachment_updated', array( $this, 'save_own_changes' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'save_own_alt' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'save_own_alt' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'save_own_alt' ), 10, 3 );

		// show the hint and the option to discard the own changes.
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_field' ), 20, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_field' ), 20, 2 );
	}

	/**
	 * Return the labels of the fields.
	 *
	 * @return array<string,string>
	 */
	private function get_labels(): array {
		return array(
			'caption'     => __( 'Caption', 'external-files-in-media-library' ),
			'description' => __( 'Description', 'external-files-in-media-library' ),
			'alt'         => __( 'Alternative text', 'external-files-in-media-library' ),
		);
	}

	/**
	 * Return the values of the given attachment of the actual website.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,string>
	 */
	public function get_values( int $attachment_id ): array {
		// get the post.
		$post = get_post( $attachment_id );

		// bail if it does not exist.
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		// return the values.
		return array(
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			'alt'         => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Return the own changes of the given copy.
	 *
	 * @param int $copy_id The attachment ID in the actual website.
	 *
	 * @return array<string,string>
	 */
	public function get_overrides( int $copy_id ): array {
		// get the saved changes.
		$overrides = get_post_meta( $copy_id, Media_Library::META_OVERRIDES, true );

		// bail if there are none.
		if ( ! is_array( $overrides ) ) {
			return array();
		}

		// return the known fields.
		return array_map( 'strval', array_intersect_key( $overrides, self::FIELDS ) );
	}

	/**
	 * Use the given values of the central media library for the given copy, except the own changes of the website.
	 *
	 * @param int                  $copy_id The attachment ID in the actual website.
	 * @param array<string,string> $values  The values of the central file.
	 *
	 * @return void
	 */
	public function apply( int $copy_id, array $values ): void {
		// get the actual values.
		$actual = $this->get_values( $copy_id );

		// bail if the copy does not exist.
		if ( empty( $actual ) ) {
			return;
		}

		// get the values to use: the own changes of this website are kept.
		$values = array_merge( array_intersect_key( $values, self::FIELDS ), $this->get_overrides( $copy_id ) );

		// collect the changed fields of the post.
		$post_array = array();
		foreach ( array( 'caption', 'description' ) as $field ) {
			if ( isset( $values[ $field ] ) && $values[ $field ] !== $actual[ $field ] ) {
				$post_array[ self::FIELDS[ $field ] ] = $values[ $field ];
			}
		}

		// mark the change.
		$this->applying = true;

		try {
			// update the post.
			if ( ! empty( $post_array ) ) {
				$post_array['ID'] = $copy_id;
				wp_update_post( wp_slash( $post_array ) );
			}

			// update the alternative text.
			if ( isset( $values['alt'] ) && $values['alt'] !== $actual['alt'] ) {
				if ( '' === $values['alt'] ) {
					delete_post_meta( $copy_id, '_wp_attachment_image_alt' );
				} else {
					update_post_meta( $copy_id, '_wp_attachment_image_alt', wp_slash( $values['alt'] ) );
				}
			}
		} finally {
			$this->applying = false;
		}
	}

	/**
	 * Return whether the actual change of an attachment is made by a user in the backend.
	 *
	 * Changes by the plugin (synchronization, import, the passing of changes) or by other code are not own changes:
	 * only the saving of a file in the media library, on its edit page or via REST API is one.
	 *
	 * @return bool
	 */
	private function is_user_change(): bool {
		// bail if this object or the propagation changes the values.
		if ( $this->applying || Propagation::get_instance()->is_running() ) {
			return false;
		}

		// bail during a synchronization (e.g. started via the REST API of this plugin).
		if ( \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->is_running() ) {
			return false;
		}

		// changes of the file via the REST API of WordPress (e.g. in the block editor).
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			global $wp;
			return $wp instanceof \WP && isset( $wp->query_vars['rest_route'] ) && str_starts_with( (string) $wp->query_vars['rest_route'], '/wp/v2/media' );
		}

		// get the requested action.
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked by WordPress for these actions.

		// changes in the media library (via AJAX) or on the edit page of the file.
		return ( wp_doing_ajax() && in_array( $action, array( 'save-attachment', 'save-attachment-compat' ), true ) ) || ( is_admin() && 'editpost' === $action );
	}

	/**
	 * Save the own changes of caption and description of a copy in a website.
	 *
	 * @param int     $post_id     The attachment ID.
	 * @param WP_Post $post_after  The attachment after the change.
	 * @param WP_Post $post_before The attachment before the change.
	 *
	 * @return void
	 */
	public function save_own_changes( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		// bail if this is not a change by a user.
		if ( ! $this->is_user_change() ) {
			return;
		}

		// bail if this is not a copy of a file of the central media library.
		if ( 0 === Media_Library::get_instance()->get_central_id( $post_id ) ) {
			return;
		}

		// collect the changed fields.
		$overrides = $this->get_overrides( $post_id );
		$changes   = array();
		foreach ( array( 'caption', 'description' ) as $field ) {
			$key = self::FIELDS[ $field ];
			if ( $post_after->$key !== $post_before->$key ) {
				$overrides[ $field ] = (string) $post_after->$key;
				$changes[ $field ]   = (string) $post_after->$key;
			}
		}

		// pass the changes of the website, from which the file comes, to the central media library, if enabled.
		if ( ! empty( $changes ) && $this->push_to_central( $post_id, $changes ) ) {
			return;
		}

		// save them.
		if ( ! empty( $changes ) ) {
			update_post_meta( $post_id, Media_Library::META_OVERRIDES, $overrides );
		}
	}

	/**
	 * Pass the given changes of an own upload of the actual website to the central media library, from where they are
	 * passed to all websites. Returns false, if this is disabled or if the file is not an own upload.
	 *
	 * @param int                  $attachment_id The attachment ID in the actual website.
	 * @param array<string,string> $changes       The changed fields with their new values.
	 *
	 * @return bool
	 */
	public function push_to_central( int $attachment_id, array $changes ): bool {
		// bail if this is disabled or if the file is not an own upload of this website.
		if ( 1 !== (int) Media_Library::get_instance()->get_setting( 'origin_edits' ) || ! Media_Library::get_instance()->is_own_upload( $attachment_id ) ) {
			return false;
		}

		// get the file in the central media library.
		$central_id = Media_Library::get_instance()->get_central_id( $attachment_id );
		if ( 0 === $central_id ) {
			return false;
		}

		// change it there: the propagation passes it to all websites, which use it.
		switch_to_blog( Media_Library::get_instance()->get_central_site_id() );
		$this->apply( $central_id, array_merge( $this->get_values( $central_id ), $changes ) );
		restore_current_blog();

		// forget former own changes of these fields: the values are the same for all websites now.
		$overrides = array_diff_key( $this->get_overrides( $attachment_id ), $changes );
		if ( empty( $overrides ) ) {
			delete_post_meta( $attachment_id, Media_Library::META_OVERRIDES );
		} else {
			update_post_meta( $attachment_id, Media_Library::META_OVERRIDES, $overrides );
		}

		// return success.
		return true;
	}

	/**
	 * Save the own change of the alternative text of a copy in a website.
	 *
	 * @param int|array<int,int> $meta_id   The meta ID(s).
	 * @param int                $object_id The attachment ID.
	 * @param string             $meta_key  The meta key.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function save_own_alt( int|array $meta_id, int $object_id, string $meta_key ): void {
		// bail if this is not the alternative text.
		if ( '_wp_attachment_image_alt' !== $meta_key ) {
			return;
		}

		// bail if this is not a change by a user.
		if ( ! $this->is_user_change() ) {
			return;
		}

		// bail if this is not a copy of a file of the central media library.
		if ( 0 === Media_Library::get_instance()->get_central_id( $object_id ) ) {
			return;
		}

		// pass the change of the website, from which the file comes, to the central media library, if enabled.
		$alt = (string) get_post_meta( $object_id, '_wp_attachment_image_alt', true );
		if ( $this->push_to_central( $object_id, array( 'alt' => $alt ) ) ) {
			return;
		}

		// save the change.
		$overrides        = $this->get_overrides( $object_id );
		$overrides['alt'] = $alt;
		update_post_meta( $object_id, Media_Library::META_OVERRIDES, $overrides );
	}

	/**
	 * Add the hint and the option to discard the own changes to the fields of a copy in a website.
	 *
	 * @param array<string,array<string,mixed>> $form_fields The fields.
	 * @param WP_Post                           $post        The attachment.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_field( array $form_fields, WP_Post $post ): array {
		// bail if this is not a copy of a file of the central media library.
		if ( 0 === Media_Library::get_instance()->get_central_id( $post->ID ) ) {
			return $form_fields;
		}

		// create the hint.
		$html = '<p>' . esc_html__( 'Caption, description and alternative text come from the central media library. Your changes apply only to this website.', 'external-files-in-media-library' ) . '</p>';
		if ( 1 === (int) Media_Library::get_instance()->get_setting( 'origin_edits' ) && Media_Library::get_instance()->is_own_upload( $post->ID ) ) {
			$html = '<p>' . esc_html__( 'This file has been uploaded in this website. Your changes of caption, description and alternative text apply to all websites.', 'external-files-in-media-library' ) . '</p>';
		}

		// show the own changes and the option to discard them.
		$overrides = $this->get_overrides( $post->ID );
		if ( ! empty( $overrides ) ) {
			$labels = array_intersect_key( $this->get_labels(), $overrides );
			/* translators: %1$s will be replaced by a list of fields. */
			$html .= '<p>' . esc_html( sprintf( __( 'Changed in this website: %1$s', 'external-files-in-media-library' ), implode( ', ', $labels ) ) ) . '</p>';
			$html .= '<label><input type="checkbox" name="attachments[' . absint( $post->ID ) . '][efml_network_reset]" value="1"> ' . esc_html__( 'Discard the changes and use the values of the central media library', 'external-files-in-media-library' ) . '</label>';
		}

		// add the field.
		$form_fields['efml_network_metadata'] = array(
			'label' => __( 'Central media library', 'external-files-in-media-library' ),
			'input' => 'html',
			'html'  => $html,
		);

		// return the fields.
		return $form_fields;
	}

	/**
	 * Discard the own changes of a copy, if requested.
	 *
	 * The values of the central media library are used at the end of the request, as WordPress saves the
	 * submitted values after this filter.
	 *
	 * @param array<string,mixed> $post       The attachment data.
	 * @param array<string,mixed> $attachment The submitted fields.
	 *
	 * @return array<string,mixed>
	 */
	public function save_field( array $post, array $attachment ): array {
		// bail if the reset is not requested.
		if ( empty( $attachment['efml_network_reset'] ) || empty( $post['ID'] ) ) {
			return $post;
		}

		// bail if the user could not edit this file.
		if ( ! current_user_can( 'edit_post', absint( $post['ID'] ) ) ) {
			return $post;
		}

		// remove the own changes.
		delete_post_meta( absint( $post['ID'] ), Media_Library::META_OVERRIDES );

		// use the values of the central media library at the end of the request.
		$this->resets[] = absint( $post['ID'] );
		if ( 1 === count( $this->resets ) ) {
			add_action( 'shutdown', array( $this, 'run_resets' ) );
		}

		// return the data.
		return $post;
	}

	/**
	 * Use the values of the central media library for the copies, whose own changes have been discarded.
	 *
	 * @return void
	 */
	public function run_resets(): void {
		// get the list and reset it.
		$resets       = $this->resets;
		$this->resets = array();

		// update each copy.
		foreach ( $resets as $copy_id ) {
			$this->reset( $copy_id );
		}
	}

	/**
	 * Use the values of the central media library for the given copy.
	 *
	 * @param int $copy_id The attachment ID in the actual website.
	 *
	 * @return void
	 */
	public function reset( int $copy_id ): void {
		// get the central file.
		$central_id = Media_Library::get_instance()->get_central_id( $copy_id );

		// bail if it is unknown.
		if ( 0 === $central_id ) {
			return;
		}

		// remove the own changes.
		delete_post_meta( $copy_id, Media_Library::META_OVERRIDES );

		// get the values of the central file.
		switch_to_blog( Media_Library::get_instance()->get_central_site_id() );
		$values = $this->get_values( $central_id );
		restore_current_blog();

		// use them.
		if ( ! empty( $values ) ) {
			$this->apply( $copy_id, $values );
		}
	}
}
