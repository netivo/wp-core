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
	 *
	 * @param bool $auto_register Whether to register WordPress hooks immediately. Pass
	 *                             false to construct the instance without touching any
	 *                             hook, then call register() explicitly when ready.
	 *                             Defaults to true to match the previous behaviour.
	 */
	public function __construct( bool $auto_register = true ) {
		if ( $auto_register ) {
			$this->register();
		}
	}

	/**
	 * Registers this block's WordPress hooks. Called automatically from the constructor
	 * unless it was built with $auto_register = false.
	 */
	public function register(): void {
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
