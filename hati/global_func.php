<?php

use hati\Util;

/**
 * The functions, this file contains are global. You can access them from anywhere within
 * the project.
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

    // Remove one extra break if it of type array/obj for CLI outpu
	if ((is_array($value) || is_object($value) && Util::cli()))
		$numOfBreak -= 1;

    // Can't have zero number of break for str_repeat function
    if ($numOfBreak < 0) $numOfBreak = 0;
    $break = str_repeat($b, $numOfBreak);

    if (is_array($value) || is_object($value)) {
        $value = print_r($value, true);
    } elseif (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    }

    if ($pretty && !Util::cli()) echo "<pre>";
    echo $value;
    if ($pretty && !Util::cli()) echo "</pre>";
    echo $break;
}