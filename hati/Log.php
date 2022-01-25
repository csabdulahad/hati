<?php

namespace hati;

class Log {

    public static function printArray(array $arr) {
        echo '<br><pre><code>';
        print_r($arr);
        echo '</code></pre>';
    }

    public static function printStr(string $str) {
        echo "<br><pre><code>$str</code></pre>";
    }

}