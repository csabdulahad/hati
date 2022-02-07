<?php

namespace hati;

class Log {

    public static function printArray(array $arr) {
        echo '<pre><code>';print_r($arr);echo '</code></pre>';
    }

    public static function printStr(string $str) {
        echo "<pre>$str</pre>";
    }

}