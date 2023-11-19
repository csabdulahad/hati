<?php

namespace hati;

/**
 * Hati provides very simple means of Benchmarking functionalities. This class uses
 * singleton pattern like many Hati class libraries. Any starting point of the
 * benchmark must be started with calling {@link Benchmark::from()} method and end point must
 * call the {@link Benchmark::to()} method in order to calculate the execution time.
 * */

class Benchmark {

    private static ?Benchmark $INS = null;

    // pair array for start & end marking points array
    private array $markA = [];
    private array $markB = [];

    private function __construct() {

    }

    // singleton pattern
    private static function get(): Benchmark {
        if (self::$INS == null) self::$INS = new Benchmark();
        return self::$INS;
    }

    /**
     * This marks the starting point of the benchmarking inside of the code.
     * If you call this method, then associated @link to() method must be called
     * in order to mark the end point of the benchmarking.
     * */
    public static function from(): void {
        $ins = self::get();
        $ins -> markA[] = microtime(true);
    }

    /**
     * This marks the end point of the benchmarking inside of the code.
     * If you call this method, then it assumes associated @link from() method
     * has already been called in order to calculate the benchmarking.
     * */
    public static function to(): void {
        $ins = self::get();
        $ins -> markB[] = microtime(true);
    }

    /**
     * Using this method any start and end point benchmarking can be extracted.
     * If there is any marking array which is out of index, then it resets all
     * the marking and returns null to indicate that.
     *
     * @return ?float It returns the last mark. Returns null if it fails to calculate.
     * */
    public static function getMarking(): ?float {
        $ins = self::get();
        if (count($ins -> markA) == 0 || count($ins -> markB) == 0) {
            array_splice($ins -> markA, 0);
            array_splice($ins -> markB, 0);
            return null;
        }
        $start = array_pop($ins -> markA);
        $end = array_pop($ins -> markB);
        return $end - $start;
    }

    /**
     * Using this method, the last marking can be printed. If the last calculation
     * is null then it prints RST to tell that.
     * */
    public static function print(string $msg = 'Execution time:'): void {
        $ins = self::get();
        $time = $ins -> getMarking();
        if ($time == null) echo 'Execution time: RST';
        else echo sprintf('%s %.4f', $msg, $time);
    }

}