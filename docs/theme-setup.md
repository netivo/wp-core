# Theme setup

## The theme class

Create one class in your theme namespace that extends `Netivo\Core\Theme`. It must implement `init()`, which runs after wp-core has set up its subsystems.

```php
namespace Netivo\Theme;

use Netivo\Core\Theme;

class Main extends Theme {
    protected function init(): void {
        // add your own hooks, filters or helpers here
    }
}
```

Start it from `functions.php` with `Main::get_instance()`. The instance is created once per class.

Optional static properties set before `get_instance()`:

```php
\Netivo\Theme\Main::$admin_panel = \Netivo\Theme\Admin\Panel::class;     // admin side, see admin.md
\Netivo\Theme\Main::$woocommerce_panel = \Netivo\Theme\Woocommerce::class; // when WooCommerce is active
```

Helpers you can use from the theme:

- `get_configuration()`: the merged configuration array.
- `get_view_path()`: the directory of view files.

A common pattern is a global function for access from templates:

```php
if ( ! function_exists( 'Main' ) ) {
    function Main() {
        return \Netivo\Theme\Main::get_instance();
    }
}
```

## The modules map

`config/modules.config.php` lists the classes wp-core should create. Each key takes a list of fully qualified class names:

```php
<?php
return [
    'modules' => [
        'views_path' => get_template_directory() . '/src/views', // optional
        'customizer' => [ \Netivo\Theme\Customizer\Logo::class ],
        'database'   => [ \Netivo\Theme\Model\Product::class ],
        'endpoint'   => [ \Netivo\Theme\Endpoint\Download::class ],
        'rest'       => [ \Netivo\Theme\Rest\Products::class ],
        'cli'        => [ \Netivo\Theme\Cli\ImportProducts::class ],
        'gutenberg'  => [ \Netivo\Theme\Blocks\Hero::class ],
        'widget'     => [ \Netivo\Theme\Widgets\Contact::class ],
        'admin'      => [
            'pages'     => [ [ 'class' => \Netivo\Theme\Admin\Settings::class, 'children' => [] ] ],
            'metabox'   => [ \Netivo\Theme\Admin\Metabox\Gallery::class ],
            'term-meta' => [ \Netivo\Theme\Admin\TermMeta\Color::class ],
            'gutenberg' => [ \Netivo\Theme\Admin\Blocks\Hero::class ],
            'bulk'      => [ \Netivo\Theme\Admin\Bulk\Export::class ],
        ],
    ],
];
```

| Key | Class must extend | Usage page |
|---|---|---|
| `customizer` | none; created with `new` and must register its own settings | [frontend.md](frontend.md#customizer) |
| `database` | `Database\Entity` | [database.md](database.md) |
| `endpoint` | `Endpoint` | [frontend.md](frontend.md#endpoints) |
| `rest` | `RestController` | [frontend.md](frontend.md#rest-routes) |
| `cli` | `CliCommand` (WP-CLI only) | [cli.md](cli.md) |
| `gutenberg` | `Gutenberg` | [frontend.md](frontend.md#gutenberg-blocks) |
| `widget` | `WP_Widget` | [frontend.md](frontend.md#widgets) |
| `admin.pages` | `Admin\Page` | [admin.md](admin.md#pages) |
| `admin.metabox` | `Admin\MetaBox` | [admin.md](admin.md#metaboxes) |
| `admin.term-meta` | `Admin\TermMeta` | [admin.md](admin.md#term-fields) |
| `admin.gutenberg` | `Gutenberg` | [frontend.md](frontend.md#gutenberg-blocks) |
| `admin.bulk` | `Admin\BulkAction` | [admin.md](admin.md#bulk-actions) |

Entries that are not valid class names, or that do not exist, are skipped silently. Check the class name first if a module does not appear to load.
