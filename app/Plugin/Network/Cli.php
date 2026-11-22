<?php
/**
 * This file contains the commands for WP-CLI for the central media libraries of the network.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Manage the central media libraries of the network.
 *
 * @noinspection PhpUnused
 */
class Cli {
	/**
	 * Show the groups with their central media library and the state of their websites.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network status
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function status(): void {
		$status = Api::get_instance()->get_status();
		if ( empty( $status ) ) {
			\WP_CLI::warning( __( 'No central media library is used in this network.', 'external-files-in-media-library' ) );
			return;
		}
		$rows = array();
		foreach ( $status as $group ) {
			$rows[] = array(
				'group'   => $group['name'],
				'website' => $group['label'] . ' (' . __( 'central media library', 'external-files-in-media-library' ) . ')',
				'files'   => '',
				'own'     => '',
				'errors'  => '',
				'overdue' => '',
			);
			foreach ( $group['sites'] as $site ) {
				$rows[] = array(
					'group'   => $group['name'],
					'website' => $site['label'] . ( $site['leaving'] ? ' (' . __( 'leaving', 'external-files-in-media-library' ) . ')' : '' ),
					'files'   => $site['files'],
					'own'     => $site['own'],
					'errors'  => $site['errors'],
					'overdue' => $site['overdue'],
				);
			}
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'group', 'website', 'files', 'own', 'errors', 'overdue' ) );
	}

	/**
	 * Move a website to a group or let it leave its group.
	 *
	 * <blog_id>
	 * : The ID of the website.
	 *
	 * <group_id>
	 * : The ID of the group, 0 to leave its group.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network join 3 2
	 *
	 * @param array<int,string> $args The arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function join( array $args ): void {
		$this->finish( Api::get_instance()->set_group( absint( $args[0] ?? 0 ), absint( $args[1] ?? 0 ) ), __( 'The website has been moved. Leaving a group runs in the background.', 'external-files-in-media-library' ) );
	}

	/**
	 * Change, which websites could use a file of a central media library.
	 *
	 * <central>
	 * : The ID of the website with the central media library.
	 *
	 * <attachment_id>
	 * : The attachment ID in the central media library.
	 *
	 * <mode>
	 * : "all", "only" or "except".
	 *
	 * [--sites=<ids>]
	 * : Comma-separated IDs of the websites for "only" and "except".
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network visibility 1 42 only --sites=2,3
	 *
	 * @param array<int,string>    $args      The arguments.
	 * @param array<string,string> $arguments The named arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function visibility( array $args, array $arguments = array() ): void {
		$sites = empty( $arguments['sites'] ) ? array() : array_map( 'absint', explode( ',', $arguments['sites'] ) );
		$this->finish( Api::get_instance()->set_visibility( absint( $args[0] ?? 0 ), absint( $args[1] ?? 0 ), (string) ( $args[2] ?? '' ), $sites ), __( 'The availability has been saved.', 'external-files-in-media-library' ) );
	}

	/**
	 * Show the usage of a file of a central media library in the websites.
	 *
	 * <central>
	 * : The ID of the website with the central media library.
	 *
	 * <attachment_id>
	 * : The attachment ID in the central media library.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network usage 1 42
	 *
	 * @param array<int,string> $args The arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function usage( array $args ): void {
		$usage = Api::get_instance()->get_usage( absint( $args[0] ?? 0 ), absint( $args[1] ?? 0 ) );
		if ( $usage instanceof WP_Error ) {
			\WP_CLI::error( $usage->get_error_message() );
			return;
		}
		$rows = array();
		foreach ( $usage as $blog_id => $entry ) {
			$rows[] = array_merge( array( 'blog_id' => $blog_id ), array_map( 'strval', array_intersect_key( $entry, array_flip( array( 'label', 'attachment_id', 'posts' ) ) ) ) );
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'blog_id', 'label', 'attachment_id', 'posts' ) );
	}

	/**
	 * Run the synchronization with the central media library in a website.
	 *
	 * <blog_id>
	 * : The ID of the website.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network sync 3
	 *
	 * @param array<int,string> $args The arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function sync( array $args ): void {
		$this->finish( Api::get_instance()->sync( absint( $args[0] ?? 0 ) ), __( 'The synchronization has been run.', 'external-files-in-media-library' ) );
	}

	/**
	 * Run the tasks in the background now: passing of changes, leaving websites and migrations.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network run
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function run(): void {
		Propagation::get_instance()->run_migration( 600 );
		Move::get_instance()->run( 600 );
		foreach ( \ExternalFilesInMediaLibrary\Plugin\Helper::get_blogs() as $website ) {
			Membership::get_instance()->run_leave( absint( $website->blog_id ), 600 ); // @phpstan-ignore property.notFound
		}
		\WP_CLI::success( __( 'The tasks have been run.', 'external-files-in-media-library' ) );
	}

	/**
	 * Transfer the local files of websites to their central media library in the background.
	 *
	 * [<blog_id>...]
	 * : The IDs of the websites. Without them, all websites of all groups are used.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network transfer-local 3 4
	 *
	 * @subcommand transfer-local
	 *
	 * @param array<int,string> $args The arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function transfer_local( array $args = array() ): void {
		$sites = array_map( 'absint', $args );
		if ( empty( $sites ) ) {
			foreach ( Media_Library::get_instance()->get_groups() as $group ) {
				$sites = array_merge( $sites, $group['sites'] );
			}
		}
		foreach ( $sites as $blog_id ) {
			$result = Existing::get_instance()->start( $blog_id );
			if ( $result instanceof WP_Error ) {
				\WP_CLI::warning( Media_Library::get_instance()->get_site_label( $blog_id ) . ': ' . $result->get_error_message() );
				continue;
			}
			\WP_CLI::log( Media_Library::get_instance()->get_site_label( $blog_id ) . ': ' . $result );
		}
		\WP_CLI::success( __( 'Done.', 'external-files-in-media-library' ) );
	}

	/**
	 * Show the files of a central media library, which no website uses.
	 *
	 * <central>
	 * : The ID of the website with the central media library.
	 *
	 * [--check]
	 * : Check the usage of all files now (otherwise the result of the last check in the background is used).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network unused 1 --check
	 *
	 * @param array<int,string>    $args      The arguments.
	 * @param array<string,string> $arguments The named arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function unused( array $args, array $arguments = array() ): void {
		$central = absint( $args[0] ?? 0 );
		if ( ! Media_Library::get_instance()->is_central( $central ) ) {
			\WP_CLI::error( __( 'The website is not a central media library.', 'external-files-in-media-library' ) );
			return;
		}
		switch_to_blog( $central );
		if ( isset( $arguments['check'] ) ) {
			Unused::get_instance()->run( 86400 );
		}
		$rows = array();
		foreach ( Unused::get_instance()->get_unused() as $attachment_id ) {
			$rows[] = array(
				'ID'    => $attachment_id,
				'title' => get_the_title( $attachment_id ),
				'file'  => basename( (string) get_attached_file( $attachment_id ) ),
			);
		}
		restore_current_blog();
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'title', 'file' ) );
	}

	/**
	 * Show or merge files with the same content in a central media library.
	 *
	 * <central>
	 * : The ID of the website with the central media library.
	 *
	 * [--merge]
	 * : Merge them: the oldest file of each list is kept, the others are deleted after the websites use the kept one.
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network duplicates 1
	 *
	 * @param array<int,string>    $args      The arguments.
	 * @param array<string,string> $arguments The named arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function duplicates( array $args, array $arguments = array() ): void {
		$central = absint( $args[0] ?? 0 );
		if ( ! Media_Library::get_instance()->is_central( $central ) ) {
			\WP_CLI::error( __( 'The website is not a central media library.', 'external-files-in-media-library' ) );
			return;
		}
		switch_to_blog( $central );
		$groups = Duplicates::get_instance()->get_groups( 10000 );
		foreach ( $groups as $ids ) {
			\WP_CLI::log( implode( ', ', $ids ) );
		}
		if ( isset( $arguments['merge'] ) && ! empty( $groups ) ) {
			\WP_CLI::confirm( __( 'Merge these files? The other files of each list are deleted in the central media library.', 'external-files-in-media-library' ), $arguments );
			foreach ( $groups as $ids ) {
				$keep = array_shift( $ids );
				foreach ( $ids as $duplicate ) {
					$result = Duplicates::get_instance()->merge( absint( $keep ), $duplicate );
					if ( $result instanceof WP_Error ) {
						\WP_CLI::warning( $duplicate . ': ' . $result->get_error_message() );
					}
				}
			}
		}
		restore_current_blog();
		\WP_CLI::success( __( 'Done.', 'external-files-in-media-library' ) );
	}

	/**
	 * Move the central media library of a group to another website.
	 *
	 * The files are added to the new website, the websites of the group use them afterward. The files in the former
	 * central media library are kept, it does not belong to the group afterward.
	 *
	 * <group_id>
	 * : The ID of the group.
	 *
	 * <blog_id>
	 * : The ID of the website, which becomes the central media library.
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network move 1 5
	 *
	 * @param array<int,string>    $args      The arguments.
	 * @param array<string,string> $arguments The named arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function move( array $args, array $arguments = array() ): void {
		\WP_CLI::confirm( __( 'Move the central media library? Create a backup beforehand.', 'external-files-in-media-library' ), $arguments );
		$result = Move::get_instance()->start( absint( $args[0] ?? 0 ), absint( $args[1] ?? 0 ) );
		if ( $result instanceof WP_Error ) {
			\WP_CLI::error( $result->get_error_message() );
			return;
		}
		while ( ! Move::get_instance()->run( 600 ) ) {
			\WP_CLI::log( __( 'Continue …', 'external-files-in-media-library' ) );
		}
		\WP_CLI::success( __( 'The central media library has been moved.', 'external-files-in-media-library' ) );
	}

	/**
	 * Use the actual addresses of the files of the central media libraries in the websites, e.g. after a change of the
	 * domain.
	 *
	 * [--dry-run]
	 * : Only show the number of files with another address.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network fix-addresses --dry-run
	 *
	 * @subcommand fix-addresses
	 *
	 * @param array<int,string>    $args      The arguments.
	 * @param array<string,string> $arguments The named arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function fix_addresses( array $args = array(), array $arguments = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$total = 0;
		foreach ( Media_Library::get_instance()->get_groups() as $group ) {
			foreach ( $group['sites'] as $blog_id ) {
				$count = Addresses::get_instance()->fix( $blog_id, isset( $arguments['dry-run'] ) );
				if ( $count > 0 ) {
					\WP_CLI::log( Media_Library::get_instance()->get_site_label( $blog_id ) . ': ' . $count );
				}
				$total += $count;
			}
		}
		/* translators: %1$d will be replaced by a number. */
		\WP_CLI::success( sprintf( isset( $arguments['dry-run'] ) ? __( '%1$d files use another address.', 'external-files-in-media-library' ) : __( '%1$d files use the actual address now.', 'external-files-in-media-library' ), $total ) );
	}

	/**
	 * Send the weekly summary of problems now (if it is enabled and there are problems).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml network digest
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function digest(): void {
		if ( Digest::get_instance()->send() ) {
			\WP_CLI::success( __( 'The summary has been sent.', 'external-files-in-media-library' ) );
			return;
		}
		\WP_CLI::warning( __( 'No summary has been sent: it is disabled or there are no problems.', 'external-files-in-media-library' ) );
	}

	/**
	 * Show the result.
	 *
	 * @param mixed  $result  The result.
	 * @param string $message The message on success.
	 *
	 * @return void
	 */
	private function finish( mixed $result, string $message ): void {
		if ( $result instanceof WP_Error ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::success( $message );
	}
}
