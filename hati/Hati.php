<?php

/*                            _
                          .'``'.__
                         /      \ `'"-,
        .-''''--...__..-/ .     |      \
      .'               ; :'     '.  a   |
     /                 | :.       \     =\
    ;                   \':.      /  ,-.__;.-;`
   /|     .              '--._   /-.7`._..-;`
  ; |       '                |`-'      \  =|
  |/\        .   -' /     /  ;         |  =/
  (( ;.       ,_  .:|     | /     /\   | =|
   ) / `\     | `""`;     / |    | /   / =/
     | ::|    |      \    \ \    \ `--' =/
    /  '/\    /       )    |/     `-...-`
   /    | |  `\    /-'    /;
   \  ,,/ |    \   D    .'  \
    `""`   \  nnh  D_.-'L__nnh
            `"""`

    Hati - A Speedy PHP Library
    Version - 1.0
    RootData21 Inc.
*/


/**
 *
 * WARNING : DON'T MODIFY THIS CLASS ANY POINT BELOW THIS COMMENT.
 *
 * Hati, a speedy PHP library. This class does all the magic under the
 * hood. It gets the very first call from the server, before any code
 * can execute. This prepares dependencies by setting a class loader.
 *
 * It uses the configuration object to prepare the working environment
 * properly. Please use HatiConfig.php file in order to customize your
 * great HATI.
 *
 * WARNING : DON'T MODIFY THIS CLASS ANY POINT BELOW THIS COMMENT
 *
 * */

namespace hati;

use Throwable;

class Hati {

    // This is the first method call of the server. It initializes the environment
    // as per configuration and resolve dependencies.
    public static function start(): void {
        self::setLoader();
        date_default_timezone_set(self::defaultTimezone());

        if (CONFIG['session_auto_start']) session_start();
        if (CONFIG['welcome_hati']) self::printHati();
    }

    // This method sets up Hati auto loader which resolves all the dependencies
    // automatically by class file inclusion using server root with root folder
    // name provided by the configuration
    private static function setLoader(): void {
        spl_autoload_register(function ($className) {
            $file = self::docRoot() . $className . '.php';
            $file = self::neutralizeSeparator($file);
            if (class_exists($className)) return;
            if (file_exists($file)) include $file;
        });
    }

    /**
     * This method replaces slashes with system's directory separator.
     *
     * @param string $path the path including different directory separator
     * than server's one.
     *
     * @return string system's neutral path with directory separator.
    */
    public static function neutralizeSeparator(string $path): string {
        if (DIRECTORY_SEPARATOR == '\\') return str_replace('/', '\\', $path);
        return str_replace('\\', '/', $path);
    }

    /**
     * This returns the name of folder if all the resources and code files
     * are kept within that folder. This is really helpful to easily switch
     * between testing environment and live sever.
     *
     * @return string the name of the root folder defined by configuration
     * */
    public static function rootFolder(): string {
        return CONFIG['root_folder'];
    }

    /**
     * Using this method, code can get the path to the document root.
     * The speciality of this method is that it also considers the
     * root folder name given by configuration beside server's document
     * root. At the end, you get a un-breaking, right document root.
     *
     * @return string it returns calculated document root by configuration.
     * */
    public static function docRoot(): string {
        if (empty(self::rootFolder())) $ext = DIRECTORY_SEPARATOR;
        else $ext = DIRECTORY_SEPARATOR . self::rootFolder() . DIRECTORY_SEPARATOR;
        return self::neutralizeSeparator($_SERVER['DOCUMENT_ROOT']) . $ext;
    }

    /**
     * Using this method, code can get the current working path with no
     * trailing slash at the end.
     *
     * @return string it returns the current working path  of the file
     * being executed with no trailing slash.
    */
    public static function currentDir(): string {
        return self::neutralizeSeparator(getcwd());
    }

    /* the getters for the configurations */

    public static function docConfig(): array {
        return CONFIG['doc_config'];
    }

    public static function imgConfig(): array {
        return CONFIG['img_config'];
    }

    public static function videoConfig(): array {
        return CONFIG['video_config'];
    }

    public static function audioConfig(): array {
        return CONFIG['audio_config'];
    }

    public static function sessionMsgKey(): bool {
        return CONFIG['session_msg_key'];
    }

    public static function asJSONOutput(): bool {
        return CONFIG['as_JSON_output'];
    }

    public static function defaultTimezone(): string {
        return CONFIG['time_zone'];
    }

    public static function dbHost(): string {
        return CONFIG['db_host'];
    }

    public static function dbName(): string {
        return CONFIG['db_name'];
    }

    public static function dbUsername(): string {
        return CONFIG['db_username'];
    }

    public static function dbPassword(): string {
        return CONFIG['db_password'];
    }

    private static function printHati(): void {
        include('page/welcome.php');
    }

    public static function dPrint(string $string, int $numOfBreak = 1): void {
        echo $string;
        for ($i = 0; $i < $numOfBreak; $i++) echo '<br>';
    }

}

try {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . 'HatiConfig.php');
    Hati::start();
} catch (Throwable $t) {
    echo 'Hati encountered error while initializing: ' . $t -> getMessage();
}