<?php

namespace hati\util;

/**
 * A class containing helper functions to manipulate arrays in PHP.
 *
 * @since 5.0.0
 * */

abstract class Arr {

	/**
	 * Converts an array into comma separated string values.
	 *
	 * @param array $arr the array whose values to be converted
	 * @param bool $addBrackets If set to ture, the string will be wrapped by [] brackets
	 * @return string comma separated string of array values
	 * */
	public static function strList(array $arr, bool $addBrackets = false): string {
		$str = '';
		foreach ($arr as $a) $str .= "$a, ";
		$str = rtrim($str, ', ');

		if (!$addBrackets) return $str;
		return "[$str]";
	}

	/**
	 * Recursively flattens a multidimensional array into a one-dimensional array. This function
	 * takes a multidimensional array as input and returns a one-dimensional array by recursively
	 * flattening nested arrays.
	 *
	 * @param array $array The multidimensional array to be flattened.
	 * @return array The one-dimensional array resulting from flattening the input array.
	 */
	public static function flatten(array $array): array {
		$result = [];

		foreach ($array as $element) {
			if (is_array($element)) {
				// Recursively flatten the nested array
				$result = array_merge($result, self::flatten($element));
			} else {
				// Add the non-array element to the result
				$result[] = $element;
			}
		}

		return $result;
	}

	/**
	 * A utility method which creates an array for variable arguments or varargs for short.
	 * This function could be helpful for situation where a function takes in arguments like
	 * string|array as data type.<br>
	 * It also takes care of multidimensional arrays found in the varargs.
	 *
	 * @param mixed $args the variable arguments
	 * @return array one dimensional array containing all the items in the variable arguments
	 * */
	public static function varargsAsArray(mixed $args): array {
		$option = [];

		foreach ($args as $col) {
			if (is_array($col)) {
				$option = array_merge($option, self::flatten($col));
				continue;
			}
			$option[] = $col;
		}

		return $option;
	}

	/**
	 * Lets you determine whether an array index is set and whether it has a value.
	 * If the element is empty it returns null (or whatever you specify as the default value.)
	 *
	 * @param string $key The key to be found in the array
	 * @param array $array The array
	 * @param mixed $default The default value to be returned if not found
	 * @return mixed depends on what the array contains
	 */
	public static function element(string $key, array $array, mixed $default = null): mixed {
		return array_key_exists($key, $array) ? $array[$key] : $default;
	}

	/**
	 * Returns only the array items specified. Will return a default value if
	 * it is not set.
	 *
	 * @param array|string $items The key(s) whose values to be returned
	 * @param array $array The array
	 * @param mixed $default The default to be returned when key's value wasn't found
	 * @return array depends on what the array contains
	 */
	public static function elements(array|string $items, array $array, mixed $default = null): array {
		$return = [];

		is_array($items) OR $items = array($items);

		foreach ($items as $item) {
			$return[$item] = array_key_exists($item, $array) ? $array[$item] : $default;
		}

		return $return;
	}

	/**
	 * Takes an array as input and returns a random element
	 *
	 * @param array $array The array
	 * @return mixed depends on what the array contains
	 */
	public static function randElement(array $array): mixed {
		return is_array($array) ? $array[array_rand($array)] : $array;
	}

	/**
	 * Searches an array through dot syntax. Supports
	 * wildcard searches, like foo.*.bar
	 *
	 * @param string $index The index to get to the value in the array
	 * @param array $array The array
	 * @return mixed the value found by the index; otherwise null is returned
	 */
	public static function dotSearch(string $index, array $array): mixed {
		// See https://regex101.com/r/44Ipql/1
		$segments = preg_split(
			'/(?<!\\\\)\./',
			rtrim($index, '* '),
			0,
			PREG_SPLIT_NO_EMPTY
		);

		$segments = array_map(static fn ($key) => str_replace('\.', '.', $key), $segments);

		return self::_array_search_dot($segments, $array);
	}

	/**
	 * Sorts a multidimensional array by its elements values. The array
	 * columns to be used for sorting are passed as an associative
	 * array of key names and sorting flags.
	 *
	 * Both arrays of objects and arrays of array can be sorted.
	 *
	 * Example:
	 * <code>
	 *     array_sort_by_multiple_keys($players, [
	 *         'team.hierarchy' => SORT_ASC,
	 *         'position'       => SORT_ASC,
	 *         'name'           => SORT_STRING,
	 *     ]);
	 * </code>
	 * The '.' dot operator in the column name indicates a deeper array or
	 * object level. In principle, any number of sub-levels could be used,
	 * as long as the level and column exist in every array element.
	 *
	 * For information on multi-level array sorting, refer to Example #3 here:
	 * https://www.php.net/manual/de/function.array-multisort.php
	 *
	 * @param array $array  the reference of the array to be sorted
	 * @param array $sortColumns an associative array of columns to sort
	 * after and their sorting flags
	 * @return bool true on success or false on failure
	 */
	public static function sortByMultiKeys(array &$array, array $sortColumns): bool {
		// Check if there really are columns to sort after
		if (empty($sortColumns) || empty($array)) {
			return false;
		}

		// Group sorting indexes and data
		$tempArray = [];

		foreach ($sortColumns as $key => $sortFlag) {
			// Get sorting values
			$carry = $array;

			// The '.' operator separates nested elements
			foreach (explode('.', $key) as $keySegment) {
				// Loop elements if they are objects
				if (is_object(reset($carry))) {
					// Extract the object attribute
					foreach ($carry as $index => $object) {
						$carry[$index] = $object->{$keySegment};
					}

					continue;
				}

				// Extract the target column if elements are arrays
				$carry = array_column($carry, $keySegment);
			}

			// Store the collected sorting parameters
			$tempArray[] = $carry;
			$tempArray[] = $sortFlag;
		}

		// Append the array as reference
		$tempArray[] = &$array;

		// Pass sorting arrays and flags as an argument list.
		return array_multisort(...$tempArray);
	}

	/**
	 * Flatten a multidimensional array using dots as separators.
	 *
	 * @param iterable $array The multi-dimensional array
	 * @param string   $id    Something to initially prepend to the flattened keys
	 *
	 * @return array The flattened array
	 */
	public static function flattenWithDots(iterable $array, string $id = ''): array {
		$flattened = [];

		foreach ($array as $key => $value) {
			$newKey = $id . $key;

			if (is_array($value) && $value !== []) {
				$flattened = array_merge($flattened, self::flattenWithDots($value, $newKey . '.'));
			} else {
				$flattened[$newKey] = $value;
			}
		}

		return $flattened;
	}

	/**
	 * Groups all rows by their index values. Result's depth equals number of indexes
	 *
	 * @param array $array        Data array (i.e. from query result)
	 * @param array $indexes      Indexes to group by. Dot syntax used. Returns $array if empty
	 * @param bool  $includeEmpty If true, null and '' are also added as valid keys to group
	 *
	 * @return array Result array where rows are grouped together by indexes values.
	 */
	public static function groupBy(array $array, array $indexes, bool $includeEmpty = false): array {
		if ($indexes === []) {
			return $array;
		}

		$result = [];

		foreach ($array as $row) {
			$result = self::_array_attach_indexed_value($result, $row, $indexes, $includeEmpty);
		}

		return $result;
	}

	/**
	 * Used by `array_group_by` to recursively attach $row to the $indexes path of values found by
	 * `dot_array_search`
	 *
	 * @internal This should not be used on its own
	 */
	private static function _array_attach_indexed_value(array $result, array $row, array $indexes, bool $includeEmpty): array {
		if (($index = array_shift($indexes)) === null) {
			$result[] = $row;

			return $result;
		}

		$value = self::dotSearch($index, $row);

		if (! is_scalar($value)) {
			$value = '';
		}

		if (is_bool($value)) {
			$value = (int) $value;
		}

		if (! $includeEmpty && $value === '') {
			return $result;
		}

		if (! array_key_exists($value, $result)) {
			$result[$value] = [];
		}

		$result[$value] = self::_array_attach_indexed_value($result[$value], $row, $indexes, $includeEmpty);

		return $result;
	}

	/**
	 * Used by `dot_array_search` to recursively search the
	 * array with wildcards.
	 *
	 * @param array $indexes
	 * @param array $array
	 * @return mixed
	 * @internal This should not be used on its own.
	 */
	private static function _array_search_dot(array $indexes, array $array): mixed {
		// If index is empty, returns null.
		if ($indexes === []) {
			return null;
		}

		// Grab the current index
		$currentIndex = array_shift($indexes);

		if (! isset($array[$currentIndex]) && $currentIndex !== '*') {
			return null;
		}

		// Handle Wildcard (*)
		if ($currentIndex === '*') {
			$answer = [];

			foreach ($array as $value) {
				if (! is_array($value)) {
					return null;
				}

				$answer[] = self::_array_search_dot($indexes, $value);
			}

			$answer = array_filter($answer, static fn ($value) => $value !== null);

			if ($answer !== []) {
				if (count($answer) === 1) {
					// If array only has one element, we return that element for BC.
					return current($answer);
				}

				return $answer;
			}

			return null;
		}

		// If this is the last index, make sure to return it now,
		// and not try to recurse through things.
		if (empty($indexes)) {
			return $array[$currentIndex];
		}

		// Do we need to recursively search this value?
		if (is_array($array[$currentIndex]) && $array[$currentIndex] !== []) {
			return self::_array_search_dot($indexes, $array[$currentIndex]);
		}

		// Otherwise, not found.
		return null;
	}

}