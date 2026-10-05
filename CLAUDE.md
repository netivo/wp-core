# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

- Install: `composer install` (PHP >= 8.1; generates `vendor/autoload.php`, PSR-4 `Netivo\Core\` => `src/Core/`)
- There is no build, lint, or test tooling configured (no phpunit/pest config, no phpcs/phpstan config, no CI). Verify changes by loading the code inside a WordPress install.

## What this is

A library, not an application. `netivo/wp-core` is the shared base for Netivo WordPress themes and modules. Nothing runs standalone: every class file starts with `if ( ! defined( 'ABSPATH' ) ) exit;`, so it only executes inside WordPress.

## Architecture

**Bootstrap flow.** A consuming theme extends the abstract `Theme` class (`src/Core/Theme.php`) in its own namespace and calls `Main::get_instance()` from `functions.php`. The constructor (`Theme::__construct`) drives everything:

1. `init_configuration()` reads every `config/*.config.php` file from the active template directory (`images`, `sidebars`, `posts`, `menu`, `assets`, `main`, `modules`) and merges them into one `$this->configuration` array. Nothing else is loaded if the `config/` directory is missing.
2. Subsystems are wired from configuration: security hardening, front-end hooks (menus, sidebars, CPT/taxonomies, image sizes, assets), customizer, database, endpoints, Gutenberg, REST routes, WooCommerce if `WC()` exists, and finally the subclass's own `init()`.
3. In `is_admin()` only, `Theme::$admin_panel` is instantiated.

**Module loading is config-driven.** `modules.config.php` lists class names under keys such as `database`, `endpoint`, `gutenberg`, `rest`, `widget`, `customizer`, and `admin`. Each entry is checked with `class_exists()` and instantiated, so a missing class is silently skipped. Changing what a theme loads means editing its config, not code.

**Custom posts and taxonomies** accept either an array of `register_post_type()` args or a class name extending `PostType`. Taxonomy terms are reseeded when the `version` key changes (stored in the `nt_tax_{name}_version` option).

**Admin layer (`src/Core/Admin/`).** The theme's `Panel` subclass (abstract `Panel`) reads `modules.admin` and loads sub-modules: `pages`, `metabox`, `term-meta`, `gutenberg`, `bulk`. A `Page` subclass defines `do_action()`/`save()` and renders through `View`, which resolves view files under the theme's views path and proxies `$this->foo` to the page. `views/layout.phtml` is the shared wrapper; its success/error notices are printed from `$_GET` params.

**Database layer (`src/Core/Database/`).** Entities are plain PHP classes extending `Entity`, described entirely by docblock annotations: `@Table(name=..., version=...)` on the class and `@Column(name=..., type=..., format=..., primary=..., required=..., default=...)` on properties. `Annotations` (extends the base `Netivo\Core\Annotations` parser) reflects over these docblocks and caches the result. `EntityManager` creates tables on `after_setup_theme` via `dbDelta`, tracking versions in the `_nt_db_version` option, and provides `get/findAll/find_one/find_one_by/count/save/delete/clear_table`. Note that `EntityManager::__callStatic` is how `create_table_*` hooks resolve, so table creation depends on that magic method name pattern.

**Other extension points.** `Endpoint` registers a rewrite endpoint and either loads a template or runs an action on `template_redirect`. `RestController` registers routes on `rest_api_init`. `Gutenberg` registers a block from `<theme>/src/views/gutenberg/<classname>/block.json` (or a `Netivo\Attributes\Block` attribute). `PostType`, `Admin\MetaBox`, `Admin\TermMeta`, and `Admin\BulkAction` are abstract bases that subclasses fill in.

## Gotchas

- `RestController::build_route()` hardcodes the `/v1` segment and ignores `$this->version`.
- Most module classes do their registration in the constructor (`new $class()` from the config loaders), so instantiating a class is what activates it.
- Admin-only behaviour is gated by `is_admin()`; `Panel::__construct` swallows `\Exception` and calls `var_dump`, so failures there are easy to miss.
