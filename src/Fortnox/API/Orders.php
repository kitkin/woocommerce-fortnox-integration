<?php

namespace Fortnox\API;

use Exception;
use WC_Order;
use WC_Product;
use WC_Tax;
use Fortnox\API\Request;
use Fortnox\API\Customers;
use Fortnox\API\Products;
use Fortnox\API\Invoices;

class Orders {
	/**
	 * Check wether order synced to Fortnox
	 *
	 * TODO: Change meta_key to '_fortnox_synced'
	 *
	 * @param int $orderId
	 */
	static public function isSynced( $orderId ) {
		return get_post_meta( $orderId, '_fortnox_order_synced', true );
	}
	
	/**
	 * Sync order to Fortnox
	 *
	 * @param int $orderId
	 */
	static public function sync( $orderId ) {
		$order = new WC_Order( $orderId );
		
		if( "completed" != $order->get_status() )
			return false;
		
		$address = $order->get_address();
		$customer = [
			'Email' => $address['email'],
			'Name' => ! empty( $address['company'] ) ? $address['company'] : $address['first_name'] . ' ' . $address['last_name'],
			'Type' => ( ! empty( $address['company'] ) ? "COMPANY" : "PRIVATE" ),
			'Address1' => $address['address_1'],
			'Address2' => $address['address_2'],
			'ZipCode' => $address['postcode'],
			'City' => $address['city'],
			'CountryCode' => $address['country'],
			'DeliveryAddress1' => 'ShowMeWhatYouGot'
		];
		$vatNumber = get_post_meta( $orderId, 'vat_number', true );
		$euCountries = [ 'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL',
				'ES', 'FI', 'FR', 'GB', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV',
				'MT', 'NL', 'PL', 'PT', 'RO', 'SI', 'SK' ];
		
		// Set customer VAT type based on country
		if( ! empty( $vatNumber ) ) {
			$customer['VATNumber'] = $vatNumber;
			
			if( "SE" == $address['country'] )
				$customer['VATType'] = "SEVAT";
			
			elseif( in_array( $address['country'], $euCountries ) )
				$customer['VATType'] = "EUREVERSEDVAT";
			
			else
				$customer['VATType'] = "EXPORT";
		}
		elseif( empty( $vatNumber ) && "SE" != $address['country'] && ! in_array( $address['country'], $euCountries ) )
			$customer['VATType'] = "EXPORT";
		
		try {
			$customerNumber = Customers::sync( $customer );
			$orderRows = [];
			$tax = new WC_Tax();
			$accountSales25 = get_option( 'fortnox_account_vat_25' );
			$accountSales12 = get_option( 'fortnox_account_vat_12' );
			$accountSales6 = get_option( 'fortnox_account_vat_6' );
			$accountSalesEU = get_option( 'fortnox_account_sales_eu' );
			$accountVATEU = get_option( 'fortnox_account_vat_eu' );
			$accountSalesExport = get_option( 'fortnox_account_sales_export' );
			
			foreach( $order->get_items() as $item ) {
				if( ! empty( $item['variation_id'] ) )
					$product = new WC_Product( $item['variation_id'] );
				else
					$product = new WC_Product( $item['product_id'] );
				
				Products::sync( $product->id );
				
				$orderRow = [
					'ArticleNumber' => $product->get_sku(),
					'Description' => $item['name'],
					'DeliveredQuantity' => $item['item_meta']['_qty'][ 0 ],
					'OrderedQuantity' => $item['item_meta']['_qty'][ 0 ],
					'Unit' => "st",
					'Price' => $product->get_regular_price(),
					'Discount' => $product->get_regular_price() - $product->get_price(),
					'DiscountType' => "AMOUNT"
				];
				
				if( isset( $customer['VATType'] ) && "EXPORT" == $customer['VATType'] && ! empty( $accountSalesExport ) ) {
					$orderRow['AccountNumber'] = $accountSalesExport;
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
								$orderRow['AccountNumber'] = $accountSales25;
							break;
						
						case 12:
							if( ! empty( $accountSales12 ) )
								$orderRow['AccountNumber'] = $accountSales12;
							break;
						
						case 6:
							if( ! empty( $accountSales6 ) )
								$orderRow['AccountNumber'] = $accountSales6;
							break;
					}
				}
				
				$orderRows[] = $orderRow;
			}
			
			// var_dump($address['country']);
			// echo "<br><br><br>";
			// var_dump(WC()->countries->countries);
			// echo "<br><br><br>";
			// return false;
			$countries_sweden = array ("AF" => "Afghanistan","AL" => "Albanien","DZ" => "Algeriet","VI" => "Amerikanska Jungfruöarna","AS" => "Amerikanska Samoa","AD" => "Andorra","AO" => "Angola","AI" => "Anguilla","AQ" => "Antarktis","AG" => "Antigua och Barbuda","AR" => "Argentina","AM" => "Armenien","AW" => "Aruba","AU" => "Australien","AZ" => "Azerbajdzjan","BS" => "Bahamas","BH" => "Bahrain","BD" => "Bangladesh","BB" => "Barbados","BE" => "Belgien","BZ" => "Belize","BJ" => "Benin","BM" => "Bermuda","BT" => "Bhutan","BO" => "Bolivia","BA" => "Bosnien och Hercegovina","BW" => "Botswana","BV" => "Bouvetön","BR" => "Brasilien","VG" => "Brittiska Jungfruöarna","IO" => "Brittiska territoriet i Indiska  Oceanen","BN" => "Brunei","BG" => "Bulgarien","BF" => "Burkina Faso","BI" => "Burundi","KY" => "Caymanöarna","CF" => "Centralafrikanska republiken","CL" => "Chile","CO" => "Colombia","CK" => "Cooköarna","CR" => "Costa Rica","CY" => "Cypern","DK" => "Danmark","TF" => "De franska territorierna i södra Indiska  oceanen","CD" => "Demokratiska republiken Kongo","DJ" => "Djibouti","DM" => "Dominica","DO" => "Dominikanska republiken","EC" => "Ecuador","EG" => "Egypten","GQ" => "Ekvatorialguinea","SV" => "El Salvador","CI" => "Elfenbenskusten","ER" => "Eritrea","EE" => "Estland","ET" => "Etiopien","FO" => "Färöarna","AE" => "Förenade Arabemiraten","GB" => "Förenade kungariket","US" => "Förenta staterna","UM" => "Förenta staternas mindre öar i Oceanien och Västindien","FK" => "Falklandsöarna","FJ" => "Fiji","PH" => "Filippinerna","FI" => "Finland","FR" => "Frankrike","GF" => "Franska Guyana","PF" => "Franska Polynesien","GA" => "Gabon","GM" => "Gambia","GE" => "Georgien","GH" => "Ghana","GI" => "Gibraltar","GL" => "Grönland","GR" => "Grekland","GD" => "Grenada","GP" => "Guadeloupe","GU" => "Guam","GT" => "Guatemala","GN" => "Guinea","GW" => "Guinea-Guinea-Bissau","GY" => "Guyana","HT" => "Haiti","HM" => "Heardön och McDonaldöarna","VA" => "Heliga stolen","HN" => "Honduras","HK" => "Hongkong","IN" => "Indien","ID" => "Indonesien","IQ" => "Irak","IR" => "Iran","IE" => "Irland","IS" => "Island","IL" => "Israel","IT" => "Italien","JM" => "Jamaica","JP" => "Japan","JO" => "Jordanien","YU" => "Jugoslavien","MK" => "f.d. jugoslaviska republiken Makedonien","CX" => "Julön","KH" => "Kambodja","CM" => "Kamerun","CA" => "Kanada","CV" => "Kap Verde","KZ" => "Kazakstan","KE" => "Kenya","CN" => "Kina","KG" => "Kirgizistan","KI" => "Kiribati","CC" => "Cocos_(Keeling)Kokosöarna","KM" => "Komorerna","CG" => "Kongo","HR" => "Kroatien","CU" => "Kuba","KW" => "Kuwait","LA" => "Laos","LS" => "Lesotho","LV" => "Lettland","LB" => "Libanon","LR" => "Liberia","LY" => "Libyen","LI" => "Liechtenstein","LT" => "Litauen","LU" => "Luxemburg","MO" => "Macao","MG" => "Madagaskar","MW" => "Malawi","MY" => "Malaysia","MV" => "Maldiverna","ML" => "Mali","MT" => "Malta","MA" => "Marocko","MH" => "Marshallöarna","MQ" => "Martinique","MR" => "Mauretanien","MU" => "Mauritius","YT" => "Mayotte","MX" => "Mexiko","FM" => "Mikronesien","MZ" => "Moçambique","MD" => "Moldavien","MC" => "Monaco","MN" => "Mongoliet","MS" => "Montserrat","MM" => "Myanmar","NA" => "Namibia","NR" => "Nauru","NL" => "Nederländerna","AN" => "Nederländska Antillerna","NP" => "Nepal","NI" => "Nicaragua","NE" => "Niger","NG" => "Nigeria","NU" => "Niue","KP" => "Nordkorea","MP" => "Nordmarianerna","NF" => "Norfolkön","NO" => "Norge","NC" => "Nya Kaledonien","NZ" => "Nya Zeeland","OM" => "Oman","AT" => "Österrike","TL" => "Östtimor","PK" => "Pakistan","PW" => "Palau","PA" => "Panama","PG" => "Papua Nya Guinea","PY" => "Paraguay","PE" => "Peru","PN" => "Pitcairn","PL" => "Polen","PT" => "Portugal","PR" => "Puerto Rico","QA" => "Qatar","RE" => "Réunion","RO" => "Rumänien","RW" => "Rwanda","RU" => "Ryssland","ST" => "São Tomé och Príncipe","KN" => "Saint Christopher och Nevis","SH" => "Saint Helena","LC" => "Saint Lucia","PM" => "Saint Pierre och Miquelon","VC" => "Saint Vincent och Grenadinerna","SB" => "Salomonöarna","WS" => "Samoa","SM" => "San Marino","SA" => "Saudiarabien","CH" => "Schweiz","SN" => "Senegal","SC" => "Seychellerna","SL" => "Sierra Leone","SG" => "Singapore","SK" => "Slovakien","SI" => "Slovenien","SO" => "Somalia","ES" => "Spanien","LK" => "Sri Lanka","SD" => "Sudan","SR" => "Surinam","SJ" => "Svalbard och Jan Mayen","SE" => "Sverige","SZ" => "Swaziland","ZA" => "Sydafrika","GS" => "Sydgeorgien och Sydsandwichöarna","KR" => "Sydkorea","SY" => "Syrien","TJ" => "Tadzjikistan","TW" => "Taiwan","TZ" => "Tanzania","TD" => "Tchad","TH" => "Thailand","CZ" => "Tjeckien","TG" => "Togo","TK" => "Tokelau","TO" => "Tonga","TT" => "Trinidad och Tobago","TN" => "Tunisien","TR" => "Turkiet","TM" => "Turkmenistan","TC" => "Turks- och Caicosöarna","TV" => "Tuvalu","DE" => "Tyskland","UG" => "Uganda","UA" => "Ukraina","HU" => "Ungern","UY" => "Uruguay","UZ" => "Uzbekistan","EH" => "Västsahara","VU" => "Vanuatu","VE" => "Venezuela","VN" => "Vietnam","BY" => "Vitryssland","WF" => "Wallis och Futuna","YE" => "Yemen","ZM" => "Zambia","ZW" => "Zimbabwe");

			$fortnoxOrder = [
				'Address1' => $address['address_1'],
				'Address2' => $address['address_2'],
				'City' => $address['city'],
				'Country' => $countries_sweden[ $address['country'] ],
				'CustomerNumber' => $customerNumber,
				'DocumentNumber' => apply_filters( 'woocommerce_order_number', $orderId, $order ), 
				'Freight' => $order->get_total_shipping(),
				'OrderRows' => $orderRows,
				'ZipCode' => $address['postcode']
			];
			
			if( get_option( 'fortnox_write_payment_type_to_ordertext' ) )
				$fortnoxOrder['Remarks'] = get_post_meta( $order->id, '_payment_method', true );
			
			if( $shipping = $order->get_items( 'shipping' ) ) {
				$shipping = reset( $shipping );
				$fortnoxShippingCode = get_option( "fortnox_shipping_code_" . $shipping['method_id'] );
				
				if( $fortnoxShippingCode )
					$fortnoxOrder['WayOfDelivery'] = $fortnoxShippingCode;
			}
			
			$response = Request::make( "POST", "/orders", [
				'Order' => $fortnoxOrder
			] );
			
			// Set order as synced
			update_post_meta( $orderId, '_fortnox_order_synced', 1 );
			
			if( get_option( 'fortnox_auto_create_order_invoice' ) )
				Invoices::create( $orderId );
		}
		catch( Exception $error ) {
			throw new Exception( $error->getMessage() );
		}
	}
}