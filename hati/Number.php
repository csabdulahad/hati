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
     * @return int|float The calculated percentage of the given number.
     * */
    public static function percentage(int|float $of, int|float $percent): int|float {
        if ($of == 0) return 0;
        return ($of / 100) * $percent;
    }

    /**
     * Percent ratio to a number can be calculated by this method.
     *
     * @param int|float $amount The number which holds the share of another number.
     * @param int|float $outOf The number of which the amount is of.
     *
     * @return int|float The calculated percentage for the share amount it is of the number.
     * */
    public static function percentShare(int|float $amount, int|float $outOf): int|float {
        if ($outOf == 0) return 0;
        $unit = $outOf / 100;
        return $amount / $unit;
    }

    /**
     * Either an array of numbers or normal comma separated numbers can be argument
     * input to this method for calculating average of those numbers.
     *
     * @param mixed ...$number either an array of numbers or normal var-args of numbers.
     *
     * @return int|float The average of the numbers.
     * */
    public static function avg(...$number): int|float {
        $total = 0;
        $arg = func_get_args();
        if (is_array($arg[0])) $number = $arg[0];
        $len = count($number);
        foreach ($number as $num) $total += $num;
        return $total / $len;
    }

    /**
     * Any random number can be generated using this method. By default, the end range
     * is inclusive.
     *
     * @param int $min The minimum number.
     * @param int $max The maximum number.
     * @param bool $inclusive Indicate whether the end range inclusive.
     *
     * @return int Randomly generated integer.
     * */
    public static function rand(int $min, int $max, bool $inclusive = true): int {
        if (!$inclusive) $max -= 1;
        return rand($min, $max);
    }

}