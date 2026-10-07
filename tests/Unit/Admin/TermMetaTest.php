<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Netivo\Core\Admin\TermMeta;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass( TermMeta::class )]
class TermMetaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs( [ 'sanitize_text_field', 'wp_unslash' ] );
	}

	protected function tearDown(): void {
		unset( $_POST['foo_nonce'], $_POST['bar_nonce'] );
		parent::tearDown();
	}

	private function build( string $class ): TermMeta {
		return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
	}

	public function test_do_save_returns_early_when_nonce_field_is_missing(): void {
		$term_meta = $this->build( FooTermMeta::class );

		$result = $term_meta->do_save( 42 );

		$this->assertSame( 42, $result );
		$this->assertFalse( $term_meta->saved );
	}

	public function test_do_save_returns_early_when_nonce_is_invalid(): void {
		$_POST['foo_nonce'] = 'bad-nonce';
		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$term_meta = $this->build( FooTermMeta::class );
		$result    = $term_meta->do_save( 42 );

		$this->assertSame( 42, $result );
		$this->assertFalse( $term_meta->saved );
	}

	public function test_do_save_returns_early_when_user_lacks_capability(): void {
		$_POST['foo_nonce'] = 'good-nonce';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );

		$term_meta = $this->build( FooTermMeta::class );
		$result    = $term_meta->do_save( 42 );

		$this->assertSame( 42, $result );
		$this->assertFalse( $term_meta->saved );
	}

	public function test_do_save_calls_save_when_nonce_and_capability_pass(): void {
		// Regression test for B15 companion: current_user_can('edit_term', ...) is checked.
		$_POST['foo_nonce'] = 'good-nonce';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$term_meta = $this->build( FooTermMeta::class );
		$result    = $term_meta->do_save( 42, 99 );

		$this->assertSame( 42, $result );
		$this->assertTrue( $term_meta->saved );
		$this->assertSame( [ 42, 99 ], $term_meta->save_args );
	}

	public function test_each_subclass_uses_its_own_meta_field_name(): void {
		// Regression test for B15: self:: used to always read the base class's empty
		// string, so every subclass shared the same (empty) nonce field name.
		$_POST['foo_nonce'] = 'good-nonce';
		$_POST['bar_nonce'] = 'good-nonce';
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$foo = $this->build( FooTermMeta::class );
		$bar = $this->build( BarTermMeta::class );

		$this->assertSame( 42, $foo->do_save( 42 ) );
		$this->assertTrue( $foo->saved );

		$this->assertSame( 43, $bar->do_save( 43 ) );
		$this->assertTrue( $bar->saved );
	}

	public function test_auto_register_false_skips_registering_any_hook(): void {
		// Regression test for R9.
		Functions\when( 'add_action' )->alias( function () {
			throw new \RuntimeException( 'add_action() should not have been called.' );
		} );

		new RegistrableTermMeta( '/views', false );
		$this->addToAssertionCount( 1 );
	}

	public function test_register_can_be_called_explicitly_after_opting_out_of_auto_register(): void {
		$calls = [];
		Functions\when( 'add_action' )->alias( function ( string $hook ) use ( &$calls ) {
			$calls[] = $hook;
		} );

		$term_meta = new RegistrableTermMeta( '/views', false );
		$this->assertSame( [], $calls );

		$term_meta->register();
		$this->assertSame( [
			'category_add_form_fields',
			'category_edit_form_fields',
			'edited_category',
			'create_category',
		], $calls );
	}
}

class RegistrableTermMeta extends TermMeta {
	public static string $META_FIELD_NAME = 'registrable';

	protected array|string $taxonomy = 'category';

	public function save( int $term_id, ?int $tt_id = null ): int {
		return $term_id;
	}
}

class FooTermMeta extends TermMeta {
	public static string $META_FIELD_NAME = 'foo';

	public bool $saved = false;
	public array $save_args = [];

	public function save( int $term_id, ?int $tt_id = null ): int {
		$this->saved     = true;
		$this->save_args = [ $term_id, $tt_id ];

		return $term_id;
	}
}

class BarTermMeta extends TermMeta {
	public static string $META_FIELD_NAME = 'bar';

	public bool $saved = false;
	public array $save_args = [];

	public function save( int $term_id, ?int $tt_id = null ): int {
		$this->saved     = true;
		$this->save_args = [ $term_id, $tt_id ];

		return $term_id;
	}
}
