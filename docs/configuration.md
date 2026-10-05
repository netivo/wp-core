# Configuration

Configuration lives in `<theme>/config/`. wp-core loads each known file if it exists, then merges them into one array. Nothing is loaded if the directory is missing.

| File | Returns (top-level key) | Used for |
|---|---|---|
| `main.config.php` | any keys, e.g. `supports`, `admin_bar` | theme supports and admin bar |
| `images.config.php` | `image` | custom image sizes |
| `posts.config.php` | `posts`, `taxonomies` | custom post types and taxonomies |
| `menu.config.php` | `menu` | navigation menu locations |
| `sidebars.config.php` | `sidebars` | sidebars |
| `assets.config.php` | `assets` | front-end styles and scripts |
| `modules.config.php` | `modules` | classes to load (see [Theme setup](theme-setup.md)) |

Each file returns an array with its top-level key:

```php
<?php
// config/menu.config.php
return [
    'menu' => [
        'primary' => [ 'name' => 'Main menu' ],
        'footer'  => [ 'name' => 'Footer menu' ],
    ],
];
```

Keys are merged with `array_merge`, so a key set in two files is overwritten by the later one in this order: main, images, posts, menu, assets, sidebars, modules.

## Notes

- Use the `.config.php` names exactly. Other files are ignored.
- The `modules.views_path` key changes where view files are read from.
- `supports` in `main.config.php` works with plain names, e.g. `[ 'post-thumbnails', 'title-tag' ]`. The key/value form (`'custom-logo' => [ ... ]`) does not pass its arguments through, so use `add_theme_support()` directly for those.
