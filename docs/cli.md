# WP-CLI commands

`Netivo\Core\CliCommand` is the base class for WP-CLI commands. Each command gets a `--dry-run` flag, and changes made with `perform()` are only reported when it is set.

Commands are registered only when WP-CLI runs. In normal requests nothing is loaded.

## Create a command

```php
namespace Netivo\Theme\Cli;

use Netivo\Core\CliCommand;

class ImportProducts extends CliCommand {
    protected string $name      = 'netivo import products';
    protected string $shortdesc = 'Imports products from the feed.';
    protected string $longdesc  = 'Reads the product feed and creates or updates products.';

    // Extra arguments and flags. --dry-run is added automatically.
    protected array $synopsis = [
        [
            'type'        => 'assoc',
            'name'        => 'limit',
            'description' => 'Maximum number of products to import.',
            'optional'    => true,
        ],
    ];

    protected function execute( array $args, array $assoc_args ): void {
        $limit = (int) ( $assoc_args['limit'] ?? 100 );

        foreach ( $this->fetch_feed( $limit ) as $row ) {
            $this->perform( 'update product ' . $row['sku'], function () use ( $row ) {
                // real change, skipped during --dry-run
                // ...
            } );
        }

        $this->success( 'Done.' );
    }

    private function fetch_feed( int $limit ): array {
        return [];
    }
}
```

Run it:

```bash
wp netivo import products --limit=50
wp netivo import products --dry-run
wp help netivo import products
```

## Properties

| Property | Purpose |
|---|---|
| `$name` | Command, including parent words (`netivo import products`). Required. |
| `$shortdesc` | One line in `wp help`. |
| `$longdesc` | Text in `wp help <command>`. |
| `$synopsis` | Extra positional arguments, flags and assoc arguments, in WP-CLI synopsis format. |

## Methods for the subclass

- `execute( array $args, array $assoc_args )`: required. The command logic. `$args` holds positional arguments, `$assoc_args` the flags and `--key=value` options.
- `perform( string $description, callable $action )`: prints the description and runs `$action`. During `--dry-run` it prints `[dry-run] would …` and does not call `$action`. Use it for every write (database, files, remote calls).
- `is_dry_run(): bool`: check the flag yourself when a branch needs it.
- `log()`, `success()`, `warning()`, `error()`: wrappers around `WP_CLI::log`, `success`, `warning` and `error`. `error()` stops the command with a non-zero exit code.

## Enable it in the theme

Add the class to `modules.cli` in `config/modules.config.php`:

```php
'modules' => [
    'cli' => [
        \Netivo\Theme\Cli\ImportProducts::class,
    ],
],
```

The theme loads the class only when `WP_CLI` is defined, so there is no cost in normal requests.

## Notes

- `wp` loads the theme, so the theme's modules are available. Use `--skip-themes` to skip them.
- Commands with spaces in the name are nested, so `netivo import products` is run as `wp netivo import products`.
- Keep reads outside `perform()`. Only changes need to be guarded.
