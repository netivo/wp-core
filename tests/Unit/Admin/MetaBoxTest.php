<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Netivo\Core\Admin\MetaBox;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass( MetaBox::class )]
class MetaBoxTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs( [ 'sanitize_text_field', 'wp_unslash' ] );
	}

	protected function tearDown(): void {
		unset( $_POST['nt_test_box_nonce'], $_POST['post_type'] );
		parent::tearDown();
	}

	private function build(): TestMetaBox {
		return ( new ReflectionClass( TestMetaBox::class ) )->newInstanceWithoutConstructor();
	}

	public function test_do_save_returns_early_when_nonce_field_is_missing(): void {
		$box = $this->build();

		$this->assertSame( 5, $box->do_save( 5 ) );
		$this->assertFalse( $box->saved );
	}

	public function test_do_save_returns_early_when_nonce_is_invalid(): void {
		$_POST['nt_test_box_nonce'] = 'bad';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$box = $this->build();

		$this->assertSame( 5, $box->do_save( 5 ) );
		$this->assertFalse( $box->saved );
	}

	public function test_do_save_returns_early_when_post_type_does_not_match_screen(): void {
		// Regression test for B7: post_type is read via isset() before use.
		$_POST['nt_test_box_nonce'] = 'good';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$box = $this->build();

		$this->assertSame( 5, $box->do_save( 5 ) );
		$this->assertFalse( $box->saved );
	}

	public function test_do_save_returns_early_when_user_lacks_capability(): void {
		$_POST['nt_test_box_nonce'] = 'good';
		$_POST['post_type']         = 'post';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );

		$box = $this->build();

		$this->assertSame( 5, $box->do_save( 5 ) );
		$this->assertFalse( $box->saved );
	}

	public function test_do_save_calls_save_when_every_check_passes(): void {
		$_POST['nt_test_box_nonce'] = 'good';
		$_POST['post_type']         = 'post';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$box = $this->build();

		$this->assertSame( 5, $box->do_save( 5 ) );
		$this->assertTrue( $box->saved );
	}

	/**
	 * Invokes the protected get_page_on_front() via reflection.
	 */
	private function get_page_on_front( MetaBox $box ): array {
		$method = new ReflectionMethod( MetaBox::class, 'get_page_on_front' );

		return $method->invoke( $box );
	}

	public function test_get_page_on_front_uses_the_page_on_front_option_without_a_translation_plugin(): void {
		Functions\when( 'get_option' )->justReturn( '7' );
		Functions\when( 'has_filter' )->justReturn( false );

		$result = $this->get_page_on_front( $this->build() );

		$this->assertSame( [ 7 ], $result );
	}

	public function test_get_page_on_front_uses_the_wpml_active_languages_filter_when_present(): void {
		// Regression test for D3: wpml_active_languages is checked before the legacy
		// icl_get_languages() fallback.
		Functions\when( 'get_option' )->justReturn( '7' );
		Functions\when( 'has_filter' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( function ( string $hook, mixed $value, ...$args ) {
			if ( $hook === 'wpml_active_languages' ) {
				return [
					[ 'language_code' => 'en' ],
					[ 'language_code' => 'pl' ],
				];
			}
			if ( $hook === 'wpml_object_id' ) {
				return 'en' === $args[2] ? 7 : 70;
			}

			return $value;
		} );

		$result = $this->get_page_on_front( $this->build() );

		$this->assertSame( [ 7, 70 ], $result );
	}

	public function test_auto_register_false_skips_registering_any_hook(): void {
		// Regression test for R9.
		Functions\when( 'add_action' )->alias( function () {
			throw new \RuntimeException( 'add_action() should not have been called.' );
		} );

		new TestMetaBox( '/views', false );
		$this->addToAssertionCount( 1 );
	}

	public function test_register_can_be_called_explicitly_after_opting_out_of_auto_register(): void {
		$calls = [];
		Functions\when( 'add_action' )->alias( function ( string $hook ) use ( &$calls ) {
			$calls[] = $hook;
		} );

		$box = new TestMetaBox( '/views', false );
		$this->assertSame( [], $calls );

		$box->register();
		$this->assertSame( [ 'add_meta_boxes', 'save_post' ], $calls );
	}
}

class TestMetaBox extends MetaBox {
	protected string $id             = 'nt_test_box';
	protected string $title          = 'Test box';
	protected array|string $screen   = [ 'post' ];

	public bool $saved = false;

	public function save( int $post_id ): mixed {
		$this->saved = true;

		return $post_id;
	}
}
