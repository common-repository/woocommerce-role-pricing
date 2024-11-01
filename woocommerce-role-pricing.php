<?php
/**
 * woo-role-pricing-light.php
 *
 * Copyright (c) 2011,2012 Antonio Blanco http://www.blancoleon.com
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
 * @since woorolepricinglight 1.0.0
 *
 * Plugin Name: Woocommerce Role Pricing
 * Plugin URI: https://www.eggemplo.com/plugins/woocommerce-role-pricing
 * Description: Shows different prices according to the user's role
 * Version: 3.5.3
 * Author: eggemplo
 * Author URI: https://www.eggemplo.com
 * Text Domain: woocommerce-role-pricing
 * Domain Path: /languages
 * License: GPLv3
 */

define( 'WOO_ROLE_PRICING_LIGHT_PLUGIN_NAME', 'woocommerce-role-pricing' );

define( 'WOO_ROLE_PRICING_LIGHT_FILE', __FILE__ );

if ( !defined( 'WOO_ROLE_PRICING_LIGHT_CORE_DIR' ) ) {
	define( 'WOO_ROLE_PRICING_LIGHT_CORE_DIR', WP_PLUGIN_DIR . '/' . WOO_ROLE_PRICING_LIGHT_PLUGIN_NAME . '/core' );
}

define( 'WOO_ROLE_PRICING_LIGHT_PLUGIN_URL', plugin_dir_url( WOO_ROLE_PRICING_LIGHT_FILE ) );

define ( 'WOO_ROLE_PRICING_LIGHT_DECIMALS', 2 );

class WooRolePricingLight_Plugin {

	private static $notices = array();

	public static function init() {

		load_plugin_textdomain( 'woocommerce-role-pricing', null, WOO_ROLE_PRICING_LIGHT_PLUGIN_NAME . '/languages' );

		register_activation_hook( WOO_ROLE_PRICING_LIGHT_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( WOO_ROLE_PRICING_LIGHT_FILE, array( __CLASS__, 'deactivate' ) );

		register_uninstall_hook( WOO_ROLE_PRICING_LIGHT_FILE, array( __CLASS__, 'uninstall' ) );

		add_action( 'init', array( __CLASS__, 'wp_init' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		// in plugin list add a link to settings
		add_filter( 'plugin_action_links_' . plugin_basename( WOO_ROLE_PRICING_LIGHT_FILE ), array( __CLASS__, 'plugin_action_links' ) );

	}

	public static function wp_init() {

		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$active_sitewide_plugins = array_keys( $active_sitewide_plugins );
			$active_plugins = array_merge( $active_plugins, $active_sitewide_plugins );
		}

		$woo_is_active = in_array( 'woocommerce/woocommerce.php', $active_plugins );

		if ( !$woo_is_active ) {
			self::$notices[] = "<div class='error'>" . __( 'The <strong>Woocommerce Role Pricing Light</strong> plugin requires the <a href="http://wordpress.org/extend/plugins/woocommerce" target="_blank">Woocommerce</a> plugin to be activated.', 'woocommerce-role-pricing' ) . "</div>";

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			deactivate_plugins( array( __FILE__ ) );
		} else {

			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 40 );

			//call register settings function
			add_action( 'admin_init', array( __CLASS__, 'register_woorolepricinglight_settings' ) );

			if ( !class_exists( "WooRolePricingLight" ) ) {
				include_once 'core/class-woorolepricinglight.php';
				include_once 'core/class-wrp-variations-admin.php';
			}
		}
	}

	public static function register_woorolepricinglight_settings() {
		register_setting( 'woorolepricinglight', 'wrp-method' );
		add_option( 'wrp-method','rate' ); // by default rate

		register_setting( 'woorolepricinglight', 'wrp-baseprice' );
		add_option( 'wrp-baseprice','regular' ); // by default regular

		register_setting( 'woorolepricinglight', 'wrp-haveset' );
		add_option( 'wrp-haveset','discounts' ); // by default discounts

	}

	public static function admin_notices() { 
		if ( !empty( self::$notices ) ) {
			foreach ( self::$notices as $notice ) {
				echo $notice;
			}
		}
	}

	/**
	 * Adds the admin section.
	 */
	public static function admin_menu() {
		$admin_page = add_submenu_page(
				'woocommerce',
				__( 'Woo Role Pricing', 'woocommerce-role-pricing' ),
				__( 'Woo Role Pricing', 'woocommerce-role-pricing' ),
				'manage_options',
				'woorolepricinglight',
				array( __CLASS__, 'woorolepricinglight_settings_menu' )
		);

	}

	public static function woorolepricinglight_settings_menu () {
		global $wp_roles;

		$section_links_array = array(
				'method' => __( 'Method', 'woocommerce-role-pricing' ),
				'roles'  => __( 'by Roles', 'woocommerce-role-pricing' ),
				'import' => __( 'Import/Export', 'woocommerce-role-pricing' )
		);
	
		$alert = '';

		$section = isset( $_REQUEST['section'] ) ? $_REQUEST['section'] : 'method';

	if ( class_exists( 'WP_Roles' ) ) {
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
	}

	if ( isset( $_POST['submit'] ) ) {
		switch ( $section ) {
			case 'roles' :
				if ( class_exists( 'WP_Roles' ) ) {
					if ( ! isset( $wp_roles ) ) {
						$wp_roles = new WP_Roles();
					}
				}
				// guest
				if ( isset( $_POST[ "wrp-guest" ] ) && ( $_POST[ "wrp-guest" ] !== "" ) ) {
				    update_option( "wrp-guest", $_POST[ "wrp-guest" ] );
				} else {
				    delete_option( "wrp-guest" );
				}
				// roles
				foreach ( $wp_roles->role_objects as $role ) {
					if ( isset( $_POST[ "wrp-" . $role->name ] ) && ( $_POST[ "wrp-" . $role->name ] !== "" ) ) {
						update_option( "wrp-" . $role->name, $_POST[ "wrp-" . $role->name ] );
					} else {
						delete_option( "wrp-" . $role->name );
					}
				}
				$alert = __("Saved Roles subsection", 'woocommerce-role-pricing');
				break;
			case 'import' :
				break;
			case 'method' :
			default:
				if ( isset( $_POST[ "wrp-method" ] ) ) {
					update_option( "wrp-method", sanitize_text_field($_POST[ "wrp-method" ]) );
				}
				if ( isset( $_POST[ "wrp-baseprice" ] ) ) {
				    update_option( "wrp-baseprice", sanitize_text_field($_POST[ "wrp-baseprice" ]) );
				}
				if ( isset( $_POST[ "wrp-haveset" ] ) ) {
				    update_option( "wrp-haveset", sanitize_text_field($_POST[ "wrp-haveset" ]) );
				}
				if ( isset( $_POST[ 'wrp-hideprice-guest' ] ) ) {
				    update_option( "wrp-hideprice-guest", intval($_POST[ 'wrp-hideprice-guest' ]) );
				} else {
				    update_option( "wrp-hideprice-guest", 0 );
				}
				
				$alert = __("Saved Method subsection", 'woocommerce-role-pricing');
				break;
		}
		// clear product cache
		WooRolePricingLight::clear_products_cache();
		}
		
		if ($alert != "") {
			echo '<div style="background-color: #ffffe0;border: 1px solid #993;padding: 1em;margin-right: 1em;">' . $alert . '</div>';
		}
		
		$section_title = $section_links_array[$section];
		
		echo '<div class="wrap">';
		echo '<h2>' . __( 'Woocommerce Role Pricing', 'woocommerce-role-pricing' ) . '</h2>';
		
		$section_links = '';
		foreach( $section_links_array as $sec => $title ) {
			$section_links .= sprintf(
					'<a class="section-link nav-tab %s" href="%s">%s</a>',
					$section == $sec ? 'active nav-tab-active' : '',
					esc_url( add_query_arg( 'section', $sec, admin_url( 'admin.php?page=woorolepricinglight' ) ) ),
					$title
					);
		}

		echo '<div class="section-links nav-tab-wrapper">';
		echo $section_links;
		echo '</div>';

		switch( $section ) {
			case 'roles' :
				self::section_roles();
				break;
			case 'import' :
				self::section_import();
				break;
			case 'method' :
			default :
				self::section_method();
				break;
		}
	}

	/**
	 * Display the Method subsection settings page.
	 */
	protected static function section_method() {
	?>
		<div class="wrap" style="border: 1px solid #ccc; padding:10px;">
		<form method="post" action="">
		    <table class="form-table">
		        <tr valign="top">
			        <th scope="row"><strong><?php echo __( 'Products discount method:', 'woocommerce-role-pricing' ); ?></strong></th>
			        <td>
			        	<select name="wrp-method">
			        	<?php 
			        	if ( get_option("wrp-method") == "amount" ) {
			        	?>
			        		<option value="rate"><?php echo __( 'Rate', 'woocommerce-role-pricing' ); ?></option>
			        		<option value="amount" selected="selected"><?php echo __( 'Amount', 'woocommerce-role-pricing' ); ?></option>
			        	<?php 
			        	} else {
			        	?>
			        		<option value="rate" selected="selected"><?php echo __( 'Rate', 'woocommerce-role-pricing' ); ?></option>
			        		<option value="amount"><?php echo __( 'Amount', 'woocommerce-role-pricing' ); ?></option>
			        	<?php 
			        	}
			        	?>
			        	</select>
			        </td>
		        </tr>

		        <tr valign="top">
			        <th scope="row"><strong><?php echo __( 'If it\'s on sale, apply to:', 'woocommerce-role-pricing' ); ?></strong></th>
			        <td>
			        	<select name="wrp-baseprice">
			        	<?php 
			        	if ( get_option("wrp-baseprice") == "sale" ) {
			        	?>
			        		<option value="regular"><?php echo __( 'Regular price', 'woocommerce-role-pricing' ); ?></option>
			        		<option value="sale" selected="selected"><?php echo __( 'Sale price', 'woocommerce-role-pricing' ); ?></option>
			        	<?php 
			        	} else {
			        	?>
			        		<option value="regular" selected="selected"><?php echo __( 'Regular price', 'woocommerce-role-pricing' ); ?></option>
			        		<option value="sale"><?php echo __( 'Sale price', 'woocommerce-role-pricing' ); ?></option>
			        	<?php 
			        	}
			        	?>
			        	</select>
			        	</br>
						<span class="description"><?php echo __( "If you select <b>Sale Price</b> then if the product has a sale price then discount is applied over this sale price. But if it doesn't, then the discount is applied on the regular price.", 'woocommerce-role-pricing'); ?></span>
			        </td>
		        </tr>

		        <tr valign="top">
			        <th scope="row"><strong><?php echo __( 'Your values are:', 'woocommerce-role-pricing' ); ?></strong></th>
			        <td>
			        	<select name="wrp-haveset">
			        	<?php 
			        	switch ( get_option("wrp-haveset") ) {
			        		case 'amounts':
			        			?>
			        			<option value="discounts"><?php echo __( 'Discounts', 'woocommerce-role-pricing' );?></option>
			        			<option value="amounts" selected="selected"><?php echo __( 'Amounts', 'woocommerce-role-pricing' );?></option>
			        			<?php
			        			break;
							case 'discounts':
							default:
					        	?>
				        		<option value="discounts" selected="selected"><?php echo __( 'Discounts', 'woocommerce-role-pricing' );?></option>
					        	<option value="amounts"><?php echo __( 'Amounts', 'woocommerce-role-pricing' );?></option>
				        		<?php
					        	break;
			        	}
			        	?>
			        	</select>
			        	</br>
						<span class="description"><?php echo __( "If you select <b>Discounts</b> and your value is 0.1, then the group will see 90% of the original price.", 'woocommerce-role-pricing'); ?></span>
						</br>
						<span class="description"><?php echo __( "If you select <b>Final values</b> and your value is 0.1, then the group will see 10% of the original price.", 'woocommerce-role-pricing'); ?></span>
					</td>
		        </tr>
				<tr valign="top">
			        <th scope="row"><strong><?php echo __( 'Hide prices:', 'woocommerce-role-pricing' ); ?></strong></th>
			        <td>
    			        <?php 
    			        if ( get_option("wrp-hideprice-guest", '0') == '1' ) {
    			            $checked = "checked";
    			        } else {
    			            $checked = "";
    			        }
    			        ?>
						<input type="checkbox" name="wrp-hideprice-guest" value="1" <?php echo $checked; ?> >
						<span class="description"><?php echo __( 'Hide price and "add to cart" button for unregistered users.', 'woocommerce-role-pricing'); ?></span>
					</td>
		        </tr>
				<tr valign="top" style="border-bottom: 1px solid #ccc;">
					<th scope="row"></th>
					<td></td>
				</tr>
				<tr valign="top">
					<th scope="row"><strong style="color:red;">PRO FEATURES</strong></th>
					<td><a href="https://www.eggemplo.com/plugin/woocommerce-role-pricing/?ref=5" target="_blank"><?php echo __('Get the Pro version')?></a></td>
				</tr>
				<tr valign="top" style="border: 2px solid red;">
					<th scope="row"></th>
					<td><img src="<?php echo WOO_ROLE_PRICING_LIGHT_PLUGIN_URL;?>/assets/pro_features.png" alt="Pro features" /></td>
				</tr>
			</table>

			<?php submit_button( __( "Save", 'woocommerce-role-pricing' ) ); ?>
			<?php settings_fields( 'woorolepricinglight' ); ?>
		</form>
		</div>
		</div>  <!-- close wrap div -->
	<?php
	}

	/**
	 * Display the Import/Export subsection settings page.
	 */
	protected static function section_import() {
		// if download is requested
		if ( isset( $_GET['download'] ) && $_GET['download'] == 'woorolepricinglight' ) {
			$settings = array();
			$settings['wrp-method'] = get_option( 'wrp-method' );
			$settings['wrp-baseprice'] = get_option( 'wrp-baseprice' );
			$settings['wrp-haveset'] = get_option( 'wrp-haveset' );
			$settings['wrp-hideprice-guest'] = get_option( 'wrp-hideprice-guest' );
			$settings['wrp-guest'] = get_option( 'wrp-guest' );
			$settings['wrp-roles'] = array();
			global $wp_roles;
			if ( class_exists( 'WP_Roles' ) ) {
				if ( ! isset( $wp_roles ) ) {
					$wp_roles = new WP_Roles();
				}
			}
			foreach ( $wp_roles->role_objects as $role ) {
				$settings['wrp-roles'][$role->name] = get_option( 'wrp-' . $role->name );
			}
			// generamos un fichero y lo guardamos en el directorio temporal
			$upload_dir = wp_upload_dir();
			$filename = $upload_dir['basedir'] . '/woorolepricinglight-settings.json';
			file_put_contents( $filename, json_encode( $settings ) );
			// url del fichero
			// no usamos $upload_dir['baseurl'] porque no funciona en multisite
			$filename = str_replace( ABSPATH, get_site_url() . '/', $filename );
			// mostramos un boton de descarga con un mensaje
			echo '<div class="notice notice-success"><a href="' . $filename . '" download>' . __( 'Download settings', 'woocommerce-role-pricing' ) . '</a></div>';

		}
		// if import is requested
		if ( isset( $_FILES['import_file'] ) && $_FILES['import_file']['error'] == 0 ) {
			$import_file = $_FILES['import_file']['tmp_name'];

			$settings = json_decode( file_get_contents( $import_file ), true );
			if ( $settings ) {
				if ( isset( $settings['wrp-method'] ) ) {
					update_option( 'wrp-method', $settings['wrp-method'] );
				}
				if ( isset( $settings['wrp-baseprice'] ) ) {
					update_option( 'wrp-baseprice', $settings['wrp-baseprice'] );
				}
				if ( isset( $settings['wrp-haveset'] ) ) {
					update_option( 'wrp-haveset', $settings['wrp-haveset'] );
				}
				if ( isset( $settings['wrp-hideprice-guest'] ) ) {
					update_option( 'wrp-hideprice-guest', $settings['wrp-hideprice-guest'] );
				}
				if ( isset( $settings['wrp-guest'] ) ) {
					update_option( 'wrp-guest', $settings['wrp-guest'] );
				}
				global $wp_roles;
				if ( class_exists( 'WP_Roles' ) ) {
					if ( ! isset( $wp_roles ) ) {
						$wp_roles = new WP_Roles();
					}
				}
				foreach ( $wp_roles->role_objects as $role ) {
					if ( isset( $settings['wrp-roles'][$role->name] ) ) {
						update_option( 'wrp-' . $role->name, $settings['wrp-roles'][$role->name] );
					} else {
						delete_option( 'wrp-' . $role->name );
					}
				}
				$alert = __("Settings imported", 'woocommerce-role-pricing');
			} else {
				$alert = __("Error importing settings", 'woocommerce-role-pricing');
			}

			// display alert
			if ($alert != "") {
				echo '<div style="background-color: #ffffe0;border: 1px solid #993;padding: 1em;margin-right: 1em;">' . $alert . '</div>';
			}
		}
		// option to download the current settings of the plugin
		$download_url = add_query_arg( array( 'download' => 'woorolepricinglight' ), admin_url( 'admin.php?page=woorolepricinglight&section=import' ) );
		?>
		<div class="wrap" style="border: 1px solid #ccc; padding:10px;">
			<form method="post" action="">
			    <h3><?php echo __( 'Import/Export:', 'woocommerce-role-pricing' ); ?></h3>
			    <div class="description"><?php echo __( 'You can download the current settings of the plugin.', 'woocommerce-role-pricing' ); ?></div>
			    <a href="<?php echo $download_url; ?>" class="button"><?php echo __( 'Download settings', 'woocommerce-role-pricing' ); ?></a>
			</form>
		</div>
		<?php
		// now an option to import
		?>
		<div class="wrap" style="border: 1px solid #ccc; padding:10px;">
			<form method="post" action="" enctype="multipart/form-data">
			    <h3><?php echo __( 'Import settings:', 'woocommerce-role-pricing' ); ?></h3>
			    <div class="description"><?php echo __( 'You can import settings from a file.', 'woocommerce-role-pricing' ); ?></div>
			    <input type="file" name="import_file" />
			    <?php submit_button( __( "Import", 'woocommerce-role-pricing' ) ); ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Display the Roles subsection settings page.
	 */
	protected static function section_roles() {
		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}
		?>
		<div class="wrap" style="border: 1px solid #ccc; padding:10px;">
			<form method="post" action="">
			    <h3><?php echo __( 'Roles:', 'woocommerce-role-pricing' ); ?></h3>
			    <div class="description"><?php echo __( 'Leave empty if no role discount should be applied (default setting).<br>Example with rate method: Indicate 0.1 for 10% discounts on every product.', 'woocommerce-role-pricing');?>
			    </div>

				<table class="form-table">
					<tr valign="top">
				        <th scope="row"><?php echo __('User not logged in', 'woocommerce-role-pricing') . ':'; ?></th>
				        <td>
				        	<input type="text" name="wrp-guest" value="<?php echo get_option( "wrp-guest" ); ?>" />
				        </td>
				    </tr>
			    <?php 
			    	foreach ( $wp_roles->role_objects as $role ) {
					        ?>
					        <tr valign="top">
					        <th scope="row"><?php echo ucwords($role->name) . ':'; ?></th>
					        <td>
					        	<input type="text" name="wrp-<?php echo $role->name;?>" value="<?php echo get_option( "wrp-" . $role->name ); ?>" />
					        </td>
					        </tr>
					        <?php 
					}
				?>
				</table>

				<?php submit_button( __( "Save", 'woocommerce-role-pricing' ) ); ?>
				<?php settings_fields( 'woocommerce-role-pricing' ); ?>
			</form>
		</div>
		</div>  <!-- close Wrap div -->
		<?php
	}

	/**
	 * Plugin activation work.
	 * 
	 */
	public static function activate() {

	}

	/**
	 * Plugin deactivation.
	 *
	 */
	public static function deactivate() {

	}

	/**
	 * Plugin uninstall. Delete database table.
	 *
	 */
	public static function uninstall() {

	}

	/**
	 * Add a link to the settings page in the plugin list.
	 *
	 * @param array $links
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$settings_link = '<a href="admin.php?page=woorolepricinglight">' . __( 'Settings', 'woocommerce-role-pricing' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

}
WooRolePricingLight_Plugin::init();

