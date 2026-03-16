<?php

/** @noinspection PhpUnused */

use hati\util\Util;

/**
 * The functions, this file contains are global. You can access them from anywhere within
 * the project.
 *
 * @since 5.0.0
 * */


/**
 * Var dump any type of variable for debugging.
 *
 * @param mixed $var Any variable
 * @param bool $exit When set to true it exits the script after dumping the variable
 **/
function vd(mixed $var, bool $exit = true): void {
	if (is_object($var)) {
		if (Util::isCLI()) var_dump($var);
		else {
			echo "<pre>";
			var_dump($var);
			echo "</pre>";
		}

		if ($exit) exit;
	} else if (is_array($var)) {
		$var = json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	} else if (is_bool($var)) {
		$var = $var ? 'true' : 'false';
	} else if (is_null($var)) {
		$var = 'null';
	}

	if (Util::isCLI()) echo $var;
	else echo "<pre>$var</pre>";
	
	$trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
	$file   = $trace['file'] ?? '';
	$line   = $trace['line'] ?? '';
	$line   = empty($line) ? '' : ":$line";
	
	$calledAt = "$file$line";
	$calledAt = Util::isCLI() ? "\n$calledAt" : $calledAt;
	echo $calledAt;

	if ($exit) exit;
}

/**
 * Retrieves and removes a value from an associative array using a specified key.
 *
 * @param string $key The key whose associated value is to be retrieved and removed from the array.
 * @param array &$var The associative array from which the value is to be retrieved and removed.
 * @param mixed $default If the key doesn't exist then returns default value.
 *
 * @return mixed The value associated with the specified key in the associative array.
 */
function pop(string $key, array &$var, mixed $default = null): mixed {
	$value = $var[$key] ?? $default;
	unset($var[$key]);
	return $value;
}
	
/**
 * Conditionally mutates a variable based on a specified condition and value.
 *
 * This function conditionally mutates a variable based on the given condition and value.
 * If the condition is true, it assigns the specified value to the variable.
 * If the value is callable, it executes the callable and assigns its result to the variable.
 *
 * @param bool $condition The condition determining whether to mutate the variable.
 * @param mixed &$var The variable to be mutated.
 * @param mixed $val The value to assign to the variable if the condition is true, or a callable function that returns the value.
 *
 * @return void
 */
function mutate(bool $condition, &$var, mixed $val): void {
	if (!$condition) return;
	
	if (!is_callable($val)) {
		$var = $val;
		return;
	}
	
	$var = $val();
}

/**
 * Mutates a variable if it is null.
 * If the variable is null, it assigns the specified value to it.
 *
 * @param mixed &$var The variable to be mutated.
 * @param mixed $val The value to assign to the variable if it is null. It can be a callback
 * function which should return the value to be set if mutating condition is met.
 *
 * @return void
 */
function mutateIfNull(&$var, mixed $val): void {
	mutate(is_null($var), $var, $val);
}

/**
 * Mutates a variable if it is not null.
 * If the variable is not null, it assigns the specified value to it.
 *
 * @param mixed &$var The variable to be mutated.
 * @param mixed $val The value to assign to the variable if it is null. It can be a callback
 * function which should return the value to be set if mutating condition is met.
 *
 * @return void
 */
function mutateIfNotNull(&$var, mixed $val): void {
	mutate(!is_null($var), $var, $val);
}

/**
 * Mutates a variable if it is empty.
 * If the variable is empty, it assigns the specified value to it.
 *
 * @param mixed &$var The variable to be mutated.
 * @param mixed $val The value to assign to the variable if it is null. It can be a callback
 * function which should return the value to be set if mutating condition is met.
 *
 * @return void
 */
function mutateIfEmpty(&$var, mixed $val): void {
	mutate(empty($var), $var, $val);
}

/**
 * Mutates a variable if it is not empty.
 * If the variable is not empty, it assigns the specified value to it.
 *
 * @param mixed &$var The variable to be mutated.
 * @param mixed $val The value to assign to the variable if it is null. It can be a callback
 * function which should return the value to be set if mutating condition is met.
 *
 * @return void
 */
function mutateIfNotEmpty(&$var, mixed $val): void {
	mutate(!empty($var), $var, $val);
}

/**
 * Renames an existing key in an array (in-place) while preserving its value.
 *
 * - If $oldKey does not exist, the array is unchanged.
 * - If $oldKey === $newKey, the array is unchanged.
 * - If $newKey already exists, it will be overwritten.
 *
 * @param string|int $oldKey Key to rename.
 * @param string|int $newKey New key name.
 * @param array      $array  Target array (modified by reference).
 */
function renameKey(string|int $oldKey, string|int $newKey, array &$array): void
{
	if (!array_key_exists($oldKey, $array) || $oldKey === $newKey) return;
	$array[$newKey] = pop($oldKey, $array);
}