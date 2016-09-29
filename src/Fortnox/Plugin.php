<?php

namespace Fortnox;

use Wetail\Admin\Settings;
use Fortnox\API\Customers;
use Fortnox\API\Orders;
use Fortnox\API\Products;
use WCF_API;
use WC_Fortnox_Controller;
use WC_Product;
use WCF_Database_Interface;
use Exception;

class Plugin {
	const TEXTDOMAIN = "woocommerce-fortnox-integration";
	const CLIENT_SECRET = "AQV9TbDU1k";
	
	/**
	 * Set sequential order
	 *
	 * @param $orderId
	 * @param $post
	 */
	public static function setSequentialOrderNumber( $orderId, $post ) 
	{
		if( ! get_option( 'fortnox_order_number_prefix' ) )
			return false;
		
		if( is_array( $post ) || is_null( $post ) || ( 'shop_order' === $post->post_type && 'auto-draft' !== $post->post_status ) ) {
			$orderId = is_a( $orderId, "WC_Order" ) ? $orderId->id : $orderId;
			$orderNumber = get_post_meta( $orderId, '_order_number', true );
			
			update_post_meta( $orderId, '_order_number', get_option( 'fortnox_order_number_prefix' ) . $orderNumber );
		}
	}
	
	/**
	 * Get sequential order number
	 *
	 * @param $orderNumber
	 * @param $order
	 */
	public static function getSequentialOrderNumber( $orderNumber, $order ) 
	{
		if( $order instanceof WC_Subscription )
			return $orderNumber;
		
		if( get_post_meta( $order->id, '_order_number_formatted', true ) )
			return get_post_meta( $order->id, '_order_number_formatted', true );
		
		if( ! get_option( 'fortnox_order_number_prefix' ) )
			return $orderNumber;
		
		return get_option( 'fortnox_order_number_prefix' ) . $orderNumber;
	}
	
	/**
	 * Get plugin path
	 *
	 * @param string $path
	 */
	public static function getPath( $path = '' )
	{
		return plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . ltrim( $path, '/' );
	}
	
	/**
	 * Get plugin URL
	 *
	 * @param string $path
	 */
	public static function getUrl( $path = '' )
	{
		return plugins_url( $path, dirname( dirname( __FILE__ ) ) );
	}
	
	/**
	 * Load textdomain
	 *
	 * @hook 'plugins_loaded'
	 */
	public static function loadTextdomain()
	{
		load_plugin_textdomain( self::TEXTDOMAIN );
	}
	
	/**
	 * Add settings
	 *
	 * @hook 'admin_init'
	 */
	public static function addSettings()
	{
		$page = "fortnox";
		
		// General tab
		Settings::addTab( [
			'page' => $page,
			'name' => "general",
			'title' => __( "General", self::TEXTDOMAIN )
		] );
		
		// API section
		Settings::addSection( [
			'page' => $page,
			'tab' => "general",
			'name' => "api",
			'title' => __( "API keys", self::TEXTDOMAIN ),
			'description' => __( "Your API keys to communicate with Fortnox.", self::TEXTDOMAIN )
		] );
		
		// API key field
		Settings::addField( [
			'page' => $page,
			'tab' => "general",
			'section' => "api",
			'name' => "fortnox_api_key",
			'title' => __( "API key", self::TEXTDOMAIN ),
			'after' => '<a href="#" class="button fortnox-check-connection">' . __( "Check", self::TEXTDOMAIN ) . '</a> <span class="spinner fortnox-spinner"></span>'
		] );
		
		// API code field
		Settings::addField( [
			'page' => $page,
			'tab' => "general",
			'section' => "api",
			'name' => "fortnox_auth_code",
			'title' => __( "Authorisation code", self::TEXTDOMAIN ),
			'after' => '<a href="#" class="button fortnox-check-connection">' . __( "Check", self::TEXTDOMAIN ) . '</a> <span class="spinner fortnox-spinner"></span>'
		] );
		
		// Bookkeeping section
		Settings::addSection( [
			'page' => $page,
			'tab' => "general",
			'name' => "bookkeeping",
			'title' => __( "Bookkeeping", self::TEXTDOMAIN )
		] );
		
		// Price list field
		Settings::addField( [
			'page' => $page,
			'tab' => "general",
			'section' => "bookkeeping",
			'name' => "fortnox_default_price_list",
			'type' => "text",
			'placeholder' => "A",
			'title' => __( "Price list", self::TEXTDOMAIN ),
			'description' => __( "Default Fortnox price list. Sets default price list to A when left empty.", self::TEXTDOMAIN )
		] );
		
		// Bookkeeping preferences
		Settings::addField( [
			'page' => $page,
			'tab' => "general",
			'section' => "bookkeeping",
			'type' => "checkboxes",
			//'title' => __( "Preferences", self::TEXTDOMAIN ),
			'options' => [
				[
					'name' => "fortnox_auto_sync_orders",
					'label' => __( "Automatically synchronise all orders with status 'Completed'", self::TEXTDOMAIN )
				],
				[
					'name' => "fortnox_auto_create_order_invoice",
					'label' => __( "Create invoice when successfully synchronised order", self::TEXTDOMAIN )
				],
				[
					'name' => "fortnox_auto_post_order_invoice",
					'label' => __( "Automatically post order invoice to bookkeeping", self::TEXTDOMAIN )
				]
			]
		] );
		
		// Products section
		Settings::addSection( [
			'page' => $page,
			'tab' => "general",
			'name' => "products",
			'title' => __( "Products", self::TEXTDOMAIN )
		] );
		
		// Preferences checkboxes fields
		Settings::addField( [
			'page' => $page,
			'tab' => "general",
			'section' => "products",
			'type' => "checkboxes",
			'options' => [
				[
					'name' => "fortnox_auto_sync_products",
					'label' => __( "Automatically synchronise products", self::TEXTDOMAIN )
				],
				[
					'name' => "fortnox_sync_master_product",
					'label' => __( "Synchronise master product (must have SKU).", self::TEXTDOMAIN )
				],
				[
					'name' => "fortnox_skip_product_variations",
					'label' => __( "Do not synchronise product variations.", self::TEXTDOMAIN )
				]
			]
		] );
		
		// Misc sections
		Settings::addSection( [
			'page' => $page,
			'tab' => "general",
			'name' => "misc",
			'title' => __( "Miscellaneous", self::TEXTDOMAIN )
		] );
		
		// Show advanced settings
		Settings::addField( [
			'page' => $page,
			'tab' => "general",
			'section' => "misc",
			'type' => "checkbox",
			'name' => "fortnox_show_advanced_settings",
			'label' => __( "Show advanced settings", self::TEXTDOMAIN )
		] );
		
		// Order settings tab
		Settings::addTab( [
			'page' => $page,
			'name' => "order",
			'title' => __( "Order settings", self::TEXTDOMAIN )
		] );
		
		// Order settings section
		Settings::addSection( [
			'page' => $page,
			'tab' => "order",
			'name' => "order",
			'title' => __( "Order", self::TEXTDOMAIN )
		] );
		
		// Administration fee field
		Settings::addField( [
			'page' => $page,
			'tab' => "order",
			'section' => "order",
			'name' => "fortnox_admin_fee",
			'title' => __( "Administration fee", self::TEXTDOMAIN ),
			'description' => __( "Specify invoice or administration fee if you acquire any.", self::TEXTDOMAIN )
		] );
		
		// Payment options field
		Settings::addField( [
			'page' => $page,
			'tab' => "order",
			'section' => "order",
			'name' => "fortnox_payment_terms",
			'title' => __( "Payment terms", self::TEXTDOMAIN ),
			'description' => __( "The code for payment terms found in Fortnox: INSTÄLLNINGAR &#8594; OFFERT/ORDER &#8594; BETALNINGSALTERNATIV", self::TEXTDOMAIN )
		] );
		
		// Cost center field
		Settings::addField( [
			'page' => $page,
			'tab' => "order",
			'section' => "order",
			'name' => "fortnox_cost_center",
			'title' => __( "Cost center", self::TEXTDOMAIN ),
			'description' => __( "Adds cost center for an order to use in bookkeeping. This is used within different sale channels in Fortnox.", self::TEXTDOMAIN )
		] );
		
		// Add payment type field
		Settings::addField( [
			'page' => $page,
			'tab' => "order",
			'section' => "order",
			'type' => "checkbox",
			'name' => "fortnox_write_payment_type_to_ordertext",
			'label' => __( "Add payment type", self::TEXTDOMAIN ),
			'description' => __( "Writes order payment type to ordertext field in Fortnox.", self::TEXTDOMAIN )
		] );
		
		// FIX: Sequential order numbers
		if( ! is_plugin_active( 'woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers.php' ) ) {
			// Sequential order number
			Settings::addField( [
				'page' => $page,
				'tab' => "order",
				'section' => "order",
				'name' => "fortnox_order_number_prefix",
				'type' => "text",
				'title' => __( "Sequential order number", self::TEXTDOMAIN ),
				'description' => __( "Sequential number to prepend to WooCommerce order number", self::TEXTDOMAIN )
			] );
		}
		
		// Shipping sections
		Settings::addSection( [
			'page' => $page,
			'tab' => "order",
			'name' => "shipping",
			'title' => __( "Shipping", self::TEXTDOMAIN )
		] );
		
		// Shipping product SKU field
		Settings::addField( [
			'page' => $page,
			'tab' => "order",
			'section' => "shipping",
			'name' => "fortnox_shipping_product_sku",
			'title' => __( "Shipping product SKU", self::TEXTDOMAIN ),
			'description' => __( "This settings is recommended if you're selling products with other VAT rate than 25%. Create an unpublished product with 25% VAT rate and specify its SKU here.", self::TEXTDOMAIN )
		] );
		
		// Bulk actions tab
		Settings::addTab( [
			'page' => $page,
			'name' => "bulk-actions",
			'title' => __( "Bulk actions", self::TEXTDOMAIN ),
			'saveButton' => false
		] );
		
		// Bulk actions section
		Settings::addSection( [
			'page' => $page,
			'tab' => "bulk-actions",
			'name' => "bulk-actions",
			'title' => __( "Bulk actions", self::TEXTDOMAIN ),
			'description' => __( "Useful bulk actions to perform retroactively between WooCommerce and Fortnox.", self::TEXTDOMAIN )
		] );
		
		// Sync products button
		Settings::addField( [
			'page' => $page,
			'tab' => "bulk-actions",
			'section' => "bulk-actions",
			'title' => __( "Sync products", self::TEXTDOMAIN ),
			'type' => "button",
			'button' => [
				'text' => __( "Sync products", self::TEXTDOMAIN ),
			],
			'data' => [
				[
					'key' => "fortnox-bulk-action",
					'value' => "fortnox_sync_products"
				]
			],
			'description' => __( "Upload all products from WooCommerce to Fortnox. It may take a while to synchronise large shops.", self::TEXTDOMAIN )
		] );
		
		// Sync inventory button
		Settings::addField( [
			'page' => $page,
			'tab' => "bulk-actions",
			'section' => "bulk-actions",
			'title' => __( "Sync inventory", self::TEXTDOMAIN ),
			'type' => "button",
			'button' => [
				'text' => __( "Sync inventory", self::TEXTDOMAIN ),
			],
			'data' => [
				[
					'key' => "fortnox-bulk-action",
					'value' => "fortnox_sync_inventory"
				]
			],
			'description' => __( "Update stock status from Fortnox. It may take a while to synchronise large shops.", self::TEXTDOMAIN )
		] );
		
		// Find missing products button
		Settings::addField( [
			'page' => $page,
			'tab' => "bulk-actions",
			'section' => "bulk-actions",
			'title' => __( "Find missing products", self::TEXTDOMAIN ),
			'type' => "button",
			'button' => [
				'text' => __( "Find missing products", self::TEXTDOMAIN ),
			],
			'data' => [
				[
					'key' => "fortnox-bulk-action",
					'value' => "fortnox_find_missing_products"
				]
			],
			'description' => __( "Get a list of missing products in Fortnox.", self::TEXTDOMAIN ) . '</span><div class="fortnox-missing-products-list"></div><span></span>'
		] );
		
		// Sync diff orders button
		Settings::addField( [
			'page' => $page,
			'tab' => "bulk-actions",
			'section' => "bulk-actions",
			'title' => __( "Sync diff orders", self::TEXTDOMAIN ),
			'type' => "button",
			'button' => [
				'text' => __( "Sync diff orders", self::TEXTDOMAIN ),
			],
			'data' => [
				[
					'key' => "fortnox-bulk-action",
					'value' => "fortnox_sync_diff_orders"
				]
			],
			'description' => __( "Update orders that have different amount in Fortnox.", self::TEXTDOMAIN ) . '</span><div class="fortnox-diff-orders-list"></div><span></span>',
		] );
		
		// Flush access token button
		Settings::addField( [
			'page' => $page,
			'tab' => "bulk-actions",
			'section' => "bulk-actions",
			'title' => __( "Flush access token", self::TEXTDOMAIN ),
			'type' => "button",
			'button' => [
				'text' => __( "Flush access token", self::TEXTDOMAIN ),
			],
			'data' => [
				[
					'key' => "fortnox-bulk-action",
					'value' => "fortnox_flush_access_token"
				]
			],
			'description' => sprintf( __( "Delete Fortnox access token. <a href=\"%s\">Read more</a>", self::TEXTDOMAIN ), 'https://wetail.helpdocs.com/felmeddelande/fortnox-accesstoken-fel' )
		] );
		
		// Advanced settings tab
		Settings::addTab( [
			'page' => $page,
			'name' => "advanced",
			'title' => __( "Advanced settings", self::TEXTDOMAIN ),
			'class' => ! get_option( 'fortnox_show_advanced_settings' ) ? 'hidden' : ''
		] );
		
		// Shipping section
		Settings::addSection( [
			'page' => $page,
			'tab' => "advanced",
			'name' => "advanced-shipping",
			'title' => __( "Shipping", self::TEXTDOMAIN ),
			'description' => __( "Advanced shipping settings to use within Fortnox.", self::TEXTDOMAIN )
		] );
		
		// Active shipping methods
		foreach( WC()->shipping->get_shipping_methods() as $shippingMethod ) {
			Settings::addField( [
				'page' => $page,
				'tab' => "advanced",
				'section' => "advanced-shipping",
				'name' => "fortnox_shipping_code_{$shippingMethod->id}",
				'title' => $shippingMethod->method_title,
				'description' => sprintf( __( "Code for '%s' shipping method in Fortnox.", self::TEXTDOMAIN ), $shippingMethod->method_title )
			] );
		}
		
		// Bookkeeping section
		Settings::addSection( [
			'page' => $page,
			'tab' => "advanced",
			'name' => "advanced-bookkeeping",
			'title' => __( "Bookkeeping", self::TEXTDOMAIN ),
			'description' => __( "Advanced bookkeeping settings in Fortnox.", self::TEXTDOMAIN )
		] );
		
		// Sales 25% VAT field
		Settings::addField( [
			'page' => $page,
			'tab' => "advanced",
			'section' => "advanced-bookkeeping",
			'name' => "fortnox_account_vat_25",
			'title' => __( "Sales 25% VAT", self::TEXTDOMAIN ),
		] );
		
		// Sales 12% VAT field
		Settings::addField( [
			'page' => $page,
			'tab' => "advanced",
			'section' => "advanced-bookkeeping",
			'name' => "fortnox_account_vat_12",
			'title' => __( "Sales 12% VAT", self::TEXTDOMAIN ),
		] );
		
		// Sales 6% VAT field
		Settings::addField( [
			'page' => $page,
			'tab' => "advanced",
			'section' => "advanced-bookkeeping",
			'name' => "fortnox_account_vat_6",
			'title' => __( "Sales 6% VAT", self::TEXTDOMAIN ),
		] );
		
		// Sales EU field
		Settings::addField( [
			'page' => $page,
			'tab' => "advanced",
			'section' => "advanced-bookkeeping",
			'name' => "fortnox_account_sales_eu",
			'title' => __( "Sales EU", self::TEXTDOMAIN ),
		] );
		
		// VAT EU field
		Settings::addField( [
			'page' => $page,
			'tab' => "advanced",
			'section' => "advanced-bookkeeping",
			'name' => "fortnox_account_vat_eu",
			'title' => __( "VAT EU", self::TEXTDOMAIN ),
		] );
		
		// Sales export field
		Settings::addField( [
			'page' => $page,
			'tab' => "advanced",
			'section' => "advanced-bookkeeping",
			'name' => "fortnox_account_sales_export",
			'title' => __( "Sales export", self::TEXTDOMAIN ),
		] );
		
		// Purchases field
		Settings::addField( [
			'page' => $page,
			'tab' => "advanced",
			'section' => "advanced-bookkeeping",
			'name' => "fortnox_account_purchases",
			'title' => __( "Purchases", self::TEXTDOMAIN ),
		] );
	}
	
	/**
	 * Add settings page
	 *
	 * @hook 'admin_menu'
	 */
	public static function addSettingsPage()
	{
		$page = Settings::addPage( [
			'slug' => "fortnox",
			'title' => __( "Fortnox settings", self::TEXTDOMAIN ),
			'menu' => __( "Fortnox", self::TEXTDOMAIN )
		] );
	}
	
	/**
	 * Add admin scripts
	 */
	public static function addAdminScripts()
	{
		wp_enqueue_script( 'mustache', self::getUrl( 'assets/scripts/mustache.js' ) );
		wp_enqueue_script( 'fortnox', self::getUrl( 'assets/scripts/admin.js' ), [ 'jquery', 'mustache' ] );
		
		wp_enqueue_style( 'fortnox', self::getUrl( 'assets/styles/admin.css' ) );
	}
	
	/**
	 * An array helper
	 *
	 * @param $array
	 * @param $insert
	 * @param $at
	 */
	public static function arrayInsert( $array, $insert, $at ) 
	{
		$insert = ( array ) $insert;
		$left = array_slice( $array, 0, $at );
		$right = array_slice( $array, $at, count( $array ) );
		
		return $left + $insert + $right;
	}
	
	/**
	 * Add orders table columns
	 *
	 * @param $columns
	 */
	public static function addOrdersTableColumns( $columns = [] ) 
	{
		$columns['fortnox'] = "Fortnox";
		
		return $columns;
	}
	
	/**
	 * Print orders table column content
	 *
	 * @param $columnName
	 * @param $postId
	 */
	public static function printOrdersTableColumnContent( $columnName, $postId ) 
	{
		if( "fortnox" != $columnName )
			return;
		
		$nonce = wp_create_nonce( "fortnox_woocommerce" );
		
		print '<a href="#" class="button wetail-button wetail-icon-repeat syncOrderToFortnox" data-order-id="' . $postId . '" data-nonce="' . $nonce . '" title="Sync order to Fortnox"></a> ';
		
		$synced = self::isOrderSynced( $postId );
		
		print '<span class="wetail-fortnox-status ' . ( 1 == $synced ? 'wetail-icon-check' : 'wetail-icon-cross' ) . '" title="' . ( 1 == $synced ? __( "Order has synchronized", self::TEXTDOMAIN ) : __( "Order has not syncronized", self::TEXTDOMAIN ) ) . '"></span>';
		print '<span class="spinner fortnox-spinner"></span>';
	}
	
	/**
	 * Add products table columns
	 *
	 * @param $columns
	 */
	public static function addProductsTableColumns( $columns = [] ) 
	{
		$columns['fortnox'] = "Fortnox";
		
		return $columns;
	}
	
	/**
	 * Print products table column content
	 *
	 * @param $columnName
	 * @param $postId
	 */
	public static function printProductsTableColumnContent( $columnName, $postId ) 
	{
		if( "fortnox" != $columnName )
			return;
		
		$nonce = wp_create_nonce( "fortnox_woocommerce" );
		
		print '<a href="#" class="button wetail-button wetail-icon-repeat syncProductToFortnox" data-product-id="' . $postId . '" data-nonce="' . $nonce . '" title="Sync product to Fortnox"></a> ';
		
		$synced = self::isProductSynced( $postId );
		
		print '<span class="wetail-fortnox-status ' . ( 1 == $synced ? 'wetail-icon-check' : 'wetail-icon-cross' ) . '" title="' . ( 1 == $synced ? __( "Product has synchronized", self::TEXTDOMAIN ) : __( "Product has not syncronized", self::TEXTDOMAIN ) ) . '"></span>';
		print '<span class="spinner fortnox-spinner"></span>';
	}
	
	/**
	 * Check if order is synced
	 *
	 * @param $orderId
	 */
	public static function isOrderSynced( $orderId ) 
	{
		return Orders::isSynced( $orderId );
	}
	
	/**
	 * Check if product is synced
	 *
	 * @param $productId
	 */
	public static function isProductSynced( $productId ) 
	{
		return Products::isSynced( $productId );
	}
	
	/**
	 * Add Fortnox meta boxes to Edit Product and Order views
	 */
	public static function addMetaBoxes() 
	{
		add_meta_box(
			'fortnox_product_meta_box',
			__( "Fortnox", self::TEXTDOMAIN ),
			[ __CLASS__, 'renderProductMetaBox' ],
			"product",
			"side",
			"high"
		);
		
		add_meta_box(
			'fortnox_order_meta_box',
			__( "Fortnox", self::TEXTDOMAIN ),
			[ __CLASS__, 'renderOrderMetaBox' ],
			"shop_order",
			"side",
			"high"
		);
	}
	
	/**
	 * Render Product meta box
	 */
	public static function renderProductMetaBox() 
	{
		print '<p><label><input type="checkbox" name="fortnox_sync_product" ' . checked( get_option( 'fortnox_auto_sync_products' ), '1', false ) . '> ' . __( "Sync changes to Fortnox", self::TEXTDOMAIN ) . '</label></p>';
	}
	
	/**
	 * Render Order meta box
	 */
	public static function renderOrderMetaBox() 
	{
		print '<p><label><input type="checkbox" name="fortnox_sync_order" ' . checked( get_option( 'fortnox_auto_sync_orders' ), '1', false ) . '> ' . __( "Sync changes to Fortnox", self::TEXTDOMAIN ) . '</label></p>';
	}
	
	/**
	 * Sync changes to Fortnox
	 */
	public static function syncChangesToFortnox( $postId )
	{
		if( wp_is_post_revision( $postId ) )
			return;
		
		if( "product" == get_post_type( $postId ) && ! empty( $_POST['fortnox_sync_product'] ) ) {
			try {
				Products::sync( $_POST['ID'] );
			}
			catch( Exception $error ) {
				// Silently fail
			}
		}
		
		if( isset( $_REQUEST['post_type'] ) && "shop_order" == $_REQUEST['post_type'] && ! empty( $_POST['fortnox_sync_order'] ) ) {
			try {
				Orders::sync( $_POST['ID'] );
			}
			catch( Exception $error ) {
				// Silanetly fail
			}
		}
	}
}