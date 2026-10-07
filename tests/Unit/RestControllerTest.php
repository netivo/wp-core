<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Brain\Monkey\Functions;
use Netivo\Core\RestController;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass( RestController::class )]
class RestControllerTest extends TestCase {

	public function test_build_route_uses_the_controllers_own_version(): void {
		// Regression test for B11: build_route() used to hardcode '/v1'.
		$captured = null;
		Functions\when( 'register_rest_route' )->alias( function ( string $namespace, string $route, array $args ) use ( &$captured ) {
			$captured = [ 'namespace' => $namespace, 'route' => $route, 'args' => $args ];
		} );

		$controller = ( new ReflectionClass( TestRestControllerV2::class ) )->newInstanceWithoutConstructor();
		$controller->expose_build_route( 'my_callback' );

		$this->assertSame( 'netivo/v2', $captured['namespace'] );
		$this->assertSame( '/widgets/', $captured['route'] );
		$this->assertSame( 'my_callback', $captured['args'][0]['callback'] );
		$this->assertSame( 'GET', $captured['args'][0]['methods'] );
	}

	public function test_build_route_accepts_a_custom_method_and_permission(): void {
		$captured = null;
		Functions\when( 'register_rest_route' )->alias( function ( string $namespace, string $route, array $args ) use ( &$captured ) {
			$captured = $args;
		} );

		$controller = ( new ReflectionClass( TestRestControllerV2::class ) )->newInstanceWithoutConstructor();
		$controller->expose_build_route( 'my_callback', 'POST', 'my_permission_check', '(?P<id>[\d]+)' );

		$this->assertSame( 'POST', $captured[0]['methods'] );
		$this->assertSame( 'my_permission_check', $captured[0]['permission_callback'] );
	}
}

class TestRestControllerV2 extends RestController {
	protected string $base    = 'widgets';
	protected string $version = 'v2';

	public function register_routes(): void {
	}

	public function expose_build_route( mixed $callback, string $method = 'GET', mixed $permission = '__return_true', string $params = '' ): void {
		$this->build_route( $callback, $method, $permission, $params );
	}
}
