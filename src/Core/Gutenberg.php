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

use Netivo\Core\Traits\ResolvesViewName;

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

	use ResolvesViewName;

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
	 * Registers the block type defined by block.json.
	 *
	 * @throws \Exception When error.
	 */
	public function register_block(): void {
		$name = $this->resolve_view_attribute( 'Netivo\Attributes\Block' )
			?? ( 'src/views/gutenberg/' . $this->resolve_view_name_from_filename() );

		$block_json = Theme::resolve_path( '/' . strtolower( $name ) . '/block.json' );
		if ( null !== $block_json ) {
			$args = [];
			if ( ! empty( $this->callback ) ) {
				$args['render_callback'] = array( $this, $this->callback );
			}

			register_block_type( $block_json, $args );
		} else {
			throw new \Exception( esc_html( 'Block json not found.' ) );
		}

	}

}
