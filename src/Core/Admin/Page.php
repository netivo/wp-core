<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 7.08.2024
 * Time: 16:40
 *
 */

namespace Netivo\Core\Admin;

use Exception;
use ReflectionClass;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Abstract class Page
 *
 * Parent class for an admin menu page, sub-page or tab.
 * Subclasses set the configuration properties, implement do_action() and save(),
 * and render their content from a view file.
 */
abstract class Page {

	/**
	 * Name of the page used as view name
	 *
	 * @var string
	 */
	protected string $_name = '';

	/**
	 * View class for the page
	 *
	 * @var View
	 */
	protected View $view;

	/**
	 * Page type. One of: main, subpage, tab
	 * main - Main page will display in first level menu
	 * subpage - Sub page will display in second level menu, MUST have parent attribute
	 * tab - Tab for page, will not display in menu, MUST have parent attribute
	 *
	 * @var string
	 */
	protected string $_type = 'main';

	/**
	 * The text to be displayed in the title tags of the page when the menu is selected.
	 *
	 * @var string
	 */
	protected string $_page_title = '';

	/**
	 * The text to be used for the menu.
	 *
	 * @var string
	 */
	protected string $_menu_text = '';
	/**
	 * The capability required for this menu to be displayed to the user.
	 *
	 * @var string
	 */
	protected string $_capability = 'manage_options';
	/**
	 * The slug name to refer to this menu by. Should be unique for this menu page and only include lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key()
	 *
	 * @var string
	 */
	protected string $_menu_slug = '';
	/**
	 * The URL to the icon to be used for this menu.
	 * Pass a base64-encoded SVG using a data URI, which will be colored to match the color scheme. This should begin with 'data:image/svg+xml;base64,'.
	 * Pass the name of a Dashicons helper class to use a font icon, e.g. 'dashicons-chart-pie'.
	 * Pass 'none' to leave div.wp-menu-image empty so an icon can be added via CSS.
	 *
	 * Ignored when subpage or tab
	 *
	 * @var string
	 */
	protected string $_icon = '';
	/**
	 * The position in the menu order this one should appear.
	 *
	 * Ignored when subpage or tab
	 *
	 * @var int|null
	 */
	protected ?int $_position = null;

	/**
	 * The slug name for the parent element (or the file name of a standard WordPress admin page).
	 * Needed when submenu or tab
	 *
	 * @var string
	 */
	protected string $_parent = '';

	/**
	 * List of Child classes
	 *
	 * @var array
	 */
	protected array $_children = array();

	/**
	 * Children of page
	 *
	 * @var array
	 */
	protected array $_childrenObjects = array();

	/**
	 * Redirect url after saving
	 *
	 * @var string
	 */
	protected string $_redirect_url = '';

	/**
	 * Path to admin
	 *
	 * @var string
	 */
	protected string $_views_path = '';

	/**
	 * Page constructor.
	 *
	 * @param string $path Path to admin.
	 * @param array $children Child page definitions, each with 'class' and optional 'children'.
	 */
	public function __construct( $path, $children ) {
		$this->_views_path = $path;
		$this->_children   = $children;
		$this->generate_redirect();
		add_action( 'admin_init', [ $this, 'do_save' ] );
		if ( $this->_type !== 'tab' ) {
			add_action( 'admin_menu', [ $this, 'register_menu' ] );
			$this->register_children();
		}
		$this->view = new View( $this );
	}

	/**
	 * Generate redirect link if empty
	 */
	protected function generate_redirect(): void {
		if ( empty( $this->_redirect_url ) ) {
			$rdu = 'admin.php?page=';
			if ( $this->_type === 'tab' ) {
				$rdu .= $this->_parent;
				$rdu .= '&tab=' . $this->_menu_slug;
			} else {
				$rdu .= $this->_menu_slug;
			}
			$this->_redirect_url = $rdu;
		}
	}

	/**
	 * Register all children of page
	 */
	protected function register_children(): void {
		if ( ! empty( $this->_children ) ) {
			foreach ( $this->_children as $page ) {
				if ( class_exists( $page['class'] ) ) {
					$className                 = $page['class'];
					$children                  = ( ! empty( $page['children'] ) ) ? $page['children'] : [];
					$this->_childrenObjects[] = new $className( $this->_views_path, $children );
				}
			}
		}
	}

	/**
	 * Outputs the nonce field for this page's save form.
	 * Theme view files must call this inside the `<form>` that submits `save_{$this->_menu_slug}`.
	 */
	public function nonce_field(): void {
		wp_nonce_field( 'save_' . $this->_menu_slug );
	}

	/**
	 * Register menu element in Admin
	 */
	public function register_menu(): void {
		if ( $this->_type === 'subpage' ) {
			add_submenu_page( $this->_parent, $this->_page_title, $this->_menu_text, $this->_capability, $this->_menu_slug, function () {
				$this->display();
			} );
		} elseif ( $this->_type === 'main' ) {
			add_menu_page( $this->_page_title, $this->_menu_text, $this->_capability, $this->_menu_slug, function () {
				$this->display();
			}, $this->_icon, $this->_position );
		}
	}

	/**
	 * Displays the view
	 * @throws Exception
	 */
	public function display(): void {
		$this->view->title = $this->_page_title;
		wp_enqueue_media();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation, not a state change; value is sanitized with sanitize_key().
		$tab_slug = ( isset( $_GET['tab'] ) && ! empty( $_GET['tab'] ) ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( ! $this->is_tab() && ! empty( $tab_slug ) ) {
			$tab = $this->find_tab( $tab_slug );
			if ( ! empty( $tab ) ) {
				$tab->display();
			} else {
				$this->do_action();
				$this->view->display();
			}
		} else {
			if ( $this->is_tab() ) {
				$this->view->tab = $this->_menu_slug;
			}
			$this->do_action();
			$this->view->display();
		}
	}

	/**
	 * Check if page is tab
	 *
	 * @return bool
	 */
	public function is_tab(): bool {
		return $this->_type === 'tab';
	}

	/**
	 * Finds called tab
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return mixed|null
	 */
	public function find_tab( string $tab ): mixed {
		foreach ( $this->_childrenObjects as $child ) {
			if ( $child->is_tab() ) {
				if ( $child->get_slug() === $tab ) {
					return $child;
				}
			}
		}

		return null;
	}

	/**
	 * Get page slug
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->_menu_slug;
	}

	/**
	 * Action done before displaying content
	 */
	abstract public function do_action(): void;

	/**
	 * Save main function, called on save
	 */
	public function do_save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation, not a state change; value is sanitized with sanitize_key().
		$tab_slug = ( isset( $_GET['tab'] ) && ! empty( $_GET['tab'] ) ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( ! $this->is_tab() && ! empty( $tab_slug ) ) {
			$tab = $this->find_tab( $tab_slug );
			if ( ! empty( $tab ) ) {
				$tab->do_save();

				return;
			}
		}

		if ( $this->is_post() ) {
			$this->process_save();
		}
	}

	/**
	 * Runs save(), handling the nonce/capability failure and the redirect.
	 */
	protected function process_save(): void {
		if ( ! current_user_can( $this->_capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'netivo' ) );
		}
		check_admin_referer( 'save_' . $this->_menu_slug );

		try {
			$this->save();
			wp_safe_redirect( add_query_arg( 'success', '1', admin_url( $this->_redirect_url ) ) );
			exit;
		} catch ( Exception $e ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( $e->getMessage() ), admin_url( $this->_redirect_url ) ) );
			exit;
		}
	}

	/**
	 * Checks if current page is saved.
	 *
	 * @return bool
	 */
	public function is_post(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- only checks the save button was clicked; process_save() verifies the nonce before anything is saved.
		return isset( $_POST[ 'save_' . $this->_menu_slug ] );
	}

	/**
	 * Save function, to be used in child class.
	 * Main data saving is done here.
	 */
	abstract public function save(): void;

	/**
	 * Get view path for current page.
	 */
	public function get_view_file(): string {
		$obj  = new ReflectionClass( $this );
		$data = $obj->getAttributes();
		foreach ( $data as $attribute ) {
			if ( $attribute->getName() === 'Netivo\Attributes\View' ) {
				$name = $attribute->getArguments()[0];
			}
		}
		if ( empty( $name ) ) {
			$filename = $obj->getFileName();
			$filename = str_replace( '.php', '', $filename );

			$name = basename( $filename );
			$name = strtolower( $name );
		}

		return $this->_views_path . '/admin/pages/' . $name . '.phtml';
	}

	/**
	 * Gets the path to admin.
	 *
	 * @return string
	 */
	public function get_views_path(): string {
		return $this->_views_path;
	}
}