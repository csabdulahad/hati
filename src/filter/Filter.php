<?php

namespace hati\filter;

/**
 * Filter class is very helpful and handy in situations where user inputs need to be
 * filtered and sanitized. It has many methods which are of different capabilities.
 * All the methods are safe as they use both validation and sanitization.
 *
 * They all returns the validated & sanitized input value upon successful filtering. To
 * check if the data returned by one of filter functions was all ok, use {@link Filter::ok()}
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
 * These patterns removes other characters from the input as specified.
 **/

abstract class Filter {

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
	 * With Hati 5, filter functions can return various {@link FilterOut} enum values. To
	 * make simple whether a filter has passed and sanitized the input successfully, use
	 * this function on the returned value by filter function.
	 *
	 * @param mixed $value value returned by any filter functions
	 * @return bool true if the input was all ok based on type & sanitization. Otherwise false.
	 * */
	public static function ok(mixed $value): bool {
		return !in_array($value, [
			FilterOut::NULL, FilterOut::EMPTY, FilterOut::ILLEGAL, FilterOut::INVALID,
			FilterOut::VAL_LEN_ERROR, FilterOut::VAL_LEN_UNDER_ERROR, FilterOut::VAL_LEN_OVER_ERROR,
			FilterOut::RANGE_ERROR, FilterOut::RANGE_UNDER_ERROR, FilterOut::RANGE_OVER_ERROR,
			FilterOut::RANGE_FRACTION_ERROR, FilterOut::NOT_IN_OPTION
		]);
	}

	/**
	 * A date input can be checked for ISO formatted date. This method first try to match
	 * ISO date format in the input. Upon match it removes the matched ISO date with
	 * empty space. Then it counts the remaining characters. It it was a valid formatted
	 * ISO date then should have no remaining characters. Thus confirming a valid ISO
	 * formatted date input.
	 *
	 * @param mixed $input the string to be checked for ISO date format.
	 *
	 * @return string|FilterOut returns the input if the input is a valid ISO formatted date. on failure
	 * it can return one of these based on checks:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY}, {@link FilterOutput::INVALID}
	 * */
	public static function isoDate(mixed $input): string|FilterOut {

		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		// check whether we have empty input
		if (strlen($input) < 1) {
			return FilterOut::EMPTY;
		}

		// try to remove the YYYY-MM-DD match from the input if there is any
		$date = self::sanitize($input, '#(\d{4}-\d{2}-\d{2})#');

		// it it was a valid ISO formatted date then it should have no character
		// remaining after the filter.
		$pass = strlen($date) == 0;

		return !$pass ? FilterOut::INVALID : $input;
	}

	/**
	 * Very similar to {@link Filter::isoDate()}. It only validates input as a valid fully
	 * qualified ISO datetime in YYYY-MM-DD HH:MM:SS format.
	 *
	 * @param mixed $input the string to be checked for ISO date format.
	 *
	 * @return string|FilterOut returns the input if the input is a valid ISO formatted date. on failure
	 * it can return one of these output:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY}, {@link FilterOutput::INVALID}
	 * */
	public static function isoDatetime(mixed $input): string|FilterOut {
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		// check whether we have empty input
		if (strlen($input) < 1) {
			return FilterOut::EMPTY;
		}

		// try to remove the YYYY-MM-DD match from the input if there is any
		$date = self::sanitize($input, '#(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})#');

		// it it was a valid ISO formatted date then it should have no character
		// remaining after the filter.
		$pass = strlen($date) == 0;

		return !$pass ? FilterOut::INVALID : $input;
	}

	/**
	 * This method can work with any {@link FilterOut} defined sanitizing patterns of client code defined
	 * pattern as method argument. By default, upon matching the pattern it replaces with an empty
	 * string.
	 *
	 * @param string $value the string, the subject of the pattern matching.
	 * @param string $pattern any @link Filter defined or client code defined regular expression.
	 * @param string $replacement to replace the matched patter.
	 *
	 * @return string it returns the replaced input as string upon matching the pattern.
	 * */
	public static function sanitize(string $value, string $pattern, string $replacement = ''): string {
		return preg_replace($pattern, $replacement, $value);
	}

	/**
	 * This method checks email. It first checks whether the email is valid or not.
	 * If it is valid then it sanitize the email and returns.
	 *
	 * On invalid email argument, it behaves differently. If trigger error is set then
	 * it throws HatiError. If not then it returns null value to indicate that the email
	 * was failed to pass the check.
	 *
	 * @param mixed $input string containing email to be checked
	 *
	 * @return string|FilterOut sanitized email. On failure, it can return one of these:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY}, {@link FilterOutput::INVALID}
	 * */
	public static function email(mixed $input): string|FilterOut {
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		// check whether we have empty input
		if (empty($input)) {
			return FilterOut::EMPTY;
		}

		$isEmail = filter_var($input, FILTER_VALIDATE_EMAIL);
		if (!$isEmail) return FilterOut::INVALID;

		return  filter_var($input, FILTER_SANITIZE_EMAIL);
	}

	/**
	 * This method checks for integer. It first checks whether the input is a valid
	 * integer or not. If it is valid then it sanitize the number and returns.
	 *
	 * On invalid argument, it behaves differently. If trigger error is set then it
	 * throws HatiError. If not then it returns null value to indicate that the number
	 * was failed to pass the check.
	 *
	 * @param mixed $input string or integer to be checked
	 *
	 * @return int|FilterOut sanitized integer. On failure it can return one of these:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY},
	 * {@link FilterOutput::ILLEGAL}, {@link FilterOutput::INVALID}
	 * */
	public static function int(mixed $input): int|FilterOut {
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		// check whether we have empty input
		if (empty($input) && $input != 0) {
			return FilterOut::EMPTY;
		}

		// let's see if we have got any illegal character in the input by removing a
		// valid either signed or unsigned value from the the input then assess the
		// length of it. For a valid integer of either signed or unsigned it should
		// have a length of zero after filtering.
		$filter = strlen(preg_replace('#-?\d+#', '', $input));
		if ($filter > 0) {
			return FilterOut::ILLEGAL;
		}

		// capture absolute zero value as an integer
		$input = (int) $input;
		if ($input === 0) return $input;

		$isInt = filter_var($input, FILTER_VALIDATE_INT);
		if (!$isInt) return FilterOut::INVALID;

		return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
	}

	/**
	 * This method checks for floating number. It the number is a floating number then
	 * it just returns it.
	 *
	 * On invalid argument, it behaves differently. If trigger error is set then it
	 * throws HatiError. If not then it returns null value to indicate that the number
	 * was failed to pass the floating check.
	 *
	 * On any input given, it checks whether it has any dot in the number or the string.
	 * If not found then it adds one with 0 at the end after the decimal point to make
	 * it a valid decimal point number for the check logic to work.
	 *
	 * @param mixed $input string or number to be checked
	 *
	 * @return float|FilterOut sanitized floating number. On failure it can return one of these:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY},
	 * {@link FilterOutput::ILLEGAL}, {@link FilterOutput::INVALID}
	 * */
	public static function float(mixed $input): float|FilterOut {
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		// check whether we have empty input
		if (empty($input)) {
			return FilterOut::EMPTY;
		}

		// add the floating point place if it has not
		if (!preg_match('#\.#', $input)) $input .= '.0';

		// let's see if we have got any illegal character in the input by removing a
		// valid either signed or unsigned value from the the input then assess the
		// length of it. For a valid floating of either signed or unsigned it should
		// have a length of zero after filtering.
		$filter = strlen(preg_replace('#-?\d+\.\d+#', '', $input));
		if ($filter > 0) {
			return FilterOut::ILLEGAL;
		}

		$isFloat = filter_var($input, FILTER_VALIDATE_FLOAT);
		if (!$isFloat) return FilterOut::INVALID;

		return $input;
	}

	/**
	 * Any string can be sanitized by this method. This method sanitize special characters
	 * from the string html tags brackets, double quotes, ampersand will be escaped with
	 * html entities values.
	 *
	 * Additionally it checks for empty string value. If an empty string is passed-in as
	 * argument then it throws HatiError based on the setting.
	 *
	 * @param mixed $input string to be escaped
	 *
	 * @return string|FilterOut returns the escaped string. On failure, it can return one of these:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY}
	 * */
	public static function string(mixed $input): string|FilterOut {
		// check if we have empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		$empty = empty($input);
		if ($empty) return FilterOut::EMPTY;

		return filter_var($input, FILTER_SANITIZE_SPECIAL_CHARS);
	}

	/**
	 * This method validates a string as url first then on successful validation it returns
	 * sanitized url.
	 *
	 * @param mixed $input the url string for checking.
	 *
	 * @return string|FilterOut the sanitized url string. On failure, it can return one of these:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY}, {@link FilterOutput::INVALID}
	 */
	public static function url(mixed $input): string|FilterOut {
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		// check whether we have empty input
		if (strlen($input) < 1) {
			return FilterOut::EMPTY;
		}

		$isUrl = filter_var($input, FILTER_VALIDATE_URL);
		if (!$isUrl) return FilterOut::INVALID;

		return filter_var($input, FILTER_SANITIZE_URL);
	}

	/**
	 * This method uses the FILTER_VALIDATE_BOOLEAN as filter which can support many possible
	 * values of boolean type in php such as On, 1, yes, true, false, no, 0, off. If the input
	 * is not any of the above then it considers the input as invalid & throws
	 * {@link FilterOut} type.
	 *
	 * @param mixed $input a string possible holding boolean value.
	 *
	 * @return bool|FilterOut the boolean value. On failure it can return one of these:
	 * {@link FilterOutput::NULL}, {@link FilterOutput::EMPTY}, {@link FilterOutput::INVALID}
	 * */
	public static function bool(mixed $input): bool|FilterOut {
		// check whether the input is null & empty
		if ($input === null) {
			return FilterOut::NULL;
		}

		// check whether we have empty input
		if (gettype($input) != 'boolean' && strlen($input) < 1) {
			return FilterOut::EMPTY;
		}

		$isBool = filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($isBool === null) return FilterOut::INVALID;

		return $isBool;
	}

	/**
	 * Using this method, a string can be checked whether it is in a length or not. Both min and max
	 * range arguments are optional. If they both are not set then no checking is performed and true
	 * value is returned. If any range limit is set and trigger is set then it throws exception on
	 * failure.
	 *
	 * The min and max argument are inclusive when the check is performed.
	 *
	 * @param string $input the string has to be checked.
	 * @param ?int $min the min length of the input.
	 * @param ?int $max the max length of the input.
	 *
	 * @return string|FilterOut returns the input if it is within the range, otherwise it can return one of these:<br>
	 * - {@link FilterOutput::RANGE_ERROR} : When input is not in preferred length<br>
	 * - {@link FilterOutput::UNDER_ERROR} : When input is below the minimum length<br>
	 * - {@link FilterOutput::OVER_ERROR}  : When input is over the maximum length
	 */
	public static function strLen(string $input, ?int $min = null, ?int $max = null): string|FilterOut {
		$minPass = false;
		$maxPass = false;
		if ($min != null) $minPass = strlen($input) >= $min;
		if ($max != null) $maxPass = strlen($input) <= $max;

		if ($min != null && $max != null && (!$minPass || !$maxPass)) {
			return FilterOut::VAL_LEN_ERROR;
		}

		if ($min != null && !$minPass) {
			return FilterOut::VAL_LEN_UNDER_ERROR;
		}

		if ($max != null && !$maxPass) {
			return FilterOut::VAL_LEN_OVER_ERROR;
		}

		return $input;
	}

	/**
	 * Using this method, an integer can be checked whether it is in range or not. Both min and max
	 * range arguments are optional. If they both are not set then no checking is performed and true
	 * value is returned. If any range limit is set and trigger is set then it throws exception on
	 * failure.
	 *
	 * The min and max argument are inclusive when the check is performed.
	 *
	 * @param int $input the integer has to be checked.
	 * @param ?int $min the min limit of the input.
	 * @param ?int $max the max limit of the input.
	 *
	 * @return int|FilterOut true if the input is within the range, otherwise it returns:.<br>
	 *  - {@link FilterOutput::RANGE_ERROR} : When input is not in preferred value<br>
	 *  - {@link FilterOutput::UNDER_ERROR} : When input is below the minimum value<br>
	 *  - {@link FilterOutput::OVER_ERROR}  : When input is over the maximum value
	 * **/
	public static function intLimit(int $input, ?int $min = null, ?int $max = null): int|FilterOut {
		$haveBothRange = $min != null && $max != null;

		$minPass = false;
		$maxPass = false;
		if ($min != null) $minPass = $input >= $min;
		if ($max != null) $maxPass = $input <= $max;

		if ($haveBothRange && (!$minPass || !$maxPass)) {
			return FilterOut::RANGE_ERROR;
		}

		if ($min != null && !$minPass) {
			return FilterOut::RANGE_UNDER_ERROR;
		}

		if ($max != null && !$maxPass) {
			return FilterOut::RANGE_OVER_ERROR;
		}

		return $input;
	}

}