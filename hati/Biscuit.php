<?php

namespace hati;

/*
 * Biscuit class gets its name from the cookie. Biscuit is a word that is interchangeably used
 * alternative to cookie in Bangladesh thus it got the name. This class has very straightforward
 * methods that stores, delete and manipulate cookies.
 *
 * This is also considered EGIC(EverGreen Improving Class) class. Improved methods and structural
 * change are continuously made to this class over time.
 * */

class Biscuit {

    /**
     * This method sets a cookie with specified set of arguments. It has a few default values in arguments.
     * By default, it stores the cookie on http only. The cookie is transmitted over SSL layer by default.
     * For expiry, it remembers the cookie as long as the browser remain opened.
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
    public static function giveAway(string $name, string $value, int $expire = 0, bool $secure = true, bool $httpOnly = true): bool {
        return setcookie($name, $value, $expire, '/', self::getDomain(), $secure, $httpOnly);
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