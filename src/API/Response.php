<?php

namespace Hati\API;

use Hati\Trunk;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Response builds a Hati API JSON response.
 *
 * It buffers JSON output data, headers, cookies, and HTTP status code.
 * It does not emit headers, cookies, body content, or terminate the PHP process.
 *
 * Calling {@link reply()} finalizes the response and throws Trunk.
 * Exception handler catches Trunk and returns the response array.
 */
class Response
{

	// constants that represent response status of the API execution
	const ERROR = -1;
	const WARNING = 0;
	const SUCCESS = 1;
	const INFO = 2;

	// buffer for JSON output
	private array $output = [];
	
	private Trunk $trunk;
	
	public function __construct()
	{
		$this->trunk = new Trunk(msg: '', httpStatusCode: 200, status: self::SUCCESS);
	}
	
	public function addKey(string $key): Response
	{
		if (!array_key_exists($key, $this->output)) $this->output[$key] = null;
		
		return $this;
	}

	public function add(string $key, $value): Response
	{
		$this->output[$key] = $value;
		
		return $this;
	}
	
	public function addAll($keys, $values): Response
	{
		$keyCount = count($keys);
		if ($keyCount != count($values)) throw new InvalidArgumentException('Keys and values are not of same length.');

		for ($i = 0; $i < $keyCount; $i++) $this->add($keys[$i], $values[$i]);
		
		return $this;
	}

	/**
	 * This method add the passed argument to the array specified by the key. This method
	 * first checks whether the array has already been defined or not. If not, then it creates
	 * the array and then add the value to the end of the array.
	 *
	 * @param string $arrKey <p>the name of the array</p>
	 * @param mixed $val <p>any value you want to add to the array. passed value will be parsed
	 * to obtain the right type.</p>
	 * @return Response returns this object for further method chaining
	 *
	 * @noinspection PhpUnused
	 * */
	public function addToArray(string $arrKey, mixed $val): Response
	{
		// first define the array with given key if we don't have already
		$this->addKey($arrKey);
		if (!is_array($this->output[$arrKey])) $this->output[$arrKey] = [];

		// check whether the value is an array of map; if yes then add them iteratively
		if (is_array($val)) foreach ($val as $map) $this->addToArr($arrKey, $map);

		// otherwise add the value normally
		else $this->addToArr($arrKey, $val);
		
		return $this;
	}

	private function addToArr(string $arrKey, $val): void
	{
		$this->output[$arrKey][] =  $val;
	}

	/**
	 * This method can take a map or object and add the keys and the values in the JSON output
	 * buffer iteratively. Every property of the passed object will be the direct properties
	 * of the final JSON output object. Existing property value of the main JSON output object
	 * will be overridden by latest property value.
	 *
	 * @param array|object $map The map(array/object) you want to add directly to the JSON output object.
	 * @return Response returns this object for further method chaining
	 */
	public function addFromMap(array|object $map): Response
	{
		$this->checkMap($map);
		foreach ($map as $key => $value) $this->add($key, $value);
		
		return $this;
	}

	/**
	 * This method internally calls on {@link addFromMap} on the argument map array iteratively. This method
	 * will override the existing property value if any presents already in the JSON output object.
	 *
	 * @param $maps <p>It must be an array containing maps(array/object).</p>
	 * @return Response returns this object for further method chaining
	 *
	 * @noinspection PhpUnused
	 * */
	public function addFromMaps($maps): Response
	{
		if (!is_array($maps)) throw new InvalidArgumentException('The value has to be an array of maps');
		foreach ($maps as $map) $this->addFromMap($map);
		
		return $this;
	}

	/**
	 * This method checks whether a value is either a map of array or object. If not then
	 * it throw an InvalidArgumentException.
	 *
	 * @param mixed $val The value which has to be a map
	 * @return void
	 * */
	private function checkMap(mixed $val): void
	{
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
	 * @return Response returns this object for further method chaining
	 */
	public function addToMap(string $mapKey, string $key, mixed $val): Response
	{
		$this->addKey($mapKey);
		if ($this->output[$mapKey] == null) $this->output[$mapKey] = [];
		$this->output[$mapKey][$key] = $val;
		
		return $this;
	}

	/**
	 * This method takes a map/object and put their properties with values under a direct property
	 * of the JSON output object. It checks whether passed map is an actual map or not. It then
	 * internally call {@link addToMap} iteratively to add all the properties of the given map to the
	 * specified map/object of the JSON output object.
	 *
	 * @param string $mapKey The property of JSON output object which will hold each property of given
	 * map.
	 * @param array|object $map The map(array/object) whose properties will be copied to the property-object of
	 * JSON output object.
	 * @return Response returns this object for further method chaining
	 */
	public function addMapToMap(string $mapKey, array|object $map): Response
	{
		$this->checkMap($map);
		foreach ($map as $key => $value) $this->addToMap($mapKey, $key, $value);
		
		return $this;
	}

	/**
	 * This method iteratively calls on
	 * @param string $mapKey The property of JSON output object which will hold each property of given
	 * map.
	 * @param mixed $mapArray The array which contains the maps of arrays or objects
	 * @return Response returns this object for further method chaining
	 *
	 * @noinspection PhpUnused
	 */
	public function addMapsToMap(string $mapKey, mixed $mapArray): Response
	{
		if (!is_array($mapArray)) throw new InvalidArgumentException('an array of maps is required.');
		foreach ($mapArray as $map) $this->addMapToMap($mapKey, $map);
		
		return $this;
	}
	
	public function httpStatus(int $code): Response
	{
		if ($code < 100 || $code > 599) {
			throw new InvalidArgumentException('Invalid HTTP status code.');
		}
		
		$this->trunk->httpStatusCode = $code;
		
		return $this;
	}
	
	public function getHttpStatus(): int
	{
		return $this->trunk->httpStatusCode;
	}
	
	public function addHeader(string $name, string $value): Response
	{
		$this->trunk->addHeader($name, $value);
		return $this;
	}
	
	public function addHeaders(array $headers): Response
	{
		$this->trunk->addHeaders($headers);
		return $this;
	}
	
	public function getHeaders(): array
	{
		return $this->trunk->getHeaders();
	}
	
	public function getCookies(): array
	{
		return $this->trunk->getCookies();
	}
	
	public function addCookie(string $name, mixed $value, int $expire = 0, bool $secure = true, bool $httpOnly = true, string $path = '/', string $domain = '', string $sameSite = 'Strict'): Response
	{
		$this->trunk->addCookie($name, $value, $expire, $secure, $httpOnly, $path, $domain, $sameSite);
		return $this;
	}
	
	public function addCookies(array $cookies): Response
	{
		$this->trunk->addCookies($cookies);
		return $this;
	}

	/**
	 * Returns the response data in JSON format as string
	 *
	 * @return string JSON data as string
	 * */
	public function getJSON(): string
	{
		try {
			return json_encode($this->output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RuntimeException('Unable to encode JSON: ' . $e->getMessage(), 0, $e);
		}
	}
	
	public function toArray(): array
	{
		$this->trunk->body = $this->getJSON();
		return $this->trunk->toArray();
	}

	/**
	 * Write out the response in JSON format.
	 *
	 * @param mixed $msg The response message
	 * @param int $status The response status
	 * @param ?array $headers HTTP headers as key-value pairs.
	 * @param ?array $cookies cookies to be set before sending JSON response. Each cookie has name, value and other
	 * cookie parameters.
	 * */
	public function reply(mixed $msg = '', int $status = Response::SUCCESS, ?array $headers = null, ?array $cookies = null): void
	{
		$this->trunk->msg = $msg;
		$this->trunk->status = $status;
		
		$this->output['response'] = $this->trunk->responseObject();
		
		if (!empty($headers)) {
			$this->addHeaders($headers);
		}
		
		if (!empty($cookies)) {
			$this->addCookies($cookies);
		}
		
		$this->trunk->body = $this->getJSON();
		
		throw $this->trunk;
	}

}