<?php

/** @noinspection SpellCheckingInspection */

namespace hati\cli;

use hati\util\Util;
use InvalidArgumentException;
use Throwable;

/**
 * A helper class for CLI programming. Most of this class is brought from CodeIgniter CLI class.
 * Many helpful methods allows easy manipulation with CLI such as {@link progress()}, {@link wait()},
 * {@link table()} etc.
 *
 * @since 5.0.0
 * */

abstract class CLI {

	public static string $waitMsg = 'Press return to continue...';

	private static array $icons =  [
		['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇'],
		['⣾','⣽','⣻','⢿','⡿','⣟','⣯','⣷'],
		['⠁','⠂','⠄','⡀','⢀','⠠','⠐','⠈'],
		['◐', '◓', '◑', '◒'],
		["◜", "◠", "◝", "◞", "◡", "◟"],
		['◰', '◳', '◲', '◱'],
		['|', '/', '-', '*', '\\'],
		["d", "q", "p", "b"],
		["◢", "◣", "◤", "◥"],
		["∙", "●"],
		["▮", "▯"],
		["◉", "◎"],
	];

	// Icon with default style
	private static array $icon = ['◐', '◓', '◑', '◒'];

	private static array $foreground_colors = [
		'black'        => '0;30',
		'dark_gray'    => '1;30',
		'blue'         => '0;34',
		'dark_blue'    => '0;34',
		'light_blue'   => '1;34',
		'green'        => '0;32',
		'light_green'  => '1;32',
		'cyan'         => '0;36',
		'light_cyan'   => '1;36',
		'red'          => '0;31',
		'light_red'    => '1;31',
		'purple'       => '0;35',
		'light_purple' => '1;35',
		'yellow'       => '0;33',
		'light_yellow' => '1;33',
		'light_gray'   => '0;37',
		'white'        => '1;37',
	];

	private static array $background_colors = [
		'black'      => '40',
		'red'        => '41',
		'green'      => '42',
		'yellow'     => '43',
		'blue'       => '44',
		'magenta'    => '45',
		'cyan'       => '46',
		'light_gray' => '47',
	];

	// Height of the CLI window
	protected static ?int $height = null;

	// Width of the CLI window
	protected static ?int $width = null;

	// Whether the current stream supports colored output.
	protected static ?bool $isColored = null;

	/**
	 * Get input from the shell, using the standard STDIN
	 *
	 * Named options must be in the following formats:
	 * php index.php user -v --v -name=John --name=John
	 *
	 * @param string|null $prefix You may specify a string with which to prompt the user.
	 */
	public static function input(?string $prefix = null): string {
		echo $prefix;
		return fgets(fopen('php://stdin', 'rb'));
	}

	/**
	 * Asks the user for input.
	 *
	 * Usage:
	 *
	 * // Takes any input
	 * $color = CLI::prompt('What is your favorite color?');
	 *
	 * // Takes any input, but offers default
	 * $color = CLI::prompt('What is your favourite color?', 'white');
	 *
	 * // Will validate options with the in_list rule and accept only if one of the list
	 * $color = CLI::prompt('What is your favourite color?', array('red','blue'));
	 *
	 * // Do not provide options but requires a valid email
	 * $email = CLI::prompt('What is your favourite color?', null, ['red', 'grey']);
	 *
	 * @param string $field Output "field" question
	 * @param array|string|null $options String to a default value, array to a list of options (the first option will be the default value)
	 * @return string The user input
	 *
	 * @codeCoverageIgnore
	 */
	public static function prompt(string $field, array|string $options = null): string {
		$extraOutput = '';
		$default     = '';

		if (is_string($options)) {
			$extraOutput = ' [' . static::color($options, 'green') . ']';
			$default     = $options;
		}

		if (is_array($options) && $options) {
			$opts               = $options;
			$extraOutputDefault = static::color($opts[0], 'green');

			unset($opts[0]);

			if (empty($opts)) {
				$extraOutput = $extraOutputDefault;
			} else {
				$extraOutput  = '[' . $extraOutputDefault . ', ' . implode(', ', $opts) . ']';
			}

			$default = $options[0];
		}

		static::fwrite(STDOUT, $field . (trim($field) ? ' ' : '') . $extraOutput . ': ');

		// Read the input from keyboard.
		$input = trim(static::input()) ?: $default;

		if (is_array($options)) {
			while (! static::validate($input, $options)) {
				$input = static::prompt($field, $options);
			}
		}

		return $input;
	}

	/**
	 * prompt(), but based on the option's key
	 *
	 * @param array|string      $text       Output "field" text or an one or two value array where the first value is the text before listing the options
	 *                                      and the second value the text before asking to select one option. Provide empty string to omit
	 * @param array             $options    A list of options (array(key => description)), the first option will be the default value
	 *
	 * @return string The selected key of $options
	 */
	public static function promptByKey(array|string $text, array $options): string {
		if (is_string($text)) {
			$text = [$text];
		} elseif (! is_array($text)) {
			throw new InvalidArgumentException("$text can only be of type string|array");
		}

		CLI::isZeroOptions($options);

		if ($line = array_shift($text)) {
			CLI::write($line);
		}

		CLI::printKeysAndValues($options);

		return static::prompt(PHP_EOL . array_shift($text), array_keys($options));
	}

	/**
	 * This method is the same as promptByKey(), but this method supports multiple keys, separated by commas.
	 *
	 * @param string $text    Output "field" text or an one or two value array where the first value is the text before listing the options
	 *                        and the second value the text before asking to select one option. Provide empty string to omit
	 * @param array  $options A list of options (array(key => description)), the first option will be the default value
	 *
	 * @return array The selected key(s) and value(s) of $options
	 */
	public static function promptByMultipleKeys(string $text, array $options): array
	{
		CLI::isZeroOptions($options);

		$extraOutputDefault = static::color('0', 'green');
		$opts               = $options;
		unset($opts[0]);

		if (empty($opts)) {
			$extraOutput = $extraOutputDefault;
		} else {
			$optsKey = [];

			foreach (array_keys($opts) as $key) {
				$optsKey[] = $key;
			}
			$extraOutput = '[' . $extraOutputDefault . ', ' . implode(', ', $optsKey) . ']';
			$extraOutput = 'You can specify multiple values separated by commas.' . PHP_EOL . $extraOutput;
		}

		CLI::write($text);
		CLI::printKeysAndValues($options);
		CLI::newLine();
		$input = static::prompt($extraOutput) ?: 0; // 0 is default

		// validation
		while (true) {
			$pattern = preg_match_all('/^\d+(,\d+)*$/', trim($input));

			// separate input by comma and convert all to an int[]
			$inputToArray = array_map(static fn ($value) => (int) $value, explode(',', $input));
			// find max from key of $options
			$maxOptions = array_key_last($options);
			// find max from input
			$maxInput = max($inputToArray);

			// return the prompt again if $input contain(s) non-numeric charachter, except a comma.
			// And if max from $options less than max from input
			// it is mean user tried to access null value in $options
			if (! $pattern || $maxOptions < $maxInput) {
				static::error('Please select correctly.');
				CLI::newLine();
				$input = static::prompt($extraOutput) ?: 0;
			} else {
				break;
			}
		}

		$input = [];

		foreach ($options as $key => $description) {
			foreach ($inputToArray as $inputKey) {
				if ($key === $inputKey) {
					$input[$key] = $description;
				}
			}
		}

		return $input;
	}

	// --------------------------------------------------------------------
	// Utility for promptBy...
	// --------------------------------------------------------------------

	/**
	 * Validation for $options in promptByKey() and promptByMultipleKeys(). Return an error if $options is an empty array.
	 */
	private static function isZeroOptions(array $options): void	{
		if (! $options) {
			throw new InvalidArgumentException('No options to select from were provided');
		}
	}

	/**
	 * Print each key and value one by one
	 */
	private static function printKeysAndValues(array $options): void {
		// +2 for the square brackets around the key
		$keyMaxLength = max(array_map('mb_strwidth', array_keys($options))) + 2;

		foreach ($options as $key => $description) {
			$name = str_pad('  [' . $key . ']  ', $keyMaxLength + 4);
			CLI::write(CLI::color($name, 'green') . CLI::wrap($description, 125, $keyMaxLength + 4));
		}
	}

	// --------------------------------------------------------------------
	// End Utility for promptBy...
	// --------------------------------------------------------------------

	/**
	 * Validate one prompt "field" at a time
	 *
	 * @param mixed $value Input value
	 * @param array $options
	 *
	 * @return bool
	 * @codeCoverageIgnore
	 */
	protected static function validate(mixed $value, array $options): bool {
		return in_array($value, $options);
	}

	/**
	 * Outputs a string to the CLI without any surrounding newlines.
	 * Useful for showing repeating elements on a single line.
	 *
	 * @param string $text
	 * @param string|null $foreground
	 * @param string|null $background
	 * @return void
	 */
	public static function print(string $text = '', ?string $foreground = null, ?string $background = null): void {
		if ($foreground || $background) {
			$text = static::color($text, $foreground, $background);
		}

		static::fwrite(STDOUT, $text);
	}

	/**
	 * Outputs a string to the cli on its own line.
	 *
	 * @param string $text
	 * @param string|null $foreground
	 * @param string|null $background
	 * @return void
	 */
	public static function write(string $text = '', ?string $foreground = null, ?string $background = null): void {
		if ($foreground || $background) {
			$text = static::color($text, $foreground, $background);
		}

		static::fwrite(STDOUT, $text . PHP_EOL);
	}

	/**
	 * Outputs an error to the CLI using STDERR instead of STDOUT
	 *
	 * @param string $text
	 * @param string $foreground
	 * @param string|null $background
	 * @return void
	 */
	public static function error(string $text, string $foreground = 'light_red', ?string $background = null): void {
		// Check color support for STDERR
		$stdout            = static::$isColored;
		static::$isColored = static::hasColorSupport(STDERR);

		if ($foreground || $background) {
			$text = static::color($text, $foreground, $background);
		}

		static::fwrite(STDERR, $text . PHP_EOL);

		// return STDOUT color support
		static::$isColored = $stdout;
	}

	/**
	 * Beeps a certain number of times.
	 *
	 * @param int $num The number of times to beep
	 *
	 * @return void
	 */
	public static function beep(int $num = 1): void {
		echo str_repeat("\x07", $num);
	}

	/**
	 * A helper function which waits for a certain amount of seconds.
	 *
	 * @param int|float $seconds Number of seconds. Seconds can be a fractional number such as 4.5 seconds
	 * @param string $msg Optional message which will be shown as countdown text
	 * @param bool $askToContinue Indicates whether to ask for confirmation to continue after wait is over
	 * @param bool $keepContinueMsg Whether to keep the the continution confirmation message or to be cleared
	 * */
	public static function wait(int|float $seconds, string $msg = '', bool $askToContinue = false, bool $keepContinueMsg = true): void {
		$int = is_int($seconds);
		$print = !empty($msg);

		if ($print) $msg = "\r\033[K" . $msg;

		// Loop through the seconds and show the countdown
		for ($i = $seconds; $i > 0; $i -= 0.1){

			$frack = str_contains($i, '.');
			if ($int && $frack){
				usleep(100000);
				continue;
			}

			// Output the message with the current time
			if ($print)
				echo sprintf($msg, number_format($i, !$int ? 1 : 0));

			usleep(100000);
		}

		if ($print) echo "\r\033[K";

		if (!$askToContinue) return;

		// ask for continuing...
		self::print(self::$waitMsg);
		self::input();

		if ($keepContinueMsg) return;

		echo "\033[1A"; // Move the cursor one line up
		echo "\033[K";  // Clear the line
	}

	/**
	 * Checks if the OS is windows
	 *
	 * @return bool true if the OS is windows.
	 * */
	public static function isWindows(): bool {
		return PHP_OS_FAMILY === "Windows";
	}

	/**
	 * Enter a number of empty lines
	 *
	 * @param int $num
	 * @return void
	 */
	public static function newLine(int $num = 1): void {
		// Do it once or more, write with empty string gives us a new line
		for ($i = 0; $i < $num; $i++) {
			static::write();
		}
	}

	/**
	 * Clears the screen of output
	 * @return void
	 */
	public static function clearScreen(): void {
		// Unix systems, and Windows with VT100 Terminal support (i.e. Win10)
		// can handle CSI sequences. For lower than Win10 we just shove in 40 new lines.
		self::isWindows() && ! static::streamSupports('sapi_windows_vt100_support', STDOUT)
			? static::newLine(40)
			: static::fwrite(STDOUT, "\033[H\033[2J");
	}

	/**
	 * Returns the given text with the correct color codes for a foreground and
	 * optionally a background color.
	 *
	 * @param string      $text       The text to color
	 * @param string      $foreground The foreground color
	 * @param string|null $background The background color
	 * @param string|null $format     Other formatting to apply. Currently only 'underline' is understood
	 *
	 * @return string The color coded string
	 */
	public static function color(string $text, string $foreground, ?string $background = null, ?string $format = null): string {

		/*
		 * If the isColored is null that means it was not checkd for color support yet.
		 * So check it and cache the result for STDOUT.
		 * */
		if (is_null(self::$isColored) && Util::cli()) {
			static::$isColored = static::hasColorSupport(STDOUT);
		}

		if (! static::$isColored || $text === '') {
			return $text;
		}

		$newText = '';

		// Detect if color method was already in use with this text
		if (str_contains($text, "\033[0m")) {
			$pattern = '/\\033\\[0;.+?\\033\\[0m/u';

			preg_match_all($pattern, $text, $matches);
			$coloredStrings = $matches[0];

			// No colored string found. Invalid strings with no `\033[0;??`.
			if ($coloredStrings === []) {
				return $newText . self::getColoredText($text, $foreground, $background, $format);
			}

			$nonColoredText = preg_replace(
				$pattern,
				'<<__colored_string__>>',
				$text
			);
			$nonColoredChunks = preg_split(
				'/<<__colored_string__>>/u',
				$nonColoredText
			);

			foreach ($nonColoredChunks as $i => $chunk) {
				if ($chunk !== '') {
					$newText .= self::getColoredText($chunk, $foreground, $background, $format);
				}

				if (isset($coloredStrings[$i])) {
					$newText .= $coloredStrings[$i];
				}
			}
		} else {
			$newText .= self::getColoredText($text, $foreground, $background, $format);
		}

		return $newText;
	}

	private static function getColoredText(string $text, string $foreground, ?string $background, ?string $format): string {
		$string = "\033[" . (static::$foreground_colors[$foreground] ?? $foreground) . 'm';

		if ($background !== null) {
			$string .= "\033[" . (static::$background_colors[$background] ?? $background) . 'm';
		}

		if ($format === 'underline') {
			$string .= "\033[4m";
		}

		return $string . $text . "\033[0m";
	}

	/**
	 * Get the number of characters in string having encoded characters
	 * and ignores styles set by the {@link color()} function
	 */
	public static function strlen(?string $string): int	{
		if ($string === null) {
			return 0;
		}

		foreach (static::$foreground_colors as $color) {
			$string = strtr($string, ["\033[" . $color . 'm' => '']);
		}

		foreach (static::$background_colors as $color) {
			$string = strtr($string, ["\033[" . $color . 'm' => '']);
		}

		$string = strtr($string, ["\033[4m" => '', "\033[0m" => '']);

		return mb_strwidth($string);
	}

	/**
	 * Checks whether the current stream resource supports or
	 * refers to a valid terminal type device.
	 *
	 * @param resource $resource
	 */
	public static function streamSupports(string $function, $resource): bool {
		return function_exists($function) && @$function($resource);
	}

	/**
	 * Returns true if the stream resource supports colors.
	 *
	 * This is tricky on Windows, because Cygwin, Msys2 etc. emulate pseudo
	 * terminals via named pipes, so we can only check the environment.
	 *
	 * Reference: https://github.com/composer/xdebug-handler/blob/master/src/Process.php
	 *
	 * @param resource $resource
	 */
	public static function hasColorSupport($resource): bool	{
		// Follow https://no-color.org/
		if (isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false) {
			return false;
		}

		if (getenv('TERM_PROGRAM') === 'Hyper') {
			return true;
		}

		if (self::isWindows()) {
			return static::streamSupports('sapi_windows_vt100_support', $resource)
				|| isset($_SERVER['ANSICON'])
				|| getenv('ANSICON') !== false
				|| getenv('ConEmuANSI') === 'ON'
				|| getenv('TERM') === 'xterm';
		}

		return static::streamSupports('stream_isatty', $resource);
	}

	/**
	 * Attempts to determine the width of the viewable CLI window.
	 */
	public static function getWidth(int $default = 80): int	{
		if (static::$width === null) {
			static::generateDimensions();
		}

		return static::$width ?: $default;
	}

	/**
	 * Attempts to determine the height of the viewable CLI window.
	 */
	public static function getHeight(int $default = 32): int {
		if (static::$height === null) {
			static::generateDimensions();
		}

		return static::$height ?: $default;
	}

	/**
	 * Populates the CLI's dimensions.
	 *
	 * @codeCoverageIgnore
	 *
	 * @return void
	 */
	public static function generateDimensions(): void {
		try {
			if (self::isWindows()) {
				// Shells such as `Cygwin` and `Git bash` returns incorrect values
				// when executing `mode CON`, so we use `tput` instead
				if (getenv('TERM') || (($shell = getenv('SHELL')) && preg_match('/(?:bash|zsh)(?:\.exe)?$/', $shell))) {
					static::$height = (int) exec('tput lines');
					static::$width  = (int) exec('tput cols');
				} else {
					$return = -1;
					$output = [];
					exec('mode CON', $output, $return);

					// Look for the next lines ending in ": <number>"
					// Searching for "Columns:" or "Lines:" will fail on non-English locales
					if ($return === 0 && $output && preg_match('/:\s*(\d+)\n[^:]+:\s*(\d+)\n/', implode("\n", $output), $matches)) {
						static::$height = (int) $matches[1];
						static::$width  = (int) $matches[2];
					}
				}
			} elseif (($size = exec('stty size')) && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
				static::$height = (int) $matches[1];
				static::$width  = (int) $matches[2];
			} else {
				static::$height = (int) exec('tput lines');
				static::$width  = (int) exec('tput cols');
			}
		} catch (Throwable) {
			// Reset the dimensions so that the default values will be returned later.
			// Then let the developer know of the error.
			static::$height = null;
			static::$width  = null;
		}
	}

	/**
	 * Displays a progress bar on the CLI. You must call it repeatedly
	 * to update it. Set $thisStep = false to erase the progress bar.
	 *
	 * @param bool|int $thisStep
	 * @param int $totalSteps
	 * @return void
	 */
	public static function progressBar(bool|int $thisStep = 1, int $totalSteps = 10): void {
		if ($thisStep !== false) {
			// Don't allow div by zero or negative numbers....
			$thisStep   = abs($thisStep);
			$totalSteps = max($totalSteps, 1);

			$percent = (int) (($thisStep / $totalSteps) * 100);
			$step    = (int) round($percent / 5);

			// Write the progress bar
			static::fwrite(STDOUT, "\r\033[K[\033[32m" . str_repeat('*', $step) . str_repeat('-', 20 - $step) . "\033[0m]");
			// Textual representation...
			static::fwrite(STDOUT, sprintf(' %3d%%', $percent));
		} else {
			static::fwrite(STDOUT, "\r\033[K");
			static::fwrite(STDOUT, "\007");
		}
	}

	/**
	 * Takes a string and writes it to the command line, wrapping to a maximum
	 * width. If no maximum width is specified, will wrap to the window's max
	 * width.
	 *
	 * If an int is passed into $pad_left, then all strings after the first
	 * will pad with that many spaces to the left. Useful when printing
	 * short descriptions that need to start on an existing line.
	 */
	public static function wrap(?string $string = null, int $max = 0, int $padLeft = 0): string	{
		if (empty($string)) {
			return '';
		}

		if ($max === 0) {
			$max = self::getWidth();
		}

		if (self::getWidth() < $max) {
			$max = self::getWidth();
		}

		$max -= $padLeft;

		$lines = wordwrap($string, $max, PHP_EOL);

		if ($padLeft > 0) {
			$lines = explode(PHP_EOL, $lines);

			$first = true;

			array_walk($lines, static function (&$line) use ($padLeft, &$first) {
				if (! $first) {
					$line = str_repeat(' ', $padLeft) . $line;
				} else {
					$first = false;
				}
			});

			$lines = implode(PHP_EOL, $lines);
		}

		return $lines;
	}

	/**
	 * Returns a well formatted table
	 *
	 * @param array $tbody List of rows
	 * @param array $thead List of columns
	 * @param bool $return
	 * @return string|int
	 */
	public static function table(array $tbody, array $thead = [], bool $return = false): int|string {
		// All the rows in the table will be here until the end
		$tableRows = [];

		// We need only indexes and not keys
		if (! empty($thead)) {
			$tableRows[] = array_values($thead);
		}

		foreach ($tbody as $tr) {
			$tableRows[] = array_values($tr);
		}

		// Yes, it really is necessary to know this count
		$totalRows = count($tableRows);

		// Store all columns lengths
		// $all_cols_lengths[row][column] = length
		$allColsLengths = [];

		// Store maximum lengths by column
		// $max_cols_lengths[column] = length
		$maxColsLengths = [];

		// Read row by row and define the longest columns
		for ($row = 0; $row < $totalRows; $row++) {
			$column = 0; // Current column index

			foreach ($tableRows[$row] as $col) {
				// Sets the size of this column in the current row
				$allColsLengths[$row][$column] = static::strlen($col);

				// If the current column does not have a value among the larger ones
				// or the value of this is greater than the existing one
				// then, now, this assumes the maximum length
				if (! isset($maxColsLengths[$column]) || $allColsLengths[$row][$column] > $maxColsLengths[$column]) {
					$maxColsLengths[$column] = $allColsLengths[$row][$column];
				}

				// We can go check the size of the next column...
				$column++;
			}
		}

		// Read row by row and add spaces at the end of the columns
		// to match the exact column length
		for ($row = 0; $row < $totalRows; $row++) {
			$column = 0;

			foreach ($tableRows[$row] as $col) {
				$diff = $maxColsLengths[$column] - static::strlen($col);

				if ($diff !== 0) {
					$tableRows[$row][$column] .= str_repeat(' ', $diff);
				}

				$column++;
			}
		}

		$table = '';
		$cols  = '';

		// Joins columns and append the well formatted rows to the table
		for ($row = 0; $row < $totalRows; $row++) {
			// Set the table border-top
			if ($row === 0) {
				$cols = '+';

				foreach ($tableRows[$row] as $col) {
					$cols .= str_repeat('-', static::strlen($col) + 2) . '+';
				}
				$table .= $cols . PHP_EOL;
			}

			// Set the columns borders
			$table .= '| ' . implode(' | ', $tableRows[$row]) . ' |' . PHP_EOL;

			// Set the thead and table borders-bottom
			if (($row === 0 && ! empty($thead)) || ($row + 1 === $totalRows)) {
				$table .= $cols . PHP_EOL;
			}
		}

		if ($return) return $table;

		static::write($table);
		return mb_strlen($table);
	}

	/**
	 * While the library is intended for use on CLI commands,
	 * commands can be called from controllers and elsewhere
	 * so we need a way to allow them to still work.
	 *
	 * For now, just echo the content, but look into a better
	 * solution down the road.
	 *
	 * @param resource $handle
	 * @param string $string
	 * @return void
	 */
	private static function fwrite($handle, string $string): void {
		if (! Util::cli()) {
			echo $string;
			return;
		}

		fwrite($handle, $string);
	}

	/**
	 * Sets progress icons style for progress.
	 *
	 * @param ProgressIcon $icon progress icon style contanst
	 * */
	public static function setProgressIcon(ProgressIcon $icon): void {
		self::$icon = match ($icon) {
			ProgressIcon::BRAILLE => self::$icons[0],
			ProgressIcon::DOT_RUNNING_AROUND => self::$icons[2],
			ProgressIcon::HALF_CIRCLE_SPINNING => self::$icons[3],
			ProgressIcon::CIRCLE_QUARTER => self::$icons[4],
			ProgressIcon::SQUARE_QUARTER => self::$icons[5],
			ProgressIcon::SLASHES => self::$icons[6],
			ProgressIcon::BDPQ => self::$icons[7],
			ProgressIcon::TRIANGLE_QUARTER => self::$icons[8],
			ProgressIcon::PULSE_DOT => self::$icons[9],
			ProgressIcon::PULSE_SQUARE => self::$icons[10],
			ProgressIcon::PULSE_CIRCLE => self::$icons[11],
			default => self::$icons[1]
		};
	}

	/**
	 * Nice progress status can be shown using this method. To stop showing the progress,
	 * call this method with null as $msg and false as $running. To show different icon in
	 * the progress, use {@link setProgressIcon()}.
	 *
	 * @param ?string $msg The text to be shown when showing progress
	 * @param bool $running To indicate whether the progress is done or running
	 * @param bool $success Progress can be marked either successful or not. When marked true,
	 * then the progress finish will be shown in green text with tick sign. Otherwise will be
	 * shown in red text with a cross sign.
	 * @param bool $sticky Whether to keep the progress message each time this method is called
	 * @param bool $colorize Indicates whether any successful/fail message uses color or normal text
	 * @param bool $icon Indicates whether to show any icon with the progress message
	 * */
	public static function progress(?string $msg, bool $running = true, bool $success = true, bool $sticky = false, bool $colorize = true, bool $icon = true): void {
		if (is_null($msg)) {
			$running = false;
		}

		static $iconIndex = -1;
		if ($iconIndex == count(self::$icon)-1) $iconIndex = -1;
		$i = self::$icon[++$iconIndex];

		// move the cursor to the beginning of the row & clear the line
		$output = "\r\033[K";

		if ($running) {
			if ($colorize) $output .= "\033[1;33m";
			if ($icon) $output .= "$i ";

			$output .= "$msg...";
		} elseif (!is_null($msg)) {
			$iconIndex = -1;

			if ($colorize) $output .= "\033[" . ($success ? '1;32' : '1;31') . "m";
			if ($icon) $output .= $success ? '✔ ' : '✖ ';

			$output .= $msg;

			if ($sticky) $output .= PHP_EOL;
		}
		$output .= "\033[0m";

		echo $output;
	}

	/**
	 * Prints strikethrough text in console
	 * @param mixed $text the text
	 * @param bool $return whether to return the text with strikethrough formatting or to print
	 * @return string|int formatted text when $return is true; otherwise prints out text and returns
	 * the number of characters it printed out.
	 * */
	public static function strikeThrough(mixed $text, bool $return = false): string|int {
		// Check if the terminal supports strike-through

		if (mb_stripos(`echo -e "\033[9m"`, "\033[9m") !== false) {
			$text = "\033[9m$text\033[0m";
		}

		if ($return) return $text;

		echo $text;
		return mb_strlen($text);
	}

	/**
	 * Prints strikethrough text in console
	 * @param mixed $text the text
	 * @param bool $return whether to return the text with strikethrough formatting or to print
	 * @return string|int formatted text when $return is true; otherwise prints out text and returns
	 * the number of characters it printed out.
	 * */
	public static function underline(mixed $text, bool $return = false): string|int {
		// Check if the terminal supports underlining
		if (mb_stripos(`echo -e "\033[4m"`, "\033[4m") !== false) {
			$text = "\033[4m$text\033[0m";
		}

		if ($return) return $text;

		echo $text;
		return mb_strlen($text);
	}

	/**
	 * Prints italic text in console
	 * @param mixed $text the text
	 * @param bool $return whether to return the text with italic formatting or to print
	 * @return string|int formatted text when $return is true; otherwise prints out text and returns
	 * the number of characters it printed out.
	 * */
	public static function italic(mixed $text, bool $return = false): string|int {
		// Check if the terminal supports italic text
		if (mb_stripos(`echo -e "\033[3m"`, "\033[3m") !== false) {
			$text = "\033[3m$text\033[0m";
		}

		if ($return) return $text;

		echo $text;
		return mb_strlen($text);
	}

	/**
	 * Prints bold text in console
	 * @param mixed $text the text
	 * @param bool $return whether to return the text with bold formatting or to print
	 * @return string|int formatted text when $return is true; otherwise prints out text and returns
	 * the number of characters it printed out.
	 * */
	public static function bold(mixed $text, bool $return = false): string|int {
		// Check if the terminal supports bold text
		if (mb_stripos(`echo -e "\033[1m"`, "\033[1m") !== false) {
			$text = "\033[1m$text\033[0m\n";
		}

		if ($return) return $text;

		echo $text;
		return mb_strlen($text);
	}

	/**
	 * Asks for user confirmation. '[y/n] : ' will be appended to the prompt
	 * message. Use {@link \hati\cli\Color} class constants for setting
	 * prompt text background/foreground color.
	 *
	 * @param string $prompt Confirmation message to be shown before input
	 * @param int $attempt The number of attempt user needs to confirm by
	 * @param ?string $promptColor Background/foreground for the prompot text
	 * @return true when user confirms positive. Returns false user doesn't
	 * confirm with yes or number of attempts tried.
	 * */
	public static function confirm(string $prompt = 'Are you sure?', int $attempt = 3, ?string $promptColor = null): bool {

		$confirmation = false;
		$count = 0;

		$prompt .= ' [y/n] : ';
		if (!is_null($promptColor)) {
			$prompt = self::color($prompt, $promptColor);
		}

		while (true) {
			if ($count == $attempt) break;
			$count++;

			$input = self::input($prompt);
			$input = trim($input);

			if (!in_array($input, ['y', 'n'])) {
				continue;
			} else {
				$confirmation = $input == 'y';
				break;
			}
		}

		return $confirmation;
	}

}