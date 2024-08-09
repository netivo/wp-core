<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 9.08.2024
 * Time: 14:50
 *
 */

namespace Netivo\Core\Admin;

use Exception;
use ReflectionClass;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

abstract class MetaBox {
	/**
	 * Metabox id.
	 *
	 * @var string
	 */
	protected string $id;
	/**
	 * Metabox title.
	 *
	 * @var string
	 */
	protected string $title;
	/**
	 * Post type/s where the metabox is visible.
	 *
	 * @var array|string
	 */
	protected array|string $screen;
	/**
	 * Page template on which metabox must appear. Works only when screen includes page.
	 * It gets value with relative path to template file
	 * or value 'home-page' if the metabox must display on front-page only.
	 *
	 * @var array|string
	 */
	protected array|string $template = '';
	/**
	 * Context of the metabox. One of:
	 * advanced - main column of edition
	 * side - right column of edition
	 *
	 * @var string
	 */
	protected string $context = 'advanced';
	/**
	 * Priority of the metabox in list. One of:
	 * default - no priority, show when initalized.
	 * high - high priority, show on top but with the init sequence stayed.
	 *
	 * @var string
	 */
	protected string $priority = 'default';

	/**
	 * Path to Admin folder on server.
	 *
	 * @var string
	 */
	protected string $path = '';

	/**
	 * Name of the view file, taken from reflection name or class attribute
	 *
	 * @var string
	 */
	protected string $view_name = '';

	/**
	 * MetaBox constructor.
	 *
	 * @param string $path Path to Admin folder.
	 */
	public function __construct( string $path ) {
		$this->path = $path;

		$obj  = new ReflectionClass( $this );
		$data = $obj->getAttributes();
		foreach ( $data as $attribute ) {
			if ( $attribute->getName() == 'Netivo\Attributes\View' ) {
				$this->view_name = $attribute->getArguments()[0];
			}
		}
		if ( empty( $this->view_name ) ) {
			$filename = $obj->getFileName();
			$filename = str_replace( '.php', '', $filename );

			$name = basename( $filename );

			$this->view_name = strtolower( $name );
		}

		if ( ! is_array( $this->screen ) ) {
			$this->screen = array( $this->screen );
		}

		add_action( 'add_meta_boxes', [ $this, 'register_box' ] );
		add_action( 'save_post', [ $this, 'do_save' ] );

	}

	/**
	 * Register metabox in admin panel.
	 */
	public function register_box(): void {
		if ( empty( $this->template ) || ! in_array( 'page', $this->screen ) ) {
			add_meta_box( $this->id, $this->title, [
				$this,
				'display'
			], $this->screen, $this->context, $this->priority );
		} else {
			global $post;
			if ( $this->template == 'home-page' ) {
				if ( in_array( $post->ID, $this->get_page_on_front() ) ) {
					add_meta_box( $this->id, $this->title, [
						$this,
						'display'
					], $this->screen, $this->context, $this->priority );
				}
			} else {
				if ( ! is_array( $this->template ) ) {
					$this->template = array( $this->template );
				}
				if ( in_array( get_post_meta( $post->ID, '_wp_page_template', true ), $this->template ) ) {
					add_meta_box( $this->id, $this->title, [
						$this,
						'display'
					], $this->screen, $this->context, $this->priority );
				}
			}
		}
	}

	protected function get_page_on_front(): array {
		$pof = (int) get_option( 'page_on_front' );
		$ret = [];
		if ( function_exists( 'pll_current_language' ) ) {
			$languages = pll_the_languages( array( 'raw' => 1 ) );
			foreach ( $languages as $lang ) {
				$ret[] = pll_get_post( $pof, $lang['slug'] );
			}
		} elseif ( function_exists( 'icl_get_languages' ) ) {
			$languages = icl_get_languages();
			foreach ( $languages as $lang ) {
				$id = apply_filters( 'wpml_object_id', $pof, 'page', false, $lang['language_code'] );
				if ( $id != null ) {
					$ret[] = $id;
				}
			}
		} else {
			$ret = [ $pof ];
		}

		return $ret;
	}

	/**
	 * Displays the metabox content.
	 *
	 * @param WP_Post $post Id of the edited post.
	 *
	 * @throws Exception When error.
	 */
	public function display( WP_Post $post ): void {
		wp_nonce_field( 'save_' . $this->id, $this->id . '_nonce' );

		$filename = $this->path . '/admin/metabox/' . $this->view_name . '.phtml';

		if ( file_exists( $filename ) ) {
			include $filename;
		} else {
			throw new Exception( "There is no view file for this admin action" );
		}

	}

	/**
	 * Start saving process of the metabox.
	 *
	 * @param int $post_id Id of the saved post.
	 *
	 * @return mixed
	 */
	public function do_save( int $post_id ): mixed {
		if ( ! isset( $_POST[ $this->id . '_nonce' ] ) ) {
			return $post_id;
		}
		if ( ! wp_verify_nonce( $_POST[ $this->id . '_nonce' ], 'save_' . $this->id ) ) {
			return $post_id;
		}
		if ( ! in_array( $_POST['post_type'], $this->screen ) ) {
			return $post_id;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		return $this->save( $post_id );
	}

	/**
	 * Method where the saving process is done. Use it in metabox to save the data.
	 *
	 * @param int $post_id Id of the saved post.
	 *
	 * @return mixed
	 */
	abstract public function save( int $post_id ): mixed;

}