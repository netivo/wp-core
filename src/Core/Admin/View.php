<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 7.08.2024
 * Time: 14:20
 *
 */

namespace Netivo\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Class View
 *
 * Renders an admin page: the shared layout around the page's own view file.
 * Variables set through magic properties are available in the layout and the page view.
 */
class View {
	/**
	 * Variables to put on view
	 *
	 * @var array
	 */
	protected array $_variables = [];

	/**
	 * Page to which view is assigned
	 *
	 * @var null|Page
	 */
	protected ?Page $_page = null;

	/**
	 * View constructor.
	 *
	 * @param Page $page Page the view belongs to.
	 */
	public function __construct( Page $page ) {
		$this->_page = $page;
	}

	/**
	 * Displays content of page
	 *
	 * @throws \Exception When error.
	 */
	public function display(): void {
		$this->render();
	}

	/**
	 * Renders the view
	 */
	protected function render(): void {
		require __DIR__ . '/../../../views/layout.phtml';
	}

	/**
	 * Renders the page content
	 *
	 * @throws \Exception When error.
	 */
	public function content(): void {
		extract( $this->_variables );
		if ( file_exists( $this->_page->get_view_file() ) ) {
			require $this->_page->get_view_file();
		} else {
			throw new \Exception( "There is no view file for this admin action" );
		}
	}

	/**
	 * Magical method to get the parameter from variables array.
	 *
	 * @param string $name Name of parameter to get.
	 *
	 * @return mixed|null
	 */
	public function __get( string $name ): mixed {
		return ( array_key_exists( $name, $this->_variables ) ) ? $this->_variables[ $name ] : null;
	}

	/**
	 * Magical method to set parameter in variables to value.
	 *
	 * @param string $name Name of the parameter to set.
	 * @param mixed $value Value of new parameter.
	 */
	public function __set( string $name, mixed $value ): void {
		$this->_variables[ $name ] = $value;
	}

	/**
	 * Proxies method calls from a view file to the page, e.g. $this->nonce_field().
	 *
	 * @param string $name Method name.
	 * @param array $arguments Method arguments.
	 *
	 * @return mixed
	 */
	public function __call( string $name, array $arguments ): mixed {
		return $this->_page->$name( ...$arguments );
	}
}