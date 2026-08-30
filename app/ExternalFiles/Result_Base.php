<?php
/**
 * File, which provide the base functions for each result object.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object serving the base result object.
 */
class Result_Base {
	/**
	 * The internal name of the object.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * The title of the object.
	 *
	 * @var string
	 */
	protected string $title = '';

	/**
	 * The text of the object.
	 *
	 * @var string
	 */
	protected string $text = '';

	/**
	 * Whether this is an error object or not.
	 *
	 * @var bool
	 */
	private bool $error = true;

	/**
	 * Return the internal name of this result object.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Set the title.
	 *
	 * @param string $title The title to use.
	 *
	 * @return void
	 */
	public function set_title( string $title ): void {
		$this->title = $title;
	}

	/**
	 * Return the text of this result object.
	 *
	 * @return string
	 */
	public function get_text(): string {
		return $this->text;
	}

	/**
	 * Set the text.
	 *
	 * @param string $text The text to use.
	 *
	 * @return void
	 */
	public function set_text( string $text ): void {
		$this->text = $text;
	}

	/**
	 * Mark this as error or not.
	 *
	 * @param bool $is_error The mark (true = error, false = no error).
	 *
	 * @return void
	 */
	public function set_error( bool $is_error ): void {
		$this->error = $is_error;
	}

	/**
	 * Return whether this is an error.
	 *
	 * @return bool
	 */
	public function is_error(): bool {
		return $this->error;
	}

	/**
	 * Return the persistable state of this object.
	 *
	 * @return array<string,mixed>
	 */
	public function get_state(): array {
		return array(
			'name'     => $this->get_name(),
			'is_error' => $this->is_error(),
			'text'     => $this->text,
		);
	}

	/**
	 * Restore this object from a persisted state.
	 *
	 * @param array<string,mixed> $state The state to restore.
	 *
	 * @return void
	 */
	public function set_state( array $state ): void {
		$this->set_error( ! empty( $state['is_error'] ) );
		$this->set_text( (string) ( ! empty( $state['text'] ) ? $state['text'] : '' ) );
	}
}
