# Netivo WP Core

This module is the main module for WordPress themes.

## Requirements

- PHP >= 8.4
- WordPress >= 6.3 (required for the `strategy => defer` script-loading option used in asset enqueuing)

## Development

- `composer install` — install dependencies
- `composer lint` / `composer lint:fix` — WordPress VIP coding standards (phpcs/phpcbf)
- `composer test` — PHPUnit, with WordPress functions mocked via Brain Monkey
- `composer analyse` — PHPStan at level 6, with WordPress stubs via `szepeviktor/phpstan-wordpress`; `phpstan-baseline.neon` covers accepted non-issues (see the comment at the top of that file)