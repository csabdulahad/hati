<?php

namespace hati\cli;

use hati\cli\io\BufferedIO;
use hati\cli\io\ConsoleIO;
use hati\cli\io\TeeIO;
use hati\cli\io\TerminalIO;
use hati\filter\Filter;
use hati\util\Arr;
use hati\util\Text;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;
use Throwable;

/**
 * Any CLI tool can be built with HatiCLI class. This abstract class captures all the boilerplate
 * CLI coding such as validating input and outputting error info, showing command help doc etc.<br>
 *
 * Any implementation of this class needs to implement the following methods:
 * - {@link describeCLI()} : set information such as name, description & version about the CLI
 * - {@link setup()} : add options or flags to take for the CLI from the user
 * - {@link run()} : CLI application code
 *
 * To fetch various help doc, execute the CLI with either of these flags: -?, -h, --help<br>
 *
 * HatiCLI implementation should not use built-in exit function directly. It is
 * because any HatiCLI tool can be now called using {@link invoke()} method. Any script
 * calling an HatiCLI tool would like to continue executing even though the CLI terminates successfully
 * or not. It is recommended to use {@link error()} & {@link exit()} instead.<br>
 *
 * Calling these functions, behind the scene {@link CLIExit} runtime exception is thrown which is
 * supposed to be picked by {@link invoke()} method. Catching that exception will not report exit or
 * error to HatCLI thus not getting expected behavior. If you need to catch exception, then make sure to
 * make the first call to the {@link rethrowIfExit()} in catch block so that HatiCLI can handle the
 * exception to simulate the error/exit scenarios intended.
 * */
abstract class HatiCLI
{
	// Indicates whether the CLI is being loaded so that we shouldn't invoke the start method!
	private static bool $loading = false;
	
	protected string $name = 'Unnamed CLI';
	protected string $description = 'A mysterious CLI tool!';
	protected string $version = '1.0.0';
	
	private array $flagAliasByName = [];
	private array $optAliasByName  = [];
	
	// these are built lazily!
	private ?array $flagNameByAlias = null;
	private ?array $optNameByAlias  = null;
	
	private array $required 	 = [];
	private array $optional 	 = [];
	
	private array $allowedOptions = [];
	private array $allowedFlags   = [];
	private array $allowedExtra   = [];
	
	private array $flags = [];
	private array $options = [];
	private array $extra = [];
	private array $userInputMap = [];
	
	private CLIContext $ctx;
	protected TerminalIO $io;
	
	/**
	 * Any HatiCLI implementation should override this method to set various
	 * helpful information to describe itself as a CLI tool. It should set
	 * three important pieces of information. They are:
	 * - {@link HatiCLI::name} : The name of the CLI tool
	 * - {@link HatiCLI::version} : The version of the tool
	 * - {@link HatiCLI::$description} : Description about the tool
	 * */
	protected function describeCLI(): void {}
	
	/**
	 * A HatiCLI implementation must override this method to set various options and
	 * flags for the CLI command. This method is called by the {@link HatiCLI::start()}
	 * method to initialize the CLI tool. To set options & flags, use the following
	 * methods:
	 *
	 * - {@link HatiCLI::addOption()}
	 * - {@link HatiCLI::addFlag()}
	 * */
	protected abstract function setup(CLIInputBuilder $builder): void;
	
	/**
	 * The starting point of the CLI tool derived from {@link HatiCLI}. When a user
	 * invokes the CLI tool, HatiCLI performs various arguments & flags validation
	 * as defined in the {@link HatiCLI::setup()} method.
	 *
	 * On passing the validation phase successfully, HatiCLI calls this method with
	 * the argument list the user passed-in as an associative array. Required options
	 * are always guaranteed to be in the array.
	 *
	 * To check if a particular flag was set in the invocation, use {@link isflagSet()}
	 * method.
	 *
	 * @param array $args The associative array containing argument name & value pair
	 * */
	protected abstract function run(array $args): void;
	
	private function buildAliasIndexes(): void
	{
		if ($this->flagNameByAlias === null) {
			$this->flagNameByAlias = [];
			
			foreach ($this->flagAliasByName as $name => $alias) {
				$this->flagNameByAlias[$alias] = $name;
			}
		}
		
		if ($this->optNameByAlias === null) {
			$this->optNameByAlias = [];
			
			foreach ($this->optAliasByName as $name => $alias) {
				$this->optNameByAlias[$alias] = $name;
			}
		}
	}
	
	private function isRegisteredFlagName(string $nameOrAlias): bool
	{
		$this->buildAliasIndexes();
		
		return
			isset($this->allowedFlags[$nameOrAlias]) ||
			isset($this->flagNameByAlias[$nameOrAlias]);
	}
	
	private function isRegisteredOptionName(string $nameOrAlias): bool
	{
		$this->buildAliasIndexes();
		
		return
			isset($this->allowedOptions[$nameOrAlias]) ||
			isset($this->optNameByAlias[$nameOrAlias]);
	}
	
	/**
	 * Make the thread sleep
	 *
	 * @param float $sec The number of seconds to sleep
	 * */
	protected function sleep(float $sec): void
	{
		usleep($sec * 1000000);
	}
	
	private function normalizeFlag(string $nameOrAlias): ?string
	{
		$this->buildAliasIndexes();
		
		if (isset($this->allowedFlags[$nameOrAlias])) return $nameOrAlias;
		
		return $this->flagNameByAlias[$nameOrAlias] ?? null;
	}
	
	private function normalizeOption(string $nameOrAlias): ?string
	{
		$this->buildAliasIndexes();
		
		if (isset($this->allowedOptions[$nameOrAlias])) return $nameOrAlias;
		
		return $this->optNameByAlias[$nameOrAlias] ?? null;
	}
	
	private function getName(string $key, array $allowedArr): ?string {
		if (array_key_exists("$key", $allowedArr)) return $key;
		
		foreach ($allowedArr as $s => $l) {
			if ($l['alias'] == "$key") return $s;
		}
		
		return null;
	}
	
	private static function isShortFlagBundle(string $v): bool
	{
		return preg_match('/^-[a-zA-Z]{2,}$/', $v) === 1;
	}
	
	private static function isName(string $v): bool
	{
		return str_starts_with($v, '--') && $v !== '--';
	}
	
	private static function splitLongEquals(string $v): array
	{
		$pos = strpos($v, '=');
		if ($pos === false) return [$v, null];
		
		return [substr($v, 0, $pos), substr($v, $pos + 1)];
	}
	
	/**
	 * Like, option various flags can be added to the CLI. All the
	 * flags are optional. A flag has three fields.
	 * - name
	 * - alias
	 * - description
	 *
	 * An example of flag:
	 *  <code>
	 *  $cli -> addFlag([
	 *        'name' => 'currency',
	 *        'alias' => 'c',
	 *        'description' => 'Indicates whether the rate should have currency symbol'
	 *  ]);
	 *  </code>
	 *
	 * @param array $flag The flag
	 * @throws RuntimeException if the short name is missing
	 * */
	public function addFlag(array $flag): void {
		if (empty($flag['name'])) {
			throw new RuntimeException("Flag must have a name");
		}
		
		// allow user to pass flag with dashes or no dashes
		$name = ltrim($flag['name'], '-');
		$alias = $flag['alias'] ?? null;
		
		if (!empty($alias)) {
			$alias = ltrim($alias, '-');
			
			// Optional: validate single-letter alias
			if (strlen($alias) !== 1) {
				throw new RuntimeException("Flag alias must be a single letter, got: $alias");
			}
			
			$this->flagAliasByName[$name] = $alias;
		}
		
		// Flags are always optional!
		$flag['required'] = false;
		$flag['kind'] = 'flag';
		
		$this->allowedFlags[$name] = $flag;
	}
	
	/**
	 * Adds the option specified by the array to the CLI.
	 *
	 * An option can have various fields. By default, an options is mandatory,
	 * however it can be marked optional using required field. Canonical name is
	 * mandatory field.
	 *
	 * An option can be validated
	 * - string : use str as type. It is default type for option.
	 * - integer : use int as type
	 * - float : use float as type
	 * Below is a fully fledged example of an option:
	 * <code>
	 * $cli -> addOption([
	 *		'name' => 'from',
	 *		'alias' => 'cf',
	 *		'type' => 'string',
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
		if (empty($option['name'])) {
			throw new RuntimeException("Option must have a name");
		}
		
		$name = ltrim((string)$option['name'], '-');
		$alias = $option['alias'] ?? null;
		
		if (!empty($alias)) {
			$alias = ltrim((string)$alias, '-');
			$this->optAliasByName[$name] = $alias;
		}
		
		// required?
		$required = $option['required'] ?? true;
		$option['required'] = $required;
		
		if ($required) {
			$this->required[] = $name;
		} else {
			$this->optional[$name] = $option['default'] ?? null;
		}
		
		// normalize type
		$type = $option['type'] ?? 'string';
		if ($type === 'str') $type = 'string';
		
		$option['type'] = $type;
		
		// extras?
		if (isset($option['allow_extra'])) {
			$this->allowedExtra[$name] = (bool) $option['allow_extra'];
		}
		
		$option['value'] = null;
		$option['kind'] = 'option';
		
		$this->allowedOptions[$name] = $option;
	}
	
	private function getAlias(string $type, string $key): ?string
	{
		$map =
			$type == 'flag' ?
				$this->flagAliasByName :
				$this->optAliasByName;
		
		return $map[$key] ?? '';
	}
	
	private function parseArgs(array $args): void
	{
		$this->buildAliasIndexes();
		$n = count($args);
		
		for ($i = 0; $i < $n; $i++) {
			$token = $args[$i];
			
			if ($token === '--') {
				$this->error("Positional arguments are not supported", 2);
			}
			
			
			/*
			 * Bundled short flags
			 * */
			if (self::isShortFlagBundle($token)) {
				$letters = substr($token, 1);
				$len	 = strlen($letters);
				
				for ($k = 0; $k < $len; $k++) {
					$alias = $letters[$k];
					
					if (!$this->isRegisteredFlagName($alias)) {
						$this->errorWithHelp('flag', "-$alias");
					}
					
					$name = $this->normalizeFlag($alias);
					$this->flags[] = $name;
					
					// Store what key user used!
					$this->userInputMap[$name] = "-$alias";
				}
				
				continue;
			}
			
			
			/*
			 * Single short flag
			 * */
			if (str_starts_with($token, '-') && !str_starts_with($token, '--') && strlen($token) === 2) {
				$alias = substr($token, 1);
				
				if (!$this->isRegisteredFlagName($alias)) {
					$this->errorWithHelp('flag', $token);
				}
				
				$name = $this->normalizeFlag($alias);
				$this->flags[] = $name;
				
				// Store what key user used!
				$this->userInputMap[$name] = $token;
				
				continue;
			}
			
			
			/*
			 *
			 * */
			if (self::isName($token)) {
				[$raw, $eqVal] = self::splitLongEquals($token);
				$raw = ltrim($raw, '-');
				
				// Is it a flag?
				// canonical only
				if (isset($this->allowedFlags[$raw])) {
					if ($eqVal !== null && $eqVal !== '') {
						$this->error("Flag --$raw does not take a value", 2);
					}
					
					$this->flags[] = $raw;
					$this->userInputMap[$raw] = "--$raw";
					continue;
				}
				
				// Option canonical or option alias:
				if (!$this->isRegisteredOptionName($raw)) {
					$this->errorWithHelp('option', "--$raw");
				}
				
				$name = $this->normalizeOption($raw);
				$this->userInputMap[$name] = "--$raw";
				
				$val = $eqVal;
				if ($val === null) {
					if (!isset($args[$i + 1]) || $args[$i + 1] === '' || $args[$i + 1] === '--') {
						$this->error("Value missing for option --$raw", 2);
					}
					
					$val = $args[++$i];
				}
				
				$this->options[$name] = $val;
				
				// Extras
				$allowedExtra = $this->allowedExtra[$name] ?? false;
				
				if ($allowedExtra) {
					while (isset($args[$i + 1])) {
						$peek = $args[$i + 1];
						
						if ($peek === '--') break;
						
						// next option/flag
						if (str_starts_with($peek, '-')) break;
						
						$this->extra[$name][] = $peek;
						$i++;
					}
				}
				
				continue;
			}
			
			$this->error("Unexpected value: $token", 2);
		}
	}
	
	private function validate(array $args): void {
		// Do you need help?
		if (!empty($args) && ($args[0] === '-?' || $args[0] === '--help' || $args[0] === '-h')) {
			$this->showHelp($args);
		}
		
		$this->parseArgs($args);
		
		// Have we got all required ones?
		$userInputKeys = array_keys($this->userInputMap);
		foreach ($this->required as $req) {
			if (!in_array($req, $userInputKeys, true)) {
				$this->error("Missing required argument: --$req ", 2);
			}
		}
		
		// Filter all the input
		foreach ($this->options as $key => $v) {
			$option = $this->allowedOptions[$key];
			$usrKey = $this->userInputMap[$key];
			$type	= $option['type'];
			
			$msgByKey = str_starts_with($usrKey, '--');
			
			if ($type == 'int') {
				$output = Filter::checkInt($v);
				if(!Filter::isOK($output)) {
					if ($msgByKey) {
						$this->error("$usrKey must be an integer number", 2);
					} else {
						$str  = "Invalid value: $usrKey\n";
						$str .= 'It must be an integer.';
						
						$this->error($str, 2);
					}
				}
			} elseif ($type == 'float') {
				$output = Filter::checkFloat($v);
				if(!Filter::isOK($output)) {
					if ($msgByKey) {
						$this->error("$usrKey must be a float", 2);
					} else {
						$str  = "Invalid value: $usrKey\n";
						$str .= 'It must be a float.';
						
						$this->error($str, 2);
					}
				}
			} else {
				$output = Filter::checkString($v, FILTER_DEFAULT);
				if(!Filter::isOK($output)) {
					if ($msgByKey) {
						$this->error("$usrKey must a string", 2);
					} else {
						$str  = "Invalid value: $usrKey\n";
						$str .= 'It must be a string.';
						
						$this->error($str, 2);
					}
				}
			}
			
			// Check if it is one of valid options
			if (!empty($option['options']) && !in_array($output,  $option['options'], true)) {
				$errMsg  = $msgByKey ? "Invalid value given for $usrKey" : "Invalid value: $usrKey";
				$errMsg .= "\n";
				
				$guideMsg = "Allowed values: " . Arr::strList($option['options']);
				
				$str  = $errMsg;
				$str .= $guideMsg;
				
				$this->error($str, 2);
			}
			
			$this->options[$key] = $output;
		}
		
		// Add missing default option values
		foreach ($this->optional as $k => $v) {
			if (array_key_exists($k, $this->options)) continue;
			$this->options[$k] = $v;
		}
	}
	
	private function getHelp(string $type, ?string $key): ?array {
		$isOption = $type === 'option';
		$array    = $isOption ? $this->allowedOptions : $this->allowedFlags;
		
		$key = ltrim($key, '-');
		
		/*
		 * If there is no option/flag for the CLI then returns empty array
		 * to indicate that.
		 */
		if (empty($array)) {
			return [[], []];
		}
		
		$titles = [ucfirst($type) . "s"];
		$des    = [''];
		
		/*
		 * Compute the maximum length of the "short" keys (e.g. --language, --country)
		 * so we can pad shorter ones and align the pipe (|) across lines.
		 * */
		$maxShortLen = 0;
		
		foreach (array_keys($array) as $name) {
			$len = mb_strlen($name) + 2;
			if ($len > $maxShortLen) $maxShortLen = $len;
		}
		
		if (!empty($key)) {
			$name = $this->getName($key, $array);
			
			if (is_null($name)) {
				$this->error("Unknown $type: $key", 2);
			}
			
			$opt  = $array[$name];
			$name = "--$name";
			
			$alias = $opt['alias'];
			
			if (!empty($alias)) {
				$alias = $opt['kind'] == 'option' ? "--$alias" : "-$alias";
			}
			
			$alias 	  	= empty($alias) ? '' : "| $alias";
			$star     	= $opt['required'] ? '* ' : '  ';
			
			$paddedShort = str_pad($name, $maxShortLen);
			$titles[] = "$star   $paddedShort $alias";
			
			$des[]    = "  {$opt['description']}";
			
		} else {
			$count = 0;
			foreach ($array as $opt => $v) {
				$count++;
				
				// (kept as-is) you compute $count but don't use it in output; leaving unchanged
				$count 	  = $isOption ? "#$count" : ' ';
				$alias = $v['alias'];
				
				if (!empty($alias)) {
					$alias = $v['kind'] == 'option' ? "--$alias" : "-$alias";
				}
				
				$alias = empty($alias) ? '' : "| $alias";
				$star     = $v['required'] ? '* ' : '  ';
				
				$paddedOpt = str_pad("--$opt", $maxShortLen);
				$titles[]  = "$star  $paddedOpt $alias";
				
				$des[] = $v['description'] ?? '';
				
				if (!empty($v['options'])) {
					$str = "Allowed values: " . Arr::strList($v['options']);
					$titles[] = '';
					$des[] = $str;
				}
			}
		}
		
		return [$titles, $des];
	}
	
	/**
	 * Tells whether a specified flag was set by the user in the command.
	 *
	 * @param string $key The flag (name/alias). Dash prefix is optional.
	 * @return bool true if the flag was set; false otherwise
	 * */
	protected function isFlagSet(string $key): bool
	{
		$this->buildAliasIndexes();
		
		// Alias flag
		if (str_starts_with($key, '-') && !str_starts_with($key, '--') && strlen($key) === 2) {
			$alias = substr($key, 1);
			$name = $this->normalizeFlag($alias);
			
			return $name !== null && in_array($name, $this->flags, true);
		}
		
		// Canonical flag
		if (str_starts_with($key, '--')) {
			$key = ltrim($key, '-');
		}
		
		$name = $this->normalizeFlag($key) ?? $key;
		return isset($this->allowedFlags[$name]) && in_array($name, $this->flags, true);
	}
	
	/**
	 * Returns whether an option was set by the user in the command.
	 *
	 * @param string $key The option (name/alias). Dashes are optional.
	 * @return bool true if the flag was set; false otherwise
	 * */
	protected function isOptionSet(string $key): bool
	{
		$key = ltrim($key, '-');
		
		$key = $this->getName($key, $this->allowedOptions);
		return isset($this->options[$key]);
	}
	
	/**
	 * Returns the value entered by user for specified option.
	 *
	 * @param string $key. The option (name/alias). Dash prefix is optional.
	 * @param mixed $default default value to be returned if the option value wasn't found in user input
	 * @return mixed value for the option provided by the user
	 * */
	protected function getOptionValue(string $key, mixed $default = null): mixed {
		$this->buildAliasIndexes();
		
		$key = ltrim($key, '-');
		$key = $this->getName($key, $this->allowedOptions);
		
		if ($key === null) return $default;
		
		return $this->options[$key] ?? $default;
	}
	
	/**
	 * HatiCLI can be called from within any php script. This method should only be called
	 * from catch block to allow HatiCLI handle the CLI output in a consistent way so that
	 * an CLI tool implemented using HatiCLI can be terminated as expecting by the calling
	 * script.
	 *
	 * @throws CLIExit|Throwable rethrows the {@link CLIExit} exception up the stack
	 * */
	protected function rethrowIfExit(Throwable $t): void
	{
		if ($t instanceof CLIExit) {
			throw $t;
		}
	}
	
	/**
	 * Call this method to report error to the user, and it exits the CLI properly.
	 * This method takes care of the CLI termination stack if called by another
	 * script.<br>
	 * Remember to call {@link rethrowIfExit()}
	 * in catch block, if error is being reported from within tht try block.
	 * */
	#[NoReturn]
	public function error(string $msg, int $code = 1): void
	{
		if ($this->ctx->embedded) {
			throw new CLIExit($code, $msg);
		}
		
		if ($msg !== '') {
			$this->io->error($msg);
		}
		
		exit($code);
	}
	
	/**
	 * Call this method to properly exit out of the CLI. Exiting using this
	 * method will make sure that the CLI doesn't exit the caller script which would
	 * be unexpected.<br>
	 *
	 * Remember to call {@link rethrowIfExit()} in catch block, if exit is being
	 * done from within any try-catch block.
	 * */
	#[NoReturn]
	protected function exit($code = 0): void
	{
		$this->error('', $code);
	}
	
	private function whatIsIt(string $input): ?string
	{
		$this->buildAliasIndexes();
		
		$raw = ltrim($input, '-');
		
		// Flag canonical
		if (isset($this->allowedFlags[$raw])) {
			return 'flag';
		}
		
		// Flag alias
		if (isset($this->flagNameByAlias[$raw])) {
			return 'flag';
		}
		
		// Option canonical
		if (isset($this->allowedOptions[$raw])) {
			return 'option';
		}
		
		// Option alias
		if (isset($this->optNameByAlias[$raw])) {
			return 'option';
		}
		
		return null;
	}
	
	#[NoReturn]
	private function showHelp(array $args): void
	{
		$what = $args[1] ?? null;
		
		if (!is_null($what)) {
			$entity = $this->whatIsIt($what);
			
			if ($entity == 'option') {
				$data = $this->getHelp('option', $what);
			} elseif ($entity == 'flag') {
				$data = $this->getHelp('flag', $what);
				if (count($data[0]) == 0) {
					$this->io->write("This CLI tool doesn't take in any flag");
					$this->exit();
				}
			} elseif (str_starts_with($what, 'flag')) {
				$data = $this->getHelp('flag', null);
				if (count($data[0]) == 0) {
					$this->io->write("This CLI tool doesn't take in any flag");
					$this->exit();
				}
			} elseif (str_starts_with($what, 'option')) {
				$data = $this->getHelp('option', null);
			} else {
				$this->error("Invalid help argument: $what", 2);
			}
			
			$table = Text::table2D($data[0], $data[1], 4, 100, true);
			$this->io->write($table);
			
			$this->exit();
		}
		
		$this->io->write("$this->name v$this->version");
		$this->io->write($this->io->wrap("$this->description", 80));
		
		$titles = [];
		$des = [];
		
		// show option
		$data = $this->getHelp('option', null);
		
		$hasOption = !empty($data[0]);
		if ($hasOption) {
			$titles = array_merge([''], $data[0], ['']);
			$des = array_merge([''], $data[1], ['']);
		}
		
		// show flags
		$data = $this->getHelp('flag', null);
		
		if (!empty($data[0])) {
			if (!$hasOption) {
				$this->io->newLine();
			}
			
			$titles = array_merge($titles, $data[0]);
			$des = array_merge($des, $data[1]);
		}
		
		if (!empty($titles)) {
			$table = Text::table2D($titles, $des, 4, 100, true);
			$this->io->write($table);
		}
		
		$this->exit();
	}
	
	#[NoReturn]
	private function errorWithHelp(string $type, string $value): void
	{
		$this->io->error("Invalid $type: $value");
		
		$isFlag = $type === 'flag';
		$map =
			$isFlag ?
			$this->allowedFlags :
			$this->allowedOptions;
		
		if (empty($map)) {
			$this->error("No $type suggestions found", 2);
		}
		
		$primary = array_keys($map);
		$meta	 = array_values($map);
		
		/*
		 * Build headers
		 * */
		if ($isFlag) {
			$headers = [ucwords($type), 'Alias', 'Description'];
		} else {
			$headers = ['Required', ucwords($type), 'Alias', 'Type', 'Allowed Values', 'Description'];
		}
		
		/*
		 * Build the rows
		 * */
		$rows = [];
		
		for ($i = 0; $i < count($primary); $i++) {
			$entity 	 = $primary[$i];
			$alias 		 = $this->getAlias($type, $primary[$i]);
			$description = $meta[$i]['description'] ?? '-';
			
			if ($isFlag) {
				$row = [
					$entity,
					$alias,
					$description,
				];
			} else {
				$options = $this->allowedOptions[$entity]['options'] ?? '-';
				$options = is_array($options) ? Arr::strList($options) : $options;
				
				$row = [
					$meta[$i]['required'] ? '       *' : '',
					$entity,
					$alias,
					$this->allowedOptions[$entity]['type'] ?? '-',
					$options,
					$description,
				];
			}
			
			$rows[] = $row;
		}
		
		$table = $this->io->table($rows, $headers, true, false);
		
		$this->io->write("\n$table");
		
		$this->error("", 2);
	}
	
	/**
	 * Bootstraps the CLI tool. Any implementation of HatiCLI should invoke
	 * this method to start the CLI and start validating user inputs to the
	 * CLI.
	 * This method first initializes the CLI tool and then starts validating
	 * the user input as per options.
	 *
	 * On successfully passing the validation phase, it calls the {@link run()}
	 * method on the implementation class with the validated user inputs to
	 * allow the tool execution.
	 *
	 * @param string $cliCls A class which is an instance of HatiCLI
	 * @param string|array ...$args Any predefined argument to be passed in to the CIL.
	 * If none provided then any arguments from terminal while invoking CLI will be
	 * passed in which is global variable $argv. The first item of $argv representing
	 * the script path will be removed before passing the array as arguments to the CLI.
	 */
	#[NoReturn]
	public static function start(string $cliCls, string|array ...$args): void
	{
		if (self::$loading) {
			self::$loading = false;
			return;
		}
		
		$args = Arr::varargsAsArray($args);
		
		if (empty($args)) {
			global $argv;
			array_shift($argv);
			$args = $argv;
		}
		
		$res = self::invoke($cliCls, $args, new CLIContext(embedded: false, io: new ConsoleIO()));
		
		exit($res->code);
	}
	
	/**
	 * Any HatiCLI can be called using this method from anywhere.
	 * Just make sure that you don't make it run in an infinite loop!
	 *
	 * For sub-CLI, there is an instance helper method {@link call()}
	 * should be used.
	 *
	 * @param string $cliCls A FQC CLI class implemented HatiCLI
	 * @param string|array ...$args Any arguments to be passed in to the CLI
	 * @return CLIResult The result returned by the CLI
	 * */
	public static function invoke(string $cliCls, array $args = [], ?CLIContext $ctx = null): CLIResult
	{
		/*
		 * Make sure the class is resolved and an implementation of HatiCLI
		 * */
		if (!class_exists($cliCls)) {
			return new CLIResult(1, '', "Class $cliCls doesn't exist");
		}
		
		if (!is_subclass_of($cliCls, HatiCLI::class)) {
			return new CLIResult(1, '', "$cliCls is not an implementation of HatiCLI");
		}
		
		$ctx ??= new CLIContext(embedded: true);
		
		// Choose IO
		$io = $ctx->io;
		
		if (is_null($io)) {
			$io = $ctx->captureOutput ? new BufferedIO() : new ConsoleIO();
			$ctx->io = $io;
		}
		
		// If captureOutput is requested but the chosen IO isn't a BufferedIO,
		// we can tee output into a BufferedIO while still keeping the original IO.
		// (Enable this behavior only if you actually want "capture + show".)
		$capture = null;
		
		if ($ctx->captureOutput) {
			if ($io instanceof BufferedIO) {
				$capture = $io;
			} else {
				// capture + show
				$capture = new BufferedIO();
				$io = new TeeIO($io, $capture);
				$ctx->io = $io;
			}
		}
		
		$cli = new $cliCls;
		
		$cli->ctx = $ctx;
		$cli->io  = $io;
		
		try {
			$inputBuilder = new CLIInputBuilder($cli);
			
			$cli->describeCLI();
			$cli->setup($inputBuilder);
			
			$cli->validate($args);
			$cli->run($cli->options);
			
			// Build result if buffered
			if ($capture instanceof BufferedIO) {
				return new CLIResult(0, $capture->stdout(), $capture->stderr());
			}
			
			return new CLIResult(0);
		} catch (CLIExit $e) {
			$code = $e->code;
			$msg  = $e->getMessage();
			
			if ($msg !== '' && !str_ends_with($msg, "\n")) {
				$msg .= "\n";
			}
			
			// If message exists, write it to the current IO (Console shows it, Buffered captures it).
			if ($msg !== '') {
				$io->error($msg);
			}
			
			if ($capture instanceof BufferedIO) {
				return new CLIResult($code, $capture->stdout(), $capture->stderr());
			}
			
			return new CLIResult($code);
		} catch (Throwable $t) {
			// unexpected crash
			$msg = "Unhandled error: " . $t->getMessage();
			if (!str_ends_with($msg, "\n")) $msg .= "\n";
			
			$io->error($msg);
			
			if ($capture instanceof BufferedIO) {
				return new CLIResult(1, $capture->stdout(), $capture->stderr());
			}
			
			return new CLIResult(1);
		}
	}
	
	/**
	 * Invoke another CLI class as a child command.
	 *
	 * The child CLI runs in embedded mode and shares the current IO instance.
	 * Its exit behavior is controlled by the provided ExitPolicy:
	 *
	 * - PROPAGATE: Parent may treat child exit code as its own.
	 * - SWALLOW:   Child failure does not stop the parent.
	 * - RAISE:     Non-zero child exit code immediately triggers a parent-level error.
	 *
	 * @param string $cliCls  Fully-qualified CLI class name.
	 * @param array  $args    Arguments to pass to the child CLI.
	 * @param ?CLIExitPolicy $policy Exit handling policy (defaults to current context policy).
	 *
	 * @return CLIResult Result object containing exit code and captured output (if any).
	 */
	protected function call(string $cliCls, array $args = [], TerminalIO $io = null, ?CLIExitPolicy $policy = null): CLIResult
	{
		$policy ??= $this->ctx->exitPolicy;
		
		$io = !is_null($io) ? $io : $this->io;
		
		// If parent is embedded and capturing, often we want child to share same IO
		$childCtx = new CLIContext(
			embedded: true,
			io: $io,
			exitPolicy: $policy,
			captureOutput: false
		);
		
		self::$loading = true;
		
		$res = self::invoke($cliCls, $args, $childCtx);
		
		// Escalation
		if ($policy === CLIExitPolicy::RAISE && $res->code !== 0) {
			$this->error("Child CLI failed ($cliCls) with code $res->code", $res->code);
		}
		
		self::$loading = false;
		return $res;
	}
	
}