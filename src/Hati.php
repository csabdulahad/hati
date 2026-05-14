<?php

namespace Hati;

/**
 * Hati, a speedy PHP library.
 * This class initializes the library.
 * */
abstract class Hati
{

	// version
	private static string $version = '7.0.37-beta';
	
	public static function getGlobalFuncPath(): string
	{
		return self::fixSeparator(__DIR__ . '/global_func.php');
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
	
}