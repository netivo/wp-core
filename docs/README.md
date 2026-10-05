# wp-core usage guide

`netivo/wp-core` is a library for Netivo WordPress themes. A theme extends one base class, describes what it needs in configuration files, and wp-core registers everything: menus, sidebars, post types, assets, admin pages, database tables, REST routes, and blocks.

This guide covers how to use the library. It does not document every class; the source docblocks do that.

## Contents

- [Configuration](configuration.md): the `config/*.config.php` files and how they are merged.
- [Theme setup](theme-setup.md): the theme class, `functions.php`, and the `modules` map.
- [Admin](admin.md): admin panel, pages, metaboxes, term fields, bulk actions.
- [Database](database.md): entities, annotations, queries.
- [Frontend](frontend.md): post types and taxonomies, assets, menus, sidebars, image sizes, endpoints, REST routes, Gutenberg blocks, widgets, customizer.
- [WP-CLI commands](cli.md): commands with `--dry-run`, enabled through `modules.cli`.

## Quick start

1. Install the library into the theme: `composer require netivo/wp-core`, then keep `vendor/` in the theme.
2. In `functions.php`:

   ```php
   <?php
   namespace Netivo\Theme;

   require_once __DIR__ . '/vendor/autoload.php';

   class Main extends \Netivo\Core\Theme {
       protected function init(): void {
           // theme-specific setup
       }
   }

   Main::get_instance();
   ```

   `namespace` must be the first statement in the file. Put the class in its own file under `src/` if you prefer.

3. Create `config/` in the theme with the files you need (see [Configuration](configuration.md)).
4. Add the classes you want loaded to `config/modules.config.php` (see [Theme setup](theme-setup.md)).

## Conventions

- Classes are referenced by their fully qualified name in config files, as strings. wp-core checks `class_exists()` before using them, so a missing class is skipped without an error.
- View files live under the theme's views path, which defaults to `src/views` in the theme. Set `modules.views_path` to change it.
- Each module is created with `new`. Its constructor registers its hooks.
