<?php

namespace hati;

/**
 * Number class has very simple methods which eases the number processing such getting
 * percentage, formatting numbers with leading zero, formatting a floating number to a
 * fixed decimal point place etc.
 *
 * Like @link Util this class is under continuous improvement. Methods gets added as
 * we discover over time.
 */

class Number {

    /**
     * A leading zero is added when the integer number is less than 10. Otherwise
     * the input number is returned.
     *
     * @param int $int The integer which needs a leading zero.
     *
     * @return string A leading zero is added and returned as string, if the number
     * is less than 10. Otherwise the original input is returned as string.
     * */
    public static function leadingZero(int $int): string {
        return ($int < 10) ? "0$int" : $int;
    }

    /**
     * The fractional part of any floating number can ba calculated by this method.
     *
     * @param float $number The float value whose fraction is to be returned.
     *
     * @return float The fraction of the float input.
     * */
    public static function getDecimal(float $number): float {
        $whole = (int) $number;
        return $number - $whole;
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

    /**
     * Percentage of any number can be calculated using this method.
     *
     * @param int|float $of The number whose percentage is to be calculated.
     * @param int|float $percent The percentage out of the number value.
     *
     * @return float The calculated percentage of the given number.
     * */
    public static function percentage(int|float $of, int|float $percent): float {
        return ($of / 100) * $percent;
    }

}