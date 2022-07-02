<?php

namespace hati;

class Log {

    public static function arr(array $arr) {
        echo '<pre><code>';print_r($arr);echo '</code></pre>';
    }

    public static function str(string $str) {
        echo "<pre>$str</pre>";
    }

}