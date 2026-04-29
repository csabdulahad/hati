<?php

namespace hati\filter;

use DateTimeImmutable;

/**
 * Filter class is very helpful and handy in situations where user inputs need to be
 * filtered and sanitized. It has many methods which are of different capabilities.
 * All the methods are safe as they use both validation and sanitization.
 *
 * They all returns the validated & sanitized input value upon successful filtering. To
 * check if the data returned by one of filter functions was all ok, use {@link Filter::isOK()}
 * method on the returned value. For example:
 *
 * <code>
 * $email = Filter::email($_POST['email']);
 * if (Filter::ok($email)) {
 *		echo 'Email accepted';
 * } else {
 *     echo 'Email invalid';
 * }
 * </code>
 *
 * There is one special method {@link Filter::sanitize()} which can directly work with any regular
 * expression or some predefined pattern constants by Filter class. Predefined regular expression
 * pattern for filtering input in various formats are provided. Those constants start with <b>SAN_</b>
 * name. For example: <code>Filter::SAN_ANSD</code>
 * In the naming of these constants, they have meaning like regular expression.<br>
 * - A    = Alphabets(including capital & small letters)
 * - N    = Numbers
 * - S    = Space
 * - C    = Comma
 * - D    = Dot
 *
 * These patterns remove other characters from the input as specified.
 **/

abstract class Filter
{

	/** Alphabets A-Z a-z */
	public const SAN_A = '#[^a-zA-Z]#';

	/** Numbers 0-9 */
	public const SAN_N = '#[^0-9]#';

	/** Alphabets & numbers */
	public const SAN_AN = '#[^a-zA-Z0-9]#';

	/** Alphabets & spaces*/
	public const SAN_AS = '#[^a-zA-Z\s]#';

	/** Alphabets & commas */
	public const SAN_AC = '#[^a-zA-Z,]#';

	/** Alphabets & dots */
	public const SAN_AD = '#[^a-zA-Z.]#';

	/** Alphabets, numbers & spaces */
	public const SAN_ANS = '#[^a-zA-Z0-9\s]#';

	/** Alphabets, spaces & commas */
	public const SAN_ASC = '#[^a-zA-Z\s,]#';

	/** Alphabets, numbers & dots */
	public const SAN_AND = '#[^a-zA-Z0-9.]#';

	/** Alphabets, numbers, spaces & commas */
	public const SAN_ANSC = '#[^a-zA-Z0-9\s,]#';

	/** Alphabets, numbers, spaces & dots */
	public const SAN_ANSD = '#[^a-zA-Z0-9\s.]#';

	/** Alphabets, numbers, spaces, commas & dots */
	public const SAN_ANSCD = '#[^a-zA-Z0-9\s,.]#';

	/**
	 * Filter functions can return various {@link FilterOut} enum values. To
	 * make simple whether a filter has passed and sanitized the input successfully, use
	 * this function on the returned value by filter function.
	 *
	 * @param mixed $value value returned by any filter functions
	 * @return bool true if the input was all ok based on type & sanitization. Otherwise, false.
	 * */
	public static function isOK(mixed $value): bool
	{
		if (!$value instanceof FilterOut) {
			return true;
		}
		
		return $value === FilterOut::OK;
	}

	public static function checkISODate(mixed $input): string|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (!is_scalar($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim((string) $input);
		
		// check whether we have empty input
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		
		if (!$date || $date->format('Y-m-d') !== $value) {
			return FilterOut::INVALID;
		}
		
		return $value;
	}

	public static function checkISOTime(mixed $input): string|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (!is_scalar($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim((string) $input);
		
		// check whether we have empty input
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		$time = DateTimeImmutable::createFromFormat('!H:i:s', $value);
		
		if (!$time || $time->format('H:i:s') !== $value) {
			return FilterOut::INVALID;
		}
		
		return $value;
	}
	
	public static function checkISODatetime(mixed $input): string|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (!is_scalar($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim((string) $input);
		
		// check whether we have empty input
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
		
		if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
			return FilterOut::INVALID;
		}
		
		return $value;
	}
	
	public static function sanitize(string $value, string $pattern, string $replacement = ''): string
	{
		return preg_replace($pattern, $replacement, $value);
	}

	public static function checkEmail(mixed $input): string|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (!is_scalar($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim((string) $input);
		
		// check whether we have empty input
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		$email = filter_var($value, FILTER_VALIDATE_EMAIL);
		
		if ($email === false) {
			return FilterOut::INVALID;
		}
		
		return $value;
	}
	
	public static function checkInt(mixed $input): int|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (is_int($input)) {
			return $input;
		}
		
		if (!is_string($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim($input);

		// check whether we have empty input
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		if (!preg_match('/^[+-]?\d+$/', $value)) {
			return FilterOut::ILLEGAL;
		}
		
		$int = filter_var($value, FILTER_VALIDATE_INT);
		
		if ($int === false) {
			return FilterOut::INVALID;
		}
		
		return $int;
	}

	public static function checkFloat(mixed $input): float|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (is_float($input) || is_int($input)) {
			return (float) $input;
		}
		
		if (!is_string($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim($input);
		
		// check whether we have empty input
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $value)) {
			return FilterOut::ILLEGAL;
		}
		
		$float = filter_var($value, FILTER_VALIDATE_FLOAT);
		
		if ($float === false) {
			return FilterOut::INVALID;
		}
		
		return $float;
	}
	
	public static function checkString(mixed $input, ?int $flag = FILTER_SANITIZE_FULL_SPECIAL_CHARS): string|FilterOut
	{
		// check if we have empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (!is_string($input)) {
			return FilterOut::INVALID;
		}
		
		$value = (string) $input;
		
		if (trim($value) === '') {
			return FilterOut::EMPTY;
		}
		
		if ($flag === null) {
			return $value;
		}
		
		$filtered = filter_var($value, $flag);
		
		if ($filtered === false) {
			return FilterOut::INVALID;
		}
		
		return $filtered;
	}

	public static function checkURL(mixed $input): string|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (!is_scalar($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim((string) $input);

		// check whether we have empty input
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		$url = filter_var($value, FILTER_VALIDATE_URL);
		
		if ($url === false) {
			return FilterOut::INVALID;
		}
		
		return $value;
	}
	
	public static function checkBool(mixed $input): bool|FilterOut
	{
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}
		
		if (is_bool($input)) {
			return $input;
		}
		
		if (!is_scalar($input)) {
			return FilterOut::INVALID;
		}
		
		$value = trim((string) $input);
		
		if ($value === '') {
			return FilterOut::EMPTY;
		}
		
		$bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		
		if ($bool === null) {
			return FilterOut::INVALID;
		}
		
		return $bool;
	}
	
	public static function checkStrLen(string $input, ?int $min = null, ?int $max = null): string|FilterOut
	{
		$length =
			function_exists('mb_strlen')
			? mb_strlen($input, 'UTF-8')
			: strlen($input);
		
		$minPass = true;
		$maxPass = true;
		
		if ($min !== null) {
			$minPass = $length >= $min;
		}
		
		if ($max !== null) {
			$maxPass = $length <= $max;
		}
		
		if ($min !== null && $max !== null && (!$minPass || !$maxPass)) {
			return FilterOut::VAL_LEN_ERROR;
		}
		
		if ($min !== null && !$minPass) {
			return FilterOut::VAL_LEN_UNDER_ERROR;
		}
		
		if ($max !== null && !$maxPass) {
			return FilterOut::VAL_LEN_OVER_ERROR;
		}
		
		return $input;
	}

	public static function checkIntLimit(int $input, ?int $min = null, ?int $max = null): int|FilterOut
	{
		$haveBothRange = $min !== null && $max !== null;
		
		$minPass = true;
		$maxPass = true;
		
		if ($min !== null) {
			$minPass = $input >= $min;
		}
		
		if ($max !== null) {
			$maxPass = $input <= $max;
		}
		
		if ($haveBothRange && (!$minPass || !$maxPass)) {
			return FilterOut::RANGE_ERROR;
		}
		
		if ($min !== null && !$minPass) {
			return FilterOut::RANGE_UNDER_ERROR;
		}
		
		if ($max !== null && !$maxPass) {
			return FilterOut::RANGE_OVER_ERROR;
		}
		
		return $input;
	}

	/**
	 * Checks a value for integer number. If it isn't a valid integer then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function int(mixed $value, mixed $defVal = 0): mixed
	{
		$int = self::checkInt($value);
		return self::isOK($int) ? $int : $defVal;
	}
	
	/**
	 * Checks a value for float number. If it isn't a valid float then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function float(mixed $value, mixed $defVal = 0): mixed
	{
		$int = self::checkFloat($value);
		return self::isOK($int) ? $int : $defVal;
	}
	
	public static function string(mixed $value, mixed $defVal = null): mixed
	{
		$string = self::checkString($value);
		return self::isOK($string) ? $string : $defVal;
	}
	
	/**
	 * Checks a value for boolean. If it isn't a valid boolean value then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function bool(mixed $value, mixed $defVal = false): mixed
	{
		$int = self::checkBool($value);
		return self::isOK($int) ? $int : $defVal;
	}
	
	/**
	 * Checks a value for email. If it isn't a valid email then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function email(mixed $value, mixed $defVal = null): mixed
	{
		$email = self::checkEmail($value);
		return self::isOK($email) ? $email : $defVal;
	}
	
	/**
	 * Checks a value for url. If it isn't a valid url then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function url(mixed $value, mixed $defVal = null): mixed
	{
		$url = self::checkURL($value);
		return self::isOK($url) ? $url : $defVal;
	}
	
	/**
	 * Checks a value for iso time. If it isn't a valid iso time then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function isoTime(mixed $value, mixed $defVal = null): mixed
	{
		$isoDate = self::checkISOTime($value);
		return self::isOK($isoDate) ? $isoDate : $defVal;
	}
	
	/**
	 * Checks a value for iso date. If it isn't a valid iso date then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function isoDate(mixed $value, mixed $defVal = null): mixed
	{
		$isoDate = self::checkISODate($value);
		return self::isOK($isoDate) ? $isoDate : $defVal;
	}
	
	/**
	 * Checks a value for iso datetime. If it isn't a valid iso datetime then default
	 * value is returned.
	 *
	 * @param mixed $value
	 * @param mixed $defVal
	 * @return mixed
	 * */
	public static function isoDateTime(mixed $value, mixed $defVal = null): mixed
	{
		$isoDateTime = self::checkISODateTime($value);
		return self::isOK($isoDateTime) ? $isoDateTime : $defVal;
	}
	
}