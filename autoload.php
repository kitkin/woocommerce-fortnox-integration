<?php

/**
 * PSR-4 compliant autoloader
 *
 * @param $path
 */
spl_autoload_register( function( $class ) {
	$file = plugin_dir_path( __FILE__ ) . "src/" . str_replace( "\\", "/", $class ) . ".php";
	
	if( ! file_exists( $file ) )
		return;
	
	require_once $file;
} );
