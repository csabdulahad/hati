<?php

namespace hati;

use hati\trunk\TrunkErr;

/**
 * Filter class is very helpful and handy in situations where user inputs need to be
 * filtered and sanitized. It has many methods which are a different capabilities.
 * All the methods are safe as they use both validation and sanitization where possible.
 *
 * They all returns the original argument input value as passed into filter methods upon
 * successful validation and optionally after validation. If the input fails the validation
 * check then it returns either null value or trigger error based on how the methods is
 * executed set by the arguments.
 *
 * All the methods return null value of failure where the trigger error is turned off. It is
 * so that program can easily detect whether any error happened and giving a way to set a
 * default value upon failure or unset or unwanted values from the user via input. While
 * checking for null value as the outcome of any of the Filter class methods, THREE EQUAL SIGN
 * === should be used to strictly match and avoid language construction confusions as it is
 * seen in JS, PHP like languages.
 *
 * Specially there is one method @link Filter::sanitize which can directly work with any regular
 * expression or some predefined pattern constants by Filter class.
 *
 **/

class Filter {

    /**
     * Predefined regular expression pattern for filtering input in various formats.
     * This list has a useful pattern which can be used in general for any project.
     * However, any required pattern can be passed as an argument to @link sanitize
     * method.
     *
     * In the naming of these constants, they have meaning like regular expression.
     * A    = Alphabets(including capital & small letters)
     * N    = Numbers
     * AN   = Alphabets & Numbers
     * S    = Space
     * C    = Comma
     * D    = Dot
     *
     * When you any of these pattern they will remove any other characters except the
     * mentioned characters in the pattern names.
     * */
    public const SAN_A = '#[^a-zA-Z]#';
    public const SAN_N = '#[^0-9]#';
    public const SAN_AN = '#[^a-zA-Z0-9]#';
    public const SAN_AS = '#[^a-zA-Z\s]#';
    public const SAN_AC = '#[^a-zA-Z,]#';
    public const SAN_AD = '#[^a-zA-Z.]#';
    public const SAN_ANS = '#[^a-zA-Z0-9\s]#';
    public const SAN_ASC = '#[^a-zA-Z\s,]#';
    public const SAN_AND = '#[^a-zA-Z0-9.]#';
    public const SAN_ANSC = '#[^a-zA-Z0-9\s,]#';
    public const SAN_ANSD = '#[^a-zA-Z0-9\s.]#';
    public const SAN_ANSCD = '#[^a-zA-Z0-9\s,.]#';

    /**
     * A date input can be checked for ISO formatted date. This method first try to match
     * ISO date format in the input. Upon match it removes the matched ISO date with
     * empty space. Then it counts the remaining characters. It it was a valid formatted
     * ISO date then should have no remaining characters. Thus confirming a valid ISO
     * formatted date input.
     *
     * @param mixed $input the string to be checked for ISO date format.
     * @param bool $throwErr if it set then it throw HatiError on failure.
     *
     * @return ?string returns the input if the input is a valid ISO formatted date. on failure
     * it returns null if the trigger error is not set.
     * */
    public static function ISODateFormat(mixed $input, bool $throwErr = false): ?string {
        // check whether the input is null & empty
        if ($input === null) {
            if ($throwErr) throw new TrunkErr('Date is null');
            return null;
        }

        // check whether we have empty input
        if (strlen($input) < 1) {
            if ($throwErr) throw new TrunkErr('Date is empty');
            return null;
        }

        // try to remove the YYYY-MM-DD match from the input if there is any
        $date = self::sanitize($input, '#(\d{4}-\d{2}-\d{2})#');

        // it it was a valid ISO formatted date then it should have no character
        // remaining after the filter.
        $pass = strlen($date) == 0;

        if (!$pass && $throwErr) throw new TrunkErr('Invalid date is given. Date must be in YYYY-MM-DD format.');
        return !$pass ? null : $input;
    }

    /**
     * This method can work with any @link Filter defined sanitizing patterns of client code defined
     * pattern as method argument. By default, upon matching the pattern it replaces with an empty
     * string.
     *
     * @param string $value the string, the subject of the pattern matching.
     * @param string $pattern any @link Filter defined or client code defined regular expression.
     * @param string $replacement to replace the matched patter.
     *
     * @return string it returns the replaced input as string upon matching the pattern.
     * */
    public static function sanitize(string $value, string $pattern, string $replacement = ''): string {
        return preg_replace($pattern, $replacement, $value);
    }

    /**
     * This method checks email. It first checks whether the email is valid or not.
     * If it is valid then it sanitize the email and returns.
     *
     * On invalid email argument, it behaves differently. If trigger error is set then
     * it throws HatiError. If not then it returns null value to indicate that the email
     * was failed to pass the check.
     *
     * @param mixed $input string containing email to be checked
     * @param bool $throwErr if it is set then it throws HatiError on failure
     * otherwise it will return null value instead.
     *
     * @return ?string it returns null on failure if trigger error is not set otherwise
     * it will return null. on successful pass it returns the sanitized email.
     * */
    public static function email(mixed $input, bool $throwErr = false): ?string {
        // check whether the input is null & empty
        if ($input === null) {
            if ($throwErr) throw new TrunkErr('Email is null');
            return null;
        }

        // check whether we have empty input
        if (strlen($input) < 1) {
            if ($throwErr) throw new TrunkErr('Email is empty');
            return null;
        }

        $isEmail = filter_var($input, FILTER_VALIDATE_EMAIL);
        if ($throwErr && !$isEmail) throw new TrunkErr('The email is not valid');
        if (!$isEmail) return null;

        return  filter_var($input, FILTER_SANITIZE_EMAIL);
    }

    /**
     * This method checks for integer. It first checks whether the input is a valid
     * integer or not. If it is valid then it sanitize the number and returns.
     *
     * On invalid argument, it behaves differently. If trigger error is set then it
     * throws HatiError. If not then it returns null value to indicate that the number
     * was failed to pass the check.
     *
     * @param mixed $input string or integer to be checked
     * @param bool $throwErr if it is set then it throws HatiError on failure
     * otherwise it will return null value instead.
     *
     * @return ?int it returns null on failure if trigger error is not set otherwise
     * it will return null. on successful pass it returns the sanitized integer.
     * */
    public static function int(mixed $input, bool $throwErr = false): ?int {
        // check whether the input is null & empty
        if ($input === null) {
            if ($throwErr) throw new TrunkErr('Input number is null');
            return null;
        }

        // check whether we have empty input
        if (strlen($input) < 1) {
            if ($throwErr) throw new TrunkErr('Input number is empty');
            return null;
        }

        // let's see if we have got any illegal character in the input by removing a
        // valid either signed or unsigned value from the the input then assess the
        // length of it. For a valid integer of either signed or unsigned it should
        // have a length of zero after filtering.
        $filter = strlen(preg_replace('#-?\d+#', '', $input));
        if ($filter > 0) {
            if ($throwErr) throw new TrunkErr('Number has illegal characters.');
            return null;
        }

        // capture absolute zero value as an integer
        $input = (int) $input;
        if ($input === 0) return $input;

        $isInt = filter_var($input, FILTER_VALIDATE_INT);
        if (!$isInt && $throwErr) throw new TrunkErr($input . ' is not an integer number.');
        if (!$isInt) return null;

        return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * This method checks for floating number. It the number is a floating number then
     * it just returns it.
     *
     * On invalid argument, it behaves differently. If trigger error is set then it
     * throws HatiError. If not then it returns null value to indicate that the number
     * was failed to pass the floating check.
     *
     * On any input given, it checks whether it has any dot in the number or the string.
     * If not found then it adds one with 0 at the end after the decimal point to make
     * it a valid decimal point number for the check logic to work.
     *
     * @param mixed $input string or number to be checked
     * @param bool $throwErr if it is set then it throws HatiError on failure
     * otherwise it will return null value instead.
     *
     * @return ?float it returns null on failure if trigger error is not set otherwise
     * it will return null. on successful pass it returns the number.
     * */
    public static function float(mixed $input, bool $throwErr = false): ?float {
        // check whether the input is null & empty
        if ($input === null) {
            if ($throwErr) throw new TrunkErr('Float number input is null');
            return null;
        }

        // check whether we have empty input
        if (strlen($input) < 1) {
            if ($throwErr) throw new TrunkErr('Float number input is empty');
            return null;
        }

        // add the floating point place if it has not
        if (!preg_match('#\.#', $input)) $input .= '.0';

        // let's see if we have got any illegal character in the input by removing a
        // valid either signed or unsigned value from the the input then assess the
        // length of it. For a valid floating of either signed or unsigned it should
        // have a length of zero after filtering.
        $filter = strlen(preg_replace('#-?\d+\.\d+#', '', $input));
        if ($filter > 0) {
            if ($throwErr) throw new TrunkErr('Float number has illegal characters.');
            return null;
        }

        $isFloat = filter_var($input, FILTER_VALIDATE_FLOAT);
        if (!$isFloat && $throwErr) throw new TrunkErr($input . ' is not a floating number.');
        if (!$isFloat) return null;

        return $input;
    }

    /**
     * Any string can be sanitized by this method. This method sanitize special characters
     * from the string html tags brackets, double quotes, ampersand will be escaped with
     * html entities values.
     *
     * Additionally it checks for empty string value. If an empty string is passed-in as
     * argument then it throws HatiError based on the setting.
     *
     * @param mixed $input string to be escaped
     * @param bool $throwErr if it is set then it throws HatiError on empty string
     * value. if it is not set then it returns null on empty string value.
     *
     * @return ?string returns the escaped string. null on failure if the trigger error
     * is set false, otherwise it will trow exception.
     * */
    public static function string(mixed $input, bool $throwErr = false): ?string {
        // check if we have empty
        if ($input === null) {
            if ($throwErr) throw new TrunkErr('String is null');
            return null;
        }

        $empty = empty($input);
        if ($empty && $throwErr) throw new TrunkErr('The string is empty');
        if ($empty) return null;
        return filter_var($input, FILTER_SANITIZE_SPECIAL_CHARS);
    }

    /**
     * This method validates a string as url first then on successful validation it returns
     * sanitized url. If trigger is no and the string is invalid then it throws error. If
     * the trigger is set off then it returns null indicating that it didn't pass the check.
     *
     * @param mixed $input the url string for checking.
     * @param bool $throwErr whether to throw error upon failure of validation.
     *
     * @return ?string returns null on failure otherwise it returns the sanitized url string.
     */
    public static function url(mixed $input, bool $throwErr = false): ?string {
        // check whether the input is null & empty
        if ($input === null) {
            if ($throwErr) throw new TrunkErr('Url is null');
            return null;
        }

        // check whether we have empty input
        if (strlen($input) < 1) {
            if ($throwErr) throw new TrunkErr('Url is empty');
            return null;
        }

        $isUrl = filter_var($input, FILTER_VALIDATE_URL);
        if (!$isUrl && $throwErr)
            throw new TrunkErr('The input has to be a valid url string');
        if (!$isUrl) return null;

        return filter_var($input, FILTER_SANITIZE_URL);
    }

    /**
     * This method uses the FILTER_VALIDATE_BOOLEAN as filter which can support many possible
     * values of boolean type in php such as On, 1, yes, true, false, no, 0, off. If the input
     * is not any of the above then it throws error if the trigger error is set. otherwise it
     * returns null value.
     *
     * In situation for the outcome of this filter where the returned value is compared against
     * null value should be done THREE EQUAL SIGN(===) comparison to avoid logical confusion that
     * is done by programming languages like JS, PHP etc.
     *
     * @param mixed $input a string possible holding boolean value.
     * @param bool $throwErr whether to throw error on failure.
     *
     * @return ?bool returns null on invalid boolean value, otherwise returns the original value.
     * */
    public static function bool(mixed $input, bool $throwErr = false): ?bool {
        // check whether the input is null & empty
        if ($input === null) {
            if ($throwErr) throw new TrunkErr('Boolean input is null');
            return null;
        }

        // check whether we have empty input
        if (gettype($input) != 'boolean' && strlen($input) < 1) {
            if ($throwErr) throw new TrunkErr('Boolean input is empty');
            return null;
        }

        $isBool = filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isBool === null && $throwErr) throw new TrunkErr('The input has to be a valid boolean value.');
        return $isBool;
    }

    /**
     * Using this method, a string can be checked whether it is in a length or not. Both min and max
     * range arguments are optional. If they both are not set then no checking is performed and true
     * value is returned. If any range limit is set and trigger is set then it throws exception on
     * failure.
     *
     * The min and max argument are inclusive when the check is performed.
     *
     * @param string $input the string has to be checked.
     * @param ?int $min the min length of the input.
     * @param ?int $max the max length of the input.
     * @param bool $throwErr whether to throw exception on range failure.
     *
     * @return ?string returns the input if it is within the range, otherwise null.
     */
    public static function strLength(string $input, ?int $min = null, ?int $max = null, bool $throwErr = false): ?string {
        $minPass = false;
        $maxPass = false;
        if ($min != null) $minPass = strlen($input) >= $min;
        if ($max != null) $maxPass = strlen($input) <= $max;

        if ($min != null && $max != null && (!$minPass || !$maxPass)) {
            if ($throwErr)
                throw new TrunkErr("The string has to be between $min and $max in length inclusive.");
            return null;
        }

        if ($min != null && !$minPass) {
            if ($throwErr)
                throw new TrunkErr("The string has to be equal to or greater than $min in length.");
            return null;
        }

        if ($max != null && !$maxPass) {
            if ($throwErr)
                throw new TrunkErr("The string has to be equal to or less than $max in length.");
            return null;
        }

        return $input;
    }

    /**
     * Using this method, an integer can be checked whether it is in range or not. Both min and max
     * range arguments are optional. If they both are not set then no checking is performed and true
     * value is returned. If any range limit is set and trigger is set then it throws exception on
     * failure.
     *
     * The min and max argument are inclusive when the check is performed.
     *
     * @param int $input the integer has to be checked.
     * @param ?int $min the min limit of the input.
     * @param ?int $max the max limit of the input.
     * @param bool $throwErr whether to throw exception on range failure.
     *
     * @return ?int returns true if the input is within the range, otherwise null.
     * **/
    public static function intLimit(int $input, ?int $min = null, ?int $max = null, bool $throwErr = false): ?int {
        $haveBothRange = $min != null && $max != null;

        $minPass = false;
        $maxPass = false;
        if ($min != null) $minPass = $input >= $min;
        if ($max != null) $maxPass = $input <= $max;

        if ($haveBothRange && (!$minPass || !$maxPass)) {
            if ($throwErr)
                throw new TrunkErr("The integer has to be between $min and $max inclusive.");
            return null;
        }

        if ($min != null && !$minPass) {
            if ($throwErr)
                throw new TrunkErr("The integer has to be equal to or greater than $min.");
            return null;
        }

        if ($max != null && !$maxPass) {
            if ($throwErr)
                throw new TrunkErr("The integer has to be equal to or less than $max.");
            return null;
        }

        return $input;
    }

}