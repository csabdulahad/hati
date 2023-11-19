<?php

namespace hati;

/**
 * NumFormat class has various methods relating formatting number in many forms
 * such as any number either integer or float can be represented in money-currency
 * format making it easier for money presentation to user. Conversely, it can convert
 * any money presentation in to numeric values.
 * */

class NumFormat {


    // Currency signs for countries
    public const SIGN_BD = '৳';
    public const SIGN_GBP = '£';
    public const SIGN_USD = '$';

    /**
     * Format method can take any input and tries to represent it into either money format
     * or a leading zero, decimal placed number format. The number can be either signed or
     * unsigned. It cleverly, add the currency signs before and in between the negative sign.
     *
     * Since it takes mixed as input type, there are many possibilities that it could fail to
     * to format a number. It tries its best to format a number by forcefully casting the input
     * into float when both normal integer and float filtering fail. It prints out 0-0 on
     * encountering error while formatting the input.
     *
     * @param mixed $input The input which is to converted.
     * @param string $sign Any currency sign.
     * @param bool $leadZero Indicates whether to add zero in front of the number.
     * @param int $place Number of decimal place if the input is a floating number.
     *
     * @return string The formatted number as specified by the arguments.
     * */
    public static function format(mixed $input, string $sign = '', bool $leadZero = false, int $place = 2): string {

        // first see if it is an integer number of not
        $num = Filter::int($input);

        // now see whether it is a floating number
        if ($num === null) $num = Filter::float($input);

        // none of the cases happened. it is neither integer nor floating value
        // let's see if we can apply manual approach here
        if($num === null) $num = (float) $num;

        // make sure we have a number
        if ($num === null) return '0-0';

        // figure out whether it is a round up integer or floating value
        $integer = is_integer($num);

        // learn if it is a positive value
        $negative = $num < 0;

        $num = $negative ? abs($num) : $num;

        // create the symbol
        $symbol = $negative ? '-' : '';
        $symbol .= strlen($sign) != 0 ? $sign : '';

        // add decimal places
        $num = !$integer ? number_format($num, $place) : $num;

        // add leading zero if asked
        $num = $leadZero ? self::leadingZero($num) : $num;

        return "{$symbol}{$num}";
    }

    /**
     * It prints out the input using {@link format()} method as specified by the arguments.
     *
     * @param mixed $input The input which is to converted.
     * @param string $sign Any currency sign.
     * @param bool $leadZero Indicates whether to add zero in front of the number.
     * @param int $place Number of decimal place if the input is a floating number.
     *
     * @return void
     * */
    public static function print(mixed $input, string $sign = '', bool $leadZero = false, int $place = 2): void {
        echo self::format($input, $sign, $leadZero, $place);
    }

    /**
     * This method takes in any money formatted value and converts it into
     * either integer of floating number based on the input value.
     *
     * @param mixed $input The money formatted value.
     * @param string $sign The sign is in the formatted value.
     *
     * @return int|float Returns integer or floating representation based on the input.
     * */
    public static function moneyToNum(mixed $input, string $sign): int | float {
        $input = (string) $input;
        $val = str_replace($sign,'', $input);
        return Filter::int($val) != null ? (int) $val : (float) $val;
    }

    /**
     * A leading zero is added when the integer number is less than 10. Otherwise
     * the input number is returned.
     *
     * @param mixed $int The input which needs a leading zero.
     *
     * @return string A leading zero is added and returned as string, if the number
     * is less than 10. Otherwise the original input is returned as string.
     * */
    public static function leadingZero(mixed $int): string {
        return ($int < 10) ? "0$int" : $int;
    }

    /**
     * Any floating number can be formatted to a fixed size decimal point place for
     * displaying it more friendly on the UI.
     *
     * By default, it fixes the decimal point place to 2.
     *
     * @param int|float $number The floating value whose decimal place is to be fixed.
     * @param int $place The decimal place for the display.
     *
     * @return string Formatted floating value to specified decimal point place.
     * */
    public static function toFixed(int|float $number, int $place = 2): string {
        return number_format($number, $place);
    }

}