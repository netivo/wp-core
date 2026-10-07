<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Brain\Monkey\Functions;
use Netivo\Core\Tests\TestCase;
use Netivo\Core\PostType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( PostType::class )]
class PostTypeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs( [ 'esc_html' ] );
	}

	public function test_registers_the_post_type_when_it_does_not_exist_yet(): void {
		Functions\when( 'post_type_exists' )->justReturn( false );
		Functions\when( 'get_role' )->justReturn( null );
		$captured = null;
		Functions\when( 'register_post_type' )->alias( function ( string $id, array $options ) use ( &$captured ) {
			$captured = [ $id, $options ];
		} );

		new TestPostType();

		$this->assertSame( 'test_cpt', $captured[0] );
	}

	public function test_grants_capabilities_once_without_re_adding_an_existing_one(): void {
		// Regression test for B9: capabilities are only added when the role doesn't have them.
		Functions\when( 'post_type_exists' )->justReturn( false );
		Functions\when( 'register_post_type' )->justReturn( true );

		$role = new class {
			public array $added = [];

			public function has_cap( string $cap ): bool {
				return 'existing_cap' === $cap;
			}

			public function add_cap( string $cap ): void {
				$this->added[] = $cap;
			}
		};
		Functions\when( 'get_role' )->justReturn( $role );

		new TestPostType();

		$this->assertSame( [ 'new_cap' ], $role->added );
	}

	public function test_does_nothing_when_the_role_is_missing(): void {
		Functions\when( 'post_type_exists' )->justReturn( false );
		Functions\when( 'register_post_type' )->justReturn( true );
		Functions\when( 'get_role' )->justReturn( null );

		// Must not fatal when get_role() returns null (role missing).
		new TestPostType();
		$this->addToAssertionCount( 1 );
	}

	public function test_throws_when_the_post_type_already_exists(): void {
		Functions\when( 'post_type_exists' )->justReturn( true );

		$this->expectException( \Exception::class );
		new TestPostType();
	}
}

class TestPostType extends PostType {
	public function get_id(): string {
		return 'test_cpt';
	}

	public function get_settings(): array {
		return [
			'public'       => true,
			'capabilities' => [ 'existing_cap', 'new_cap' ],
		];
	}
}
