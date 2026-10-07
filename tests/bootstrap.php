<?php
/**
 * PHPUnit bootstrap for netivo/wp-core.
 *
 * Every class file in src/Core guards on `if ( ! defined( 'ABSPATH' ) ) exit;`, so ABSPATH
 * must exist before any of them are loaded, or the test run dies immediately.
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/fixtures/abspath/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
