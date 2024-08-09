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
 * Class Column
 */
class Column {
	/**
	 * Name of column in table;
	 *
	 * @var string
	 */
	public string $name;
	/**
	 * Type of column in table.
	 *
	 * @var string
	 */
	public string $type;
	/**
	 * Format of column. Connected to type. One of %d, %f, %s
	 * Default %s.
	 *
	 * @var string
	 */
	public string $format = '%s';
	/**
	 * Is the column primary key. Type must be bigint(20). Default false.
	 *
	 * @var boolean
	 */
	public bool $primary = false;
	/**
	 * Is the column required. Default false.
	 *
	 * @var boolean
	 */
	public bool $required = false;
	/**
	 * Default value of column. Default null.
	 *
	 * @var null|string
	 */
	public ?string $default = null;

	/**
	 * Get column name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Sets name of column.
	 *
	 * @param string $name Name of column.
	 *
	 * @return Column
	 */
	public function set_name( string $name ): Column {
		$this->name = $name;

		return $this;
	}

	/**
	 * Get column type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Sets type of column.
	 *
	 * @param string $type Type of column.
	 *
	 * @return Column
	 */
	public function set_type( string $type ): Column {
		$this->type = $type;

		return $this;
	}

	/**
	 * Get column format.
	 *
	 * @return string
	 */
	public function get_format(): string {
		return $this->format;
	}

	/**
	 * Sets column format.
	 *
	 * @param string $format Format of column.
	 *
	 * @return Column
	 */
	public function set_format( string $format ): Column {
		$this->format = $format;

		return $this;
	}

	/**
	 * Is the column primary key.
	 *
	 * @return bool
	 */
	public function is_primary(): bool {
		return $this->primary;
	}

	/**
	 * Sets the primary column indicator.
	 *
	 * @param bool $primary Is the column primary key.
	 *
	 * @return Column
	 */
	public function set_primary( bool $primary ): Column {
		$this->primary = $primary;

		return $this;
	}

	/**
	 * Is the column required.
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return $this->required;
	}

	/**
	 * Sets the required column indicator.
	 *
	 * @param bool $required Is the column required.
	 *
	 * @return Column
	 */
	public function set_required( bool $required ): Column {
		$this->required = $required;

		return $this;
	}

	/**
	 * Get default value of column.
	 *
	 * @return string|null
	 */
	public function get_default(): ?string {
		return $this->default;
	}

	/**
	 * Sets default value to column.
	 *
	 * @param string $default Default value.
	 *
	 * @return Column
	 */
	public function set_default( string $default ): Column {
		$this->default = $default;

		return $this;
	}

}