<?php

namespace hati;

use InvalidArgumentException;

/**
 * Response - A JSON response writer class
 *
 * This class allows the application to form a JSON response with various simple and
 * powerful functions which allow to avoid creating and keeping track of various variables
 * that were previously required to write JSON output.
 *
 * It has two JSON output methods namely @link report and @link reply. These methods add
 * a <b>response</b> object at the end of the JSON output object to indicate response level,
 * message and status.
 *
 * Method <b>reply</b> returns JSON output and exits the script. It is usually used for
 * indicating successful execution by status flag of the script. On the other hand,
 * <b>report</b> is a static method which is used to write JSON output telling that an
 * error or unusual something happened by status flag and it also exits the script.
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
 * Example:
 *  $response = new Response();
 *  $response -> add('name', 'ABDUL AHAD'); // single key-value pair
 *  $response -> addAll(['age', 'sex'], [26, 'male']); // multiple key-value pairs in order
 *  $response -> getJSON();
 *
 * Output:
 *  {
 *      'name': 'ABDUL AHAD',
 *      'age': 26,
 *      'sex': 'male',
 *      'response' : {
 *          'status': 1,
 *          'level': 0,
 *          'msg': ''
 *      }
 *  }
 *
 * */

class Response {

    // constants that describe reporting level
    const LVL_SYSTEM = 0;
    const LVL_USER = 1;

    // constants that represent response status of the API execution
    const ERROR = -1;
    const WARNING = 0;
    const SUCCESS = 1;
    const INFO = 2;

    // json output keys
    private static string $KEY_RESPONSE = 'response';
    private static string $KEY_STATUS = 'status';
    private static string $KEY_LEVEL = 'level';
    private static string $KEY_MSG = 'msg';

    // buffer for json output
    private array $output = [];

    public function addKey(string $key) {
        if (!array_key_exists($key, $this -> output)) $this -> output[$key] = null;
    }

    public function add(string $key, $value) {
        $this -> output[$key] = $this -> getTypedValue($value);
    }

    public function addAll($keys, $values) {
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
     * @param mixed $value <p>any value you want to add to the array. passed value will be parsed
     * to obtain the right type.</p>
     * @return void
     * */
    public function addToArr(string $arrKey, mixed $value) {
        // first define the array with given key if we don't have already
        $this -> addKey($arrKey);
        if (!is_array($this -> output[$arrKey])) $this -> output[$arrKey] = [];

        // check whether the value is an array of map; if yes then add them iteratively
        if (is_array($value)) foreach ($value as $map) $this -> addToArray($arrKey, $map);

        // otherwise add the value normally
        else $this -> addToArray($arrKey, $value);
    }

    private function addToArray(string $arrKey, $value) {
        $this -> output[$arrKey][] =  $this -> getTypedValue($value);
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
    public function addFromMap($map) {
        $this -> checkMap($map);
        foreach ($map as $key => $value) $this -> add($key, $value);
    }

    /**
     * This method internally calls on @link addFromMap on the argument map array iteratively. This method
     * will override the existing property value if any presents already in the JSON output object.
     *
     * @param $maps <p>It must be an array containing maps(array/object).</p>
     * @return void
     * */
    public function addFromMaps($maps) {
        if (!is_array($maps)) throw new InvalidArgumentException('The value has to be an array of maps');
        foreach ($maps as $map) $this -> addFromMap($map);
    }

    /*
     * This method checks whether a value is either a map of array or object. If not then
     * it throw an InvalidArgumentException.
     *
     * @param $value <p>The value which has to be a map</p>
     * @return void
     * */
    private function checkMap($value) {
        if (!is_object($value) && !is_array($value))
            throw new InvalidArgumentException('The value has to be a map of either array or object.');
    }

    /**
     * Using this method, we can add any key-value pair as property of specified object of JSON
     * output object. Each key-value pair or property will go under the map key of JSON output
     * object.
     *
     * @param $mapKey <p>The name of the property of the JSON output object which will hold
     * key-value pari property.</p>
     * @param string $key <p>The name of the property.</p>
     * @param mixed $value <p>The value of the property.</p>
     * @return void
     */
    public function addToMap(string $mapKey, string $key, mixed $value) {
        $this -> addKey($mapKey);
        if ($this -> output[$mapKey] == null) $this -> output[$mapKey] = [];
        $this -> output[$mapKey][$key] = $value;
    }

    /**
     * This methods takes a map/object and put their properties with values under a direct property
     * of the JSON output object. It checks whether passed map is an actual map or not. It then
     * internally call @link addToMap iteratively to add all the properties of the given map to the
     * specified map/object of the JSON output object.
     *
     * @param string $mapKey <p>The property of JSON output object which will hold each property of given
     * map.</p>
     * @param $map <p>The map(array/object) whose properties will be copied to the property-object of
     * JSON output object.</p>
     * @return void
     */
    public function addMapToMap(string $mapKey, $map) {
        $this -> checkMap($map);
        foreach ($map as $key => $value) $this -> addToMap($mapKey, $key, $value);
    }

    /**
     * This method iteratively calls on
     * @param string $mapKey <p>The property of JSON output object which will hold each property of given
     * map.</p>
     * @param $mapArray <p>The array which contains the maps of arrays or objects</p>
     * @return void
     */
    public function addMapsToMap(string $mapKey, $mapArray) {
        if (!is_array($mapArray)) throw new InvalidArgumentException('an array of maps is required.');
        foreach ($mapArray as $map) $this -> addMapToMap($mapKey, $map);
    }

    public function getJSON(): string {
        return json_encode($this -> output);
    }

    public function reply($msg = '', $status = Response::SUCCESS, $level = Response::LVL_USER) {
        $resObj = self::addResponseObject($status, $level, $msg);
        $this -> add(self::$KEY_RESPONSE, $resObj);


        if (Hati::asJSONOutput()) header('Content-Type: application/json');

        exit($this -> getJSON());
    }

    public static function report($msg,  $status, $level) {
        if (Hati::asJSONOutput()) header('Content-Type: application/json');
        exit(self::reportJSON($msg, $status, $level));
    }

    public static function reportOk(string $msg = '', int $lvl = self::LVL_USER) {
        self::report($msg, Response::SUCCESS, $lvl);
    }

    public static function reportInfo(string $msg = '', int $lvl = self::LVL_USER) {
        self::report($msg, Response::INFO, $lvl);
    }

    public static function reportWarn(string $msg = '', int $lvl = self::LVL_USER) {
        self::report($msg, Response::WARNING, $lvl);
    }

    public static function reportErr(string $msg = '', int $lvl = self::LVL_USER) {
        self::report($msg, Response::ERROR, $lvl);
    }

    /**
     * This method statically creates a JSON output object with response property including
     * response message, level and status.
     *
     * @param string $msg response message.
     * @param int $level response message to the intended audience.
     * @param int $status the status of the response of execution.
     *
     * @return string JSON output object consisting of response object.
    */
    public static function reportJSON(string $msg, int $status, int $level): string {
        $output[self::$KEY_RESPONSE] = self::addResponseObject($status, $level, $msg);
        if (Hati::asJSONOutput()) header('Content-Type: application/json');
        return json_encode($output);
    }

    private static function addResponseObject($status, $level, $message): array {
        $output[self::$KEY_STATUS] = $status;
        $output[self::$KEY_LEVEL] = $level;
        $output[self::$KEY_MSG] = $message;
        return $output;
    }

    private function getTypedValue($value) {
        if (!is_numeric($value))  return $value;
        return strpos($value, ".") ? (float) $value : (int) $value;
    }

}