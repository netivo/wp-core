<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Admin;

use Netivo\Core\Admin\BulkAction;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass( BulkAction::class )]
class BulkActionTest extends TestCase {

	/**
	 * Builds a BulkAction subclass instance without running the constructor (which registers
	 * WordPress hooks); add_action()/handle() don't need any of that to be mocked.
	 */
	private function build(): TestBulkAction {
		return ( new ReflectionClass( TestBulkAction::class ) )->newInstanceWithoutConstructor();
	}

	public function test_add_action_adds_its_own_id_and_name_to_the_actions_list(): void {
		$action = $this->build();

		$result = $action->add_action( [ 'existing' => 'Existing action' ] );

		$this->assertSame( [
			'existing'      => 'Existing action',
			'nt_test_mark' => 'Test mark',
		], $result );
	}

	public function test_handle_ignores_a_different_action_name(): void {
		// Regression guard for B10: handle() must only act when the action name matches.
		$action = $this->build();

		$result = $action->handle( 'https://example.test/redirect', 'some_other_action', [ '1', '2' ] );

		$this->assertSame( 'https://example.test/redirect', $result );
		$this->assertSame( [], $action->received_ids );
	}

	public function test_handle_casts_ids_to_integers_before_calling_do_action(): void {
		// Regression test for B10/S3: ids arriving from $_REQUEST/HPOS as strings must be
		// cast with absint() before reaching the subclass.
		$action = $this->build();

		$action->handle( 'https://example.test/redirect', 'nt_test_mark', [ '5', '7', 'not-a-number' ] );

		$this->assertSame( [ 5, 7, 0 ], $action->received_ids );
		foreach ( $action->received_ids as $id ) {
			$this->assertIsInt( $id );
		}
	}

	public function test_handle_returns_whatever_do_action_returns(): void {
		$action = $this->build();

		$result = $action->handle( 'https://example.test/redirect', 'nt_test_mark', [ '1' ] );

		$this->assertSame( 'https://example.test/redirect?marked=1', $result );
	}
}

class TestBulkAction extends BulkAction {
	protected string $id     = 'nt_test_mark';
	protected string $name   = 'Test mark';
	protected string $screen = 'edit-post';

	/** @var array<int, int> */
	public array $received_ids = [];

	public function do_action( array $ids, string $redirect_url ): string {
		$this->received_ids = $ids;

		return $redirect_url . '?marked=' . count( $ids );
	}
}
