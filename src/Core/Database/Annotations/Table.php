<?php
/**
 * Created by Netivo for Netivo Core Plugin.
 * User: Michal
 * Date: 26.10.2018
 * Time: 08:44
 *
 * @package Netivo\Core\Database\Annotations
 */

namespace Netivo\Core\Database\Annotations;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class Table
 */
class Table {
	/**
	 * Name of table in database without prefix.
	 *
	 * @var string
	 */
	public string $name;
	/**
	 * Version of table.
	 *
	 * @var float
	 */
	public float $version;

	/**
	 * Column list in table.
	 *
	 * @var Column[]
	 */
	public array $columns;

	/**
	 * Gets the table name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Sets the table name.
	 *
	 * @param string $name Name of table.
	 *
	 * @return Table
	 */
	public function set_name( string $name ): Table {
		$this->name = $name;

		return $this;
	}

	/**
	 * Gets version of table.
	 *
	 * @return float
	 */
	public function get_version(): float {
		return $this->version;
	}

	/**
	 * Sets version of table.
	 *
	 * @param float $version Version number.
	 *
	 * @return Table
	 */
	public function set_version( float $version ): Table {
		$this->version = $version;

		return $this;
	}

	/**
	 * Gets columns in table.
	 *
	 * @return Column[]
	 */
	public function get_columns(): array {
		return $this->columns;
	}

	/**
	 * Sets columns in table.
	 *
	 * @param Column[] $columns Columns in table.
	 *
	 * @return Table
	 */
	public function set_columns( array $columns ): Table {
		$this->columns = $columns;

		return $this;
	}

	/**
	 * Adds column to the set.
	 *
	 * @param string $name Name of column.
	 * @param Column $column Column class.
	 *
	 * @return Table
	 */
	public function add_column( string $name, Column $column ): Table {
		$this->columns[ $name ] = $column;

		return $this;
	}

}