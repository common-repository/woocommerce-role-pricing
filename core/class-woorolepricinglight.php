<?php
/**
 * class-woorolepricinglight.php
 *
 * Copyright (c) Antonio Blanco http://www.blancoleon.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author Antonio Blanco
 * @package woorolepricinglight
 * @since woorolepricing 1.0.0
 */

/**
 * WooRolePricingLight class
 */
class WooRolePricingLight {

	public static function init() {
		global $woocommerce;

		if ( version_compare( $woocommerce->version, '3.0.0', '>=' ) ) {
			add_filter('woocommerce_product_get_price', array( __CLASS__, 'woocommerce_get_price' ), 10, 2);
		} else {
			add_filter('woocommerce_get_price', array( __CLASS__, 'woocommerce_get_price' ), 10, 2);
		}

		// Cart 3.x
		if ( apply_filters( 'wrp_apply_woocommerce_cart_product_price', false ) ) {
			add_filter('woocommerce_cart_product_price', array( __CLASS__, 'wc_price_woocommerce_get_price' ), 10, 2);
		}
		add_filter('woocommerce_product_variation_get_price', array( __CLASS__, 'woocommerce_get_price' ), 10, 2);

		add_filter( 'woocommerce_get_price_html', array(__CLASS__,'woocommerce_get_price_html'), 100, 2 );
		
		// Hide Add To Cart button ?
		if ( !is_user_logged_in() && get_option( "wrp-hideprice-guest", false ) ) {
		    add_filter('woocommerce_is_purchasable', '__return_false');
		}

	}

	public static function wc_price_woocommerce_get_price ( $price, $product ) {
		return wc_price( self::woocommerce_get_price( $price, $product ) );
	}

	public static function woocommerce_get_price ( $price, $product ) {
		global $post, $woocommerce;

		$baseprice = $price;
		$result = $baseprice;

		if ( ( ( $post == null ) || !is_admin() ) ) {

			$commission = null;
			if ( $product->is_type( 'variation' ) ) {
				if ( version_compare( $woocommerce->version, '3.0.0', '>=' ) ) {
					$commission = WRP_Variations_Admin::get_commission( $product, $product->get_id() );
				} else {
					$commission = WRP_Variations_Admin::get_commission( $product, $product->variation_id );
				}
			} else {
				$commission = self::get_commission( $product );
			}

			if ( $commission !== null ) {

				//$baseprice = $product->get_regular_price();
				// get_price( $context ) We need to set 'edit', so the woocommerce_product_get_price filter is not hooked
				//if ( version_compare( $woocommerce->version, '3.0.0', '>=' ) ) {
				//    error_log( $product );
				//	$product_get_price = $product->get_price( 'edit' );
				//} else {
				//	$product_get_price = $product->price;
				//}
				/*
				if ( $product->get_sale_price() != $product->get_regular_price() && $product->get_sale_price() == $product_get_price ) {
				    error_log( "is on sale");
					if ( get_option( "wrp-baseprice", "regular" )=="sale" ) {
					    error_log( " aplicamos sobre SALE");
						$baseprice = $product->get_sale_price();
					}
				}
				*/
				
			    $product_get_price = $product->get_price( 'edit' );
				$baseprice = $product->get_regular_price('edit');
				if ( get_option( "wrp-baseprice", "regular" )=="sale" ) { // We apply on sale price if is on sale
				    if ( $product->get_sale_price() != $product->get_regular_price() && $product->get_sale_price() == $product_get_price ) {
				        $baseprice = $product->get_sale_price();
				    }
				}
				
				$product_price = $baseprice;

				$type = get_option( "wrp-method", "rate" );
				$result = 0;
				if ($type == "rate") {
					// if rate and price includes taxes
					if ( $product->is_taxable() && get_option('woocommerce_prices_include_tax') == 'yes' ) {
						$_tax       = new WC_Tax();
						$tax_rates  = $_tax->get_shop_base_rate( $product->get_tax_class() );
						$taxes      = $_tax->calc_tax( $baseprice, $tax_rates, true );
						$product_price      = $_tax->round( $baseprice - array_sum( $taxes ) );
					}

					$result = self::bcmul($product_price, $commission, WOO_ROLE_PRICING_LIGHT_DECIMALS);

					if ( $product->is_taxable() && get_option('woocommerce_prices_include_tax') == 'yes' ) {
						$_tax       = new WC_Tax();
						$tax_rates  = $_tax->get_shop_base_rate( $product->get_tax_class() );
						$taxes      = $_tax->calc_tax( $result, $tax_rates, false ); // important false
						$result      = $_tax->round( $result + array_sum( $taxes ) );
					}
				} else {
					if ( get_option( "wrp-haveset", "discounts" ) === 'discounts' ) {
						$result = self::bcsub($product_price, $commission, WOO_ROLE_PRICING_LIGHT_DECIMALS);
					} else {
						$result = $commission;
					}
				}
			}
		}
		return $result;
	}

	// extra functions

	public static function get_commission ( $product ) {
		global $post, $woocommerce;

		$discount = null;

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$user_roles = $user->roles;
			$user_role = array_shift($user_roles);

			if ( $user_role !== null ) {
				if ( get_option( "wrp-" . $user_role, "-1" ) !== "-1" ) {
					$discount = get_option( "wrp-" . $user_role );
				}
			}
		} else {
		    // Not logged in
		    if ( get_option( "wrp-guest", "-1" ) !== "-1" ) {
		        $discount = get_option( "wrp-guest" );
		    }
		}

		if ( $discount !== null ) {
			$method = get_option( "wrp-method", "rate" );
			if ( $method == "rate" ) {
				if ( get_option( "wrp-haveset", "discounts" ) === 'discounts' ) {
					$discount = self::bcsub ( 1, $discount, WOO_ROLE_PRICING_LIGHT_DECIMALS );
				}
			}
		}

		return $discount;
	}

	/**
	 * Display the original and discounted prices.
	 * @param float $price
	 * @param Product $product
	 * @return String
	 */
	public static function woocommerce_get_price_html( $price, $product ){
		global $post;
		$result = $price;

		// if enable 'wrp-hideprice-guest' and is guest
		if ( !is_user_logged_in() && get_option( "wrp-hideprice-guest", false ) ) {
		    return '';
		}
		
		if ( is_user_logged_in() && !is_admin() ) {

			if ( get_option( "wrp-baseprice", "regular" ) == "sale" ) {
				$price_key = '_price';
			} else {
				$price_key = '_regular_price';
			}

			if ( ( $product->get_type() == 'variable' ) || ( $product->get_type() == 'variation' ) ) {

				$commission = null;
				$original_prices = array();

				if ( $product->get_type() == 'variable' ) {
					$children = $product->get_visible_children(true);

					foreach ( $children as $child ) {
						$original_prices[] = get_post_meta( $child, $price_key, true );
						if ( $commission == null ) {
							$commission = WRP_Variations_Admin::get_commission( $product, $child );
						}
					}
				} else {  // 'variation' on javascript variable product select
					//$original_prices[] = get_post_meta( $product, $price_key, true );
					if ( ( get_option( "wrp-baseprice", "regular" ) == "sale" ) && $product->is_on_sale('edit') ) {
						$original_prices[] = $product->get_sale_price();
						$result = wc_price( self::woocommerce_get_price($product->get_sale_price(), $product) );
					} else {
						$original_prices[] = $product->get_regular_price();
						$result = wc_price( self::woocommerce_get_price($product->get_regular_price(), $product) );
					}
					if ( $commission == null ) {
						$commission = WRP_Variations_Admin::get_commission( $product, $product->get_id() );
					}
				}

				$original_price = $price;

				if ( $commission !== null ) {
					// if the product price includes the taxes, then we need to subtract it.
					foreach ( $original_prices as $key=>$original_p ) {
						if ( wc_tax_enabled() && ( 'excl' === get_option( 'woocommerce_tax_display_shop' ) ) && ( get_option('woocommerce_prices_include_tax') == 'yes' ) ) {
							$_tax           = new WC_Tax();
							$tax_rates      = $_tax->get_base_tax_rates( $product->get_tax_class() );
							$taxes          = $_tax->calc_tax( $original_p, $tax_rates, true );
							$original_prices[$key] = $_tax->round( $original_p - array_sum( $taxes ) );
						}
					}

					$min_price = min( $original_prices );
					$max_price = max( $original_prices );

					if ( is_numeric( $min_price ) && is_numeric( $max_price ) ) {
						$original_price = $min_price !== $max_price ? sprintf( _x( '%1$s–%2$s', 'Price range: from-to', 'woocommerce' ), wc_price( $min_price ), wc_price( $max_price ) ) : wc_price( $min_price );
					}
				}
			} else {
				$original_price = get_post_meta( $product->get_id(), $price_key, true );
				$commission = self::get_commission( $product );
				if ( $commission !== null ) {
					$method = get_option( 'wrp-method', 'rate' );

					if ( ( ( $method == "rate" ) && ( $commission <= 1 ) ) || ( ( $method !== "rate" ) && ( $commission >= 0 ) ) ) {
						// if the product price includes the taxes, then we need to subtract it.
						if ( wc_tax_enabled() && ( 'excl' === get_option( 'woocommerce_tax_display_shop' ) ) && ( get_option('woocommerce_prices_include_tax') == 'yes' ) ) {
							$_tax           = new WC_Tax();
							$tax_rates      = $_tax->get_base_tax_rates( $product->get_tax_class() );
							$taxes          = $_tax->calc_tax( get_post_meta( $product->get_id(), $price_key, true ), $tax_rates, true );
							$original_price = $_tax->round( get_post_meta( $product->get_id(), $price_key, true ) - array_sum( $taxes ) );
						}
						$original_price = wc_price( $original_price );
					}
				}
			}
		}
		return $result;
	}

	public static function bcmul( $data1, $data2, $prec = 0 ) {
		$result = 0;
		if ( function_exists('bcmul') ) {
			$result = bcmul( $data1, $data2, $prec );
		} else {
			$value = $data1 * $data2;
			if ($prec) {
				$result = round($value, $prec);
			}
		}
		return $result;
	}

	public static function bcsub( $data1, $data2, $prec = 0 ) {
		$result = 0;
		if ( function_exists('bcsub') ) {
			$result = bcsub( $data1, $data2, $prec );
		} else {
			$value = $data1 - $data2;
			if ($prec) {
				$result = round($value, $prec);
			}
		}
		return $result;
	}

	public static function clear_products_cache() {
		global $woocommerce;
		if(version_compare($woocommerce->version, '2.4', '>=')) {
			WC_Cache_Helper::get_transient_version('product', true);
		}
	}

}
WooRolePricingLight::init();
