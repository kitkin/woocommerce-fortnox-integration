<?php
namespace Wetail;

class Credentials
{
	/**
	 * Check user credentials
	 */
	static public function check() {
		// Remove this
		//return true;
		
		$license_key = get_option( 'fortnox_api_key' );

		if(!isset($license_key)){
		    return false;
		}

		// -----------------------------------
		//  -- Configuration Values --
		// -----------------------------------
		$whmcsurl = 'http://whmcs.onlineforce.net/'; //wfim-6df2e67e2999ae05a497 This was not the right API-key, will get a new one for you Monday morning.
		$licensing_secret_key = 'ak4763';
		$check_token = time() . md5(mt_rand(1000000000, 9999999999) . $license_key);
		$checkdate = date("Ymd");
		$domain = $_SERVER['SERVER_NAME'];
		$usersip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];
		$dirpath = dirname(__FILE__);
		$verifyfilepath = 'modules/servers/licensing/verify.php';
		
	    $postfields = array(
	        'licensekey' => $license_key,
	        'domain' => $domain,
	        'ip' => $usersip,
	        'dir' => $dirpath,
	    );
	    if ($check_token) $postfields['check_token'] = $check_token;
	    $query_string = '';
	    foreach ($postfields AS $k=>$v) {
	        $query_string .= $k.'='.urlencode($v).'&';
	    }
	    if (function_exists('curl_exec')) {
	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);
	        curl_setopt($ch, CURLOPT_POST, 1);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
	        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	        $data = curl_exec($ch);
	        curl_close($ch);
	    } else {
	        $fp = fsockopen($whmcsurl, 80, $errno, $errstr, 5);
	        if ($fp) {
	            $newlinefeed = "\r\n";
	            $header = "POST ".$whmcsurl . $verifyfilepath . " HTTP/1.0" . $newlinefeed;
	            $header .= "Host: ".$whmcsurl . $newlinefeed;
	            $header .= "Content-type: application/x-www-form-urlencoded" . $newlinefeed;
	            $header .= "Content-length: ".@strlen($query_string) . $newlinefeed;
	            $header .= "Connection: close" . $newlinefeed . $newlinefeed;
	            $header .= $query_string;
	            $data = '';
	            @stream_set_timeout($fp, 20);
	            @fputs($fp, $header);
	            $status = @socket_get_status($fp);
	            while (!@feof($fp)&&$status) {
	                $data .= @fgets($fp, 1024);
	                $status = @socket_get_status($fp);
	            }
	            @fclose ($fp);
	        }
	    }
	    
	    if (!$data) {
	        return false;
	    } else {
	        preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches);
	        $results = array();
	        foreach ($matches[1] AS $k=>$v) {
	            $results[$v] = $matches[2][$k];
	        }
	    }

	    logthis(print_r($results, true));
	    if (!is_array($results)) {
	        die("Invalid License Server Response");
	    }

	    if( empty( $results['md5hash'] ) ) {
	        return false;
	    }
	    
	    if ( ! empty( $results['md5hash'] ) ) {
	        if ($results['md5hash'] != md5($licensing_secret_key . $check_token)) {
	            return false;
	        }
	    }
	    
	    if ($results['status'] == "Active") {
	        $results['checkdate'] = $checkdate;
	        $data_encoded = serialize($results);
	        $data_encoded = base64_encode($data_encoded);
	        $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
	        $data_encoded = strrev($data_encoded);
	        $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
	        $data_encoded = wordwrap($data_encoded, 80, "\n", true);
	        $results['localkey'] = $data_encoded;
	    }
	    $results['remotecheck'] = true;

	    unset($postfields,$data,$matches,$whmcsurl,$licensing_secret_key,$checkdate,$usersip,$md5hash);

		// Return true on valid license
		if ($results["status"] == "Active") {
			return true;
		}

		return false;		
	}
	
}