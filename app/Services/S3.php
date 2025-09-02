<?php
/**
 * File to handle the AWS S3 support as directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\EndpointV2\EndpointDefinitionProvider;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Languages;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Error;
use WP_User;

/**
 * Object to handle support for AWS S3-based directory listing.
 */
class S3 extends Service_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 's3';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'AWS S3';

	/**
	 * Marker if login is required.
	 *
	 * @var bool
	 */
	protected bool $requires_3fields_api = true;

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_s3';

	/**
	 * Instance of actual object.
	 *
	 * @var ?S3
	 */
	private static ?S3 $instance = null;

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
	 * @return S3
	 */
	public static function get_instance(): S3 {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use parent initialization.
		parent::init();

		// add settings.
		add_action( 'init', array( $this, 'init_aws_s3' ), 30 );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your AWS S3 server', 'external-files-in-media-library' );

		// use our own hooks.
		add_filter( 'efml_service_s3_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 3 );
		add_filter( 'eml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_directory_listing', array( $this, 'prepare_tree_building' ), 10, 3 );

		// use hooks.
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get the S3Client.
		$s3 = $this->get_s3_client();

		// get list of directories and files in given bucket.
		try {
			// create the query to load the list of files.
			$query = array(
				'Bucket' => $this->get_api_key(),
			);

			// try to load the requested bucket.
			$result = $s3->listObjectsV2( $query );

			/**
			 * Get list of files.
			 *
			 * This will be all files in the complete bucket incl. subdirectories.
			 */
			$files = $result['Contents'];

			// bail if no data returned.
			if ( ! is_array( $files ) || empty( $files ) ) {
				// create error object.
				$error = new WP_Error();
				$error->add( 'efml_service_s3', __( 'No files returned from AWS S3.', 'external-files-in-media-library' ) );

				// add it to the list.
				$this->add_error( $error );

				// do nothing more.
				return array();
			}

			// collect the content of this directory.
			$listing = array(
				'title' => $this->get_directory(),
				'files' => array(),
				'dirs'  => array(),
			);

			// collect list of folders.
			$folders = array();

			// add each file to the list.
			foreach ( $files as $file ) {
				$false = false;
				/**
				 * Filter whether given AWS S3 file should be hidden.
				 *
				 * @since 5.0.0 Available since 5.0.0.
				 *
				 * @param bool $false True if it should be hidden.
				 * @param array<string,mixed> $file The array with the file data.
				 * @param string $directory The requested directory.
				 *
				 * @noinspection PhpConditionAlreadyCheckedInspection
				 */
				if ( apply_filters( 'efml_service_s3_hide_file', $false, $file, $directory ) ) {
					continue;
				}

				// get directory-data for this file and add file in the given directories.
				$parts = explode( '/', $file['Key'] );

				// collect the entry.
				$entry = array(
					'title' => basename( $file['Key'] ),
				);

				// if array contains more than 1 entry this file is in a directory.
				if ( end( $parts ) ) {
					// get content type of this file.
					$mime_type = wp_check_filetype( basename( $file['Key'] ) );

					// bail if file type is not allowed.
					if ( empty( $mime_type['type'] ) ) {
						continue;
					}

					// add settings for entry.
					$entry['file']          = $this->get_directory() . '/' . $file['Key'];
					$entry['filesize']      = absint( $file['Size'] );
					$entry['mime-type']     = $mime_type['type'];
					$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
					$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $file['LastModified']->format( 'U' ) ) ) );
					$entry['preview']       = '';
				}

				if ( count( $parts ) > 1 ) {
					$the_keys = array_keys( $parts );
					$last_key = end( $the_keys );
					$last_dir = '';
					$dir_path = '';

					// loop through all parent folders, add the directory if it does not exist in the list
					// and add the file to each of them.
					foreach ( $parts as $key => $dir ) {
						// bail if dir is empty.
						if ( empty( $dir ) ) {
							continue;
						}

						// bail for last entry (which is a file).
						if ( $key === $last_key ) {
							// add the file to the last iterated directory.
							$folders[ $last_dir ]['files'][] = $entry;
							continue;
						}

						// add the path.
						$dir_path .= DIRECTORY_SEPARATOR . $dir;

						// create the full path.
						$index = $this->get_api_key() . trailingslashit( $dir_path );

						// add the directory if it does not exist atm in the main folder list.
						if ( ! isset( $folders[ $index ] ) ) {
							// add the directory to the list.
							$folders[ $index ] = array(
								'title' => $dir,
								'files' => array(),
								'dirs'  => array(),
							);
						}

						// add the directory if it does not exist atm in the main folder list.
						if ( ! empty( $last_dir ) && ! isset( $folders[ $last_dir ]['dirs'][ $index ] ) ) {
							// add the directory to the list.
							$folders[ $last_dir ]['dirs'][ $index ] = array(
								'title' => $dir,
								'files' => array(),
								'dirs'  => array(),
							);
						}

						// mark this dir as last dir for file path.
						$last_dir = $index;
					}
				} else {
					// simply add the entry to the list if no directory data exist.
					$listing['files'][] = $entry;
				}
			}

			// return the resulting file list.
			return array_merge( array( 'completed' => true ), array( $this->get_api_key() => $listing ), $folders );
		} catch ( S3Exception $e ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$d will be replaced by an HTTP-status code like 403. */
			$error->add( 'efml_service_s3', sprintf( __( 'Credentials are not valid. AWS S3 returns with HTTP-Status %1$d!', 'external-files-in-media-library' ), $e->getStatusCode() ) );

			// add it to the list.
			$this->add_error( $error );

			return array();
		}
	}

	/**
	 * Return the actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		// get list of allowed mime types.
		$mimetypes = implode( ',', Helper::get_allowed_mime_types() );

		return array(
			array(
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file, "login": login, "password": password, "api_key": apiKey, "term": term } );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";mimetypes.includes( file["mime-type"] )',
				'hint'   => '<span class="dashicons dashicons-editor-help" title="' . esc_attr__( 'File-type is not supported', 'external-files-in-media-library' ) . '"></span>',
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		return array_merge(
			parent::get_global_actions(),
			array(

				array(
					'action' => 'location.href="https://console.aws.amazon.com/s3/buckets/";',
					'label'  => __( 'Go to AWS S3 Bucket', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": actualDirectoryPath, "login": login, "password": password, "term": config.term } );',
					'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", "' . $this->get_url_mark() . '" + actualDirectoryPath, login, password, "", config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Check if login with given credentials is valid.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// bail if credentials are missing.
		if ( empty( $this->get_login() ) || empty( $this->get_password() || empty( $this->get_api_key() ) ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_s3', __( 'No credentials set for this AWS S3 connection!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// get AWS S3 client to check if credentials are ok.
		$s3 = $this->get_s3_client();
		try {
			// try to load the requested bucket.
			$s3->listObjectsV2( array( 'Bucket' => $this->get_api_key() ) );

			// return true if it could be loaded.
			return true;
		} catch ( S3Exception $e ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$d will be replaced by an HTTP-status code like 403. */
			$error->add( 'efml_service_s3', sprintf( __( 'Credentials are not valid. AWS S3 returns with HTTP-Status %1$d!', 'external-files-in-media-library' ), $e->getStatusCode() ) );

			// add it to the list.
			$this->add_error( $error );

			// add log entry.
			/* translators: %1$d will be replaced by a HTTP-status (like 301). */
			Log::get_instance()->create( sprintf( __( 'Credentials are not valid. AWS S3 returns with HTTP-Status %1$d! Error:', 'external-files-in-media-library' ), $e->getStatusCode() ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );

			// return false to prevent any further actions.
			return false;
		}
	}

	/**
	 * Return the S3Client object with given credentials.
	 *
	 * @return S3Client
	 */
	public function get_s3_client(): S3Client {
		return new S3Client(
			array(
				'version'     => 'latest',
				'region'      => $this->get_region(),
				'credentials' => array(
					'key'    => $this->get_login(),
					'secret' => $this->get_password(),
				),
			)
		);
	}

	/**
	 * Return region from settings.
	 *
	 * @return string
	 */
	private function get_region(): string {
		// get from global setting, if enabled.
		if ( 'global' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			return get_option( 'eml_s3_region', 'eu-central-1' );
		}

		// get from user setting, if enabled.
		if ( 'user' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			// get current user.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}

			// get the setting.
			$region = get_user_meta( $user->ID, 'efml_s3_region', true );

			// bail if value is not a string.
			if ( ! is_string( $region ) ) {
				return '';
			}

			// return the region from settings.
			return Crypt::get_instance()->decrypt( $region );
		}

		// return empty string.
		return '';
	}

	/**
	 * Enable WP CLI for AWS S3 tasks.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return the directory to use.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		// bail if no bucket is set.
		if ( empty( $this->get_api_key() ) ) {
			return '/';
		}

		// return label and bucket name.
		return $this->get_label() . '/' . $this->get_api_key();
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool                $result True if it should be hidden.
	 * @param array<string,mixed> $file The array with the file data.
	 * @param string              $url The requested directory.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_not_allowed_files( bool $result, array $file, string $url ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( basename( $file['Key'] ) );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// add the AWS S3 protocol before the HTTPS-protocol and return resulting list of protocols.
		array_unshift( $protocols, 'ExternalFilesInMediaLibrary\Services\S3\Protocol' );

		// return the resulting list.
		return $protocols;
	}

	/**
	 * Add settings for AWS S3 support.
	 *
	 * @return void
	 */
	public function init_aws_s3(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// add new tab for settings.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// add section for file statistics.
		$section = $tab->get_section( 'section_' . $this->get_name() . '_main' );

		// bail if tab does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add setting for region.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || 'global' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			$setting = $settings_obj->add_setting( 'eml_s3_region' );
			$setting->set_section( $section );
			$setting->set_type( 'string' );
			$setting->set_default( $this->get_mapping_region() );
			$field = new Select();
			$field->set_title( __( 'Choose your region', 'external-files-in-media-library' ) );
			$field->set_options( $this->get_regions() );
			$setting->set_field( $field );
		}

		// show hint for user settings.
		if ( 'user' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			$setting = $settings_obj->add_setting( 'eml_s3_credential_location_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo();
			$field->set_title( __( 'Hint', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() ) );
			$setting->set_field( $field );
		}
	}

	/**
	 * Return list of AWS S3 regions for settings.
	 *
	 * @return array<string,string>
	 */
	private function get_regions(): array {
		// get cached value.
		$list = get_transient( 'eml_aws_s3_regions' );

		// use them if they are set.
		if ( ! empty( $list ) && is_array( $list ) ) {
			return $list;
		}

		// get all partitions from AWS S3 SDK.
		$partitions = EndpointDefinitionProvider::getPartitions();

		// bail if regions are not set there.
		if ( empty( $partitions['partitions'][0]['regions'] ) ) {
			return array();
		}

		// create our list for the settings.
		$list = array();
		foreach ( $partitions['partitions'][0]['regions'] as $name => $region ) {
			$list[ $name ] = $region['description'];
		}

		/**
		 * Filter the resulting list of AWS S3 regions.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,string> $list List of regions.
		 */
		$list = apply_filters( 'efml_service_s3_regions', $list );

		// save the list in cache.
		set_transient( 'eml_aws_s3_regions', $list, WEEK_IN_SECONDS );

		// return the list.
		return $list;
	}

	/**
	 * Try to map the region according to the used WordPress language.
	 *
	 * @return string
	 */
	private function get_mapping_region(): string {
		// get actual language.
		$language = Languages::get_instance()->get_current_lang();

		// use eu-central-1 for german.
		if ( Languages::get_instance()->is_german_language() ) {
			return 'eu-central-1';
		}

		// return 'eu-south-2' for spain.
		if ( 'es' === $language ) {
			return 'eu-south-2';
		}

		// return 'ap-northeast-1' for japanese.
		if ( 'ja' === $language ) {
			return 'ap-northeast-1';
		}

		// return 'il-central-1' for hebrew.
		if ( 'he' === $language ) {
			return 'il-central-1';
		}

		// return "aws-global" for all others.
		return 'aws-global';
	}

	/**
	 * Show option to connect to DropBox on user profile.
	 *
	 * @param WP_User $user The WP_User object for the actual user.
	 *
	 * @return void
	 */
	public function add_user_settings( WP_User $user ): void {
		// bail if settings are not user-specific.
		if ( 'user' !== get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		?><h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'AWS S3', 'external-files-in-media-library' ); ?></h3>
		<div class="efml-user-settings">
		<?php

		// show settings table.
		$this->get_user_settings_table( absint( $user->ID ) );

		?>
		</div>
		<?php
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_user_settings(): array {
		$list = array(
			's3_region' => array(
				'label'   => __( 'Choose your region', 'external-files-in-media-library' ),
				'field'   => 'select',
				'options' => $this->get_regions(),
				'default' => $this->get_mapping_region(),
			),
		);

		/**
		 * Filter the list of possible user settings for Google Drive.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 */
		return apply_filters( 'eml_service_s3_user_settings', $list );
	}

	/**
	 * Return the URL mark which identifies Google Cloud Storage URLs within this plugin.
	 *
	 * @return string
	 */
	public function get_url_mark(): string {
		return 'https://console.aws.amazon.com/s3/buckets/';
	}

	/**
	 * Rebuild the resulting list to remove the pagination folders for clean view of the files.
	 *
	 * @param array<string,mixed> $listing The resulting list.
	 * @param string              $url The called URL.
	 * @param string              $service The used service.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prepare_tree_building( array $listing, string $url, string $service ): array {
		// bail if this is not our service.
		if ( $this->get_name() !== $service ) {
			return $listing;
		}

		// create the key of the index we want to remove.
		$index = trailingslashit( $this->get_url_mark() . $this->get_api_key() );

		// bail if the entry with url_marker is not set.
		if( ! isset( $listing[ $index ] ) ) {
			return $listing;
		}

		// remove this entry.
		unset( $listing[ $index ] );

		// return resulting list.
		return $listing;
	}
}
