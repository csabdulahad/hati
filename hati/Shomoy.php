<?php

namespace hati;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use hati\trunk\TrunkErr;
use Throwable;

/**
 * Shomoy class is a wrapper class around php's DateTime object class. It has many
 * helpful methods and constants to simplify date and time calculation in the client
 * code. It creates datetime of default timezone as how it is configured in the
 * HatiConfig.php file.
 *
 * See HatiConfig.php file for changing the default timezone.
 * */

class Shomoy {

    public const TIMEZONE_DHAKA = 'Asia/Dhaka';
    public const TIMEZONE_LONDON = 'Europe/London';

    private DateTime $dateTime;

    // During construction of shomoy object, it created the internal datetime
    // object with the default timezone as it is configured in the HatiConfig.php
    // file. By default, it creates current datetime object.
    public function __construct(string $time = 'now', string $timezone = null) {
       try {
           $timezone = $timezone ?? Hati::defaultTimezone();
           $this -> dateTime = new DateTime($time, new DateTimeZone($timezone));
       } catch (Throwable $t) {
           throw new TrunkErr('Hati encountered error while creating current date & time: ' . $t -> getMessage());
       }
    }

    /**
     * This function can create Shomoy object from timestamp.
     *
     * @param int $timestamp the timestamp for the datetime.
     *
     * @return Shomoy the shomoy object that is representing the timestamp.
     * */
    public static function fromTimestamp(int $timestamp): Shomoy {
        // first, convert the timestamp into textual representation.
        // then create datetime object from that string.
        $dateTime = date_create(date(DateTimeInterface::ISO8601, $timestamp));

        // finally wrap that formatted string value of datetime within shomoy and return.
        return new Shomoy($dateTime -> format(DateTimeInterface::ISO8601));
    }

    /**
     * When this methods compares itself with other date time object, it is considered
     * that the comparing date time object is in the same timezone as this shomoy date
     * time is. The  Shomoy gets its default timezone from the Hati by calling
     * Hati::defaultTimezone() method. It can be configured with other flags in
     * HatiConfig.php file.
     *
     * For removing all the confusion and ambiguity in date time, please always store and
     * use one timezone in all persistent storage. Just convert the timezone into native
     * or user recommend timezone when displaying date time.
     *
     * @param DateTime $dateTime the datetime you want to compare with. This has to be in
     * the same timezone as this shomoy datetime is.
     *
     * @return int it returns -1 if this shomoy is behind the comparing datetime.
     * 1 is returned when this shomoy is ahead of the comparing datetime.
     * 0 is returned when both of the datetime are equal.
     */
    public function compareDateTime(DateTime $dateTime): int {
        // get micro-seconds from both objects
        $thisSec = strtotime($this -> dateTime -> format(DateTimeInterface::ISO8601)) * 1000;
        $thatSec = strtotime($dateTime -> format(DateTimeInterface::ISO8601)) * 1000;

        // now calculate the difference
        if ($thisSec == $thatSec) return 0;
        else if ($thisSec < $thatSec) return -1;
        else return 1;
    }

    /**
     * A shomoy object can compare itself with other shomoy object. Internally it
     * uses the @link compareDateTime() function to calculate the difference in
     * timestamp and returns either 0, 1, or -1 based on the calculation.
     *
     * @param Shomoy $shomoy The Shomoy object to calculate against
     *
     * @return int the difference between two shomoy objects
     * */
    public function compare(Shomoy $shomoy): int {
        return $this -> compareDateTime($shomoy -> getDateTime());
    }

    /*
     * The difference between two shomoy objects can be calculated either in
     * milliseconds(which is default) or microseconds(timestamp) value. It always
     * finds the difference from $this object to passed one.
     *
     * @param Shomoy $shomoy the Shomoy object to calculate the difference against
     * @param bool $inMilli indicates whether to calculate in milliseconds or microseconds
     *
     * @return int the difference between two Shomoy objects.
     * */
    public function diff(Shomoy $shomoy, bool $inMilli = true): int {
        if ($inMilli) return $this -> getMilliSeconds() - $shomoy -> getMilliSeconds();
        else return $this -> getTimestamp() - $shomoy -> getTimestamp();
    }

    /**
     * Any number of seconds can be added to the Shomoy object using this method.
     *
     * @param int $sec number of seconds to be added.
     * */
    public function addSec(int $sec) {
        try {
            $interval = new DateInterval(sprintf('PT%dS', $sec));
            $this -> dateTime -> add($interval);
        } catch (Exception $e) {
        }
    }

    /**
     * Any number of minutes can be added to the Shomoy object using this method.
     *
     * @param int $min number of minutes to be added.
     * */
    public function addMin(int $min) {
        try {
            $interval = new DateInterval(sprintf('PT%dM', $min));
            $this -> dateTime -> add($interval);
        } catch (Exception $e) {
        }
    }

    /**
     * Any number of hours can be added to the Shomoy object using this method.
     *
     * @param int $hour number of hours to be added.
     * */
    public function addHour(int $hour) {
        try {
            $interval = new DateInterval(sprintf('PT%dH', $hour));
            $this -> dateTime -> add($interval);
        } catch (Exception $e) {
        }
    }

    /**
     * Any number of days can be added to the Shomoy object using this method.
     *
     * @param int $day number of days to be added.
     * */
    public function addDay(int $day) {
        try {
            $interval = new DateInterval(sprintf('P%dD', $day));
            $this -> dateTime -> add($interval);
        } catch (Exception $e) {
        }
    }

    /**
     * Any number of months can be added to the Shomoy object using this method.
     *
     * @param int $month number of months to be added.
     * */
    public function addMonth(int $month) {
        try {
            $interval = new DateInterval(sprintf('P%dM', $month));
            $this -> dateTime -> add($interval);
        } catch (Exception $e) {
        }
    }

    /**
     * Any number of years can be added to the Shomoy object using this method.
     *
     * @param int $year number of years to be added.
     * */
    public function addYear(int $year) {
        try {
            $interval = new DateInterval(sprintf('P%dY', $year));
            $this -> dateTime -> add($interval);
        } catch (Exception $e) {
        }
    }

    /**
     * It changes the timezone of the datetime object. However, it doesn't
     * affect the underlying timestamp so changing timezone it just a
     * representational function.
     *
     * Currently Shomoy has two default timezone constants for Dhaka & London.
     *
     * @param string $timeZone the timezone must in region/city format such
     * as Asia/Dhaka.
     * */
    public function changeTimeZoneTo(string $timeZone): void {
        $this -> dateTime -> setTimezone(new DateTimeZone($timeZone));
    }

    public function __toString(): string {
        return $this -> dateTime -> format(DateTimeInterface::ISO8601);
    }

    public function iso(): string {
        return $this -> __toString();
    }

    public function isoDate(): string {
        return $this -> dateTime -> format('Y-m-d');
    }

    public function year() : string {
        return $this -> dateTime -> format('Y');
    }

    public function month(bool $leadingZero = true) : string {
        $month = $this -> dateTime -> format('m');
        if ($leadingZero) return Number::leadingZero((int)$month);
        return $month;
    }

    public function date(bool $leadingZero = true) : string {
        $date = $this -> dateTime -> format('d');
        if ($leadingZero) return Number::leadingZero((int)$date);
        return $date;
    }

    public function day(bool $shortForm = true): string {
        $format = $shortForm ? 'D' : 'l';
        return $this -> dateTime -> format($format);
    }

    public function monthStr(bool $shortForm = true): string {
        $format = $shortForm ? 'M' : 'F';
        return $this -> dateTime -> format($format);
    }

    public function hour(bool $twenty_four = true, bool $leadingZero = true): string {
        if ($twenty_four && $leadingZero) $format = 'H';
        else if ($twenty_four && !$leadingZero) $format = 'G';
        else if (!$twenty_four && $leadingZero) $format = 'h';
        else $format = 'g';
        return $this -> dateTime -> format($format);
    }

    public function min(bool $leadingZero = true): string {
        $min = $this -> dateTime -> format('i');
        return $leadingZero ? $min : (int) $min;
    }

    public function sec(bool $leadingZero = true): string {
        $sec = $this -> dateTime -> format('s');
        return $leadingZero ? $sec : (int) $sec;
    }

    public function ampm(bool $uppercase = true): string {
        $format = $uppercase ? 'A' : 'a';
        return $this -> dateTime -> format($format);
    }

    public function displayDateTime(): string {
        return "{$this -> hour()}:{$this -> min()}:{$this -> sec()}, {$this -> day()} {$this -> date()} {$this -> monthStr()} {$this -> year()}";
    }

    public function displayTime24(): string {
        return "{$this -> hour()}:{$this -> min()}:{$this -> sec()}";
    }

    public function displayTime(bool $uppercase = true): string {
        return "{$this -> hour(false)}:{$this -> min()}:{$this -> sec()} {$this -> ampm($uppercase)}";
    }

    public function getDateTime(): DateTime {
        return $this -> dateTime;
    }

    public function getTimezone(): DateTimeZone {
        return $this -> dateTime -> getTimezone();
    }
    public function getTimestamp(): int {
        return $this -> dateTime -> getTimestamp();
    }

    public function getMilliSeconds(): int {
        return $this -> dateTime -> getTimestamp() * 1000;
    }

    /**
     * Using this method, the starting timestamp of the shomoy can be calculated.
     *
     * @return int the starting timestamp of the shomoy object.
     */
    public function timestampStart(): int {
        return (date_create($this -> isoDate())) -> getTimestamp();
    }

    /**
     * Using this method, the ending timestamp of the shomoy can be calculated.
     *
     * @return int the ending timestamp of the shomoy object.
     */
    public function timestampEnd(): int {
        return $this -> timestampStart() - 1 + self::secInDay(1);
    }

    public static function secInMin(int $of): int {
        return 60 * $of;
    }

    public static function secInHour(int $of): int {
        return 60 * 60 * $of;
    }

    public static function secInDay(int $of): int {
        return 60 * 60 * 24 * $of;
    }

}