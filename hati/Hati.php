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

    // version
    private static string $version = '3.2';

    private static float $BENCHMARK_START = 0;

    // This is the first method call of the server. It initializes the environment
    // as per configuration and resolve dependencies.
    public static function start(): void {

        self::setLoader();

        // start the benchmark if Hati is setup to include dev benchmark
        if(Hati::dev_API_benchmark()) self::$BENCHMARK_START = microtime(true);

        date_default_timezone_set(self::defaultTimezone());

        if (CONFIG['session_auto_start']) {
            // Cookies will only be sent in a first-party context and not be sent along with
            // requests initiated by third party websites.
            session_set_cookie_params(['SameSite' => 'Strict', 'Secure' => true]);
            session_start();
        }

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
     * root. At the end, you get an un-breaking, right document root.
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

    public static function dev_API_benchmark(): bool {
        return CONFIG['dev_API_benchmark'];
    }

    public static function dev_api_delay(): int {
        return CONFIG['dev_API_delay'];
    }

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

    public static function mailerPort(): int {
        return CONFIG['mailer_port'];
    }

    public static function mailerEmail(): string {
        return CONFIG['mailer_email'];
    }

    public static function mailerPass(): string{
        return CONFIG['mailer_pass'];
    }

    public static function mailerName(): string {
        return CONFIG['mailer_name'];
    }

    public static function mailerReplyTo() {
        return CONFIG['mailer_reply_to'];
    }

    public static function favicon(): string {
        return CONFIG['favicon'];
    }

    public static function version(): string {
        return Hati::$version;
    }

    public static function configObj(): array {
        return CONFIG;
    }

    public static function benchmarkStart(): float {
        return self::$BENCHMARK_START;
    }

    private static function printHati(): void {
        include('page/welcome.php');
    }

}

try {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . 'HatiConfig.php');
    Hati::start();
} catch (Throwable $t) {
    echo 'Hati encountered error while initializing: ' . $t -> getMessage();
}