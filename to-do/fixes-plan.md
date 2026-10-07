# wp-core – compatibility & fix plan

Review date: 2026-09-30
Reviewed version: 1.2.1
Checked against: WordPress 7.0 / 7.1, PHP 8.4 / 8.5, current WooCommerce (HPOS)

References:
- [WordPress 7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)
- [WordPress 7.1 Field Guide](https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/)

Priorities:
- **P1** – broken or insecure now, fix first
- **P2** – compatibility with current WP/PHP, fix in the next release
- **P3** – improvements / modern APIs, optional

---

## Phase 1 – Critical (P1)

### 1.1 PHP requirement does not match code
- **File:** `src/Core/Database/EntityManager.php:449`
- **Problem:** `clear_table(): true|int` uses the standalone `true` type, which exists only from PHP 8.2. `composer.json` allows `>=8.1`, so the file fails to compile on PHP 8.1.
- **Fix:** Pick one:
  - change the return type to `bool|int`, or
  - raise `"php"` in `composer.json` to `>=8.2`.
- [ ] Done

### 1.2 Reflected XSS in admin layout
- **File:** `views/layout.phtml`
- **Problem:** `$_GET['success']` and `$_GET['error']` are echoed without escaping. `$this->title` is also echoed without escaping.
- **Fix:** Wrap the values in `esc_html( wp_unslash( ... ) )` and the title in `esc_html()`. Also consider a whitelist of message keys instead of free-text messages in the URL.
- [ ] Done

### 1.3 Admin page save: no nonce / capability check (CSRF)
- **File:** `src/Core/Admin/Page.php` (`do_save`, `is_post`)
- **Problem:** A save is triggered only by the presence of `$_POST['save_{slug}']`. There is no nonce and no `current_user_can()` check.
- **Fix:**
  - Output `wp_nonce_field( 'save_' . $this->_menu_slug )` in the layout or form (the layout could provide a helper).
  - In `is_post()`/`do_save()`, check `check_admin_referer( 'save_' . $this->_menu_slug )` and `current_user_can( $this->_capability )`.
  - Hook on `admin_init` instead of `init`.
- [ ] Done

### 1.4 `wp_redirect()` without `exit` and unencoded error message
- **File:** `src/Core/Admin/Page.php:270-282`
- **Problem:** Execution continues after the redirect. `$e->getMessage()` is appended to the URL raw.
- **Fix:**
  - Use `wp_safe_redirect( add_query_arg( 'error', rawurlencode( $e->getMessage() ), admin_url( $this->_redirect_url ) ) ); exit;`, and the same for success.
  - Deduplicate the two identical try/catch blocks into one private method.
- [ ] Done

### 1.5 BulkAction broken with WooCommerce HPOS and for non-order screens
- **File:** `src/Core/Admin/BulkAction.php:64`
- **Problem:**
  - It is hardcoded to `post_type == 'shop_order'` (the legacy posts-based orders screen). The HPOS orders screen (`admin.php?page=wc-orders`, the default since WC 8.2) uses the screen id `woocommerce_page_wc-orders` and passes IDs in `id[]`.
  - The `$screen` property is ignored for the handler, so the action never fires on `edit-post` or any other screen.
- **Fix:**
  - Replace the `admin_init` + `$_GET` handling with the core filter:
    ```php
    add_filter( 'handle_bulk_actions-' . $this->screen, [ $this, 'handle' ], 10, 3 );
    // handle( string $redirect_url, string $action, array $ids ): string
    ```
  - Leave nonce verification to core.
  - Document that for WC HPOS orders `$screen = 'woocommerce_page_wc-orders'`, and for legacy orders `'edit-shop_order'`.
  - This is a breaking change to `do_action( array $data )`. Keep the signature but pass `$ids`, and let it return the redirect URL.
- [ ] Done

---

## Phase 2 – WordPress / PHP compatibility (P2)

### 2.1 PHP 8.4+ implicit nullable deprecations
- **File:** `src/Core/Database/EntityManager.php:249` (`findAll`), `:333` (`count`)
- **Problem:** `array $where = null` style parameters emit deprecation notices on PHP 8.4+. This was confirmed with `php -l` on PHP 8.5.
- **Fix:** Use `?array $where = null, ?array $order = null, ?int $limit = null, ?int $page = null`.
- [ ] Done

### 2.2 Always-iframed block editor (WP 7.0 / 7.1)
- **Files:** `src/Core/Admin/Panel.php` (`init_header`), `src/Core/Admin/MetaBox.php`, `src/Core/Gutenberg.php`
- **Problem:**
  - WP 7.0 iframes the editor when all blocks use `apiVersion: 3`.
  - WP 7.1 iframes it always, including when legacy meta boxes are registered.
  - Assets enqueued on `admin_enqueue_scripts` no longer reach the editor canvas.
- **Fix:**
  - Document in the README that theme `block.json` files must use `"apiVersion": 3`.
  - Optionally, `Gutenberg::register_block()` could `_doing_it_wrong()` or log when apiVersion < 3.
  - Add support for editor-canvas assets through the `enqueue_block_assets` hook (for example an `assets.config.php` section `editor`).
  - Test all project meta box views in 7.1. Meta boxes render outside the iframe, so any JS that touched editor content will break.
- [ ] Done

### 2.3 Legacy admin scripts loaded globally
- **File:** `src/Core/Admin/Panel.php:146-156`
- **Problem:**
  - `thickbox` and `media-upload` are the pre-3.5 media modal, and `wp_enqueue_media()` already covers the modern one.
  - jQuery UI was updated to 1.14.2 in WP 7.1.
  - Everything is loaded on every admin screen.
- **Fix:**
  - Remove `thickbox` / `media-upload` (after checking projects for `tb_show` / `send_to_editor` usage).
  - Load color picker, sortable and progressbar only when the screen needs them, for example via a hook argument or a per-page/metabox asset list.
  - Retest sortable/progressbar with jQuery UI 1.14.
- [ ] Done

### 2.4 Translations loaded too early (WP 6.7+)
- **Files:** `src/Core/Theme.php:134-175` (`init_configuration`), `:201-206` (`register_nav_menu` in constructor)
- **Problem:** The config files are `include`d while `functions.php` runs. If a project's `posts.config.php` or `menu.config.php` uses `__()`, WP 6.7+ triggers `_load_textdomain_just_in_time was called incorrectly`.
- **Fix:**
  - Move `register_nav_menu` into an `after_setup_theme` callback.
  - Either load the config files lazily (on `after_setup_theme` / `init`), or document that labels in config files must not call `__()` and translate them at registration time instead. `init_sidebars` already does this.
- [ ] Done

### 2.5 `html5` theme support for `script`/`style` removed (WP 7.0)
- **File:** `src/Core/Theme.php:85-96`
- **Problem:** Projects passing `'html5' => [ 'script', 'style' ]` through `supports` get no effect, and possibly a notice.
- **Fix:** Strip `script`/`style` from the `html5` args before calling `add_theme_support`, or document it.
- [ ] Done

---

## Phase 3 – Bugs (not version related)

### 3.1 Page tabs never resolve
- **File:** `src/Core/Admin/Page.php` (`register_children`)
- **Problem:** Children are instantiated but never stored in `$_childrenObjects`, so `find_tab()` always returns `null`.
- **Fix:** Use `$this->_childrenObjects[] = new $className( ... );`.
- [ ] Done

### 3.2 TermMeta uses `self::` instead of `static::`
- **File:** `src/Core/Admin/TermMeta.php` (`display_add`, `display_edit`, `do_save`)
- **Problem:** `self::$META_FIELD_NAME` always reads the base class's value (`''`), so every subclass shares the nonce `_nonce` / `save_`.
- **Fix:**
  - Replace `self::$META_FIELD_NAME` with `static::$META_FIELD_NAME`.
  - Add a `current_user_can( 'edit_term', $term_id )` check in `do_save()`.
- [ ] Done

### 3.3 RestController ignores `$version`
- **File:** `src/Core/RestController.php:70`
- **Fix:** Use `$this->namespace . '/' . $this->version`.
- [ ] Done

### 3.4 Asset `register` option ignored
- **File:** `src/Core/Theme.php:680-708` (`enqueue_script_or_style`)
- **Problem:** `$function` is computed but `wp_enqueue_style` / `wp_enqueue_script` are always called.
- **Fix:**
  - Call `$function( ... )`.
  - Pass `[]` instead of `null` for empty dependencies.
  - Allow `strategy` / `in_footer` to be overridden from config instead of forcing `defer`.
- [ ] Done

### 3.5 Debug output in Panel
- **File:** `src/Core/Admin/Panel.php:67`
- **Problem:** `var_dump( $e->getCode() )` prints into the admin page.
- **Fix:** Replace it with `error_log()` / `wp_trigger_error()` (WP 6.4+), or rethrow when `WP_DEBUG` is on.
- [ ] Done

### 3.6 MetaBox save robustness
- **File:** `src/Core/Admin/MetaBox.php` (`do_save`)
- **Problem:** `$_POST['post_type']` is read without `isset`, and there is no autosave or revision guard.
- **Fix:**
  - Use `$post_type = get_post_type( $post_id )` instead of `$_POST`.
  - Return early on `wp_is_post_autosave()` / `wp_is_post_revision()`.
  - Consider hooking `save_post_{$post_type}` per screen.
- [ ] Done

### 3.7 Capabilities written on every request
- **Files:** `src/Core/Theme.php:528-533`, `src/Core/PostType.php:43-48`
- **Problem:** `add_cap()` runs on every `init`.
- **Fix:** Run it once per version (store a version in an option, like taxonomies do with `nt_tax_*_version`).
- [ ] Done

### 3.8 Column default `'0'` ignored
- **File:** `src/Core/Database/EntityManager.php` (`__callStatic`)
- **Problem:** `! empty( $column->get_default() )` skips the default value `'0'`. The primary key line is also missing a space: `{type}unsigned`.
- **Fix:** Use `null !== $column->get_default()` and `" unsigned NOT NULL ..."`.
- [ ] Done

---

## Phase 4 – Improvements (P3, optional)

### 4.1 SQL hardening in EntityManager
- **Problem:** `$key` (column names) and `ORDER BY` fields/directions are concatenated into the SQL without escaping. LIKE values skip `esc_like()`.
- **Fix:**
  - Whitelist column names against `$table->get_columns()`.
  - Use `%i` for identifiers (WP 6.2+).
  - Restrict order direction to `ASC|DESC`.
  - Use `'%' . $wpdb->esc_like( $value ) . '%'`.
  - Make `count()` use `SELECT COUNT(*)` instead of fetching all rows.

### 4.2 Faster block registration
- **Change:** Support `wp_register_block_types_from_metadata_collection()` (WP 6.8) with a generated `blocks-manifest.php`, to avoid reading `block.json` from disk for every block on each request.

### 4.3 PHP-only blocks (WP 7.0)
- **Change:** Optionally allow `Gutenberg` subclasses to register blocks without `block.json`, using `supports.autoRegister` plus a render callback, for simple server-rendered blocks.

### 4.4 Gutenberg block path and child themes
- **File:** `src/Core/Gutenberg.php:62`
- **Problem:** It uses `get_template_directory()` only.
- **Fix:** Check `get_stylesheet_directory()` first, the same way `init_styles_and_scripts` does.

### 4.5 Extra user-enumeration hardening
- **File:** `src/Core/Theme.php` (`init_security`)
- **Changes:**
  - Remove the users sitemap provider: `wp_sitemaps_add_provider` filter, or `wp_sitemaps_users_query_args`.
  - Optionally redirect `?author=N` for anonymous visitors.
  - Also unset `/wp/v2/users/me` for completeness.

### 4.6 Script modules versioning
- **Problem:** `remove_version_scripts` does not cover script modules (`wp_enqueue_script_module`, WP 6.5+). Add a matching filter if projects use modules or the Interactivity API.

### 4.7 Annotations → PHP attributes
- **Change:** `Database\Annotations` parses docblocks by regex. `Gutenberg`, `MetaBox` and `Page` already use PHP 8 attributes (`Netivo\Attributes\*`). Migrating `@Table` / `@Column` to attributes would remove the custom parser and be faster and safer. It could keep docblock parsing as a fallback for backward compatibility.

### 4.8 Tooling
- **Changes:**
  - Add `phpcs` with `WordPress-Coding-Standards` and `PHPCompatibilityWP` (testVersion `8.1-`) to `require-dev`.
  - Add a CI job that runs `php -l` on PHP 8.1–8.5.
  - Add `wordpress-stubs` + PHPStan for static checks.

---

## Suggested release split

| Release | Items |
|---|---|
| 1.2.2 (patch) | 1.1, 1.2, 1.3, 1.4, 2.1, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.8 |
| 1.3.0 (minor) | 1.5 (BulkAction API change), 2.2, 2.3, 2.4, 2.5, 3.7 |
| later | Phase 4 |
