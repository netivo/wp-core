<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Brain\Monkey\Functions;
use Netivo\Core\Tests\Fixtures\ConcreteTheme;
use Netivo\Core\Tests\TestCase;
use Netivo\Core\Theme;
use PHPUnit\Framework\Attributes\CoversMethod;
use ReflectionClass;
use ReflectionMethod;

#[CoversMethod( Theme::class, 'init_configuration' )]
class ConfigMergeTest extends TestCase {

	private const PARENT_DIR = __DIR__ . '/../stubs/parent-theme';
	private const CHILD_DIR  = __DIR__ . '/../stubs/child-theme';

	/**
	 * Builds a Theme instance (skipping the constructor) and runs init_configuration() on it.
	 */
	private function configure_theme( string $stylesheet_dir, string $template_dir ): ConcreteTheme {
		Functions\when( 'get_stylesheet_directory' )->justReturn( $stylesheet_dir );
		Functions\when( 'get_template_directory' )->justReturn( $template_dir );

		$theme  = ( new ReflectionClass( ConcreteTheme::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( ConcreteTheme::class, 'init_configuration' );
		$method->invoke( $theme );

		return $theme;
	}

	public function test_loads_every_config_file_from_the_parent_theme_when_no_child_theme_overrides_them(): void {
		$theme = $this->configure_theme( self::PARENT_DIR, self::PARENT_DIR );

		$config = $theme->get_configuration();

		$this->assertSame( 'parent', $config['main_marker'] );
		$this->assertSame( 'Parent Menu', $config['menu']['primary_menu']['name'] );
		$this->assertSame( 'thumb', $config['image']['thumb']['name'] );
		$this->assertArrayHasKey( 'modules', $config );
	}

	public function test_child_theme_config_file_overrides_the_parent_file_entirely(): void {
		// Regression test for B3.
		$theme = $this->configure_theme( self::CHILD_DIR, self::PARENT_DIR );

		$config = $theme->get_configuration();

		$this->assertSame( 'Child Menu', $config['menu']['primary_menu']['name'] );
	}

	public function test_files_the_child_theme_does_not_override_still_load_from_the_parent(): void {
		// The child fixture theme only ships menu.config.php; everything else must still
		// come from the parent/template theme.
		$theme = $this->configure_theme( self::CHILD_DIR, self::PARENT_DIR );

		$config = $theme->get_configuration();

		$this->assertSame( 'parent', $config['main_marker'] );
		$this->assertSame( 'thumb', $config['image']['thumb']['name'] );
	}

	public function test_view_path_falls_back_to_the_template_directory_when_not_configured(): void {
		$theme = $this->configure_theme( self::CHILD_DIR, self::PARENT_DIR );

		$this->assertSame( self::PARENT_DIR . '/src/views', $theme->get_view_path() );
	}
}
