<?php

namespace hati\cli\io;

use hati\cli\ProgressIcon;
use hati\util\Util;
use InvalidArgumentException;
use Throwable;

abstract class TerminalIO
{
	public string $waitMsg = 'Press return to continue...';
	
	private static array $icons = [
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
	private array $icon = ['◐', '◓', '◑', '◒'];
	
	// Height of the CLI window
	protected ?int $height = null;
	
	// Width of the CLI window
	protected ?int $width = null;
	
	protected array $foreground_colors = [
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
	
	protected array $background_colors = [
		'black'      => '40',
		'red'        => '41',
		'green'      => '42',
		'yellow'     => '43',
		'blue'       => '44',
		'magenta'    => '45',
		'cyan'       => '46',
		'light_gray' => '47',
	];
	
	abstract public function read(): string;
	abstract public function write(string $text = ''): void;
	abstract public function writeNoLine(string $text): void;
	abstract public function error(string $text): void;
	abstract public function isInteractive(): bool;
	
	/**
	 * Get input from the shell, using the standard STDIN
	 *
	 * Named options must be in the following formats:
	 * php index.php user -v --v -name=John --name=John
	 *
	 * @param string|null $promptMsg You may specify a string with which to prompt the user.
	 * @param bool $trim when set true, it will remove whitespace/line break from the input.
	 * @return string the input enter by the user
	 */
	public function input(?string $promptMsg = null, bool $trim = true): string
	{
		if ($promptMsg !== null) {
			$this->writeNoLine($promptMsg);
		}
		
		$input = $this->read();
		return $trim ? trim($input) : $input;
	}
	
	/**
	 * Asks for user confirmation. '[y/n] : ' will be appended to the prompt
	 * message.
	 *
	 * @param string $prompt Confirmation message to be shown before input
	 * @param int $attempt The number of attempt user needs to confirm by
	 *
	 * @return true when user confirms positive. Returns false user doesn't
	 * confirm with yes or number of attempts tried.
	 * */
	public function confirm(string $prompt = 'Are you sure?', int $attempt = 3): bool
	{
		$prompt .= ' [y/n] : ';
		
		for ($count = 0; $count < $attempt; $count++) {
			$input = trim($this->input($prompt));
			if ($input === 'y') return true;
			if ($input === 'n') return false;
		}
		
		return false;
	}
	
	/**
	 * A helper function which waits for a certain amount of seconds.
	 *
	 * @param int|float $seconds Number of seconds. Seconds can be a fractional number such as 4.5 seconds
	 * @param string $msg Optional message which will be shown as countdown text
	 * @param bool $askToContinue Indicates whether to ask for confirmation to continue after wait is over
	 * @param bool $keepContinueMsg Whether to keep the continuation confirmation message or to be cleared
	 * */
	public function wait(int|float $seconds, string $msg = '', bool $askToContinue = false, bool $keepContinueMsg = true): void
	{
		$int = is_int($seconds);
		$print = !empty($msg);
		
		// Only do cursor-control output in interactive terminals
		$interactive = $this->isInteractive();
		
		if ($print && $interactive) $msg = "\r\033[K" . $msg;
		
		// Loop through the seconds and show the countdown
		for ($i = (float)$seconds; $i > 0; $i -= 0.1) {
			$frack = (abs($i - floor($i)) > 0.000001);
			
			if ($int && $frack){
				usleep(100000);
				continue;
			}
			
			// Output the message with the current time
			if ($print) {
				$val = number_format($i, !$int ? 1 : 0);
				
				if ($interactive) {
					// same behavior as before (overwrite same line)
					$this->writeNoLine(sprintf($msg, $val));
				}
			}
			
			usleep(100000);
		}
		
		if ($print && $interactive) $this->writeNoLine("\r\033[K");
		
		if (!$askToContinue) return;
		
		// ask for continuing...
		$this->writeNoLine($this->waitMsg);
		$this->input();
		
		if ($keepContinueMsg) return;
		
		if ($interactive) {
			$this->writeNoLine("\033[1A"); // Move the cursor one line up
			$this->writeNoLine("\033[K");  // Clear the line
		}
	}
	
	/**
	 * Returns a well formatted table
	 *
	 * @param array $tbody List of rows
	 * @param array $thead List of columns
	 * @param bool $return
	 * @param bool $border
	 * @param bool $keepHeaderBorder
	 * @return string|int
	 */
	public function table(array $tbody, array $thead = [], bool $return = false, bool $border = true, bool $keepHeaderBorder = true): int|string
	{
		$tableRows = [];
		
		if (! empty($thead)) {
			$tableRows[] = array_values($thead);
		}
		
		foreach ($tbody as $tr) {
			$tableRows[] = array_values($tr);
		}
		
		$totalRows = count($tableRows);
		
		$allColsLengths = [];
		$maxColsLengths = [];
		
		for ($row = 0; $row < $totalRows; $row++) {
			$column = 0;
			
			foreach ($tableRows[$row] as $col) {
				$allColsLengths[$row][$column] = $this->strlen($col);
				
				if (! isset($maxColsLengths[$column]) || $allColsLengths[$row][$column] > $maxColsLengths[$column]) {
					$maxColsLengths[$column] = $allColsLengths[$row][$column];
				}
				
				$column++;
			}
		}
		
		for ($row = 0; $row < $totalRows; $row++) {
			$column = 0;
			
			foreach ($tableRows[$row] as $col) {
				$diff = $maxColsLengths[$column] - $this->strlen($col);
				
				if ($diff !== 0) {
					$tableRows[$row][$column] .= str_repeat(' ', $diff);
				}
				
				$column++;
			}
		}
		
		$table = '';
		$cols  = '';
		
		if ($border && $totalRows > 0) {
			$cols = '+';
			foreach ($tableRows[0] as $col) {
				$cols .= str_repeat('-', $this->strlen($col) + 2) . '+';
			}
		}
		
		for ($row = 0; $row < $totalRows; $row++) {
			if ($border && $row === 0) {
				$table .= $cols . PHP_EOL;
			}
			
			if ($border) {
				$table .= '| ' . implode(' | ', $tableRows[$row]) . ' |' . PHP_EOL;
			} else {
				$table .= implode('  ', $tableRows[$row]) . PHP_EOL;
				
				if (
					$row === 0 &&
					! empty($thead) &&
					$keepHeaderBorder
				) {
					$underline = [];
					foreach ($tableRows[$row] as $i => $col) {
						$underline[] = str_repeat('-', $maxColsLengths[$i]);
					}
					$table .= implode('  ', $underline) . PHP_EOL;
				}
			}
			
			if ($border && (($row === 0 && ! empty($thead)) || ($row + 1 === $totalRows))) {
				$table .= $cols . PHP_EOL;
			}
		}
		
		$table = rtrim($table);
		
		if ($return) return $table;
		
		$this->write($table);
		return mb_strlen($table);
	}
	
	/**
	 * Beeps a certain number of times.
	 *
	 * @param int $num The number of times to beep
	 *
	 * @return void
	 */
	public function beep(int $num = 1): void
	{
		if (!$this->isInteractive() || $num < 1) {
			return;
		}
		
		$this->writeNoLine(str_repeat("\x07", $num));
	}
	
	/**
	 * Asks the user for input.
	 *
	 * Usage:
	 *
	 * // Takes any input
	 * $color = prompt('What is your favorite color?');
	 *
	 * // Takes any input, but offers default
	 * $color = prompt('What is your favourite color?', 'white');
	 *
	 * // Will validate options with the in_list rule and accept only if one of the list
	 * $color = prompt('What is your favourite color?', ['red','blue']);
	 *
	 * // Do not provide options but requires a valid email
	 * $email = prompt('What is your favourite color?', null, ['red', 'gray']);
	 *
	 * @param string $field Output "field" question
	 * @param array|string|null $options String to a default value, array to a list of options (the first option will be the default value)
	 * @return string The user input
	 *
	 * @codeCoverageIgnore
	 */
	public function prompt(string $field, array|string $options = null): string
	{
		$extraOutput = '';
		$default     = '';
		
		if (is_string($options)) {
			$extraOutput = ' [' . $this->color($options, 'green') . ']';
			$default     = $options;
		}
		
		if (is_array($options) && $options) {
			$opts               = $options;
			$extraOutputDefault = $this->color($opts[0], 'green');
			
			unset($opts[0]);
			
			if (empty($opts)) {
				$extraOutput = $extraOutputDefault;
			} else {
				$extraOutput  = '[' . $extraOutputDefault . ', ' . implode(', ', $opts) . ']';
			}
			
			$default = $options[0];
		}
		
		$this->writeNoLine($field . (trim($field) ? ' ' : '') . $extraOutput . ': ');
		
		// Read the input from keyboard.
		$input = trim($this->input()) ?: $default;
		
		if (is_array($options)) {
			while (!in_array($input, $options, true)) {
				$input = $this->prompt($field, $options);
			}
		}
		
		return $input;
	}
	
	/**
	 * prompt(), but based on the option's key
	 *
	 * @param array|string      $text       Output "field" text or a one or two value array where the first value is the text before listing the options
	 *                                      and the second value the text before asking to select one option. Provide empty string to omit
	 * @param array             $options    A list of options (array(key => description)), the first option will be the default value
	 *
	 * @return string The selected key of $options
	 */
	public function promptByKey(array|string $text, array $options): string
	{
		if (is_string($text)) {
			$text = [$text];
		} elseif (! is_array($text)) {
			throw new InvalidArgumentException("$text can only be of type string|array");
		}
		
		if ($options === []) {
			throw new InvalidArgumentException('No options to select from were provided');
		}
		
		if ($line = array_shift($text)) {
			$this->write($line);
		}
		
		$this->printKeysAndValues($options);
		
		return $this->prompt(PHP_EOL . array_shift($text), array_keys($options));
	}
	
	/**
	 * This method is the same as promptByKey(), but this method supports multiple keys, separated by commas.
	 *
	 * @param string $text    Output "field" text or a one or two value array where the first value is the text before listing the options
	 *                        and the second value the text before asking to select one option. Provide empty string to omit
	 * @param array  $options A list of options (array(key => description)), the first option will be the default value
	 *
	 * @return array The selected key(s) and value(s) of $options
	 */
	public function promptByMultipleKeys(string $text, array $options): array
	{
		if ($options === []) {
			throw new InvalidArgumentException('No options to select from were provided');
		}
		
		$extraOutputDefault = $this->color('0', 'green');
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
		
		$this->write($text);
		$this->printKeysAndValues($options);
		$this->newLine();
		$input = $this->prompt($extraOutput) ?: 0; // 0 is default
		
		// validation
		while (true) {
			$pattern = preg_match_all('/^\d+(,\d+)*$/', trim($input));
			
			// separate input by comma and convert all to an int[]
			$inputToArray = array_map(static fn ($value) => (int) $value, explode(',', $input));
			// find max from key of $options
			$maxOptions = array_key_last($options);
			// find max from input
			$maxInput = max($inputToArray);
			
			// return the prompt again if $input contain(s) non-numeric character, except a comma.
			// And if max from $options less than max from input
			// it is mean user tried to access null value in $options
			if (! $pattern || $maxOptions < $maxInput) {
				$this->error('Please select correctly.');
				$this->newLine();
				$input = $this->prompt($extraOutput) ?: 0;
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
	
	private function printKeysAndValues(array $options): void
	{
		// +2 for the square brackets around the key
		$keyMaxLength = max(array_map('mb_strwidth', array_keys($options))) + 2;
		
		foreach ($options as $key => $description) {
			$name = str_pad('  [' . $key . ']  ', $keyMaxLength + 4);
			$this->write($this->color($name, 'green') . $this->wrap($description, 125, $keyMaxLength + 4));
		}
	}
	
	private function generateDimensions(): void {
		try {
			if ($this->isWindows()) {
				// Shells such as `Cygwin` and `Git bash` returns incorrect values
				// when executing `mode CON`, so we use `tput` instead
				if (getenv('TERM') || (($shell = getenv('SHELL')) && preg_match('/(?:bash|zsh)(?:\.exe)?$/', $shell))) {
					$this->height = (int) exec('tput lines');
					$this->width  = (int) exec('tput cols');
				} else {
					$return = -1;
					$output = [];
					exec('mode CON', $output, $return);
					
					// Look for the next lines ending in ": <number>"
					// Searching for "Columns:" or "Lines:" will fail on non-English locales
					if ($return === 0 && $output && preg_match('/:\s*(\d+)\n[^:]+:\s*(\d+)\n/', implode("\n", $output), $matches)) {
						$this->height = (int) $matches[1];
						$this->width  = (int) $matches[2];
					}
				}
			} elseif (($size = exec('stty size')) && preg_match('/(\d+)\s+(\d+)/', $size, $matches)) {
				$this->height = (int) $matches[1];
				$this->width  = (int) $matches[2];
			} else {
				$this->height = (int) exec('tput lines');
				$this->width  = (int) exec('tput cols');
			}
		} catch (Throwable) {
			// Reset the dimensions so that the default values will be returned later.
			// Then let the developer know of the error.
			$this->height = null;
			$this->width  = null;
		}
	}
	
	/**
	 * Attempts to determine the width of the viewable CLI window.
	 */
	public function getWidth(int $default = 80): int
	{
		if ($this->width === null) {
			static::generateDimensions();
		}
		
		return $this->width ?: $default;
	}
	
	/**
	 * Attempts to determine the height of the viewable CLI window.
	 */
	public function getHeight(int $default = 32): int
	{
		if ($this->height === null) {
			$this->generateDimensions();
		}
		
		return $this->height ?: $default;
	}
	
	/**
	 * Clears the screen of output
	 * @return void
	 */
	public function clearScreen(): void
	{
		if (!$this->isInteractive()) {
			return; // do nothing for non-interactive outputs (logs, buffers, etc.)
		}
		
		// Unix systems, and Windows with VT100 Terminal support (i.e. Win10)
		// can handle CSI sequences. For lower than Win10 we just shove in 40 new lines.
		$this->isWindows() && ! $this->streamSupports('sapi_windows_vt100_support', STDOUT)
			? $this->newLine(40)
			: $this->writeNoLine("\033[H\033[2J");
	}
	
	/**
	 * Nice progress status can be shown using this method. To stop showing the progress,
	 * call this method with null as $msg and false as $running. To show different icon in
	 * the progress, use {@link setProgressIcon()}.
	 *
	 * @param ?string $msg The text to be shown when showing progress
	 * @param bool $running To indicate whether the progress is done or running
	 * @param bool $success Progress can be marked either successful or not. When marked true,
	 * then the progress finish will be shown in green text with tick sign. Otherwise, will be
	 * shown in red text with a cross sign.
	 * @param bool $sticky Whether to keep the progress message each time this method is called
	 * @param bool $colorize Indicates whether any successful/fail message uses color or normal text
	 * @param bool $icon Indicates whether to show any icon with the progress message
	 * */
	public function progress(?string $msg, bool $running = true, bool $success = true, bool $sticky = false, bool $colorize = true, bool $icon = true): void
	{
		if (is_null($msg)) {
			$running = false;
		}
		
		// Non-interactive outputs should not receive ANSI overwrite/spinner junk
		if (!$this->isInteractive()) {
			if ($msg === null) return;
			
			if ($running) {
				$this->write($msg . "...");
			} else {
				$this->write($msg);
			}
			return;
		}
		
		static $iconIndex = -1;
		if ($iconIndex == count($this->icon)-1) $iconIndex = -1;
		$i = $this->icon[++$iconIndex];
		
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
		
		$this->writeNoLine($output);
	}
	
	/**
	 * Displays a progress bar. You must call it repeatedly
	 * to update it. Set $thisStep = false to erase the progress bar.
	 *
	 * @param bool|int $thisStep
	 * @param int $totalSteps
	 * @return void
	 */
	public function progressBar(bool|int $thisStep = 1, int $totalSteps = 10): void
	{
		if (!$this->isInteractive()) {
			return;
		}
		
		if ($thisStep !== false) {
			// Don't allow div by zero or negative numbers....
			$thisStep   = abs($thisStep);
			$totalSteps = max($totalSteps, 1);
			
			$percent = (int) (($thisStep / $totalSteps) * 100);
			$step    = (int) round($percent / 5);
			
			// Write the progress bar
			$this->writeNoLine("\r\033[K[\033[32m" . str_repeat('*', $step) . str_repeat('-', 20 - $step) . "\033[0m]");
			
			// Textual representation...
			$this->writeNoLine(sprintf(' %3d%%', $percent));
		} else {
			$this->writeNoLine("\r\033[K");
			$this->writeNoLine("\007");
		}
	}
	
	/**
	 * Sets progress icons style for progress.
	 *
	 * @param ProgressIcon $icon progress icon style constant
	 * */
	public function setProgressIcon(ProgressIcon $icon): void {
		$this->icon = match ($icon) {
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
	 * Takes a string and writes it to the command line, wrapping to a maximum
	 * width. If no maximum width is specified, will wrap to the window's max
	 * width.
	 *
	 * If an int is passed into $pad_left, then all strings after the first
	 * will pad with that many spaces to the left. Useful when printing
	 * short descriptions that need to start on an existing line.
	 */
	public function wrap(?string $string = null, int $max = 0, int $padLeft = 0): string
	{
		if (empty($string)) {
			return '';
		}
		
		if ($max === 0) {
			$max = $this->getWidth();
		}
		
		if ($this->getWidth() < $max) {
			$max = $this->getWidth();
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
	 * Outputs a string to the CLI without any surrounding newlines.
	 * Useful for showing repeating elements on a single line.
	 *
	 * @param string $text
	 * @param string|null $foreground
	 * @param string|null $background
	 * @return void
	 */
	public function print(string $text = '', ?string $foreground = null, ?string $background = null): void {
		if ($foreground || $background) {
			$text = $this->color($text, $foreground, $background);
		}
		
		$this->writeNoLine($text);
	}
	
	/**
	 * Enter a number of empty lines
	 *
	 * @param int $num
	 * @return void
	 */
	public function newLine(int $num = 1): void
	{
		// Do it once or more, write with empty string gives us a new line
		for ($i = 0; $i < $num; $i++) {
			$this->write();
		}
	}
	
	/**
	 * Get the number of characters in string having encoded characters
	 * and ignores styles set by the {@link color()} function
	 */
	public static function strlen(?string $string): int	{
		if ($string === null) {
			return 0;
		}
		
		$string = strtr($string, ["\033[4m" => '', "\033[0m" => '']);
		
		return mb_strwidth($string);
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
	public function color(string $text, string $foreground, ?string $background = null, ?string $format = null): string
	{
		if ($text === '' || !$this->hasColorSupport()) {
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
				return $newText . $this->getColoredText($text, $foreground, $background, $format);
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
					$newText .= $this->getColoredText($chunk, $foreground, $background, $format);
				}
				
				if (isset($coloredStrings[$i])) {
					$newText .= $coloredStrings[$i];
				}
			}
		} else {
			$newText .= $this->getColoredText($text, $foreground, $background, $format);
		}
		
		return $newText;
	}
	
	private function getColoredText(string $text, string $foreground, ?string $background, ?string $format): string {
		$string = "\033[" . ($this->foreground_colors[$foreground] ?? $foreground) . 'm';
		
		if ($background !== null) {
			$string .= "\033[" . ($this->background_colors[$background] ?? $background) . 'm';
		}
		
		if ($format === 'underline') {
			$string .= "\033[4m";
		}
		
		return $string . $text . "\033[0m";
	}
	
	public function streamSupports(string $function, $resource): bool {
		return function_exists($function) && @$function($resource);
	}
	
	private function hasColorSupport(): bool
	{
		if (!$this->isInteractive
			() || !Util::isCLI()) {
			return false;
		}
		
		if (isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false) {
			return false;
		}
		
		if (getenv('TERM_PROGRAM') === 'Hyper') {
			return true;
		}
		
		if (self::isWindows()) {
			return $this->streamSupports('sapi_windows_vt100_support', STDOUT)
				|| isset($_SERVER['ANSICON'])
				|| getenv('ANSICON') !== false
				|| getenv('ConEmuANSI') === 'ON'
				|| getenv('TERM') === 'xterm';
		}
		
		return $this->streamSupports('stream_isatty', STDOUT);
	}
	
	/**
	 * Checks if the OS is windows
	 *
	 * @return bool true if the OS is windows.
	 * */
	public function isWindows(): bool
	{
		return PHP_OS_FAMILY === "Windows";
	}
	
	/**
	 * Bolds text in console
	 * @param mixed $text the text
	 * @return string formatted text
	 * */
	public function bold(mixed $text): string
	{
		return $this->style((string)$text, '1');
	}
	
	/**
	 * Add italic sequence characters in text
	 * @param mixed $text the text
	 * @return string formatted text
	 * */
	public function italic(mixed $text): string
	{
		return $this->style((string)$text, '3');
	}
	
	/**
	 * Underlines text in console
	 * @param mixed $text the text
	 * @return string formatted text
	 * */
	public function underline(mixed $text): string
	{
		return $this->style((string)$text, '4');
	}
	
	/**
	 * Strikes through text
	 * @param mixed $text the text
	 * @return string formatted text
	 * */
	public function strikeThrough(mixed $text): string
	{
		return $this->style((string)$text, '9');
	}
	
	/*
	 * Generic ANSI style wrapper.
	 * Code can be '1' (bold), '3' (italic), '4' (underline), '9' (strike), etc.
	 */
	private function style(string $text, string $code): string
	{
		if ($text === '' || !$this->hasColorSupport()) {
			return $text;
		}
		
		return "\033[{$code}m$text\033[0m";
	}
	
}