<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 8.07.2024
 * Time: 17:04
 *
 */

namespace Netivo\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Entity class
 *
 * Parent class for defining database entities.
 */
class Entity {
	/**
	 * State of entity. One of: new, existing, changed
	 * new - entity newly created, not existing in DB
	 * existing - entity existing in DB, not changed
	 * changed - entity existing in DB and changed
	 *
	 * @var string
	 */
	public string $state = 'new';

	/**
	 * Return called class.
	 *
	 * @return string
	 */
	public function get_self(): string {
		return get_called_class();
	}

	/**
	 * Get table data information.
	 *
	 * @return Annotations\Table|null
	 *
	 * @throws \ReflectionException When error.
	 */
	public function get_table_data(): ?Annotations\Table {
		return Annotations::get_table_annotations($this->get_self());
	}

	/**
	 * Gets entity state.
	 *
	 * @return string
	 */
	public function get_state(): string {
		return $this->state;
	}

	/**
	 * Set the entity state.
	 *
	 * @param string $new_state New state.
	 *
	 * @return $this
	 */
	public function set_state( string $new_state): object {
		$this->state = $new_state;
		return $this;
	}

	/**
	 * Set entity data from array.
	 *
	 * @param array $data Data to set.
	 *
	 * @return $this
	 *
	 * @throws \ReflectionException When error.
	 */
	public function from_array( array $data): object {
		foreach($data as $key => $value){
			$class = $this->get_self();
			$table = Annotations::get_table_annotations($class);
			if(!empty($table)) {
				if ( array_key_exists( $key, $table->get_columns() ) ) {
					$this->$key = $value;
				}
			}
		}

		return $this;
	}

}