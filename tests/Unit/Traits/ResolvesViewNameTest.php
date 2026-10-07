<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Traits;

use Netivo\Core\Tests\TestCase;
use Netivo\Core\Traits\ResolvesViewName;
use PHPUnit\Framework\Attributes\CoversTrait;

#[CoversTrait( ResolvesViewName::class )]
class ResolvesViewNameTest extends TestCase {

	public function test_resolve_view_attribute_reads_the_named_attribute(): void {
		$instance = new WithAttributeFixture();

		$this->assertSame( 'custom/view-name', $instance->call_resolve_view_attribute( 'Netivo\Attributes\View' ) );
	}

	public function test_resolve_view_attribute_returns_null_when_attribute_is_absent(): void {
		$instance = new WithoutAttributeFixture();

		$this->assertNull( $instance->call_resolve_view_attribute( 'Netivo\Attributes\View' ) );
	}

	public function test_resolve_view_attribute_ignores_a_differently_named_attribute(): void {
		$instance = new WithAttributeFixture();

		$this->assertNull( $instance->call_resolve_view_attribute( 'Netivo\Attributes\Block' ) );
	}

	public function test_resolve_view_name_from_filename_lower_cases_the_declaring_file_basename(): void {
		$instance = new WithoutAttributeFixture();

		// The fixture classes below are declared in this very file (ResolvesViewNameTest.php),
		// so that's the basename resolve_view_name_from_filename() must return, lower-cased.
		$this->assertSame( 'resolvesviewnametest', $instance->call_resolve_view_name_from_filename() );
	}
}

trait ExposesProtectedResolvers {
	use ResolvesViewName;

	public function call_resolve_view_attribute( string $attribute_class ): ?string {
		return $this->resolve_view_attribute( $attribute_class );
	}

	public function call_resolve_view_name_from_filename(): string {
		return $this->resolve_view_name_from_filename();
	}
}

#[\Netivo\Attributes\View( 'custom/view-name' )]
class WithAttributeFixture {
	use ExposesProtectedResolvers;
}

class WithoutAttributeFixture {
	use ExposesProtectedResolvers;
}
