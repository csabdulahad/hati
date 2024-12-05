<?php

/** @noinspection SpellCheckingInspection */

namespace hati\util;

use Exception;
use hati\cli\CLI;
use InvalidArgumentException;
use function ord;
use function strlen;

/**
 * A helper calss makes it easier to deal with text in PHP!
 *
 * @since 5.0.0
 * */

abstract class Text {

	/**
	 * Checks if a string is of multibyte or not
	 *
	 * @param string $string the string to be checked
	 * @return true if the sting is multibyte; false otherwise
	 * */
	public static function isMultibyte(string $string): bool {
		$length = strlen($string);

		for ($i = 0; $i < $length; $i++) {
			$value = ord($string[$i]);
			if ($value > 128) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A title-description arrays can be printed in a nice table like structure suitable
	 * for printing in CLI or showing to user nicely.
	 *
	 * @param array $titles containing the titles column
	 * @param array $descriptions containing the description column
	 * @param int $gap the gap between the title & description column
	 * @param int $desWidth the limit for the width of the description to be wrapped when it is long
	 * @param bool $return the formatted title-description when set true; otherwise prints that out
	 *
	 * @return ?string when the $return is set to false. Otherwise the string is returned.
	 * */
	public static function table2D(array $titles, array $descriptions, int $gap = 5, int $desWidth = 50, bool $return = false): ?string {
		$maxLen = max(array_map('strlen', $titles));

		$str = '';
		for ($i = 0; $i < count($titles); $i++) {
			$str .=
				// Display the title on the left of the row
				substr(
					$titles[$i] . str_repeat(' ', $maxLen + $gap),
					0,
					$maxLen + $gap
				) .
				// Wrap the descriptions in a right-hand column
				// with its left side 4 characters wider than
				// the longest item on the left.
				CLI::wrap($descriptions[$i], $desWidth, $maxLen + $gap)
			;
			$str .= "\n";
		}
		$str = rtrim($str, "\n");

		if ($return) return $str;

		CLI::write($str);
		return null;
	}

	/**
	 * Takes a plural word and makes it singular
	 *
	 * @param string $string Input string
	 * @return string Singular version of the noun input
	 */
	function singular(string $string): string {
		$result = $string;

		if (! self::isPluralizable($result)) {
			return $result;
		}

		// Arranged in order.
		$singularRules = [
			'/(matr)ices$/'                                                   => '\1ix',
			'/(vert|ind)ices$/'                                               => '\1ex',
			'/^(ox)en/'                                                       => '\1',
			'/(alias)es$/'                                                    => '\1',
			'/([octop|vir])i$/'                                               => '\1us',
			'/(cris|ax|test)es$/'                                             => '\1is',
			'/(shoe)s$/'                                                      => '\1',
			'/(o)es$/'                                                        => '\1',
			'/(bus|campus)es$/'                                               => '\1',
			'/([m|l])ice$/'                                                   => '\1ouse',
			'/(x|ch|ss|sh)es$/'                                               => '\1',
			'/(m)ovies$/'                                                     => '\1\2ovie',
			'/(s)eries$/'                                                     => '\1\2eries',
			'/([^aeiouy]|qu)ies$/'                                            => '\1y',
			'/([lr])ves$/'                                                    => '\1f',
			'/(tive)s$/'                                                      => '\1',
			'/(hive)s$/'                                                      => '\1',
			'/([^f])ves$/'                                                    => '\1fe',
			'/(^analy)ses$/'                                                  => '\1sis',
			'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/' => '\1\2sis',
			'/([ti])a$/'                                                      => '\1um',
			'/(p)eople$/'                                                     => '\1\2erson',
			'/(m)en$/'                                                        => '\1an',
			'/(s)tatuses$/'                                                   => '\1\2tatus',
			'/(c)hildren$/'                                                   => '\1\2hild',
			'/(n)ews$/'                                                       => '\1\2ews',
			'/(quiz)zes$/'                                                    => '\1',
			'/([^us])s$/'                                                     => '\1',
		];

		foreach ($singularRules as $rule => $replacement) {
			if (preg_match($rule, $result)) {
				$result = preg_replace($rule, $replacement, $result);
				break;
			}
		}

		return $result;
	}

	/**
	 * Takes a singular word and makes it plural
	 *
	 * @param string $string Input string
	 * @return string Plural version of the input
	 */
	public static function plural(string $string): string {
		$result = $string;

		if (! self::isPluralizable($result)) {
			return $result;
		}

		$pluralRules = [
			'/(quiz)$/'               => '\1zes',    // quizzes
			'/^(ox)$/'                => '\1\2en', // ox
			'/([m|l])ouse$/'          => '\1ice', // mouse, louse
			'/(matr|vert|ind)ix|ex$/' => '\1ices', // matrix, vertex, index
			'/(x|ch|ss|sh)$/'         => '\1es', // search, switch, fix, box, process, address
			'/([^aeiouy]|qu)y$/'      => '\1ies', // query, ability, agency
			'/(hive)$/'               => '\1s', // archive, hive
			'/(?:([^f])fe|([lr])f)$/' => '\1\2ves', // half, safe, wife
			'/sis$/'                  => 'ses', // basis, diagnosis
			'/([ti])um$/'             => '\1a', // datum, medium
			'/(p)erson$/'             => '\1eople', // person, salesperson
			'/(m)an$/'                => '\1en', // man, woman, spokesman
			'/(c)hild$/'              => '\1hildren', // child
			'/(buffal|tomat)o$/'      => '\1\2oes', // buffalo, tomato
			'/(bu|campu)s$/'          => '\1\2ses', // bus, campus
			'/(alias|status|virus)$/' => '\1es', // alias
			'/(octop)us$/'            => '\1i', // octopus
			'/(ax|cris|test)is$/'     => '\1es', // axis, crisis
			'/s$/'                    => 's', // no change (compatibility)
			'/$/'                     => 's',
		];

		foreach ($pluralRules as $rule => $replacement) {
			if (preg_match($rule, $result)) {
				$result = preg_replace($rule, $replacement, $result);
				break;
			}
		}

		return $result;
	}

	/**
	 * Checks if the given word has a plural version.
	 *
	 * @param string $word Word to check
	 * @return bool true if so; false otherwise
	 */
	public static function isPluralizable(string $word): bool {
		$uncountables = in_array(
			strtolower($word), [
				'advice', 'bravery', 'butter', 'chaos', 'clarity', 'coal', 'courage', 'cowardice', 'curiosity',
				'education', 'equipment', 'evidence', 'fish', 'fun', 'furniture', 'greed', 'help', 'homework',
				'honesty', 'information', 'insurance', 'jewelry', 'knowledge', 'livestock', 'love', 'luck', 'marketing',
				'meta', 'money', 'mud', 'news', 'patriotism', 'racism', 'rice', 'satisfaction', 'scenery', 'series',
				'sexism', 'silence', 'species', 'spelling', 'sugar', 'water', 'weather', 'wisdom', 'work',
			],
			true
		);

		return ! $uncountables;
	}

	/**
	 * Takes multiple words separated by spaces or underscores and camelizes them
	 *
	 * @param string $string Input string
	 * @return string camelized version of the input string
	 */
	public static function camelize(string $string): string {
		return lcfirst(str_replace(' ', '', ucwords(preg_replace('/[\s_]+/', ' ', $string))));
	}
	
	/**
	 * Converts a string with a given separator into camelCase format.
	 *
	 * @param string $string The input string to be converted.
	 *                       Example: "trans-typed-data".
	 * @param string $separator The character that separates words in the input string.
	 *                          Default is '-'. Example: '-' or '_'.
	 *
	 * @return string The converted camelCase string.
	 *                Example: "transTypedData".
	 *
	 * @example
	 * toCamelCase("trans-typed-data");          // Returns: "transTypedData"
	 * toCamelCase("trans_typed_data", "_");     // Returns: "transTypedData"
	 */
	public static function toCamelCase(string $string, string $separator = '-'): string {
		$camelCase = str_replace(' ', '', ucwords(str_replace($separator, ' ', $string)));
		return lcfirst($camelCase);
	}
	
	/**
	 * Converts a camelCase string into a lowercased string with a given separator.
	 *
	 * @param string $string The camelCase string to be converted.
	 *                       Example: "transTypedData".
	 * @param string $separator The character to insert between words in the output string.
	 *                          Default is '-'. Example: '-' or '_'.
	 *
	 * @return string The deCamelCased string with words separated by the given separator.
	 *                Example: "trans-typed-data".
	 *
	 * @example
	 * deCamelCase("transTypedData");          // Returns: "trans-typed-data"
	 * deCamelCase("transTypedData", "_");     // Returns: "trans_typed_data"
	 */
	public static function deCamelCase(string $string, string $separator = '-'): string {
		return strtolower(preg_replace('/([a-z])([A-Z])/', '$1' . $separator . '$2', $string));
	}

	/**
	 * Limits a string to X number of words.
	 *
	 * @param string $str the input string
	 * @param int $limit the number of words
	 * @param string $endChar the end character. Usually an ellipsis
	 * @return string string having limited words as specified
	 */
	public static function limitWord(string $str, int $limit = 100, string $endChar = '…'): string {
		if (trim($str) === '') {
			return $str;
		}

		preg_match('/^\s*+(?:\S++\s*+){1,' . $limit . '}/', $str, $matches);

		if (strlen($str) === strlen($matches[0])) {
			$endChar = '';
		}

		return rtrim($matches[0]) . $endChar;
	}

	/**
	 * Limits the string based on the character count. Preserves complete words
	 * so the character count may not be exactly as specified.
	 *
	 * @param string $str the input string
	 * @param int $n the number of characters
	 * @param string $endChar the end character. Usually an ellipsis
	 * @return string string having limited characters as specified
	 */
	public static function limitChar(string $str, int $n = 500, string $endChar = '…'): string {
		if (mb_strlen($str) < $n) {
			return $str;
		}

		// a bit complicated, but faster than preg_replace with \s+
		$str = preg_replace('/ {2,}/', ' ', str_replace(["\r", "\n", "\t", "\x0B", "\x0C"], ' ', $str));

		if (mb_strlen($str) <= $n) {
			return $str;
		}

		$out = '';

		foreach (explode(' ', trim($str)) as $val) {
			$out .= $val . ' ';
			if (mb_strlen($out) >= $n) {
				$out = trim($out);
				break;
			}
		}

		return (mb_strlen($out) === mb_strlen($str)) ? $out : $out . $endChar;
	}

	/**
	 * Wraps text at the specified character. Maintains the integrity of words.
	 * Anything placed between {unwrap}{/unwrap} will not be word wrapped, nor
	 * will URLs.
	 *
	 * @param string $str the text string
	 * @param int $charLimit the number of characters to wrap at
	 * @return string
	 */
	public static function wrapWord(string $str, int $charLimit = 76): string {
		// Reduce multiple spaces
		$str = preg_replace('| +|', ' ', $str);

		// Standardize newlines
		if (str_contains($str, "\r")) {
			$str = str_replace(["\r\n", "\r"], "\n", $str);
		}

		// If the current word is surrounded by {unwrap} tags we'll
		// strip the entire chunk and replace it with a marker.
		$unwrap = [];

		if (preg_match_all('|\{unwrap\}(.+?)\{/unwrap\}|s', $str, $matches)) {
			for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
				$unwrap[] = $matches[1][$i];
				$str      = str_replace($matches[0][$i], '{{unwrapped' . $i . '}}', $str);
			}
		}

		// Use PHP's native function to do the initial wordwrap.
		// We set the cut flag to FALSE so that any individual words that are
		// too long get left alone. In the next step we'll deal with them.
		$str = wordwrap($str, $charLimit);

		// Split the string into individual lines of text and cycle through them
		$output = '';

		foreach (explode("\n", $str) as $line) {
			// Is the line within the allowed character count?
			// If so we'll join it to the output and continue
			if (mb_strlen($line) <= $charLimit) {
				$output .= $line . "\n";

				continue;
			}

			$temp = '';

			while (mb_strlen($line) > $charLimit) {
				// If the over-length word is a URL we won't wrap it
				if (preg_match('!\[url.+\]|://|www\.!', $line)) {
					break;
				}
				// Trim the word down
				$temp .= mb_substr($line, 0, $charLimit - 1);
				$line = mb_substr($line, $charLimit - 1);
			}

			// If $temp contains data it means we had to split up an over-length
			// word into smaller chunks so we'll add it back to our current line
			if ($temp !== '') {
				$output .= $temp . "\n" . $line . "\n";
			} else {
				$output .= $line . "\n";
			}
		}

		// Put our markers back
		foreach ($unwrap as $key => $val) {
			$output = str_replace('{{unwrapped' . $key . '}}', $val, $output);
		}

		// remove any trailing newline
		return rtrim($output);
	}

	/**
	 * This function will strip tags from a string, split it at its max_length and ellipsize
	 *
	 * @param string $str string to ellipsize
	 * @param int $maxLen max length of string
	 * @param mixed	$position int (1|0) or float, .5, .2, etc for position to split
	 * @param string $ellipsis ellipsis ; Default '...'
	 * @return string ellipsized string
	 */
	public static function ellipsize(string $str, int $maxLen, mixed $position = 1, string $ellipsis = '…'): string {
		// Strip tags
		$str = trim(strip_tags($str));

		// Is the string long enough to ellipsize?
		if (mb_strlen($str) <= $maxLen) {
			return $str;
		}

		$beg      = mb_substr($str, 0, (int) floor($maxLen * $position));
		$position = ($position > 1) ? 1 : $position;

		if ($position === 1) {
			$end = mb_substr($str, 0, -($maxLen - mb_strlen($beg)));
		} else {
			$end = mb_substr($str, -($maxLen - mb_strlen($beg)));
		}

		return $beg . $ellipsis . $end;
	}

	/**
	 * Create a Random String
	 *
	 * Useful for generating passwords or hashes.
	 *
	 * @param string $type Type of random string. basic, alpha, alnum, numeric, nozero, md5, sha1, and crypto
	 * The type 'basic', 'md5', and 'sha1' are deprecated. They are not cryptographically secure.
	 * @param int $len Number of characters
	 * @return string randomized string
	 * @throws Exception
	 */
	public static function randomString(string $type = 'alnum', int $len = 8): string	{
		switch ($type) {
			case 'alnum':
			case 'nozero':
			case 'alpha':
				$pool = match ($type) {
					'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
					'alnum' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
					default => '123456789',
				};

				return self::_from_random($len, $pool);

			case 'numeric':
				$max  = 10 ** $len - 1;
				$rand = random_int(0, $max);

				return sprintf('%0' . $len . 'd', $rand);

			case 'md5':
				return md5(uniqid((string) mt_rand(), true));

			case 'sha1':
				return sha1(uniqid((string) mt_rand(), true));

			case 'crypto':
				if ($len % 2 !== 0) {
					throw new InvalidArgumentException(
						'You must set an even number to the second parameter when you use `crypto`.'
					);
				}

				return bin2hex(random_bytes($len / 2));
		}

		// 'basic' type treated as default
		return (string) mt_rand();
	}

	/**
	 * The following function was derived from code of Symfony (v6.2.7 - 2023-02-28)
	 * @throws Exception
	 * */
	private static function _from_random(int $length, string $pool): string	{
		if ($length <= 0) {
			throw new InvalidArgumentException(
				sprintf('A strictly positive length is expected, "%d" given.', $length)
			);
		}

		$poolSize = strlen($pool);
		$bits     = (int) ceil(log($poolSize, 2.0));
		if ($bits <= 0 || $bits > 56) {
			throw new InvalidArgumentException(
				'The length of the alphabet must in the [2^1, 2^56] range.'
			);
		}

		$string = '';

		while ($length > 0) {
			$urandomLength = (int) ceil(2 * $length * $bits / 8.0);
			$data          = random_bytes($urandomLength);
			$unpackedData  = 0;
			$unpackedBits  = 0;

			for ($i = 0; $i < $urandomLength && $length > 0; $i++) {
				// Unpack 8 bits
				$unpackedData = ($unpackedData << 8) | ord($data[$i]);
				$unpackedBits += 8;

				// While we have enough bits to select a character from the alphabet, keep
				// consuming the random data
				for (; $unpackedBits >= $bits && $length > 0; $unpackedBits -= $bits) {
					$index = ($unpackedData & ((1 << $bits) - 1));
					$unpackedData >>= $bits;
					// Unfortunately, the alphabet size is not necessarily a power of two.
					// Worst case, it is 2^k + 1, which means we need (k+1) bits and we
					// have around a 50% chance of missing as k gets larger
					if ($index < $poolSize) {
						$string .= $pool[$index];
						$length--;
					}
				}
			}
		}

		return $string;
	}

}