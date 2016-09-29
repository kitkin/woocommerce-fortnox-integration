<?php

namespace Fortnox;

use Exception;
use Fortnox\Plugin;
use Fortnox\API\Auth;
use Fortnox\API\Orders;
use Fortnox\API\Products;
use WCF_API;
use WC_Product;

class AJAX {
	/**
	 * Send AJAX response
	 *
	 * @param array $data
	 */
	public static function respond( $data = [] )
	{
		$defaults = [
			'error' => false
		];
		$data = array_merge( $defaults, $data );
		
		die( json_encode( $data ) );
	}
	
	/**
	 * Send AJAX error
	 *
	 * @param string $message
	 */
	public static function error( $message )
	{
		self::respond( [ 'message' => $message ] );
	}
	
	/**
	 * Update settings through AJAX
	 */
	public static function updateSetting()
	{
		if( ! empty( $_REQUEST['settings'] ) )
			foreach( $_REQUEST['settings'] as $option => $value )
				if( 0 === strpos( $option, 'fortnox_' ) )
					update_option( $option, $value );
		
		self::respond();
	}
	
	/**
	 * Process AJAX request
	 */
	public static function process()
	{
		$response = [];
		
		switch( $_REQUEST['fortnox_action'] ) {
			// Sync orders to Fortnox
			case "sync_order":
				if( empty( $_REQUEST['order_id'] ) ) {
					$response = [
						'error' => true,
						'message' => __( "Missing order ID.", Plugin::TEXTDOMAIN )
					];
					break;
				}
				
				if( Orders::isSynced( $_REQUEST['order_id'] ) ) {
					$response = [
						'error' => true,
						'message' => __( "Order already synced to Fortnox.", Plugin::TEXTDOMAIN )
					];
					break;
				}
				
				try {
					Orders::sync( $_REQUEST['order_id'] );
				}
				catch( Exception $error ) {
					$response = [
						'error' => true,
						'message' => $error->getMessage()
					];
				}
				
				if( empty( $response['error'] ) )
					$response = [
						'error' => false,
						'message' => __( "Order successfully synced.", Plugin::TEXTDOMAIN )
					];
				
				break;
			
			// Sync product to Fortnox
			case "sync_product":
				if( empty( $_REQUEST['product_id'] ) ) {
					$response = [
						'error' => true,
						'message' => __( "Missing product ID.", Plugin::TEXTDOMAIN )
					];
					break;
				}
				
				try {
					Products::sync( $_REQUEST['product_id'] );
				}
				catch( Exception $error ) {
					$response = [
						'error' => true,
						'message' => $error->getMessage()
					];
				}
				
				if( empty( $response['error'] ) )
					$response = [
						'error' => false,
						'message' => __( "Product successfully synced.", Plugin::TEXTDOMAIN )
					];
				
				break;
			
			// Invalid action
			default:
				$response = [
					'error' => true,
					'message' => __( "Invalid action specified.", Plugin::TEXTDOMAIN )
				];
				break;
		}
		
		self::respond( $response );
	}
	
	/**
	 * Do bulk action through AJAX
	 */
	public static function bulkAction()
	{
		global $wpdb;
		
		$response = [ 'error' => false ];
		
		if( empty( $_REQUEST['bulk'] ) )
			self::error( "Bulk action is missing." );
		
		switch( $_REQUEST['bulk'] ) {
			// Upload products to Fortnox
			case "fortnox_sync_products":
				$errors = [];
				$posts = get_posts( [
					'numberposts' => -1,
					'post_type' => "product",
					'post_status' => "any"
				] );
				$synced = 0;
				
				foreach( $posts as $post ) {
					try {
						Products::sync( $post->ID );
					}
					catch( Exception $error ) {
						$errors[] = $error->getMessage();
					}
					
					$synced++;
				}
				
				$response['message'] = sprintf( __( "Synced %d articles." . ( ! empty( $errors ) ? "\n\n" . join( "\n", $errors ) : "" ), Plugin::TEXTDOMAIN ), $synced );
				break;
			
			// Synchronize inventory from Fortnox to WooCommerce
			case "fortnox_sync_inventory":
				$posts = get_posts( [
					'numberposts' => -1,
					'post_type' => "product",
					'post_status' => "any"
				] );
				$synced = 0;
				$notices = [];
				
				foreach( $posts as $post ) {
					try {
						Products::updateStockFromFortnox( $post->ID );
						
						$synced++;
					}
					catch( Exception $error ) {
						$notices[] = $error->getMessage();
					}
				}
				
				$response['message'] = sprintf( __( "%d WooCommerce products are updated.", Plugin::TEXTDOMAIN ), $synced );
				
				if( ! empty( $notices ) )
					$response['message'] .= "\n\n" . join( "\n", $notices );
				break;
			
			case "fortnox_find_missing_products":
				$posts = get_posts( [
					'numberposts' => -1,
					'post_type' => "product",
					'post_status' => "any"
				] );
				
				$missing = [];
				
				foreach( $posts as $post ) {
					$product = new WC_Product( $post->ID );
					$sku = $product->get_sku();
					
					if( empty( $sku ) ) {
						$missing[] = "Product ID {$post->ID} is missing SKU.";
						
						continue;
					}
						
					try {
						if( ! Products::get( $sku ) )
							$missing[] = "Product ID {$post->ID} with SKU {$sku} is missing in Fortnox.";
					}
					catch( Exception $error ) {
						$response = [
							'error' => true,
							'message' => $error->getMessage()
						];
						
						break;
					}
				}
				
				if( ! empty( $missing ) )
					$response['message'] = __( "Found following products missing on Fortnox:\n\n", Plugin::TEXTDOMAIN ) . join( "\n", $missing );
				
				else
					$response['message'] = __( "No missing products found.", Plugin::TEXTDOMAIN );
				
				/*
				include_once( self::getPath( "class-woo-fortnox-controller.php" ) );
				
			    $controller = new WC_Fortnox_Controller();
			    $response['message'] = $controller->diff_woo_fortnox_inventory();
			    */
				break;
			
			case "fortnox_sync_diff_orders":
				$diffs = $wpdb->get_results( "SELECT m.post_id, m.meta_value AS value
					FROM {$wpdb->postmeta} AS m
					INNER JOIN {$wpdb->posts} AS p ON p.ID = m.post_id
					WHERE 1 
						AND m.meta_key = '_fortnox_difference_order'
						AND p.post_status NOT IN ( 'wc-failed', 'wc-cancelled' );" );
				$orders = [];
				
				if( ! empty( $diffs ) )
				{
					foreach( $diffs as $diff )
					{
						if( abs( floatval( $diff->value ) ) > 1 )
						{
							$orders[] = $diff->post_id;
						}
					}
				}
				
				if( ! empty( $orders ) )
					$response['message'] = sprintf( __( "Diff orders: %s", Plugin::TEXTDOMAIN ), join( ', ', $orders ) );
				else
					$response['message'] = __( "All good, no diff orders found.", Plugin::TEXTDOMAIN );
				
				break;
			
			case "fortnox_flush_access_token":
				update_option( 'fortnox_access_token', '' );
				
				$response['message'] = __( "Access token has been removed.", Plugin::TEXTDOMAIN );
				
				break;
		}
		
		self::respond( $response );
	}
	
	/**
	 * Check API key
	 */
	public static function checkAPIKey()
	{
		if( ! get_option( 'fortnox_api_key' ) )
			die( json_encode( [
				'error' => true,
				'message' => __( "API key is empty. Forgot to save changes?", Plugin::TEXTDOMAIN )
			] ) );
		
		if( Auth::checkCredentials() )
			die( json_encode( [
				'error' => false,
				'message' => __( "API key is valid.", Plugin::TEXTDOMAIN )
			] ) );
		
		self::respond( [
			'error' => true,
			'message' => __( "Invalid API key.", Plugin::TEXTDOMAIN )
		] );
	}
	
	/**
	 * Check API code
	 */
	public static function checkAuthCode()
	{
		if( ! get_option( 'fortnox_auth_code' ) )
			self::error( __( "Authorization code is empty. Forgot to save changes?", Plugin::TEXTDOMAIN ) );
		
		if( ! empty( get_option( 'fortnox_access_token' ) ) )
			self::respond( [ 'message' => __( "Auth code is valid. Access token has been already generated. If you need to regenerate the access token please flush it using 'Flush access token' button under Bulk actions tab.", Plugin::TEXTDOMAIN ) ] );
		
		try {
			Auth::getAccessToken();
		}
		catch( Exception $error ) {
			self::error( $error->getMessage() );
		}
		
		self::respond( [ 'message' => __( "Authorisation code is OK. Access token has been generated.", Plugin::TEXTDOMAIN ) ] );
	}
}