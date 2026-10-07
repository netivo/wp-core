# Changelog

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