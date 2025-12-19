<?php
/**
 * This file controls how to import external files for real in media library without external connection.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;
use WP_Query;

/**
 * Handler controls how to import external files for real in media library without external connection.
 */
class Real_Import extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'real_import';

	/**
	 * Instance of actual object.
	 *
	 * @var Real_Import|null
	 */
	private static ?Real_Import $instance = null;

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
	 * @return Real_Import
	 */
	public static function get_instance(): Real_Import {
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
		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// use our own hooks.
		add_filter( 'efml_http_save_local', array( $this, 'import_local_on_real_import' ) );
		add_filter( 'efml_file_import_attachment', array( $this, 'add_title_on_real_import' ), 10, 3 );
		add_filter( 'efml_import_no_external_file', array( $this, 'save_file_local' ), 10, 0 );
		add_filter( 'efml_add_dialog', array( $this, 'add_option_in_form' ), 10, 2 );
		add_filter( 'efml_import_options', array( $this, 'add_import_option_to_list' ) );
		add_action( 'efml_cli_arguments', array( $this, 'check_cli_arguments' ) );
		add_filter( 'efml_user_settings', array( $this, 'add_user_setting' ) );
		add_action( 'efml_show_file_info', array( $this, 'add_option_to_real_import_file' ) );
		add_filter( 'efml_external_files_infos', array( $this, 'check_for_duplicate' ) );

		// sync tasks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_option_on_sync_config' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
		add_action( 'efml_after_file_save', array( $this, 'delete_mark_as_synced' ), 20 );

		// misc.
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 20, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'run_bulk_action' ), 10, 3 );

		// actions.
		add_action( 'admin_action_eml_real_import_external_file', array( $this, 'import_local_per_request' ) );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Really import each file', 'external-files-in-media-library' );
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the advanced section.
		$advanced_tab_advanced = $settings_obj->get_section( 'settings_section_dialog' );

		// bail if section could not be loaded.
		if ( ! $advanced_tab_advanced ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_real_import' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => $this->get_title(),
				'description' => __( 'When this option is enabled, each external URL is imported as a real file into your media library. They are then no longer “external files” in your media library. If “User-specific settings” is enabled, this setting can be overridden by each user.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
	}

	/**
	 * Return true if real import is enabled to force local saving of each file.
	 *
	 * @param bool $result The result.
	 *
	 * @return bool
	 */
	public function import_local_on_real_import( bool $result ): bool {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// bail if it is not enabled during import.
		if ( 1 !== $real_import ) {
			return $result;
		}

		// return true to mark this as local importing file.
		return true;
	}

	/**
	 * Add title for file if real import is enabled.
	 *
	 * @param array<string,mixed> $post_array The attachment settings.
	 * @param string              $url        The requested external URL.
	 * @param array<string,mixed> $file_data  List of file settings detected by importer.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_title_on_real_import( array $post_array, string $url, array $file_data ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// bail if it is not enabled during import.
		if ( 1 !== $real_import ) {
			return $post_array;
		}

		// add the title.
		$post_array['post_title'] = $file_data['title'];

		// return the resulting array.
		return $post_array;
	}

	/**
	 * Save file local if setting during import or global is enabled.
	 *
	 * @return bool
	 */
	public function save_file_local(): bool {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// return whether to import this file as real file and not external.
		return 1 === $real_import;
	}

	/**
	 * Add a checkbox to mark the files to add them real and not as external files.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_in_form( array $dialog, array $settings ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $dialog;
		}

		// get the actual state for the checkbox.
		$checked = 1 === absint( get_option( 'eml_real_import' ) );

		// if user has its own setting, use this.
		if ( ImportDialog::get_instance()->is_customization_allowed() ) {
			$checked = 1 === absint( get_user_meta( get_current_user_id(), 'efml_' . $this->get_name(), true ) );
		}

		// detect count of URLs depending on slash at the end of the given URL.
		$url_count = 1;
		if ( ! empty( $settings['urls'] ) && str_ends_with( $settings['urls'], '/' ) ) {
			$url_count = 2;
		}

		// collect the entry.
		$text = '<label for="real_import"><input type="checkbox" name="real_import" id="real_import" value="1" class="eml-use-for-import"' . ( $checked ? ' checked="checked"' : '' ) . '> ' . _n( 'Import the external file as a real file. The file will then no longer be treated as an external file.', 'Import the external files as real files. They will not be treated as external files afterwards.', $url_count, 'external-files-in-media-library' );

		// add link to user settings.
		$url   = add_query_arg(
			array(),
			get_admin_url() . 'profile.php'
		);
		$text .= '<a href="' . esc_url( $url ) . '#efml-settings" target="_blank" title="' . esc_attr__( 'Go to user settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-users"></span></a>';

		// add link to global settings.
		if ( current_user_can( 'manage_options' ) ) {
			$text .= '<a href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_advanced' ) ) . '" target="_blank" title="' . esc_attr__( 'Go to plugin settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
		}

		// end the text.
		$text .= '</label>';

		// add the field.
		$dialog['texts'][] = $text;

		// return the resulting fields.
		return $dialog;
	}

	/**
	 * Add option to the list of all options used during an import, if set.
	 *
	 * @param array<string,mixed> $options List of options.
	 *
	 * @return array<string,mixed>
	 */
	public function add_import_option_to_list( array $options ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// bail if our option is not set.
		if ( ! isset( $_POST['real_import'] ) ) {
			return $options;
		}

		// add the option to the list.
		$options['real_import'] = absint( $_POST['real_import'] );

		return $options;
	}

	/**
	 * Check the WP CLI arguments before import of URLs there.
	 *
	 * @param array<string,mixed> $arguments List of WP CLI arguments.
	 *
	 * @return void
	 */
	public function check_cli_arguments( array $arguments ): void {
		$_POST['real_import'] = isset( $arguments['real_import'] ) ? 1 : 0;
	}

	/**
	 * Add option for the user-specific setting.
	 *
	 * @param array<string,array<string,mixed>> $settings List of settings.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_user_setting( array $settings ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $settings;
		}

		// add our setting.
		$settings[ $this->get_name() ] = array(
			'label'       => __( 'Really import each file', 'external-files-in-media-library' ),
			'description' => __( 'Files are not imported as external files.', 'external-files-in-media-library' ),
			'field'       => 'checkbox',
		);

		// return the settings.
		return $settings;
	}

	/**
	 * Add option to real import an external file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function add_option_to_real_import_file( File $external_file_obj ): void {
		// bail if capability is not set.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// create the import URL.
		$url = add_query_arg(
			array(
				'action' => 'eml_real_import_external_file',
				'nonce'  => wp_create_nonce( 'eml-real-import-external-file' ),
				'post'   => $external_file_obj->get_id(),
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$dialog = array(
			/* translators: %1$s will be replaced by the file name. */
			'title'   => sprintf( __( 'Import %1$s as real file', 'external-files-in-media-library' ), $external_file_obj->get_title() ),
			'texts'   => array(
				/* translators: %1$s will be replaced by the file name. */
				'<p><strong>' . sprintf( __( 'Are you sure you want to import %1$s in your media library?', 'external-files-in-media-library' ), '<code>' . $external_file_obj->get_title() . '</code>' ) . '</strong></p>',
				'<p>' . __( 'The file will be saved as real file in your media library without external connections.', 'external-files-in-media-library' ) . '</p>',
				/* translators: %1$s will be replaced by the plugin name. */
				'<p>' . sprintf( __( 'It will then no longer be managed by %1$s for you.', 'external-files-in-media-library' ), '<em>' . Helper::get_plugin_name() . '</em>' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes, import', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'No', 'external-files-in-media-library' ),
				),
			),
		);

		?>
		<li>
			<span id="eml_url_real_import"><span class="dashicons dashicons-database-import"></span> <a href="#" class="easy-dialog-for-wordpress" data-dialog="<?php echo esc_attr( Helper::get_json( $dialog ) ); ?>"><?php echo esc_html__( 'Import as real file', 'external-files-in-media-library' ); ?></a></span>
		</li>
		<?php
	}

	/**
	 * Import single file as real local file.
	 *
	 * This is the main function to convert any external file to an local file in the media library.
	 *
	 * @param File $external_file_obj The object of the external file which will be changed.
	 *
	 * @return bool Return true if the file import was successfully, false it not.
	 */
	private function import_local( File $external_file_obj ): bool {
		// bail if this file is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			return false;
		}

		// switch to local if file is external and bail if it is run in an error.
		if ( ! $external_file_obj->is_locally_saved() && ! $external_file_obj->switch_to_local() ) {
			// log this event.
			Log::get_instance()->create( __( 'File could not be converted to real local file as switching to local hosting failed.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		/**
		 * Run additional tasks to save an external file as local file.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param File $external_file_obj The external file object.
		 */
		do_action( 'efml_real_import_local', $external_file_obj );

		// remove the URL from file settings.
		$external_file_obj->remove_url();

		// remove availability.
		$external_file_obj->remove_availability();

		// remove locally saved marker.
		$external_file_obj->remove_local_saved();

		// remove the credentials.
		$external_file_obj->remove_fields();

		// clear the cache.
		$external_file_obj->delete_cache();
		$external_file_obj->delete_thumbs();

		// remove the import date.
		$external_file_obj->remove_date();

		// log this event.
		Log::get_instance()->create( __( 'File from external URL has been saved local.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );

		// return true if import was successfully.
		return true;
	}

	/**
	 * Save an existing external file as real file in media library per request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function import_local_per_request(): void {
		// check referer.
		check_admin_referer( 'eml-real-import-external-file', 'nonce' );

		// get referer.
		$referer = wp_get_referer();

		// if referer is false, set empty string.
		if ( ! $referer ) {
			$referer = '';
		}

		// get attachment ID.
		$attachment_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if attachment ID is not given.
		if ( 0 === $attachment_id ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// get the external files object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// trigger hint depending on result.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'eml_real_import' );
		if( $this->import_local( $external_file_obj ) ) {
			$transient_obj->set_message( '<strong>' . __( 'The file has been imported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'It is are now stored in the media library without any external connection.', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'success' );
		}
		else {
			if( current_user_can( 'manage_options' ) ) {
				/* translators: %1$s will be replaced by a URL. */
				$transient_obj->set_message( '<strong>' . __( 'The file could not be imported.', 'external-files-in-media-library' ) . '</strong> ' . sprintf( __( 'Check <a href="%1$s">the log</a> to see what happened.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_logs' ) ) );
			}
			else {
				$transient_obj->set_message( '<strong>' . __( 'The file could not be imported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'Talk to your project administration about this.', 'external-files-in-media-library' ) );
			}
			$transient_obj->set_type( 'error' );
		}
		$transient_obj->save();

		// forward the user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Add bulk option to import external files as real files.
	 *
	 * @param array<string,string> $actions List of actions.
	 *
	 * @return array<string,string>
	 */
	public function add_bulk_action( array $actions ): array {
		// bail if capability is not set.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return $actions;
		}

		// bail if real import is disabled.
		if ( 1 !== absint( get_option( 'eml_real_import' ) ) ) {
			return $actions;
		}

		// add our bulk action.
		$actions['eml-real-import'] = __( 'Import as real file', 'external-files-in-media-library' );

		// return resulting list of actions.
		return $actions;
	}

	/**
	 * If our bulk action is run, check each marked file if it is an external file and change it as
	 * real file.
	 *
	 * @param string         $sendback The return value.
	 * @param string         $doaction The action used.
	 * @param array<int,int> $items The items to take action.
	 *
	 * @return string
	 */
	public function run_bulk_action( string $sendback, string $doaction, array $items ): string {
		// bail if action is not ours.
		if ( 'eml-real-import' !== $doaction ) {
			return $sendback;
		}

		// bail if item list is empty.
		if ( empty( $items ) ) {
			return $sendback;
		}

		// check the files and change its state.
		foreach ( $items as $attachment_id ) {
			// get external file object for this attachment.
			$external_file_obj = Files::get_instance()->get_file( absint( $attachment_id ) );

			// change it.
			$this->import_local( $external_file_obj );
		}

		// return the given value.
		return $sendback;
	}

	/**
	 * Add config on sync configuration form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_option_on_sync_config( string $form, int $term_id ): string {
		// get the actual setting.
		$checked = 1 === absint( get_term_meta( $term_id, 'real_import', true ) );

		// add the HTML-code.
		$form .= '<div><label for="real_import"><input type="checkbox" name="real_import" id="real_import" value="1"' . ( $checked ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Really import each file. Files are not synchronized, just saved if they do not exist.', 'external-files-in-media-library' ) . '</label></div>';

		// return the resulting html-code for the form.
		return $form;
	}

	/**
	 * Save the custom sync configuration for an external directory.
	 *
	 * @param array<string,string> $fields List of fields.
	 *
	 * @return void
	 */
	public function save_sync_settings( array $fields ): void {
		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// if "use_dates" is 0, just remove the setting.
		if ( empty( $fields['real_import'] ) || 0 === absint( $fields['real_import'] ) ) {
			delete_term_meta( $term_id, 'real_import' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'real_import', 1 );
	}

	/**
	 * Add setting to import files as real files before sync.
	 *
	 * @param string               $url The used URL.
	 * @param array<string,string> $term_data The term data.
	 * @param int                  $term_id The term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_action_before_sync( string $url, array $term_data, int $term_id ): void {
		// bail if settings is not set.
		if ( 1 !== absint( get_term_meta( $term_id, 'real_import', true ) ) ) {
			return;
		}

		// set use_dates to 1.
		$_POST['real_import'] = 1;

		// add filter.
		add_filter( 'efml_external_file_infos', array( $this, 'check_for_duplicate_during_sync' ) );
	}

	/**
	 * Mark as updated.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function delete_mark_as_synced( File $external_file_obj ): void {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// bail if settings is not set.
		if ( ! isset( $_POST['real_import'] ) || 1 !== absint( $_POST['real_import'] ) ) {
			return;
		}

		// remove the sync markers.
		delete_post_meta( $external_file_obj->get_id(), 'eml_synced' );
		delete_post_meta( $external_file_obj->get_id(), 'eml_synced_time' );
		wp_delete_object_term_relationships( $external_file_obj->get_id(), Taxonomy::get_instance()->get_name() );
	}

	/**
	 * Check each result for duplicate in media library.
	 *
	 * @param array<int,array<string,mixed>> $results List of resulting file checks.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function check_for_duplicate( array $results ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// bail if it is not enabled during import.
		if ( 1 !== $real_import ) {
			return $results;
		}

		// check each file from results if it is already in media library.
		foreach ( $results as $index => $file ) {
			// run the check.
			$check_result = $this->check_for_duplicate_during_sync( $file );

			// remove file from list, if result is negativ (it's a duplicate).
			if ( empty( $check_result ) ) {
				unset( $results[ $index ] );
			}
		}

		// return the resulting list.
		return $results;
	}

	/**
	 * Check for duplicate of real files during sync.
	 *
	 * @param array<string,mixed> $results The result with the file infos.
	 *
	 * @return array<string,mixed>
	 */
	public function check_for_duplicate_during_sync( array $results ): array {
		// bail if no URL is given.
		if ( empty( $results['url'] ) ) {
			return array();
		}

		// query for file with same filename.
		$query         = array(
			'post_type'      => 'attachment',
			'title'          => basename( $results['url'] ),
			'post_status'    => array( 'inherit', 'trash' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		$existing_file = new WP_Query( $query );

		// bail if another file with same name could be found.
		if ( $existing_file->found_posts >= 1 ) {
			// log this event.
			Log::get_instance()->create( __( 'This file is already in your media library.', 'external-files-in-media-library' ), $results['url'], 'error', 0, Import::get_instance()->get_identifier() );

			// return empty array as this file is already on the media library.
			return array();
		}

		// return the file infos.
		return $results;
	}

	/**
	 * Change media row actions for URL-files: add export option for local files.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Post              $post The Post.
	 *
	 * @return array<string,string>
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// bail if real import is disabled.
		if ( 1 !== absint( get_option( 'eml_real_import' ) ) ) {
			return $actions;
		}

		// bail given file is not an external file.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );
		if( ! $external_file_obj->is_valid() ) {
			return $actions;
		}

		// create URL to export this file.
		$url = add_query_arg(
			array(
				'action' => 'eml_real_import_external_file',
				'nonce'  => wp_create_nonce( 'eml-real-import-external-file' ),
				'post'   => $post->ID,
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$dialog = array(
			'title'   => __( 'Import external file', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Do you really want to import this external file as real file in media library?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'The file will then exist in the media library like any other local file. It will no longer have an external connection.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add the option.
		$actions['eml-real-import-file'] = '<a href="' . esc_url( $url ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Import as real file', 'external-files-in-media-library' ) . '</a>';

		// return resulting list of actions.
		return $actions;
	}
}
