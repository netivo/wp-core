# Changelog

## Version 1.3.2

- Config files, views, and Gutenberg block paths now resolve child theme first, falling back to the parent/template theme (`Theme::resolve_path()`/`Theme::resolve_uri()`)
- Fixed `supports` config entries in `'key' => [args]` form being silently skipped (e.g. `'custom-logo' => [...]`); plain string entries still work as before
- `add_theme_support()` and `register_nav_menu()` now run on `after_setup_theme`, as WordPress expects, instead of directly in the theme constructor
- `'html5'` support args no longer pass `script`/`style`, removed in WordPress 7.0
- Menu names are now translated at registration time (`register_nav_menu()`), matching how sidebar names are already translated in `init_sidebars()`
- **Breaking:** `Admin\BulkAction` now registers through WordPress core's `handle_bulk_actions-{$screen}` filter instead of parsing `$_GET`/`$_REQUEST` manually. This fixes bulk actions being silently broken on any screen other than the legacy `edit-shop_order` orders screen, including the WooCommerce HPOS orders screen (`woocommerce_page_wc-orders`, the default since WooCommerce 8.2) and any non-order post-type screen. `do_action()`'s signature changed from `do_action( array $data ): mixed` to `do_action( array $ids, string $redirect_url ): string` — subclasses must be updated.
- `RestController::build_route()` now uses `$this->version` instead of a hardcoded `/v1`
- `Admin\MetaBox::get_page_on_front()` now checks the `wpml_active_languages` filter first for WPML, falling back to the legacy `icl_get_languages()` only if that filter isn't registered
- Raised `phpcs.xml.dist`'s `minimum_supported_wp_version` to 6.3 (matching the `strategy => defer` script-loading option already in use) and documented the WordPress/PHP minimums in the README
- Reduced duplication: a shared `load_modules()` helper in `Theme`/`Admin\Panel` replaces the repeated `class_exists()`/`new` loader blocks; a shared `ResolvesViewName` trait replaces the duplicated reflection/attribute lookup in `Admin\Page`, `Admin\MetaBox`, `Admin\TermMeta` and `Gutenberg`; `Database\EntityManager::findAll()`/`count()` share a `build_where()` helper instead of duplicating the WHERE-clause builder
- Split `Theme::init_custom_posts_and_taxonomies()` into `register_post_types()`, `apply_builtin_post_labels()`, `register_taxonomies()` and `seed_taxonomy_terms()`
- Extracted the CSS/JS asset-resolution logic shared by `Theme::init_styles_and_scripts()` into `enqueue_configured_asset()`

## Version 1.3.1

- Fixed `register` option being ignored when enqueuing styles/scripts, so assets marked for registration only are no longer also enqueued
- Fixed `EntityManager::insert()` dropping columns with a falsy-but-valid value (`0`, `'0'`, `false`, `''`)
- Fixed `EntityManager::count()` fetching all rows instead of using `SELECT COUNT(*)`
- Fixed missing space in generated primary key column DDL, and columns with a default of `0` no longer losing their `DEFAULT` clause
- Fixed admin page tabs never resolving because child pages were not stored (`Admin\Page::register_children()`)
- Fixed `Admin\TermMeta` subclasses sharing the same nonce field name due to `self::` instead of `static::`
- Fixed capabilities being re-added to the administrator role on every request instead of once
- Escaped the admin page title and success/error notices in `views/layout.phtml` (reflected XSS)
- Added a nonce and capability check to `Admin\Page` save handling (CSRF), via a new `Page::nonce_field()` helper theme views must call inside their save form
- Hardened `Admin\Page` redirects: `wp_safe_redirect()` + `exit`, and URL-encoded error messages
- Added missing `isset()`/sanitization checks for POST/GET/REQUEST data in `Admin\Page`, `Admin\MetaBox`, `Admin\BulkAction`
- Added a `current_user_can( 'edit_term', ... )` check to `Admin\TermMeta::do_save()`
- Replaced `var_dump()` debug output in `Admin\Panel` with `wp_trigger_error()`
- Converted implicitly nullable parameters in `EntityManager` to explicit `?type` (PHP 8.4 deprecation)
- Reduced reliance on dynamic `$wpdb` properties in `EntityManager` via a `table_name()` helper
- Converted loose (`==`/`!=`) comparisons to strict ones throughout the codebase where safe, and documented the few left loose intentionally
- Added the strict `true` argument to `in_array()` calls flagged by phpcs
- Escaped annotation-parsing and post-type-registration exception messages
- Removed small dead-code leftovers (`$key`, `$nextToken`, `$function`) flagged by phpcs
- Upgraded `phpcompatibility/php-compatibility` so `composer lint` runs to completion

## Version 1.3

- Added option to create WP CLI commands

## Version 1.2.2

- Added support for AI Agents programming
- Added docs

## Version 1.2.1

- Added security rules: disabled XML-RPC, removed generator from feeds, removed RSD and WLW manifest links, removed X-Pingback header
- Restricted users REST endpoints to users who can edit posts
- Fixed removing versions from styles/scripts with version set in assets config
- Versions are no longer removed from styles/scripts in admin

## Version 1.2

- Added term metabox class to simplify term meta handling
- Modified metabox class to store meta filed names

## Version 1.1.3

- Fixed bug with DB delta when updating table structure

## Version 1.1.2

- Custom post registration also using class objects.

## Version 1.1.1

- Added changelog and Readme

## Version 1.1

- Moved Modules to separate packages

## Version 1.0

- Changed option for loading Gutenberg blocks
- Moved from mu-plugin