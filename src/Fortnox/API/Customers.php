<?php

namespace Fortnox\API;

use Exception;
use Fortnox\API\Request;

class Customers {
	/**
	 * Get customer number by e-mail
	 *
	 * @param string $email
	 */
	static public function get( $email ) {
		try {
			$response = Request::make( "GET", "/customers?email=" . $email );
			
			if( ! empty( $response->Customers ) )
				return $response->Customers[ 0 ];
		}
		catch( Exception $error ) {
			throw new Exception( $error->getMessage() );
		}
	}
	
	/**
	 * Sync customer
	 *
	 * @param array $customer
	 */
	static public function sync( $customer ) {
		try {
			$existingCustomer = self::get( $customer['Email'] );
			
			// Create new customer
			if( empty( $existingCustomer ) ) {
				$response = Request::make( "POST", "/customers", [ 'Customer' => $customer ] );
				$customerNumber = $response->Customer->CustomerNumber;
			}
			
			// Update customer details
			elseif( ! empty( $existingCustomer ) ) {
				$customerNumber = $existingCustomer->CustomerNumber;
				$response = Request::make( "PUT", "/customers/" . $customerNumber, [ 
					'Customer' => $customer 
				] );
			}
			
			return $customerNumber;
		}
		catch( Exception $error ) {
			throw new Exception( $error->getMessage() );
		}
	}
}