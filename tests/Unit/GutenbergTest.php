<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Brain\Monkey\Functions;
use Netivo\Core\Gutenberg;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass( Gutenberg::class )]
class GutenbergTest extends TestCase {

	private const THEME_DIR = __DIR__ . '/../stubs/parent-theme';

	public function test_register_block_registers_using_the_attribute_provided_path(): void {
		Functions\when( 'get_stylesheet_directory' )->justReturn( self::THEME_DIR );
		Functions\when( 'get_template_directory' )->justReturn( self::THEME_DIR );
		$captured = null;
		Functions\when( 'register_block_type' )->alias( function ( string $path, array $args ) use ( &$captured ) {
			$captured = [ 'path' => $path, 'args' => $args ];
		} );

		$block = ( new ReflectionClass( AttributedBlock::class ) )->newInstanceWithoutConstructor();
		$block->register_block();

		$this->assertSame( self::THEME_DIR . '/blocks/my-block/block.json', $captured['path'] );
	}

	public function test_register_block_throws_when_block_json_is_not_found(): void {
		Functions\when( 'get_stylesheet_directory' )->justReturn( self::THEME_DIR );
		Functions\when( 'get_template_directory' )->justReturn( self::THEME_DIR );
		Functions\stubs( [ 'esc_html' ] );

		$block = ( new ReflectionClass( AttributedBlock::class ) )->newInstanceWithoutConstructor();

		// A second attributed block pointing at a path with no block.json fixture.
		$missing = ( new ReflectionClass( BlockWithMissingJson::class ) )->newInstanceWithoutConstructor();

		$this->expectException( \Exception::class );
		$missing->register_block();
	}

	public function test_register_block_passes_the_render_callback_when_set(): void {
		Functions\when( 'get_stylesheet_directory' )->justReturn( self::THEME_DIR );
		Functions\when( 'get_template_directory' )->justReturn( self::THEME_DIR );
		$captured = null;
		Functions\when( 'register_block_type' )->alias( function ( string $path, array $args ) use ( &$captured ) {
			$captured = $args;
		} );

		$block = ( new ReflectionClass( BlockWithCallback::class ) )->newInstanceWithoutConstructor();
		$block->register_block();

		$this->assertArrayHasKey( 'render_callback', $captured );
		$this->assertSame( [ $block, 'render' ], $captured['render_callback'] );
	}

	public function test_auto_register_false_skips_registering_any_hook(): void {
		// Regression test for R9.
		Functions\when( 'add_action' )->alias( function () {
			throw new \RuntimeException( 'add_action() should not have been called.' );
		} );

		new AttributedBlock( false );
		$this->addToAssertionCount( 1 );
	}

	public function test_register_can_be_called_explicitly_after_opting_out_of_auto_register(): void {
		$calls = [];
		Functions\when( 'add_action' )->alias( function ( string $hook ) use ( &$calls ) {
			$calls[] = $hook;
		} );

		$block = new AttributedBlock( false );
		$this->assertSame( [], $calls );

		$block->register();
		$this->assertSame( [ 'init' ], $calls );
	}
}

#[\Netivo\Attributes\Block( 'blocks/my-block' )]
class AttributedBlock extends Gutenberg {
}

#[\Netivo\Attributes\Block( 'blocks/does-not-exist' )]
class BlockWithMissingJson extends Gutenberg {
}

#[\Netivo\Attributes\Block( 'blocks/my-block' )]
class BlockWithCallback extends Gutenberg {
	protected ?string $callback = 'render';

	public function render(): string {
		return '';
	}
}
