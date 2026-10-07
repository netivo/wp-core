<?php
/**
 * Minimal stand-in for the WP_CLI class, which only exists when running under WP-CLI.
 * Records every call so tests can assert on it instead of mocking a real plugin class.
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		/** @var array<int, array{0: string, 1: mixed}> */
		public static array $calls = [];

		public static function reset(): void {
			self::$calls = [];
		}

		public static function add_command( string $name, callable $callback, array $args = [] ): void {
			self::$calls[] = [ 'add_command', [ $name, $callback, $args ] ];
		}

		public static function log( string $message ): void {
			self::$calls[] = [ 'log', $message ];
		}

		public static function success( string $message ): void {
			self::$calls[] = [ 'success', $message ];
		}

		public static function warning( string $message ): void {
			self::$calls[] = [ 'warning', $message ];
		}

		public static function error( string $message ): void {
			self::$calls[] = [ 'error', $message ];
		}
	}
}
