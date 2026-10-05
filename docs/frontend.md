# Frontend

## Assets

Declare styles and scripts in `config/assets.config.php`. Paths are relative to the theme directory and start with `/`. wp-core looks in the child theme first, then in the parent.

```php
<?php
return [
    'assets' => [
        'css' => [
            [
                'name'         => 'main',
                'file'         => '/assets/css/main.css',
                'version'      => '1.0.3',       // optional; versions are kept in the URL
                'dependencies' => [],
                'media'        => 'all',
                'condition'    => fn() => ! is_admin(), // optional callable
            ],
        ],
        'js' => [
            [
                'name'         => 'app',
                'file'         => '/assets/js/app.js',
                'dependencies' => [ 'jquery' ],
                'register'     => false,         // true to only register, not enqueue
            ],
        ],
    ],
];
```

- Scripts load in the footer with `defer`.
- Query strings like `?ver=` are removed from other assets. Assets with a `version` keep theirs.

## Menus, sidebars, image sizes

```php
// config/menu.config.php
return [ 'menu' => [ 'primary' => [ 'name' => 'Main menu' ] ] ];
```

Show a menu with `wp_nav_menu( [ 'theme_location' => 'primary' ] );`.

```php
// config/sidebars.config.php
return [
    'sidebars' => [
        'sidebar-shop' => [ 'id' => 'sidebar-shop', 'name' => 'Shop sidebar' ],
    ],
];
```

```php
// config/images.config.php
return [
    'image' => [
        'card' => [ 'name' => 'card', 'width' => 400, 'height' => 300, 'crop' => true ],
    ],
];
```

Use the size as `the_post_thumbnail( 'card' )`.

## Post types and taxonomies

Declare them in `config/posts.config.php`:

```php
return [
    'posts' => [
        'book' => [
            'labels'      => [ 'name' => 'Books', 'singular_name' => 'Book' ],
            'public'      => true,
            'supports'    => [ 'title', 'thumbnail' ],
            'has_archive' => true,
        ],
        'post' => [ 'labels' => [ 'name' => 'News' ] ], // built-in types: only labels can change
    ],
    'taxonomies' => [
        'genre' => [
            'post'    => 'book',
            'version' => 2,                         // change to re-seed the terms below
            'options' => [ 'hierarchical' => true, 'label' => 'Genres', 'public' => true ],
            'terms'   => [
                'fiction' => [ 'name' => 'Fiction', 'slug' => 'fiction' ],
            ],
        ],
    ],
];
```

- Each `posts` value is either register arguments, or a class name extending `Netivo\Core\PostType`:

  ```php
  class Book extends \Netivo\Core\PostType {
      public function get_id(): string { return 'book'; }
      public function get_settings(): array {
          return [ 'labels' => [ 'name' => 'Books' ], 'public' => true ];
      }
  }
  ```

  Put `\Netivo\Theme\Post\Book::class` as the value.

- `'post'` and `'page'` cannot be created; only their labels are changed.
- Taxonomy terms in `terms` are created if missing. Terms not in the list are **deleted** when `version` changes, so bump `version` only when you want that.
- A `capabilities` array in a post type's settings is granted to the `administrator` role.

## Endpoints

An endpoint is a URL under the current page, e.g. `/shop/download/123`. Extend `Netivo\Core\Endpoint`:

```php
namespace Netivo\Theme\Endpoint;

use Netivo\Core\Endpoint;

class Download extends Endpoint {
    protected string $name = 'download';   // query var, and URL segment
    protected string $type = 'action';     // or 'template'
    protected int $place   = EP_ROOT;      // where the endpoint is added

    public function doAction( mixed $var ): void {
        // $var is the value after /download/ ; send a file, then exit
    }
}
```

For `type = 'template'`, set `protected string $template = 'page-download.php';` (a file in the theme). `doAction()` is not used then.

Register it in `modules.endpoint`. Flush permalinks once after adding an endpoint (Settings → Permalinks → Save).

## REST routes

Extend `Netivo\Core\RestController`:

```php
namespace Netivo\Theme\Rest;

use Netivo\Core\RestController;

class Products extends RestController {
    protected string $base = 'products';   // route: /wp-json/netivo/v1/products

    public function register_routes(): void {
        $this->build_route( [ $this, 'index' ] );
        $this->build_route( [ $this, 'show' ], 'GET', '__return_true', '(?P<id>\d+)' );
    }

    public function index( \WP_REST_Request $request ) {
        return rest_ensure_response( [] );
    }

    public function show( \WP_REST_Request $request ) {
        return rest_ensure_response( [ 'id' => $request['id'] ] );
    }
}
```

- `build_route( $callback, $method, $permission, $params )`. The default permission is `__return_true` (public). Pass a permission callback for anything that changes data or exposes private data.
- Routes are always under `netivo/v1`, whatever `$version` says.

Register it in `modules.rest`.

## Gutenberg blocks

A block needs a `block.json` in `<theme>/src/views/gutenberg/<class name lowercase>/`. Extend `Netivo\Core\Gutenberg`:

```php
namespace Netivo\Theme\Blocks;

use Netivo\Core\Gutenberg;

class Hero extends Gutenberg {
    protected ?string $callback = 'render';

    public function render( array $attributes, string $content ): string {
        return '<section class="hero">' . esc_html( $attributes['title'] ?? '' ) . '</section>';
    }
}
```

- Set `$callback` to a method for server-rendered output. Leave it `null` for static blocks that render from `block.json`.
- The class file is `Hero.php`, so the directory is `src/views/gutenberg/hero/`.
- Register the class in `modules.gutenberg` for the front end, and in `modules.admin.gutenberg` to load the editor assets (the admin panel passes `include_path` and `uri`).

## Widgets

Widgets extend `WP_Widget` and are registered by listing them in `modules.widget`. Use standard WordPress widget code; wp-core only calls `register_widget()` at `widgets_init`.

## Customizer

List classes in `modules.customizer`. wp-core creates each with `new`, so the constructor should register its settings with `add_action( 'customize_register', … )`.
