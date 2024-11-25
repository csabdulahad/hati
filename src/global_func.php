<?php

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
		if (Util::cli()) var_dump($var);
		else {
			echo "<pre>";
			var_dump($var);
			echo "</pre>";
		}

		if ($exit) exit;
	} else if (is_array($var)) {
		$var = print_r($var, true);
	} else if (is_bool($var)) {
		$var = $var ? 'true' : 'false';
	} else if (is_null($var)) {
		$var = 'null';
	}

	if (Util::cli()) echo $var;
	else echo "<pre>$var</pre>";

	if ($exit) exit;
}

/**
 * Prints out variable of mixed type in nice formatted way. If object is passed as
 * value then it prints the var_dump of the object. By default, it adds one line
 * break at the end of the output. This works seamlessly in both CLI & HTMl output.
 *
 * @param mixed $value The value needs to be printed
 * @param int $numOfBreak The number break to be added at the end. 1 is default
 * @param bool $pretty Controls whether output keeps the whitespace intact
 **/
function println(mixed $value, int $numOfBreak = 1, bool $pretty = true): void {

	// Remove one extra break as pre tag adds one already
	$b = Util::cli() ? "\n" : "<br>";
	if (!Util::cli() && $pretty) {
		$numOfBreak -= 1;
	}

	// Remove one extra break if it of type array/obj for CLI output
	if ((is_array($value) || is_object($value) && Util::cli()))
		$numOfBreak -= 1;

	// Can't have zero number of break for str_repeat function
	if ($numOfBreak < 0) $numOfBreak = 0;
	$break = str_repeat($b, $numOfBreak);

	if (is_array($value) || is_object($value)) {
		$value = print_r($value, true);
	} elseif (is_bool($value)) {
		$value = $value ? 'true' : 'false';
	} else if (is_null($value)) {
		$value = 'null';
	}

	if ($pretty && !Util::cli()) echo "<pre>";
	echo $value;
	if ($pretty && !Util::cli()) echo "</pre>";
	echo $break;
}

/**
 * Retrieves and removes a value from an associative array using a specified key.
 *
 * @param string $key The key whose associated value is to be retrieved and removed from the array.
 * @param array &$var The associative array from which the value is to be retrieved and removed.
 *
 * @return mixed The value associated with the specified key in the associative array.
 */
function pop(string $key, &$var): mixed {
	$value = $var[$key];
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