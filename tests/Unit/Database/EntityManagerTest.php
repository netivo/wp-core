<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Unit\Database;

use Brain\Monkey\Functions;
use Netivo\Core\Database\Annotations;
use Netivo\Core\Database\EntityManager;
use Netivo\Core\Tests\Fixtures\TestEntity;
use Netivo\Core\Tests\Support\FakeWpdb;
use Netivo\Core\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

#[CoversClass( EntityManager::class )]
class EntityManagerTest extends TestCase {

	private FakeWpdb $wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb          = new FakeWpdb();
		$GLOBALS['wpdb']     = $this->wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Invokes EntityManager's protected static build_where() via reflection, against
	 * TestEntity's own table annotation.
	 */
	private function build_where( ?array $where ): string {
		$method = new ReflectionMethod( EntityManager::class, 'build_where' );
		$table  = Annotations::get_table_annotations( TestEntity::class );

		return $method->invoke( null, $table, $where );
	}

	/**
	 * Invokes EntityManager's protected static build_order() via reflection, against
	 * TestEntity's own table annotation.
	 */
	private function build_order( ?array $order ): string {
		$method = new ReflectionMethod( EntityManager::class, 'build_order' );
		$table  = Annotations::get_table_annotations( TestEntity::class );

		return $method->invoke( null, $table, $order );
	}

	public function test_build_where_returns_empty_string_for_no_conditions(): void {
		$this->assertSame( '', $this->build_where( null ) );
		$this->assertSame( '', $this->build_where( [] ) );
	}

	public function test_build_where_builds_a_simple_equals_condition(): void {
		$sql = $this->build_where( [ 'title' => [ 'type' => '%s', 'value' => "o'brien" ] ] );

		$this->assertSame( " WHERE title = 'o\\'brien'", $sql );
	}

	public function test_build_where_supports_a_custom_operator(): void {
		$sql = $this->build_where( [ 'sort_order' => [ 'type' => '%d', 'value' => 5, 'operator' => '>' ] ] );

		$this->assertSame( ' WHERE sort_order > 5', $sql );
	}

	public function test_build_where_wraps_like_values_in_wildcards(): void {
		$sql = $this->build_where( [ 'title' => [ 'type' => '%s', 'value' => 'foo', 'operator' => 'LIKE' ] ] );

		$this->assertSame( " WHERE title LIKE '%foo%'", $sql );
	}

	public function test_build_where_joins_multiple_conditions_with_and(): void {
		$sql = $this->build_where( [
			'title'      => [ 'type' => '%s', 'value' => 'foo' ],
			'sort_order' => [ 'type' => '%d', 'value' => 1 ],
		] );

		$this->assertSame( " WHERE title = 'foo' AND sort_order = 1", $sql );
	}

	public function test_build_where_rejects_a_column_name_not_on_the_entity(): void {
		// Regression test for S5: a $where key must be one of the entity's own columns.
		$sql = $this->build_where( [ 'id = 1; DROP TABLE wp_users; --' => [ 'type' => '%s', 'value' => 'x' ] ] );

		$this->assertSame( '', $sql );
	}

	public function test_build_where_rejects_an_operator_outside_the_allow_list(): void {
		$sql = $this->build_where( [ 'title' => [ 'type' => '%s', 'value' => 'x', 'operator' => '; DROP TABLE wp_users; --' ] ] );

		$this->assertSame( '', $sql );
	}

	public function test_build_where_rejects_a_placeholder_type_outside_the_allow_list(): void {
		$sql = $this->build_where( [ 'title' => [ 'type' => '%i', 'value' => 'x' ] ] );

		$this->assertSame( '', $sql );
	}

	public function test_build_where_drops_only_the_rejected_condition_keeping_valid_ones(): void {
		$sql = $this->build_where( [
			'not_a_real_column' => [ 'type' => '%s', 'value' => 'x' ],
			'title'              => [ 'type' => '%s', 'value' => 'foo' ],
		] );

		$this->assertSame( " WHERE title = 'foo'", $sql );
	}

	public function test_build_order_rejects_a_column_name_not_on_the_entity(): void {
		// Regression test for S5: an $order key must be one of the entity's own columns.
		$sql = $this->build_order( [ 'id; DROP TABLE wp_users; --' => 'ASC' ] );

		$this->assertSame( '', $sql );
	}

	public function test_build_order_rejects_a_direction_outside_the_allow_list(): void {
		$sql = $this->build_order( [ 'title' => 'ASC; DROP TABLE wp_users; --' ] );

		$this->assertSame( '', $sql );
	}

	public function test_build_order_accepts_a_valid_column_and_direction(): void {
		$sql = $this->build_order( [ 'title' => 'desc', 'sort_order' => 'ASC' ] );

		$this->assertSame( ' ORDER BY title DESC, sort_order ASC', $sql );
	}

	public function test_insert_keeps_a_falsy_but_explicitly_set_value(): void {
		// Regression test for B2: insert() used to drop columns whose value was `0`/`''`/false.
		$entity = new TestEntity();
		$entity->set_title( 'Something' );
		$entity->set_sort_order( 0 );
		$entity->set_is_active( 0 );

		EntityManager::save( $entity );

		$this->assertCount( 1, $this->wpdb->insert_calls );
		$data = $this->wpdb->insert_calls[0]['data'];
		$this->assertArrayHasKey( 'sort_order', $data );
		$this->assertSame( 0, $data['sort_order'] );
		$this->assertArrayHasKey( 'is_active', $data );
		$this->assertSame( 0, $data['is_active'] );
	}

	public function test_insert_sets_the_new_id_and_marks_the_entity_existing(): void {
		$this->wpdb->insert_id = 7;

		$entity = new TestEntity();
		$entity->set_title( 'Something' );

		EntityManager::save( $entity );

		$this->assertSame( 7, $entity->get_id() );
		$this->assertSame( 'existing', $entity->get_state() );
	}

	public function test_update_keeps_a_falsy_but_explicitly_set_value(): void {
		$entity = new TestEntity();
		$entity->set_id( 3 );
		$entity->set_state( 'changed' );
		$entity->set_title( 'Something' );
		$entity->set_sort_order( 0 );

		EntityManager::save( $entity );

		$this->assertCount( 1, $this->wpdb->update_calls );
		$this->assertArrayHasKey( 'sort_order', $this->wpdb->update_calls[0]['data'] );
		$this->assertSame( 0, $this->wpdb->update_calls[0]['data']['sort_order'] );
	}

	public function test_count_uses_select_count_instead_of_fetching_all_rows(): void {
		// Regression test for B5.
		$this->wpdb->next_result = '3';

		$count = EntityManager::get( TestEntity::class )->count();

		$this->assertSame( 3, $count );
		$this->assertStringContainsString( 'SELECT COUNT(*) FROM wp_nt_test_items', $this->wpdb->queries[0] );
	}

	public function test_create_table_builds_valid_ddl_with_a_space_before_unsigned(): void {
		// Regression test for B16: the primary-key column previously had no space before
		// "unsigned", producing invalid SQL like "intunsigned".
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );
		$captured_sql = null;
		Functions\when( 'dbDelta' )->alias( function ( string $sql ) use ( &$captured_sql ) {
			$captured_sql = $sql;
		} );

		$method_name = 'create_table_' . str_replace( '\\', '_', TestEntity::class );
		EntityManager::{$method_name}();

		$this->assertNotNull( $captured_sql );
		$this->assertStringContainsString( 'id bigint(20) unsigned NOT NULL auto_increment PRIMARY KEY', $captured_sql );
	}

	public function test_create_table_keeps_a_falsy_default_value(): void {
		// Regression test for B16: a column defaulting to 0 previously got no DEFAULT clause.
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );
		$captured_sql = null;
		Functions\when( 'dbDelta' )->alias( function ( string $sql ) use ( &$captured_sql ) {
			$captured_sql = $sql;
		} );

		$method_name = 'create_table_' . str_replace( '\\', '_', TestEntity::class );
		EntityManager::{$method_name}();

		$this->assertStringContainsString( "sort_order int(11) NULL DEFAULT '0'", $captured_sql );
	}

	public function test_create_table_skips_dbdelta_when_version_already_matches(): void {
		$table = Annotations::get_table_annotations( TestEntity::class );
		Functions\when( 'get_option' )->justReturn( [ $table->get_name() => $table->get_version() ] );
		$called = false;
		Functions\when( 'dbDelta' )->alias( function () use ( &$called ) {
			$called = true;
		} );

		$method_name = 'create_table_' . str_replace( '\\', '_', TestEntity::class );
		EntityManager::{$method_name}();

		$this->assertFalse( $called );
	}
}
