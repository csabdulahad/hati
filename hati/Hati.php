<?php

/**
 *
 * WARNING : DON'T MODIFY THIS CLASS ANY POINT BELOW THIS COMMENT.
 *
 * Hati, a speedy PHP library. This class does all the magic under the
 * hood. It gets the very first call from the server, before any code
 * can execute. This prepares dependencies by setting a class loader.
 *
 * It uses the configuration object to prepare the working environment
 * properly. Please use hati.json file in order to customize your
 * great HATI.
 *
 * WARNING : DON'T MODIFY THIS CLASS ANY POINT BELOW THIS COMMENT.
 *
 * */

namespace hati;

use hati\hati_config\Key;
use RuntimeException;
use Throwable;

class Hati {

	// version
	private static string $version = '5.0.0';

	private static float $BENCHMARK_START = 0;

	private static ?object $loader = null;

	// The project root directory [where the vendor folder is found]
	private static ?string $DIR_ROOT = null;

	// The config directory
	private static ?string $DIR_CONFIG = null;

	// The hati configuration file is cached as json decoded array
	private static ?array $CONFIG = null;

	// The db configuration file is cached as json decoded array
	private static ?array $DB_CONFIG = [];

	/**
	 * This is the first method call of the execution. It initializes the environment
	 * as per configuration and resolve dependencies.
	 *
	 * @throws Throwable
	 */
	public static function start(): void {
		// calculate the project root folder
		self::$DIR_ROOT = realpath(dirname(__DIR__) . '../') . DIRECTORY_SEPARATOR;

		// register autoloader function
		Hati::$loader = require self::$DIR_ROOT . 'vendor/autoload.php';

		// load the correct config json file
		self::loadConfig();

		date_default_timezone_set(self::config(Key::TIME_ZONE));

		// start the benchmark if Hati is setup to include dev benchmark
		if(self::config(Key::DEV_API_BENCHMARK, 'bool'))
			self::$BENCHMARK_START = microtime(true);

		// set project root as include path
		if (self::config(Key::ROOT_AS_INCLUDE_PATH, 'bool'))
			set_include_path(self::root());

		if (self::config(Key::SESSION_AUTO_START, 'bool')) {
			// Cookies will only be sent in a first-party context and not be sent along with
			// requests initiated by third party websites.
			session_set_cookie_params(['SameSite' => 'Strict', 'Secure' => true]);
			session_start();
		}

		// Loads global functions as per configuration
		if (self::config(Key::USE_GLOBAL_FUNC, 'bool')) {
			require self::absPath('hati/GlobalFunc.php');
		}

		// include global php code files here
		$globalPHP = self::config(Key::GLOBAL_PHP, 'arr');
		foreach ($globalPHP as $file) {
			$file = trim($file);
			$path = self::absPath("$file.php");
			if (file_exists($path)) require_once $path;
		}
	}

	private static function loadConfig(): void {
		/*
		 * Calculate the config path by going a directory up each iteration to find
		 * any local hati/hati.json file to use
		 * */
		$cwd = getcwd();

		while ($cwd !== false) {
			$hatiJson = $cwd . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'hati.json';

			if (file_exists($hatiJson)) {
				self::$DIR_CONFIG = $cwd . DIRECTORY_SEPARATOR;
				break;
			}

			// go one directory up
			$cwd = realpath($cwd . '/..');
		}

		/*
		 * If we could not figure out any path for config then fallback to default root directory
		 * */
		if (empty(self::$DIR_CONFIG)) {
			self::$DIR_CONFIG = self::$DIR_ROOT;
		}

		/*
		 * Load hati configuration json object
		 * */
		$configFile = self::absPath('config/hati.json');
		if (!file_exists($configFile))
			throw new RuntimeException("hati.json file was not found in $configFile");

		$config = file_get_contents($configFile);
		$config = json_decode($config, true);
		if (json_last_error() != JSON_ERROR_NONE)
			throw new RuntimeException("hati.json seems to be corrupted in $configFile");

		self::$CONFIG = $config;

		/*
		 * Load db configuration json object
		 * */
		$configFile = self::absPath('config/db.json');
		if (!file_exists($configFile))
			throw new RuntimeException("Hati couldn't find the hati.json file to configure");

		$dbConfig = file_get_contents($configFile);
		$dbConfig = json_decode($dbConfig, true);
		if (json_last_error() != JSON_ERROR_NONE)
			throw new RuntimeException("db.json seems to be corrupted in $configFile");

		self::$DB_CONFIG = $dbConfig;
	}

	public static function dbConfigObj(): array {
		return self::$DB_CONFIG;
	}

	/**
	 * Using this method, code can get the path to the root directory of the project.
	 * Root path can be set by hati config file. If it is set, then Hati uses that
	 * as project root path for everything.
	 *
	 * <br>Otherwise, Hati calculates the project root path by directory magic constant
	 * of php.
	 *
	 * <br><b>Directory separator is added at the end of the root folder.</b>
	 *
	 * @return string The project root folder
	 * */
	public static function root(): string {
		return self::$DIR_CONFIG;
	}

	/**
	 * This returns the loader instance of the composer auto loader.
	 * It returns null if Hati is configured to use its own loader.
	 *
	 * @return ?object The composer auto loader object.
	 */
	public static function loader(): ?object {
		return self::$loader;
	}

	/**
	 * This method replaces slashes with system's directory separator.
	 *
	 * @param string $path the path including different directory separator
	 * than server's one.
	 *
	 * @return string system's neutral path with directory separator.
	 */
	public static function fixSeparator(string $path): string {
		if (DIRECTORY_SEPARATOR == '\\') return str_replace('/', '\\', $path);
		return str_replace('\\', '/', $path);
	}

	/*
	 * Gets the absolute path added to the specified path. It uses __DIR__ magic
	 * constant in Hati.php to calculate the base directory and appends the path.
	 * The specified is neutralized with directory separators.
	 *
	 * @param string $path The path to calculate absolute path for
	 * @return string The absolute for the path specified
	 **/
	public static function absPath(string $path = ''): string {
		return self::$DIR_CONFIG . self::fixSeparator($path);
	}

	public static function version(): string {
		return self::$version;
	}

	public static function benchmarkStart(): float {
		return self::$BENCHMARK_START;
	}

	public static function config(string $key, string $as = 'str') : string|array|int|bool|float {
		if (!isset(self::$CONFIG))
			throw new RuntimeException("Hati config file is missing $key. Please reinstall hati.");

		$data = self::$CONFIG[$key];

		if ($as == 'int') return (int) $data;
		else if ($as == 'bool') return (bool) $data;
		else if ($as == 'str') return (string) $data;
		else if ($as == 'arr') return (array) $data;
		return (float) $data;
	}

}

try {
	// Fetch the user configuration for this Hati
	require __DIR__ . DIRECTORY_SEPARATOR . 'hati_config' .  DIRECTORY_SEPARATOR . 'Key.php';

	Hati::start();
} catch (Throwable $t) {
	echo "Hati encountered error while initializing: {$t -> getMessage()}";
}