<?php
/**
 * Created by Netivo for Netivo Core Plugin.
 * User: Michal
 * Date: 23.10.2018
 * Time: 15:14
 *
 * @package Netivo\Core\Database
 */

namespace Netivo\Core\Database;

use Netivo\Core\Database\Annotations;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class EntityManager
 */
class EntityManager {

	/**
	 * Entity name to run sql.
	 *
	 * @var string
	 */
	protected string $entity_name = '';

	/**
	 * Get the specified entity.
	 *
	 * @param string $entityName Class name of entity to get.
	 *
	 * @return object
	 */
	public static function get( string $entityName ): object {
		return new self( $entityName );
	}

	/**
	 * Adds action to create database of entity.
	 *
	 * @param string $entity_name Class name of entity to create.
	 */
	public static function createTable( string $entity_name ): void {
		add_action( 'after_setup_theme', [
			self::class,
			'create_table_' . str_replace( '\\', '_', $entity_name )
		] );
	}

	/**
	 * Magic method to call create_table_%class%.
	 *
	 * @param string $name Name of method.
	 * @param mixed  $arguments Argments of method.
	 *
	 * @throws \ReflectionException When error.
	 */
	public static function __callStatic( string $name, mixed $arguments ) {
		if ( str_starts_with( $name, 'create_table_' ) ) {
			$class_name = str_replace( 'create_table_', '', $name );
			$class_name = str_replace( '_', '\\', $class_name );

			$table = Annotations::get_table_annotations( $class_name );

			if ( ! empty( $table ) ) {

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				global $wpdb;

				$v = get_option( '_nt_db_version', array() );

				if ( ! array_key_exists( $table->get_name(), $v ) || ( $table->get_version() != $v[ $table->get_name() ] ) ) {

					$fld = [];
					foreach ( $table->get_columns() as $key => $column ) {
						$tmp = "{$column->get_name()} {$column->get_type()}";
						if ( $column->is_primary() ) {
							$tmp .= "unsigned NOT NULL auto_increment PRIMARY KEY";
						} else {
							if ( $column->is_required() ) {
								$tmp .= " NOT NULL";
							} else {
								$tmp .= " NULL";
							}
							if ( ! empty( $column->get_default() ) ) {
								$tmp .= " DEFAULT '{$column->get_default()}'";
							}
						}
						$fld[] = $tmp;
					}
					$sql = "CREATE TABLE {$wpdb->prefix}{$table->get_name()} ( " . implode( ',', $fld ) . ") {$wpdb->get_charset_collate()}";

					dbDelta( $sql );

					$v[ $table->get_name() ] = $table->get_version();

					update_option( '_nt_db_version', $v );
				}

				$tn        = $table->get_name();
				$wpdb->$tn = "{$wpdb->prefix}{$tn}";

			}
		}
	}

	/**
	 * Saves the entity.
	 *
	 * @param mixed $entity Entity to save.
	 *
	 * @return mixed
	 */
	public static function save( mixed $entity ): mixed {
		if ( $entity->get_state() == 'new' ) {
			return self::insert( $entity );
		} elseif ( $entity->get_state() == 'changed' ) {
			return self::update( $entity );
		}

		return $entity;
	}

	/**
	 * Deletes entity from database table.
	 *
	 * @param mixed $entity Entity to delete.
	 *
	 * @return null|object
	 */
	public static function delete( mixed $entity ): ?object {
		if ( $entity->get_state() == 'new' ) {
			return $entity;
		}
		global $wpdb;
		$table = $entity->get_table_data();
		if ( ! empty( $table ) ) {
			$name    = $table->get_name();
			$deleted = $wpdb->delete( $wpdb->$name, array( 'id' => $entity->get_id() ), array( $table->get_columns()['id']->get_format() ) );
			if ( $deleted ) {
				return $entity;
			} else {
				return null;
			}
		}

		return null;
	}

	/**
	 * Inserts entity into database table.
	 *
	 * @param mixed $entity Entity to insert.
	 *
	 * @return null|mixed
	 */
	protected static function insert( mixed $entity ): mixed {
		global $wpdb;
		$table = $entity->get_table_data();
		if ( ! empty( $table ) ) {
			$data  = array();
			$types = array();
			foreach ( $table->get_columns() as $key => $column ) {
				if ( $key != 'id' ) {
					$method = 'get_' . $column->get_name();
					if ( $entity->$method() != null ) {
						$data[ $key ] = $entity->$method();
						$types[]      = $column->get_format();
					}
				}
			}
			unset( $data['id'] );

			$name = $table->get_name();

			$inserted = $wpdb->insert( $wpdb->$name, $data, $types );
			if ( $inserted ) {
				$entity->set_id( $wpdb->insert_id );
				$entity->set_state( 'existing' );

				return $entity;
			}

		}

		return null;
	}

	/**
	 * Updates entity in database table.
	 *
	 * @param mixed $entity Entity to update.
	 *
	 * @return null|object
	 */
	protected static function update( mixed $entity ): ?object {
		global $wpdb;
		$table = $entity->get_table_data();
		if ( ! empty( $table ) ) {
			$data  = array();
			$types = array();
			foreach ( $table->get_columns() as $key => $column ) {
				if ( $key != 'id' ) {
					$method = 'get_' . $column->get_name();
					if ( $entity->$method() !== null ) {
						$data[ $key ] = $entity->$method();
						$types[]      = $column->get_format();
					}
				}
			}
			unset( $data['id'] );
			$name    = $table->get_name();
			$updated = $wpdb->update( $wpdb->$name, $data, array( 'id' => $entity->get_id() ), $types, array( $table->get_columns()['id']->get_format() ) );

			if ( $updated ) {
				return $entity;
			}

		}

		return null;
	}

	/**
	 * EntityManager constructor.
	 *
	 * @param string $entity_name Class name of entity.
	 */
	protected function __construct( string $entity_name ) {
		$this->entity_name = $entity_name;
	}

	/**
	 * Finds all entities matching query.
	 *
	 * @param array|null $where Array with where options.
	 * @param array|null $order Array to set order.
	 * @param int|null $limit Limit of query.
	 * @param int|null $page Page limit to get.
	 *
	 * @return array|null
	 * @throws \ReflectionException When error.
	 */
	public function findAll( array $where = null, array $order = null, int $limit = null, int $page = null ): ?array {
		global $wpdb;
		$class = $this->entity_name;
		$table = Annotations::get_table_annotations( $class );
		if ( ! empty( $table ) ) {
			$name = $table->get_name();

			$sql = "SELECT * FROM {$wpdb->$name}";

			$where_s = '';
			if ( $where && is_array( $where ) ) {
				$where_s = ' WHERE ';
				$i       = 0;
				foreach ( $where as $key => $value ) {
					if ( array_key_exists( 'operator', $value ) ) {
						if ( $value['operator'] != 'LIKE' ) {
							$where_s .= $wpdb->prepare( $key . ' ' . $value['operator'] . ' ' . $value['type'], $value['value'] );
						} else {
							$where_s .= $wpdb->prepare( $key . ' LIKE ' . $value['type'], '%' . $value['value'] . '%' );
						}
					} else {
						$where_s .= $wpdb->prepare( $key . ' = ' . $value['type'], $value['value'] );
					}
					if ( $i != count( $where ) - 1 ) {
						$where_s .= ' AND ';
					}
					$i ++;
				}
			}

			$order_s = '';
			if ( $order && is_array( $order ) ) {
				$order_s = ' ORDER BY ';
				$i       = 0;
				foreach ( $order as $key => $type ) {
					$order_s .= $key . ' ' . $type;
					if ( $i != count( $order ) - 1 ) {
						$order_s .= ', ';
					}
					$i ++;
				}
			}

			$limit_s = '';
			if ( $limit != null && is_int( $limit ) ) {
				if ( $page != null && is_int( $page ) ) {
					$offset  = ( ( $page - 1 ) * $limit );
					$limit_s = ' LIMIT ' . $offset . ', ' . $limit;
				} else {
					$limit_s = ' LIMIT ' . $limit;
				}
			}

			$sql = $sql . $where_s . $order_s . $limit_s;

			$res = $wpdb->get_results( $sql );


			if ( ! empty( $res ) ) {
				$ret = array();
				foreach ( $res as $result ) {
					$data = get_object_vars( $result );
					$ob   = new $class();
					$ob->from_array( $data );
					$ob->set_state( 'existing' );
					array_push( $ret, $ob );
				}

				return $ret;
			}
		}

		return null;
	}

	/**
	 * Count entities matching query.
	 *
	 * @param array|null $where Array with where options.
	 *
	 * @return int
	 *
	 * @throws \ReflectionException When error.
	 */
	public function count( array $where = null ): int {
		global $wpdb;
		$class = $this->entity_name;
		$table = Annotations::get_table_annotations( $class );
		if ( ! empty( $table ) ) {
			$name = $table->get_name();

			$sql = "SELECT * FROM {$wpdb->$name}";

			$where_s = '';
			if ( $where && is_array( $where ) ) {
				$where_s = ' WHERE ';
				$i       = 0;
				foreach ( $where as $key => $value ) {
					if ( array_key_exists( 'operator', $value ) ) {
						if ( $value['operator'] != 'LIKE' ) {
							$where_s .= $wpdb->prepare( $key . ' ' . $value['operator'] . ' ' . $value['type'], $value['value'] );
						} else {
							$where_s .= $wpdb->prepare( $key . ' LIKE ' . $value['type'], '%' . $value['value'] . '%' );
						}
					} else {
						$where_s .= $wpdb->prepare( $key . ' = ' . $value['type'], $value['value'] );
					}
					if ( $i != count( $where ) - 1 ) {
						$where_s .= ' AND ';
					}
					$i ++;
				}
			}

			$sql = $sql . $where_s;
			$res = $wpdb->get_results( $sql );


			if ( ! empty( $res ) ) {
				return count( $res );
			}
		}

		return 0;
	}

	/**
	 * Search for entity with id.
	 *
	 * @param int $id Id of entity.
	 *
	 * @return null|object
	 *
	 * @throws \ReflectionException When error.
	 */
	public function find_one( int $id ): ?object {
		global $wpdb;
		$class = $this->entity_name;
		$table = Annotations::get_table_annotations( $class );
		if ( ! empty( $table ) ) {
			$name = $table->get_name();
			$sql  = $wpdb->prepare( "SELECT * FROM {$wpdb->$name} WHERE id = %d LIMIT 1", $id );
			$res  = $wpdb->get_results( $sql );

			if ( ! empty( $res ) ) {
				$data = get_object_vars( $res[0] );
				$ret  = new $class();
				$ret->from_array( $data );
				$ret->set_state( 'existing' );

				return $ret;
			}
		}
		return null;
	}

	/**
	 * Search for entity by existing column name.
	 *
	 * @param string $column Column to search by.
	 * @param mixed $value Value to match.
	 *
	 * @return object|null
	 *
	 * @throws \ReflectionException When error.
	 */
	public function find_one_by( string $column, mixed $value ): ?object {
		global $wpdb;

		$class = $this->entity_name;
		$table = Annotations::get_table_annotations( $class );
		if ( ! empty( $table ) ) {

			$name = $table->get_name();

			if ( array_key_exists( $column, $table->get_columns() ) ) {
				$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->$name} WHERE {$column} = {$table->get_columns()[$column]->get_format()} LIMIT 1", $value );
				$res = $wpdb->get_results( $sql );

				if ( ! empty( $res ) ) {
					$data = get_object_vars( $res[0] );
					$ret  = new $class();
					$ret->from_array( $data );
					$ret->set_state( 'existing' );

					return $ret;
				}
			}
		}

		return null;
	}

	/**
	 * Clears the table
	 *
	 * @return true|int
	 * @throws \ReflectionException
	 */
    public function clear_table(): true|int {
        global $wpdb;
        $class = $this->entity_name;
        $table = Annotations::get_table_annotations( $class );
        if ( ! empty( $table ) ) {
            $name = $table->get_name();
            $sql = "TRUNCATE {$wpdb->$name}";
            $res = $wpdb->query($sql);
            if($res) return true;
        }
        return 0;
    }

}