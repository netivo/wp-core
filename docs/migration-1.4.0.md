# Migrating toward 1.4.0

Two changes from the Phase 3 refactoring plan landed in 1.4.0 as backward-compatible
shims: the old behaviour keeps working, with a deprecation notice. Removal isn't
scheduled for any specific version yet — this page is both a migration guide for
consuming themes today and a note on what a later cleanup should finish removing,
whenever that's decided.

## Explicit `register()` instead of constructor side effects (R9)

Every class whose constructor used to wire WordPress hooks directly —
`Admin\Page`, `Admin\MetaBox`, `Admin\TermMeta`, `Admin\BulkAction`, `Gutenberg`,
`RestController`, `Endpoint`, `PostType`, `CliCommand` — now exposes that wiring as an
explicit `register()` method, and accepts an `$auto_register` constructor argument
(default `true`).

**Today:** nothing to change. Instantiating any of these classes still registers its
hooks immediately, exactly as before:

```php
new MyMetaBox( $view_path ); // registers add_meta_boxes/save_post hooks right away, as always
```

**Going forward (optional today, required if a later release removes the default):** construct
the instance without touching any hook, then register explicitly when ready:

```php
$box = new MyMetaBox( $view_path, auto_register: false );
// ... do something with $box before any hook fires ...
$box->register();
```

This matters for anyone who wants to build an object graph (e.g. for testing, or to
inspect/modify an instance before it starts reacting to WordPress) without immediately
subscribing to hooks as a side effect of `new`.

**If this is ever removed:** the cleanup would drop the `$auto_register` parameter and
make `register()` the only way to wire hooks — i.e. flip today's default. Consumers who
already pass `auto_register: false` and call `register()` themselves would be unaffected
by that flip; consumers who still rely on the implicit constructor-time registration
would need to add an explicit `->register()` call.

## Renamed `Admin\Page` properties, dropping the `_` prefix (R10)

`Admin\Page`'s configuration properties have been renamed to drop their non-PSR `_`
prefix:

| Old (deprecated)     | New               |
|----------------------|-------------------|
| `$_name`             | `$name`           |
| `$_type`              | `$type`           |
| `$_page_title`       | `$page_title`     |
| `$_menu_text`        | `$menu_text`      |
| `$_capability`       | `$capability`     |
| `$_menu_slug`        | `$menu_slug`      |
| `$_icon`             | `$icon`           |
| `$_position`         | `$position`       |
| `$_parent`           | `$parent`         |
| `$_children`         | `$children`       |
| `$_childrenObjects`  | `$children_objects` |
| `$_redirect_url`     | `$redirect_url`   |
| `$_views_path`       | `$views_path`     |

**Today:** nothing to change. A subclass that overrides an old, `_`-prefixed property —
the normal way to configure a `Page` subclass —

```php
class MySettingsPage extends Page {
    protected string $_menu_slug = 'my-settings';
    protected string $_capability = 'manage_options';
    // ...
}
```

still works exactly as before. `Page::__construct()` detects any `_`-prefixed property a
subclass overrode away from its original default, copies it into the new, unprefixed
property, and emits an `E_USER_DEPRECATED` notice (visible with `WP_DEBUG` on, silent in
production) naming the property to rename.

**Going forward:** use the unprefixed names in new and updated subclasses:

```php
class MySettingsPage extends Page {
    protected string $menu_slug = 'my-settings';
    protected string $capability = 'manage_options';
    // ...
}
```

**Known limitation of the shim:** the migration only runs once, at construction time,
and only detects a property **declared** with a non-default value (the standard way
every base class in this library is configured). It does **not** bridge a `_`-prefixed
property assigned at runtime inside a method body (e.g. `$this->_menu_slug = $slug;`
inside a custom `do_action()`) — that pattern isn't used anywhere in this codebase's own
classes, but a theme doing it would need to switch to the new property name directly,
since there's no reliable way to intercept that in PHP without turning every property
access into a method call.

**If this is ever removed:** the cleanup would remove the `_`-prefixed properties,
`DEPRECATED_PROPERTIES`, and `migrate_deprecated_properties()` entirely. Consuming
themes should rename every overridden property per the table above at their own pace —
there's no deadline for this yet.
