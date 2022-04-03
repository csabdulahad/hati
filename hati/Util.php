<?php

namespace hati;

/**
 * Util class is a helper class which has many helpful methods that can easily deal with
 * session, cookie and other aspect of a project. This class is under continuous improvement
 * as we discover many helper simple functions over time.
 */

class Util {

    /**
     * Using this method, a session message can be set using the key from HatiConfig.php.
     * The key can be configured. The message is set without any escaping so it can be
     * containing manipulating code inside it. Always use @link sessVar method with
     * escaping turned on. Both message and redirect path is optional as it can support
     * no redirection by setting message or any redirection with no message.
     *
     * By default it redirect one directory up to from the current calling path.
     *
     * @param string $msg the message is to be hold in session variable.
     * @param string $to the location where redirection should go to.
     * */
    public static function redirect(string $msg = '', string $to = "../"): void {
        if (!empty($msg)) $_SESSION['msg'] = $msg;
        if (!empty($to)) {
            header("Location: $to");
            exit;
        }
    }

    /**
     * Any arbitrary session variable can be unset by this method using the given key.
     *
     * @param string $key The key for the value is to be unset.
     * */
    public static function unsetSess(string $key): void {
        unset($_SESSION[$key]);
    }

    /**
     * This method can display any previously set session message using the key from HatiConfig.php.
     * If there is not already set a message then this function doesn't print anything; just simply
     * returns.
     *
     * For decorating the presentation of the error UI or the error message containing div, an optional
     * css classes can be passed in as argument. The error message is kept inside a p(paragraph) tag.
     *
     * @param string $cssClass Optional css classes for decorating the UI.
     * */
    public static function displayMsg(string $cssClass = ''): void {
        if (!isset($_SESSION[Hati::sessionMsgKey()])) return;

        $msg = self::sessVar(Hati::sessionMsgKey());
        echo "<div class='$cssClass'><p>$msg</p></div>";
        self::unsetSess(Hati::sessionMsgKey());
    }

    /**
     * Any session variable can be accessed either with escaping/safe manner or without escaping.
     * This method first checks whether the session variable is set; if not then it simply returns
     * an empty string.
     *
     * The escaping can be turned off. By default, escaping is on.
     *
     * @param string $key Session variable key to get.
     * @param bool $escape Whether to escape the session variable or not.
     * */
    public static function sessVar(string $key, bool $escape = true): string {
        $set = isset($_SESSION[$key]);
        if (!$set) return '';

        $value = $_SESSION[$key];
        return  $escape ? htmlentities($value) : $value;
    }

    /**
     * Any cookie variable can be accessed either with escaping/safe manner or without escaping.
     * This method first checks whether the cookie variable is set; if not then it simply returns
     * an empty string.
     *
     * The escaping can be turned off. By default, escaping is on.
     *
     * @param string $key Cookie variable key to get.
     * @param bool $escape Whether to escape the cookie variable or not.
     * */
    public static function cookieVar(string $key, bool $escape = true): string {
        $set = isset($_COOKIE[$key]);
        if (!$set) return '';

        $value = $_COOKIE[$key];
        return  $escape ? htmlentities($value) : $value;
    }

    /**
     * A session variable is set, can be printed using this method. The printed value is always
     * escaped for safety so that XSS attack can be prevented.
     *
     * @param string $key The session variable key whose value is to be printed out.
     * */
    public static function printSessVar(string $key){
        echo self::sessVar($key);
    }


    /**
     * Any cookie variable is set, can be printed using this method. The printed value is always
     * escaped for safety so that XSS attack can be prevented.
     *
     * @param string $key The cookie variable key whose value is to be printed out.
     * */
    public static function printCookie(string $key) {
        echo self::cookieVar($key);
    }

    /**
     * This method adds given seconds, minutes, hours and day as seconds to current time. When no
     * argument is set, then it returns current in seconds. All the argument's value will be
     * converted into seconds before they gets added to the current time in second except the sec
     * argument.
     *
     * All the arguments values have to be of type integer. If not, then an exception is thrown.
     *
     * This method can come in handy in situations like setting cookie value with expiration,
     * calculating future date time etc.
     *
     * @param int $sec Number of seconds is to be added to the current time in second.
     * @param int $min Number of minutes is to be added to the current time in second.
     * @param int $hour Number of hours is to be added to the current time in second.
     * @param int $day Number of days is to be added to the current time in second.
     *
     * @return int Seconds added to the current time as defined by the arguments.
     *
     * @throws HatiError If all the arguments are not of type integer
     * */
    public static function secFromNowTo(int $sec = 0, int $min = 0, int $hour = 0, int $day = 0): int {
        if (!is_int($day) || !is_int($hour) || !is_int($min) || !is_int($sec))
            throw new HatiError('Make sure day, hour and minute are of type int.');

        $now = time();

        if ($sec != 0) $now += $sec;
        if ($min != 0) $now += $min * 60;
        if ($hour != 0) $now += $hour * 60 * 60;
        if ($day != 0) $now += $day * 24 * 60 * 60;

        return $now;
    }

    /**
     * A random token can be generated using this method. Default length
     * of the token is 11. It uses shuffling of time value after md5
     * encryption. However, it doesn't guarantee the uniqueness of the token.
     * Use @param int $length The length of the token.
     *
     * @return string A randomly generated token.
     * *@link uniqueId instead.
     *
     */
    public static function randToken(int $length = 11): string {
        return substr(str_shuffle(md5(time())),0, $length);
    }

    /**
     * A unique string using php uniqid can be generated by this method.
     * It uses more entropy to generate more random and unique string/id.
     *
     * @param string $prefix Any arbitrary string to be prefixed.
     *
     * @return string A unique string.
     * */
    public static function uniqueId(string $prefix = ''): string {
        return uniqid($prefix, true);
    }

}