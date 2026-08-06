<?php

namespace Hati;

/**
 * Hati, a speedy PHP library.
 * This class initializes the library.
 * */
abstract class Hati
{

	// version
	public const string VERSION = '7.0.52-beta';
	
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
	
}