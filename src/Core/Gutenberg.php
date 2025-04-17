<?php
/**
 * Created by Netivo for Netivo Core Plugin.
 * User: michal
 * Date: 09.11.18
 * Time: 08:42
 *
 * @package Netivo\Core\Admin
 */

namespace Netivo\Core;

use ReflectionClass;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Abstract class Gutenberg
 *
 * Parent class which handles gutenberg block registration.
 */
abstract class Gutenberg {

	/**
	 * Callback name.
	 *
	 * @var string|null
	 */
	protected ?string $callback = null;
	

	/**
	 * Gutenberg constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers scripts, styles and block.
	 *
	 * @throws \Exception When error.
	 */
	public function register_block(): void {
		$obj  = new ReflectionClass( $this );
		$data = $obj->getAttributes();
		foreach ( $data as $attribute ) {
			if ( $attribute->getName() == 'Netivo\Attributes\Block' ) {
				$name = $attribute->getArguments()[0];
			}
		}
		if ( empty( $name ) ) {
			$filename = $obj->getFileName();
			$filename = str_replace( '.php', '', $filename );
			$name     = basename( $filename );
			$name     = 'src/views/gutenberg/' . strtolower( $name );
		}

		$block_json = get_template_directory() . '/' . strtolower( $name ) . '/block.json';
		if ( file_exists( $block_json ) ) {
			$args = [];
			if ( ! empty( $this->callback ) ) {
				$args['render_callback'] = array( $this, $this->callback );
			}

			register_block_type( $block_json, $args );
		} else {
			throw new \Exception( 'Block json not found.' );
		}

	}

}
