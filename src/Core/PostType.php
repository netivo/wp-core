<?php
/**
 * Created by Netivo for modules
 * User: manveru
 * Date: 9.06.2025
 * Time: 14:37
 *
 */

namespace Netivo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Abstract class PostType
 *
 * Parent class which handles CPT registration.
 */
abstract class PostType {

	/**
	 * @throws \Exception
	 */
	public function __construct() {
		$this->register();
	}

	/**
	 * Registers the post type.
	 *
	 * @throws \Exception
	 */
	protected function register(): void {
		$options = $this->get_settings();
		$id      = $this->get_id();
		if ( ! empty( $id ) && ! empty( $options ) ) {
			if ( ! post_type_exists( $id ) ) {
				register_post_type( $this->get_id(), $options );
				if ( ! empty( $options['capabilities'] ) ) {
					$role = get_role( 'administrator' );
					foreach ( $options['capabilities'] as $capability ) {
						$role->add_cap( $capability );
					}
				}
			} else {
				throw new \Exception( 'Post type: \'' . $id . '\' already exists.' );
			}
		}
	}

	/**
	 * Retrieves post type id.
	 *
	 * @return string
	 */
	public abstract function get_id(): string;

	/**
	 * Retrieves the settings configuration.
	 *
	 * @return array An associative array containing settings data.
	 */
	public abstract function get_settings(): array;
}