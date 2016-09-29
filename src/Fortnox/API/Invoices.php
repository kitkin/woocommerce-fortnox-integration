<?php

namespace Fortnox\API;

use Exception;
use WC_Order;
use WC_Product;
use WC_Tax;
use Fortnox\API\Request;
use Fortnox\API\Customers;

class Invoices {
	/**
	 * Create invoice out of order
	 */
	static public function create( $orderId ) {
		try {
			$order = new WC_Order( $orderId );
			$customerDetails = $order->get_address();
			$customer = Customers::get( $customerDetails['email'] );
			$priceList = empty( get_option( 'fortnox_default_price_list' ) ) 
				? "A" 
				: get_option( 'fortnox_default_price_list' );
			$invoice = [
				'CustomerNumber' => $customer->CustomerNumber,
				'Freight' => $order->get_total_shipping(),
				'YourOrderNumber' => apply_filters( 'woocommerce_order_number', $orderId, $order ),
				'PriceList' => $priceList
			];
			$invoiceRows = [];
			$items = $order->get_items();
			
			if( $shipping = $order->get_items( 'shipping' ) ) {
				$shipping = reset( $shipping );
				$fortnoxShippingCode = get_option( "fortnox_shipping_code_" . $shipping['method_id'] );
				
				if( $fortnoxShippingCode )
					$invoice['WayOfDelivery'] = $fortnoxShippingCode;
			}
			
			$tax = new WC_Tax();
			$accountSales25 = get_option( 'fortnox_account_vat_25' );
			$accountSales12 = get_option( 'fortnox_account_vat_12' );
			$accountSales6 = get_option( 'fortnox_account_vat_6' );
			$accountSalesEU = get_option( 'fortnox_account_sales_eu' );
			$accountVATEU = get_option( 'fortnox_account_vat_eu' );
			$accountSalesExport = get_option( 'fortnox_account_sales_export' );
			
			foreach( $items as $item ) {
				if( ! empty( $item['variation_id'] ) )
					$product = new WC_Product( $item['variation_id'] );
				else
					$product = new WC_Product( $item['product_id'] );
				
				$invoiceRow = [
					'ArticleNumber' => $product->get_sku(),
					'Description' => $item['name'],
					'DeliveredQuantity' => $item['item_meta']['_qty'][ 0 ],
					'Discount' => $product->get_regular_price() - $product->get_price(),
					'DiscountType' => "AMOUNT",
					'Price' => $product->get_regular_price(),
					'Unit' => "st"
				];
				
				if( isset( $customer->VATType ) && "EXPORT" == $customer->VATType && ! empty( $accountSalesExport ) ) {
					$invoiceRow['AccountNumber'] = $accountSalesExport;
				}
				else {
					$vatRate = 0;
					
					if( $taxClass = $product->get_tax_class() ) {
						$vatRate = $tax->get_rates( $taxClass );
						$vatRate = reset( $vatRate );
						$vatRate = intval( $vatRate['rate'] );
					}
					
					switch( $vatRate ) {
						default:
						case 25:
							if( ! empty( $accountSales25 ) )
								$invoiceRow['AccountNumber'] = $accountSales25;
							break;
						
						case 12:
							if( ! empty( $accountSales12 ) )
								$invoiceRow['AccountNumber'] = $accountSales12;
							break;
						
						case 6:
							if( ! empty( $accountSales6 ) )
								$invoiceRow['AccountNumber'] = $accountSales6;
							break;
					}
				}
								
				$invoiceRows[] = $invoiceRow;
			}
			
			$invoice['InvoiceRows'] = $invoiceRows;
			$response = Request::make( "POST", "/invoices", [ 'Invoice' => $invoice ] );
			
			if( ! empty( $response->ErrorInformation ) )
				throw new Exception( "{$response->ErrorInformation->Message} (Felkod: {$response->ErrorInformation->Code})" );
			
			$invoiceNumber = $response->Invoice->DocumentNumber;
			
			// Auto post invoices
			if( get_option( 'fortnox_auto_post_order_invoice' ) ) {
				$response = Request::make( "PUT", "/invoices/{$invoiceNumber}/bookkeep" );
				
				if( ! empty( $response->ErrorInformation ) )
					throw new Exception( "{$response->ErrorInformation->Message} (Felkod: {$response->ErrorInformation->Code})" );
			}
		}
		catch( Exception $error ) {
			throw new Exception( $error->getMessage() );
		}
	}
}