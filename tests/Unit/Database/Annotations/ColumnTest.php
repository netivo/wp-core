<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Database\Annotations;

use Netivo\Core\Database\Annotations\Column;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( Column::class )]
class ColumnTest extends TestCase {

	public function test_defaults(): void {
		$column = new Column();

		$this->assertSame( '%s', $column->get_format() );
		$this->assertFalse( $column->is_primary() );
		$this->assertFalse( $column->is_required() );
		$this->assertNull( $column->get_default() );
	}

	public function test_fluent_setters_round_trip_and_return_the_column(): void {
		$column = new Column();

		$result = $column
			->set_name( 'sort_order' )
			->set_type( 'int(11)' )
			->set_format( '%d' )
			->set_primary( true )
			->set_required( true )
			->set_default( '0' );

		$this->assertSame( $column, $result );
		$this->assertSame( 'sort_order', $column->get_name() );
		$this->assertSame( 'int(11)', $column->get_type() );
		$this->assertSame( '%d', $column->get_format() );
		$this->assertTrue( $column->is_primary() );
		$this->assertTrue( $column->is_required() );
		$this->assertSame( '0', $column->get_default() );
	}

	public function test_a_falsy_string_default_is_kept_not_treated_as_unset(): void {
		// Regression companion to B16: the Column object itself must distinguish "0" from
		// "no default" — EntityManager::__callStatic() relies on that via `null !== get_default()`.
		$column = ( new Column() )->set_default( '0' );

		$this->assertNotNull( $column->get_default() );
		$this->assertSame( '0', $column->get_default() );
	}
}
