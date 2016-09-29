<?php

/**
 * Plugin Name: Fortnox integration for WooCommerce
 * Plugin URI: http://plugins.svn.wordpress.org/woocommerce-fortnox-integration/
 * Description: A Fortnox 3 API Interface. Synchronizes products, orders and more to Fortnox. Also updated inventory from Fortnox to WooCommerce.
 * Version: 3.0.12
 * Author: Advanced WP-Plugs
 * Author URI: http://wp-plugs.com
 * License: GPL2
 */

require_once "autoload.php";

use Fortnox\Plugin;
use Fortnox\AJAX;
use Fortnox\API\Orders;

/**
 * Load plugin textdomain
 */
add_action( 'plugins_loaded', function() {
	Plugin::loadTextdomain();
} );

/**
 * init
 */
add_action( 'init', function() {
	// Set sequential order number
	add_action( 'woocommerce_checkout_update_order_meta', [ "Fortnox\Plugin", "setSequentialOrderNumber" ], 10, 2 );
	add_action( 'woocommerce_process_shop_order_meta', [ "Fortnox\Plugin", "setSequentialOrderNumber" ], 10, 2 );
	add_action( 'woocommerce_before_resend_order_emails', [ "Fortnox\Plugin", "setSequentialOrderNumber" ], 10, 2 );
	add_action( 'woocommerce_api_create_order', [ "Fortnox\Plugin", "setSequentialOrderNumber" ], 10, 2 );
	add_action( 'woocommerce_deposits_create_order', [ "Fortnox\Plugin", "setSequentialOrderNumber" ], 10, 2 );
	
	// Get sequential order number
	add_filter( 'woocommerce_order_number', [ "Fortnox\Plugin", "getSequentialOrderNumber" ], 10, 2 );
} );

/**
 * admin_init
 */
add_action( 'admin_init', function() {
	// Add settings
	Plugin::addSettings();
	
	// Add admin scripts
	add_action( 'admin_enqueue_scripts', [ "Fortnox\Plugin", "addAdminScripts" ] );
	
	// Add Fortnox column to Orders table
	add_filter( 'manage_edit-shop_order_columns', [ "Fortnox\Plugin", "addOrdersTableColumns" ] );
	
	// Get Fornox column content to Orders table
	add_action( 'manage_shop_order_posts_custom_column', [ "Fortnox\Plugin", "printOrdersTableColumnContent" ], 10, 2 );
	
	// Add Fortnox column to product table
	add_filter( 'manage_edit-product_columns', [ "Fortnox\Plugin", "addProductsTableColumns" ] );
	
	// Get Fortnox column content to Products table
	add_action( 'manage_product_posts_custom_column', [ "Fortnox\Plugin", "printProductsTableColumnContent" ], 10, 2 );
	
	// Add Fortnox meta box to Product and Order views
	add_action( 'load-post.php', [ "Fortnox\Plugin", "addMetaBoxes" ] );
	add_action( 'load-post-new.php', [ "Fortnox\Plugin", "addMetaBoxes" ] );
	
	add_action( 'save_post', [ "Fortnox\Plugin", "syncChangesToFortnox" ] );
	add_action( 'woocommerce_order_status_completed', function( $orderId ) {
		try {
			if( ! Orders::isSynced( $orderId ) )
				Orders::sync( $orderId );
		}
		catch( Exception $error ) {
			wp_die( $error->getMessage() . " (Felkod: " . $error->getCode() . ")" );
		}
	} );
} );

/**
 * Add settings page
 */
add_action( 'admin_menu', function() {
	Plugin::addSettingsPage();
} );

/**
 * Update settings thorugh AJAX
 */
add_action( 'wp_ajax_fortnox_update_setting', function() {
	AJAX::updateSetting();
} );

/**
 * Fortnox bulk actions
 */
add_action( 'wp_ajax_fortnox_bulk_action', function() {
	AJAX::bulkAction();
} );

/**
 * Check Fortnox API key thorugh AJAX
 */
add_action( 'wp_ajax_check_fortnox_api_key', function() {
	AJAX::checkAPIKey();
} );

/**
 * Check Fortnox API code thorugh AJAX
 */
add_action( 'wp_ajax_check_fortnox_auth_code', function() {
	AJAX::checkAuthCode();
} );

/**
 * Fortnox AJAX API
 */
add_action( 'wp_ajax_fortnox_action', function() {
	AJAX::process();
} );


