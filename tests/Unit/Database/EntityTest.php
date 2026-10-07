<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Database;

use Netivo\Core\Database\Entity;
use Netivo\Core\Tests\Fixtures\TestEntity;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass( Entity::class )]
class EntityTest extends TestCase {

	public function test_get_self_returns_the_called_class(): void {
		$entity = new TestEntity();

		$this->assertSame( TestEntity::class, $entity->get_self() );
	}

	public function test_starts_in_the_new_state(): void {
		$entity = new TestEntity();

		$this->assertSame( 'new', $entity->get_state() );
	}

	public function test_set_state_is_fluent_and_updates_the_state(): void {
		$entity = new TestEntity();

		$result = $entity->set_state( 'changed' );

		$this->assertSame( $entity, $result );
		$this->assertSame( 'changed', $entity->get_state() );
	}

	public function test_get_table_data_reads_the_table_annotation(): void {
		$entity = new TestEntity();

		$table = $entity->get_table_data();

		$this->assertNotNull( $table );
		$this->assertSame( 'nt_test_items', $table->get_name() );
	}

	public function test_from_array_only_sets_known_columns(): void {
		$entity = new TestEntity();

		$result = $entity->from_array( [
			'title'           => 'Hello',
			'sort_order'      => 5,
			'not_a_column'    => 'ignored',
			'also_not_a_prop' => 123,
		] );

		$this->assertSame( $entity, $result );
		$this->assertSame( 'Hello', $entity->get_title() );
		$this->assertSame( 5, $entity->get_sort_order() );
	}
}
