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

    /**
     * Page title for any webpage can be extracted from the file name that the server
     * is loading. For example, if a filename is employee-profile.php then this method
     * can extract a title as Employee Profile. The title can be capitalized and the
     * actual file separator can be specified by argument.
     *
     * @param bool $capitalize Whether to capitalize the title or not.
     * @param string $separator The page name can be separated by any character
     * specified here.
     *
     * @return string The page title as specified by the arguments.
     * */
    public static function fileTitle(bool $capitalize = true, string $separator = '-'): string {
        $page = basename($_SERVER['SCRIPT_FILENAME']);
        $page = str_replace('.php', '', $page);
        $page = str_replace($separator, ' ', $page);
        return $capitalize ? ucwords($page) : $page;
    }

    /**
     * Using this method, extra echo/print statement can be avoided to print out the
     * page title in the page.
     *
     * @param bool $capitalize Whether to capitalize the title or not.
     * @param string $separator The page name can be separated by any character
     * specified here.
     * */
    public static function printFileTitle(bool $capitalize = true, string $separator = '-'): void {
        echo self::fileTitle($capitalize, $separator);
    }

    /**
     * Tedious title and meta tag can be replaced with this method call. It no title
     * is provided then it tries to extract the title from the file name where file
     * name is separated by -. Optional capitalization can be set using the augment.
     * Internally this method uses @link fileTitle method to obtain the file title
     * from the file name.
     *
     * @param string $title Any specified title to override the file name as title.
     * @param bool $capitalize Whether to capitalize the file name in title output.
     * @param string $separatorInFileName It indicates how the file name should be extracted.
     * By default it is '-' which means that the file name has '-' in file names. For example
     * 'employee-profile.php' will be extracted as Employee Profile.     *
     * */
    public static function titleTag(string $title = '', bool $capitalize = true, string $separatorInFileName = '-') {
        if (empty($title)) $title = self::fileTitle($capitalize, $separatorInFileName);

        echo '<meta charset="UTF-8">';
        echo '<title>'. $title .'</title>';
    }

    /**
     * All the tedious stylesheet linking in html pages can be replaced with
     * this method call. By default it looks for css files inside the style
     * directory in the root folder of the server. This can be changes using
     * folder argument. Folder name doesn't have any trailing slashes.
     *
     * @param string $files comma separated files names without css extension.
     * @param string $folder any folder structure where the css files are residing.
     * */
    public static function css(string $files, string $folder = 'style'): void {
        $files = explode(',', $files);
        foreach ($files as $file) {
            echo sprintf('<link rel="stylesheet" href="%s/%s.css">', $folder, trim($file));
        }
    }

    /**
     * All the tedious js importing in html pages can be replaced with
     * this method call. By default it looks for js files inside the js
     * directory in the root folder of the server. This can be changes using
     * folder argument. Folder name doesn't have any trailing slashes.
     *
     * @param string $files comma separated files names without js extension.
     * @param string $folder any folder structure where the js files are residing.
     * */
    public static function js(string $files, string $folder = 'js'): void {
        $files = explode(',', $files);
        foreach ($files as $file) {
            echo sprintf('<script src="%s/%s.js"></script>', $folder, trim($file));
        }
    }

}