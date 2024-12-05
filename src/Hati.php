<?php

namespace hati;

use hati\config\Key;
use RuntimeException;
use Throwable;

/**
 * Hati, a speedy PHP library. This class does all the magic under the
 * hood. It gets the very first call from the server, before any code
 * can execute. This prepares dependencies by setting a class loader.
 *
 * It uses the configuration object to prepare the working environment
 * properly. Please use hati.json file in order to customize your
 * great HATI.
 * */

abstract class Hati {

	// version
	private static string $version = '7.0.13-beta';

	private static float $BENCHMARK_START = 0;

	private static ?object $loader = null;

	// The project root directory [where the vendor folder is found]
	private static ?string $DIR_ROOT = null;

	// The config directory
	private static ?string $DIR_PROJECT = null;

	// The global hati json configuration
	private static ?array $CONFIG_GLOBAL = null;

	// The subproject hati json configuration
	private static ?array $CONFIG_LOCAL = null;

	// Indicates if the global config in use
	private static bool $GLOBAL_CONFIG_IN_USE;

	// The db configuration file is cached as json decoded array
	private static ?array $DB_CONFIG = [];

	/**
	 * This is the first method call of the execution. It initializes the environment
	 * as per configuration and resolve dependencies.
	 *
	 * @throws Throwable
	 */
	public static function start(): void {
		global $HATI_USE_SRC_AS_ROOT;

		// calculate the project root folder
		self::$DIR_ROOT = realpath(dirname(__DIR__, 4)) . DIRECTORY_SEPARATOR;

		// register autoloader function
		Hati::$loader = require self::$DIR_ROOT . 'vendor/autoload.php';

		/*
		 * Adjust root dir if there is a src folder!
		 * */
		if ($HATI_USE_SRC_AS_ROOT) {
			$root = self::$DIR_ROOT . 'src' . DIRECTORY_SEPARATOR;
			self::$DIR_ROOT = $root;
		}

		// load the correct config json file
		self::loadConfig();

		date_default_timezone_set(self::config(Key::TIME_ZONE));

		// start the benchmark if Hati is set up to include dev benchmark
		if(self::config(Key::DEV_API_BENCHMARK, 'bool'))
			self::$BENCHMARK_START = microtime(true);

		/*
		 * Adjust include paths
		 * */
		$projectDirAsInclude = self::config(Key::PROJECT_DIR_AS_INCLUDE_PATH, 'bool');
		if ($projectDirAsInclude) {
			set_include_path(get_include_path() . PATH_SEPARATOR . self::projectRoot());
		}

		// See if root is already added
		if (!str_ends_with(get_include_path(), self::root())) {
			set_include_path(get_include_path() . PATH_SEPARATOR . self::root());
		}

		if (self::config(Key::SESSION_AUTO_START, 'bool')) {
			// Cookies will only be sent in a first-party context and not be sent along with
			// requests initiated by third party websites.
			session_set_cookie_params(['SameSite' => 'Strict', 'Secure' => true]);
			session_start();
		}

		// Load the global functions file
		if (self::config(Key::USE_GLOBAL_FUNC, 'bool')) {
			$path = self::fixSeparator(__DIR__ . '/global_func.php');
			require_once $path;
		}

		// include global php code files for project
		$globalPHP = self::config(Key::GLOBAL_PHP, 'arr');
		foreach ($globalPHP as $file) {
			$file = trim($file);
			$path = self::projectRoot("$file.php");
			if (file_exists($path)) require_once $path;
		}
	}

	/**
	 * Based on location, where the hati is being used, it tries to find the config path.
	 * This helps Hati to figure out whether it is used for subproject & load configuration
	 * appropriately.
	 *
	 * It tries for 15 times, from current working directory to see if there is any folder called
	 * hati and there is a hati.json file exists. If so then Hati loads that as subproject config
	 * file. However, on failure, Hati falls back to root project config file.
	 * */
	private static function getConfigPath(): void {
		/*
		 * Calculate the config path by going a directory up each iteration to find
		 * any local hati/hati.json file to use
		 * */
		$cwd = getcwd();

		$counter = 0;
		while ($cwd !== false) {
			if ($counter >= 15) break;

			$hatiJson = $cwd . DIRECTORY_SEPARATOR . 'hati' . DIRECTORY_SEPARATOR . 'hati.json';
			if (file_exists($hatiJson)) {
				self::$DIR_PROJECT = $cwd . DIRECTORY_SEPARATOR;
				break;
			}

			// go one directory up
			$cwd = realpath($cwd . '/..');

			$counter ++;
		}

		/*
		 * If we could not figure out any path for config then fallback to default root directory
		 * */
		if (empty(self::$DIR_PROJECT)) {
			self::$DIR_PROJECT = self::$DIR_ROOT;
		}

		self::$GLOBAL_CONFIG_IN_USE = self::$DIR_ROOT === self::$DIR_PROJECT;
	}

	/**
	 * Parse a specific json file as associative array and assigns it to the specified variable.
	 * @throws RuntimeException If the file doesn't exist or the json is not properly formatted.
	 * */
	private static function parseConfigFile(string $fileName, string $path, &$assignTo): void {
		if (!file_exists($path))
			throw new RuntimeException("$fileName file is missing at: $path");

		$config = file_get_contents($path);
		$config = json_decode($config, true);
		if (json_last_error() != JSON_ERROR_NONE)
			throw new RuntimeException("$fileName couldn't not be parsed: $path");

		$assignTo = $config;
	}

	/**
	 * Loads configuration files to set up Hati as defined by various json files
	 * */
	private static function loadConfig(): void {
		self::getConfigPath();

		// Load hati configuration json object
		$configFile =
			self::$GLOBAL_CONFIG_IN_USE ?
			self::root('hati/hati.json') :
			self::projectRoot('hati/hati.json');

		self::parseConfigFile('hati.json', $configFile, self::$CONFIG_LOCAL);

		// Load db configuration json object from root
		try {
			$configFile = self::root('hati/db.json');
			self::parseConfigFile('db.json', $configFile, self::$DB_CONFIG);
		} catch (Throwable) {}
	}

	/**
	 * The hati/db.json file is parsed as array using {@link json_decode()} to be used by {@link Fluent}
	 * to manage database configurations & connections
	 *
	 * @return array represents the database configuration object
	 * */
	public static function dbConfigObj(): array {
		return self::$DB_CONFIG;
	}

	/**
	 * Since version 5, Hati can be installed once and be reused in subproject like structure.
	 * Using this method, code can get the root path, the path where the vendor folder is found.
	 *
	 * This way it makes it easier for subprojects to refer to any file/folder from the root
	 * directory path, avoid confusion and, it becomes very clear what the path is referring to.
	 *
	 * @param string $path Any path segment to be appended to the root path
	 * @return string The path referring from the root directory
	 * */
	public static function root(string $path = ''): string {
		return self::fixSeparator(self::$DIR_ROOT . $path);
	}

	/**
	 * With introduction of version 5, Hati can be reused in more than one project in
	 * the same parent folder. Using this method, code can get the path to the
	 * subproject directory.
	 *
	 * This method always returns the subproject directory, not the parent folder.
	 * If the project is not a subproject, then both {@link Hati::root()} & this
	 * method return the same path.
	 *
	 * @param string $path Any path segment to be appended to the root path
	 * @return string The project root folder
	 * */
	public static function projectRoot(string $path = ''): string {
		return self::fixSeparator(self::$DIR_PROJECT . $path);
	}

	/**
	 * This returns the loader instance of the composer autoloader.
	 * It returns null if Hati is configured to use its own loader.
	 *
	 * @return ?object The composer autoloader object.
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

	/**
	 * Tells about which version of the Hati is running.
	 *
	 * @return string version of the Hati in use
	 * */
	public static function version(): string {
		return self::$version;
	}

	public static function benchmarkStart(): float {
		return self::$BENCHMARK_START;
	}

	/**
	 * Fetches the config value. It tries to get the value from the local hati configuration. If not
	 * found then it goes to global hati configuration.
	 *
	 * @param string $key The config key defined in the hati.json either in local/global configuration
	 * @param string $as How to cast the return value. Supported types are:<br>
	 * - str: cast as string<br>
	 * - arr: cast as array<br>
	 * - bool: cast as boolean<br>
	 * - int: cast as integer<br>
	 * - float: cast as float
	 * @return string|array|int|bool|float The value in required type
	 * */
	public static function config(string $key, string $as = 'str') : string|array|int|bool|float {
		if (!isset(self::$CONFIG_LOCAL[$key])) {

			// Global config is in-use and the value is missing; throw exception!
			if (self::$GLOBAL_CONFIG_IN_USE) {
				throw new RuntimeException("Config $key is missing in global hati.json");
			}

			// Parse the global config if needed
			if(is_null(self::$CONFIG_GLOBAL)) {
				self::parseConfigFile('hati.json', self::root('hati/hati.json'), self::$CONFIG_GLOBAL);
			}

			// Global config is in-use and the value is missing; throw exception!
			if (!isset(self::$CONFIG_GLOBAL[$key])) {
				throw new RuntimeException("Global config \"$key\" is missing for local");
			}

			$data = self::$CONFIG_GLOBAL[$key];
		} else {
			$data = self::$CONFIG_LOCAL[$key];
		}

		if ($as == 'int') return (int) $data;
		else if ($as == 'bool') return (bool) $data;
		else if ($as == 'str') return (string) $data;
		else if ($as == 'arr') return (array) $data;
		return (float) $data;
	}

}

try {
	// Fetch the user configuration for this Hati
	require __DIR__ . DIRECTORY_SEPARATOR . 'config' .  DIRECTORY_SEPARATOR . 'Key.php';

	Hati::start();
} catch (Throwable $t) {
	echo "Hati encountered error while initializing: {$t -> getMessage()}";
}