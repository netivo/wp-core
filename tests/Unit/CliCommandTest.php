<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Brain\Monkey\Functions;
use Netivo\Core\CliCommand;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass( CliCommand::class )]
class CliCommandTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();
	}

	private function build(): TestCliCommand {
		return ( new ReflectionClass( TestCliCommand::class ) )->newInstanceWithoutConstructor();
	}

	public function test_handle_is_not_a_dry_run_by_default(): void {
		$command = $this->build();

		$command->handle( [], [] );

		$this->assertFalse( $command->is_dry_run() );
		$this->assertSame( [ [ 'things' => 'done' ] ], $command->executed_with );
	}

	public function test_handle_detects_the_dry_run_flag_and_logs_it(): void {
		$command = $this->build();

		$command->handle( [], [ 'dry-run' => true ] );

		$this->assertTrue( $command->is_dry_run() );
		$this->assertSame( [ 'log', 'Dry run: nothing will be changed.' ], \WP_CLI::$calls[0] );
	}

	public function test_perform_runs_the_action_outside_a_dry_run(): void {
		$command = $this->build();
		$command->handle( [], [] );

		$result = $command->call_perform( 'do the thing', fn() => 'done!' );

		$this->assertSame( 'done!', $result );
	}

	public function test_perform_skips_the_action_during_a_dry_run(): void {
		$command = $this->build();
		$command->handle( [], [ 'dry-run' => true ] );

		$ran    = false;
		$result = $command->call_perform( 'do the thing', function () use ( &$ran ) {
			$ran = true;

			return 'done!';
		} );

		$this->assertNull( $result );
		$this->assertFalse( $ran );
	}

	public function test_get_synopsis_appends_the_dry_run_flag(): void {
		$command = $this->build();

		$synopsis = $command->call_get_synopsis();

		$this->assertSame( [
			[ 'type' => 'positional', 'name' => 'target' ],
			[ 'type' => 'flag', 'name' => 'dry-run', 'description' => 'Show what would be done without changing anything.', 'optional' => true ],
		], $synopsis );
	}

	public function test_register_adds_the_command_when_wp_cli_is_available(): void {
		$command = $this->build();

		$command->register();

		$this->assertCount( 1, \WP_CLI::$calls );
		$this->assertSame( 'add_command', \WP_CLI::$calls[0][0] );
		$this->assertSame( 'netivo test', \WP_CLI::$calls[0][1][0] );
	}
}

class TestCliCommand extends CliCommand {
	protected string $name      = 'netivo test';
	protected string $shortdesc = 'A test command.';
	protected array $synopsis   = [
		[ 'type' => 'positional', 'name' => 'target' ],
	];

	/** @var array<int, array> */
	public array $executed_with = [];

	protected function execute( array $args, array $assoc_args ): void {
		$this->executed_with[] = [ 'things' => 'done' ];
	}

	public function call_perform( string $description, callable $action ): mixed {
		return $this->perform( $description, $action );
	}

	public function call_get_synopsis(): array {
		return $this->get_synopsis();
	}
}
