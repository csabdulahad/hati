<?php

namespace hati;

use hati\trunk\TrunkErr;

/**
 * A powerful helper class for validating query parameters by request. Using
 * this class you can simply leave all the parameter checking to this. It has
 * normal boolean returning methods which can throw error based on the argument
 * settings as it has to flexible for the client code.
 */

class Param {

    /**
     * It beautifies any string into spaced separated words, as they were previously
     * seperated by '_'. For example, a string 'subject_title' will be returned as
     * 'Subject title'.
     *
     * @param string $string the string where words are seperated by '_'.
     * @return string beautified string separated by space and capitalized.
     */
    public static function beautifyString(string $string): string {
        return ucfirst(trim(str_replace('_', ' ', $string)));
    }

    public static function invalidGet(string $params, bool $throwErr = false): string|bool {
        return self::scanInvalid($params, $_GET, $throwErr);
    }

    public static function invalidPost(string $params, bool $throwErr = false): string|bool {
        return self::scanInvalid($params, $_POST, $throwErr);
    }

    /**
     * This method can perform checks on parameter lists seperated by commas. If the param is empty
     * or not set then it can trigger/throw an error or it can return the param as string to indicate
     * that the param has failed the check based on the argument settings.
     *
     * If the parameters pass the check then it replies false, indicating that no param was failed.
     * This method is used by invalidGet and invalidPost.
     *
     * When it returns the param which it was failed for, it returns the param after beautifying it.
     * Meaning that any param name including words separated by '_' will be processed to present it
     * nicely. For example, a param 'subject_title' will be returned as 'Subject title'.
     *
     * @param string $params parameter list seperated by commas.
     * @param array $paramArray which super-global array to scan.
     * @param bool $throwErr indicates whether to throw error or not.
     *
     * @return string|bool returns true of string, the param, otherwise false when the trigger error
     * is not set. It throws error when trigger is set for failed param.
     */
    private static function scanInvalid(string $params, array $paramArray, bool $throwErr): string|bool {
        $list = explode(',', $params);
        if ($list < 1) throw new TrunkErr('No param to scan');

        foreach ($list as $param) {
            $verify = self::verifyParam($paramArray, trim($param));

            if ($throwErr && $verify < 1) {
                $beautified = self::beautifyString($param);
                $message = "$beautified is required.";
                throw new TrunkErr($message);
            }

            if ($verify < 1) return $param;
        }
        return false;
    }


    /**
     * It does the actual checking on the super-global array $_GET and $_POST.
     *
     * @param array $array the super-global array.
     * @param string the key has to be set and has to have a non-empty value.
     *
     * @return int it returns 0 when the key is not set, returns -1 when the
     * value is empty. Otherwise it returns 1.
     * */
    private static function verifyParam(array $array, string $param): int {
        if (!isset($array[$param])) return 0;
        else if (strlen($array[$param]) == 0) return -1;
        return 1;
    }

}