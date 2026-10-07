<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Brain\Monkey\Functions;
use Netivo\Core\Endpoint;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass( Endpoint::class )]
class EndpointTest extends TestCase {

	private function build( string $class ): Endpoint {
		return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
	}

	public function test_register_endpoint_adds_the_rewrite_endpoint(): void {
		$captured = null;
		Functions\when( 'add_rewrite_endpoint' )->alias( function ( string $name, int $place ) use ( &$captured ) {
			$captured = [ $name, $place ];
		} );

		$this->build( TestTemplateEndpoint::class )->register_endpoint();

		$this->assertSame( [ 'my_endpoint', EP_NONE ], $captured );
	}

	public function test_redirect_template_does_nothing_when_the_query_var_is_not_set(): void {
		Functions\when( 'get_query_var' )->justReturn( '' );

		// locate_template()/exit() must never run; if they did, this test would hang or fatal.
		$this->build( TestTemplateEndpoint::class )->redirect_template();

		$this->addToAssertionCount( 1 );
	}

	public function test_auto_register_false_skips_registering_any_hook(): void {
		// Regression test for R9.
		Functions\when( 'add_action' )->alias( function () {
			throw new \RuntimeException( 'add_action() should not have been called.' );
		} );

		new TestTemplateEndpoint( false );
		$this->addToAssertionCount( 1 );
	}

	public function test_register_can_be_called_explicitly_after_opting_out_of_auto_register(): void {
		$calls = [];
		Functions\when( 'add_action' )->alias( function ( string $hook ) use ( &$calls ) {
			$calls[] = $hook;
		} );

		$endpoint = new TestTemplateEndpoint( false );
		$this->assertSame( [], $calls );

		$endpoint->register();
		$this->assertSame( [ 'init', 'template_redirect' ], $calls );
	}
}

class TestTemplateEndpoint extends Endpoint {
	protected string $name = 'my_endpoint';

	public function doAction( mixed $var ): void {
	}
}
