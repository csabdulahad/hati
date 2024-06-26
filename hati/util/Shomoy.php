<?php

namespace hati\util;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use hati\config\Key;
use hati\Hati;
use hati\Trunk;
use Throwable;

/**
 * Shomoy class is a wrapper class around php's DateTime object class. It has many
 * helpful methods and constants to simplify date and time calculation in the client
 * code. It creates datetime of default timezone as how it is configured in the
 * hati.json file.
 *
 * See <b>hati.json</b> file for changing the default timezone.
 * */

class Shomoy {

	public const TIMEZONE_DHAKA = 'Asia/Dhaka';
	public const TIMEZONE_LONDON = 'Europe/London';

	private DateTime $dateTime;

	// During construction of shomoy object, it created the internal datetime
	// object with the default timezone as it is configured in the hati.json
	// file. By default, it creates current datetime object.
	public function __construct(string $time = 'now', string $timezone = null) {
		try {
			$timezone = $timezone ?? Hati::config(Key::TIME_ZONE);
			$this -> dateTime = new DateTime($time, new DateTimeZone($timezone));
		} catch (Throwable $t) {
			throw new Trunk('Shomoy encountered error while creating current date & time: ' . $t -> getMessage());
		}
	}

	/**
	 * This function can create Shomoy object from timestamp.
	 *
	 * @param int $timestamp the timestamp for the datetime.
	 *
	 * @return Shomoy the shomoy object that is representing the timestamp.
	 * */
	public static function fromTimestamp(int $timestamp): Shomoy {
		// first, convert the timestamp into textual representation.
		// then create datetime object from that string.
		$dateTime = date_create(date('Y-m-d\TH:i:sO', $timestamp));

		// finally wrap that formatted string value of datetime within shomoy and return.
		return new Shomoy($dateTime -> format('Y-m-d\TH:i:sO'));
	}

	/**
	 * When this methods compares itself with other date time object, it is considered
	 * that the comparing date time object is in the same timezone as this shomoy date
	 * time is. The  Shomoy gets its default timezone from the Hati by calling
	 * {@link Hati::defaultTimezone()} method. It can be configured with other flags in
	 * hati.json file.
	 *
	 * For removing all the confusion and ambiguity in date time, please always store and
	 * use one timezone in all persistent storage. Just convert the timezone into native
	 * or user recommend timezone when displaying date time.
	 *
	 * @param DateTime $dateTime the datetime you want to compare with. This has to be in
	 * the same timezone as this shomoy datetime is.
	 *
	 * @return int it returns -1 if this shomoy is behind the comparing datetime.
	 * 1 is returned when this shomoy is ahead of the comparing datetime.
	 * 0 is returned when both of the datetime are equal.
	 */
	public function compareDateTime(DateTime $dateTime): int {
		// get micro-seconds from both objects
		$thisSec = strtotime($this -> dateTime -> format('Y-m-d\TH:i:sO')) * 1000;
		$thatSec = strtotime($dateTime -> format('Y-m-d\TH:i:sO')) * 1000;

		// now calculate the difference
		if ($thisSec == $thatSec) return 0;
		else if ($thisSec < $thatSec) return -1;
		else return 1;
	}

	/**
	 * A shomoy object can compare itself with other shomoy object. Internally it
	 * uses the {@link compareDateTime()} function to calculate the difference in
	 * timestamp and returns either 0, 1, or -1 based on the calculation.
	 *
	 * @param Shomoy $shomoy The Shomoy object to calculate against
	 *
	 * @return int the difference between two shomoy objects
	 * */
	public function compare(Shomoy $shomoy): int {
		return $this -> compareDateTime($shomoy -> getDateTime());
	}

	/**
	 * The difference between two shomoy objects can be calculated either in
	 * milliseconds(which is default) or microseconds(timestamp) value. It always
	 * finds the difference from $this object to passed one.
	 *
	 * @param Shomoy $shomoy the Shomoy object to calculate the difference against
	 * @param bool $inMilli indicates whether to calculate in milliseconds or microseconds
	 *
	 * @return int the difference between two Shomoy objects.
	 * */
	public function diff(Shomoy $shomoy, bool $inMilli = true): int {
		if ($inMilli) return $this -> getMilliSeconds() - $shomoy -> getMilliSeconds();
		else return $this -> getTimestamp() - $shomoy -> getTimestamp();
	}

	/**
	 * Set timestamp to underlying DateTime object of the shomoy object.
	 *
	 * @param int $timestamp The timestamp value
	 * */
	public function setTimestamp(int $timestamp): void {
		$this -> dateTime -> setTimestamp($timestamp);
	}

	/**
	 * Checks if this shomoy is past a specific time period. Time period can be
	 * very flexible such as you can check if this shomoy is past '2months 5hour'
	 * either exactly or just past the period.Time period must be positive.
	 * You can use singular/plural form for the time period. min for minutes, for example.<br>
	 * Valid periods are:
	 *  - sec/second
	 *  - min/minute
	 *  - hour
	 *  - day/days
	 *  - month/months
	 *  - year/years
	 *
	 * @param string $period The time period
	 * @param bool $exact to calculate whether the time has past the period exactly or not
	 * @return bool true if the shomoy has past the time period; false otherwise.
	 * */
	public function past(string $period, bool $exact = false): bool {
		// Get the current date and time
		$now = new Shomoy();

		// Parse the period string to extract days, weeks, months, and years
		preg_match_all('/(\d+)\s*(sec|min|hour|day|week|month|year)s?/', $period, $matches, PREG_SET_ORDER);

		$dateInterval = new DateInterval('P0D');

		foreach ($matches as $match) {
			$value = intval($match[1]);
			$unit = $match[2];

			// Add the corresponding interval to the DateInterval
			switch ($unit) {
				case 'sec':
					$dateInterval -> s -= $value;
					break;
				case 'min':
					$dateInterval -> i -= $value;
					break;
				case 'hour':
					$dateInterval -> h -= $value;
					break;
				case 'day':
					$dateInterval -> d -= $value;
					break;
				case 'week':
					$dateInterval -> d -= $value * 7; // Convert weeks to days
					break;
				case 'month':
					$dateInterval -> m -= $value;
					break;
				case 'year':
					$dateInterval -> y -= $value;
					break;
			}
		}

		// Add the calculated DateInterval to the input datetime
		$now -> dateTime -> add($dateInterval);

		// Compare the modified datetime with the current datetime
		if ($exact) {
			return $this -> dateTime == $now -> dateTime;
		} else {
			return $this -> dateTime <= $now -> dateTime;
		}
	}

	/**
	 * Any time period in format '1 year -4 months 2 days 30 min 5 seconds' can be
	 * added the shomoy object which makes it easier to deal with time.<br> Time
	 * period can be positive/negative. You can use singular/plural form for the
	 * time period. min for minutes, for example. Valid periods are:
	 * - sec/second
	 * - min/minute
	 * - hour
	 * - day/days
	 * - month/months
	 * - year/years
	 *
	 * @param string $period The time period
	 * */
	public function add(string $period): void {

		// Parse the period string to extract days, weeks, months, and years
		preg_match_all('/(-?\d+)\s*(sec|min|hour|day|week|month|year)s?/', $period, $matches, PREG_SET_ORDER);

		$dateInterval = new DateInterval('P0D');

		foreach ($matches as $match) {
			$value = intval($match[1]);
			$unit = $match[2];

			// Add the corresponding interval to the DateInterval
			switch ($unit) {
				case 'sec':
					$dateInterval -> s += $value;
					break;
				case 'min':
					$dateInterval -> i += $value;
					break;
				case 'hour':
					$dateInterval -> h += $value;
					break;
				case 'day':
					$dateInterval -> d += $value;
					break;
				case 'week':
					$dateInterval -> d += $value * 7; // Convert weeks to days
					break;
				case 'month':
					$dateInterval -> m += $value;
					break;
				case 'year':
					$dateInterval -> y += $value;
					break;
				default: return;
			}
		}

		// Add the calculated DateInterval to the datetime
		$this -> dateTime -> add($dateInterval);
	}

	/**
	 * Any number of seconds can be added to the Shomoy object using this method.
	 * Negative value can be added too.
	 *
	 * @param int $sec number of seconds to be added.
	 * */
	public function addSec(int $sec): void {
		try {
			$interval = sprintf('PT%dS', $sec);
			$this -> adjustInterval($sec, $interval);
		} catch (Exception) {
		}
	}

	/**
	 * Any number of minutes can be added to the Shomoy object using this method.
	 * Negative value can be added too.
	 *
	 * @param int $min number of minutes to be added.
	 * */
	public function addMin(int $min): void {
		try {
			$interval = sprintf('PT%dM', $min);
			$this -> adjustInterval($min, $interval);
		} catch (Exception) {}
	}

	/**
	 * Any number of hours can be added to the Shomoy object using this method.
	 * It also takes negative hours which subtracts the hours from the shomoy,
	 *
	 * @param int $hour number of hours to be added.
	 * */
	public function addHour(int $hour): void {
		try {
			$interval = sprintf('PT%dH', $hour);
			$this -> adjustInterval($hour, $interval);
		} catch (Exception) {}
	}

	/**
	 * Any number of days can be added to the Shomoy object using this method.
	 * It also takes negative day which subtracts the days from the shomoy,
	 *
	 * @param int $day number of days to be added.
	 * */
	public function addDay(int $day): void {
		try {
			$interval = sprintf('P%dD', $day);
			$this -> adjustInterval($day, $interval);
		} catch (Exception) {}
	}

	/**
	 * Any number of months can be added to the Shomoy object using this method.
	 * Negative value can be added too.
	 *
	 * @param int $month number of months to be added.
	 * */
	public function addMonth(int $month): void {
		try {
			$interval = sprintf('P%dM', $month);
			$this -> adjustInterval($month, $interval);
		} catch (Exception) {}
	}

	/**
	 * Any number of years can be added to the Shomoy object using this method.
	 * Negative value can be added too.
	 *
	 * @param int $year number of years to be added.
	 * */
	public function addYear(int $year): void {
		try {
			$interval = sprintf('P%dY', $year);
			$this -> adjustInterval($year, $interval);
		} catch (Exception) {}
	}

	/**
	 * It changes the timezone of the datetime object. However, it doesn't
	 * affect the underlying timestamp so changing timezone it just a
	 * representational function.
	 *
	 * Currently Shomoy has two default timezone constants for Dhaka & London.
	 *
	 * @param string $timeZone the timezone must in region/city format such
	 * as Asia/Dhaka.
	 *
	 * @throws Exception When fails to set the timezone
	 */
	public function setTimezone(string $timeZone): void {
		$this -> dateTime -> setTimezone(new DateTimeZone($timeZone));
	}
	
	/**
	 * Allows to change different component of the date.
	 * All arguments are optional. It only changes the associate
	 * value if it is non-zero. It changes the underlying timestamp.
	 *
	 * @param int $day 1-31 day of the month
	 * @param int $month 1-12 month number
	 * @param int $year 4 digit year number
	 * */
	public function changeDate(int $day = 0, int $month = 0, int $year = 0): void {
		$dateTime = $this -> dateTime;
		
		$cYear  = $dateTime -> format('Y');
		$cMonth = $dateTime -> format('m');
		$cDay   = $dateTime -> format('d');
		
		$year = $year > 0 ? $year : $cYear;
		$month = $month > 0 ? $month : $cMonth;
		$day = $day > 0 ? $day : $cDay;
		
		$dateTime -> setDate($year, $month, $day);
	}
	
	/**
	 * Any specific components of time can be changed.
	 * Only non-zero argument is considered. The time components are considered
	 * ISO compliant meaning hours are always 12Hour format.
	 * It changed the internal timestamp of the datetime object wrapped by Shomoy.
	 *
	 * @param int $hour hour
	 * @param int $min minute
	 * @param int $sec second
	 * @param int $ms microsecond
	 * */
	public function changeTime(int $hour = -1, int $min = -1, int $sec = -1, int $ms = -1): void {
		$dateTime = $this -> dateTime;
		
		$cHour = $dateTime->format('H');
		$cMin  = $dateTime->format('i');
		$cSec  = $dateTime->format('s');
		$cMs   = $dateTime->format('u');
		
		$ms = $ms >= 0 ? $ms : $cMs;
		$sec = $sec >= 0 ? $sec : $cSec;
		$min = $min >= 0 ? $min : $cMin;
		$hour = $hour >= 0 ? $hour : $cHour;
		
		$dateTime -> setTime($hour, $min, $sec, $ms);
	}

	public function __toString(): string {
		return $this -> iso();
	}

	public function iso(): string {
		return sprintf('%s %s', $this -> isoDate(), $this -> isoTime());
	}

	public function iso8601(): string {
		return $this -> dateTime -> format('Y-m-d\TH:i:sO');
	}

	public function isoDate(): string {
		return $this -> dateTime -> format('Y-m-d');
	}

	public function isoTime(): string {
		return "{$this -> hour()}:{$this -> min()}:{$this -> sec()}";
	}

	public function year() : string {
		return $this -> dateTime -> format('Y');
	}

	public function month(bool $leadingZero = true) : string {
		$month = $this -> dateTime -> format('m');
		if ($leadingZero) return Number::leadingZero((int)$month);
		return $month;
	}

	public function date(bool $leadingZero = true) : string {
		$date = $this -> dateTime -> format('d');
		if ($leadingZero) return Number::leadingZero((int)$date);
		return $date;
	}

	public function day(bool $shortForm = true): string {
		$format = $shortForm ? 'D' : 'l';
		return $this -> dateTime -> format($format);
	}

	public function monthStr(bool $shortForm = true): string {
		$format = $shortForm ? 'M' : 'F';
		return $this -> dateTime -> format($format);
	}

	public function hour(bool $twenty_four = true, bool $leadingZero = true): string {
		if ($twenty_four && $leadingZero) $format = 'H';
		else if ($twenty_four && !$leadingZero) $format = 'G';
		else if (!$twenty_four && $leadingZero) $format = 'h';
		else $format = 'g';
		return $this -> dateTime -> format($format);
	}

	public function min(bool $leadingZero = true): string {
		$min = $this -> dateTime -> format('i');
		return $leadingZero ? $min : (int) $min;
	}

	public function sec(bool $leadingZero = true): string {
		$sec = $this -> dateTime -> format('s');
		return $leadingZero ? $sec : (int) $sec;
	}

	public function ampm(bool $uppercase = true): string {
		$format = $uppercase ? 'A' : 'a';
		return $this -> dateTime -> format($format);
	}

	public function strDateTime(): string {
		return "{$this -> date()} {$this -> monthStr()} {$this -> year()}, {$this -> hour()}:{$this -> min()}";
	}

	public function echoDateTime(): void {
		echo $this -> strDateTime();
	}

	public function strDate(bool $separated = false): string {
		if ($separated) return "{$this -> date()}-{$this -> month()}-{$this -> year()}";
		else return "{$this -> date()} {$this -> monthStr()}, {$this -> year()}";
	}

	public function echoDate(bool $separated = false): void {
		echo $this -> strDate($separated);
	}

	public function strTime24(bool $sec = true): string {
		if ($sec) return "{$this -> hour()}:{$this -> min()}:{$this -> sec()}";
		else return "{$this -> hour()}:{$this -> min()}";
	}

	public function echoTime24($sec = true): void {
		echo $this -> strTime24($sec);
	}

	public function strTime(bool $sec = true, bool $ampm = true, bool $uppercase = true): string {
		if ($sec) {
			if ($ampm)
				return "{$this -> hour(false)}:{$this -> min()}:{$this -> sec()} {$this -> ampm($uppercase)}";
			else
				return "{$this -> hour(false)}:{$this -> min()}:{$this -> sec()}";
		} else {
			if ($ampm)
				return "{$this -> hour(false)}:{$this -> min()} {$this -> ampm($uppercase)}";
			else
				return "{$this -> hour(false)}:{$this -> min()}";
		}
	}

	public function echoTime(bool $sec = true, bool $ampm = true, bool $uppercase = true): void	{
		echo $this -> strTime($sec, $ampm, $uppercase);
	}

	public function getDateTime(): DateTime {
		return $this -> dateTime;
	}

	public function getTimezone(): DateTimeZone {
		return $this -> dateTime -> getTimezone();
	}

	public function getTimestamp(): int {
		return $this -> dateTime -> getTimestamp();
	}

	public function getMilliSeconds(): int {
		return $this -> dateTime -> getTimestamp() * 1000;
	}

	/**
	 * Using this method, the starting timestamp of the shomoy can be calculated.
	 *
	 * @return int the starting timestamp of the shomoy object.
	 */
	public function timestampStart(): int {
		return (date_create($this -> isoDate())) -> getTimestamp();
	}

	/**
	 * Using this method, the ending timestamp of the shomoy can be calculated.
	 *
	 * @return int the ending timestamp of the shomoy object.
	 */
	public function timestampEnd(): int {
		return $this -> timestampStart() - 1 + self::secInDay(1);
	}

	/**
	 * Formats this shomoy in the specified format.
	 *
	 * @param string $format The format
	 * @return ?string the formatted shomoy; null when to print the shomoy.
	 * */
	public function format(string $format, bool $print = false): ?string {
		$shomoy = $this -> dateTime -> format($format);

		if (!$print) return $shomoy;

		echo $shomoy;
		return null;
	}

	/**
	 * Formats the shomoy in human friendly style such as '1 day 4 hours' style. You
	 * can then append ' ago' to the string to complete the sentence. The time difference
	 * is always calculated from the current shomoy. A negitive sign in the string means,
	 * the time comaparing to current time is ahread of the current time meaning it is a
	 * future time.
	 *
	 * @param int $precision what component to show such as year, month, hour, min, sec
	 * @param bool $print indicates whether to print or return the formatted string
	 * @return ?string returns string or null based on print argument
	 * */
	public function formatInAgo(int $precision = 1, bool $print = false): ?string {
		$str = self::formatTimeInAgo($this, $precision);
		if (!$print) return $str;

		echo $str;
		return null;
	}

	/**
	 * This method adds given seconds, minutes, hours and day as seconds to current time. When no
	 * argument is set, then it returns current in seconds. All the argument's value will be
	 * converted into seconds before they gets added to the current time in second except the sec
	 * argument.
	 *
	 * All the arguments values have to be of type integer. If not, then an exception is thrown.
	 *
	 * This method can come in handy in situations like setting cookie value with expiration,
	 * calculating future date time etc.
	 *
	 * @param int $sec Number of seconds is to be added to the current time in second.
	 * @param int $min Number of minutes is to be added to the current time in second.
	 * @param int $hour Number of hours is to be added to the current time in second.
	 * @param int $day Number of days is to be added to the current time in second.
	 *
	 * @return int Seconds added to the current time as defined by the arguments.
	 *
	 * @throws Trunk If all the arguments are not of type integer
	 * */
	public static function addToNow(int $sec = 0, int $min = 0, int $hour = 0, int $day = 0): int {
		if (!is_int($day) || !is_int($hour) || !is_int($min) || !is_int($sec))
			throw new Trunk('Make sure day, hour and minute are of type int.');

		$now = time();

		if ($sec != 0) $now += $sec;
		if ($min != 0) $now += $min * 60;
		if ($hour != 0) $now += $hour * 60 * 60;
		if ($day != 0) $now += $day * 24 * 60 * 60;

		return $now;
	}

	public static function secInMin(int $of): int {
		return 60 * $of;
	}

	public static function secInHour(int $of): int {
		return 60 * 60 * $of;
	}

	public static function secInDay(int $of): int {
		return 60 * 60 * 24 * $of;
	}

	// This method either adds or removes interval to the datetime object
	// based on the sign of the value is being added or subtracted.
	private function adjustInterval(int $signed, $interval): void {
		if ($signed < 0)
			$interval = str_replace('-', '', $interval);

		try {
			$interval = new DateInterval($interval);
			if ($signed < 0) $this -> dateTime -> sub($interval);
			else $this -> dateTime -> add($interval);
		} catch (Exception) {}
	}

	/**
	 * This takes an ISO datetime input and prints time in 12hr format with many
	 * configuration as specified by the arguments.
	 *  *
	 * @param string $isoDatetime ISO datetime.
	 * @param bool $sec Indicates whether to print second or not.
	 * @param bool $ampm Whether to add AM/PM.
	 * @param bool $uppercase Whether to uppercase the AM/PM.
	 *
	 * @return void
	 * */
	public static function printTime(string $isoDatetime, bool $sec = true, bool $ampm = true, bool $uppercase = true): void {
		try {
			$shomoy = new Shomoy($isoDatetime);
			$shomoy -> echoTime($sec, $ampm, $uppercase);
		} catch (Exception) {}
	}

	/**
	 * It takes an ISO datetime input and prints out the time portion in with second
	 * or not as specified by the arguments. Prints nothing on encountering parse error.
	 *
	 * @param string $isoDatetime ISO datetime.
	 * @param bool $sec Indicates whether to print second or not.
	 *
	 * @return void
	 * */
	public static function printTime24(string $isoDatetime, bool $sec = true): void {
		try {
			$shomoy = new Shomoy($isoDatetime);
			$shomoy -> echoTime24($sec);
		} catch (Exception) {}
	}

	/**
	 * This takes an ISO Datetime as input and echo out this in two of formats. One when
	 * separated is false then it prints out as 01 Jan 2022. And when it is false then
	 * it prints out as 01-01-2022 format.
	 *
	 * On parse error for the date time, it doesn't print out anything.
	 *
	 * @param string $isoDatetime ISO datetime.
	 * @param bool $separated indicates whether to print date in iso date format in reverse
	 * format or in 01 Jan 2020 format.
	 *
	 * @return void
	 * */
	public static function printDate(string $isoDatetime, bool $separated = false): void {
		try {
			$shomoy = new Shomoy($isoDatetime);
			$shomoy -> echoDate($separated);
		} catch (Exception) {}
	}

	/**
	 * This takes an ISO Datetime as input and echo out this in the following format.
	 * 23 May 2022, 13:01
	 *
	 * On parse error for the date time, it doesn't print out anything.
	 *
	 * @param string $isoDatetime ISO datetime.
	 *
	 * @return void
	 * */
	public static function printDateTime(string $isoDatetime): void {
		try {
			$shomoy = new Shomoy($isoDatetime);
			$shomoy -> echoDateTime();
		} catch (Exception) {}
	}

	/**
	 * A helper method allows easy time formatting to specified format.
	 *
	 * @param string|int $time It could be an ISO datetime or a timestamp
	 * @param string $format The format
	 * @param bool $print whether to return the formatted value of prints it out
	 *
	 * @return ?string formatted value as string if true; else it prints it out and returns null
	 * */
	public static function formatAs(string|int $time, string $format, bool $print = false): ?string {

		if (gettype($time) == 'integer') {
			$shomoy = new Shomoy();
			$shomoy -> setTimestamp($time);
		} else {
			$shomoy = new Shomoy($time);
		}

		$str = $shomoy -> format($format);

		if (!$print) return $str;

		echo $str;
		return null;
	}

	/**
	 * A helper function allows easy formatting time difference in 'X Ago' style to display
	 * in more user friendly way. The time difference is always calculated from the current shomoy.
	 * A negitive sign in the string means, the time comaparing to current time is ahread of the
	 * current time meaning it is a future time.
	 *
	 * @param Shomoy|int|float $time A shomoy object or the seconds to calculate in relative
	 * to current time
	 * @param int $precision The precision down to year, month, day, hour, minute, sec for the formatting
	 * @return ?string in 'X Ago' style if print is false; otherwise it prints and returns null
	 * */
	public static function formatTimeInAgo(Shomoy|int|float $time, int $precision = 1, bool $print = false): ?string {
		$shomoy = new Shomoy();

		$timeAgo = $time instanceof Shomoy ? ($time -> getTimestamp()) : $time;

		$diff = $shomoy -> getTimestamp() - $timeAgo;
		$str = Number::formatTime($diff, $precision);

		if (!$print) return $str;

		echo $str;
		return null;
	}
	
	/**
	 * Helper method, returns the current datetime in ISO format
	 * @return string
	 * */
	public static function datetimeISO(): string {
		$shomoy = new Shomoy();
		return $shomoy -> iso();
	}
	
	/**
	 * Helper method, returns current date in ISO format
	 * @return string
	 * */
	public static function dateISO(): string {
		$shomoy = new Shomoy();
		return $shomoy -> isoDate();
	}
	
	/**
	 * Helper method, returns current time in ISO format
	 * @return string
	 * */
	public static function timeISO(): string {
		$shomoy = new Shomoy();
		return $shomoy -> isoTime();
	}

	/**
	 * Helper method, returns current datetime in ISO 8601 format
	 * @return string
	 * */
	public static function datetimeISO8601(): string {
		$shomoy = new Shomoy();
		return $shomoy -> iso8601();
	}
	
}