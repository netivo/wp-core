<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 5.07.2024
 * Time: 17:11
 *
 */

namespace Netivo\Core;

use Netivo\Core\Database\EntityManager;
use WP_Term_Query;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Abstract class Theme
 *
 * During theme creation you must create a main theme class in your project namespace.
 * Created class must extend this class.
 *
 * To properly run the application in functions.php you must create instace of the class like this.
 * Remember to change class name and namespace to yours project.
 *
 * require_once( 'vendor/autoload.php' );
 * -- define theme options
 * \Netivo\Theme\Main::get_instance();
 *
 * You can also declare global function to get instance anywhere in the theme.
 * if( ! function_exists( 'Main' )) {
 *     function Main() {
 *         return \Netivo\Theme\Main::get_instance();
 *     }
 * }
 */
abstract class Theme {
	/**
	 * Class name of Admin panel.
	 *
	 * @var string
	 */
	public static string $admin_panel = '';
	/**
	 * Class name of Woocommerce panel class.
	 *
	 * @var string
	 */
	public static string $woocommerce_panel = '';
	/**
	 * Main theme instance, theme run once, allow for only one instance per run
	 *
	 * @var array
	 */
	protected static array $instances = array();
	/**
	 * Configuration for theme
	 *
	 * @var array
	 */
	protected array $configuration = array();

	/**
	 * View path for the theme classes
	 *
	 * @var string
	 */
	protected string $view_path = '';

	/**
	 * Theme constructor inits all necessary data.
	 */
	protected function __construct() {
		$this->init_configuration();

		if ( array_key_exists( 'admin_bar', $this->configuration ) ) {
			if ( ! $this->configuration['admin_bar'] ) {
				add_action( 'after_setup_theme', [ $this, 'remove_admin_bar' ] );
			}
		}

		if ( array_key_exists( 'supports', $this->configuration ) ) {
			foreach ( $this->configuration['supports'] as $support ) {
				if ( is_string( $support ) ) {
					$args = ( array_key_exists( $support, $this->configuration['supports'] ) ) ? $this->configuration['supports'][ $support ] : [];
					if ( empty( $args ) ) {
						add_theme_support( $support );
					} else {
						add_theme_support( $support, $args );
					}
				}
			}
		}

		$this->init_security();
		$this->init_front_site();
		$this->init_customizer();
		$this->init_database();
		$this->init_endpoints();
		$this->init_gutenberg();
		$this->init_rest_routes();

		if ( function_exists( 'WC' ) ) {
			$this->init_woocommerce();
		}

		$this->init();

		if ( is_admin() ) {
			$this->init_admin_site();
		}

	}

	/**
	 * Gets the configuration from config files.
	 * Config files must return an array with configuration.
	 * Available config files are:
	 * images.config.php - Stores information about custom image sizes
	 * sidebars.config.php - Stores information about sidebars and widgets to register
	 * posts.config.php - Stores information about CPT and CT to register
	 * menu.config.php - Stores information about menu positions to register
	 * assets.config.php - Stores information about styles and scripts to load on front
	 * modules.config.php - Stores information about theme/plugin modules to load
	 * main.config.php - Stores other configuration connected.
	 *
	 * It is possible but not recommended to use single file configuration.
	 *
	 * @return void
	 */
	protected function init_configuration(): void {
		if ( file_exists( get_template_directory() . "/config/" ) ) {
			$imagesConfig   = array();
			$postsConfig    = array();
			$menuConfig     = array();
			$assetsConfig   = array();
			$sidebarsConfig = array();
			$mainConfig     = array();
			$modulesConfig  = array();

			$config_dir = get_template_directory() . "/config/";
			if ( file_exists( $config_dir . 'images.config.php' ) ) {
				$imagesConfig = include $config_dir . 'images.config.php';
			}
			if ( file_exists( $config_dir . 'sidebars.config.php' ) ) {
				$sidebarsConfig = include $config_dir . 'sidebars.config.php';
			}
			if ( file_exists( $config_dir . 'posts.config.php' ) ) {
				$postsConfig = include $config_dir . 'posts.config.php';
			}
			if ( file_exists( $config_dir . 'menu.config.php' ) ) {
				$menuConfig = include $config_dir . 'menu.config.php';
			}
			if ( file_exists( $config_dir . 'assets.config.php' ) ) {
				$assetsConfig = include $config_dir . 'assets.config.php';
			}
			if ( file_exists( $config_dir . 'main.config.php' ) ) {
				$mainConfig = include $config_dir . 'main.config.php';
			}
			if ( file_exists( $config_dir . 'modules.config.php' ) ) {
				$modulesConfig = include $config_dir . 'modules.config.php';
			}

			$this->configuration = array_merge( $this->configuration, $mainConfig, $imagesConfig, $postsConfig, $menuConfig, $assetsConfig, $sidebarsConfig, $modulesConfig );

			if ( ! empty( $this->configuration['modules']['views_path'] ) ) {
				$this->view_path = $this->configuration['modules']['views_path'];
			} else {
				$this->view_path = get_template_directory() . '/src/views';
			}
		}
	}

	/**
	 * Calls security rules for WordPress.
	 */
	protected function init_security(): void {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'style_loader_src', [ $this, 'remove_version_scripts' ], 9999 );
		add_filter( 'script_loader_src', [ $this, 'remove_version_scripts' ], 9999 );
	}

	/**
	 * Initialize front end site filters and actions
	 */
	protected function init_front_site(): void {
		if ( array_key_exists( 'menu', $this->configuration ) ) {
			foreach ( $this->configuration['menu'] as $key => $menu ) {
				register_nav_menu( $key, $menu['name'] );
			}
		}

		add_action( 'widgets_init', [ $this, 'init_widgets' ] );

		add_action( 'init', [ $this, 'init_sidebars' ] );
		add_action( 'init', [ $this, 'init_custom_posts_and_taxonomies' ] );
		add_action( 'init', [ $this, 'init_custom_image_sizes' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'init_styles_and_scripts' ] );
	}

	/**
	 * Initialize WP Customizer settings defined in modules.config.php under the section customizer.
	 *
	 * @return void
	 */
	protected function init_customizer(): void {
		if ( ! empty( $this->configuration['modules']['customizer'] ) ) {
			foreach ( $this->configuration['modules']['customizer'] as $customizer ) {
				if ( class_exists( $customizer ) ) {
					new $customizer();
				}
			}
		}
	}

	/**
	 * Initializes database tables configured in modules.config.php  under the section database.
	 * Each module should extend \Netivo\Core\Database\Entity class.
	 *
	 * @return void
	 */
	protected function init_database(): void {
		if ( ! empty( $this->configuration['modules']['database'] ) ) {
			foreach ( $this->configuration['modules']['database'] as $dbTable ) {
				if ( class_exists( $dbTable ) ) {
					EntityManager::createTable( $dbTable );
				}
			}
		}
	}

	/**
	 * Initializes endpoints configured in modules.config.php under the section endpoint
	 * Each module should extend \Netivo\Core\Endpoint class.
	 * @return void
	 */
	protected function init_endpoints(): void {
		if ( ! empty( $this->configuration['modules']['endpoint'] ) ) {
			foreach ( $this->configuration['modules']['endpoint'] as $endpoint ) {
				if ( class_exists( $endpoint ) ) {
					new $endpoint();
				}
			}
		}
	}

	/**
	 * Initialize frontend(dynamic content) Gutenberg blocks defined in modules.config.php under the section gutenberg.
	 * Each module should extend \Netivo\Core\Gutenberg class.
	 *
	 * @return void
	 */
	protected function init_gutenberg(): void {
		if ( ! empty( $this->configuration['modules']['gutenberg'] ) ) {
			foreach ( $this->configuration['modules']['gutenberg'] as $gutenberg ) {
				if ( class_exists( $gutenberg ) ) {
					new $gutenberg( $this->get_view_path(), str_replace( get_stylesheet_directory(), get_stylesheet_directory_uri(), $this->get_view_path() ) );
				}
			}
		}
	}

	/**
	 * Retrieves the path for the view files.
	 *
	 * @return string
	 */
	public function get_view_path(): string {
		return $this->view_path;
	}

	/**
	 * Initializes rest routes configured in modules.config.php under the section rest.
	 * Each module should extend \Netivo\Core\Endpoint class.
	 *
	 * @return void
	 */
	protected function init_rest_routes(): void {
		if ( ! empty( $this->configuration['modules']['rest'] ) ) {
			foreach ( $this->configuration['modules']['rest'] as $rest ) {
				if ( class_exists( $rest ) ) {
					new $rest();
				}
			}
		}
	}

	/**
	 * Initializes woocommerce functions and class if it is set up in the main instance.
	 * To set up WooCommerce class in functions.php you must put (change class names to project namespace):
	 * \Netivo\Theme\Main::$woocommerce_panel = \Netivo\Theme\Woocommerce::class;
	 * before getting the instance
	 *
	 * Woocommerce class should extend \Netivo\Core\Woocommerce class.
	 */
	protected function init_woocommerce(): void {
		add_action( 'after_setup_theme', [ $this, 'enable_woocommerce_support' ] );
		if ( ! empty( self::$woocommerce_panel ) && class_exists( self::$woocommerce_panel ) ) {
			$name = self::$woocommerce_panel;
			new $name( $this );
		}
	}

	/**
	 * Abstract method where you can put your own code.
	 *
	 * @return void
	 */
	protected abstract function init(): void;

	/**
	 * Initializes the admin class if it is set up in the main instance.
	 * To set up admin class in functions.php you must put (change class names to project namespace):
	 * \Netivo\Theme\Main::$admin_panel = \Netivo\Theme\Admin\Panel::class;
	 * before getting the main instance.
	 *
	 * Admin panel class should extend \Netivo\Core\Admin\Panel class.
	 */
	protected function init_admin_site(): void {
		if ( ! empty( self::$admin_panel ) && class_exists( self::$admin_panel ) ) {
			$name = self::$admin_panel;
			new $name( $this );
		}
	}

	/**
	 * Get class instance, allowed only one instance per run
	 *
	 * @return object
	 */
	public static function get_instance(): object {
		$class = get_called_class();
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class();
		}

		return self::$instances[ $class ];
	}

	/**
	 * Retrieves configuration for theme/plugin
	 *
	 * @return array
	 */
	public function get_configuration(): array {
		return $this->configuration;
	}

	/**
	 * Removes admin bar from front view
	 */
	public function remove_admin_bar(): void {
		show_admin_bar( false );
	}

	/**
	 * Removes version from query string loading styles/scripts
	 * It does not remove version for sources which have version in assets
	 *
	 * @param string $src Query string from style/script.
	 *
	 * @return string
	 */
	public function remove_version_scripts( string $src ): string {
		if ( ! empty( $this->configuration['assets']['versions'] ) ) {
			if ( in_array( $src, $this->configuration['assets']['versions'] ) ) {
				return $src;
			}
		}

		return remove_query_arg( 'ver', $src );
	}

	/**
	 * Initializes widgets defined in configuration file: modules.config.php under section widget.
	 * Each module should extend \WP_Widget class.
	 */
	public function init_widgets(): void {
		if ( ! empty( $this->configuration['modules']['widget'] ) ) {
			foreach ( $this->configuration['modules']['widget'] as $widget ) {
				if ( class_exists( $widget ) ) {
					register_widget( $widget );
				}
			}
		}
	}

	/**
	 * Initialize sidebars based on configuration file: sidebars.config.php
	 *  Array structure:
	 *  [
	 *   'sidebars' => [
	 *       '#id' => [
	 *           'id' => #id of the sidebar,
	 *           'name' => #name of the sidebar
	 *       ]
	 *   ]
	 *  ]
	 */
	public function init_sidebars(): void {
		$sidebars = array();
		if ( array_key_exists( 'sidebars', $this->configuration ) ) {
			$sidebars = $this->configuration['sidebars'];
		}
		$args = array(
			'before_widget' => '<div class="sidebar-content widget widget-%2$s" id="%1$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3>',
			'after_title'   => '</h3>',
		);
		foreach ( $sidebars as $sidebar ) {
			$real_args         = $args;
			$real_args['id']   = $sidebar['id'];
			$real_args['name'] = __( $sidebar['name'], 'netivo' );
			if ( isset( $sidebar['before_widget'] ) ) {
				$real_args['before_widget'] = $sidebar['before_widget'];
			}
			if ( isset( $sidebar['after_widget'] ) ) {
				$real_args['after_widget'] = $sidebar['after_widget'];
			}
			if ( isset( $sidebar['before_title'] ) ) {
				$real_args['before_title'] = $sidebar['before_title'];
			}
			if ( isset( $sidebar['after_title'] ) ) {
				$real_args['after_title'] = $sidebar['after_title'];
			}
			register_sidebar( $real_args );
		}
	}

	/**
	 * Initializes custom posts and taxonomies based on configuration file: posts.config.php
	 * Array structure:
	 * [
	 *  'posts' => [
	 *      '#post_type_name' => [
	 *          #post options, accepts all options passed to $args in {@see register_post_type()} function
	 *      ]
	 *  ],
	 *  'taxonomies' => [
	 *      '#taxonomy_name' => [
	 *          'version' => #version of taxonomy, works when terms are specified,
	 *          'post' => #post_type_name for which the taxonomy is available,
	 *          'options' => #taxonomy options, accepts all options passed to $args in {@see register_taxonomy()} function,
	 *          'terms' => [ //list of terms to be added on creation
	 *              '#term_slug' => [
	 *                  'name' => #term name,
	 *                  'slug' => #term slug
	 *              ]
	 *           ]
	 *      ]
	 *  ]
	 * ]
	 */
	public function init_custom_posts_and_taxonomies(): void {
		$customPosts = array();
		if ( array_key_exists( 'posts', $this->configuration ) ) {
			$customPosts = $this->configuration['posts'];
		}


		$customTaxonomies = array();
		if ( array_key_exists( 'taxonomies', $this->configuration ) ) {
			$customTaxonomies = $this->configuration['taxonomies'];
		}


		foreach ( $customPosts as $id => $customPost ) {
			if ( ! in_array( $id, [ 'post', 'page' ] ) ) {
				register_post_type( $id, $customPost );
				if ( ! empty( $customPost['capabilities'] ) ) {
					$role = get_role( 'administrator' );
					foreach ( $customPost['capabilities'] as $capability ) {
						$role->add_cap( $capability );
					}
				}
			} else {
				global $wp_post_types;
				$labels = &$wp_post_types[ $id ]->labels;
				if ( isset( $customPost['labels']['name'] ) ) {
					$labels->name = $customPost['labels']['name'];
				}
				if ( isset( $customPost['labels']['singular_name'] ) ) {
					$labels->singular_name = $customPost['labels']['singular_name'];
				}
				if ( isset( $customPost['labels']['add_new'] ) ) {
					$labels->add_new = $customPost['labels']['add_new'];
				}
				if ( isset( $customPost['labels']['add_new_item'] ) ) {
					$labels->add_new_item = $customPost['labels']['add_new_item'];
				}
				if ( isset( $customPost['labels']['edit_item'] ) ) {
					$labels->edit_item = $customPost['labels']['edit_item'];
				}
				if ( isset( $customPost['labels']['new_item'] ) ) {
					$labels->new_item = $customPost['labels']['new_item'];
				}
				if ( isset( $customPost['labels']['view_item'] ) ) {
					$labels->view_item = $customPost['labels']['view_item'];
				}
				if ( isset( $customPost['labels']['search_items'] ) ) {
					$labels->search_items = $customPost['labels']['search_items'];
				}
				if ( isset( $customPost['labels']['not_found'] ) ) {
					$labels->not_found = $customPost['labels']['not_found'];
				}
				if ( isset( $customPost['labels']['not_found_in_trash'] ) ) {
					$labels->not_found_in_trash = $customPost['labels']['not_found_in_trash'];
				}
				if ( isset( $customPost['labels']['all_items'] ) ) {
					$labels->all_items = $customPost['labels']['all_items'];
				}
				if ( isset( $customPost['labels']['menu_name'] ) ) {
					$labels->menu_name = $customPost['labels']['menu_name'];
				}
				if ( isset( $customPost['labels']['name_admin_bar'] ) ) {
					$labels->name_admin_bar = $customPost['labels']['name_admin_bar'];
				}
			}
		}

		foreach ( $customTaxonomies as $id => $customTaxonomy ) {
			register_taxonomy( $id, $customTaxonomy['post'], $customTaxonomy['options'] );
			if ( ! empty( $customTaxonomy['terms'] ) ) {
				if ( empty( $customTaxonomy['version'] ) || ( $customTaxonomy['version'] != get_option( 'nt_tax_' . $id . '_version' ) ) ) {
					foreach ( $customTaxonomy['terms'] as $term ) {
						if ( ! term_exists( $term['slug'], $id ) ) {
							wp_insert_term( $term['name'], $id, [ 'slug' => $term['slug'] ] );
						}
					}
					$current_terms = new WP_Term_Query( [ 'taxonomy'   => $id,
					                                      'hide_empty' => false,
					                                      'fields'     => 'id=>slug'
					] );
					foreach ( $current_terms->get_terms() as $tid => $ct ) {
						if ( ! array_key_exists( $ct, $customTaxonomy['terms'] ) ) {
							wp_delete_term( $tid, $id );
						}
					}
					update_option( 'nt_tax_' . $id . '_version', $customTaxonomy['version'] );
				}
			}
		}
	}

	/**
	 * Initialize styles and scripts based on configuration file: assets.config.php
	 * Array structure
	 * [
	 *  'assets' => [
	 *      'css' => [
	 *          '#stylesheet_id' => [
	 *              'name' => #stylesheet unique name,
	 *              'file' => #stylesheet file path,
	 *              'condition' => #callback function to check when to enqueue the style, optional.
	 *              'version' => #version of the stylesheet, optional
	 *          ]
	 *      ],
	 *      'js' => [
	 *          '#script_id' => [
	 *               'name' => #script unique name,
	 *               'file' => #script file path,
	 *               'condition' => #callback function to check when to enqueue the script, optional.
	 *               'version' => #version of the script, optional
	 *           ]
	 *      ]
	 *  ]
	 * ]
	 */
	public function init_styles_and_scripts(): void {
		if ( array_key_exists( 'assets', $this->configuration ) ) {
			$js                                        = $this->configuration['assets']['js'];
			$css                                       = $this->configuration['assets']['css'];
			$this->configuration['assets']['versions'] = [];
			foreach ( $css as $st ) {
				$loading_dir = '';
				if ( file_exists( get_stylesheet_directory() . $st['file'] ) ) {
					$loading_dir = get_stylesheet_directory_uri();
				} else if ( file_exists( get_template_directory() . $st['file'] ) ) {
					$loading_dir = get_template_directory_uri();
				}
				if ( ! empty( $loading_dir ) ) {
					if ( ! empty( $st['condition'] ) && is_callable( $st['condition'] ) ) {
						if ( $st['condition']() ) {
							$this->enqueue_script_or_style( $loading_dir, $st );
						}
					} else {
						$this->enqueue_script_or_style( $loading_dir, $st );
					}
				}
			}
			foreach ( $js as $sc ) {
				$loading_dir = '';
				if ( file_exists( get_stylesheet_directory() . $sc['file'] ) ) {
					$loading_dir = get_stylesheet_directory_uri();
				} else if ( file_exists( get_template_directory() . $sc['file'] ) ) {
					$loading_dir = get_template_directory_uri();
				}
				if ( ! empty( $loading_dir ) ) {
					if ( ! empty( $sc['condition'] ) && is_callable( $sc['condition'] ) ) {
						if ( $sc['condition']() ) {
							$this->enqueue_script_or_style( $loading_dir, $sc, 'script' );
						}
					} else {
						$this->enqueue_script_or_style( $loading_dir, $sc, 'script' );
					}
				}
			}
		}
	}

	/**
	 * Enqueues style or script specified in params
	 *
	 * @param string $load_dir File loading directory uri
	 * @param array $file Array with file data
	 * @param string $type Type of file to enqueue, possible options: style, script
	 *
	 * @return void
	 */
	protected function enqueue_script_or_style( string $load_dir, array $file, string $type = 'style' ): void {
		if ( $type == 'style' ) {
			wp_enqueue_style( $file['name'], $load_dir . $file['file'], array(), ( ( ! empty( $file['version'] ) ) ? $file['version'] : null ), ( ( ! empty( $file['media'] ) ) ? $file['media'] : 'all' ) );
		} else {
			wp_enqueue_script( $file['name'], $load_dir . $file['file'], array(), ( ( ! empty( $file['version'] ) ) ? $file['version'] : null ), [ 'in_footer' => true ] );
		}
		if ( ! empty( $file['version'] ) ) {
			$this->configuration['assets']['versions'][] = $load_dir . $file['file'];
		}
	}

	/**
	 * Initialize custom image sizes defined in configuration file: images.config.php
	 * Array structure:
	 * [
	 *  'image' => [
	 *      '#name' => [
	 *          'name' => #name of the image size,
	 *          'width' => #width of the image
	 *          'height' => #height of the image,
	 *          'crop' => #whither to crop the image or not, possible values: true, false
	 *      ]
	 *  ]
	 * ]
	 */
	public function init_custom_image_sizes(): void {
		$imageSizes = array();
		if ( array_key_exists( 'image', $this->configuration ) ) {
			$imageSizes = $this->configuration['image'];
		}
		foreach ( $imageSizes as $size ) {
			add_image_size( $size['name'], $size['width'], $size['height'], $size['crop'] );
		}
	}

	/**
	 * Add theme support for woocommerce.
	 */
	public function enable_woocommerce_support(): void {
		add_theme_support( 'woocommerce' );
	}

	protected function __clone() {
	}
}