<?php

namespace hati;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
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
           throw new HatiError('Hati encountered error while creating current date & time: ' . $t -> getMessage());
       }
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
    public function compare(DateTime $dateTime): int {
        // get micro-seconds from both objects
        $thisSec = strtotime($this -> dateTime -> format(DateTimeInterface::ISO8601)) * 1000;
        $thatSec = strtotime($dateTime -> format(DateTimeInterface::ISO8601)) * 1000;

        // now calculate the difference
        if ($thisSec == $thatSec) return 0;
        else if ($thisSec < $thatSec) return -1;
        else return 1;
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