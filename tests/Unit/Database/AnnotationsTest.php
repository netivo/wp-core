<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Database;

use Netivo\Core\Database\Annotations;
use Netivo\Core\Tests\Fixtures\TestEntity;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( Annotations::class )]
class AnnotationsTest extends TestCase {

	public function test_reads_table_name_and_version_from_the_class_docblock(): void {
		$table = Annotations::get_table_annotations( TestEntity::class );

		$this->assertNotNull( $table );
		$this->assertSame( 'nt_test_items', $table->get_name() );
		$this->assertSame( 2.0, $table->get_version() );
	}

	public function test_reads_every_column_from_property_docblocks(): void {
		$table   = Annotations::get_table_annotations( TestEntity::class );
		$columns = $table->get_columns();

		$this->assertCount( 4, $columns );
		$this->assertArrayHasKey( 'id', $columns );
		$this->assertArrayHasKey( 'title', $columns );
		$this->assertArrayHasKey( 'sort_order', $columns );
		$this->assertArrayHasKey( 'is_active', $columns );
	}

	public function test_reads_primary_key_and_format_flags(): void {
		$columns = Annotations::get_table_annotations( TestEntity::class )->get_columns();

		$this->assertTrue( $columns['id']->is_primary() );
		$this->assertSame( '%d', $columns['id']->get_format() );
		$this->assertFalse( $columns['title']->is_primary() );
		$this->assertTrue( $columns['title']->is_required() );
	}

	public function test_reads_a_falsy_default_value_as_a_string_not_as_empty(): void {
		// Regression test for B16: a default of 0 must not be treated as "no default".
		$column = Annotations::get_table_annotations( TestEntity::class )->get_columns()['sort_order'];

		$this->assertNotNull( $column->get_default() );
		$this->assertSame( '0', $column->get_default() );
	}

	public function test_caches_the_table_annotations_per_class(): void {
		$first  = Annotations::get_table_annotations( TestEntity::class );
		$second = Annotations::get_table_annotations( TestEntity::class );

		$this->assertSame( $first, $second );
	}
}
