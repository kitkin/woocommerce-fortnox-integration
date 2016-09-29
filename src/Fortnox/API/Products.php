<?php

namespace Fortnox\API;

use Exception;
use WC_Product;
use WC_Tax;
use Fortnox\Plugin;
use Fortnox\API\Request;

class Products 
{
	/**
	 * Check wether product is synced to Fortnox
	 *
	 * TODO: Change to '_fortnox_synced'
	 *
	 * @param int $orderId
	 */
	static public function isSynced( $productId ) 
	{
		$product = new WC_Product( $productId );
		
		if( $product->has_child() ) {
			foreach( $product->get_children() as $variationId )
				if( ! get_post_meta( $variationId, '_is_synced_to_fortnox', true ) )
					return false;
			
			return true;
		}
		
		else
			return get_post_meta( $product->id, '_is_synced_to_fortnox', true );
	}
	
	/**
	 * Has variations
	 *
	 * @param int $productId
	 */
	public static function hasVariations( $productId ) 
	{
		return get_posts( [
			'post_parent' => $productId,
			'post_status' => "any",
			'post_type' => "product_variation",
			'numberposts' => -1
		] );
	}
	
	/**
	 * Sync product to Fortnox
	 *
	 * @param int $productId
	 */
	static public function sync( $productId ) 
	{
		$product = new WC_Product( $productId );
		
		if( $variations = self::hasVariations( $productId ) ) {
			if( ! get_option( 'fortnox_skip_product_variations' ) )
				foreach( $variations as $variation )
					self::sync( $variation->ID );
			
			if( ! get_option( 'fortnox_sync_master_product' ) )
				return;
		}
		
		$sku = $product->get_sku();
		
		if( ! $sku )
			throw new Exception( __( "Product ID {$productId} is missing SKU", Plugin::TEXTDOMAIN ) );
		
		try {
			$article = [
				'ArticleNumber' => $sku,
				'Description' => $product->get_title()
			];
			
			if( $purchaseAccount = get_option( 'fortnox_account_purchases' ) )
				$article['PurchaseAccount'] = $purchaseAccount;
			
			if( $inStock = $product->get_stock_quantity() ) {
				$article['StockGoods'] = true;
				$article['QuantityInStock'] = $inStock;
			}
			
			$priceList = empty( get_option( 'fortnox_default_price_list' ) ) 
				? "A" 
				: get_option( 'fortnox_default_price_list' );
			$price = [
				'ArticleNumber' => $sku,
				'FromQuantity' => 0,
				'Price' => get_option( 'woocommerce_prices_include_tax' )
					? $product->get_price_including_tax() 
					: $product->get_price_excluding_tax(),
				'PriceList' => $priceList,
			];
			
			$searchArticleResponseCode = Request::getResponseCode( "GET", "/articles?articlenumber=" . $sku );
			
			if( 2000513 == $searchArticleResponseCode )
				$response = Request::make( "POST", "/articles", [ 'Article' => $article ] );
			else {
				try {
					$response = Request::make( "PUT", "/articles/{$sku}", [ 'Article' => $article ] );
				}
				catch( Exception $error ) {
					// UGLYFIX: Add the product
					if( 2000513 == $error->getCode() )
						$response = Request::make( "POST", "/articles", [ 'Article' => $article ] );
				}
			}
			
			$searchPriceResponseCode = Request::getResponseCode( "GET", "/prices/{$priceList}/{$sku}/0" );
			
			if( 2000430 == $searchPriceResponseCode )
				$response = Request::make( "POST", "/prices", [ 'Price' => $price ] );
			
			else
				$response = Request::make( "PUT", "/prices/{$priceList}/{$sku}/0", [ 'Price' => $price ] );
			
			$response = Request::make( "PUT", "/prices/{$priceList}/{$sku}/0", [ 'Price' => [ 'Price' => $price['Price'] ] ] );
			
			update_post_meta( $productId, '_is_synced_to_fortnox', 1 );
			
			return $sku;
		}
		catch( Exception $error ) {
			throw new Exception( $error->getMessage() );
		}
	}
	
	/**
	 * Update stock from Fortnox
	 *
	 * @param $productId
	 */
	static public function updateStockFromFortnox( $productId ) 
	{
		$product = new WC_Product( $productId );
		
		if( $variations = self::hasVariations( $productId ) ) {
			if( ! get_option( 'fortnox_skip_product_variations' ) )
				foreach( $variations as $variation )
					self::updateStockFromFortnox( $variation->ID );
			
			if( ! get_option( 'fortnox_sync_master_product' ) )
				return;
		}
		
		$sku = $product->get_sku();
		
		if( ! $sku )
			throw new Exception( __( "Product ID {$productId} is missing SKU.", Plugin::TEXTDOMAIN ) );
		
		try {
			$response = Request::make( "GET", "/articles/{$sku}" );
			
			if( isset( $response->Article->QuantityInStock ) ) {
				if( 0 == $response->Article->QuantityInStock )
					update_post_meta( $productId, '_stock_status', "outofstock" );
				else
					update_post_meta( $productId, '_stock_status', "instock" );
				
				update_post_meta( $productId, '_stock', $response->Article->QuantityInStock );
			}
		}
		catch( Exception $error ) {
			throw new Exception( "Product ID {$productId}: " . $error->getMessage() );
		}
	}
	
	/**
	 * Update price from Fortnox
	 *
	 * @param $productId
	 */
	static public function updatePriceFromFortnox( $productId ) 
	{
		$product = new WC_Product( $productId );
		$sku = $product->get_sku();
		
		if( ! $sku )
			throw new Exception( __( "Product ID {$productId} is missing SKU.", Plugin::TEXTDOMAIN ) );
		
		$priceList = empty( get_option( 'fortnox_default_price_list' ) ) 
			? "A" 
			: get_option( 'fortnox_default_price_list' );
		$response = Request::make( "GET", "/prices/{$priceList}/{$sku}" );
		
		if( ! empty( $response->ErrorInformation ) )
			throw new Exception( "{$response->ErrorInformation->message} (Felkod: {$response->ErrorInformation->code})" );
		
		update_post_meta( $productId, '_regular_price', $response->Price->Price );
		
		if( ! get_post_meta( $productId, '_sale_price', true ) )
			update_post_meta( $productId, '_price', $response->Price->Price );
	}
	
	/**
	 * Get product from Fortnox
	 *
	 * @param $sku
	 */
	static public function get( $sku ) 
	{
		try {
			$response = Request::make( "GET", "/article/{$sku}" );
			
			return $response->Article;
		}
		catch( Exception $error ) {
			throw new Exception( $error->getMessage() );
		}
	}
	
	/**
	 * Delete product from Fortnox
	 *
	 * @param $sku
	 */
	static public function drop( $sku ) 
	{
		Request::make( "DELETE", "/articles/{$sku}" );
	}
}