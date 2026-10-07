<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Support;

/**
 * Minimal stand-in for $wpdb, just enough to assert on the SQL/args EntityManager builds.
 *
 * prepare() mimics wpdb::prepare()'s placeholder handling closely enough for assertions:
 * %s is quoted, %d/%f are not. It does not attempt to replicate wpdb's SQL escaping.
 *
 * #[AllowDynamicProperties] matches real wpdb, which carries the same attribute: EntityManager
 * assigns a dynamic `$wpdb->{$table_name}` property for backward compatibility (see D2).
 */
#[\AllowDynamicProperties]
class FakeWpdb {

	public string $prefix = 'wp_';

	public int $insert_id = 42;

	/** @var array<int, array{table: string, data: array, format: array}> */
	public array $insert_calls = [];

	/** @var array<int, array{table: string, data: array, where: array, format: array, where_format: array}> */
	public array $update_calls = [];

	/** @var array<int, array{table: string, where: array, where_format: array}> */
	public array $delete_calls = [];

	/** @var array<int, string> */
	public array $queries = [];

	/** @var mixed Value returned by the next get_results()/get_var()/query() call. */
	public mixed $next_result = null;

	public function prepare( string $query, mixed ...$args ): string {
		if ( count( $args ) === 1 && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$i = -1;
		$query = preg_replace_callback( '/%[sdf]/', function ( array $matches ) use ( &$i, $args ) {
			$i ++;
			$value = $args[ $i ] ?? '';

			return $matches[0] === '%s' ? "'" . addslashes( (string) $value ) . "'" : (string) $value;
		}, $query );

		return $query;
	}

	public function insert( string $table, array $data, array $format = [] ): int {
		$this->insert_calls[] = [ 'table' => $table, 'data' => $data, 'format' => $format ];

		return 1;
	}

	public function update( string $table, array $data, array $where, array $format = [], array $where_format = [] ): int {
		$this->update_calls[] = [
			'table'        => $table,
			'data'         => $data,
			'where'        => $where,
			'format'       => $format,
			'where_format' => $where_format,
		];

		return 1;
	}

	public function delete( string $table, array $where, array $where_format = [] ): int {
		$this->delete_calls[] = [ 'table' => $table, 'where' => $where, 'where_format' => $where_format ];

		return 1;
	}

	public function get_results( string $query ): array {
		$this->queries[] = $query;

		return is_array( $this->next_result ) ? $this->next_result : [];
	}

	public function get_var( string $query ): mixed {
		$this->queries[] = $query;

		return $this->next_result;
	}

	public function query( string $query ): mixed {
		$this->queries[] = $query;

		return $this->next_result ?? 1;
	}

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
}
