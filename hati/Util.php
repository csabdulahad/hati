<?php

namespace hati;

use hati\trunk\TrunkErr;
use hati\trunk\TrunkInfo;
use hati\trunk\TrunkOK;
use hati\trunk\TrunkWarn;

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
     * @param string $to the location where redirection should go to.
     * @param string $msg the message is to be hold in session variable.
     * */
    public static function redirect(string $to = '../', string $msg = ''): void {
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
     * A random token can be generated using this method. Default length
     * of the token is 11. It uses shuffling of time value after md5
     * encryption. However, it doesn't guarantee the uniqueness of the token.
     * In order to get a unique id use @link uniqueId instead.
     *
     * @param int $len The length of the token.
     * @return string A randomly generated token.
     */
    public static function randToken(int $len = 11): string {
        return substr(str_shuffle(md5(time())),0, $len);
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
    public static function titleTag(string $title = '', bool $capitalize = true, string $separatorInFileName = '-'): void {
        if (empty($title)) $title = self::fileTitle($capitalize, $separatorInFileName);

        echo '<meta charset="UTF-8">'. PHP_EOL;
        echo '    <title>'. $title .'</title>'. PHP_EOL;

        // add favicon to the page
        if (!file_exists(Util::absolutePath('img/' . Hati::favicon()))) return;
        $path = Util::host() .  'img/' . Hati::favicon();
        echo '    <link rel="icon" type="image/x-icon" href="'. $path .'">'. PHP_EOL;
    }

    /**
     * All the tedious stylesheet linking in html pages can be replaced with
     * this method call. By default it looks for css files inside the css
     * directory in the root folder of the server. This can be changes using
     * folder argument. Folder name doesn't have any trailing slashes.
     *
     * The css files will be linked by absolute path to avoid broken link because
     * of directory structure changes by default.
     *
     * @param string $files comma separated files names without css extension.
     * @param string $folder any folder structure where the css files are residing.
     * @param bool $common indicates whether to include common css as defined in config.
     * */
    public static function css(string $files = '', string $folder = 'css', bool $common = true): void {
        if ($common) {
            foreach (Hati::common_css_files() as $file) {
                if(!file_exists(self::absolutePath("style/$file.css"))) continue;
                echo sprintf('    <link rel="stylesheet" href="%s/%s.css">' . PHP_EOL, Util::host() . 'css', $file);
            }
        }

        if (empty($files)) return;
        $files = explode(',', $files);
        foreach ($files as $file) {
            echo sprintf('    <link rel="stylesheet" href="%s/%s.css">' . PHP_EOL, Util::host() . $folder, trim($file));
        }
    }

    /**
     * All the tedious js importing in html pages can be replaced with
     * this method call. By default it looks for js files inside the js
     * directory in the root folder of the server. This can be changed using
     * folder argument. Folder name doesn't have any trailing slashes.
     *
     * The source will be linked by absolute path to avoid broken link because
     * of directory structure changes.
     *
     * It also tries to load all the js files listed in the config files with
     * file existence check by default.
     *
     * @param string $files comma separated files names without js extension.
     * @param string $folder any folder structure where the js files are residing.
     * @param bool $common indicates whether to include common scripts as defined in config.
     * */
    public static function js(string $files = '', string $folder = 'js', bool $common = true): void {
        if ($common) {
            foreach (Hati::common_js_files() as $file) {
                if(!file_exists(self::absolutePath("js/$file.js"))) continue;
                echo sprintf('    <script src="%s/%s.js"></script>' . PHP_EOL, Util::host() . 'js', $file);
            }
        }

        if (empty($files)) return;
        $files = explode(',', $files);
        foreach ($files as $file) {
            echo sprintf('    <script src="%s/%s.js"></script>' . PHP_EOL, Util::host() . $folder, trim($file));
        }
    }

    /**
     * Any php files inside the inc folder on project root folder can be included
     * simply passing their names without php extension at the end. Before each
     * inclusion it checks whether the files exists or not. If triggerError is on,
     * then it throws exception, otherwise it ignores that inclusion.
     *
     * @param string $files comma separated php files names to be included.
     * @param string $folder any folder structure where the php files are residing.
     * @param bool $throwErr indicates whether to throw exception on unresolved file.
     * */
    public static function inc(string $files, string $folder = 'inc', bool $throwErr = false): void {
        $files = explode(',', $files);
        foreach ($files as $file) {
            if(!file_exists(self::absolutePath('inc/' . $file . '.php'))) {
                if ($throwErr) throw new TrunkErr('Failed to include '. $file .'.php');
                continue;
            }
            include(sprintf('%s/%s.php', $folder, trim($file)));
        }
    }

    /**
     * The absolute path to a file can sometime be problematic to extract. Using this
     * method it can be done easily. This uses @link Hati::docRoot() method internally
     * to append the server document root to the path to point the file absolutely.
     *
     * @param string $filePath The file name with extension and any directory structure
     * appended in front it.
     *
     * @return string The absolute path to the file.
     * */
    public static function absolutePath(string $filePath): string {
        return Hati::neutralizeSeparator(Hati::docRoot() . $filePath);
    }

    /**
     * Any message can be thrown as Trunk success using this function.
     *
     * @param String $msg The message
     * @param int $lvl The intended audience of the message
     *
     * @throws TrunkOK
     * */
    public static function trunkOK(String $msg, int $lvl = Response::LVL_USER) {
        throw new TrunkOK($msg, $lvl);
    }

    /**
     * Any message can be thrown as Trunk info using this function.
     *
     * @param String $msg The message
     * @param int $lvl The intended audience of the message
     *
     * @throws TrunkInfo
     * */
    public static function trunkInfo(String $msg, int $lvl = Response::LVL_USER) {
        throw new TrunkInfo($msg, $lvl);
    }

    /**
     * Any message can be thrown as Trunk warning using this function.
     *
     * @param String $msg The message
     * @param int $lvl The intended audience of the message
     *
     * @throws TrunkWarn
     * */
    public static function trunkWarn(String $msg, int $lvl = Response::LVL_USER) {
        throw new TrunkWarn($msg, $lvl);
    }

    /**
     * Any message can be thrown as Trunk error using this function.
     *
     * @param String $msg The message
     * @param int $lvl The intended audience of the message
     *
     * @throws TrunkErr
     * */
    public static function trunkErr(String $msg, int $lvl = Response::LVL_USER) {
        throw new TrunkErr($msg, $lvl);
    }

    /**
     * This function can return the server root address. If the hati is inside
     * any folder then it also includes that as part of the host address.
     *
     * @return String the server address including folder if Hati has one defined
     * in HatiConfig file.
     * */
    public static function host(): string {
        $host = $_SERVER['HTTP_HOST'];
        if ($host == 'localhost') $host = 'localhost/' . Hati::rootFolder();
        return sprintf('http://%s/', $host);
    }

    /**
     * this function print out the host address
     * */
    public static function printHost() {
        echo self::host();
    }

    /**
     * JavaScript library Toast can use this cookie values to show any message after
     * loading the page. The JS Toast library unset/deletes these cookies after showing
     * the toast.
     * By default, it  shows message as info and hiders the toast after the 2sec delay.
     *
     * @param string $msg the message.
     * @param string $to optional url for redirection where the toast will be shown.
     * @param int $type toast type for success, info, warning and error.
     * @param string $autoHide whether to auto hide the toast.
     * @param int $delay the number seconds the toast will be displayed for.
     * */
    public static function toast(string $msg, string $to = '', int $type = Response::WARNING, string $autoHide = 'true', int $delay = 2) {
        Biscuit::giveAway('toast_msg', $msg, httpOnly: false);
        Biscuit::giveAway('toast_type', $type, httpOnly: false);
        Biscuit::giveAway('toast_auto_hide', $autoHide, httpOnly: false);
        Biscuit::giveAway('toast_delay', $delay, httpOnly: false);
        if (!empty($to)) header("Location: $to");
    }

}