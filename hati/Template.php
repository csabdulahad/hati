<?php

namespace hati;

use hati\trunk\TrunkErr;

/**
 * Templating in project sometimes are necessary to have. Hati provides a wonderful
 * support for templating with Template library class. It is simple in nature yet very
 * powerful in capabilities.
 *
 * Any template file must have an extension of '.tlp.php' which clears up many confusion
 * and makes it easier to mark that the file is meant to go through template engine.
 *
 * It holds all the passed parameters to the template in a static manner. The template file
 * can access them in a safe way using shorted function names namely {@link Template::g()}
 * and {@link Template::w()}
 * */

class Template {

    // parameters buffer
    private static array $params = [];

    // no instantiation is allowed
    private function __construct() {

    }

    /**
     * Any php file can be used as template file. It is advised to keep the file extension
	 * as '.tlp.php' to make it distinguishable from other php scripts file. Rendered output
	 * can be returned or written in the buffer by specifying print argument.
	 *
	 * Use helper methods {@link Hati::root()} & {@link Hati::projectRoot()} to explicitly
	 * get the path to the template file.
     *
     * @param string $filePath Path to the template file
     * @param bool $print Specify whether to print out the rendered template or return
     * @param bool $throwErr Indicates whether to throw error
     *
     * @return ?string Either returns or print out the rendered template file based on argument value.
     * */
    public static function render(string $filePath, array $params = [], bool $print = false, bool $throwErr = false): ?string {
        if (!file_exists($filePath)) {
            if ($throwErr) throw new TrunkErr("Couldn't locate the template file at: $filePath");
            return null;
        }

        self::$params = $params;

        // start a local buffer
        ob_start();

        include($filePath);

        // flush the buffer and clean the resources
        $rendered = ob_get_clean();

        // clean the parameter buffer
        array_splice(self::$params, 0);

        if ($print) echo $rendered;

        return !$print ? $rendered : null;
    }

    /**
     * The template file which is being rendered can access any passed argument using
     * key value and the value will be printed. This uses {@link Template::g()} to get
     * the parameter value in a safe manner.
     *
     * @param string $key The key for the parameter.
     * @param mixed $defVal The value to be returned in case of undefined key.
     * */
    public static function w(string $key, mixed $defVal = null): void {
        echo self::g($key, $defVal);
    }

    /**
     * Any passed parameter value can be accessed using key. In case of non-existence
     * of the parameter value, a default value is returned as specified in the 2nd
     * argument.
     *
     * @param string $key The key for the parameter.
     * @param mixed $defVal The value to be returned in case of undefined key.
     *
     * @return mixed Returns the data for the key in the parameter buffer.
     * */
    public static function g(string $key, mixed $defVal = null): mixed {
        return self::$params[$key] ?? $defVal;
    }

}