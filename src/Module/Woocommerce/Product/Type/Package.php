<?php
/**
 * Created by Netivo for wp-core
 * User: manveru
 * Date: 12.08.2024
 * Time: 17:25
 *
 */

namespace Netivo\Module\Woocommerce\Product\Type;

use Override;
use WC_Product_Simple;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Product type class Package
 */
class Package extends WC_Product_Simple {
	/**
	 * Registers new product type.
	 */
	public static function register(): void {

		add_filter( 'woocommerce_product_class', [ self::class, 'product_class' ], 10, 4 );
		add_action( 'woocommerce_package_add_to_cart', [ self::class, 'add_to_cart' ] );

		add_filter( 'woocommerce_product_get_price', [ self::class, 'change_price' ], 10, 2 );
		add_filter( 'woocommerce_product_get_regular_price', [ self::class, 'change_price' ], 10, 2 );
		add_filter( 'woocommerce_product_get_sale_price', [ self::class, 'change_price' ], 10, 2 );

		add_action( 'woocommerce_checkout_create_order', [ self::class, 'save_order' ] );
		add_filter( 'product_type_selector', [ self::class, 'add_to_select' ] );

		if ( is_admin() ) {
			add_filter('woocommerce_product_data_tabs', [self::class, 'add_tab']);
			add_action('woocommerce_product_data_panels', [self::class, 'display']);
			add_action( 'save_post', [ self::class, 'do_save' ] );

			add_filter( 'woocommerce_product_data_tabs', [ self::class, 'modify_pricing_tab' ] );
			add_action( 'admin_footer', [ self::class, 'admin_footer_js' ] );
		}

	}

	/**
	 * Add product type to select.
	 *
	 * @param array $types Types of the products.
	 *
	 * @return array
	 */
	public static function add_to_select( array $types ): array {
		$types['package'] = __( 'Zestaw', 'elazienki' );

		return $types;
	}

	/**
	 * Add classes to special tabs.
	 *
	 * @param array $tabs Current tabs in product data metabox.
	 *
	 * @return array
	 */
	public static function modify_pricing_tab( array $tabs ): array {
		$tabs['inventory']['class'][] = 'show_if_package';

		return $tabs;
	}

	/**
	 * Sets the class name for product type.
	 *
	 * @param string $class_name   Generated class name for type.
	 * @param string $product_type Product type.
	 * @param string $variation    Is product a variation.
	 * @param string $product_id   Product id.
	 *
	 * @return string
	 */
	public static function product_class( string $class_name, string $product_type, string $variation, string $product_id ): string {
		if ( $product_type === 'package' ) {
			return self::class;
		}

		return $class_name;
	}

	/**
	 * Add script to enable pricing in product type.
	 */
	public static function admin_footer_js(): void {
		if ( 'product' != get_post_type() ) :
			return;
		endif;

		?>
		<script type='text/javascript'>
            jQuery('.package_options').addClass('show_if_package');
            jQuery('.options_group.pricing').addClass('show_if_package');
            jQuery('.form-field._manage_stock_field').addClass('show_if_package');
		</script><?php

	}

	/**
	 * Adds tab to product data metabox.
	 *
	 * @param array $tabs Current tabs in product data metabox.
	 *
	 * @return array
	 */
	public static function add_tab( array $tabs): array {
		$tabs['package'] = array(
			'label'    => __('Ustawienia zestawu', 'netivo'),
			'target'   => 'nt_package_product_data',
			'class'    => array( '' ),
			'priority' => 15,
		);
		return $tabs;
	}

	/**
	 * Displays the tab content.
	 *
	 * @throws \Exception When error.
	 */
	public static function display(): void {
		global $post, $thepostid, $product_object;
		$filename = __DIR__ . '/../../../../../views/woocommerce/product/type/package.phtml';

		if ( file_exists( $filename ) ) {
			echo '<div id="nt_package_product_data" class="panel woocommerce_options_panel">';
			include $filename;
			echo '</div>';
		} else {
			throw new \Exception( "There is no view file for this admin action" );
		}

	}

	/**
	 * Start saving process of the metabox.
	 *
	 * @param int $post_id Id of the saved post.
	 *
	 * @return mixed
	 */
	public static function do_save( int $post_id ): mixed {
		if ( ! isset( $_POST[ 'product_package_tab_nonce' ] ) ) {
			return $post_id;
		}
		if ( ! wp_verify_nonce( $_POST[ 'product_package_tab_nonce' ], 'save_product_package_tab' ) ) {
			return $post_id;
		}
		if ( ! in_array( $_POST['post_type'], ['product'] ) ) {
			return $post_id;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		$products = $_POST['_package_products_field'];

		delete_post_meta($post_id, '_nt_package_product');
		if(!empty($products)){
			foreach ($products as $product => $amount){
				add_post_meta($post_id, '_nt_package_product', array('product' => $product, 'amount' => $amount));
			}
		}
	}

	/**
	 * Add to cart view for package product type.
	 */
	public static function add_to_cart(): void {
		wc_get_template( 'single-product/add-to-cart/simple.php' );
	}

	/**
	 * Gets the price for product.
	 *
	 * @param float $price   Price to get from.
	 * @param \WC_Product $product Product to get price from.
	 *
	 * @return float
	 */
	public static function change_price( float $price, \WC_Product $product ): float|int {
		if ( $product->get_type() == 'package' && ! (is_admin() && !wp_doing_ajax()) ) {
			if(!empty($price)) return $price;
			$pr_price = 0;
			$package_products = $product->get_meta('_nt_package_product', false);
			if(!empty($package_products)){
				foreach($package_products as $package_product){
					$dp = $package_product->value;
					$pr = wc_get_product($dp['product']);
					if(!empty($pr)){
						$pr_price += $pr->get_price('normal') * (float)$dp['amount'];
					}
				}
			}
			return $pr_price;
		}
		return $price;
	}

	/**
	 * Saves pickup place when shipping method is local pickup.
	 *
	 * @param \WC_Order $order Order to save.
	 */
	public static function save_order( $order ): void {
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_type() == 'line_item' ) {
				/**
				 * @var $item \WC_Order_Item_Product
				 */
				$prod = $item->get_product();
				if ( $prod->get_type() == 'package' ) {
					$proportions = [];
					$summed_price = self::change_price(0, $prod);
					$package_products = $prod->get_package_products();
					foreach($package_products as $package_product){
						$prop = $package_product['product']->get_price() / $summed_price;
						$proportions[$package_product['product']->get_id()] = $package_product['product']->get_price() / $summed_price;

					}
					$item->add_meta_data('_nt_package_proportions', $proportions);
					$item->save_meta_data();
				}
			}
		}
	}


	/**
	 * Meters constructor.
	 *
	 * @param string $product Product.
	 */
	public function __construct( $product ) {
		parent::__construct( $product );
	}

	/**
	 * Gets the product type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'package';
	}

	public function is_purchasable(): true {
		return true;
	}

	#[Override] public function get_stock_quantity( $context = 'view' ): mixed {
		$qty = $this->get_prop('stock_quantity', $context);
		if($qty > 0 || is_admin()) return $qty;
		$qty = null;
		$package_products = $this->get_meta('_nt_package_product', false);
		if(!empty($package_products)){
			foreach($package_products as $package_product){
				$dp = $package_product->value;
				$pr = wc_get_product($dp['product']);
				if(!empty($pr)){
					$pqty = $pr->get_stock_quantity();
					$pqty = (empty($pqty)) ? 0 : $pqty;
					if($qty === null || $pqty < $qty) $qty = $pqty/(float)$dp['amount'];
				}
			}
		}
		if($qty < 0) return 0;
		return $qty;
	}

	public function get_package_products(): array {
		$products = [];
		$package_products = $this->get_meta('_nt_package_product', false);
		if(!empty($package_products)) {
			foreach ( $package_products as $package_product ) {
				$dp = $package_product->value;
				$pr = wc_get_product($dp['product']);
				if(!empty($pr)){
					$products[] = [
						'product' => $pr,
						'amount' => (float)$dp['amount']
					];
				}
			}
		}
		return $products;
	}
}