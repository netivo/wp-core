<?php

namespace Netivo\Module\Woocommerce\Widget;

use Automattic\WooCommerce\Internal\ProductAttributesLookup\Filterer;
use WC_Query;
use WP_Meta_Query;
use WP_Tax_Query;use WP_Term_Query;use WP_Widget;

class Filters extends WP_Widget {
	public function __construct() {
		parent::__construct( 'netivo_filters', 'Netivo - Filtry produktowe', [ 'description' => __( 'Wyświetla widget z możliwością filtrowania produktów.', 'netivo' ) ] );
	}

	/**
	 * Wyświetla formularz edycji widgetu.
	 *
	 * @param array $instance Instancja ustawień widgetu.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		$values = ( isset( $instance['filters'] ) ) ? $instance['filters'] : [];
        $form = (isset($instance['form'])) ? $instance['form'] : false;
        $always_show = (isset($instance['always_show'])) ? $instance['always_show'] : false;
		if ( function_exists( 'WC' ) ) {
			?>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'filters' ) ); ?>">Wybierz filtry, które chcesz
                    włączyć</label>
                <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'filters' ) ); ?>"
                        name="<?php echo esc_attr( $this->get_field_name( 'filters' ) ); ?>[]" multiple>
                    <option value="category" <?php echo ( ! empty( $values ) && in_array( 'category', $values ) ) ? 'selected' : ''; ?>>
                        Kategorie
                    </option>
                    <option value="availability" <?php echo ( ! empty( $values ) && in_array( 'availability', $values ) ) ? 'selected' : ''; ?>>
                        Dostępność
                    </option>
                    <option value="promotion" <?php echo ( ! empty( $values ) && in_array( 'promotion', $values ) ) ? 'selected' : ''; ?>>
                        Promocja
                    </option>
                    <option value="attributes" <?php echo ( ! empty( $values ) && in_array( 'attributes', $values ) ) ? 'selected' : ''; ?>>
                        Atrybuty
                    </option>
                    <option value="price" <?php echo ( ! empty( $values ) && in_array( 'price', $values ) ) ? 'selected' : ''; ?>>
                        Cena
                    </option>
                </select>
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'form' ) ); ?>">
                    <input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'form' ) ); ?>" value="1" <?php echo ($form) ? 'checked' : ''; ?>/> Zatwierdzanie przyciskiem
                </label>
            </p>
            <p>
                <label for="<?php echo esc_attr( $this->get_field_id( 'always_show' ) ); ?>">
                    <input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'always_show' ) ); ?>" value="1" <?php echo ($always_show) ? 'checked' : ''; ?>/> Zawsze pokazuj
                </label>
            </p>
			<?php
		} else {
			echo '<p>Aby włączyć filtry produktrowe, musisz mieć aktywną wtyczkę WooCommerce.</p>';
		}
	}

	/**
	 * Zapisuje ustawienia widgetu.
	 *
	 * @param array $new_instance Nowa instancja z ustawieniami widgetu.
	 * @param array $old_instance Stara instancja z ustawieniami widgetu.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance            = $old_instance;
		$instance['filters'] = $new_instance['filters'];
		$instance['form'] = ($new_instance['form'] == 1);
		$instance['always_show'] = ($new_instance['always_show'] == 1);

		return $instance;
	}

	/**
	 * Output widget.
	 *
	 * @param array $args Arguments.
	 * @param array $instance Widget instance.
	 *
	 * @see WP_Widget
	 *
	 */
	public function widget( $args, $instance ) {
		global $wp;
		if ( empty( $instance['filters'] ) ) {
			return;
		}

        $form = (!empty($instance['form']));
        $always_show = (!empty($instance['always_show']));


		$show_categories   = in_array( 'category', $instance['filters'] );
		$show_attributes   = in_array( 'attributes', $instance['filters'] );
		$show_availability = in_array( 'availability', $instance['filters'] );
		$show_promotion    = in_array( 'promotion', $instance['filters'] );
		$show_price        = in_array( 'price', $instance['filters'] );



		if ( ! $show_categories && ! $show_attributes && ! $show_availability && ! $show_promotion && !$show_price ) {
			return;
		}

		if ( ! function_exists( 'WC' ) ) {
			return;
		}

        if( ! $always_show ) {
            if ( ! is_shop() && ! is_product_taxonomy() && ! is_product_category() ) {
			    return;
		    }
        }

		?>
        <?php if($form) : ?>
            <form method="get" class="netivo-filters js-filter-box">
        <?php else : ?>
            <div class="netivo-filters js-filter-box">
        <?php endif; ?>
			<?php if ( $show_categories ) : ?>
				<?php $this->print_category_filter(); ?>
			<?php endif; ?>
			<?php if ( $show_availability ) : ?>
				<?php $this->print_availability_filter(); ?>
			<?php endif; ?>
			<?php if ( $show_promotion ) : ?>
				<?php $this->print_promotion_filter(); ?>
			<?php endif; ?>
			<?php if ( $show_attributes ) : ?>
				<?php $this->print_attributes_filter(); ?>
			<?php endif; ?>
			<?php if ( $show_price ) : ?>
				<?php $this->print_price_filter(); ?>
			<?php endif; ?>
		<?php if($form) : ?>
		    <?php if(!empty($_GET['s'])) : ?>
                <input type="hidden" name="s" value="<?= $_GET['s']; ?>"/>
            <?php endif; ?>
            <button type="submit" class="netivo-filters__button"><?php echo __('Filtruj', 'netivo'); ?></button>
            </form>
        <?php else : ?>
            </div>
        <?php endif; ?>
		<?php
	}

	public function print_category_filter() {
		$parent     = 0;
		$current_id = 0;
		$current    = null;
		$atr        = '';
		$atr_val    = '';
		if ( is_product_category() ) {
			$current    = get_queried_object();
			$current_id = $current->term_id;
			$parent     = $current->parent;
			$atr        = get_query_var( 'atr' );
			$atr_val    = get_query_var( 'atr_val' );
			if ( ! empty( $atr ) && ! empty( $atr_val ) ) {
				$parent = $current_id;
			}
		}

		$category_query = new WP_Term_Query( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'parent'     => $parent,
			'pad_counts' => false,
			'exclude'    => [ get_option( 'default_product_cat' ) ]
		] );
		$categories     = array();
		foreach ( $category_query->get_terms() as $category ) {
			$hide = get_term_meta( $category->term_id, '_hide_category', true );
			if ( empty( $hide ) ) {
				$count             = $this->get_term_count( $category->term_id );
				$tmp               = [
					'id'            => $category->term_id,
					'count'         => 0,
					'name'          => $category->name,
					'active'        => ( ! empty( $current ) && $current->term_id == $category->term_id ),
					'subcategories' => []
				];
				$subcategories     = [];
				$subcategory_query = new WP_Term_Query( [
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
					'parent'     => $category->term_id,
					'pad_counts' => false,
					'exclude'    => [ get_option( 'default_product_cat' ) ]
				] );
				foreach ( $subcategory_query->get_terms() as $subcategory ) {
					$sub_hide = get_term_meta( $subcategory->term_id, '_hide_category', true );
					if ( empty( $sub_hide ) ) {
						$stmp = [
							'id'    => $subcategory->term_id,
							'count' => $this->get_term_count( $subcategory->term_id ),
							'name'  => $subcategory->name,
						];
						if ( $stmp['count'] > 0 ) {
							$subcategories[] = $stmp;
						}
					}
				}
				$this->get_alternative_tree_for_category( $category, $subcategories, $atr, $atr_val );
				$tmp['subcategories'] = $subcategories;
				$tmp['count']         = $count;
				if ( $count > 0 ) {
					$categories[] = $tmp;
				}
			}
		}
		if ( ! empty( $parent ) ) {
			$pt = get_term( $parent );
			$this->get_alternative_tree_for_category( $pt, $categories, $atr, $atr_val );
		}
		if ( ! empty( $categories ) ) {
			wc_get_template( 'widget/filters-category.php', [ 'categories' => $categories, 'parent' => $parent ] );
		}
	}

	public function get_alternative_tree_for_category( $category, &$categories, $atr, $atr_val ): void {
		$av_atr = get_term_meta( $category->term_id, '_alternative_attributes', true );
		if ( ! empty( $av_atr ) ) {
			foreach ( $av_atr as $a => $t ) {
				if ( ! empty( $t['values'] ) ) {
					foreach ( $t['values'] as $k => $v ) {
						$tm = get_term_by( 'slug', $v['slug'], wc_attribute_taxonomy_name( $t['slug'] ) );
						if ( ! empty( $tm ) ) {
							$count = $this->get_term_count( $category->term_id, $tm->term_id );
							if ( $count > 0 ) {
								if ( ! empty( $v['alt_name'] ) ) {
									$name = $v['alt_name'];
								} else {
									$nm   = ( ! empty( $t['name'] ) ) ? $t['name'] : $t['slug'];
									$name = $category->term_name . $nm . ': ' . $v['name'];
								}
								$tmp = [
									'id'          => $category->term_id,
									'alternative' => $t['slug'] . ':' . $v['slug'],
									'count'       => $count,
									'name'        => $name,
									'active'      => false

								];
								if ( ! empty( $atr ) && ! empty( $atr_val ) ) {
									if ( $atr == $t['slug'] && $atr_val == $v['slug'] ) {
										$tmp['active'] = true;
									}
								}
								$categories[] = $tmp;
							}
						}
					}
				}
			}
		}
	}

	public function print_attributes_filter(): void {
		$enabled_filters = get_option( '_nt_filters', [] );

		if ( is_product_category() ) {
			$category = get_queried_object();
			$filters  = ( array_key_exists( $category->term_id, $enabled_filters ) ) ? $enabled_filters[ $category->term_id ] : [];
			if ( empty( $filters ) && $category->parent != 0 ) {
				$filters = ( array_key_exists( $category->parent, $enabled_filters ) ) ? $enabled_filters[ $category->parent ] : [];
			}
			if ( empty( $filters ) && ! empty( $_GEt['s'] ) ) {
				$filters = ( array_key_exists( 'search', $enabled_filters ) ) ? $enabled_filters['search'] : [];
			}
			$atr     = get_query_var( 'atr' );
			$atr_val = get_query_var( 'atr_val' );
			if ( ! empty( $atr ) && ! empty( $atr_val ) ) {
				$term = get_term_by( 'slug', $atr_val, wc_attribute_taxonomy_name( $atr ) );
				if ( ! empty( $term ) ) {
					$id = array_search( $atr, $filters );
					if ( $id !== false ) {
						unset( $filters[ $id ] );
					}
				}
			}
		} else {
			if ( ! empty( $_GET['s'] ) ) {
				$filters = ( array_key_exists( 'search', $enabled_filters ) ) ? $enabled_filters['search'] : [];
			}
		}

		if ( ! empty( $filters ) ) {
			$final_filters = [];
			foreach ( $filters as $filter ) {
				$tmp = $this->get_attribute_filter( $filter );
				if ( ! empty( $tmp ) ) {
					$final_filters[] = $tmp;
				}
			}
			if ( ! empty( $final_filters ) ) {
				wc_get_template( 'widget/filters-attributes.php', [ 'filters' => $final_filters ] );
			}
		}
	}

	public function get_attribute_filter( $attribute ): ?array {
		$attribute_array      = array();
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $tax ) {
				if ( taxonomy_exists( wc_attribute_taxonomy_name( $tax->attribute_name ) ) ) {
					$attribute_array[ $tax->attribute_name ] = $tax->attribute_label;
				}
			}
		}

		$taxonomy = wc_attribute_taxonomy_name( $attribute );
		$terms    = get_terms( $taxonomy, [
			'hide_empty' => '1',
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		$term_counts = $this->get_filtered_term_product_counts( wp_list_pluck( $terms, 'term_id' ), $taxonomy, 'or' );

		$_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes();

		$result_array            = [];
		$tmp_option_count        = 0;
		$tmp_option_active_count = 0;
		$tmp_options_active      = [];
		$tmp_options_all         = [];

		$current_values = isset( $_chosen_attributes[ $taxonomy ]['terms'] ) ? $_chosen_attributes[ $taxonomy ]['terms'] : array();
		$filter_name    = 'filter_' . str_replace( 'pa_', '', $taxonomy );

		arsort( $term_counts );

		$slice = ( count( $current_values ) < 5 ) ? 5 - count( $current_values ) : 3;
		$maxes = array_slice( $term_counts, 0, $slice, true );

		foreach ( $terms as $term ) {

			$option_is_set = in_array( $term->slug, $current_values, true );

			$current_filter = isset( $_GET[ $filter_name ] ) ? explode( ',', wc_clean( wp_unslash( $_GET[ $filter_name ] ) ) ) : array(); // WPCS: input var ok, CSRF ok.
			$current_filter = array_map( 'sanitize_title', $current_filter );

			if ( ! in_array( $term->slug, $current_filter, true ) ) {
				$current_filter[] = $term->slug;
			}

			$link = remove_query_arg( $filter_name, $this->get_current_page_url() );

			// Add current filters to URL.
			foreach ( $current_filter as $key => $value ) {
				// Exclude query arg for current term archive term.
				if ( $value === $this->get_current_term_slug() ) {
					unset( $current_filter[ $key ] );
				}

				// Exclude self so filter can be unset on click.
				if ( $option_is_set && $value === $term->slug ) {
					unset( $current_filter[ $key ] );
				}
			}

			if ( ! empty( $current_filter ) ) {
				asort( $current_filter );
				$link = add_query_arg( $filter_name, implode( ',', $current_filter ), $link );

				// Add Query type Arg to URL.
				if ( ! ( 1 === count( $current_filter ) && $option_is_set ) ) {
					$link = add_query_arg( 'query_type_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) ), 'or', $link );
				}
				$link = str_replace( '%2C', ',', $link );
			}
			$link = esc_url( apply_filters( 'woocommerce_layered_nav_link', $link, $term, $taxonomy ) );

			$tmp_option = [];

			if ( array_key_exists( $term->term_id, $term_counts ) && $term_counts[ $term->term_id ] > 0 ) {
				$tmp_option_count ++;

				$tmp_option['id']     = $term->term_id;
				$tmp_option['slug']   = $term->slug;
				$tmp_option['link']   = $link;
				$tmp_option['is_set'] = $option_is_set;
				$tmp_option['name']   = $term->name;
				$tmp_option['count']  = $term_counts[ $term->term_id ];
				$tmp_option['active'] = ( $option_is_set || array_key_exists( $term->term_id, $maxes ) );

				$tmp_options_all[] = $tmp_option;

				if ( $option_is_set ) {
					$tmp_option_active_count ++;
					$tmp_options_active[] = $tmp_option;
				}
			}
		}
		$result_array['filter_name']         = $attribute_array[ $attribute ];
		$result_array['input_name']          = $filter_name;
		$result_array['taxonomy']            = $taxonomy;
		$result_array['filter_option_count'] = $tmp_option_count;
		$result_array['count_show']          = ( $tmp_option_active_count < 5 ) ? 5 : 8;
		$result_array['options_active']      = $tmp_options_active;
		$result_array['options_all']         = $tmp_options_all;

		if ( ! empty( $result_array['filter_option_count'] ) ) {
			return $result_array;
		}

		return null;
	}

	public function print_availability_filter(): void {

		$availabilities = [
			'yes' => 'Dostępne',
			'no'  => 'Niedostępne'
		];

		$options = [];

		foreach ( $availabilities as $key => $value ) {
			$cf   = ! empty( $_GET['filter_availability'] ) ? explode( ',', wc_clean( wp_unslash( $_GET['filter_availability'] ) ) ) : array();
			$link = remove_query_arg( 'filter_availability', $this->get_current_page_url() );

			$is_set = false;
			if ( in_array( $key, $cf ) ) {
				$is_set = true;
			}

			if ( ! in_array( $key, $cf ) ) {
				$cf[] = $key;
			}

			foreach ( $cf as $k => $v ) {
				if ( $is_set && $key == $v ) {
					unset( $cf[ $k ] );
				}
			}
			if ( ! empty( $cf ) ) {
				$link = add_query_arg( 'filter_availability', implode( ',', $cf ), $link );
			}

			$option    = [
				'id'     => $key,
				'name'   => $value,
				'link'   => $link,
				'active' => $is_set
			];
			$options[] = $option;
		}

        $options = apply_filters('netivo/widget/filters/availability-options', $options);

		wc_get_template( 'widget/filters-availability.php', [ 'filters' => $options ] );
	}

	public function print_promotion_filter(): void {

		$promotions = [
			'yes' => 'Tak',
			'no'  => 'Nie'
		];

		$options = [];

		foreach ( $promotions as $key => $value ) {
			$cf   = ! empty( $_GET['filter_promotion'] ) ? explode( ',', wc_clean( wp_unslash( $_GET['filter_promotion'] ) ) ) : array();
			$link = remove_query_arg( 'filter_promotion', $this->get_current_page_url() );

			$is_set = false;
			if ( in_array( $key, $cf ) ) {
				$is_set = true;
			}

			if ( ! in_array( $key, $cf ) ) {
				$cf[] = $key;
			}

			foreach ( $cf as $k => $v ) {
				if ( $is_set && $key == $v ) {
					unset( $cf[ $k ] );
				}
			}
			if ( ! empty( $cf ) ) {
				$link = add_query_arg( 'filter_promotion', implode( ',', $cf ), $link );
			}

			$option    = [
				'id'     => $key,
				'name'   => $value,
				'link'   => $link,
				'active' => $is_set
			];
			$options[] = $option;
		}

		wc_get_template( 'widget/filters-promotion.php', [ 'filters' => $options ] );
	}

    public function print_price_filter(): void {
        $prices = $this->get_filtered_price();
        $min    = floor( $prices->min_price );
        $max    = ceil( $prices->max_price );

        $min_price = isset( $_GET['min_price'] ) ? wc_clean( wp_unslash( $_GET['min_price'] ) ) : apply_filters( 'woocommerce_price_filter_widget_min_amount', $min ); // WPCS: input var ok, CSRF ok.
        $max_price = isset( $_GET['max_price'] ) ? wc_clean( wp_unslash( $_GET['max_price'] ) ) : apply_filters( 'woocommerce_price_filter_widget_max_amount', $max ); // WPCS: input var ok, CSRF ok.

        $ignore = ['min_price', 'max_price'];
        foreach(get_option('_nt_attributes', []) as $att){
            $ignore[] = 'query_type_'.$att;
            $ignore[] = 'filter_'.$att;
            $ignore[] = 'min_'.$att;
            $ignore[] = 'max_'.$att;
        }

        $min_price_val = esc_attr( $min_price );
        $max_price_val = esc_attr( $max_price );

        $min_val = esc_attr( apply_filters( 'woocommerce_price_filter_widget_min_amount', $min ) );
        $max_val = esc_attr( apply_filters( 'woocommerce_price_filter_widget_max_amount', $max ) );

        $options = array(
            'min' => $min,
            'max' => $max,
            'min_price' => $min_price,
            'max_price' => $max_price,
            'min_price_val' => $min_price_val,
            'max_price_val' => $max_price_val,
            'min_val' => $min_val,
            'max_val' => $max_val
        );

        wc_get_template( 'widget/filters-price.php', [ 'options' => $options ] );
    }

	protected function get_filtered_term_product_counts( $term_ids, $taxonomy, $query_type ) {
		return wc_get_container()->get( Filterer::class )->get_filtered_term_product_counts( $term_ids, $taxonomy, $query_type );
	}

	protected function get_term_count( $term_id, $alt_id = null ): int {
		global $wpdb, $wp_query;

		$children = get_term_children( $term_id, 'product_cat' );
		if ( empty( $children ) ) {
			$children = [ $term_id ];
		} else {
			$children[] = $term_id;
		}

        $is_man = false;
		if ( is_product_category() ) {
			$kpr = get_query_var('kpr');
			if(!empty($kpr)){
				$term = get_term_by('slug', $kpr, 'pa_producent');
				if(!empty($term)){
					$is_man = true;
					$man_id = $term->term_id;
				}
			}
		} elseif (is_tax('pa_producent')){
            $is_man = true;
            $man_id = get_queried_object_id();
        }


        if( ! empty( WC_Query::get_main_query() ) ) {
            $meta_query = WC_Query::get_main_meta_query();
        } else {
            $meta_query = false;
        }
		$meta_query     = new WP_Meta_Query( $meta_query );
		$meta_query_sql = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );

		$query           = array();
		$query['select'] = "SELECT {$wpdb->posts}.ID";
		$query['from']   = "FROM {$wpdb->posts}";
		$query['join']   = "LEFT JOIN {$wpdb->term_relationships} AS term_relationships ON {$wpdb->posts}.ID = term_relationships.object_id ";


		$query['where'] = "WHERE {$wpdb->posts}.post_type IN ( 'product' ) 
		                   AND {$wpdb->posts}.post_status = 'publish' 
		                   AND term_relationships.term_taxonomy_id IN (" . implode( ',', $children ) . ") ";

        if( $is_man ) {
            $query['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS term_relationships_manufacturer ON {$wpdb->posts}.ID = term_relationships_manufacturer.object_id ";
			$query['where'] .= " AND term_relationships_manufacturer.term_taxonomy_id IN (".$man_id.") ";
        }

		if ( ! empty( $alt_id ) ) {
			$query['join']  .= "LEFT JOIN {$wpdb->term_relationships} AS term_relationships_alt ON {$wpdb->posts}.ID = term_relationships_alt.object_id ";
			$query['where'] .= "AND term_relationships_alt.term_taxonomy_id IN ({$alt_id}) ";
		}
		$query['join']  .= $meta_query_sql['join'];
		$query['where'] .= $meta_query_sql['where'];

		$query['group_by'] = "GROUP BY {$wpdb->posts}.ID";

		if ( ! empty( $_GET['s'] ) ) {
			$search = $this->parse_search( $wp_query->query_vars );
			if ( $search ) {
				$query['where'] .= $search;
			}
		}

		$query = implode( ' ', $query );

		$results = $wpdb->get_results( $query, ARRAY_A );

		return count( $results );
	}

	/**
	 * Generates SQL for the WHERE clause based on passed search terms.
	 *
	 * @param array $q Query variables.
	 *
	 * @return string WHERE clause.
	 * @since 3.7.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 */
	protected function parse_search( &$q ): string {
		global $wpdb;

		$search = '';

		// added slashes screw with quote grouping when done early, so done later
		$q['s'] = stripslashes( $q['s'] );
		// there are no line breaks in <input /> fields
		$q['s']                  = str_replace( array( "\r", "\n" ), '', $q['s'] );
		$q['search_terms_count'] = 1;
		if ( ! empty( $q['sentence'] ) ) {
			$q['search_terms'] = array( $q['s'] );
		} else {
			if ( preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $q['s'], $matches ) ) {
				$q['search_terms_count'] = count( $matches[0] );
				$q['search_terms']       = $this->parse_search_terms( $matches[0] );
				// if the search string has only short terms or stopwords, or is 10+ terms long, match it as sentence
				if ( empty( $q['search_terms'] ) || count( $q['search_terms'] ) > 9 ) {
					$q['search_terms'] = array( $q['s'] );
				}
			} else {
				$q['search_terms'] = array( $q['s'] );
			}
		}

		$n                         = ! empty( $q['exact'] ) ? '' : '%';
		$searchand                 = '';
		$q['search_orderby_title'] = array();

		/**
		 * Filters the prefix that indicates that a search term should be excluded from results.
		 *
		 * @param string $exclusion_prefix The prefix. Default '-'. Returning
		 *                                 an empty value disables exclusions.
		 *
		 * @since 4.7.0
		 *
		 */
		$exclusion_prefix = apply_filters( 'wp_query_search_exclusion_prefix', '-' );

		foreach ( $q['search_terms'] as $term ) {
			// If there is an $exclusion_prefix, terms prefixed with it should be excluded.
			$exclude = $exclusion_prefix && ( $exclusion_prefix === substr( $term, 0, 1 ) );
			if ( $exclude ) {
				$like_op  = 'NOT LIKE';
				$andor_op = 'AND';
				$term     = substr( $term, 1 );
			} else {
				$like_op  = 'LIKE';
				$andor_op = 'OR';
			}

			if ( $n && ! $exclude ) {
				$like                        = '%' . $wpdb->esc_like( $term ) . '%';
				$q['search_orderby_title'][] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like );
			}

			$like      = $n . $wpdb->esc_like( $term ) . $n;
			$search    .= $wpdb->prepare( "{$searchand}(({$wpdb->posts}.post_title $like_op %s) $andor_op ({$wpdb->posts}.post_excerpt $like_op %s) $andor_op ({$wpdb->posts}.post_content $like_op %s))", $like, $like, $like );
			$searchand = ' AND ';
		}

		if ( ! empty( $search ) ) {
			$search = " AND ({$search}) ";
			if ( ! is_user_logged_in() ) {
				$search .= " AND ({$wpdb->posts}.post_password = '') ";
			}
		}

		return $search;
	}

	/**
	 * Check if the terms are suitable for searching.
	 *
	 * Uses an array of stopwords (terms) that are excluded from the separate
	 * term matching when searching for posts. The list of English stopwords is
	 * the approximate search engines list, and is translatable.
	 *
	 * @param array $terms Terms to check.
	 *
	 * @return array Terms that are not stopwords.
	 * @since 3.7.0
	 *
	 */
	protected function parse_search_terms( $terms ): array {
		$strtolower = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
		$checked    = array();

		$stopwords = $this->get_search_stopwords();

		foreach ( $terms as $term ) {
			// keep before/after spaces when term is for exact match
			if ( preg_match( '/^".+"$/', $term ) ) {
				$term = trim( $term, "\"'" );
			} else {
				$term = trim( $term, "\"' " );
			}

			// Avoid single A-Z and single dashes.
			if ( ! $term || ( 1 === strlen( $term ) && preg_match( '/^[a-z\-]$/i', $term ) ) ) {
				continue;
			}

			if ( in_array( strtolower( $term ), $stopwords, true ) ) {
				continue;
			}

			$checked[] = $term;
		}

		return $checked;
	}

	/**
	 * Retrieve stopwords used when parsing search terms.
	 *
	 * @return array Stopwords.
	 * @since 3.7.0
	 *
	 */
	protected function get_search_stopwords(): array {
		/* translators: This is a comma-separated list of very common words that should be excluded from a search,
		 * like a, an, and the. These are usually called "stopwords". You should not simply translate these individual
		 * words into your language. Instead, look for and provide commonly accepted stopwords in your language.
		 */
		$words = explode( ',', _x( 'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www',
			'Comma-separated list of search stopwords in your language' ) );

		$stopwords = array();
		foreach ( $words as $word ) {
			$word = trim( $word, "\r\n\t " );
			if ( $word ) {
				$stopwords[] = $word;
			}
		}

		return $stopwords;
	}

	/**
	 * Get current page URL with various filtering props supported by WC.
	 *
	 * @return string
	 * @since  3.3.0
	 */
	protected function get_current_page_url(): string {
		if ( defined( 'SHOP_IS_ON_FRONT' ) ) {
			$link = home_url();
		} elseif ( is_shop() ) {
			$link = get_permalink( wc_get_page_id( 'shop' ) );
		} elseif ( is_product_category() ) {
			$link    = get_term_link( get_query_var( 'product_cat' ), 'product_cat' );
			$atr     = get_query_var( 'atr' );
			$atr_val = get_query_var( 'atr_val' );
			if ( ! empty( $atr ) && ! empty( $atr_val ) ) {
				$term = get_term_by( 'slug', $atr_val, wc_attribute_taxonomy_name( $atr ) );
				if ( ! empty( $term ) ) {
					$link .= '/' . $atr . ':' . $atr_val . '/';
				}
			}
		} elseif ( is_product_tag() ) {
			$link = get_term_link( get_query_var( 'product_tag' ), 'product_tag' );
		} else {
			$queried_object = get_queried_object();
			$link           = get_term_link( $queried_object->slug, $queried_object->taxonomy );
		}

		// Min/Max.
		if ( isset( $_GET['min_price'] ) ) {
			$link = add_query_arg( 'min_price', wc_clean( wp_unslash( $_GET['min_price'] ) ), $link );
		}

		if ( isset( $_GET['max_price'] ) ) {
			$link = add_query_arg( 'max_price', wc_clean( wp_unslash( $_GET['max_price'] ) ), $link );
		}

		// Order by.
		if ( isset( $_GET['orderby'] ) ) {
			$link = add_query_arg( 'orderby', wc_clean( wp_unslash( $_GET['orderby'] ) ), $link );
		}

		/**
		 * Search Arg.
		 * To support quote characters, first they are decoded from &quot; entities, then URL encoded.
		 */
		if ( get_search_query() ) {
			$link = add_query_arg( 's', rawurlencode( htmlspecialchars_decode( get_search_query() ) ), $link );
		}

		// Post Type Arg.
		if ( isset( $_GET['post_type'] ) ) {
			$link = add_query_arg( 'post_type', wc_clean( wp_unslash( $_GET['post_type'] ) ), $link );
		}

		// Min Rating Arg.
		if ( isset( $_GET['rating_filter'] ) ) {
			$link = add_query_arg( 'rating_filter', wc_clean( wp_unslash( $_GET['rating_filter'] ) ), $link );
		}

		if ( isset( $_GET['filter_availability'] ) ) {
			$link = add_query_arg( 'filter_availability', wc_clean( wp_unslash( $_GET['filter_availability'] ) ), $link );
		}
		if ( isset( $_GET['filter_promotion'] ) ) {
			$link = add_query_arg( 'filter_promotion', wc_clean( wp_unslash( $_GET['filter_promotion'] ) ), $link );
		}

		// All current filters.
		if ( $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes() ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found, WordPress.CodeAnalysis.AssignmentInCondition.Found
			foreach ( $_chosen_attributes as $name => $data ) {
				$filter_name = sanitize_title( str_replace( 'pa_', '', $name ) );
				if ( ! empty( $data['terms'] ) ) {
					$link = add_query_arg( 'filter_' . $filter_name, implode( ',', $data['terms'] ), $link );
				}
				if ( 'or' === $data['query_type'] ) {
					$link = add_query_arg( 'query_type_' . $filter_name, 'or', $link );
				}
			}
		}

		return $link;
	}

    protected function get_filtered_price() {
		global $wpdb;

        $args       = WC()->query->get_main_query()->query_vars;
        $tax_query  = isset( $args['tax_query'] ) ? $args['tax_query'] : array();
        $meta_query = isset( $args['meta_query'] ) ? $args['meta_query'] : array();

        if ( ! is_post_type_archive( 'product' ) && ! empty( $args['taxonomy'] ) && ! empty( $args['term'] ) ) {
            $tax_query[] = WC()->query->get_main_tax_query();
        }

        foreach ( $meta_query + $tax_query as $key => $query ) {
            if ( ! empty( $query['price_filter'] ) || ! empty( $query['rating_filter'] ) ) {
                unset( $meta_query[ $key ] );
            }
        }

        $meta_query = new WP_Meta_Query( $meta_query );
        $tax_query  = new WP_Tax_Query( $tax_query );
        $search     = WC_Query::get_main_search_query_sql();

        $meta_query_sql   = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
        $tax_query_sql    = $tax_query->get_sql( $wpdb->posts, 'ID' );
        $search_query_sql = $search ? ' AND ' . $search : '';

        $sql = "
			SELECT min( min_price ) as min_price, MAX( max_price ) as max_price
			FROM {$wpdb->wc_product_meta_lookup}
			WHERE product_id IN (
				SELECT ID FROM {$wpdb->posts}
				" . $tax_query_sql['join'] . $meta_query_sql['join'] . "
				WHERE {$wpdb->posts}.post_type IN ('" . implode( "','", array_map( 'esc_sql', apply_filters( 'woocommerce_price_filter_post_type', array( 'product' ) ) ) ) . "')
				AND {$wpdb->posts}.post_status = 'publish'
				" . $tax_query_sql['where'] . $meta_query_sql['where'] . $search_query_sql . '
			)';

        $sql = apply_filters( 'woocommerce_price_filter_sql', $sql, $meta_query_sql, $tax_query_sql );

        return $wpdb->get_row( $sql ); // WPCS: unprepared SQL ok.
	}

	/**
	 * Return the currently viewed term slug.
	 *
	 * @return int|string
	 */
	protected function get_current_term_slug(): int|string {
		return is_tax() ? get_queried_object()->slug : 0;
	}

}