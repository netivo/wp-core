# Database

wp-core creates and maintains custom tables from annotated PHP classes, and provides basic queries.

## Define an entity

Extend `Netivo\Core\Database\Entity`. Describe the table and columns with docblock annotations:

```php
namespace Netivo\Theme\Model;

use Netivo\Core\Database\Entity;

/**
 * @Table(name="products", version=1.1)
 */
class Product extends Entity {
    /**
     * @Column(name="id", type="bigint(20)", format="%d", primary=true)
     */
    protected int $id;

    /**
     * @Column(name="title", type="varchar(255)", format="%s", required=true)
     */
    protected string $title;

    /**
     * @Column(name="price", type="decimal(10,2)", format="%f")
     */
    protected float $price;

    public function get_id(): int { return $this->id; }
    public function set_id( int $id ): void { $this->id = $id; }
    public function get_title(): string { return $this->title; }
    public function set_title( string $title ): void { $this->title = $title; $this->set_state( 'changed' ); }
    public function get_price(): float { return $this->price; }
    public function set_price( float $price ): void { $this->price = $price; $this->set_state( 'changed' ); }
}
```

Rules:

- `@Table` needs `name` (without the WordPress prefix). Bump `version` when the table structure changes; wp-core then runs `dbDelta` on the next load.
- Each `@Column` needs `name` and `type`. Optional: `format` (`%s`, `%d`, `%f`), `primary`, `required`, `default`.
- The primary column must be named `id`. Inserts skip it, and updates use it in the `WHERE` clause.
- Every column needs a `get_<column>()` method. Saving calls these methods, so a missing one breaks the save.
- Change the state with `set_state('changed')` when a value changes, so `save()` knows to update.
- Getters run on every save. A typed property that is never set (for example an optional `float $price` on a new entity) makes its getter throw. Give optional properties a default, or set every column before saving.

Register the class in `modules.database`. The table is created on `after_setup_theme`.

## Use it

```php
use Netivo\Theme\Model\Product;
use Netivo\Core\Database\EntityManager;

// Read
$product = EntityManager::get( Product::class )->find_one( 12 );       // Product|null
$byTitle = EntityManager::get( Product::class )->find_one_by( 'title', 'Shirt' );

$list = EntityManager::get( Product::class )->findAll(
    [ 'price' => [ 'type' => '%f', 'value' => 10, 'operator' => '>' ] ], // where
    [ 'title' => 'ASC' ],                                                // order
    20,                                                                  // limit
    1                                                                    // page
);                                                                       // array|null

$total = EntityManager::get( Product::class )->count();                  // int

// Write
$p = new Product();
$p->set_title( 'Shirt' );
$p->set_price( 19.9 );
EntityManager::save( $p );          // inserts; $p now has its id and state "existing"

$p->set_price( 24.9 );              // state becomes "changed"
EntityManager::save( $p );          // updates

EntityManager::delete( $p );        // removes the row
EntityManager::get( Product::class )->clear_table();   // TRUNCATE
```

Notes:

- `where` values are escaped with `prepare()`. Column names and operators are not escaped, so never build them from user input.
- Use `'operator' => 'LIKE'` to match with `%value%`.
- `findAll()` returns `null` (not `[]`) when nothing matches. Check for `null`.
- Entities created from the database have state `existing`.
