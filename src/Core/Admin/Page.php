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
use Netivo\Core\Traits\ResolvesViewName;

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
 *
 * The configuration properties below were renamed in this version, dropping their
 * `_` prefix (R10): $_type -> $type, $_page_title -> $page_title, and so on. The old,
 * `_`-prefixed names are still declared and still work if a subclass overrides them —
 * see migrate_deprecated_properties() — but trigger a deprecation notice. No removal is
 * scheduled yet; new subclasses should use the unprefixed names regardless.
 *
 * Likewise, WordPress hooks are no longer wired unconditionally in the constructor
 * (R9): pass `$auto_register = false` to construct a Page without touching any hook,
 * then call register() explicitly when ready. The default (true) matches the previous
 * behaviour, so existing code is unaffected.
 */
abstract class Page {

	use ResolvesViewName;

	/**
	 * Maps each deprecated `_`-prefixed property to its replacement and original
	 * default, so migrate_deprecated_properties() can detect a subclass override.
	 */
	private const DEPRECATED_PROPERTIES = [
		'_name'       => [ 'name', '' ],
		'_type'       => [ 'type', 'main' ],
		'_page_title' => [ 'page_title', '' ],
		'_menu_text'  => [ 'menu_text', '' ],
		'_capability' => [ 'capability', 'manage_options' ],
		'_menu_slug'  => [ 'menu_slug', '' ],
		'_icon'       => [ 'icon', '' ],
		'_position'   => [ 'position', null ],
		'_parent'     => [ 'parent', '' ],
		'_redirect_url' => [ 'redirect_url', '' ],
	];

	/**
	 * Name of the page used as view name
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * @deprecated Use $name instead.
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
	protected string $type = 'main';

	/**
	 * @deprecated Use $type instead.
	 * @var string
	 */
	protected string $_type = 'main';

	/**
	 * The text to be displayed in the title tags of the page when the menu is selected.
	 *
	 * @var string
	 */
	protected string $page_title = '';

	/**
	 * @deprecated Use $page_title instead.
	 * @var string
	 */
	protected string $_page_title = '';

	/**
	 * The text to be used for the menu.
	 *
	 * @var string
	 */
	protected string $menu_text = '';

	/**
	 * @deprecated Use $menu_text instead.
	 * @var string
	 */
	protected string $_menu_text = '';
	/**
	 * The capability required for this menu to be displayed to the user.
	 *
	 * @var string
	 */
	protected string $capability = 'manage_options';

	/**
	 * @deprecated Use $capability instead.
	 * @var string
	 */
	protected string $_capability = 'manage_options';
	/**
	 * The slug name to refer to this menu by. Should be unique for this menu page and only include lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key()
	 *
	 * @var string
	 */
	protected string $menu_slug = '';

	/**
	 * @deprecated Use $menu_slug instead.
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
	protected string $icon = '';

	/**
	 * @deprecated Use $icon instead.
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
	protected ?int $position = null;

	/**
	 * @deprecated Use $position instead.
	 * @var int|null
	 */
	protected ?int $_position = null;

	/**
	 * The slug name for the parent element (or the file name of a standard WordPress admin page).
	 * Needed when submenu or tab
	 *
	 * @var string
	 */
	protected string $parent = '';

	/**
	 * @deprecated Use $parent instead.
	 * @var string
	 */
	protected string $_parent = '';

	/**
	 * List of Child classes
	 *
	 * @var array
	 */
	protected array $children = array();

	/**
	 * @deprecated Use $children instead.
	 * @var array
	 */
	protected array $_children = array();

	/**
	 * Children of page
	 *
	 * @var array
	 */
	protected array $children_objects = array();

	/**
	 * @deprecated Use $children_objects instead.
	 * @var array
	 */
	protected array $_childrenObjects = array();

	/**
	 * Redirect url after saving
	 *
	 * @var string
	 */
	protected string $redirect_url = '';

	/**
	 * @deprecated Use $redirect_url instead.
	 * @var string
	 */
	protected string $_redirect_url = '';

	/**
	 * Path to admin
	 *
	 * @var string
	 */
	protected string $views_path = '';

	/**
	 * @deprecated Use $views_path instead.
	 * @var string
	 */
	protected string $_views_path = '';

	/**
	 * Page constructor.
	 *
	 * @param string $path Path to admin.
	 * @param array $children Child page definitions, each with 'class' and optional 'children'.
	 * @param bool $auto_register Whether to register WordPress hooks immediately. Pass
	 *                             false to construct the instance (and its children)
	 *                             without touching any hook, then call register()
	 *                             explicitly when ready. Defaults to true to match the
	 *                             previous behaviour.
	 */
	public function __construct( $path, $children, bool $auto_register = true ) {
		$this->migrate_deprecated_properties();

		$this->views_path  = $path;
		$this->_views_path = $path;
		$this->children    = $children;
		$this->_children   = $children;
		$this->generate_redirect();
		if ( $this->type !== 'tab' ) {
			$this->register_children( $auto_register );
		}
		$this->view = new View( $this );

		if ( $auto_register ) {
			$this->register();
		}
	}

	/**
	 * Copies any `_`-prefixed property a subclass has overridden (by declaring a
	 * non-default value) into its new, unprefixed counterpart, with a deprecation
	 * notice. See the class docblock and DEPRECATED_PROPERTIES (R10).
	 */
	private function migrate_deprecated_properties(): void {
		foreach ( self::DEPRECATED_PROPERTIES as $old => [ $new, $default ] ) {
			if ( $this->$old !== $default ) {
				$this->$new = $this->$old;
				$this->deprecated_property_notice( $old, $new );
			}
		}
	}

	/**
	 * Emits a deprecation notice for a renamed property (R10).
	 *
	 * @param string $old Deprecated property name.
	 * @param string $new Replacement property name.
	 */
	private function deprecated_property_notice( string $old, string $new ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- intentional, user-facing deprecation notice (R10).
		trigger_error(
			esc_html( sprintf(
				'%s uses the deprecated $%s property; rename it to $%s.',
				static::class,
				$old,
				$new
			) ),
			E_USER_DEPRECATED
		);
	}

	/**
	 * Registers this page's WordPress hooks (admin_init, and admin_menu when the page
	 * isn't a tab). Called automatically from the constructor unless it was built with
	 * $auto_register = false.
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'do_save' ] );
		if ( $this->type !== 'tab' ) {
			add_action( 'admin_menu', [ $this, 'register_menu' ] );
		}
	}

	/**
	 * Generate redirect link if empty
	 */
	protected function generate_redirect(): void {
		if ( empty( $this->redirect_url ) ) {
			$rdu = 'admin.php?page=';
			if ( $this->type === 'tab' ) {
				$rdu .= $this->parent;
				$rdu .= '&tab=' . $this->menu_slug;
			} else {
				$rdu .= $this->menu_slug;
			}
			$this->redirect_url = $rdu;
		}

		$this->_redirect_url = $this->redirect_url;
	}

	/**
	 * Register all children of page
	 *
	 * @param bool $auto_register Passed through to each child's constructor.
	 */
	protected function register_children( bool $auto_register = true ): void {
		if ( ! empty( $this->children ) ) {
			foreach ( $this->children as $page ) {
				if ( class_exists( $page['class'] ) ) {
					$className          = $page['class'];
					$children            = ( ! empty( $page['children'] ) ) ? $page['children'] : [];
					$child               = new $className( $this->views_path, $children, $auto_register );
					$this->children_objects[] = $child;
					$this->_childrenObjects[]  = $child;
				}
			}
		}
	}

	/**
	 * Outputs the nonce field for this page's save form.
	 * Theme view files must call this inside the `<form>` that submits `save_{$this->menu_slug}`.
	 */
	public function nonce_field(): void {
		wp_nonce_field( 'save_' . $this->menu_slug );
	}

	/**
	 * Register menu element in Admin
	 */
	public function register_menu(): void {
		if ( $this->type === 'subpage' ) {
			add_submenu_page( $this->parent, $this->page_title, $this->menu_text, $this->capability, $this->menu_slug, function () {
				$this->display();
			} );
		} elseif ( $this->type === 'main' ) {
			add_menu_page( $this->page_title, $this->menu_text, $this->capability, $this->menu_slug, function () {
				$this->display();
			}, $this->icon, $this->position );
		}
	}

	/**
	 * Displays the view
	 * @throws Exception
	 */
	public function display(): void {
		$this->view->title = $this->page_title;
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
				$this->view->tab = $this->menu_slug;
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
		return $this->type === 'tab';
	}

	/**
	 * Finds called tab
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return mixed|null
	 */
	public function find_tab( string $tab ): mixed {
		foreach ( $this->children_objects as $child ) {
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
		return $this->menu_slug;
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
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'netivo' ) );
		}
		check_admin_referer( 'save_' . $this->menu_slug );

		try {
			$this->save();
			wp_safe_redirect( add_query_arg( 'success', '1', admin_url( $this->redirect_url ) ) );
			exit;
		} catch ( Exception $e ) {
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( $e->getMessage() ), admin_url( $this->redirect_url ) ) );
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
		return isset( $_POST[ 'save_' . $this->menu_slug ] );
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
		$name = $this->resolve_view_attribute( 'Netivo\Attributes\View' ) ?? $this->resolve_view_name_from_filename();

		return $this->views_path . '/admin/pages/' . $name . '.phtml';
	}

	/**
	 * Gets the path to admin.
	 *
	 * @return string
	 */
	public function get_views_path(): string {
		return $this->views_path;
	}
}
