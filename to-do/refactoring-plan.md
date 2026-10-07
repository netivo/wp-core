# wp-core: scan results and refactoring plan

Scope: every file under `src/Core/` and `views/layout.phtml`. Originally written against version 1.2.1 (`php >=8.1`, no lint tooling); re-checked against the current working tree, now version 1.3 (`php >=8.4`, `phpcs.xml.dist` added). Checked for bugs, security issues, deprecations and compatibility, and structural problems. Line numbers below reflect the current tree. Items marked **verify** need a run against a real WordPress and PHP install before changing code.

Merged in with a second, independently-written review (`to-do/fixes-plan.md`, dated 2026-09-30, also against 1.2.1) that specifically checked compatibility against WordPress 7.0/7.1 and current WooCommerce HPOS. New findings from that review are marked **(from fixes-plan)** below; items both reviews already agreed on were left as-is.

No automated tests or static analysis (PHPStan) exist yet. A PHPCS config was added since the plan was first written (`phpcs.xml.dist`, `composer lint`/`lint:fix`), but it does not currently produce usable output — see **1.5**.

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
| B6 | `Admin/Page.php` ~267-292 | `wp_redirect()` is followed by no `exit`, so code keeps running after the redirect. The error message is put into the URL unencoded. `wp_safe_redirect()` is the right call for an admin redirect. |
| B7 | `Admin/MetaBox.php` ~239 | `$_POST['post_type']` is read without `isset()`. A missing key raises a warning. |
| B8 | `Admin/Panel.php` ~74 | `var_dump( $e->getCode() )` on a caught exception prints debug output into the admin page. The exception is then swallowed, so the remaining setup silently stops. |
| B9 | `Theme.php` `init_custom_posts_and_taxonomies()` ~528-533, and `PostType.php` ~43-48 | `get_role('administrator')->add_cap()` runs on every request (a DB write each time) in **both** places, not just `Theme.php`. If the role is missing, `get_role()` returns null and the call fatals. |
| B10 | `Admin/BulkAction.php` ~70 | `action()` returns unless `post_type` is `shop_order`, so the action never runs on normal post lists despite `$screen`. **(enriched, from fixes-plan):** this also means it's broken for WooCommerce HPOS, which is the default order-storage mode since WC 8.2. The HPOS orders screen is `woocommerce_page_wc-orders` (not `edit-shop_order`) and submits IDs via `id[]`, not the legacy posts-list bulk format. The fix isn't just correcting the `post_type` check — it should move to the core `handle_bulk_actions-{$screen}` filter so both legacy and HPOS screens, and non-order post-type screens, work without screen-specific hardcoding. |
| B11 | `RestController.php` `build_route()` | Hardcodes `'/v1'`; `$version` is unused. |
| B12 | `Theme.php` `init_sidebars()` ~483 | `__( $sidebar['name'], 'netivo' )` with a runtime string. Strings passed to `__()` cannot be extracted for translation. |
| B13 | `Theme.php` `init_front_site()` / constructor | `add_theme_support()` and `register_nav_menu()` run in the constructor, which can happen before the theme is fully loaded. WordPress expects theme setup on `after_setup_theme`. **verify** that nothing breaks when theme code runs earlier than that. **(from fixes-plan):** concretely, config files are `include`d while `functions.php` runs (`init_configuration()`), and if a project's `posts.config.php` or `menu.config.php` calls `__()` inline, WordPress 6.7+ raises a `_load_textdomain_just_in_time was called incorrectly` notice, because the text domain isn't loaded yet at that point. `init_sidebars()` already avoids this by translating at registration time instead of config-load time — the other config consumers don't. |
| B14 | `Admin/Page.php` `register_children()` | Child page objects are instantiated but never stored into `$_childrenObjects`, so `find_tab()` always returns `null` and tabs never resolve. **(from fixes-plan)** |
| B15 | `Admin/TermMeta.php` `display_add()`, `display_edit()`, `do_save()` | Uses `self::$META_FIELD_NAME` instead of `static::$META_FIELD_NAME`, so late static binding never kicks in and every subclass reads the base class's value (`''`) — all term-meta subclasses end up sharing the same nonce field name. `do_save()` also has no `current_user_can( 'edit_term', $term_id )` check. **(from fixes-plan)** |
| B16 | `Database/EntityManager.php` `__callStatic()` (table creation) | `! empty( $column->get_default() )` treats a default of `'0'` as "no default," so columns intentionally defaulting to `0` get no `DEFAULT` clause in the generated DDL. Use `null !== $column->get_default()`. The primary-key column definition is also missing a space (`"{type}unsigned"` concatenates directly, producing invalid SQL like `intunsigned`). **(from fixes-plan)** |
| B17 | `Database/EntityManager.php` ~449 (`clear_table()`) | Declared as `clear_table(): true\|int`, a standalone-`true` return type that only exists from PHP 8.2. **Resolved as of this check**: `composer.json` now requires `>=8.4`, so this no longer breaks — flagged here only so it isn't rediscovered as a false "PHP 8.1 incompatibility." No action needed. **(from fixes-plan)** |

### 1.2 Security

| ID | Where | Problem |
|---|---|---|
| S1 | `views/layout.phtml` lines 19-27 | `$this->title` is echoed unescaped. `$_GET['success']` and `$_GET['error']` are echoed unescaped into the page (reflected XSS, reachable by anyone who can make an admin click a link). |
| S2 | `Admin/Page.php` ~205-206, 268 | `$_GET['tab']` is used without `sanitize_key()`. |
| S3 | `Admin/BulkAction.php` ~79 | `$_REQUEST['post']` is passed to `do_action()` without casting to integers. |
| S4 | `Admin/MetaBox.php` ~236 | The nonce is read from `$_POST` without `sanitize_text_field()`. `TermMeta` does sanitize it, so the two classes are inconsistent. |
| S5 | `EntityManager.php` `findAll()`, `count()`, `find_one_by()`, `createTable()` | Order keys, operators and column names are concatenated into SQL unescaped. `createTable()` puts `DEFAULT '{$default}'` straight into the DDL. Safe only while these come from annotations and code. Document it, and validate the values. |
| S6 | `Admin/Page.php` ~278, 288 | The exception message goes into the URL unencoded. Use `rawurlencode()`. |
| S7 | `Admin/Page.php` (`do_save()` / `is_post()`) | A save is triggered purely by the presence of `$_POST['save_{slug}']` — there is no nonce field and no `current_user_can()` check, so any admin page extending `Page` is open to CSRF. Add `wp_nonce_field()` to the form/layout, verify it with `check_admin_referer()` in `is_post()`/`do_save()`, and check `current_user_can( $this->_capability )` before saving. **(from fixes-plan)** |

### 1.3 Deprecations and compatibility

| ID | Where | Problem |
|---|---|---|
| D1 | `EntityManager.php` 249, 333 | Implicitly nullable parameters (`array $where = null`). Deprecated since PHP 8.4. Use `?array`, `?int`. |
| D2 | `EntityManager.php` 106 | `$wpdb->$tn = ...` creates a dynamic property on `wpdb`. Dynamic property creation is deprecated since PHP 8.2. The same pattern is used throughout the query code (`$wpdb->$name`). Use a helper that returns `$wpdb->prefix . $name`. **verify** against WordPress's `wpdb` on PHP 8.2+. |
| D3 | `Admin/MetaBox.php` ~185-200 | `icl_get_languages()` (old WPML API) is only reached as a fallback after Polylang is checked, so the real gap is narrower than it first looked: add the `wpml_active_languages` filter as the WPML path and keep `icl_get_languages()` only as a last resort for old WPML installs. |
| D4 | `Theme.php` `enqueue_script_or_style()` vs `phpcs.xml.dist` | `strategy => defer` needs WordPress 6.3+, but `phpcs.xml.dist` sets `minimum_supported_wp_version` to `6.0` — the lint config and the code's actual requirement already contradict each other. Fix by raising the config value (and stating the WordPress minimum in `composer.json`/README) rather than just "nothing states it." |
| ~~D5~~ | `composer.json` | **Done.** `"php"` requirement is now `>=8.4`, already past this item's own recommendation (8.2/8.3). No action needed. |
| D6 | `Admin/Panel.php` ~74 | Debug output (see B8). |
| D7 | `Admin/Panel.php` (`init_header`), `Admin/MetaBox.php`, `Gutenberg.php` | **(from fixes-plan)** WordPress 7.0 iframes the block editor whenever every registered block uses `apiVersion: 3`; WP 7.1 iframes it unconditionally, including when legacy meta boxes are registered. Assets enqueued via `admin_enqueue_scripts` no longer reach the editor canvas once iframed. Needs: documenting that theme `block.json` files must declare `"apiVersion": 3`, a way to enqueue editor-canvas assets via `enqueue_block_assets`, and testing existing meta box views under 7.1 (meta boxes render outside the iframe now, so any JS touching editor content breaks). |
| D8 | `Admin/Panel.php` ~146-156 | **(from fixes-plan)** `thickbox`/`media-upload` (pre-3.5 media modal, superseded by `wp_enqueue_media()`) and the jQuery UI color picker/sortable/progressbar are enqueued globally on every admin screen, not just where needed. jQuery UI was bumped to 1.14.2 in WP 7.1, so sortable/progressbar behavior should be re-verified. Load these per-screen (e.g. from a page/metabox asset list) instead of unconditionally, and drop thickbox/media-upload after checking consuming themes for `tb_show()`/`send_to_editor()` usage. |
| D9 | `Theme.php` ~85-96 (`supports`, see B4) | **(from fixes-plan)** WordPress 7.0 removes the `html5` theme-support args for `script`/`style` — a project passing `'html5' => [ 'script', 'style' ]` through `supports` gets no effect (and possibly a notice) on 7.0+, independent of the B4 parsing bug. Strip `script`/`style` from `html5` args before calling `add_theme_support()`, or document that they're no longer supported. |

### 1.4 Structure and duplication

| ID | Where | Problem |
|---|---|---|
| R1 | `Theme.php`, `Admin/Panel.php` | The same `if !empty / class_exists / new` block appears about 12 times across the `init_*` and loader methods. |
| R2 | `Theme.php` `init_configuration()` | Seven near-identical `if file_exists / include` blocks. |
| R3 | `Theme.php` `init_styles_and_scripts()` | CSS and JS loops duplicate the same file-resolution and condition logic. |
| R4 | `Theme.php` `init_custom_posts_and_taxonomies()` | About 100 lines (lines 524-620). Mixes post type registration, built-in label overrides, taxonomy registration and term seeding. |
| R5 | `Page`, `MetaBox`, `TermMeta`, `Gutenberg` | Each reads the `Netivo\Attributes\View` / `Block` attribute and derives a file name from the class name, with copy-pasted code. |
| R6 | `Admin/Page.php` `do_save()` | The same try/catch redirect block appears twice. |
| R7 | `EntityManager.php` `findAll()` / `count()` | The WHERE builder is copy-pasted. `find_one()`, `find_one_by()`, `findAll()` and `count()` each repeat table lookup and `$wpdb` access. |
| R8 | `Annotations` (core and database) | Static caches are unbounded and keyed by string concatenation. Acceptable for a request-scoped cache, but worth a note. |
| R9 | Constructor side effects (`Endpoint`, `RestController`, `Gutenberg`, `MetaBox`, `TermMeta`, `BulkAction`, `Page`) | Hooks are registered in constructors, so instantiation is the activation. It works, but it is hard to test and hard to see what is loaded. |
| R10 | `Admin/Page.php` | Protected properties are prefixed `_` (`$_page_title`, etc.), which breaks PSR naming. Renaming is a breaking change for child themes. |
| R11 | `Theme.php` `init_woocommerce()` docblock | Refers to `\Netivo\Core\Woocommerce`, which is not in this package (see docblock review). |
| R12 | `Gutenberg.php` ~62 | **(from fixes-plan)** Block paths are resolved from `get_template_directory()` only, same gap as B3 but specific to this file — `init_styles_and_scripts()` already checks `get_stylesheet_directory()` first. Fix alongside B3 so there's one shared child-theme-first resolution helper rather than two separate ones. |
| R13 | `Database\Annotations` vs `Netivo\Attributes\*` | **(from fixes-plan, idea only)** `@Table`/`@Column` are parsed from docblocks by regex, while `Gutenberg`, `MetaBox` and `Page` already use PHP 8 attributes. Migrating entities to attributes would remove the custom docblock parser; docblock parsing could stay as a fallback for existing entity classes. No urgency — flagged as a direction, not a defect. |

### 1.5 Tooling and process

- No automated tests. No PHPStan.
- `phpcs.xml.dist` (WordPressVIPMinimum + PHPCompatibility) and the `composer lint` / `lint:fix` scripts now exist. **Fixed**: `phpcompatibility/php-compatibility` was upgraded from `^9.3` (resolved `9.3.5`, 2019, incompatible with running phpcs under modern PHP) to `10.0.0-alpha2`. `composer lint` now runs to completion: `127 sniff violations across 20 sniff codes in 13 of the 18 scanned files`, 14 of which `phpcbf` can autofix mechanically. See **1.6** for what it found that wasn't already in this plan, and the confirmations of what was.
- `CHANGELOG.md` already has an entry for `CliCommand` / `modules.cli` (v1.3: "Added option to create WP CLI commands"). No action needed.
- `.gitignore` still lists `.vs_code`, which is probably meant to be `.vscode`.
- `composer.lock` is now committed. No action needed.

### 1.6 New findings from the first real `composer lint` run

Now that lint actually runs (see 1.5), it independently confirmed B1, B6, B7/S2/S3/S4, B8, D1, S5 at their documented locations/lines — no surprises there. It also surfaced the following, which weren't in this plan before:

| ID | Where | Problem |
|---|---|---|
| L1 | 34 occurrences across `BulkAction.php`, `MetaBox.php`, `Page.php`, `TermMeta.php`, `Annotations.php`, `EntityManager.php` (×14), `Endpoint.php`, `Gutenberg.php`, `Theme.php` | Loose comparison operators (`==`/`!=`) used throughout instead of `===`/`!==` (`Universal.Operators.StrictComparisons.*`). `phpcbf` can autofix all of these mechanically, but any comparison that currently relies on type coercion needs eyes-on review first — this sniff is also what's actually behind B2's `!= null` bug. |
| L2 | `MetaBox.php` (×3), `Theme.php` (×2), `Annotations.php`, one more — 7 total | `in_array()` called without the strict third argument (`WordPress.PHP.StrictInArray.MissingTrueStrict`). Same risk class as L1 — a loose `in_array()` can match `0` against a non-empty string. |
| L3 | `Admin/Page.php` lines 205-206, 268-269 (warning), 300 (error, inside `is_post()`) | phpcs independently flags the exact CSRF gap already tracked as **S7** — no new fix needed, this just confirms S7's scope precisely (`is_post()`, plus both tab-reading sites). |
| L4 | `Admin/View.php:69` (`content()`), `Admin/View.php:60` (`render()`) | Two new findings in the same class: `extract( $this->_variables )` exposes view variables into local scope (`WordPress.PHP.DontExtract`) — makes it hard to trace where a template variable came from, and `views/layout.phtml` is loaded via `require` rather than `file_get_contents()` (`WordPressVIPMinimum.Files.IncludingNonPHPFile`). The second rule is meant for static includes (e.g. an SVG) and doesn't really fit a `.phtml` template that must execute PHP — this is a judgment call, not a blind fix (see Decisions). |
| L5 | `Database\Annotations.php` lines 120, 128, 154, 183; `PostType.php:49` | `\Exception` messages built by concatenating raw annotation/class-name text (`WordPress.Security.EscapeOutput.ExceptionNotEscaped`) — flagged because an uncaught exception message can end up rendered in a browser. Low real risk since the inputs are docblocks/class names the author controls, but cheap to fix with `esc_html()` at the point the message might reach output. |
| L6 | `Database\EntityManager.php`: `find_one()`, `findAll()`, `count()`, `find_one_by()` (7 occurrences) | None of `EntityManager`'s direct `$wpdb` queries are cached (`WordPress.DB.DirectDatabaseQuery.NoCaching`) — a different gap from S5 (which is about escaping, not caching). There is currently no object-cache layer anywhere in the Database layer. |
| L7 | `EntityManager.php:80`, `Annotations.php:99` | Two small dead-code leftovers missed on manual read: unused `$key` and unused `$nextToken` (`VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable`). Same sniff that also caught B1's unused `$function` — worth cleaning up together. |
| L8 | `Theme.php:386` (`remove_admin_bar()`) | `show_admin_bar( false )` is flagged outright by `WordPressVIPMinimum.UserExperience.AdminBarRemoval.RemovalDetected`. This is a deliberate, opt-in library feature (a theme calls it explicitly), not a bug — needs a decision (see Decisions), not an automatic fix. |

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
10. Fix B9: cache the role lookup and guard against null; only add capabilities when they are missing (both call sites: `Theme.php` and `PostType.php`).
11. Make `composer lint` actually run: upgrade `phpcompatibility/php-compatibility` past `9.3.5` (or pin phpcs/PHP for the lint step) so it stops aborting with the `trim()` deprecation error on every file. Then run it for real and fold any findings it surfaces into this plan — everything above was found by hand, not by phpcs. **Done** — upgraded to `10.0.0-alpha2`; lint now runs to completion and independently confirmed B1.
12. Fix S7: add `wp_nonce_field()` / `check_admin_referer()` and a `current_user_can()` check to `Admin/Page.php` save handling (CSRF).
13. Fix B14: store child page objects in `register_children()` so `find_tab()` resolves.
14. Fix B15: `self::$META_FIELD_NAME` → `static::$META_FIELD_NAME` in `TermMeta`, plus a `current_user_can( 'edit_term', ... )` check in `do_save()`.
15. Fix B16: `null !== $column->get_default()` in `EntityManager::__callStatic()`, and the missing space in the primary-key column DDL.
16. Fix L1: run `phpcbf` to convert the 34 loose `==`/`!=` comparisons to `===`/`!==`, then manually review every file it touched for a comparison that actually relied on type coercion (B2 is one confirmed case; there may be others, e.g. in `Annotations.php`'s token loop).
17. Fix L2: add the strict `true` argument to the 7 flagged `in_array()` calls.
18. Fix L7: remove the unused `$key` (`EntityManager.php:80`) and `$nextToken` (`Annotations.php:99`) variables, alongside B1's unused `$function` cleanup.
19. Fix L5: wrap the annotation/class-name text in the `\Exception` messages at `Annotations.php:120,128,154,183` and `PostType.php:49` with `esc_html()` at the point they might reach output.

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
14. Add CHANGELOG entries for everything shipped in this phase.
15. Fix D4: raise `minimum_supported_wp_version` in `phpcs.xml.dist` to at least `6.3` (matching the `strategy => defer` requirement) and state the WordPress minimum in the README/composer.json. (D5, the PHP minimum, is already done — `composer.json` requires `>=8.4`.)
16. Fix D9: drop/strip `script`/`style` from `html5` theme-support args (removed in WP 7.0), alongside the B4 parsing fix, in the same pass over `init_front_site()`'s `supports` handling.
17. Fix D8: stop loading `thickbox`/`media-upload` and the jQuery UI color picker/sortable/progressbar on every admin screen; load per-screen instead. Re-verify sortable/progressbar against jQuery UI 1.14 (bundled since WP 7.1).
18. Fix R12: resolve Gutenberg block paths child-theme-first (`get_stylesheet_directory()` before `get_template_directory()`), using the same helper as B3's config/view resolution.

### Phase 3: quality tooling and design (1.4.0, then 2.0.0)

1. Add PHPUnit with Brain Monkey (or the WordPress test suite) for `Annotations`, `EntityManager` SQL generation and the config merge. Add a `composer test` script.
2. Add PHPStan (level 6 to start). Add an `analyse` script. (PHPCS/WordPress Coding Standards are already in place as of 1.3 — see Phase 1 item 11 to make them actually runnable.)
3. Add a CI workflow that runs lint, analyse and test against the minimum and latest supported PHP and WordPress versions.
4. Document and validate the SQL inputs (S5): whitelist column names against the annotated columns, restrict operators to a fixed list.
5. Investigate moving hook registration out of constructors (R9) into an explicit `register()` call. This is a breaking change for child themes, so it belongs in 2.0.0 and needs a migration note.
6. Rename the `_`-prefixed `Page` properties (R10) in the same 2.0.0 release, with a compatibility note.
7. Fix the `.gitignore` entry (`.vs_code` to `.vscode`).
8. Fix D7 (block-editor iframing, WP 7.0/7.1): document the `apiVersion: 3` requirement, add an `enqueue_block_assets` path for editor-canvas assets, and audit/retest consuming themes' meta box views under the always-iframed editor.

### Further ideas (not scheduled)

Smaller improvements from `fixes-plan.md` that aren't defects and don't need a phase yet; revisit opportunistically:

- Support `wp_register_block_types_from_metadata_collection()` (WP 6.8) with a generated `blocks-manifest.php`, instead of reading `block.json` from disk per block on every request.
- Allow `Gutenberg` subclasses to register simple server-rendered blocks without a `block.json` (WP 7.0 PHP-only blocks).
- Extra user-enumeration hardening in `init_security()`: remove the users sitemap provider, optionally redirect `?author=N` for anonymous visitors, unset `/wp/v2/users/me`.
- `remove_version_scripts` doesn't cover script modules (`wp_enqueue_script_module`, WP 6.5+); add a matching filter if consuming themes use modules or the Interactivity API.
- R13 (Annotations → PHP attributes for `@Table`/`@Column`) — direction only, see the table above.

---

## 3. Decisions needed

1. ~~**Minimum PHP version.**~~ **Resolved.** `composer.json` already requires `>=8.4`.
2. **Minimum WordPress version.** Needed for the `strategy` script option (6.3+), and now actively contradicted by `phpcs.xml.dist` (`minimum_supported_wp_version=6.0`). Recommendation: 6.4 or later, applied consistently to the lint config, composer.json and the README.
3. **Breaking changes.** Are the child-theme and constructor changes (Phase 2 item 1, Phase 3 item 5) acceptable in a minor release, or should they wait for 2.0.0?
4. ~~**WooCommerce helper.**~~ **Resolved — act on it.** `Netivo\Core\Woocommerce` does not exist anywhere in this repo (confirmed by search); it's referenced only in `Theme.php` docblock comments (`init_woocommerce()` and others). Remove the dangling references rather than leaving it as an open question.
5. **Scope of tests.** Start with the pure logic (annotations, SQL building, config merge), or also cover hooks with a WordPress test suite?
6. **L4 — `View::render()`/`content()`.** Keep `require`-based template loading and `extract()` as-is with a documented `phpcs:ignore` (they're the mechanism the whole view system depends on), or redesign view rendering to avoid both? Rewriting is a bigger change than the sniff warrants on its own.
7. **L6 — caching in `EntityManager`.** Add an object-cache layer (`wp_cache_get()`/`wp_cache_set()`, invalidated on `save()`/`delete()`), or explicitly document that `EntityManager` is uncached by design and projects needing caching should wrap it themselves?
8. **L8 — `Theme::remove_admin_bar()`.** Keep this opt-in capability (consuming themes call it explicitly) with a documented `phpcs:ignore` for the VIP rule, or drop it to stay clean under WordPressVIPMinimum?
