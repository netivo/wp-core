<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Netivo\Core\Admin\Page;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( Page::class )]
class PageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs( [ 'sanitize_key', 'wp_unslash', 'esc_html' ] );
		Functions\when( 'add_action' )->justReturn( true );
	}

	protected function tearDown(): void {
		unset( $_GET['tab'], $_POST['save_main-page'] );
		parent::tearDown();
	}

	public function test_is_tab_reflects_the_type(): void {
		$main = new TestMainPage( '/views', [] );
		$this->assertFalse( $main->is_tab() );
	}

	public function test_get_slug_returns_the_menu_slug(): void {
		$main = new TestMainPage( '/views', [] );
		$this->assertSame( 'main-page', $main->get_slug() );
	}

	public function test_generate_redirect_builds_a_plain_url_for_a_non_tab_page(): void {
		$main = new TestMainPage( '/views', [] );
		$this->assertSame( 'admin.php?page=main-page', $main->get_redirect_url() );
	}

	public function test_generate_redirect_builds_a_tab_url_including_the_parent(): void {
		$tab = new TestTabPage( '/views', [] );
		$this->assertSame( 'admin.php?page=main-page&tab=tab-one', $tab->get_redirect_url() );
	}

	public function test_is_post_is_true_only_when_its_own_save_button_was_submitted(): void {
		$main = new TestMainPage( '/views', [] );
		$this->assertFalse( $main->is_post() );

		$_POST['save_main-page'] = '1';
		$this->assertTrue( $main->is_post() );
	}

	public function test_nonce_field_outputs_a_nonce_scoped_to_the_menu_slug(): void {
		$captured = null;
		Functions\when( 'wp_nonce_field' )->alias( function ( string $action ) use ( &$captured ) {
			$captured = $action;
		} );

		( new TestMainPage( '/views', [] ) )->nonce_field();

		$this->assertSame( 'save_main-page', $captured );
	}

	public function test_register_children_resolves_tabs_via_find_tab(): void {
		// Regression test for B14: register_children() used to never store the children
		// it created, so find_tab() always returned null.
		$main = new TestMainPage( '/views', [
			[ 'class' => TestTabPage::class ],
		] );

		$tab = $main->find_tab( 'tab-one' );

		$this->assertInstanceOf( TestTabPage::class, $tab );
		$this->assertTrue( $tab->is_tab() );
	}

	public function test_find_tab_returns_null_for_an_unknown_slug(): void {
		$main = new TestMainPage( '/views', [
			[ 'class' => TestTabPage::class ],
		] );

		$this->assertNull( $main->find_tab( 'no-such-tab' ) );
	}

	public function test_register_children_skips_a_class_that_does_not_exist(): void {
		$main = new TestMainPage( '/views', [
			[ 'class' => 'Totally\\Not\\A\\Real\\Class' ],
		] );

		$this->assertNull( $main->find_tab( 'anything' ) );
	}

	public function test_do_save_delegates_to_the_matching_tab_without_touching_its_own_save(): void {
		$_GET['tab'] = 'tab-one';

		$main = new TestMainPage( '/views', [
			[ 'class' => TestTabPage::class ],
		] );

		$main->do_save();

		$tab = $main->find_tab( 'tab-one' );
		$this->assertTrue( $tab->save_was_delegated_to );
	}

	public function test_a_subclass_using_the_new_unprefixed_properties_works_the_same_way(): void {
		// Forward-compatible path (R10): no `_`-prefixed overrides at all.
		$main = new NewStylePage( '/views', [] );

		$this->assertSame( 'new-style-page', $main->get_slug() );
		$this->assertSame( 'admin.php?page=new-style-page', $main->get_redirect_url() );
	}

	public function test_an_old_style_subclass_triggers_a_deprecation_notice_per_overridden_property(): void {
		// Regression test for R10: overriding a `_`-prefixed property still works (see the
		// other tests using TestMainPage/TestTabPage throughout this file), but must now
		// also warn, since it's deprecated.
		$notices = [];
		set_error_handler( function ( int $errno, string $message ) use ( &$notices ) {
			$notices[] = $message;

			return true;
		}, E_USER_DEPRECATED );

		try {
			new TestTabPage( '/views', [] );
		} finally {
			restore_error_handler();
		}

		// TestTabPage overrides three deprecated properties: $_type, $_menu_slug, $_parent.
		$this->assertCount( 3, $notices );
		$combined = implode( ' | ', $notices );
		$this->assertStringContainsString( 'rename it to $type', $combined );
		$this->assertStringContainsString( 'rename it to $menu_slug', $combined );
		$this->assertStringContainsString( 'rename it to $parent', $combined );
	}

	public function test_auto_register_false_skips_registering_any_hook(): void {
		// Regression test for R9: constructing with $auto_register = false must not call
		// add_action() at all.
		Functions\when( 'add_action' )->alias( function () {
			throw new \RuntimeException( 'add_action() should not have been called.' );
		} );

		new TestMainPage( '/views', [], false );
		$this->addToAssertionCount( 1 );
	}

	public function test_register_can_be_called_explicitly_after_opting_out_of_auto_register(): void {
		$calls = [];
		Functions\when( 'add_action' )->alias( function ( string $hook ) use ( &$calls ) {
			$calls[] = $hook;
		} );

		$main = new TestMainPage( '/views', [], false );
		$this->assertSame( [], $calls );

		$main->register();
		$this->assertSame( [ 'admin_init', 'admin_menu' ], $calls );
	}
}

class NewStylePage extends Page {
	protected string $menu_slug = 'new-style-page';

	public function do_action(): void {
	}

	public function save(): void {
	}

	public function get_redirect_url(): string {
		return $this->redirect_url;
	}
}

class TestMainPage extends Page {
	protected string $_menu_slug = 'main-page';

	public function do_action(): void {
	}

	public function save(): void {
	}

	public function get_redirect_url(): string {
		return $this->_redirect_url;
	}
}

class TestTabPage extends Page {
	protected string $_type      = 'tab';
	protected string $_menu_slug = 'tab-one';
	protected string $_parent    = 'main-page';

	public bool $save_was_delegated_to = false;

	public function do_action(): void {
	}

	public function save(): void {
	}

	public function get_redirect_url(): string {
		return $this->_redirect_url;
	}

	public function do_save(): void {
		$this->save_was_delegated_to = true;
	}
}
