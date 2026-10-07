<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Brain\Monkey\Functions;
use Netivo\Core\Tests\Fixtures\ConcreteTheme;
use Netivo\Core\Tests\TestCase;
use Netivo\Core\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass( Theme::class )]
class ThemeTest extends TestCase {

	private function build(): ConcreteTheme {
		return ( new ReflectionClass( ConcreteTheme::class ) )->newInstanceWithoutConstructor();
	}

	private function invoke( object $instance, string $method, array $args = [] ): mixed {
		return ( new ReflectionMethod( $instance, $method ) )->invoke( $instance, ...$args );
	}

	public function test_load_modules_calls_the_factory_for_every_existing_class(): void {
		$called = [];

		$this->invoke( $this->build(), 'load_modules', [
			[ ThemeTestModuleA::class, 'Totally\\Not\\A\\Real\\Class', 42 ],
			function ( string $class ) use ( &$called ) {
				$called[] = $class;
			},
		] );

		$this->assertSame( [ ThemeTestModuleA::class ], $called );
	}

	public function test_remove_pingback_header_strips_only_x_pingback(): void {
		$result = $this->build()->remove_pingback_header( [
			'X-Pingback' => 'https://example.test/xmlrpc.php',
			'Other'      => 'kept',
		] );

		$this->assertSame( [ 'Other' => 'kept' ], $result );
	}

	public function test_restrict_users_endpoints_keeps_them_for_users_who_can_edit_posts(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		$endpoints = [ '/wp/v2/users' => 'x', '/wp/v2/posts' => 'y' ];
		$result    = $this->build()->restrict_users_endpoints( $endpoints );

		$this->assertSame( $endpoints, $result );
	}

	public function test_restrict_users_endpoints_removes_them_for_everyone_else(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = $this->build()->restrict_users_endpoints( [
			'/wp/v2/users'                     => 'x',
			'/wp/v2/users/(?P<id>[\d]+)'       => 'y',
			'/wp/v2/posts'                     => 'kept',
		] );

		$this->assertSame( [ '/wp/v2/posts' => 'kept' ], $result );
	}

	public function test_remove_version_scripts_strips_the_ver_query_arg_on_the_front_end(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'remove_query_arg' )->justReturn( 'https://example.test/style.css' );

		$result = $this->build()->remove_version_scripts( 'https://example.test/style.css?ver=1.2.3' );

		$this->assertSame( 'https://example.test/style.css', $result );
	}

	public function test_remove_version_scripts_keeps_the_version_for_a_configured_asset(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'remove_query_arg' )->justReturn( 'https://example.test/style.css' );

		$theme = $this->build();
		$config_prop = new \ReflectionProperty( Theme::class, 'configuration' );
		$config_prop->setValue( $theme, [ 'assets' => [ 'versions' => [ 'https://example.test/style.css' ] ] ] );

		$result = $theme->remove_version_scripts( 'https://example.test/style.css?ver=1.2.3' );

		$this->assertSame( 'https://example.test/style.css?ver=1.2.3', $result );
	}

	public function test_remove_version_scripts_does_nothing_in_the_admin(): void {
		Functions\when( 'is_admin' )->justReturn( true );

		$result = $this->build()->remove_version_scripts( 'https://example.test/style.css?ver=1.2.3' );

		$this->assertSame( 'https://example.test/style.css?ver=1.2.3', $result );
	}

	public function test_grant_capabilities_skips_a_capability_the_role_already_has(): void {
		// Regression test for B9: capabilities are only added once, not on every request.
		$role = new class {
			public array $added = [];

			public function has_cap( string $cap ): bool {
				return 'existing' === $cap;
			}

			public function add_cap( string $cap ): void {
				$this->added[] = $cap;
			}
		};
		Functions\when( 'get_role' )->justReturn( $role );

		$this->invoke( $this->build(), 'grant_capabilities', [ [ 'existing', 'new_one' ] ] );

		$this->assertSame( [ 'new_one' ], $role->added );
	}

	public function test_grant_capabilities_does_nothing_when_the_role_is_missing(): void {
		Functions\when( 'get_role' )->justReturn( null );

		$this->invoke( $this->build(), 'grant_capabilities', [ [ 'whatever' ] ] );
		$this->addToAssertionCount( 1 );
	}

	public function test_setup_theme_support_handles_plain_and_args_entries(): void {
		// Regression test for B4: a 'key' => [args] entry used to be silently skipped.
		$calls = [];
		Functions\when( 'add_theme_support' )->alias( function ( ...$args ) use ( &$calls ) {
			$calls[] = $args;
		} );
		Functions\when( 'register_nav_menu' )->justReturn( true );
		Functions\when( '__' )->returnArg();

		$theme       = $this->build();
		$config_prop = new \ReflectionProperty( Theme::class, 'configuration' );
		$config_prop->setValue( $theme, [
			'supports' => [
				'post-thumbnails',
				'custom-logo' => [ 'height' => 100, 'width' => 400 ],
			],
		] );

		$theme->setup_theme_support();

		$this->assertSame( [ 'post-thumbnails' ], $calls[0] );
		$this->assertSame( [ 'custom-logo', [ 'height' => 100, 'width' => 400 ] ], $calls[1] );
	}

	public function test_setup_theme_support_strips_script_and_style_from_html5_args(): void {
		// Regression test for D9.
		$calls = [];
		Functions\when( 'add_theme_support' )->alias( function ( ...$args ) use ( &$calls ) {
			$calls[] = $args;
		} );
		Functions\when( 'register_nav_menu' )->justReturn( true );
		Functions\when( '__' )->returnArg();

		$theme       = $this->build();
		$config_prop = new \ReflectionProperty( Theme::class, 'configuration' );
		$config_prop->setValue( $theme, [
			'supports' => [
				'html5' => [ 'script', 'style', 'comment-list' ],
			],
		] );

		$theme->setup_theme_support();

		$this->assertSame( [ 'html5', [ 'comment-list' ] ], $calls[0] );
	}

	public function test_init_sidebars_translates_the_name_and_applies_overrides(): void {
		Functions\when( '__' )->returnArg();
		$captured = null;
		Functions\when( 'register_sidebar' )->alias( function ( array $args ) use ( &$captured ) {
			$captured = $args;
		} );

		$theme       = $this->build();
		$config_prop = new \ReflectionProperty( Theme::class, 'configuration' );
		$config_prop->setValue( $theme, [
			'sidebars' => [
				[ 'id' => 'footer', 'name' => 'Footer', 'before_title' => '<h4>' ],
			],
		] );

		$theme->init_sidebars();

		$this->assertSame( 'footer', $captured['id'] );
		$this->assertSame( 'Footer', $captured['name'] );
		$this->assertSame( '<h4>', $captured['before_title'] );
		$this->assertSame( '</div>', $captured['after_widget'] );
	}
}

class ThemeTestModuleA {
}
