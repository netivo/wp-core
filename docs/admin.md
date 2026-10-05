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

## Pages

A page adds a menu item and a settings screen. Extend `Netivo\Core\Admin\Page`:

```php
namespace Netivo\Theme\Admin;

use Netivo\Core\Admin\Page as CorePage;

class Settings extends CorePage {
    protected string $_page_title = 'Theme settings';
    protected string $_menu_text  = 'Theme';
    protected string $_menu_slug  = 'theme-settings';
    protected string $_icon       = 'dashicons-admin-generic';
    protected ?int   $_position   = 61;

    public function do_action(): void {
        $this->view->options = get_option( 'theme_options', [] );
    }

    public function save(): void {
        update_option( 'theme_options', $_POST['options'] ?? [] );
    }
}
```

- `$_type` (default `main`) can be `subpage` (with `$_parent`) or `tab` (a tab of a parent page, with `$_parent`).
- `do_action()` runs before rendering. Set view variables on `$this->view`.
- `save()` runs on POST when the form contains `save_<menu slug>`. Throw an `\Exception` to show an error message; the message is passed back in the URL.
- The view is `<views>/admin/pages/<class name lowercase>.phtml`. Use `#[View('name')]` from `Netivo\Attributes` (if your project provides it) to choose another name.

The page view can use `$this->options` (variables set with `$this->view`) and a form that posts with a `save_<menu slug>` button. The shared layout (`views/layout.phtml` in wp-core) prints the title and the success/error notices.

Sub-pages and tabs are declared in config as children:

```php
'pages' => [
    [
        'class'    => \Netivo\Theme\Admin\Settings::class,
        'children' => [
            [ 'class' => \Netivo\Theme\Admin\SettingsGeneral::class ], // $_type = 'tab', $_parent = 'theme-settings'
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

    public function do_action( array $data ): mixed {
        // $data is an array of selected post IDs
        return null;
    }
}
```

Set `$screen` to the list screen (default `edit-post`).

**Gotcha:** `action()` only runs when the list is `shop_order` (WooCommerce orders), regardless of `$screen`. To use the action elsewhere, change the check in your subclass or in wp-core.

## Gutenberg in the editor

`modules.admin.gutenberg` takes block classes for the editor. See [frontend.md](frontend.md#gutenberg-blocks).
