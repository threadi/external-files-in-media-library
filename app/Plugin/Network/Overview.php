<?php
/**
 * File to show the state of the central media libraries and their websites.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object, which shows the state of the central media libraries and their websites.
 *
 * Changes of a central media library reach the websites via WP Cron. If WP Cron does not run on a website (e.g. as it
 * has no visitors and no cron job of the hosting), new files do not arrive there. The overview in the network
 * administration and a test in the site health of each central media library show this.
 */
class Overview {
	/**
	 * Seconds, after which a due event shows that WP Cron does not run.
	 */
	private const OVERDUE = HOUR_IN_SECONDS;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Overview
	 */
	private static ?Overview $instance = null;

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
	 * @return Overview
	 */
	public static function get_instance(): Overview {
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
		add_filter( 'site_status_tests', array( $this, 'add_site_health_test' ) );
	}

	/**
	 * Return the state of the given website, which uses a central media library.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return array{files: int, own: int, overdue: int, pending: int, errors: int, next_sync: int, leaving: bool, log_url: string}
	 */
	public function get_site_state( int $blog_id ): array {
		global $wpdb;

		// switch to the website.
		switch_to_blog( $blog_id );

		// count the files of the central media library and the own uploads.
		$files = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT `post_id`) FROM %i WHERE `meta_key` = %s', array( $wpdb->postmeta, Media_Library::META_CENTRAL_ID ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$own   = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT `post_id`) FROM %i WHERE `meta_key` = %s', array( $wpdb->postmeta, 'efml_multisite_attachment_id' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// check the events of WP Cron in this website.
		$overdue = 0;
		$pending = 0;
		$crons   = _get_cron_array();
		foreach ( $crons as $timestamp => $events ) {
			// count the overdue events.
			if ( absint( $timestamp ) < time() - self::OVERDUE ) {
				$overdue += count( $events );
			}

			// count the pending events of the central media library.
			foreach ( array_keys( $events ) as $hook ) {
				if ( in_array( $hook, array( Propagation::CRON_PROPAGATE, Propagation::CRON_IMPORT, Membership::CRON_LEAVE ), true ) ) {
					++$pending;
				}
			}
		}

		// count the errors of the last seven days.
		$errors = 0;
		$table  = $wpdb->prefix . 'eml_logs';
		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$errors = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `state` = %s AND `time` > %s', array( $table, 'error', gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// get the next synchronization with the central media library.
		$next_sync = 0;
		$term_id   = Media_Library::get_instance()->get_central_term_id();
		if ( $term_id > 0 ) {
			$schedule = \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->get_schedule_by_term_id( $term_id );
			if ( $schedule ) {
				$next_sync = absint( wp_next_scheduled( $schedule->get_name(), $schedule->get_args() ) ); // @phpstan-ignore argument.type
			}
		}

		// get whether it leaves its group at the moment.
		$leaving = absint( get_option( Membership::OPTION_LEAVING, 0 ) ) > 0;

		// get the URL of the log of this website.
		$log_url = \ExternalFilesInMediaLibrary\Plugin\Helper::get_log_url();

		// switch back.
		restore_current_blog();

		// return the state.
		return array(
			'files'     => $files,
			'own'       => $own,
			'overdue'   => $overdue,
			'pending'   => $pending,
			'errors'    => $errors,
			'next_sync' => $next_sync,
			'leaving'   => $leaving,
			'log_url'   => $log_url,
		);
	}

	/**
	 * Show the overview of the groups and their websites.
	 *
	 * @return void
	 */
	public function show(): void {
		// get the groups.
		$groups = Media_Library::get_instance()->get_groups();

		// show a hint, if there are none.
		if ( empty( $groups ) ) {
			echo '<p>' . esc_html__( 'No central media library is used.', 'external-files-in-media-library' ) . '</p>';
			return;
		}

		// show the results of the actions.
		$existing = filter_input( INPUT_GET, 'efml_existing', FILTER_SANITIZE_NUMBER_INT );
		if ( ! is_null( $existing ) ) {
			/* translators: %1$d will be replaced by a number. */
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( _n( 'The transfer of the local files has been started for %1$d website.', 'The transfer of the local files has been started for %1$d websites.', absint( $existing ), 'external-files-in-media-library' ), absint( $existing ) ) ) . '</p></div>';
		}
		$fixed = filter_input( INPUT_GET, 'efml_fixed', FILTER_SANITIZE_NUMBER_INT );
		if ( ! is_null( $fixed ) ) {
			/* translators: %1$d will be replaced by a number. */
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( _n( '%1$d file uses the actual address now.', '%1$d files use the actual address now.', absint( $fixed ), 'external-files-in-media-library' ), absint( $fixed ) ) ) . '</p></div>';
		}

		// show the state of a running move.
		$move = Move::get_instance()->get_state();
		if ( ! empty( $move ) ) {
			$phases = array(
				'copy'   => __( 'the files are added to the new central media library', 'external-files-in-media-library' ),
				'switch' => __( 'the websites are connected with the new central media library', 'external-files-in-media-library' ),
				'sites'  => __( 'the websites use the files of the new central media library', 'external-files-in-media-library' ),
			);
			/* translators: %1$s and %2$s will be replaced by names of websites, %3$s by a step. */
			echo '<div class="notice notice-info"><p>' . esc_html( sprintf( __( 'The central media library %1$s moves to %2$s: %3$s.', 'external-files-in-media-library' ), Media_Library::get_instance()->get_site_label( $move['from'] ), Media_Library::get_instance()->get_site_label( $move['to'] ), $phases[ $move['phase'] ] ?? $move['phase'] ) ) . '</p></div>';
		}

		// show each group.
		$wrong = 0;
		foreach ( $groups as $group ) {
			$central_url = get_admin_url( $group['central'], 'upload.php' );
			?>
			<h2>
				<?php
				/* translators: %1$s will be replaced by the name of a group, %2$s by the name of a website. */
				echo esc_html( sprintf( __( '%1$s (central media library: %2$s)', 'external-files-in-media-library' ), $group['name'], Media_Library::get_instance()->get_site_label( $group['central'] ) ) );
				?>
			</h2>
			<p>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'admin_filter_media_external_files' => 'efml-network-unused',
							'mode' => 'list',
						),
						$central_url
					)
				);
				?>
							"><?php echo esc_html__( 'Files unused in the network', 'external-files-in-media-library' ); ?></a>
				|
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => Duplicates::PAGE ), $central_url ) ); ?>"><?php echo esc_html__( 'Duplicates in the network', 'external-files-in-media-library' ); ?></a>
			</p>
			<table class="widefat striped" style="max-width:1100px">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Website', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Files of the central media library', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Own uploads', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Local files', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Pending changes', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Errors (7 days)', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'Next synchronization', 'external-files-in-media-library' ); ?></th>
						<th><?php echo esc_html__( 'WP Cron', 'external-files-in-media-library' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( array_keys( Media_Library::get_instance()->get_sites( $group['central'] ) ) as $blog_id ) {
						$state = $this->get_site_state( $blog_id );
						?>
						<tr>
							<td>
								<?php
								echo esc_html( Media_Library::get_instance()->get_site_label( $blog_id ) );
								if ( $state['leaving'] ) {
									echo '<br><em>' . esc_html__( 'leaves the group at the moment', 'external-files-in-media-library' ) . '</em>';
								}
								?>
							</td>
							<td><?php echo absint( $state['files'] ); ?></td>
							<td><?php echo absint( $state['own'] ); ?></td>
							<td>
								<?php
								$local = Existing::get_instance()->count( $blog_id );
								echo absint( $local );
								if ( $local > 0 ) {
									echo ' ' . Existing::get_instance()->get_button( $blog_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								$wrong += Addresses::get_instance()->count_wrong( $blog_id );
								?>
							</td>
							<td><?php echo absint( $state['pending'] ); ?></td>
							<td><a href="<?php echo esc_url( $state['log_url'] ); ?>"><?php echo absint( $state['errors'] ); ?></a></td>
							<td><?php echo $state['next_sync'] > 0 ? esc_html( (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $state['next_sync'] ) ) : '&ndash;'; ?></td>
							<td>
								<?php
								if ( $state['overdue'] > 0 ) {
									/* translators: %1$d will be replaced by a number. */
									echo '<span style="color:#b32d2e">' . esc_html( sprintf( _n( 'does not run: %1$d event is overdue', 'does not run: %1$d events are overdue', $state['overdue'], 'external-files-in-media-library' ), $state['overdue'] ) ) . '</span>';
								} else {
									echo esc_html__( 'runs', 'external-files-in-media-library' );
								}
								?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
			<?php
		}
		?>
		<p>
			<?php
			echo Existing::get_instance()->get_button( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $wrong > 0 ) {
				/* translators: %1$d will be replaced by a number. */
				echo ' <span style="color:#b32d2e">' . esc_html( sprintf( _n( '%1$d file uses another address than its central media library (e.g. after a change of the domain).', '%1$d files use another address than their central media library (e.g. after a change of the domain).', $wrong, 'external-files-in-media-library' ), $wrong ) ) . '</span> ' . Addresses::get_instance()->get_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</p>
		<p class="description"><?php echo esc_html__( 'Local files are files of the websites, which are not in the central media library (e.g. from before they joined their group). New files reach the websites via WP Cron. If WP Cron does not run on a website (e.g. as it has no visitors), use a cron job of your hosting for it.', 'external-files-in-media-library' ); ?></p>
		<?php
	}

	/**
	 * Add the test for WP Cron of the websites to the site health of a central media library.
	 *
	 * @param array<string,array<string,mixed>> $tests The tests.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_site_health_test( array $tests ): array {
		// bail if this is not a central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return $tests;
		}

		// add the test.
		$tests['direct']['efml_network_cron'] = array(
			'label' => __( 'WP Cron in the websites of the central media library', 'external-files-in-media-library' ),
			'test'  => array( $this, 'run_site_health_test' ),
		);

		// return the tests.
		return $tests;
	}

	/**
	 * Run the test for WP Cron of the websites of the actual central media library.
	 *
	 * @return array<string,mixed>
	 */
	public function run_site_health_test(): array {
		// collect the websites, where WP Cron does not run.
		$sites = array();
		foreach ( array_keys( Media_Library::get_instance()->get_sites() ) as $blog_id ) {
			if ( $this->get_site_state( $blog_id )['overdue'] > 0 ) {
				$sites[] = Media_Library::get_instance()->get_site_label( $blog_id );
			}
		}

		// the result, if WP Cron runs everywhere.
		$result = array(
			'label'       => __( 'WP Cron runs in all websites of the central media library', 'external-files-in-media-library' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'External files in Media Library', 'external-files-in-media-library' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'New files of the central media library reach all websites.', 'external-files-in-media-library' ) . '</p>',
			'actions'     => '',
			'test'        => 'efml_network_cron',
		);

		// bail if WP Cron runs everywhere.
		if ( empty( $sites ) ) {
			return $result;
		}

		// return the problem.
		$result['label']       = __( 'WP Cron does not run in some websites of the central media library', 'external-files-in-media-library' );
		$result['status']      = 'recommended';
		$result['description'] = '<p>' . esc_html__( 'New files of the central media library do not reach these websites, as WP Cron does not run there (e.g. as they have no visitors):', 'external-files-in-media-library' ) . '</p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $sites ) ) . '</li></ul><p>' . esc_html__( 'Use a cron job of your hosting for these websites.', 'external-files-in-media-library' ) . '</p>';
		return $result;
	}
}
