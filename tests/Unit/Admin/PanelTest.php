<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Admin;

use Netivo\Core\Admin\Panel;
use Netivo\Core\Tests\TestCase;
use Netivo\Core\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass( Panel::class )]
class PanelTest extends TestCase {

	private function build(): TestPanel {
		return ( new ReflectionClass( TestPanel::class ) )->newInstanceWithoutConstructor();
	}

	private function load_modules( Panel $panel, array $classes, callable $factory ): void {
		$method = new ReflectionMethod( Panel::class, 'load_modules' );
		$method->invoke( $panel, $classes, $factory );
	}

	public function test_calls_the_factory_for_every_existing_class(): void {
		// Regression test for R1's shared loader.
		$called = [];

		$this->load_modules( $this->build(), [ TestPanelModuleA::class, TestPanelModuleB::class ], function ( string $class ) use ( &$called ) {
			$called[] = $class;
		} );

		$this->assertSame( [ TestPanelModuleA::class, TestPanelModuleB::class ], $called );
	}

	public function test_skips_a_class_that_does_not_exist(): void {
		$called = [];

		$this->load_modules( $this->build(), [ TestPanelModuleA::class, 'Totally\\Not\\A\\Real\\Class' ], function ( string $class ) use ( &$called ) {
			$called[] = $class;
		} );

		$this->assertSame( [ TestPanelModuleA::class ], $called );
	}

	public function test_skips_a_non_string_entry(): void {
		$called = [];

		$this->load_modules( $this->build(), [ TestPanelModuleA::class, [ 'not' => 'a string' ], 42 ], function ( string $class ) use ( &$called ) {
			$called[] = $class;
		} );

		$this->assertSame( [ TestPanelModuleA::class ], $called );
	}
}

class TestPanel extends Panel {
	protected function set_vars(): void {
	}

	protected function init(): void {
	}

	protected function custom_header( string $page ): void {
	}
}

class TestPanelModuleA {
}

class TestPanelModuleB {
}
