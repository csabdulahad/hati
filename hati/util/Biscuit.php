<?php

namespace hati\util;

/*
 * Biscuit class gets its name from the cookie. Biscuit is a word that is interchangeably used
 * alternative to cookie in Bangladesh thus it got the name. This class has very straightforward
 * methods that stores, delete and manipulate cookies.
 * */

abstract class Biscuit {

	/**
	 * This method sets a cookie with specified set of arguments. It has a few default values in arguments.
	 * By default, it stores the cookie on http only. The cookie is transmitted over SSL layer by default.
	 * For expiry, it remembers the cookie as long as the browser remain opened.
	 *
	 * The samesite values are:
	 *
	 *      Strict : Cookies will only be sent in a first-party context and not be sent along with requests
	 *               initiated by third party websites.
	 *
	 *
	 *      None: Cookies will be sent in all contexts, i.e. in responses to both first-party and cross-origin
	 *            requests. If SameSite=None is set, the cookie Secure attribute must also be set
	 *            (or the cookie will be blocked).
	 *
	 *
	 *      Lax: Cookies are not sent on normal cross-site subrequests (for example to load images or frames
	 *           into a third party site), but are sent when a user is navigating to the origin site
	 *           (i.e., when following a link).
	 *
	 *           This is the default cookie value if SameSite has not been explicitly specified in recent
	 *           browser versions (see the "SameSite: Defaults to Lax" feature in the Browser Compatibility).
	 *
	 * @param string $name Name of the cookie.
	 * @param string $value Value for the cookie.
	 * @param int $expire Number of seconds to expire the cookie.
	 * @param bool $secure Whether to cookie transmission occurs over SSL layer.
	 * @param bool $httpOnly Whether the cookie is accessible to http only or not.
	 *
	 * @return bool If output exists prior to calling this function, setcookie will
	 * fail and return false. If setcookie successfully runs, it will return true.
	 * This does not indicate whether the user accepted the cookie.
	 */
	public static function giveAway(string $name, string $value, int $expire = 0, bool $secure = true, bool $httpOnly = true, string $sameSite = 'Strict'): bool {
		return setcookie($name, $value, [
			'expires' => $expire,
			'path' => '/',
			'domain' => self::getDomain(),
			'secure' => $secure,
			'httponly' => $httpOnly,
			'samesite' => $sameSite
		]);
	}

	/**
	 * A cookie can be deleted using this method. By default, the cookie is secure and only
	 * accessible to http only. This method immediately expires the cookie hence it is deleted
	 * immediately.
	 *
	 * @param string $name The cookie name is to be removed.
	 * @param bool $secure Whether to cookie transmission occurs over SSL layer.
	 * @param bool $httpOnly Whether the cookie is accessible to http only or not.
	 *
	 * @return bool If output exists prior to calling this function, setcookie will
	 * fail and return false. If setcookie successfully runs, it will return true.
	 * This does not indicate whether the user accepted the cookie.
	 * */
	public static function delete(string $name, bool $secure = true, bool $httpOnly = true): bool {
		return setcookie($name, '', 1, '/', self::getDomain(), $secure, $httpOnly);
	}

	private static function getDomain(): string {
		return ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
	}

}