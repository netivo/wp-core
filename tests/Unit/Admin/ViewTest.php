<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Admin;

use Netivo\Core\Admin\Page;
use Netivo\Core\Admin\View;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

#[CoversClass( View::class )]
class ViewTest extends TestCase {

	private function build_page(): ViewTestPage {
		return ( new ReflectionClass( ViewTestPage::class ) )->newInstanceWithoutConstructor();
	}

	public function test_set_and_get_store_arbitrary_variables(): void {
		$view = new View( $this->build_page() );

		$view->title = 'Hello';

		$this->assertSame( 'Hello', $view->title );
	}

	public function test_get_returns_null_for_an_unset_variable(): void {
		$view = new View( $this->build_page() );

		$this->assertNull( $view->never_set );
	}

	public function test_call_proxies_to_a_public_method_on_the_page(): void {
		$page = $this->build_page();
		$view = new View( $page );

		$view->mark_called( 'from the view' );

		$this->assertSame( 'from the view', $page->called_with );
	}

	public function test_content_throws_when_the_page_has_no_view_file(): void {
		$page = $this->build_page();
		$view = new View( $page );

		$this->expectException( \Exception::class );
		$view->content();
	}
}

class ViewTestPage extends Page {
	public ?string $called_with = null;

	public function do_action(): void {
	}

	public function save(): void {
	}

	public function mark_called( string $value ): void {
		$this->called_with = $value;
	}

	public function get_view_file(): string {
		return '/does/not/exist.phtml';
	}
}
