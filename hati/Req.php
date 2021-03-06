<?php

namespace hati;

/*
 * Req class stands for Request handling. It can check whether a request is
 * done by GET or POST request. Using this, you can get the browser and OS
 * tag which can be very helpful in validating legitimate users.
 * */

class Req {

    public static function isGET(): bool {
        return $_SERVER['REQUEST_METHOD'] == 'GET';
    }

    public static function isPOST(): bool {
        return $_SERVER['REQUEST_METHOD'] == 'POST';
    }

    public static function method(): string {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * OS name will be returned. This method is not exhausted list of
     * OS name. A few number of popular OS is recognized by this method.
     * See the function body for details. For unknown os it returns Unknown.
     *
     * @return string os name.
     * */
    public static function os(): string {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $os  = "Unknown OS Platform";

        $osArray  = array(
            '/windows nt 10/i'      =>  'Windows 10',
            '/windows nt 6.3/i'     =>  'Windows 8.1',
            '/windows nt 6.2/i'     =>  'Windows 8',
            '/windows nt 6.1/i'     =>  'Windows 7',
            '/windows nt 6.0/i'     =>  'Windows Vista',
            '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
            '/windows nt 5.1/i'     =>  'Windows XP',
            '/windows xp/i'         =>  'Windows XP',
            '/windows nt 5.0/i'     =>  'Windows 2000',
            '/windows me/i'         =>  'Windows ME',
            '/win98/i'              =>  'Windows 98',
            '/win95/i'              =>  'Windows 95',
            '/win16/i'              =>  'Windows 3.11',
            '/macintosh|mac os x/i' =>  'Mac OS X',
            '/mac_powerpc/i'        =>  'Mac OS 9',
            '/linux/i'              =>  'Linux',
            '/ubuntu/i'             =>  'Ubuntu',
            '/iphone/i'             =>  'iPhone',
            '/ipod/i'               =>  'iPod',
            '/ipad/i'               =>  'iPad',
            '/android/i'            =>  'Android',
            '/blackberry/i'         =>  'BlackBerry',
            '/webos/i'              =>  'Mobile'
        );

        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, $userAgent)) $os = $value;
        }
        return $os;
    }

    /**
     * A string of browser information will be returned. This can only
     * list a few number of popular browsers see the function body. For
     * unknown os it returns Unknown.
     *
     * @return string browser name.
     */
    public static function browser(): string {
        $userAgent =  $_SERVER['HTTP_USER_AGENT'];
        $browser        = "Unknown";

        $browser_array = array(
            '/msie/i'      => 'Internet Explorer',
            '/firefox/i'   => 'Firefox',
            '/safari/i'    => 'Safari',
            '/chrome/i'    => 'Chrome',
            '/edge/i'      => 'Edge',
            '/opera/i'     => 'Opera',
            '/netscape/i'  => 'Netscape',
            '/maxthon/i'   => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i'    => 'Mobile Browser'
        );

        foreach ($browser_array as $regex => $value) {
            if (preg_match($regex, $userAgent)) $browser = $value;
        }
        return $browser;
    }

    /**
     * By using this methods, script can acquire the system information
     * such as browser and os info. A string consisting of browser name
     * and os name seperated by comma will be returned.
     *
     * @return string browser,os name will be returned.
     */
    public static function systemTag(): string {
        return self::browser() . '/' . self::os();
    }

}