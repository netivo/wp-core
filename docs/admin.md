# Admin

Admin code runs only when `is_admin()` is true. It is set up by a panel class.

## Panel

Create a panel by extending `Netivo\Core\Admin\Panel` and register it in the theme class:

```php
namespace Netivo\Theme\Admin;

use Netivo\Core\Admin\Panel as CorePanel;

class Panel extends CorePanel {
    protected function set_vars(): void {
        $this->include_path = get_template_directory() . '/src';
        $this->uri          = get_template_directory_uri() . '/src';
    }

    protected function init(): void {
        // extra admin setup
    }

    protected function custom_header( $page ): void {
        wp_enqueue_style( 'theme-admin', $this->uri . '/assets/css/admin.css' );
    }
}
```

Then in `Main`: `\Netivo\Theme\Main::$admin_panel = \Netivo\Theme\Admin\Panel::class;`.

The panel reads `modules.admin` and creates each listed class. The sections below cover each key.

`custom_header()` runs on `admin_enqueue_scripts`, which never reaches inside the block
editor's iframed canvas (WordPress 7.1+ always iframes it; 7.0 does once every block uses
`apiVersion: 3` — see [frontend.md](frontend.md#gutenberg-blocks)). For CSS/JS that must
run *inside* the canvas itself, override `custom_block_editor_assets()` instead, which
runs on `enqueue_block_assets` (fires on both admin and front end — check `is_admin()`
first if front-end output isn't wanted):

```php
protected function custom_block_editor_assets(): void {
    if ( is_admin() ) {
        wp_enqueue_style( 'theme-editor-canvas', $this->uri . '/assets/css/editor-canvas.css' );
    }
}
```

## Pages

A page adds a menu item and a settings screen. Extend `Netivo\Core\Admin\Page`:

```php
namespace Netivo\Theme\Admin;

use Netivo\Core\Admin\Page as CorePage;

class Settings extends CorePage {
    protected string $page_title = 'Theme settings';
    protected string $menu_text  = 'Theme';
    protected string $menu_slug  = 'theme-settings';
    protected string $icon       = 'dashicons-admin-generic';
    protected ?int   $position   = 61;

    public function do_action(): void {
        $this->view->options = get_option( 'theme_options', [] );
    }

    public function save(): void {
        update_option( 'theme_options', $_POST['options'] ?? [] );
    }
}
```

- `$type` (default `main`) can be `subpage` (with `$parent`) or `tab` (a tab of a parent page, with `$parent`).
- `do_action()` runs before rendering. Set view variables on `$this->view`.
- `save()` runs on POST when the form contains `save_<menu slug>`. Throw an `\Exception` to show an error message; the message is passed back in the URL.
- The view is `<views>/admin/pages/<class name lowercase>.phtml`. Use `#[View('name')]` from `Netivo\Attributes` (if your project provides it) to choose another name.
- The page's save form must call `<?php $this->nonce_field(); ?>` inside its `<form>` — `save()` is only reached after the nonce and the page's `$capability` are checked.
- Older code using the `_`-prefixed property names (`$_page_title`, `$_menu_slug`, etc.) still works but is deprecated — see [migration-1.4.0.md](migration-1.4.0.md).

The page view can use `$this->options` (variables set with `$this->view`) and a form that posts with a `save_<menu slug>` button. The shared layout (`views/layout.phtml` in wp-core) prints the title and the success/error notices.

Sub-pages and tabs are declared in config as children:

```php
'pages' => [
    [
        'class'    => \Netivo\Theme\Admin\Settings::class,
        'children' => [
            [ 'class' => \Netivo\Theme\Admin\SettingsGeneral::class ], // $type = 'tab', $parent = 'theme-settings'
        ],
    ],
],
```

Tabs open as `admin.php?page=<parent>&tab=<tab slug>`.

## Metaboxes

A metabox adds fields to the post edit screen. Extend `Netivo\Core\Admin\MetaBox`:

```php
namespace Netivo\Theme\Admin\Metabox;

use Netivo\Core\Admin\MetaBox as CoreMetaBox;

class Gallery extends CoreMetaBox {
    public static array $META_FIELDS = [
        'images' => '_gallery_images',
    ];

    protected string $id       = 'gallery';
    protected string $title    = 'Gallery';
    protected array|string $screen = 'page';
    protected string $context  = 'side';
    protected string $priority = 'default';

    public function save( int $post_id ): mixed {
        update_post_meta( $post_id, static::get_field_name( 'images' ), $_POST['gallery'] ?? [] );
        return $post_id;
    }
}
```

- `$screen` is a post type or array of them. `$template` limits the box to a page template (value is the template file, or `home-page`).
- `$META_FIELDS` maps keys to meta names. Use `static::get_field_name('key')` in `save()` so names are defined in one place.
- The view is `<views>/admin/metabox/<name>.phtml`. Inside it, `$post` is the current `WP_Post`. wp-core prints a nonce field; the form fields must be named by your code.
- `save()` is called only after the nonce and the `edit_post` capability are checked.

## Term fields

Term fields add inputs to a taxonomy's add and edit screens. Extend `Netivo\Core\Admin\TermMeta`:

```php
namespace Netivo\Theme\Admin\TermMeta;

use Netivo\Core\Admin\TermMeta as CoreTermMeta;

class Color extends CoreTermMeta {
    public static string $META_FIELD_NAME = 'term_color';
    protected array|string $taxonomy      = 'product_cat';

    public function save( int $term_id, ?int $tt_id = null ): int {
        if ( current_user_can( 'manage_categories' ) ) {
            update_term_meta( $term_id, 'color', sanitize_hex_color( $_POST['term_color'] ?? '' ) );
        }
        return $term_id;
    }
}
```

- Views: `<views>/admin/term-meta/<name>.php` for the add screen and `<name>-edit.php` for the edit screen. Note the `.php` extension here, unlike pages and metaboxes.
- `save()` is called on create and edit. Check capabilities yourself.

## Bulk actions

A bulk action adds an entry to a list's bulk dropdown. Extend `Netivo\Core\Admin\BulkAction`:

```php
namespace Netivo\Theme\Admin\Bulk;

use Netivo\Core\Admin\BulkAction as CoreBulkAction;

class Export extends CoreBulkAction {
    protected string $id   = 'export_selected';
    protected string $name = 'Export selected';

    public function do_action( array $ids, string $redirect_url ): string {
        // $ids is an array of selected post/term/order IDs (already cast to int)
        return add_query_arg( 'exported', count( $ids ), $redirect_url );
    }
}
```

Set `$screen` to the list-table screen id: `edit-post` (default), `edit-shop_order` (legacy
WooCommerce orders), `woocommerce_page_wc-orders` (WooCommerce HPOS orders, the default
since WooCommerce 8.2), or any other list-table screen. Registration and nonce
verification go through WordPress core's `handle_bulk_actions-{$screen}` filter, so the
same subclass shape works on every screen without screen-specific handling.

## Gutenberg in the editor

`modules.admin.gutenberg` takes block classes for the editor. See [frontend.md](frontend.md#gutenberg-blocks).
