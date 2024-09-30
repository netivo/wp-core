<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 9.08.2024
 * Time: 15:06
 *
 */

namespace Netivo\Core\Admin;

use Netivo\Core\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

abstract class Panel {
	/**
	 * @var Theme|null
	 */
	public Theme|null $parent_class = null;

	/**
	 * Include path for plugin, defined in child classes
	 *
	 * @var string
	 */
	public string $include_path = '';

	/**
	 * Uri for plugin.
	 *
	 * @var string
	 */
	public string $uri = '';

	/**
	 * Modules to load automatically, loaded from configuration files
	 * @var array
	 */
	public array $modules = [];

	/**
	 * Panel constructor.
	 */
	public function __construct( $parent ) {
		$this->parent_class = $parent;
		$this->set_vars();

		if ( ! empty( $this->parent_class->get_configuration()['modules']['admin'] ) ) {
			$this->modules = $this->parent_class->get_configuration()['modules']['admin'];
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'init_header' ] );
		try {
			$this->init_pages();
			$this->init_metaboxes();
			$this->init_gutenberg();
			$this->init_bulkactions();

			$this->init();

		} catch ( \Exception $e ) {
			var_dump( $e->getCode() );
		}
	}

	protected abstract function set_vars(): void;

	/**
	 * Init pages to Admin view.
	 */
	protected function init_pages(): void {
		if ( ! empty( $this->modules['pages'] ) ) {
			foreach ( $this->modules['pages'] as $page ) {
				if ( class_exists( $page['class'] ) ) {
					$className = $page['class'];
					$children  = ( ! empty( $page['children'] ) ) ? $page['children'] : [];
					new $className( $this->parent_class->get_view_path(), $children );
				}
			}
		}

	}

	/**
	 * Init metaboxes in Admin view.
	 */
	protected function init_metaboxes(): void {
		if ( ! empty( $this->modules['metabox'] ) ) {
			foreach ( $this->modules['metabox'] as $meta ) {
				if ( class_exists( $meta ) ) {
					new $meta( $this->parent_class->get_view_path() );
				}
			}
		}
	}

	/**
	 * Init gutenberg blocks in Admin editor.
	 */
	protected function init_gutenberg(): void {
		if ( ! empty( $this->modules['gutenberg'] ) ) {
			foreach ( $this->modules['gutenberg'] as $gutenberg ) {
				if ( class_exists( $gutenberg ) ) {
					new $gutenberg( $this->include_path, $this->uri );
				}
			}
		}
	}

	/**
	 * Init Bulk Action to admin views.
	 */
	protected function init_bulkactions(): void {
		if ( ! empty( $this->modules['bulk'] ) ) {
			foreach ( $this->modules['bulk'] as $bulk ) {
				if ( class_exists( $bulk ) ) {
					new $bulk();
				}
			}
		}
	}

	protected abstract function init(): void;

	/**
	 * Initializes scripts and styles loaded in admin page.
	 */
	public function init_header( $page ): void {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );
		wp_enqueue_script( 'media-upload' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-progressbar' );
		wp_enqueue_media();

		$this->custom_header( $page );
	}

	protected abstract function custom_header( $page ): void;
}