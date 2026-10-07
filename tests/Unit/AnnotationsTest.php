<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit;

use Netivo\Core\Annotations;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

#[CoversClass( Annotations::class )]
class AnnotationsTest extends TestCase {

	/**
	 * Invokes the protected static parse_annotations() method directly, so each docblock
	 * format can be checked without needing a full fixture class per case.
	 */
	private function parse_annotations( string $docblock ): array {
		$method = new ReflectionMethod( Annotations::class, 'parse_annotations' );

		return $method->invoke( null, $docblock );
	}

	public function test_parses_a_simple_annotation_with_string_and_numeric_args(): void {
		$docblock = "/**\n * @Table(name=\"nt_test_items\", version=2)\n */";

		$result = $this->parse_annotations( $docblock );

		$this->assertArrayHasKey( 'Table', $result );
		$this->assertSame( 'nt_test_items', $result['Table'][0]['name'] );
		$this->assertSame( 2, $result['Table'][0]['version'] );
	}

	public function test_casts_boolean_and_numeric_values(): void {
		$docblock = "/**\n * @Column(name=\"id\", type=\"bigint(20)\", primary=true, required=false, default=0)\n */";

		$result = $this->parse_annotations( $docblock );

		$this->assertTrue( $result['Column'][0]['primary'] );
		$this->assertFalse( $result['Column'][0]['required'] );
		$this->assertSame( 0, $result['Column'][0]['default'] );
	}

	public function test_preserves_a_float_version(): void {
		$docblock = "/**\n * @Table(name=\"nt_test_items\", version=1.5)\n */";

		$result = $this->parse_annotations( $docblock );

		$this->assertSame( 1.5, $result['Table'][0]['version'] );
	}

	public function test_returns_empty_array_when_docblock_has_no_annotations(): void {
		$docblock = "/**\n * Just a regular description, no annotations here.\n */";

		$result = $this->parse_annotations( $docblock );

		$this->assertSame( [], $result );
	}

	public function test_get_class_annotations_reads_a_class_level_docblock(): void {
		$result = Annotations::get_class_annotations( AnnotatedFixture::class );

		$this->assertArrayHasKey( 'Table', $result );
		$this->assertSame( 'nt_fixture', $result['Table'][0]['name'] );
	}

	public function test_get_class_annotations_caches_the_parsed_result(): void {
		$first  = Annotations::get_class_annotations( AnnotatedFixture::class );
		$second = Annotations::get_class_annotations( AnnotatedFixture::class );

		$this->assertSame( $first, $second );
	}

	public function test_parses_a_composite_brace_value_without_a_type_error(): void {
		// Regression test: cast_value() was typed `string $val`, but the '{...}' composite
		// syntax passes it an array, which fatals with a TypeError on a strictly-typed
		// string parameter.
		$docblock = "/**\n * @Taxonomy(options={a=\"1\", b=\"2\"})\n */";

		$result = $this->parse_annotations( $docblock );

		$this->assertSame( [ 'a' => 1, 'b' => 2 ], $result['Taxonomy'][0]['options'] );
	}
}

/**
 * @Table(name="nt_fixture", version=1)
 */
class AnnotatedFixture {
}
