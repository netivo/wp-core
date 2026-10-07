<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Database\Annotations;

use Netivo\Core\Database\Annotations\Column;
use Netivo\Core\Database\Annotations\Table;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( Table::class )]
class TableTest extends TestCase {

	public function test_name_and_version_round_trip(): void {
		$table = new Table();
		$table->set_name( 'nt_items' )->set_version( 2.0 );

		$this->assertSame( 'nt_items', $table->get_name() );
		$this->assertSame( 2.0, $table->get_version() );
	}

	public function test_add_column_keys_columns_by_the_given_name(): void {
		$table  = new Table();
		$column = ( new Column() )->set_name( 'id' )->set_type( 'bigint(20)' );

		$table->add_column( 'id', $column );

		$this->assertSame( [ 'id' => $column ], $table->get_columns() );
	}

	public function test_add_column_accumulates_multiple_columns(): void {
		$table = new Table();
		$id    = ( new Column() )->set_name( 'id' )->set_type( 'bigint(20)' );
		$title = ( new Column() )->set_name( 'title' )->set_type( 'varchar(255)' );

		$table->add_column( 'id', $id )->add_column( 'title', $title );

		$this->assertSame( [ 'id' => $id, 'title' => $title ], $table->get_columns() );
	}

	public function test_set_columns_replaces_the_whole_set(): void {
		$table = new Table();
		$table->add_column( 'id', new Column() );

		$title = new Column();
		$table->set_columns( [ 'title' => $title ] );

		$this->assertSame( [ 'title' => $title ], $table->get_columns() );
	}
}
