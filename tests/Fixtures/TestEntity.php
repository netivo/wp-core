<?php
declare( strict_types=1 );

namespace Netivo\Core\Tests\Fixtures;

use Netivo\Core\Database\Entity;

/**
 * @Table(name="nt_test_items", version=2)
 */
class TestEntity extends Entity {

	/**
	 * @Column(name="id", type="bigint(20)", format="%d", primary=true)
	 */
	protected ?int $id = null;

	/**
	 * @Column(name="title", type="varchar(255)", format="%s", required=true)
	 */
	protected ?string $title = null;

	/**
	 * @Column(name="sort_order", type="int(11)", format="%d", default=0)
	 */
	protected int $sort_order = 0;

	/**
	 * @Column(name="is_active", type="tinyint(1)", format="%d", default=1)
	 */
	protected int $is_active = 1;

	public function get_id(): ?int {
		return $this->id;
	}

	public function set_id( int $id ): static {
		$this->id = $id;

		return $this;
	}

	public function get_title(): ?string {
		return $this->title;
	}

	public function set_title( ?string $title ): static {
		$this->title = $title;

		return $this;
	}

	public function get_sort_order(): int {
		return $this->sort_order;
	}

	public function set_sort_order( int $sort_order ): static {
		$this->sort_order = $sort_order;

		return $this;
	}

	public function get_is_active(): int {
		return $this->is_active;
	}

	public function set_is_active( int $is_active ): static {
		$this->is_active = $is_active;

		return $this;
	}
}
