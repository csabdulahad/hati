<?php

namespace hati\cli;

use Exception;
use hati\filter\Filter;
use hati\util\Arr;
use hati\util\Text;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

/**
 * Any CLI tool can be built with HatiCLI class. This abstract class captures all the boilerplate
 * CLI coding such as validating input and outputting error info, showing command help doc etc.<br>
 *
 * Any implementation of this class needs to implement the following methods:
 * - {@link describeCLI()} : set information such as name, description & version about the CLI
 * - {@link setup()} : add options or flags to take for the CLI from the user
 * - {@link run()} : CLI application code
 *
 * To fetch various help doc, execute the CLI with -? flag.<br>
 *
 * Options are inputted with double dash [--] and flags are marked with single dash [-]. Flags are
 * optional. Options can also be marked optional when adding the options. Use {@link addOption()} &
 * {@link addFlag()} to add options/flag.<br>
 *
 * As a fix to v5.1.0, HatiCLI implementation should not use built-in exit function directly. It is
 * because any HatiCLI tool can be now called using {@link HatiCLI::call()} method. Any script
 * calling an HatiCLI tool would like to continue executing even though the CLI terminates successfully
 * or not. It is recommended to use {@link HatiCLI::error()} & {@link HatiCLI::exit()} instead.<br>
 *
 * Calling these functions, behind the scene {@link HatiCLIThrowing} runtime exception is thrown which is
 * supposed to be picked by {@link HatiCLI::call()} method. Catching that exception will not report exit or
 * error to HatCLI thus not getting unexpected behavior. If you need to catch exception, then make sure to
 * make the first call to the {@link HatiCLI::escalate()} in catch block so that HatiCLI can handle the
 * exception to simulate the error/exit scenarios intended.
 *
 * @since 5.0.0
 * */

abstract class HatiCLI {

	protected string $name = 'Unnamed CLI';
	protected string $description = 'Mysterious CLI, BE CAREFUL!';
	protected string $version = '1.0.0';

	private array $shortLongFlag = [];
	private array $shortLongArg = [];
	private array $required = [];
	private array $optional = [];

	private array $allowedOptions = [];
	private array $allowedFlags = [];
	private array $allowedExtra = [];

	private array $userInputMap = [];
	private array $flags = [];
	
	private array $options = [];
	private array $extra = [];
	
	private bool $callMode = false;
	
	// Indicates whether the CLI is being loaded so that we shouldn't invoke the start method!
	private static bool $loading = false;

	/**
	 * For an argument value, it can calculate whether it is a flag.
	 *
	 * @param string $v The argument value
	 * @return bool true if the argument is a flag, starts with only one dash;
	 * false otherwise
	 * */
	private static function isFlag(string $v): bool {
		return !str_starts_with($v, '--') && str_starts_with($v, '-');
	}

	/**
	 * HatiCLI can be called from within any php script. This method should only be called
	 * from catch block to allow HatiCLI handle the CLI output in a consistent way so that
	 * an CLI tool implemented using HatiCLI can be terminated as expecting by the calling
	 * script.
	 *
	 * @throws HatiCLIThrowing|Exception rethrows the {@link HatiCLIThrowing} exception up the stack
	 * @since 5.1.0
	 * */
	public static function escalate(Exception $e): void {
		if ($e instanceof HatiCLIThrowing)
			throw $e;
	}
	
	/**
	 * Internal helper method, to show error and exit with error code.
	 *
	 * @param string $msg The error message
	 * @param int $code The error exit code
	 *
	 * @throws HatiCLIThrowing
	 */
	#[NoReturn]
	private static function err(string $msg, int $code, bool $callMode): void {
		if ($callMode) {
			throw new HatiCLIThrowing($msg, $code);
		}
		
		CLI::error($msg);
		exit($code);
	}
	
	/**
	 * HatiCLI implementation should call this method to report error to the user.
	 * This method takes care of the CLI termination properly if called by another
	 * script.<br>
	 * It exits the CLI on invocation. Remember to call {@link HatiCLI::escalate()}
	 * in catch block, if error is being reported from within tht try block.
	 *
	 * @since 5.1.0
	 * */
	public function error(string $msg, int $code = 1): void {
		if ($this -> callMode) {
			throw new HatiCLIThrowing($msg, $code);
		}
		
		CLI::error($msg);
		exit($code);
	}
	
	/**
	 * Any HatiCLI implementation should call this method to exit. Exiting using this
	 * method will make sure that the CLI doesn't exit the caller script which would
	 * be unexpected.<br>
	 *
	 * Remember to call {@link HatiCLI::escalate()} in catch block, if exit is being
	 * done from within any try-catch block.
	 *
	 * @since 5.1.0
	 * */
	public function exit($code = 0): void {
		if ($this -> callMode) {
			throw new HatiCLIThrowing('', $code);
		}
		
		exit($code);
	}

	/**
	 * For an options, it extracts the short & long name. Short name is
	 * a mandatory field. It removes any dash used before short or long
	 * name by the option.
	 *
	 * @param array $option The option containing various info
	 * @returns array containing short & long name. For missing long name
	 * empty string value is used.
	 *
	 * @throws RuntimeException if the short name is missing
	 * */
	private function extractNames(array &$option): array {
		$arr = [];

		if (empty($option['shortName'])) {
			throw new RuntimeException("Option must have a short name");
		}

		$shortName = $option['shortName'];
		if (str_starts_with($shortName, '-')) {
			$shortName = str_replace('-', '', $shortName);
		}

		$arr[] = $shortName;
		unset($option['shortName']);

		$longName = $option['longName'] ?? '';
		$arr[] = $longName;
		if (!empty($longName))
			unset($option['longName']);

		return $arr;
	}

	/**
	 * Adds the option specified by the array to the CLI. Options can be built
	 * and added using {@link optionBuilder()} method which eases the process.
	 *
	 * An option can have various fields. By default, an options is mandatory,
	 * however it can be marked optional using required field. Short name is the
	 * mandatory field.Marking short/long name with double dashes is not necessary.
	 * <br>
	 * An option can be validated
	 * - string : use str as type. It is default type for option.
	 * - integer : use int as type
	 * - float : use float as type
	 * Below is a fully fledged example of an option:
	 * <code>
	 * $cli -> addOption([
	 *		'shortName' => 'cf',
	 *		'longName' => 'from',
	 *		'type' => 'str',
	 *		'default' => 'bd',
	 *		'options' => ['uk', 'bd', 'usa'],
	 *		'required' => false,
	 *		'description' => 'An option which specifies the country it is from'
	 * ]);
	 * </code>
	 *
	 * @param array $option The option
	 * @throws RuntimeException if the short name is missing
	 * */
	public function addOption(array $option): void {
		// Extract names [short & long names]
		$names = $this -> extractNames($option);
		$shortName = "--$names[0]";

		$longName = $names[1];
		$longName = empty($longName) ? '' : "--$longName";
		$this -> shortLongArg[$shortName] = $longName;

		// add missing required field
		if (!isset($option['required'])) {
			$option['required'] = true;
		} else {
			$this -> optional[$shortName] = $option['default'] ?? null;
		}

		// Is this option required?
		if ($option['required'])
			$this -> required[] = $shortName;

		// What type of option is it ?
		if (!isset($option['type']))
			$option['type'] = 'str';

		// Is it allowed to take in extra values?
		if (isset($option['allow_extra']))
			$this->allowedExtra[$shortName] = $option['allow_extra'];
		
		// Save this option
		$option['value'] = null;
		$this -> allowedOptions[$shortName] = $option;
	}

	/**
	 * Like, option various flags can be added to the CLI. All the
	 * flags are optional. A flag can have four fields. Short name
	 * is the must one. Marking short/long name with single a dash
	 * is not necessary. <br>An example of flag:
	 *  <code>
	 *  $cli -> addFlag([
	 *        'shortName' => 'c',
	 *        'longName' => 'currency',
	 *        'description' => 'Indicates whether the rate should have currency symbol'
	 *  ]);
	 *  </code>
	 *
	 * @param array $flag The flag
	 * @throws RuntimeException if the short name is missing
	 * */
	public function addFlag(array $flag): void {
		$names = $this -> extractNames($flag);
		$shortName = "-$names[0]";

		$longName = $names[1] ?? '';
		$longName = empty($longName) ? '' : "-$longName";

		$this->shortLongFlag[$shortName] = $longName;

		// Flags are always optional!
		$flag['required'] = false;

		$this -> allowedFlags[$shortName] = $flag;
	}

	/**
	 * Helper method to add options to CLI more easily.
	 *
	 * @param string $shortName short name for the option
	 * @param string $longName long name for the option. Default is empty string
	 * @return CLIOptionBuilder builder object to configure the option further
	 * */
	protected function optionBuilder(string $shortName, string $longName = ''): CLIOptionBuilder {
		return new CLIOptionBuilder($this, $shortName, $longName);
	}
	
	/**
	 * Helper method to add flags to CLI more easily
	 * @param string $shortName short name for the flag
	 * @param string $longName long name for the flag. Default is empty string.
	 * @return CLIFlagBuilder builder object to configure the flag further
	 * */
	protected function flagBuilder(string $shortName, string $longName = ''): CLIFlagBuilder {
		return new CLIFlagBuilder($this, $shortName, $longName);
	}
	
	/**
	 * For arguments, this method figures out whether it was name-value
	 * arguments styled or not
	 *
	 * @param array $args The arguments to the CLI tool
	 * @return bool true if named arguments style use; false to positional style
	 * */
	private static function namedCLI(array $args): bool {
		$namedCLI = false;
		foreach ($args as $arg) {
			$firstChar = $arg[0] ?? '';
			$secondChar = $arg[1] ?? '';

			if ($firstChar == '-' && $secondChar != '-') continue;

			$namedCLI = str_starts_with($arg, '--');
			break;
		}
		return $namedCLI;
	}

	/**
	 * Internal helper method to display the helpful information for invalid
	 * options/flags to correct them.
	 *
	 * @param string $type Error found for the type of option/flag
	 * @param string $value The value user got wrong
	 * @param array $arr The short-to-long name map array
	 *
	 * @throws HatiCLIThrowing
	 */
	#[NoReturn]
	private function errInvalidIndicator(string $type, string $value, array $arr): void {
		CLI::error("Invalid $type: $value");
		$short = array_keys($arr);
		$long = array_values($arr);

		array_unshift($short, 'Short form');
		array_unshift($long, 'Long form');
		
		if (empty($arr)) {
			CLI::write('No flag suggestions found');
			$this -> exit(2);
		}

		$str = "List of valid {$type}s:\n";
		$str .= Text::table2D($short, $long, return: true);
		CLI::write($str);
		
		$this -> exit(2);
	}

	/**
	 * For a key, this method figures out the short name for it. If long name was passed-in
	 * it then tries to figure out the short name for it. If short name was passed-in, it
	 * just returns it.
	 *
	 * @param string $key The name
	 * @param array $arr The array to be used for short-to-long name mapping
	 * @return ?string the short name for the key. If they can't be figured out
	 * it returns null
	 * */
	private function getShortname(string $key, array $arr): ?string {
		if (array_key_exists($key, $arr)) return $key;

		foreach ($arr as $s => $l) {
			if ($l == $key) return $s;
		}

		return null;
	}

	/**
	 * For a flag value in the user input, it checks if it is a legitimate flag.
	 *
	 * @param string $value The flag value starts with a single dash
	 *
	 * @throws HatiCLIThrowing
	 */
	private function validateFlag(string $value): void {
		$valid = array_key_exists($value, $this -> shortLongFlag);

		if (!$valid)
			$valid =  in_array($value, array_values($this -> shortLongFlag));

		if (!$valid) $this -> errInvalidIndicator('flag', $value, $this -> shortLongFlag);

		$shortName = $this->getShortname($value, $this->shortLongFlag);
		$this -> flags[] = $shortName;

		// Store what key user used!
		$this -> userInputMap[$shortName] = $value;
	}

	/**
	 * For a key, it checks if it is a registered option key.
	 * If the key is not valid, it exits with code 2 and error
	 * message.
	 *
	 * @param string $value The key is to be checked
	 *
	 * @throws HatiCLIThrowing
	 */
	private function validateArgument(string $value): void {
		$valid = array_key_exists($value, $this -> shortLongArg);

		if (!$valid)
			$valid =  in_array($value, array_values($this -> shortLongArg));

		if (!$valid)  $this -> errInvalidIndicator('option', $value, $this -> shortLongArg);
	}

	/**
	 * Inputs are checked as named arguments.  It checks whether the general
	 * command syntax are followed by the input such as value after -- dashed
	 * argument name, whether the argument name is allowed etc.
	 *
	 * @param array $args The input array
	 *
	 * @throws HatiCLIThrowing
	 */
	private function namedArgs(array $args, bool $callMode): void {
		if (empty($args)) return;

		$value = false;
		$shortName = '';

		for ($j = 0; $j < count($args); $j++) {
			$var = $args[$j];

			if (!$value && str_starts_with($var, '--')) {
				if (!isset($args[$j+1]) || empty($args[$j+1]) || str_starts_with($args[$j+1], '--')) {
					self::err("Value missing for argument $var", 2, $callMode);
				}

				// Translate the name
				$shortName = $this->getShortname($var, $this->shortLongArg);

				// Store what key user used for this arg!
				$this->userInputMap[$shortName] = $var;
				
				// Go to next iteration for collecting its value!
				$value = true;
				continue;
			}
			
			if (!$value && !str_starts_with($var, '--')) {
				self::err("Argument name with -- was expected", 2, $callMode);
			}
			
			// Named argument's value
			$this->options[$shortName] = $var;
			
			// Check if this option is allowed to take extras
			$allowedExtra = $this->allowedExtra[$shortName] ?? true;

			if (!$allowedExtra) {
				if (isset($args[$j+1]) && !empty($args[$j+1]) && !str_starts_with($args[$j+1], '--')) {
					self::err("Option $shortName doesn't take more than one value", 2, $callMode);
				}
			}
			
			// Collect additional extras values for this arg!
			$skip = 1;
			while (isset($args[$j+$skip]) && !empty($args[$j+$skip]) && !str_starts_with($args[$j+$skip], '--')) {
				$this->extra[$shortName][] = $args[$j+$skip];
				$skip ++;
			}
			
			$j += $skip - 1;
			$value = false;
		}
	}

	/**
	 * Inputs are checked as positional arguments. It checks whether
	 * any unnecessary argument value was passed in with the command.
	 *
	 * @param array $args The input array
	 *
	 * @throws HatiCLIThrowing
	 */
	private function positionalArgs(array $args): void {
		$keys = array_keys($this -> allowedOptions);
		$argCount = count($args);
		for ($i = 0; $i < $argCount; $i++) {
			$v = $args[$i];
			
			if (empty($keys[$i])) {
				$this -> error("Unnecessary value $v was passed", 2);
			}

			$shortName = $keys[$i];
			$this -> options[$shortName] = $v;

			// Store what key user used for this arg!
			$this -> userInputMap[$shortName] = $v;
		}
	}

	/**
	 * Gets help for a specific type [such as options or flags] for a
	 * key value. The key value could be one of the following: flag,
	 * option, --ANY_OPTION, -FLAG.
	 *
	 * This then builds up the related help doc for the user to tell
	 * more about the tool.
	 *
	 * @param string $type one of the following: option, flag
	 * @param ?string $key any specific topic about the help such as dashed
	 * option or flag, or all the options [as option], or all the flags [as flags]
	 * @returns ?array 2d array, first one for titles, and second one for the
	 * description. If the $key is null, then complete help documentation is returned.
	 *
	 * @throws HatiCLIThrowing
	 */
	private function getHelp(string $type, ?string $key): ?array {

		$array = $type == 'option' ? $this -> allowedOptions : $this -> allowedFlags;
		$shortArr = $type == 'option' ? $this -> shortLongArg : $this -> shortLongFlag;

		/*
		 * If there is no option/flag for the CLI then returns empty array
		 * to indicate that.
		 * */
		if (empty($array)) {
			return [[], []];
		}

		$titles = [ CLI::color(ucfirst($type). "s", 'green') ];
		$des = [''];

		if (!empty($key)) {
			$shortName = $this -> getShortname($key, $shortArr);

			if (is_null($shortName)) {
				$this -> error("Unknown $type: $key", 2);
			}

			$opt = $array[$shortName];
			$longName = $shortArr[$shortName];
			$longName = empty($longName) ? '' : "| $longName";
			$star = $opt['required'] ? '* ' : '  ';
			$titles[] = "$star   $shortName $longName";
			$des[] = "  {$opt['description']}";

		} else {
			$count = 0;
			foreach ($array as $opt => $v) {
				$count ++;

				$count = $type == 'option' ? "#$count" : ' ';
				$longName = $shortArr[$opt];
				$longName = empty($longName) ? '' :  "| $longName";
				$star = $v['required'] ? '* ' : '  ';
				$titles[] = "$star  $opt $longName";
				$des[] = $v['description'] ?? '';

				if (!empty($v['options'])) {
					$str = "Options: " . Arr::strList($v['options']);
					$titles[] = '';
					$des[] = $str;
				}
			}
		}

		return [$titles, $des];
	}

	/**
	 * Looks into the arguments array provided the user in the command and
	 * shows information as the user wants.
	 *
	 * @param array $args The help argument input from the user
	 *
	 * @throws HatiCLIThrowing
	 */
	#[NoReturn]
	private function showHelp(array $args): void {

		$what = $args[1] ?? null;
		if (!is_null($what)) {
			if (str_starts_with($what, '--')) {
				$data = $this -> getHelp('option', $what);
			} elseif (str_starts_with($what, '-')) {
				$data = $this -> getHelp('flag', $what);
				if (count($data[0]) == 0) {
					echo CLI::color("This CLI tool doesn't take in any flag", 'yellow');
					$this -> exit();
				}
			} elseif (str_starts_with($what, 'flag')) {
				$data = $this -> getHelp('flag', null);
				if (count($data[0]) == 0) {
					echo CLI::color("This CLI tool doesn't take in any flag", 'yellow');
					$this -> exit();
				}
			} elseif (str_starts_with($what, 'option')) {
				$data = $this -> getHelp('option', null);
			} else {
				$this -> error("Invalid help argument: $what", 2);
			}

			Text::table2D($data[0], $data[1], 4, 100);
			$this -> exit();
		}

		echo CLI::color(CLI::bold($this -> name . " v" . $this -> version, true), 'yellow');
		echo CLI::wrap("$this->description", 80);
		
		$titles = [];
		$des = [];

		// show option
		$data = $this -> getHelp('option', null);
		
		$hasOption = !empty($data[0]);
		if ($hasOption) {
			echo "\n";
			$titles = array_merge([''], $data[0]);
			$des = array_merge([''], $data[1]);
		}

		// show flags
		$data = $this -> getHelp('flag', null);
		
		if (!empty($data[0])) {
			if (!$hasOption) {
				echo "\n\n";
			}
			
			$titles = array_merge($titles, $data[0]);
			$des = array_merge($des, $data[1]);
		}
		
		if (!empty($titles)) {
			Text::table2D($titles, $des, 4, 100);
		}
		
		$this -> exit();
	}

	/**
	 * The main validator. It checks if there is any help wanted and route to
	 * different function to show as asked. It then determines whether it was
	 * named / positional argument style. Further, it checks for exact options
	 * & flags names to match allowed options/flags.<br>
	 * After all of this, it makes sure all the required inputs were provided
	 * by the user and their data type matches as defined by the {@link HatiCLI::setup()}
	 * method. Passing all the stages successfully, it invokes the {@link HatiCLI::run()}
	 * with the associative argument array so that the CLI program start for
	 * real!
	 *
	 * @param array $args The arguments to the CLI
	 * @param HatiCLI $cli An implementation of HatiCLI
	 *
	 * @throws HatiCLIThrowing
	 */
	private static function validate(array $args, HatiCLI $cli): void {
		$count = count($args);

		// Do you need help?
		if (!empty($args) && $args[0] == '-?') {
			$cli -> showHelp($args);
		}

		// Collect all the flags
		for ($i = 0; $i < $count; $i++) {
			$flag = $args[$i];
			if (!self::isFlag($flag)) continue;

			$cli -> validateFlag($flag);
			unset($args[$i]);
		}

		// sort the array index ! stupid php thing!
		$args = array_values($args);

		// Figure out what type of CLI arguments were passed
		$namedCLI = self::namedCLI($args);

		if ($namedCLI) $cli -> namedArgs($args, $cli -> callMode);
		else $cli -> positionalArgs($args);

		// Have we got all required ones?
		$userInputKeys = array_keys($cli -> userInputMap);
		foreach ($cli -> required as $req) {
			if (!in_array($req, $userInputKeys)) {
				self::err("Missing required argument: $req ", 2, $cli -> callMode);
			}
		}

		// Filter all the input
		foreach ($cli -> options as $key => $v) {
			$option = $cli -> allowedOptions[$key];
			$usrKey = $cli -> userInputMap[$key];
			$type = $option['type'];
			
			$msgByKey = str_starts_with($usrKey, '--');

			if ($type == 'int') {
				$output = Filter::checkInt($v);
				if(!Filter::isOK($output)) {
					if ($msgByKey) {
						self::err("$usrKey must be an integer number", 1, $cli -> callMode);
					} else {
						$str = CLI::color("Invalid value: $usrKey\n", 'red');
						$str .= CLI::color('It must be an integer.', 'yellow');
						
						self::err($str, 1, $cli -> callMode);
					}
				}
			} elseif ($type == 'float') {
				$output = Filter::checkFloat($v);
				if(!Filter::isOK($output)) {
					if ($msgByKey) {
						self::err("$usrKey must be a float", 1, $cli -> callMode);
					} else {
						$str = CLI::color("Invalid value: $usrKey\n", 'red');
						$str .= CLI::color('It must be a float.', 'yellow');
						
						self::err($str, 1, $cli -> callMode);
					}
				}
			} else {
				$output = Filter::checkString($v);
				if(!Filter::isOK($output)) {
					if ($msgByKey) {
						self::err("$usrKey must a string", 1, $cli -> callMode);
					} else {
						$str = CLI::color("Invalid value: $usrKey\n", 'red');
						$str .= CLI::color('It must be a string.', 'yellow');
						
						self::err($str, 1, $cli -> callMode);
					}
				}
			}

			// Check if it is one of valid options
			if (!empty($option['options']) && !in_array($output,  $option['options'])) {
				$errMsg = $msgByKey ? "Invalid value given for $usrKey" : "Invalid value: $usrKey";
				$errMsg .= "\n";
				
				$guideMsg = "Allowed values: " . Arr::strList($option['options']);
				
				$str = CLI::color($errMsg, 'red');
				$str .= CLI::color($guideMsg, 'yellow');
				
				self::err($str, 1, $cli -> callMode);
			}

			$cli -> options[$key] = $output;
		}

		// Add missing default option values
		foreach ($cli -> optional as $k => $v) {
			if (array_key_exists($k, $cli -> options)) continue;
			$cli -> options[$k] = $v;
		}
	}

	/**
	 * It tells whether a specified flag was set by the user in the command.
	 *
	 * @param string $key The flag. It can be short or long name with/without '-' prepended.
	 * @return bool true if the flag was set; false otherwise
	 * */
	protected function flagSet(string $key): bool {
		if (!str_starts_with($key, '-')) $key = "-$key";
		
		return in_array($key, $this -> flags);
	}

	/**
	 * Returns whether an option was set by the user in the command.
	 *
	 * @param string $key The option. It can be short or long name with/without '--' prepended.
	 * @return bool true if the flag was set; false otherwise
	 * */
	protected function optionSet(string $key): bool {
		if (!str_starts_with($key, '--')) $key = "--$key";
		
		$key = $this->getShortname($key, $this->shortLongArg);
		return isset($this->options[$key]);
	}
	
	/**
	 * Returns the value entered by user for specified option.
	 *
	 * @param string $key. Either short/long name with/without '--' prepended.
	 * @param mixed $default default value to be returned if the option value wasn't found in user input
	 * @return mixed value for the option provided by the user
	 * */
	protected function getOptionVal(string $key, mixed $default = null): mixed {
		if (!str_starts_with($key, '--')) $key = "--$key";
		$key = $this->getShortname($key, $this->shortLongArg);
		
		return $this->options[$key] ?? $default;
	}
	
	/**
	 * Returns the extra values entered by user for a specified option. If more than one
	 * values were passed in by the user then extra is returned as array.
	 *
	 * @param string $key. either short/long name with/without '--' prepended.
	 * @param mixed $default default value to be returned if the extra value wasn't set in user input
	 * @return mixed extra value for the option provided by the user
	 * */
	protected function getExtra(string $key, mixed $default = null): mixed {
		if (!str_starts_with($key, '--')) $key = "--$key";
		
		$key = $this->getShortname($key, $this->shortLongArg);
		
		return $this->extra[$key] ?? $default;
	}
	
	/**
	 * Make the thread sleep
	 *
	 * @param float $sec The number of seconds to sleep
	 * */
	public function sleep(float $sec): void {
		usleep($sec * 1000000);
	}
	
	/**
	 * Any HatiCLI implementation should override this method to set various
	 * helpful information to describe itself as a CLI tool. It should set
	 * three important pieces of information. They are:
	 * - {@link HatiCLI::name} : The name of the CLI tool
	 * - {@link HatiCLI::version} : The version of the tool
	 * - {@link HatiCLI::$description} : Description about the tool
	 * */
	public function describeCLI(): void {}

	/**
	 * A HatiCLI implementation must override this method to set various options and
	 * flags for the CLI command. This method is called by the {@link HatiCLI::start()}
	 * method to initialize the CLI tool. To set options & flags, use the following
	 * methods:
	 *
	 * - {@link HatiCLI::addOption()}
	 * - {@link HatiCLI::addFlag()}
	 * */
	public abstract function setup(): void;

	/**
	 * The starting point of the CLI tool derived from {@link HatiCLI}. When a user
	 * invokes the CLI tool, HatiCLI performs various arguments & flags validation
	 * as defined in the {@link HatiCLI::setup()} method.
	 *
	 * On passing the validation phase successfully, HatiCLI calls this method with
	 * the argument list the user passed-in as an associative array. Required options
	 * are always guaranteed to be in the array.
	 *
	 * To check if a particular flag was set in the invocation, use {@link HatiCLI::flagSet()}
	 * method.
	 *
	 * @param array $args The associative array containing argument name & value pair
	 * */
	public abstract function run(array $args): void;

	/**
	 * Bootstraps the CLI tool. Any implementation of HatiCLI should invoke
	 * this method to start the CLI and start validating user inputs to the
	 * CLI. This method first initializes the CLI tool by calling
	 * {@link HatiCLI::describeCLI()} method on the implementation class, then it
	 * prepares CLI options & flags by calling {@link HatiCLI::setupt()} method.<br>
	 *
	 * After all that, it starts validating the user input as per options.
	 * On successfully passing the validation phase, it calls the {@link HatCLI::run()}
	 * method on the implementation class with the validated user inputs to
	 * allow the tool execution.
	 *
	 * @param string $cliCls A class which is an instance of HatiCLI
	 * @param string|array ...$args Any predefined argument to be passed in to the CIL.
	 * If none provided then any arguments from terminal while invoking CLI will be
	 * passed in which is global variable $argv. The first item of $argv representing
	 * the script path will be removed before passing the array as arguments to the CLI.
	 */
	public static function start(string $cliCls, string|array ...$args): void {
		
		if (self::$loading) {
			self::$loading = false;
			return;
		}
		
		if (!class_exists($cliCls)) {
			CLI::error("Class $cliCls doesn't exists");
		}
		
		if (!is_subclass_of($cliCls, HatiCLI::class)) {
			CLI::error("$cliCls is an implementation of HatiCLI");
		}
		
		// Initialize the cli
		$cli = new $cliCls;
		$cli -> describeCLI();
		$cli -> setup();
		
		// validate the cli arguments & flags
		$args = Arr::varargsAsArray($args);
		if (empty($args) && !$cli -> callMode) {
			global $argv;
			array_shift($argv);
			$args = $argv;
		}
		
		self::validate($args, $cli);
		
		// Fire the CLI
		$cli -> run($cli -> options);
	}
	
	/*
	 * Fires up the HatiCLI
	 * */
	private static function kickOff(HatiCLI $cli, $args): void {
		$cli -> describeCLI();
		$cli -> setup();
		
		// Validate & fire the CLI
		self::validate($args, $cli);
		$cli -> run($cli -> options);
	}
	
	/**
	 * Any HatiCLI can be called using this method from anywhere.
	 * Just make sure that you don't make it run in an infinite loop!
	 *
	 * @param string $cliClass A FQC CLI class implemented HatiCLI
	 * @param string|array ...$args Any arguments to be passed in to the CLI
	 * @return int The exit code returned by the CLI
	 *
	 * @since 5.1.0
	 * */
	public static function call(string $cliClass, string|array ...$args): int {
		self::$loading = true;
		
		$errReporting = null;
		try {
			if (!class_exists($cliClass)) {
				/*
				 * Turn off error reporting, try to load the class
				 * */
				$errReporting = error_reporting();
				error_reporting($errReporting & ~E_WARNING);
				include $cliClass;
				error_reporting($errReporting);
				
				/*
				 * Make sure the class is resolved and an implementation of HatiCLI
				 * */
				if (!class_exists($cliClass)) {
					throw new Exception("Could resolve the class: $cliClass");
				}
				
				if (!is_subclass_of($cliClass, HatiCLI::class)) {
					throw new Exception("$cliClass is an implementation of HatiCLI");
				}
			}
			
			// We have done loading the class!
			self::$loading = false;
			
			/*
			 * Process the arguments
			 * */
			$args = Arr::varargsAsArray($args);
			
			/*
			 * Execute the CLI and return the output
			 * */
			$cli = new $cliClass;
			$cli -> callMode = true;
			
			self::kickOff($cli, $args);
			
			return 0;
		} catch (Exception $e) {
			echo $e -> getMessage();
			
			if ($e instanceof HatiCLIThrowing)
				return $e -> code;
			
			return 1;
		} finally {
			if (!is_null($errReporting)) {
				error_reporting($errReporting);
			}
		}
	}

}

/**
 * Internal exception used by HatiCLI to handle error/exit
 * function properly for cases when CLI is called from within
 * another script.
 *
 * @since 5.1.0
 * */
class HatiCLIThrowing extends RuntimeException {
	public $code = 1;
	public function __construct(string $msg, int $code) {
		parent::__construct($msg);
		$this -> code = $code;
	}
}
