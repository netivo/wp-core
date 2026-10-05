# wp-core: scan results and refactoring plan

Scope: every file under `src/Core/` and `views/layout.phtml` (version 1.2.1). Checked for bugs, security issues, deprecations and compatibility, and structural problems. Line numbers refer to the state of the working tree when this was written.

No tests, static analysis or coding-standard tooling exist yet, so every finding below comes from reading the code and targeted searches. Items marked **verify** need a run against a real WordPress and PHP install before changing code.

---

## 1. Findings

### 1.1 Bugs

| ID | Where | Problem |
|---|---|---|
| B1 | `Theme.php` ~699-713 | `enqueue_script_or_style()` computes `$function` (`wp_register_*` when `register` is set) and never uses it. Assets marked `register => true` are enqueued anyway. |
| B2 | `EntityManager.php` ~171 (`insert`) | `$entity->$method() != null` skips any value that loosely equals null: `0`, `0.0`, `''`, `false`. Those columns are left out of the INSERT and get the database default. `update()` uses `!== null`, so the two disagree. |
| B3 | `Theme.php` `init_configuration()` | Config files and views are read only from `get_template_directory()` (parent theme). Assets are resolved child-first. A child theme cannot override config, views or the `modules` list, but can override assets. |
| B4 | `Theme.php` ~85-96 | `supports` key/value form is broken. The loop skips non-string entries, so `'custom-logo' => [...]` is ignored and `add_theme_support()` never receives its arguments. |
| B5 | `EntityManager.php` `count()` ~333-373 | Fetches every row (`SELECT *` + `get_results`) and counts in PHP. Use `SELECT COUNT(*)`. |
| B6 | `Admin/Page.php` ~276-288 | `wp_redirect()` is followed by no `exit`, so code keeps running after the redirect. The error message is put into the URL unencoded. `wp_safe_redirect()` is the right call for an admin redirect. |
| B7 | `Admin/MetaBox.php` ~239 | `$_POST['post_type']` is read without `isset()`. A missing key raises a warning. |
| B8 | `Admin/Panel.php` ~74 | `var_dump( $e->getCode() )` on a caught exception prints debug output into the admin page. The exception is then swallowed, so the remaining setup silently stops. |
| B9 | `Theme.php` `init_custom_posts_and_taxonomies()` | `get_role('administrator')->add_cap()` runs on every request (a DB write each time). If the role is missing, `get_role()` returns null and the call fatals. |
| B10 | `Admin/BulkAction.php` ~70 | `action()` returns unless `post_type` is `shop_order`, so the action never runs on normal post lists despite `$screen`. |
| B11 | `RestController.php` `build_route()` | Hardcodes `'/v1'`; `$version` is unused. |
| B12 | `Theme.php` `init_sidebars()` ~483 | `__( $sidebar['name'], 'netivo' )` with a runtime string. Strings passed to `__()` cannot be extracted for translation. |
| B13 | `Theme.php` `init_front_site()` / constructor | `add_theme_support()` and `register_nav_menu()` run in the constructor, which can happen before the theme is fully loaded. WordPress expects theme setup on `after_setup_theme`. **verify** that nothing breaks when theme code runs earlier than that. |

### 1.2 Security

| ID | Where | Problem |
|---|---|---|
| S1 | `views/layout.phtml` lines 19-27 | `$this->title` is echoed unescaped. `$_GET['success']` and `$_GET['error']` are echoed unescaped into the page (reflected XSS, reachable by anyone who can make an admin click a link). |
| S2 | `Admin/Page.php` ~205-206, 268-269 | `$_GET['tab']` is used without `sanitize_key()`. |
| S3 | `Admin/BulkAction.php` ~79 | `$_REQUEST['post']` is passed to `do_action()` without casting to integers. |
| S4 | `Admin/MetaBox.php` ~236 | The nonce is read from `$_POST` without `sanitize_text_field()`. `TermMeta` does sanitize it, so the two classes are inconsistent. |
| S5 | `EntityManager.php` `findAll()`, `count()`, `find_one_by()`, `createTable()` | Order keys, operators and column names are concatenated into SQL unescaped. `createTable()` puts `DEFAULT '{$default}'` straight into the DDL. Safe only while these come from annotations and code. Document it, and validate the values. |
| S6 | `Admin/Page.php` ~278, 288 | The exception message goes into the URL unencoded. Use `rawurlencode()`. |

### 1.3 Deprecations and compatibility

| ID | Where | Problem |
|---|---|---|
| D1 | `EntityManager.php` 249, 333 | Implicitly nullable parameters (`array $where = null`). Deprecated since PHP 8.4. Use `?array`, `?int`. |
| D2 | `EntityManager.php` 106 | `$wpdb->$tn = ...` creates a dynamic property on `wpdb`. Dynamic property creation is deprecated since PHP 8.2. The same pattern is used throughout the query code (`$wpdb->$name`). Use a helper that returns `$wpdb->prefix . $name`. **verify** against WordPress's `wpdb` on PHP 8.2+. |
| D3 | `Admin/MetaBox.php` ~190-191 | `icl_get_languages()` is the old WPML API. Newer WPML exposes `apply_filters('wpml_active_languages', ...)`. |
| D4 | `Theme.php` `enqueue_script_or_style()` | `strategy => defer` needs WordPress 6.3+. Nothing states the WordPress minimum. |
| D5 | `composer.json` `"php": ">=8.1"` | PHP 8.1 reached end of life at the end of 2025. Raise the minimum (8.2 or 8.3) and state the WordPress minimum. |
| D6 | `Admin/Panel.php` ~74 | Debug output (see B8). |

### 1.4 Structure and duplication

| ID | Where | Problem |
|---|---|---|
| R1 | `Theme.php`, `Admin/Panel.php` | The same `if !empty / class_exists / new` block appears about 12 times across the `init_*` and loader methods. |
| R2 | `Theme.php` `init_configuration()` | Seven near-identical `if file_exists / include` blocks. |
| R3 | `Theme.php` `init_styles_and_scripts()` | CSS and JS loops duplicate the same file-resolution and condition logic. |
| R4 | `Theme.php` `init_custom_posts_and_taxonomies()` | About 150 lines. Mixes post type registration, built-in label overrides, taxonomy registration and term seeding. |
| R5 | `Page`, `MetaBox`, `TermMeta`, `Gutenberg` | Each reads the `Netivo\Attributes\View` / `Block` attribute and derives a file name from the class name, with copy-pasted code. |
| R6 | `Admin/Page.php` `do_save()` | The same try/catch redirect block appears twice. |
| R7 | `EntityManager.php` `findAll()` / `count()` | The WHERE builder is copy-pasted. `find_one()`, `find_one_by()`, `findAll()` and `count()` each repeat table lookup and `$wpdb` access. |
| R8 | `Annotations` (core and database) | Static caches are unbounded and keyed by string concatenation. Acceptable for a request-scoped cache, but worth a note. |
| R9 | Constructor side effects (`Endpoint`, `RestController`, `Gutenberg`, `MetaBox`, `TermMeta`, `BulkAction`, `Page`) | Hooks are registered in constructors, so instantiation is the activation. It works, but it is hard to test and hard to see what is loaded. |
| R10 | `Admin/Page.php` | Protected properties are prefixed `_` (`$_page_title`, etc.), which breaks PSR naming. Renaming is a breaking change for child themes. |
| R11 | `Theme.php` `init_woocommerce()` docblock | Refers to `\Netivo\Core\Woocommerce`, which is not in this package (see docblock review). |

### 1.5 Tooling and process

- No automated tests.
- No PHPStan, PHPCS or WordPress Coding Standards config.
- `CHANGELOG.md` has no entry for `CliCommand` / `modules.cli` or the `init_cli()` hook.
- `.gitignore` lists `.vs_code`, which is probably meant to be `.vscode`.
- `composer.lock` is not committed. That is fine for a library, but CI should pin the versions it tests with.

---

## 2. Plan

### Phase 1: bugs and security (patch release 1.2.2)

Small, low-risk changes. Each one can be a separate commit.

1. Fix B1: honour `register` for styles and scripts (`wp_register_*`).
2. Fix B2: use `!== null` in `insert()`, matching `update()`.
3. Fix S1: escape the title and notices in `layout.phtml` (`esc_html`). Only accept `success` / `error` values that are safe to show, or pass them as a fixed key.
4. Fix B6, S6: add `exit` after redirects, use `wp_safe_redirect()` and `rawurlencode()` for messages.
5. Fix B7, S2, S3, S4: `isset()` checks, `sanitize_key()` for tab slugs, `array_map('absint', ...)` for bulk IDs, `sanitize_text_field()` for nonces.
6. Fix B8: remove `var_dump`. Log with `error_log()` and show an admin notice instead.
7. Fix B5: `count()` uses `SELECT COUNT(*)`.
8. Fix D1: change implicit nullable parameters to `?type`.
9. Fix D2: replace `$wpdb->$tn` with a `table_name()` helper.
10. Fix B9: cache the role lookup and guard against null; only add capabilities when they are missing.

### Phase 2: structure and compatibility (1.3.0)

Minor release. Behaviour changes are kept to what is needed.

1. Child-theme support (B3): load `config/` and views child-first, parent second, matching assets.
2. Fix B4: parse `supports` entries correctly (string, or key => args), or document the form that is supported.
3. Move theme setup (B13) to `after_setup_theme`: `add_theme_support()`, `register_nav_menu()`.
4. Replace the repeated loaders (R1) with one `load_modules( array $classes, callable $factory )` helper in `Theme` and `Panel`.
5. Collapse the seven config-file blocks (R2) into a loop over a file-name list.
6. Split `init_custom_posts_and_taxonomies()` (R4) into `register_post_types()`, `apply_builtin_labels()`, `register_taxonomies()` and `seed_terms()`.
7. Extract the asset resolution shared by CSS and JS (R3).
8. Add a trait for attribute-based view and block names (R5), used by `Page`, `MetaBox`, `TermMeta` and `Gutenberg`.
9. Factor the redirect logic in `Page::do_save()` (R6).
10. Factor the WHERE builder in `EntityManager` (R7).
11. Update WPML handling (D3). Use the `wpml_active_languages` filter first, `icl_get_languages()` only as a fallback.
12. Fix B10 and B12 (the bulk action screen check, and translating the sidebar name).
13. Fix B11: use `$this->version` in `build_route()`, with `v1` as the default.
14. Add CHANGELOG entries for everything in 1.2.2 and 1.3.0, including `CliCommand` and `modules.cli`.
15. Raise the minimum PHP version and state the WordPress minimum in `composer.json` (D4, D5).

### Phase 3: quality tooling and design (1.4.0, then 2.0.0)

1. Add PHPUnit with Brain Monkey (or the WordPress test suite) for `Annotations`, `EntityManager` SQL generation and the config merge.
2. Add PHPStan (level 6 to start) and PHPCS with WordPress Coding Standards. Add `composer` scripts `test`, `lint`, `analyse`.
3. Add a CI workflow that runs them against the minimum and latest supported PHP and WordPress versions.
4. Document and validate the SQL inputs (S5): whitelist column names against the annotated columns, restrict operators to a fixed list.
5. Investigate moving hook registration out of constructors (R9) into an explicit `register()` call. This is a breaking change for child themes, so it belongs in 2.0.0 and needs a migration note.
6. Rename the `_`-prefixed `Page` properties (R10) in the same 2.0.0 release, with a compatibility note.
7. Fix the `.gitignore` entry (`.vs_code` to `.vscode`).

---

## 3. Decisions needed

1. **Minimum PHP version.** Recommendation: 8.2 for 1.3.0, 8.3 for 2.0.0. The current `>=8.1` is past end of life.
2. **Minimum WordPress version.** Needed for the `strategy` script option (6.3+). Recommendation: 6.4 or later.
3. **Breaking changes.** Are the child-theme and constructor changes (Phase 2 item 1, Phase 3 item 5) acceptable in a minor release, or should they wait for 2.0.0?
4. **WooCommerce helper.** Does `Netivo\Core\Woocommerce` exist in another package? If not, remove the reference in `init_woocommerce()`.
5. **Scope of tests.** Start with the pure logic (annotations, SQL building, config merge), or also cover hooks with a WordPress test suite?
