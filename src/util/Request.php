<?php

namespace hati\util;

use InvalidArgumentException;

/**
 * Req class stands for Request handling. It can check whether a request is
 * done by GET or POST request. Using this, you can get the browser and OS
 * tag which can be very helpful in validating legitimate users.
 * */

abstract class Request {

	public static function isGET(): bool {
		return $_SERVER['REQUEST_METHOD'] == 'GET';
	}

	public static function isPOST(): bool {
		return $_SERVER['REQUEST_METHOD'] == 'POST';
	}

	public static function isPUT(): bool {
		return $_SERVER['REQUEST_METHOD'] == 'PUT';
	}

	public static function isDELETE(): bool {
		return $_SERVER['REQUEST_METHOD'] == 'DELETE';
	}

	public static function method(): string {
		return $_SERVER['REQUEST_METHOD'];
	}

	/**
	 * Checks whether the content type is set and the type is application/json.
	 *
	 * @return bool true if the content type is set and, it is application/json, false otherwise.
	 * */
	public static function contentTypeJSON(): bool {
		$headers = getallheaders();
		return (isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/json');
	}

	/**
	 * Extracts the request body either as 'json' or 'raw'.
	 *
	 * @param string $as The format you want to request body be in. Only supports 'raw' & 'json'.
	 * @throws InvalidArgumentException Throws InvalidArgumentException 'as' argument is neither json
	 * nor raw.
	 * @return array|string|null null when the data is not in right format or the content type is not matching
	 * as specified. Returns associative array if it is parsed successfully for json data. Otherwise,
	 * returns as string value.
	 */
	public static function body(string $as = 'json') : array|string|null {
		if (!in_array($as, ['json', 'raw'])) {
			throw new InvalidArgumentException('Argument must be either json or raw');
		}

		if ($as === 'json') {
			$data = json_decode(file_get_contents('php://input'), true);
			if (json_last_error() != JSON_ERROR_NONE) {
				return null;
			}

			return $data;
		}

		return file_get_contents('php://input');
	}

	/**
	 * Retrieve the client's IP address, considering proxy headers.
	 *
	 * This method attempts to retrieve the client's IP address by checking the 'HTTP_X_FORWARDED_FOR' header,
	 * which is often set by proxies or load balancers. If the header is present, it extracts the last IP address
	 * from the list. If the header is not present, it falls back to 'REMOTE_ADDR'.
	 *
	 * Additionally, the method performs a validation check to ensure that the obtained IP address is a valid IP
	 * and is not a private or reserved IP address. If the validation passes, the IP address is returned; otherwise,
	 * an empty string is returned, indicating that it's not safe to use the obtained IP address.
	 *
	 * @return string The client's IP address, or an empty string if not available or not safe to use.
	 */
	public static function ip(): string {
		$ip = '';

		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$x = $_SERVER['HTTP_X_FORWARDED_FOR'];
			$addresses = explode(',', $x);

			if (count($addresses) > 0) {
				// Use last IP address
				$ip = trim($addresses[count($addresses) - 1]);
			}
		}

		if ($ip == '') {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		// Ensure IP is an IP and that it is NOT private or reserved
		if (filter_var($ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE |
			FILTER_FLAG_NO_RES_RANGE
		)) {
			return $ip;
		}

		// Not safe to use this IP address
		return '';
	}

	/**
	 * Check if the current request is made over HTTPS.
	 *
	 * This function examines various server variables to determine if the request is using HTTPS.
	 * It checks the 'HTTPS' server variable, 'HTTP_X_FORWARDED_PROTO' header, and 'HTTP_FRONT_END_HTTPS' header.
	 * If any of these indicate that the request is over HTTPS, the function returns true; otherwise, it returns false.
	 *
	 * @return bool True if the request is made over HTTPS, false otherwise.
	 */
	function isHttps(): bool {
		if ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
			return true;
		} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
			return true;
		} elseif ( ! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
			return true;
		}
		return false;
	}

	/**
	 * OS name will be returned. This method is not exhausted list of
	 * OS name. A few number of popular OS is recognized by this method.
	 * See the function body for details. For unknown os it returns Unknown.
	 *
	 * @return string os name.
	 * */
	public static function os(): string {
		$userAgent = $_SERVER['HTTP_USER_AGENT'];
		$os  = "Unknown OS";

		$osArray  = array(
			'/windows nt 10/i'      =>  'Windows 10/11',
			'/windows nt 6.3/i'     =>  'Windows 8.1',
			'/windows nt 6.2/i'     =>  'Windows 8',
			'/windows nt 6.1/i'     =>  'Windows 7',
			'/windows nt 6.0/i'     =>  'Windows Vista',
			'/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
			'/windows nt 5.1/i'     =>  'Windows XP',
			'/windows xp/i'         =>  'Windows XP',
			'/windows nt 5.0/i'     =>  'Windows 2000',
			'/windows me/i'         =>  'Windows ME',
			'/win98/i'              =>  'Windows 98',
			'/win95/i'              =>  'Windows 95',
			'/win16/i'              =>  'Windows 3.11',
			'/macintosh|mac os x/i' =>  'Mac OS X',
			'/mac_powerpc/i'        =>  'Mac OS 9',
			'/linux/i'              =>  'Linux',
			'/ubuntu/i'             =>  'Ubuntu',
			'/iphone/i'             =>  'iPhone',
			'/ipod/i'               =>  'iPod',
			'/ipad/i'               =>  'iPad',
			'/android/i'            =>  'Android',
			'/blackberry/i'         =>  'BlackBerry',
			'/webos/i'              =>  'Mobile'
		);

		foreach ($osArray as $regex => $value) {
			if (preg_match($regex, $userAgent)) $os = $value;
		}
		return $os;
	}

	/**
	 * A string of browser information will be returned. This can only
	 * list a few number of popular browsers see the function body. For
	 * unknown os it returns Unknown.
	 *
	 * @return string browser name.
	 */
	public static function browser(): string {
		$userAgent =  $_SERVER['HTTP_USER_AGENT'];

		$browserList = [
			'Firefox' => '/Firefox/',
			'Edge' => '/Edg|Edge/',
			'Chrome' => '/Chrome|CriOS/',
			'Opera' => '/Opera|OPR/',
			'Safari' => '/Safari/',
			'Internet Explorer' => '/MSIE|Trident/',
			'Mobile Browser' => '/mobile/i'
		];

		
		foreach ($browserList as $browser => $pattern) {
			if (preg_match($pattern, $userAgent)) {
				// Special case: Distinguish Safari from Chrome
				if ($browser === 'Safari' && preg_match('/Chrome|CriOS/', $userAgent)) {
					continue;
				}
				
				return $browser;
			}
		}
		
		return 'Unknown Browser';
	}

	/**
	 * By using this method, script can acquire the system information
	 * such as browser and os info. A string consisting of browser name
	 * and os name seperated by comma will be returned.
	 *
	 * @return string browser,os name will be returned.
	 */
	public static function systemTag(): string {
		return self::browser() . '/' . self::os();
	}

}