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
	private static ?string $DIR_PROJECT = null;

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
		self::$DIR_ROOT = realpath(dirname(__DIR__, 4)) . DIRECTORY_SEPARATOR;

		// register autoloader function
		Hati::$loader = require self::$DIR_ROOT . 'vendor/autoload.php';

		// load the correct config json file
		self::loadConfig();

		date_default_timezone_set(self::config(Key::TIME_ZONE));

		// start the benchmark if Hati is setup to include dev benchmark
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
			$path = self::root('hati/global_func.php');
			if (file_exists($path)) require $path;
		}

		// include global php code files for project
		$globalPHP = self::config(Key::GLOBAL_PHP, 'arr');
		foreach ($globalPHP as $file) {
			$file = trim($file);
			$path = self::projectRoot("$file.php");
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
			$hatiJson = $cwd . DIRECTORY_SEPARATOR . 'hati' . DIRECTORY_SEPARATOR . 'hati.json';

			if (file_exists($hatiJson)) {
				self::$DIR_PROJECT = $cwd . DIRECTORY_SEPARATOR;
				break;
			}

			// go one directory up
			$cwd = realpath($cwd . '/..');
		}

		/*
		 * If we could not figure out any path for config then fallback to default root directory
		 * */
		if (empty(self::$DIR_PROJECT)) {
			self::$DIR_PROJECT = self::$DIR_ROOT;
		}

		/*
		 * Load hati configuration json object
		 * */
		$configFile = self::projectRoot('hati/hati.json');
		if (!file_exists($configFile)) {
			// Fallback to default one
			$configFile = self::root('hati/hati.json');
		}

		if (!file_exists($configFile))
			throw new RuntimeException("hati.json file is missing in $configFile");

		$config = file_get_contents($configFile);
		$config = json_decode($config, true);
		if (json_last_error() != JSON_ERROR_NONE)
			throw new RuntimeException("hati.json seems to be corrupted in $configFile");

		self::$CONFIG = $config;

		/*
		 * Load db configuration json object from root
		 * */
		$configFile = self::root('hati/db.json');
		if (!file_exists($configFile))
			throw new RuntimeException("db.json file is missing in $configFile");

		$dbConfig = file_get_contents($configFile);
		$dbConfig = json_decode($dbConfig, true);
		if (json_last_error() != JSON_ERROR_NONE)
			throw new RuntimeException("db.json seems to be corrupted in $configFile");

		self::$DB_CONFIG = $dbConfig;
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
	 * Since version 5, Hati can be installed once and be reused in sub-project like structure.
	 * Using this method, code can get the root path, the path where the vendor folder is found.
	 *
	 * This way it makes it easier for sub-projects to refer to any file/folder from the root
	 * directory path, avoid confusion and it becomes very clear what the path is referring to.
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
	 * sub-project directory.
	 *
	 * This method always returns the sub-project directory, not the parent folder.
	 * If the project is not a sub-project, then both {@link Hati::root()} & this
	 * method return the same path.
	 *
	 * @param string $path Any path segment to be appended to the root path
	 * @return string The project root folder
	 * */
	public static function projectRoot(string $path = ''): string {
		return self::fixSeparator(self::$DIR_PROJECT . $path);
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