<?php

namespace hati\util;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * A helper class consisting of helpful functions to deal with Date in PHP.
 *
 * @since 5.0.0
 * */

abstract class Date {

	/**
	 * Get "now" time
	 *
	 * Returns time() based on the timezone parameter or on the
	 * "time_reference" setting
	 *
	 * @param ?string $timezone The timezone. When it is null then the server's default timezone is used.
	 * @return false|int The Unix timestamp of the arguments given. If the arguments are invalid,
	 * the function returns false.
	 * @throws Exception If the timezone is invalid.
	 */
	public static function now(?string $timezone = 'local'): false|int {

		if (is_null($timezone) || $timezone === 'local' || $timezone === date_default_timezone_get()) {
			return time();
		}

		$datetime = new DateTime('now', new DateTimeZone($timezone));
		sscanf($datetime->format('j-n-Y G:i:s'), '%d-%d-%d %d:%d:%d', $day, $month, $year, $hour, $minute, $second);

		return mktime($hour, $minute, $second, $month, $day, $year);
	}

	/**
	 * Number of days in a month
	 *
	 * Takes a month/year as input and returns the number of days
	 * for the given month/year. Takes leap years into consideration.
	 *
	 * @param int $month a numeric month
	 * @param ?int $year a numeric year. When null, then current year is used.
	 * @return int The number of days for that specified month.
	 */
	public static function daysInMonth(int $month = 0, ?int $year = null): int {
		if ($month < 1 OR $month > 12) {
			return 0;
		} elseif ( ! is_numeric($year) OR strlen($year) !== 4) {
			$year = date('Y');
		}

		if ($year >= 1970) {
			return (int) date('t', mktime(12, 0, 0, $month, 1, $year));
		}

		if ($month == 2) {
			if ($year % 400 === 0 OR ($year % 4 === 0 && $year % 100 !== 0)) {
				return 29;
			}
		}

		$days_in_month	= [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		return $days_in_month[$month - 1];
	}

	/**
	 * Converts a local Unix timestamp to GMT
	 *
	 * @param ?int $time Unix timestamp
	 *
	 * @return int|false returns the Unix timestamp of the arguments given. If the
	 * arguments are invalid, the function returns false
	 */
	public static function localToGMT(?int $time = null): int|false {
		if (is_null($time)) {
			$time = time();
		}

		return mktime(
			gmdate('G', $time),
			gmdate('i', $time),
			gmdate('s', $time),
			gmdate('n', $time),
			gmdate('j', $time),
			gmdate('Y', $time)
		);
	}

	/**
	 * Converts GMT time to a localized value
	 *
	 * Takes a Unix timestamp (in GMT) as input, and returns
	 * at the local value based on the timezone and DST setting
	 * submitted
	 *
	 * @param ?int $time Unix timestamp
	 * @param string $timezone timezone
	 * @param bool $dst whether DST is active
	 * @return float|false|int
	 * @throws Exception If the timezone is invalid.
	 */
	public static function gmtToLocal(?int $time = null, string $timezone = 'UTC', bool $dst = false): float|false|int {
		if (is_null($time)) {
			return self::now();
		}

		$time += self::timezones($timezone) * 3600;

		return ($dst === true) ? $time + 3600 : $time;
	}

	/**
	 * Timezones
	 *
	 * Returns an array of timezones. This is a helper function
	 * for various other ones in this library
	 *
	 * @param string $tz timezone
	 * @return string|array Returns array containing timezones when the
	 * tz is null. For specific timezone, it returns the offset.
	 */
	public static function timezones(string $tz = ''): string|array {
		// Note: Don't change the order of these even though
		// some items appear to be in the wrong order

		$zones = [
			'UM12'		=> -12,
			'UM11'		=> -11,
			'UM10'		=> -10,
			'UM95'		=> -9.5,
			'UM9'		=> -9,
			'UM8'		=> -8,
			'UM7'		=> -7,
			'UM6'		=> -6,
			'UM5'		=> -5,
			'UM45'		=> -4.5,
			'UM4'		=> -4,
			'UM35'		=> -3.5,
			'UM3'		=> -3,
			'UM2'		=> -2,
			'UM1'		=> -1,
			'UTC'		=> 0,
			'UP1'		=> +1,
			'UP2'		=> +2,
			'UP3'		=> +3,
			'UP35'		=> +3.5,
			'UP4'		=> +4,
			'UP45'		=> +4.5,
			'UP5'		=> +5,
			'UP55'		=> +5.5,
			'UP575'		=> +5.75,
			'UP6'		=> +6,
			'UP65'		=> +6.5,
			'UP7'		=> +7,
			'UP8'		=> +8,
			'UP875'		=> +8.75,
			'UP9'		=> +9,
			'UP95'		=> +9.5,
			'UP10'		=> +10,
			'UP105'		=> +10.5,
			'UP11'		=> +11,
			'UP115'		=> +11.5,
			'UP12'		=> +12,
			'UP1275'	=> +12.75,
			'UP13'		=> +13,
			'UP14'		=> +14
		];

		if ($tz === '') {
			return $zones;
		}

		return $zones[$tz] ?? 0;
	}

}