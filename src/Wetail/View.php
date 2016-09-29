<?php
namespace Wetail;

use Mustache_Autoloader;
use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;

if( ! class_exists( __NAMESPACE__ . "\View" ) ):
class View
{
	/**
	 * Get Mustache instance (singleton)
	 */
	public static function getMustache() 
	{
		static $mustache = null;
		
		if( empty( $mustache ) ) {
			$pluginDir = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
			$templateDir = "{$pluginDir}assets/templates";
			$partialsDir = "{$templateDir}/partials";
			
			if( ! class_exists( 'Mustache_Autoloader' ) ) {
				require_once "{$pluginDir}vendor/mustache-php/src/Mustache/Autoloader.php";
				
				Mustache_Autoloader::register();
			}
			
			$mustache = new Mustache_Engine( [
				'loader' => new Mustache_Loader_FilesystemLoader( $templateDir, [
					'extension' => "ms"
				] ),
				'partials_loader' => new Mustache_Loader_FilesystemLoader( $partialsDir, [
					'extension' => "ms"
				] ),
				'cache' => WP_CONTENT_DIR . '/wp-content/cache/mustache',
				'helpers' => self::getMustacheHelpers()
			] );
		}
		
		return $mustache;
	}
	
	/**
	 * Get Mustache helpers
	 */
	public static function getMustacheHelpers() 
	{
		$helpers = [];
		
		$helpers['i18n'] = function( $text, $helper ) {
			return $helper->render( __( $text ) );
		};
		
		return $helpers;
	}
	
	/**
	 * Render view
	 *
	 * @param $template
	 * @param $data
	 */
	public static function render( $template, $data = [] )
	{
		$mustache = self::getMustache();
		
		print $mustache->render( $template, $data );
	}
}
endif;