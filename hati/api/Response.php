<?php

namespace hati\api;

use hati\config\Key;
use hati\Hati;
use hati\util\Util;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;

/**
 * Response - A JSON response writer class
 *
 * This class allows the application to form a JSON response with various simple and
 * powerful functions which allow to avoid creating and keeping track of various variables
 * that were previously required to write JSON output.
 *
 * It has two JSON output methods namely {@link report()} and {@link reply()}. These methods add
 * a <b>response</b> object at the end of the JSON output object to indicate response message and status.
 *
 * Both reply and report methods mark the response object at SYSTEM level with empty
 * message by default. Reply uses SUCCESS = 1 value and report uses ERROR = -1 as
 * status flag in the response object by default.
 *
 * Within the various methods of this Response class, the <b>map</b> argument means that
 * the argument is either of type array or of type php standard object as methods work
 * by polymorphisms on the map argument.
 *
 * When constructing an instance, optional header value of Content-Type of application/json
 * can be turned on or off by devMode flag.
 *
 *  Example:
 *  <code>
 *  $response = new Response();
 *
 *  // single key-value pair
 *  $response -> add('name', 'Alex');
 *
 *  // multiple key-value pairs in order
 *  $response -> addAll(['age', 'sex'], [26, 'male']);
 *
 *  $response -> getJSON();
 * </code>
 * Output:
 * <code>
 * {
 *      'name': 'Alex',
 *      'age': 26,
 *      'sex': 'male',
 *      'response' : {
 *          'status': 1,
 *          'msg': ''
 *      }
 * }
 * </code>
 *
 * */

class Response {

	// constants that represent response status of the API execution
	const ERROR = -1;
	const WARNING = 0;
	const SUCCESS = 1;
	const INFO = 2;

	// json output keys
	private static string $KEY_RESPONSE = 'response';
	private static string $KEY_STATUS = 'status';
	private static string $KEY_MSG = 'msg';

	// buffer for json output
	private array $output = [];

	public function addKey(string $key): void {
		if (!array_key_exists($key, $this -> output)) $this -> output[$key] = null;
	}

	public function add(string $key, $value): void {
		$this -> output[$key] = $this -> getTypedValue($value);
	}

	/** @noinspection PhpUnused **/
	public function addAll($keys, $values): void {
		$keyCount = count($keys);
		if ($keyCount != count($values)) throw new InvalidArgumentException('Keys and values are not of same length.');

		for ($i = 0; $i < $keyCount; $i++) $this -> add($keys[$i], $values[$i]);
	}

	/**
	 * This method add the passed argument to the array specified by the key. This method
	 * first checks whether the array has already been defined or not. If not, then it creates
	 * the array and then add the value to the end of the array.
	 *
	 * @param string $arrKey <p>the name of the array</p>
	 * @param mixed $val <p>any value you want to add to the array. passed value will be parsed
	 * to obtain the right type.</p>
	 * @return void
	 *
	 * @noinspection PhpUnused
	 * */
	public function addToArr(string $arrKey, mixed $val): void {
		// first define the array with given key if we don't have already
		$this -> addKey($arrKey);
		if (!is_array($this -> output[$arrKey])) $this -> output[$arrKey] = [];

		// check whether the value is an array of map; if yes then add them iteratively
		if (is_array($val)) foreach ($val as $map) $this -> addToArray($arrKey, $map);

		// otherwise add the value normally
		else $this -> addToArray($arrKey, $val);
	}

	private function addToArray(string $arrKey, $val): void {
		$this -> output[$arrKey][] =  $this -> getTypedValue($val);
	}

	/**
	 * This method can take a map or object and add the keys and the values in the json output
	 * buffer iteratively. Every properties of the passed object will be the direct properties
	 * of the final JSON output object. Existing property value of the main JSON output object
	 * will be overridden by latest property value.
	 *
	 * @param $map <p>The map(array/object) you want to add directly to the JSON output object.</p>
	 * @return void
	 */
	public function addFromMap($map): void {
		$this -> checkMap($map);
		foreach ($map as $key => $value) $this -> add($key, $value);
	}

	/**
	 * This method internally calls on {@link addFromMap} on the argument map array iteratively. This method
	 * will override the existing property value if any presents already in the JSON output object.
	 *
	 * @param $maps <p>It must be an array containing maps(array/object).</p>
	 * @return void
	 *
	 * @noinspection PhpUnused
	 * */
	public function addFromMaps($maps): void {
		if (!is_array($maps)) throw new InvalidArgumentException('The value has to be an array of maps');
		foreach ($maps as $map) $this -> addFromMap($map);
	}

	/**
	 * This method checks whether a value is either a map of array or object. If not then
	 * it throw an InvalidArgumentException.
	 *
	 * @param mixed $val The value which has to be a map
	 * @return void
	 * */
	private function checkMap(mixed $val): void {
		if (!is_object($val) && !is_array($val))
			throw new InvalidArgumentException('The value has to be a map of either array or object.');
	}

	/**
	 * Using this method, we can add any key-value pair as property of specified object of JSON
	 * output object. Each key-value pair or property will go under the map key of JSON output
	 * object.
	 *
	 * @param string $mapKey The name of the property of the JSON output object which will hold
	 * key-value pari property.
	 * @param string $key The name of the property.
	 * @param mixed $val The value of the property
	 *
	 * @return void
	 */
	public function addToMap(string $mapKey, string $key, mixed $val): void {
		$this -> addKey($mapKey);
		if ($this -> output[$mapKey] == null) $this -> output[$mapKey] = [];
		$this -> output[$mapKey][$key] = $this -> getTypedValue($val);
	}

	/**
	 * This methods takes a map/object and put their properties with values under a direct property
	 * of the JSON output object. It checks whether passed map is an actual map or not. It then
	 * internally call {@link addToMap} iteratively to add all the properties of the given map to the
	 * specified map/object of the JSON output object.
	 *
	 * @param string $mapKey The property of JSON output object which will hold each property of given
	 * map.
	 * @param array $map The map(array/object) whose properties will be copied to the property-object of
	 * JSON output object.
	 * @return void
	 */
	public function addMapToMap(string $mapKey, array $map): void {
		$this -> checkMap($map);
		foreach ($map as $key => $value) $this -> addToMap($mapKey, $key, $value);
	}

	/**
	 * This method iteratively calls on
	 * @param string $mapKey The property of JSON output object which will hold each property of given
	 * map.
	 * @param mixed $mapArray The array which contains the maps of arrays or objects
	 * @return void
	 *
	 * @noinspection PhpUnused
	 */
	public function addMapsToMap(string $mapKey, mixed $mapArray): void {
		if (!is_array($mapArray)) throw new InvalidArgumentException('an array of maps is required.');
		foreach ($mapArray as $map) $this -> addMapToMap($mapKey, $map);
	}

	public function getJSON(): string {
		return json_encode($this -> output);
	}

	#[NoReturn]
	public function reply($msg = '', $status = Response::SUCCESS): void {
		$resObj = self::addResponseObject($status, $msg);
		$this -> add(self::$KEY_RESPONSE, $resObj);

		if (!Util::cli() && Hati::config(Key::AS_JSON_OUTPUT, 'bool')) {
			header('Content-Type: application/json');
		}

		echo $this -> getJSON();
		exit;
	}

	#[NoReturn]
	public static function report($msg, $stat): void {
		if (!Util::cli() && Hati::config(Key::AS_JSON_OUTPUT, 'bool')) {
			header('Content-Type: application/json');
		}

		echo self::reportJSON($msg, $stat);
		exit;
	}

	// when the Hati has dev_API_delay flag turned on, then it adds additional
	// DEV_API_DELAY to the response object to indicate/remind the developer for
	// future work or production release.
	private static function addDevProperties(array &$buffer): void {
		if (Hati::config(Key::DEV_API_BENCHMARK, 'bool'))
			$buffer['exe_time'] = sprintf('%.4f', microtime(true) - Hati::benchmarkStart());

		// For any positive DEV_API_DELAY config, we need to add 'delay_time' to the output json
		$apiDelay = Hati::config(Key::DEV_API_DELAY, 'int');
		if ($apiDelay) $buffer['delay_time'] = $apiDelay;
	}

	/** @noinspection PhpUnused **/
	#[NoReturn]
	public static function reportOk(string $msg = ''): void {
		self::report($msg, Response::SUCCESS);
	}

	/** @noinspection PhpUnused **/
	#[NoReturn]
	public static function reportInfo(string $msg = ''): void {
		self::report($msg, Response::INFO);
	}

	/** @noinspection PhpUnused **/
	#[NoReturn]
	public static function reportWarn(string $msg = ''): void {
		self::report($msg, Response::WARNING);
	}

	/** @noinspection PhpUnused **/
	#[NoReturn]
	public static function reportErr(string $msg = ''): void {
		self::report($msg, Response::ERROR);
	}

	/**
	 * This method statically creates a JSON output object with response property including
	 * response message, level and status.
	 *
	 * @param string $msg response message.
	 * @param int $status the status of the response of execution.
	 *
	 * @return string JSON output object consisting of response object.
	 */
	public static function reportJSON(string $msg, int $status): string {
		$output[self::$KEY_RESPONSE] = self::addResponseObject($status, $msg);

		if (!Util::cli() && Hati::config(Key::AS_JSON_OUTPUT, 'bool')) {
			header('Content-Type: application/json');
		}

		return json_encode($output);
	}

	#[NoReturn]
	public static function sendJSON(array $data = []): void {
		$output = [];

		if (is_array($data)) {
			foreach ($data as $k => $v)
				$output[$k] = $v;
		}

		// check whether we have any API testing properties to perform
		$delay = Hati::config(Key::DEV_API_DELAY, 'int');
		if ($delay > 0) sleep($delay);
		self::addDevProperties($output);

		if (!Util::cli() && Hati::config(Key::AS_JSON_OUTPUT, 'bool')) {
			header('Content-Type: application/json');
		}

		echo count($output) == 0 ? '{}' : json_encode($output);
		exit;
	}

	private static function addResponseObject($stat, $msg): array {
		$output[self::$KEY_STATUS] = $stat;
		$output[self::$KEY_MSG] = $msg;

		// check whether we have any API testing properties to perform
		$delay = Hati::config(Key::DEV_API_DELAY, 'int');
		if ($delay > 0) sleep($delay);
		self::addDevProperties($output);

		return $output;
	}

	private function getTypedValue($val) {
		if (is_array($val)) foreach($val as $k => $v) $val[$k] = $this -> getTypedValue($v);
		if (!is_numeric($val))  return $val;
		return strpos($val, ".") ? (float) $val : (int) $val;
	}

}