<?php

namespace Fortnox\API;

use Exception;
use Fortnox\Plugin;
use Wetail\Credentials;

class Request
{
	/**
	 * Make an API request to Fortnox
	 *
	 * @param string $method
	 * @param string $path
	 * @param array $data
	 */
	static public function make( $method, $path, $data = [] ) {
		Credentials::check();
		
		$curl = curl_init();
		
		curl_setopt( $curl, CURLOPT_URL, "https://api.fortnox.se/3" . $path );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, [
			'Access-Token: ' . get_option( 'fortnox_access_token' ),
			'Client-Secret: ' . Plugin::CLIENT_SECRET,
			'Content-Type: application/json',
			'Accept: application/json'
		] );
		
		if( "POST" == $method )
			curl_setopt( $curl, CURLOPT_POST, true );
		
		if( "PUT" == $method || "DELETE" == $method )
			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $method );
		
		if( "POST" == $method || "PUT" == $method )
			curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $data ) );
		
		$response = curl_exec( $curl );
		
		curl_close( $curl );
		
		$response = json_decode( $response );
		
		if( self::isError( $response ) )
			throw new Exception( self::getError( $response, $method, $path, $data ), self::getErrorCode( $response ) );
		
		return $response;
	}
	
	/**
	 * Get response code
	 *
	 * @param $method
	 * @param $path
	 * @param $data
	 */
	public static function getResponseCode( $method, $path, $data = [] )
	{
		try {
			self::make( $method, $path, $data );
		}
		catch( Exception $error ) {
			return $error->getCode();
		}
	}
	
	/**
	 * Check if response has en error
	 *
	 * @param $response
	 */
	public static function isError( $response )
	{
		return isset( $response->ErrorInformation );
	}
	
	/**
	 * Extract error message and code from error response
	 *
	 * @param $response
	 */
	public static function getError( $response, $method = null, $path = null, $data = null )
	{
		$message = "Okänt fel";
		$code = "-";
		
		if( ! empty( $response->ErrorInformation->Message ) )
			$message = $response->ErrorInformation->Message;
		
		if( ! empty( $response->ErrorInformation->message ) )
			$message = $response->ErrorInformation->message;
		
		if( ! empty( $method ) && defined( 'WP_DEBUG' ) && WP_DEBUG )
			$message .= "\nMETHOD: {$method}\n";
		
		if( ! empty( $path ) && defined( 'WP_DEBUG' ) && WP_DEBUG )
			$message .= "PATH: {$path}\n";
		
		if( ! empty( $data ) && defined( 'WP_DEBUG' ) && WP_DEBUG )
			$message .= "METHOD: " . json_encode( $data ) . "\n";
		
		$code = self::getErrorCode( $response );
		
		return "{$message} (Felkod: {$code})";
	}
	
	/**
	 * Extract error code from error response
	 */
	public static function getErrorCode( $response )
	{
		if( ! empty( $response->ErrorInformation->Code ) )
			return $response->ErrorInformation->Code;
		
		if( ! empty( $response->ErrorInformation->code ) )
			return $response->ErrorInformation->code;
		
	}
}