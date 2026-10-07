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

/**
 * Abstract class Panel
 *
 * Parent class for the admin side of a theme. Created by Theme::init_admin_site().
 * Reads modules.admin from the configuration and loads pages, metaboxes, term meta,
 * Gutenberg blocks and bulk actions.
 */
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
	 *
	 * @param Theme $parent Parent theme instance.
	 */
	public function __construct( Theme $parent ) {
		$this->parent_class = $parent;
		$this->set_vars();

		if ( ! empty( $this->parent_class->get_configuration()['modules']['admin'] ) ) {
			$this->modules = $this->parent_class->get_configuration()['modules']['admin'];
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'init_header' ] );
		try {
			$this->init_pages();
			$this->init_metaboxes();
			$this->init_termmeta();
			$this->init_gutenberg();
			$this->init_bulkactions();

			$this->init();

		} catch ( \Exception $e ) {
			wp_trigger_error( __METHOD__, sprintf( '%s (code %d)', $e->getMessage(), $e->getCode() ) );
		}
	}

	/**
	 * Sets the include path and URI of the theme admin files.
	 * Must be implemented by the subclass; the values are used by Gutenberg modules.
	 *
	 * @return void
	 */
	protected abstract function set_vars(): void;

	/**
	 * Instantiates or calls $factory for each class name in $classes that exists, per
	 * class_exists(). Non-string entries are skipped. Shared by the module loaders below
	 * to avoid repeating the same "if not empty / class_exists / new" block.
	 *
	 * @param array $classes List of class names from configuration.
	 * @param callable $factory Called with each existing class name.
	 *
	 * @return void
	 */
	protected function load_modules( array $classes, callable $factory ): void {
		foreach ( $classes as $class ) {
			if ( is_string( $class ) && class_exists( $class ) ) {
				$factory( $class );
			}
		}
	}

	/**
	 * Init pages to Admin view. Each entry carries its own 'children', so it doesn't fit
	 * the plain class-list shape load_modules() handles for the other loaders below.
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
			$view_path = $this->parent_class->get_view_path();
			$this->load_modules( $this->modules['metabox'], static function ( string $class ) use ( $view_path ) {
				new $class( $view_path );
			} );
		}
	}

	/**
	 * Init term meta boxes in Admin view.
	 */
	protected function init_termmeta(): void {
		if ( ! empty( $this->modules['term-meta'] ) ) {
			$view_path = $this->parent_class->get_view_path();
			$this->load_modules( $this->modules['term-meta'], static function ( string $class ) use ( $view_path ) {
				new $class( $view_path );
			} );
		}
	}

	/**
	 * Init gutenberg blocks in Admin editor.
	 */
	protected function init_gutenberg(): void {
		if ( ! empty( $this->modules['gutenberg'] ) ) {
			$include_path = $this->include_path;
			$uri          = $this->uri;
			$this->load_modules( $this->modules['gutenberg'], static function ( string $class ) use ( $include_path, $uri ) {
				new $class( $include_path, $uri );
			} );
		}
	}

	/**
	 * Init Bulk Action to admin views.
	 */
	protected function init_bulkactions(): void {
		if ( ! empty( $this->modules['bulk'] ) ) {
			$this->load_modules( $this->modules['bulk'], static function ( string $class ) {
				new $class();
			} );
		}
	}

	/**
	 * Theme-specific admin setup, called after all modules are loaded.
	 *
	 * @return void
	 */
	protected abstract function init(): void;

	/**
	 * Initializes scripts and styles loaded in admin page.
	 *
	 * @param string $page Current admin page hook suffix.
	 */
	public function init_header( string $page ): void {
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

	/**
	 * Adds theme-specific scripts and styles to the admin page.
	 * Called from init_header() on admin_enqueue_scripts.
	 *
	 * @param string $page Current admin page hook suffix.
	 *
	 * @return void
	 */
	protected abstract function custom_header( string $page ): void;
}