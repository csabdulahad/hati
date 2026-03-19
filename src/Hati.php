<?php

/** @noinspection PhpUndefinedConstantInspection */

namespace hati;

use hati\config\Key;
use RuntimeException;
use Throwable;

/**
 * Hati, a speedy PHP library.
 * This class initializes the library.
 * */

abstract class Hati {

	// version
	private static string $version = '7.0.31-beta';

	private static float $BENCHMARK_START = 0;

	// The project root directory [where the vendor folder is found]
	private static ?string $DIR_ROOT = null;

	// The hati configuration
	private static array $CONFIG = [];

	// The db configuration file is cached as JSON decoded array
	private static ?array $DB_CONFIG = null;

	/**
	 * This is the first method call of the execution. It initializes the environment
	 * as per configuration and resolve dependencies.
	 *
	 * @throws Throwable
	 */
	public static function start(string $rootDir, ?string $configDir = null): void
	{
		self::checkConstant();

		// Set root directory
		$rootDir = rtrim($rootDir, '\/') . DIRECTORY_SEPARATOR;
		self::$DIR_ROOT = $rootDir;

		// load the correct config JSON file
		$configDir ??= 'config';
		self::loadConfig($configDir);

		// start the benchmark if Hati is set up to include dev benchmark
		if(self::config(Key::DEV_API_BENCHMARK, 'bool')) {
			self::$BENCHMARK_START = microtime(true);
		}
		
		// Set the timezone
		date_default_timezone_set(self::config(Key::TIME_ZONE));

		/*
		 * Adjust include paths
		 * */
		$projectDirAsInclude = self::config(Key::PROJECT_DIR_AS_INCLUDE_PATH, 'bool');
		if ($projectDirAsInclude) {
			set_include_path(get_include_path() . PATH_SEPARATOR . self::root());
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
			$file = str_replace('.php', '', $file);
			$path = self::root("$file.php");
			if (file_exists($path)) require_once $path;
		}
	}

	/**
	 * Parse a specific JSON file as associative array and assigns it to the specified variable.
	 * @throws RuntimeException If the file doesn't exist or the JSON is not properly formatted.
	 * */
	private static function parseConfigFile(string $fileName, string $path): array
	{
		if (!file_exists($path)) return [];

		$config = file_get_contents($path);
		$config = json_decode($config, true);
		
		if (json_last_error() != JSON_ERROR_NONE) {
			throw new RuntimeException("$fileName couldn't not be parsed: $path");
		}

		return $config;
	}

	/**
	 * Loads configuration files to set up Hati as defined by various JSON files
	 *
	 * @param string $configDir
	 * */
	private static function loadConfig(string $configDir): void
	{
		$ds = DIRECTORY_SEPARATOR;
		
		$configDir = rtrim($configDir, '\/') . $ds;
		
		// Check if config dir is absolute path
		if (!file_exists($configDir)) {
			// Try relative path
			$configDir = self::root($configDir);
			
			if (!file_exists($configDir)) {
				// Fallback to default path
				$configDir = self::root("config$ds");
			}
		}

		// Load hati configuration JSON object
		$configFile = $configDir . HATI_CONFIG_FILE;
		self::$CONFIG = self::parseConfigFile(HATI_CONFIG_FILE, $configFile);

		// Load db configuration JSON object from root
		$dbConfigFile =  $configDir . HATI_CONFIG_DB_FILE;
		self::$DB_CONFIG = self::parseConfigFile(HATI_CONFIG_DB_FILE, $dbConfigFile);
	}

	/**
	 * The hati/db.json file is parsed as array using {@link json_decode()} to be used by {@link Fluent}
	 * to manage database configurations & connections
	 *
	 * @return ?array represents the database configuration object
	 * */
	public static function dbConfigObj(): ?array
	{
		return self::$DB_CONFIG;
	}

	/**
	 * Returns the root path where the vendor folder is found as root folder!
	 *
	 * It is easier to refer to any file/folder from the root directory path,
	 * avoid confusion and, it becomes very clear what the path is referring to.
	 *
	 * @param string $path Any path segment to be appended to the root path
	 * @return string The path referring from the root directory
	 * */
	public static function root(string $path = ''): string
	{
		$path = ltrim($path, '\/');
		return self::fixSeparator(self::$DIR_ROOT . $path);
	}

	/**
	 * This method replaces slashes with system's directory separator.
	 *
	 * @param string $path the path including different directory separator
	 * than server's one.
	 *
	 * @return string system's neutral path with directory separator.
	 */
	public static function fixSeparator(string $path): string
	{
		if (DIRECTORY_SEPARATOR == '\\') return str_replace('/', '\\', $path);
		return str_replace('\\', '/', $path);
	}

	/**
	 * Tells about which version of the Hati is running.
	 *
	 * @return string version of the Hati in use
	 * */
	public static function version(): string
	{
		return self::$version;
	}

	public static function benchmarkStart(): float
	{
		return self::$BENCHMARK_START;
	}

	/**
	 * Fetches the config value from the hati configuration array.
	 *
	 * @param string $key The config key defined in the JSON configuration file
	 * @param string $as How to cast the return value. Supported types are:<br>
	 * - str: cast as string<br>
	 * - arr: cast as array<br>
	 * - bool: cast as boolean<br>
	 * - int: cast as integer<br>
	 * - float: cast as float
	 * @return string|array|int|bool|float The value in required type
	 * */
	public static function config(string $key, string $as = 'str') : string|array|int|bool|float
	{
		if (!isset(self::$CONFIG[$key])) {
			throw new RuntimeException("Config \"$key\" is missing");
		}

		$data = self::$CONFIG[$key];

		if ($as == 'int') return (int) $data;
		else if ($as == 'bool') return (bool) $data;
		else if ($as == 'str') return (string) $data;
		else if ($as == 'arr') return (array) $data;
		return (float) $data;
	}

	private static function checkConstant(): void
	{
		$constants = [
			'HATI_CONFIG_FILE' 		=> 'hati.json',
			'HATI_CONFIG_DB_FILE' 	=> 'db.json',
		];
		
		foreach ($constants as $c => $v) {
			if (defined($c)) continue;
			
			define($c, $v);
		}
	}
	
}