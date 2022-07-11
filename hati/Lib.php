<?php

/**
 * This library class holds all the inclusion urls for many 3rd party libraries/frameworks.
 * Using this, it can be super easy to include dependencies in html pages without copying
 * or maintaining any code for inclusion.
 * */

namespace hati;

class Lib {

    public static function bootstrap(): void {
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1">'. PHP_EOL;
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@'. Hati::bootstrap_version() .'/dist/css/bootstrap.min.css" rel="stylesheet">'. PHP_EOL;
        echo '    <script src="https://cdn.jsdelivr.net/npm/bootstrap@'. Hati::bootstrap_version() .'/dist/js/bootstrap.bundle.min.js"></script>'. PHP_EOL;
    }

    public static function jquery(): void {
        echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/'. Hati::jquery_version() .'/jquery.min.js"></script>'. PHP_EOL;
    }

    public static function jquery_ui(): void {
        echo '    <link rel="stylesheet" href="https://code.jquery.com/ui/'. Hati::jquery_ui_version() .'/themes/base/jquery-ui.css">'. PHP_EOL;
        echo '    <script src="https://code.jquery.com/ui/'. Hati::jquery_ui_version() .'/jquery-ui.js"></script>'. PHP_EOL;
    }

    public static function material_icon(): void {
        echo '    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">'. PHP_EOL;
    }

    public static function angular_js(): void {
        echo '    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/'. Hati::angular_js_version() .'/angular.min.js"></script>'. PHP_EOL;
    }

    public static function jst(): void {
        echo '    <link href="https://cdn.jsdelivr.net/gh/csabdulahad/jst@'. Hati::jst_version() .'/dist/jst-min.css" rel="stylesheet">'. PHP_EOL;
        echo '    <script src="https://cdn.jsdelivr.net/gh/csabdulahad/jst@'. Hati::jst_version() .'/dist/jst-min.js"></script>'. PHP_EOL;
    }

    /**
     * Using this method by default all the required 3rd party libraries and frameworks can be included
     * as project dependencies. Moreover, any framework/library can be unselected from inclusion if
     * needed.
     * */
    public static function get(bool $bootstrap = true, bool $mat = true, bool $jquery = true, bool $angularJS = true, bool $jqueryUI = true, bool $jst = true): void {
        if ($jquery) self::jquery();
        if ($angularJS) self::angular_js();
        if ($jst) self::jst();
        if ($jqueryUI) self::jquery_ui();
        if ($bootstrap) self::bootstrap();
        if ($mat) self::material_icon();
    }

}
