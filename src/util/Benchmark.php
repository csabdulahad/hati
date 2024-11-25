<?php

namespace hati\util;

/**
 * Hati provides very simple means of Benchmarking functionalities. This class uses
 * singleton pattern like many Hati class libraries. Any starting point of the
 * benchmark must be started with calling {@link Benchmark::start()} method and end point must
 * call the {@link Benchmark::end()} method in order to calculate the execution time.
 * */

class Benchmark {

	private static ?Benchmark $INS = null;

	// Benchmark buffer
	private array $marks = [];

	private function __construct() {}

	// singleton pattern
	private static function get(): Benchmark {
		if (self::$INS == null) self::$INS = new Benchmark();
		return self::$INS;
	}

	/**
	 * This marks the starting point of a benchmarking
	 *
	 * @param string $name The name to start the benchmark for
	 * */
	public static function start(string $name): void {
		$ins = self::get();

		// Reset if there already exists the same key!
		$ins -> marks[$name] = [];

		$ins -> marks[$name][] = microtime(true);
	}

	/**
	 * This marks the end of a benchmarking point
	 *
	 * @param string $name The name to end the benchmark for
	 */
	public static function end(string $name): void {
		$ins = self::get();
		$ins -> marks[$name][] = microtime(true);
	}

	/**
	 * Get all the markings benchmarked
	 *
	 * @return array containing associative array for benchmarks
	 * */
	public static function getMarkings(): array {
		$ins = self::get();
		return $ins -> marks;
	}

	/**
	 * Gets a benchmark by a name. If there is no benchmark has been done for
	 * the name, then 'N/A' is returned.
	 *
	 * @param string $name the name of the benchmark to be returned
	 * @return string benchmark specified by the name
	 * */
	public static function getMark(string $name): string {
		$ins = self::get();

		$data = $ins -> marks[$name] ?? [];
		if (count($data) != 2) {
			$str = 'N/A';
		} else {
			$str = sprintf('%.4f', $data[1] - $data[0]);
		}

		return $str;
	}

	/**
	 * Using this method, a benchmark can be printed out with a message.
	 *
	 * @param string $name The name for the benchmark
	 * @param string $msg Any additional message to be appended to the benchmark
	 * */
	public static function print(string $name, string $msg = 'Execution time: '): void {
		$time = self::getMark($name);
		if (!is_numeric($time))
			echo sprintf("%s%s", $msg, $time);
		else
			echo sprintf('%s%.4f', $msg, $time);
	}

}