<?php
/**
 * Created by Netivo for Netivo Core Package.
 *
 * @package Netivo\Core
 */

namespace Netivo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Abstract class CliCommand
 *
 * Parent class for WP-CLI commands. Subclasses set the command name, description and
 * optional synopsis, and implement execute(). Every command gets a --dry-run flag;
 * use perform() for changes so they are skipped while dry-run is active.
 *
 * Only registers when WP-CLI is running.
 */
abstract class CliCommand {

	/**
	 * Command name, including parent words, e.g. 'netivo import products'.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * One-line description shown in the command list.
	 *
	 * @var string
	 */
	protected string $shortdesc = '';

	/**
	 * Long description shown by `wp help <command>`.
	 *
	 * @var string
	 */
	protected string $longdesc = '';

	/**
	 * Extra WP-CLI synopsis entries (positional arguments, flags, assoc arguments).
	 * The --dry-run flag is added automatically.
	 *
	 * @var array
	 */
	protected array $synopsis = [];

	/**
	 * Whether the current run is a dry run.
	 *
	 * @var bool
	 */
	protected bool $dry_run = false;

	/**
	 * CliCommand constructor.
	 * Registers the command now if WP-CLI has already initialised, otherwise on cli_init.
	 */
	public function __construct() {
		if ( did_action( 'cli_init' ) ) {
			$this->register();
		} else {
			add_action( 'cli_init', [ $this, 'register' ] );
		}
	}

	/**
	 * Registers the command in WP-CLI.
	 */
	public function register(): void {
		if ( ! class_exists( '\WP_CLI' ) || empty( $this->name ) ) {
			return;
		}

		\WP_CLI::add_command( $this->name, [ $this, 'handle' ], [
			'shortdesc' => $this->shortdesc,
			'longdesc'  => $this->longdesc,
			'synopsis'  => $this->get_synopsis(),
		] );
	}

	/**
	 * Entry point called by WP-CLI. Sets dry-run state, then calls execute().
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags and assoc arguments.
	 */
	public function handle( array $args, array $assoc_args ): void {
		$this->dry_run = ! empty( $assoc_args['dry-run'] );

		if ( $this->dry_run ) {
			$this->log( 'Dry run: nothing will be changed.' );
		}

		$this->execute( $args, $assoc_args );
	}

	/**
	 * Command logic, implemented by the subclass.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags and assoc arguments.
	 *
	 * @return void
	 */
	abstract protected function execute( array $args, array $assoc_args ): void;

	/**
	 * Whether the current run is a dry run.
	 *
	 * @return bool
	 */
	public function is_dry_run(): bool {
		return $this->dry_run;
	}

	/**
	 * Runs a change, or only reports it during a dry run.
	 *
	 * @param string   $description Human-readable description of the change.
	 * @param callable $action      Callback that makes the change. Not called during a dry run.
	 *
	 * @return mixed Callback result, or null during a dry run.
	 */
	protected function perform( string $description, callable $action ): mixed {
		if ( $this->dry_run ) {
			$this->log( '[dry-run] would ' . $description );

			return null;
		}

		$this->log( $description );

		return $action();
	}

	/**
	 * Prints an informational line.
	 *
	 * @param string $message Message to print.
	 */
	protected function log( string $message ): void {
		\WP_CLI::log( $message );
	}

	/**
	 * Prints a success line.
	 *
	 * @param string $message Message to print.
	 */
	protected function success( string $message ): void {
		\WP_CLI::success( $message );
	}

	/**
	 * Prints a warning line.
	 *
	 * @param string $message Message to print.
	 */
	protected function warning( string $message ): void {
		\WP_CLI::warning( $message );
	}

	/**
	 * Prints an error and stops the command with a non-zero exit code.
	 *
	 * @param string $message Message to print.
	 */
	protected function error( string $message ): void {
		\WP_CLI::error( $message );
	}

	/**
	 * Builds the synopsis: the subclass entries followed by --dry-run.
	 *
	 * @return array
	 */
	protected function get_synopsis(): array {
		return array_merge( $this->synopsis, [
			[
				'type'        => 'flag',
				'name'        => 'dry-run',
				'description' => 'Show what would be done without changing anything.',
				'optional'    => true,
			],
		] );
	}

}
