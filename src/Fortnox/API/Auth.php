<?php

namespace Fortnox\API;

use Fortnox\Plugin;
use Exception;

class Auth
{
	/**
	 * Get access token
	 */
	static public function getAccessToken() 
	{
		$authCode = get_option( 'fortnox_auth_code' );
		$clientSecret = Plugin::CLIENT_SECRET;
		
		if( empty( $authCode ) || empty( $clientSecret ) )
			throw new Exception( "Authorisation code is empty." );
		
		$curl = curl_init();
		
		curl_setopt( $curl, CURLOPT_URL, "https://api.fortnox.se/3/" );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, [
			'Authorization-Code: ' . $authCode,
			'Client-Secret: ' . $clientSecret,
			'Content-Type: application/json',
			'Accept: application/json'
		] );
		
		$response = curl_exec( $curl );
		
		curl_close( $curl );
		
		if( ! $response = json_decode( $response )  )
			throw new Exception( "Unexpected response." );
		
		if( ! empty( $response->ErrorInformation ) )
			throw new Exception( "{$response->ErrorInformation->Message} (Felkod: {$response->ErrorInformation->Code})" );
		
		if( ! empty( $response->Authorization->AccessToken ) )
			update_option( 'fortnox_access_token', $response->Authorization->AccessToken );
		
		return $response->Authorization->AccessToken;
	}
}