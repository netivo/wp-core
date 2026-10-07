<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 9.08.2024
 * Time: 14:57
 *
 */

namespace Netivo\Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Abstract class BulkAction
 *
 * Parent class for a custom bulk action on a list-table screen (post list, taxonomy term
 * list, or a WooCommerce HPOS orders screen). Subclasses set $id, $name and $screen, and
 * implement do_action().
 *
 * $screen is a list-table screen id, e.g. 'edit-post', 'edit-shop_order' (legacy WooCommerce
 * orders), or 'woocommerce_page_wc-orders' (WooCommerce HPOS orders, the default order
 * storage since WooCommerce 8.2).
 *
 * Registration and nonce verification are both handled by WordPress core's
 * `handle_bulk_actions-{$screen}` filter, so this works the same way on every list-table
 * screen without screen-specific handling.
 */
abstract class BulkAction {
	/**
	 * Id of action, also a slug.
	 *
	 * @var string
	 */
	protected string $id = '';

	/**
	 * Name of action, to display in select.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Screen on which action can be run.
	 *
	 * @var string
	 */
	protected string $screen = 'edit-post';

	/**
	 * BulkAction constructor.
	 */
	public function __construct() {
		add_filter( 'bulk_actions-' . $this->screen, [ $this, 'add_action' ] );
		add_filter( 'handle_bulk_actions-' . $this->screen, [ $this, 'handle' ], 10, 3 );
	}

	/**
	 * Adds action to bulk action select.
	 *
	 * @param array $actions Current actions array.
	 *
	 * @return array
	 */
	public function add_action( array $actions ): array {
		$actions[ $this->id ] = $this->name;

		return $actions;
	}

	/**
	 * Handles the bulk action via WordPress core's `handle_bulk_actions-{$screen}` filter.
	 * Core verifies the nonce for this filter before calling it, so no manual check is needed here.
	 *
	 * @param string $redirect_url URL WordPress will redirect to afterwards.
	 * @param string $action_name The bulk action slug that was run.
	 * @param array $ids Post/term/order IDs the action was run on.
	 *
	 * @return string
	 */
	public function handle( string $redirect_url, string $action_name, array $ids ): string {
		if ( $action_name !== $this->id ) {
			return $redirect_url;
		}

		return $this->do_action( array_map( 'absint', $ids ), $redirect_url );
	}

	/**
	 * Do bulk action.
	 *
	 * @param array $ids Post/term/order IDs to act on.
	 * @param string $redirect_url URL WordPress will redirect to afterwards.
	 *
	 * @return string Redirect URL, optionally modified to carry a result (e.g. via add_query_arg()).
	 */
	abstract public function do_action( array $ids, string $redirect_url ): string;
}
