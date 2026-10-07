<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Admin;

use Brain\Monkey\Functions;
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

	public function test_init_block_editor_assets_is_a_no_op_by_default(): void {
		// Regression test for D7: adding this hook must not force every existing Panel
		// subclass to implement a new abstract method.
		$this->build()->init_block_editor_assets();
		$this->addToAssertionCount( 1 );
	}

	public function test_custom_block_editor_assets_is_called_when_overridden(): void {
		// Regression test for D7: a subclass needing editor-canvas assets (which
		// admin_enqueue_scripts/custom_header() can't reach once the editor is iframed)
		// can override custom_block_editor_assets(), called from enqueue_block_assets.
		$panel = ( new ReflectionClass( TestPanelWithBlockEditorAssets::class ) )->newInstanceWithoutConstructor();

		$panel->init_block_editor_assets();

		$this->assertTrue( $panel->custom_block_editor_assets_was_called );
	}

	public function test_constructor_registers_the_enqueue_block_assets_hook(): void {
		$calls = [];
		Functions\when( 'add_action' )->alias( function ( string $hook ) use ( &$calls ) {
			$calls[] = $hook;
		} );
		Functions\when( 'wp_trigger_error' )->justReturn( true );

		$theme = $this->createStub( Theme::class );
		$theme->method( 'get_configuration' )->willReturn( [] );

		new TestPanel( $theme );

		$this->assertContains( 'enqueue_block_assets', $calls );
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

class TestPanelWithBlockEditorAssets extends Panel {
	public bool $custom_block_editor_assets_was_called = false;

	protected function set_vars(): void {
	}

	protected function init(): void {
	}

	protected function custom_header( string $page ): void {
	}

	protected function custom_block_editor_assets(): void {
		$this->custom_block_editor_assets_was_called = true;
	}
}

class TestPanelModuleA {
}

class TestPanelModuleB {
}
