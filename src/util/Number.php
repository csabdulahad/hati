<?php

namespace hati\util;

use hati\filter\Filter;

/**
 * Number class has various methods relating formatting number in many forms
 * such as any number either integer or float can be represented in money-currency
 * format making it easier for money presentation to user. Conversely, it can convert
 * any money presentation in to numeric values.
 * */

abstract class Number {

	// Currency signs for countries
	public const SIGN_BD = '৳';
	public const SIGN_GBP = '£';
	public const SIGN_USD = '$';

	/**
	 * Format method can take any input and tries to represent it into either money format
	 * or a leading zero, decimal placed number format. The number can be either signed or
	 * unsigned. It cleverly, add the currency signs before and in between the negative sign.
	 *
	 * Since it takes mixed as input type, there are many possibilities that it could fail to
	 * to format a number. It tries its best to format a number by forcefully casting the input
	 * into float when both normal integer and float filtering fail. It prints out 0-0 on
	 * encountering error while formatting the input.
	 *
	 * @param mixed $input The input which is to converted.
	 * @param string $sign Any currency sign.
	 * @param bool $leadZero Indicates whether to add zero in front of the number.
	 * @param int $place Number of decimal place if the input is a floating number.
	 * @param bool $print whether to print out the formatted number
	 *
	 * @return ?string The formatted number as specified by the arguments or prints out based on
	 * print argument
	 * */
	public static function format(mixed $input, string $sign = '', bool $leadZero = false, int $place = 2, bool $print = false): ?string {

		// first see if it is an integer number or not
		$num = Filter::checkInt($input);

		// now see whether it is a floating number
		if (!Filter::isOK($num)) $num = Filter::checkFloat($input);

		// make sure we have a number
		if (!Filter::isOK($num)) {
			if (!$print) return '0-0';

			echo '0-0';
			return null;
		}

		// figure out whether it is a round up integer or floating value
		$integer = is_integer($num);

		// learn if it is a positive value
		$negative = $num < 0;

		$num = $negative ? abs($num) : $num;

		// create the symbol
		$symbol = $negative ? '-' : '';
		$symbol .= strlen($sign) != 0 ? $sign : '';

		// add decimal places
		$num = !$integer ? number_format($num, $place) : number_format($num);

		// add leading zero if asked
		$num = $leadZero ? self::leadingZero($num) : $num;

		$output = "$symbol$num";
		if (!$print) return $output;

		echo $output;
		return null;
	}
	
	/**
	 * Converts numbers from English to Bangla notation.
	 *
	 * This function takes a number or a string that represents a number in English
	 * notation and returns a string with each digit converted to Bangla. It's designed
	 * to work with both integer and floating-point numbers represented as strings.
	 * Non-numeric characters within the string are not converted but are preserved
	 * in the output.
	 *
	 * @param mixed $englishNumber - The number or string representing a number
	 *        to be converted from English to Bangla digits. This parameter can handle
	 *        both numeric and string types. For string inputs, the function iterates
	 *        through each character, converting numeric characters to Bangla while
	 *        leaving non-numeric characters unchanged.
	 *
	 * @returns string A string representation of the input number where each English
	 *         digit has been replaced with its corresponding Bangla digit. Non-numeric
	 *         characters in the input are returned as is in the output string.
	 *
	 * @example
	 * // Convert a numeric value
	 * convertToBanglaNumber(2023); // Outputs: ২০২৩
	 *
	 * // Convert a string representing a numeric value
	 * convertToBanglaNumber("4567"); // Outputs: ৪৫৬৭
	 *
	 * // Mixed input with non-numeric characters
	 * convertToBanglaNumber("Flight 370"); // Outputs: Flight ৩৭০
	 */
	public static function bdNum(mixed $englishNumber): string {
		// Mapping of English digits to Bangla digits
		$banglaDigits = [
			'0' => '০', '1' => '১', '2' => '২', '3' => '৩',
			'4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭',
			'8' => '৮', '9' => '৯'
		];
		
		// Convert the number to a string to iterate over each digit
		$englishNumberStr = (string) $englishNumber;
		
		// Replace each English digit with its Bangla counterpart
		$banglaNumberStr = '';
		foreach (str_split($englishNumberStr) as $char) {
			$banglaNumberStr .= $banglaDigits[$char] ?? $char; // Keep the character as is if not found in the map
		}
		
		return $banglaNumberStr;
	}
	
	/**
	 * Returns the Bengali ordinal representation of a number.
	 *
	 * This function returns the Bengali ordinal representation of a given number.
	 * Ordinal representations are defined for numbers 1 through 10 in Bengali.
	 * For numbers greater than 10, it appends the Bengali numeral for the number
	 * followed by the suffix 'তম' (th).
	 * If the input is not within the predefined range (1 to 10), it returns '০' (zero).
	 *
	 * @param mixed $num The number for which the Bengali ordinal representation is to be determined.
	 *                   If a string representation of a number is provided, it will be converted to an integer.
	 *
	 * @return string The Bengali ordinal representation of the given number.
	 *                If the input is not within the predefined range, '০' (zero) is returned.
	 */
	public static function bdOrdinal(mixed $num): string {
		// Ensure $num is treated as an integer
		$num = is_string($num) ? intval($num) : $num;
		
		// Define ordinal representations for 1 through 10
		$ordinals = [
			1 => 'প্রথম', 2 => 'দ্বিতীয়', 3 => 'তৃতীয়', 4 => 'চতুর্থ',
			5 => 'পঞ্চম', 6 => 'ষষ্ঠ', 7 => 'সপ্তম', 8 => 'অষ্টম',
			9 => 'নবম', 10 => 'দশম'
		];
		
		// Check if $num is in the predefined range
		if (array_key_exists($num, $ordinals)) {
			return $ordinals[$num];
		} elseif ($num > 10) {
			return self::bdNum($num) . 'তম';
		} else {
			return '০';
		}
	}

	/**
	 * This method takes in any money formatted value and converts it into
	 * either integer of floating number based on the input value.
	 *
	 * @param mixed $input The money formatted value.
	 * @param string $sign The sign is in the formatted value.
	 *
	 * @return int|float Returns integer or floating representation based on the input.
	 * */
	public static function moneyToNum(mixed $input, string $sign): int | float {
		$input = (string) $input;
		$val = str_replace($sign,'', $input);
		$int = Filter::checkInt($val);
		return Filter::isOK($int) ? (int) $int : (float) $val;
	}

	/**
	 * A leading zero is added when the integer number is less than 10. Otherwise
	 * the input number is returned.
	 *
	 * @param mixed $int The input which needs a leading zero.
	 *
	 * @return string A leading zero is added and returned as string, if the number
	 * is less than 10. Otherwise the original input is returned as string.
	 * */
	public static function leadingZero(mixed $int): string {
		return ($int < 10) ? "0$int" : $int;
	}

	/**
	 * Any floating number can be formatted to a fixed size decimal point place for
	 * displaying it more friendly on the UI.
	 *
	 * By default, it fixes the decimal point place to 2.
	 *
	 * @param int|float $number The floating value whose decimal place is to be fixed.
	 * @param int $place The decimal place for the display.
	 *
	 * @return string Formatted floating value to specified decimal point place.
	 * */
	public static function toFixed(int|float $number, int $place = 2): string {
		return number_format($number, $place);
	}

	/**
	 * Formats a numbers as bytes, based on size, and adds the appropriate suffix
	 *
	 * @param mixed $num will be cast as int
	 * @param int $precision The precision
	 * @return string nicely formatted byte number in string
	 */
	public static function formatByte(mixed $num, int $precision = 1): string {

		if ($num >= 1000000000000)
		{
			$num = round($num / 1099511627776, $precision);
			$unit = 'TERA-Byte';
		}
		elseif ($num >= 1000000000)
		{
			$num = round($num / 1073741824, $precision);
			$unit = 'GB';
		}
		elseif ($num >= 1000000)
		{
			$num = round($num / 1048576, $precision);
			$unit = 'MB';
		}
		elseif ($num >= 1000)
		{
			$num = round($num / 1024, $precision);
			$unit = 'KB';
		}
		else
		{
			$unit = 'byte';
			return number_format($num).' '.$unit;
		}

		return number_format($num, $precision).' '.$unit;
	}

	/**
	 * A helper function brought from Symfony Console package. It allows formatting time
	 * in seconds for human-like style. For 120 seconds, you can output it like: 2 mins
	 * style.
	 *
	 * @param int|float $secs The seconds
	 * @param int $precision The precision down to year, month, day, hour, minute, sec for
	 * the formatting
	 * @return string The formatted time value in string
	 * */
	public static function formatTime(int|float $secs, int $precision = 1): string {
		$secs = (int) floor($secs);

		if (0 === $secs) {
			return '< 1 sec';
		}

		static $timeFormats = [
			[1, '1 sec', 'secs'],
			[60, '1 min', 'mins'],
			[3600, '1 hr', 'hrs'],
			[86400, '1 day', 'days'],
		];

		$times = [];
		foreach ($timeFormats as $index => $format) {
			$seconds = isset($timeFormats[$index + 1]) ? $secs % $timeFormats[$index + 1][0] : $secs;

			if (isset($times[$index - $precision])) {
				unset($times[$index - $precision]);
			}

			if (0 === $seconds) {
				continue;
			}

			$unitCount = ($seconds / $format[0]);
			$times[$index] = 1 === $unitCount ? $format[1] : $unitCount.' '.$format[2];

			if ($secs === $seconds) {
				break;
			}

			$secs -= $seconds;
		}

		return implode(', ', array_reverse($times));
	}

}