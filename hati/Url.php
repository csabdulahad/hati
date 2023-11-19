<?php

namespace hati;

use hati\trunk\TrunkErr;

/**
 * All processing related with Url processing such scheme, host, query parameters etc.
 * can extracted and manipulated using this simple utility class.
 *
 * Almost all the methods take optional url argument to extract url related information.
 * By default, it gets the current url from the $_SERVER global array.
 *
 * */

class Url {

    /**
     * The page url can be calculated using various $_SERVER global array properties.
     *
     * @return string the full path of the current url.
     * */
    public static function get(): string {
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    // Internal use for getting url. If urls are not specified by argument
    // then it returns the current page url.
    private static function getUrl(string $url): string {
        return empty($url) ? self::get() : $url;
    }

    /**
     * The scheme of the url is returned such http or https. Notice that
     * it doesn't include :// after the scheme. If url is not specified
     * by the argument then current page url is used.
     *
     * @param string $url the url whose scheme is to be extracted.
     * @return string the scheme of the url
     * */
    public static function scheme(string $url = ''): string {
        $url = self::getUrl($url);
        return (string) parse_url($url, PHP_URL_SCHEME);
    }

    /**
     * The path of the url is extracted by this method. Notice that it
     * omits the forward slash when it returns the path.
     *
     * @param string $url the url whose path is to be extracted.
     * @return string The url path without forward slash.
     * */
    public static function path(string $url = ''): string {
        $url = self::getUrl($url);
        return substr(parse_url($url, PHP_URL_PATH), 1);
    }

    /**
     * The last fragment path of the url is extracted by this method.
     * Internally it uses {@link path()} method to get the path then it
     * finds the last fragment of the path and returns.
     *
     * @param string $url the url whose path is to be extracted.
     * @return string The last path fragment of the url.
     * */
    public static function lastPath(string $url = ''): string {
        $url = self::path($url);
        $lastIndex = strripos($url, '/') + 1;
        return substr($url, $lastIndex);
    }

    /**
     * The query parameter part the url can be accessed by specifying the key. Optional
     * default value can be set by the argument and is returned when the key is not
     * present in the url.
     *
     * If the throwErr is set then it throws error on not resolving the parameter in the
     * url. The default value for url is the current page url.
     *
     * @param $key string The parameter key.
     * @param $defVal mixed The value is to be returned on not resolving the parameter.
     * @param $url string The url whose parameter is to be extracted.
     * @param $throwErr bool Indicates whether to throw error on not resolving the parameter.
     * */
    public static function param(string $key, mixed $defVal = null, string $url = '', bool $throwErr = false): mixed {
        $returnValue = $defVal;

        $url = self::getUrl($url);
        $params = explode('&', parse_url($url, PHP_URL_QUERY));
        foreach ($params as $v) {
            $param = explode('=', $v);
            if ($key == $param[0]) {
                $returnValue = $param[1];
                break;
            }
        }

        if ($returnValue == $defVal && $throwErr)
            throw new TrunkErr(sprintf('Key %s was not found in the url: %s', $key, $url));

        return $returnValue;
    }

    /**
     * The fragment part after the # sign in the url can be extracted as string by
     * this method. Unlike other methods, the url is not optional to this method.
     *
     * @param $url string The url whose fragment is be extracted.
     * @param $defVal mixed The default value is to be returned when the fragment is not found.
     * @param $throwErr bool Indicated whether to throw error on not finding the fragment.
     * */
    public static function fragment(string $url, mixed $defVal = null, bool $throwErr = false): mixed {
        $value = parse_url($url, PHP_URL_FRAGMENT);

        if (empty($value) && $throwErr) throw new TrunkErr('No fragment part was found in the url: ' . $url);

        return empty($value) ? $defVal : $value;
    }

}
