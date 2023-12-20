<?php

namespace hati\cli;

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
 * {@link addFlag()} to add options/flag.
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

	private array $userInputMap = [];
	private array $flags = [];
	private array $options = [];

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
	 * Internal helper method, to show error and exit with error code.
	 *
	 * @param string $msg The error message
	 * @param int $code The error exit code
	 * */
	#[NoReturn]
	private static function error(string $msg, int $code = 1): void {
		CLI::error($msg);
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
	 * Adds the option specified by the array to the CLI. An option can have
	 * various fields. By default an options is mandatory, however it can be
	 * marked optional using required field. Short name is the mandatory field.
	 * Marking short/long name with double dashes is not necessary. <br>
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
	protected function addOption(array $option): void {
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
	protected function addFlag(array $flag): void {
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
	 * */
	#[NoReturn]
	private function errInvalidIndicator(string $type, string $value, array $arr): void {
		CLI::error("Invalid $type: $value");
		$short = array_keys($arr);
		$long = array_values($arr);

		array_unshift($short, 'Short form');
		array_unshift($long, 'Long form');

		$str = Text::table2D($short, $long, return: true);
		CLI::write("List of valid {$type}s:");
		CLI::write($str);
		exit(2);
	}

	/**
	 * For a key, this method figures out the short name for it. If long name was passed-in
	 * it then tries to figure out the short name for it. If short name was passed-in, it
	 * just returns it.
	 *
	 * @param string $key The name
	 * @param array $arr The array to be used for short-to-long name mapping
	 * @return ?string the short name for the key. If the can't be figured out
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
	 * */
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
	 * */
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
	 * */
	private function namedArgs(array $args): void {
		$count = count($args);
		$i = 0;
		while(true) {
			if ($i >= $count) break;

			$x = $args[$i];

			if (!str_starts_with($x, '--')) {
				self::error("Argument name with -- was expected", 2);
			}

			// Is argument valid?
			$this -> validateArgument($x);

			$v = $args[++$i] ?? null;
			if (empty($v) || str_starts_with($v, '-')) {
				self::error("Value missing for argument $x", 2);
			}

			// Translate the name
			$shortName = $this -> getShortname($x, $this -> shortLongArg);
			$this -> options[$shortName] = $v;

			// Store what key user used for this arg!
			$this -> userInputMap[$shortName] = $x;

			$i++;
		}
	}

	/**
	 * Inputs are checked as positional arguments. It checks whether
	 * any unnecessary argument value was passed in with the command.
	 *
	 * @param array $args The input array
	 * */
	private function positionalArgs(array $args): void {
		$keys = array_keys($this -> allowedOptions);
		$argCount = count($args);
		for ($i = 0; $i < $argCount; $i++) {
			$v = $args[$i];

			if (empty($keys[$i])) {
				CLI::error("Unnecessary value $v was passed");
				exit(2);
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
	 * */
	private function getHelp(string $type, ?string $key): ?array {

		$array = $type == 'option' ? $this -> allowedOptions : $this -> allowedFlags;
		$shortArr = $type == 'option' ? $this -> shortLongArg : $this -> shortLongFlag;

		if (empty($array)) {

			if (empty($key)) {
				echo CLI::color("This CLI tool doesn't take in any $type", 'yellow');
				exit(0);
			}

			return [[], []];
		}

		$titles = [ CLI::color(ucfirst($type). "s", 'green') ];
		$des = [''];

		if (!empty($key)) {
			$shortName = $this -> getShortname($key, $shortArr);

			if (is_null($shortName)) {
				CLI::error("Unknown $type: $key");
				exit(2);
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
				$des[] = $v['description'];

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
	 * */
	#[NoReturn]
	private function showHelp(array $args): void {

		$what = $args[1] ?? null;
		if (!is_null($what)) {
			if (str_starts_with($what, '--')) {
				$data = $this -> getHelp('option', $what);
			} elseif (str_starts_with($what, '-')) {
				$data = $this -> getHelp('flag', $what);
			} elseif (str_starts_with($what, 'flag')) {
				$data = $this -> getHelp('flag', null);
			} elseif (str_starts_with($what, 'option')) {
				$data = $this -> getHelp('option', null);
			} else {
				CLI::error("Invalid help argument: $what");
				exit(2);
			}

			Text::table2D($data[0], $data[1], 4, 100);
			exit(0);
		}

		echo CLI::color(CLI::bold($this -> name . " v" . $this -> version, true), 'yellow');
		echo CLI::wrap("$this->description\n", 80);

		$titles[] = '';
		$des[] = '';

		// show option
		$data = $this -> getHelp('option', null);
		$titles = array_merge($titles, $data[0]);
		$des = array_merge($des, $data[1]);

		$titles[] = '';
		$des[] = '';

		// show flags
		$data = $this -> getHelp('flag', null);
		$titles = array_merge($titles, $data[0]);
		$des = array_merge($des, $data[1]);

		Text::table2D($titles, $des, 4, 100);
		exit(0);
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
	 * */
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

		if ($namedCLI) $cli -> namedArgs($args);
		else $cli -> positionalArgs($args);

		// Have we got all required ones?
		$userInputKeys = array_keys($cli -> userInputMap);
		foreach ($cli -> required as $req) {
			if (!in_array($req, $userInputKeys)) {
				self::error("Missing required argument: $req ", 2);
			}
		}

		// Filter all the input
		foreach ($cli -> options as $key => $v) {
			$option = $cli -> allowedOptions[$key];
			$usrKey = $cli -> userInputMap[$key];
			$type = $option['type'];

			if ($type == 'int') {
				$output = Filter::int($v);
				if(!Filter::ok($output)) {
					self::error("$usrKey must be an integer number");
				}
			} elseif ($type == 'float') {
				$output = Filter::float($v);
				if(!Filter::ok($output)) {
					self::error("$usrKey must be of type float");
				}
			} else {
				$output = Filter::string($v);
				if(!Filter::ok($output)) {
					self::error("$usrKey must be of type string");
				}
			}

			// Check if it is one of valid options
			if (!empty($option['options']) && !in_array($output,  $option['options'])) {
				self::error("$usrKey must be of: " . Arr::strList($option['options']));
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
	 * It tell whether a specified flag was set by the user in the command.
	 *
	 * @param string $key The flag
	 * @return bool true if the flag was set; false otherwise
	 * */
	protected function flagSet(string $key): bool {		return in_array($key, $this -> flags);
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
	 * @param HatiCLI $cli Instance of HatiCLi implementation
	 * */
	public static function start(HatiCLI $cli): void {
		global $argv;

		// Initialize the cli
		$cli -> describeCLI();
		$cli -> setup();

		// validate the cli arguments & flags
		array_shift($argv);
		self::validate($argv, $cli);

		// Fire the CLI
		$cli -> run($cli -> options);
	}

}