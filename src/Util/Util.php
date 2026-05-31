<?php

namespace Hati\Util;


use Random\RandomException;

/**
 * Util class is a helper class which has many helpful methods that can easily deal with
 * session, cookie and other aspect of a project. This class is under continuous improvement
 * as we discover many helper simple functions over time.
 */

class Util
{

	/** Alternator index tracker */
	private static array $altIndex = [];

	/**
	 * Returns alternate values in a cyclical manner. It keeps track of an internal index for
	 * getting the item from the variable arguments. If no values are provided or if an empty
	 * array is passed, it resets the internal index for the given name and returns an empty string.
	 *
	 * @param string $name The name required to track the internal alternator index count
	 * @param mixed ...$values (as many parameters as needed)
	 */
	public static function alternate(string $name, mixed ...$values): mixed
	{
		if (!isset(self::$altIndex[$name]))
			self::$altIndex[$name] = 0;

		$i = self::$altIndex[$name];

		$option = Arr::varargsAsArray($values);
		if (count($option) === 0) {
			self::$altIndex[$name] = 0;
			return '';
		}

		$key = ($i++ % count($option));
		self::$altIndex[$name] = $i;

		return $option[$key];
	}

	/**
	 * PHP can run in both server & CLI. Using this, the execution environment
	 * can be detected.
	 *
	 * @return bool true if the environment is CLI, false otherwise
	 * **/
	public static function isCLI(): bool
	{
		if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
			return true;
		}

		// PHP_SAPI could be 'cgi-fcgi', 'fpm-fcgi'.
		return !isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * Figures out whether a directory is empty or not.
	 *
	 * @param string $dirPath The directory path
	 * @return bool True if the directory is empty, false otherwise
	 **/
	public static function isDirEmpty(string $dirPath): bool
	{
		return count(glob($dirPath . '/*')) === 0;
	}
	
	/**
	 * Check whether the given string is a valid email address.
	 * This only validates the email format.
	 *
	 * @param mixed $value The string to validate.
	 * @return bool True if the string is a valid email address, false otherwise.
	 */
	public static function isEmail(mixed $value): bool
	{
		return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
	}
	
	/**
	 * Check whether the current request appears to come from a mobile/tablet device.
	 *
	 * This uses the browser's User-Agent header to detect common mobile devices
	 * such as Android, iPhone, iPad, and iPod. It is intended for lightweight UI
	 * decisions only, not for security or critical business logic.
	 *
	 * @param string|null $httpUserAgentString Optional User-Agent string to check.
	 *                                         If null, $_SERVER['HTTP_USER_AGENT'] is used.
	 *
	 * @return bool True if the User-Agent looks like a mobile/tablet device, false otherwise.
	 */
	public static function isMobile(?string $httpUserAgentString = null): bool
	{
		$ua = $httpUserAgentString ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
		
		return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua) === 1;
	}
	
	/**
	 * Using this method the execution environment can be extracted.
	 * @return string Returns 'cli' if it is running CLI, 'server' if running in Apache/CGI
	 * **/
	public static function getExecEnv(): string
	{
		return self::isCLI() ? 'cli' : 'server';
	}

	/**
	 * A random token can be generated using this method. Default length
	 * of the token is 11. It uses shuffling of time value after md5
	 * encryption. However, it doesn't guarantee the uniqueness of the token.
	 * In order to get a unique id use {@link uniqueId} instead.
	 *
	 * @param int $len The length of the token.
	 * @return string A randomly generated token.
	 */
	public static function randToken(int $len = 11): string
	{
		$ts = (string) time();
		
		return $ts
				|> md5(...)
				|> str_shuffle(...)
				|> (fn($x) => substr($x, 0, $len));
	}
	
	/**
	 * Generate a random integer with the requested digit length.
	 *
	 * The length is clamped between 1 and the maximum safe integer digit length.
	 * Uses random_int() first. If secure random generation fails, falls back to
	 * mt_rand(), which is less secure but avoids throwing an exception.
	 *
	 * This does not guarantee uniqueness; use a database UNIQUE constraint for that.
	 *
	 * @param int $length Number of digits to generate.
	 * @return int Random integer with the requested digit length where possible.
	 */
	public static function randomInt(int $length): int
	{
		$maxLength = strlen((string) PHP_INT_MAX);
		$length = self::clamp($length, 1, $maxLength);
		
		$min = $length === 1 ? 0 : 10 ** ($length - 1);
		$max = $length === $maxLength
			? PHP_INT_MAX
			: (10 ** $length) - 1;
		
		try {
			return random_int($min, $max);
		} catch (RandomException) {
			return self::fallbackRandomInt($length);
		}
	}
	
	/**
	 * Generate a fallback random integer using mt_rand().
	 *
	 * This is only used when random_int() fails.
	 *
	 * @param int $length Number of digits to generate.
	 * @return int Fallback random integer.
	 */
	private static function fallbackRandomInt(int $length): int
	{
		$maxIntString = (string) PHP_INT_MAX;
		
		do {
			$number = '';
			
			for ($i = 0; $i < $length; $i++) {
				$minDigit = ($i === 0 && $length > 1) ? 1 : 0;
				$number .= mt_rand($minDigit, 9);
			}
		} while (
			strlen($number) === strlen($maxIntString)
			&& strcmp($number, $maxIntString) > 0
		);
		
		return (int) $number;
	}
	
	/**
	 * Clamp an integer between a minimum and maximum value.
	 *
	 * @param int $value The value to clamp.
	 * @param int $min Minimum allowed value.
	 * @param int $max Maximum allowed value.
	 * @return int The clamped value.
	 */
	public static function clamp(int $value, int $min, int $max): int
	{
		if ($min > $max) {
			[$min, $max] = [$max, $min];
		}
		
		return max($min, min($value, $max));
	}

	/**
	 * A unique string using php uniqid can be generated by this method.
	 * It uses more entropy to generate more random and unique string/id.
	 *
	 * @param string $prefix Any arbitrary string to be prefixed.
	 *
	 * @return string A unique string.
	 * */
	public static function uniqueId(string $prefix = ''): string
	{
		return uniqid($prefix, true);
	}

}