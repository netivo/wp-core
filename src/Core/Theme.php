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

		add_action( 'after_setup_theme', [ $this, 'setup_theme_support' ] );

		if ( array_key_exists( 'admin_bar', $this->configuration ) && ! $this->configuration['admin_bar'] ) {
			add_action( 'after_setup_theme', [ $this, 'remove_admin_bar' ] );
		}

		$this->init_security();
		$this->init_front_site();
		$this->init_customizer();
		$this->init_database();
		$this->init_endpoints();
		$this->init_gutenberg();
		$this->init_rest_routes();
		$this->init_cli();

		if ( function_exists( 'WC' ) ) {
			$this->init_woocommerce();
		}

		$this->init();

		if ( is_admin() ) {
			$this->init_admin_site();
		}

	}

	/**
	 * Resolves a path relative to the theme root, preferring the active child theme over
	 * the parent/template theme. Returns null when the path exists in neither.
	 *
	 * @param string $relative_path Path relative to the theme root, e.g. '/config/main.config.php'.
	 *
	 * @return string|null
	 */
	public static function resolve_path( string $relative_path ): ?string {
		if ( file_exists( get_stylesheet_directory() . $relative_path ) ) {
			return get_stylesheet_directory() . $relative_path;
		}

		if ( file_exists( get_template_directory() . $relative_path ) ) {
			return get_template_directory() . $relative_path;
		}

		return null;
	}

	/**
	 * Resolves the public URI for a path relative to the theme root, preferring the active
	 * child theme over the parent/template theme. Returns null when the path exists in neither.
	 *
	 * @param string $relative_path Path relative to the theme root, e.g. '/assets/css/style.css'.
	 *
	 * @return string|null
	 */
	public static function resolve_uri( string $relative_path ): ?string {
		if ( file_exists( get_stylesheet_directory() . $relative_path ) ) {
			return get_stylesheet_directory_uri() . $relative_path;
		}

		if ( file_exists( get_template_directory() . $relative_path ) ) {
			return get_template_directory_uri() . $relative_path;
		}

		return null;
	}

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
	 * Config files are resolved child theme first, then parent/template theme, the same as
	 * assets. Label strings inside config files must NOT be passed through __() at this
	 * point: config files are loaded before the text domain is available, which raises a
	 * "_load_textdomain_just_in_time" notice on WordPress 6.7+. Keep labels as plain strings
	 * and translate them where they're used (see init_sidebars()).
	 *
	 * @return void
	 */
	protected function init_configuration(): void {
		if ( null === self::resolve_path( '/config/' ) ) {
			return;
		}

		$config_files = [
			'main'       => 'main.config.php',
			'images'     => 'images.config.php',
			'posts'      => 'posts.config.php',
			'menu'       => 'menu.config.php',
			'assets'     => 'assets.config.php',
			'sidebars'   => 'sidebars.config.php',
			'modules'    => 'modules.config.php',
		];

		$configs = [];
		foreach ( $config_files as $key => $file ) {
			$path            = self::resolve_path( '/config/' . $file );
			$configs[ $key ] = ( null !== $path ) ? include $path : array();
		}

		$this->configuration = array_merge(
			$this->configuration,
			$configs['main'],
			$configs['images'],
			$configs['posts'],
			$configs['menu'],
			$configs['assets'],
			$configs['sidebars'],
			$configs['modules']
		);

		if ( ! empty( $this->configuration['modules']['views_path'] ) ) {
			$this->view_path = $this->configuration['modules']['views_path'];
		} else {
			$this->view_path = self::resolve_path( '/src/views' ) ?? get_template_directory() . '/src/views';
		}
	}

	/**
	 * Registers theme support flags and navigation menus on after_setup_theme, as WordPress
	 * expects (add_theme_support()/register_nav_menu() should not run earlier).
	 * Array structure for 'supports':
	 * [
	 *  'supports' => [
	 *      '#support_name', // plain support flag, no args
	 *      '#support_name_with_args' => [ ... ], // support flag with args, e.g. 'custom-logo' => [ 'height' => 100 ]
	 *  ]
	 * ]
	 *
	 * @return void
	 */
	public function setup_theme_support(): void {
		if ( array_key_exists( 'supports', $this->configuration ) ) {
			foreach ( $this->configuration['supports'] as $key => $value ) {
				if ( is_int( $key ) ) {
					add_theme_support( $value );
					continue;
				}

				if ( 'html5' === $key && is_array( $value ) ) {
					// 'script'/'style' html5 support args were removed in WordPress 7.0.
					$value = array_values( array_diff( $value, [ 'script', 'style' ] ) );
				}

				if ( empty( $value ) ) {
					add_theme_support( $key );
				} else {
					add_theme_support( $key, $value );
				}
			}
		}

		if ( array_key_exists( 'menu', $this->configuration ) ) {
			foreach ( $this->configuration['menu'] as $key => $menu ) {
				register_nav_menu( $key, __( $menu['name'], 'netivo' ) );
			}
		}
	}

	/**
	 * Calls security rules for WordPress.
	 */
	protected function init_security(): void {
		// xmlrpc_enabled only disables authenticated methods, pingback.ping stays open without xmlrpc_methods.
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );

		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );

		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		add_filter( 'wp_headers', [ $this, 'remove_pingback_header' ] );

		add_filter( 'style_loader_src', [ $this, 'remove_version_scripts' ], 9999 );
		add_filter( 'script_loader_src', [ $this, 'remove_version_scripts' ], 9999 );

		add_filter( 'rest_endpoints', [ $this, 'restrict_users_endpoints' ] );
	}

	/**
	 * Initialize front end site filters and actions
	 */
	protected function init_front_site(): void {
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
			$this->load_modules( $this->configuration['modules']['customizer'], static function ( string $class ) {
				new $class();
			} );
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
			$this->load_modules( $this->configuration['modules']['database'], static function ( string $class ) {
				EntityManager::createTable( $class );
			} );
		}
	}

	/**
	 * Initializes endpoints configured in modules.config.php under the section endpoint
	 * Each module should extend \Netivo\Core\Endpoint class.
	 * @return void
	 */
	protected function init_endpoints(): void {
		if ( ! empty( $this->configuration['modules']['endpoint'] ) ) {
			$this->load_modules( $this->configuration['modules']['endpoint'], static function ( string $class ) {
				new $class();
			} );
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
			$view_path = $this->get_view_path();
			$view_uri  = str_replace( get_stylesheet_directory(), get_stylesheet_directory_uri(), $view_path );
			$this->load_modules( $this->configuration['modules']['gutenberg'], static function ( string $class ) use ( $view_path, $view_uri ) {
				new $class( $view_path, $view_uri );
			} );
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
	 * Each module should extend \Netivo\Core\RestController class.
	 *
	 * @return void
	 */
	protected function init_rest_routes(): void {
		if ( ! empty( $this->configuration['modules']['rest'] ) ) {
			$this->load_modules( $this->configuration['modules']['rest'], static function ( string $class ) {
				new $class();
			} );
		}
	}

	/**
	 * Initializes WP-CLI commands configured in modules.config.php under the section cli.
	 * Each module should extend \Netivo\Core\CliCommand class. Only runs under WP-CLI.
	 *
	 * @return void
	 */
	protected function init_cli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && ! empty( $this->configuration['modules']['cli'] ) ) {
			$this->load_modules( $this->configuration['modules']['cli'], static function ( string $class ) {
				new $class();
			} );
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
	 * Removes version from query string loading styles/scripts on front.
	 * It does not remove version for sources which have version in assets
	 *
	 * @param string $src Query string from style/script.
	 *
	 * @return string
	 */
	public function remove_version_scripts( string $src ): string {
		if ( is_admin() ) {
			return $src;
		}

		$src_without_version = remove_query_arg( 'ver', $src );

		if ( ! empty( $this->configuration['assets']['versions'] ) ) {
			if ( in_array( $src_without_version, $this->configuration['assets']['versions'], true ) ) {
				return $src;
			}
		}

		return $src_without_version;
	}

	/**
	 * Removes X-Pingback header from server responses.
	 *
	 * @param array $headers Headers to be sent.
	 *
	 * @return array
	 */
	public function remove_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Removes users REST endpoints for users who can not edit posts, to prevent user enumeration.
	 *
	 * @param array $endpoints Registered REST endpoints.
	 *
	 * @return array
	 */
	public function restrict_users_endpoints( array $endpoints ): array {
		if ( current_user_can( 'edit_posts' ) ) {
			return $endpoints;
		}

		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	}

	/**
	 * Initializes widgets defined in configuration file: modules.config.php under section widget.
	 * Each module should extend \WP_Widget class.
	 */
	public function init_widgets(): void {
		if ( ! empty( $this->configuration['modules']['widget'] ) ) {
			$this->load_modules( $this->configuration['modules']['widget'], static function ( string $class ) {
				register_widget( $class );
			} );
		}
	}

	/**
	 * Initialize sidebars based on configuration file: sidebars.config.php
	 *  Array structure:
	 *  [
	 *   'sidebars' => [
	 *       '#id' => [
	 *           'id' => #id of the sidebar,
	 *           'name' => #name of the sidebar, as a plain (untranslated) string -- translated here, not in the config file
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
		$this->register_post_types();
		$this->register_taxonomies();
	}

	/**
	 * Registers custom post types from posts.config.php, or applies built-in label
	 * overrides for 'post'/'page'.
	 *
	 * @return void
	 */
	protected function register_post_types(): void {
		$customPosts = array();
		if ( array_key_exists( 'posts', $this->configuration ) ) {
			$customPosts = $this->configuration['posts'];
		}

		foreach ( $customPosts as $id => $customPost ) {
			if ( ! in_array( $id, [ 'post', 'page' ], true ) ) {
				if ( is_string( $customPost ) ) {
					if ( class_exists( $customPost ) ) {
						new $customPost();
					}
				} else {
					register_post_type( $id, $customPost );
					if ( ! empty( $customPost['capabilities'] ) ) {
						$this->grant_capabilities( $customPost['capabilities'] );
					}
				}
			} else {
				$this->apply_builtin_post_labels( $id, $customPost );
			}
		}
	}

	/**
	 * Grants a list of capabilities to the administrator role, skipping any it already has.
	 *
	 * @param array $capabilities Capability names.
	 *
	 * @return void
	 */
	protected function grant_capabilities( array $capabilities ): void {
		$role = get_role( 'administrator' );
		if ( empty( $role ) ) {
			return;
		}

		foreach ( $capabilities as $capability ) {
			if ( ! $role->has_cap( $capability ) ) {
				$role->add_cap( $capability );
			}
		}
	}

	/**
	 * Overrides labels on the built-in 'post'/'page' post type objects.
	 *
	 * @param string $id Post type name ('post' or 'page').
	 * @param array $customPost Config entry with an optional 'labels' array.
	 *
	 * @return void
	 */
	protected function apply_builtin_post_labels( string $id, array $customPost ): void {
		global $wp_post_types;
		$labels = &$wp_post_types[ $id ]->labels;

		$label_fields = [
			'name',
			'singular_name',
			'add_new',
			'add_new_item',
			'edit_item',
			'new_item',
			'view_item',
			'search_items',
			'not_found',
			'not_found_in_trash',
			'all_items',
			'menu_name',
			'name_admin_bar',
		];

		foreach ( $label_fields as $field ) {
			if ( isset( $customPost['labels'][ $field ] ) ) {
				$labels->$field = $customPost['labels'][ $field ];
			}
		}
	}

	/**
	 * Registers custom taxonomies from posts.config.php and seeds/prunes their terms.
	 *
	 * @return void
	 */
	protected function register_taxonomies(): void {
		$customTaxonomies = array();
		if ( array_key_exists( 'taxonomies', $this->configuration ) ) {
			$customTaxonomies = $this->configuration['taxonomies'];
		}

		foreach ( $customTaxonomies as $id => $customTaxonomy ) {
			register_taxonomy( $id, $customTaxonomy['post'], $customTaxonomy['options'] );
			if ( ! empty( $customTaxonomy['terms'] ) ) {
				$this->seed_taxonomy_terms( $id, $customTaxonomy );
			}
		}
	}

	/**
	 * Seeds a taxonomy's terms from config and removes any no longer listed, when the
	 * config's 'version' doesn't match the stored 'nt_tax_{$id}_version' option.
	 *
	 * @param string $id Taxonomy name.
	 * @param array $customTaxonomy Config entry with 'version' and 'terms'.
	 *
	 * @return void
	 */
	protected function seed_taxonomy_terms( string $id, array $customTaxonomy ): void {
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- get_option() returns the stored scalar as a string; a strict compare against a config int/float would always mismatch.
		if ( ! empty( $customTaxonomy['version'] ) && ( $customTaxonomy['version'] == get_option( 'nt_tax_' . $id . '_version' ) ) ) {
			return;
		}

		foreach ( $customTaxonomy['terms'] as $term ) {
			if ( ! term_exists( $term['slug'], $id ) ) {
				wp_insert_term( $term['name'], $id, [ 'slug' => $term['slug'] ] );
			}
		}

		$current_terms = new WP_Term_Query( [
			'taxonomy'   => $id,
			'hide_empty' => false,
			'fields'     => 'id=>slug',
		] );
		foreach ( $current_terms->get_terms() as $tid => $ct ) {
			if ( ! array_key_exists( $ct, $customTaxonomy['terms'] ) ) {
				wp_delete_term( $tid, $id );
			}
		}

		update_option( 'nt_tax_' . $id . '_version', $customTaxonomy['version'] );
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
			$this->configuration['assets']['versions'] = [];

			foreach ( $this->configuration['assets']['css'] as $st ) {
				$this->enqueue_configured_asset( $st, 'style' );
			}
			foreach ( $this->configuration['assets']['js'] as $sc ) {
				$this->enqueue_configured_asset( $sc, 'script' );
			}
		}
	}

	/**
	 * Resolves a configured asset's loading directory (child theme first) and enqueues it,
	 * honouring its 'condition' callback when present. Shared by the css/js loops in
	 * init_styles_and_scripts().
	 *
	 * @param array $file Asset definition from assets.config.php.
	 * @param string $type One of: style, script.
	 *
	 * @return void
	 */
	protected function enqueue_configured_asset( array $file, string $type ): void {
		$loading_dir = self::resolve_uri( $file['file'] );
		if ( null === $loading_dir ) {
			return;
		}
		$loading_dir = substr( $loading_dir, 0, - strlen( $file['file'] ) );

		if ( ! empty( $file['condition'] ) && is_callable( $file['condition'] ) && ! $file['condition']() ) {
			return;
		}

		$this->enqueue_script_or_style( $loading_dir, $file, $type );
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
		if ( $type === 'style' ) {
			$function = 'wp_enqueue_style';
			if ( ! empty( $file['register'] ) ) {
				$function = 'wp_register_style';
			}
			$function( $file['name'], $load_dir . $file['file'],
				( ( ! empty( $file['dependencies'] ) ) ? $file['dependencies'] : [] ),
				( ( ! empty( $file['version'] ) ) ? $file['version'] : null ),
				( ( ! empty( $file['media'] ) ) ? $file['media'] : 'all' )
			);
		} else {
			$function = 'wp_enqueue_script';
			if ( ! empty( $file['register'] ) ) {
				$function = 'wp_register_script';
			}
			$function( $file['name'], $load_dir . $file['file'],
				( ( ! empty( $file['dependencies'] ) ) ? $file['dependencies'] : [] ),
				( ( ! empty( $file['version'] ) ) ? $file['version'] : null ),
				[
					'in_footer' => true,
					'strategy'  => 'defer'
				]
			);
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

	/**
	 * Prevents cloning of the theme instance, which must stay a singleton.
	 */
	protected function __clone() {
	}
}
