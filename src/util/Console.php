<?php

namespace hati\util;

/**
 * A cool class help printing log messages with minimal effort. This class
 * checks the type output console and formats message accordingly with lines
 * break & colors. Currently there are 5 methods it supports:<br>
 * - {@link Console::log()} : Logs normal message with no color
 * - {@link Console::info} : Logs info message with blue color
 * - {@link Console::success()} : Logs success message with green color
 * - {@link Console::warn()} : Logs warning message with no color
 * - {@link Console::error()} : Logs error message with red color
 * */

abstract class Console {

	/**
	 * Logs any type of variable into console/page with nice formatting.
	 *
	 * @param mixed $value The value
	 * @param int $numOfBreak Number of break to be added after the message
	 * */
	public static function log(mixed $value, int $numOfBreak = 1): void {
		self::println($value, $numOfBreak);
	}

	/**
	 * Logs warn message for any type of variable into console/page with nice formatting.
	 *
	 * @param mixed $value The value
	 * @param int $numOfBreak Number of break to be added after the message
	 * */
	public static function warn(mixed $value, int $numOfBreak = 1): void {
		self::println($value, $numOfBreak, 'warn');
	}

	/**
	 * Logs info message for any type of variable into console/page with nice formatting.
	 *
	 * @param mixed $value The value
	 * @param int $numOfBreak Number of break to be added after the message
	 * */
	public static function info(mixed $value, int $numOfBreak = 1): void {
		self::println($value, $numOfBreak, 'info');
	}

	/**
	 * Logs success message for any type of variable into console/page with nice formatting.
	 *
	 * @param mixed $value The value
	 * @param int $numOfBreak Number of break to be added after the message
	 * */
	public static function success(mixed $value, int $numOfBreak = 1): void {
		self::println($value, $numOfBreak, 'success');
	}

	/**
	 * Logs error message for any type of variable into console/page with nice formatting.
	 *
	 * @param mixed $value The value
	 * @param int $numOfBreak Number of break to be added after the message
	 * */
	public static function error(mixed $value, int $numOfBreak = 1): void {
		self::println($value, $numOfBreak, 'error');
	}

	private static function println(mixed $value, int $numOfBreak = 1, string $type = 'log'): void {
		$b = Util::cli() ? "\n" : "<br>";

		// Remove one extra break as pre tag adds one already
		if (!Util::cli()) {
			$numOfBreak -= 1;
		}

		// Remove one extra break if it of type array/obj for CLI output
		if (Util::cli() && (is_array($value) || is_object($value))) {
			$numOfBreak -= 1;
		}

		// Can't have zero number of break for str_repeat function
		if ($numOfBreak < 0) $numOfBreak = 0;
		$break = str_repeat($b, $numOfBreak);

		if (is_array($value) || is_object($value)) {
			$value = print_r($value, true);
		} elseif (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		}

		if (Util::cli()) {
			echo match ($type) {
				'error' => "\033[31m$value\033[0m$break",
				'success' => "\033[32m$value\033[0m$break",
				'warn' => "\033[33m$value\033[0m$break",
				'info' => "\033[34m$value\033[0m$break",
				default => "$value$break"
			};
		} else {
			$color = match ($type) {
				'error' => 'style="color: #DC3545"',
				'success' => 'style="color: #198754"',
				'warn' => 'style="color: #FFC107"',
				'info' => 'style="color: #0D6EFD"',
				default => ''
			};

			printf('<pre %s>%s</pre>', $color, $value);
			echo $break;
		}
	}

}