<?php
/**
 * Created by Netivo for wp-core
 * User: Michal
 * Date: 26.10.2018
 * Time: 08:43
 *
 * @package Netivo\Core\Database
 */

namespace Netivo\Core\Database;

use Netivo\Core\Database\Annotations\Column;
use Netivo\Core\Database\Annotations\Table;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class Annotations
 */
class Annotations extends \Netivo\Core\Annotations {

	/**
	 * Cache of database tables
	 *
	 * @var array
	 */
	protected static array $table_cache = [];

	/**
	 * Parses the entity to get table structure represented as Table class object.
	 *
	 * @param string $class_name name of class to parse.
	 *
	 * @return Table|null
	 *
	 * @throws \ReflectionException When error.
	 */
	public static function get_table_annotations( string $class_name ): ?Table {
		if ( ! isset( self::$table_cache[ $class_name ] ) ) {
			$class = new \ReflectionClass( $class_name );
			$ca    = self::parse_annotations( $class->getDocComment() );
			if ( ! empty( $ca && array_key_exists( 'Table', $ca ) ) ) {
				$ca = $ca['Table'][0];
				if ( array_key_exists( 'name', $ca ) ) {
					$table = new Table();
					$table->set_name( $ca['name'] );
					if ( array_key_exists( 'version', $ca ) ) {
						$table->set_version( $ca['version'] );
					} else {
						$table->set_version( 1.0 );
					}

					$properties = $class->getProperties();
					foreach ( $properties as $property ) {
						$pa = self::parse_annotations( $property->getDocComment() );
						if ( array_key_exists( 'Column', $pa ) ) {
							$pa = $pa['Column'][0];
							if ( array_key_exists( 'name', $pa ) && array_key_exists( 'type', $pa ) ) {
								$column = new Column();
								$column->set_name( $pa['name'] )->set_type( $pa['type'] );
								if ( array_key_exists( 'format', $pa ) ) {
									$column->set_format( $pa['format'] );
								}
								if ( array_key_exists( 'primary', $pa ) ) {
									$column->set_primary( $pa['primary'] );
								}
								if ( array_key_exists( 'required', $pa ) ) {
									$column->set_required( $pa['required'] );
								}
								if ( array_key_exists( 'default', $pa ) ) {
									$column->set_default( $pa['default'] );
								}

								$table->add_column( $pa['name'], $column );
							}
						}
					}

					if ( ! empty( $table->get_columns() ) ) {
						self::$table_cache[ $class_name ] = $table;
					}

				}
			}

		}

		return ( isset( self::$table_cache[ $class_name ] ) ) ? self::$table_cache[ $class_name ] : null;
	}
}