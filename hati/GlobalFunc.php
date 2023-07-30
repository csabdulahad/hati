<?php

use hati\Util;

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