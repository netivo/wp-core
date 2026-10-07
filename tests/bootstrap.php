<?php
/**
 * PHPUnit bootstrap for netivo/wp-core.
 *
 * Every class file in src/Core guards on `if ( ! defined( 'ABSPATH' ) ) exit;`, so ABSPATH
 * must exist before any of them are loaded, or the test run dies immediately.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/stubs/abspath/' );

// Endpoint::$place defaults to EP_NONE, evaluated when the class is first declared/autoloaded
// (which, for an extending class declared at file scope, happens during compilation — before
// any runtime `define()` in that same test file would run). Define it here instead, up front.
define( 'EP_NONE', 0 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs/wp-cli-stub.php';
