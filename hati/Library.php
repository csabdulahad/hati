<?php

/**
 * This library class holds all the inclusion urls for many 3rd party libraries/frameworks.
 * Using this, it can be super easy to include dependencies in html pages without copying
 * or maintaining any code for inclusion.
 * */

namespace hati;

class Library {

    public static function Bootstrap_5_1_3() {
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1">'. PHP_EOL;
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">'. PHP_EOL;
        echo '    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>'. PHP_EOL;
    }

    public static function JQuery_3_6_0() {
        echo '    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>'. PHP_EOL;
    }

    public static function MaterialIcons() {
        echo '    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">'. PHP_EOL;
    }

    public static function JQuery_UI_1_13_0() {
        echo '    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">'. PHP_EOL;
        echo '    <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.js"></script>'. PHP_EOL;
    }

    public static function Angular_1_8_2() {
        echo '    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js"></script>'. PHP_EOL;
    }

    public static function SimPro_1_8() {
        echo '    <script src="https://cdn.jsdelivr.net/gh/csabdulahad/simpro/simpro.js"></script>'. PHP_EOL;
    }

    /**
     * Using this method by default all the required 3rd party libraries and frameworks can be included
     * as project dependencies. Moreover, any framework/library can be unselected from inclusion if
     * needed.
     * */
    public static function selectLib(bool $bootstrap = true, bool $materialIcon = true, bool $jquery = true, bool $angular = true, bool $jqueryUI = false, bool $simpro = false) {
        if ($bootstrap) self::Bootstrap_5_1_3();
        if ($materialIcon) self::MaterialIcons();
        if ($jquery) self::JQuery_3_6_0();
        if ($angular) self::Angular_1_8_2();
        if ($jqueryUI) self::JQuery_UI_1_13_0();
        if ($simpro) self::SimPro_1_8();
    }

}