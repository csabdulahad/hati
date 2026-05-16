<?php

namespace Hati\API;

use Hati\Trunk;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Builds a buffered JSON API response.
 *
 * Response stores output data, headers, cookies, and HTTP status code.
 * It does not emit anything directly. Calling reply() finalizes the body
 * and throws Trunk for the top-level handler/emitter.
 *
 * Casting:
 * - Object policy: CAST_DEFAULT or CAST_AUTO.
 * - Single-value methods use optional $castAs.
 * - Map methods use optional $castMap for per-field casting.
 */
class Response
{

	// constants that represent response status of the API execution
	const ERROR = -1;
	const WARNING = 0;
	const SUCCESS = 1;
	const INFO = 2;
	
	// response value casting policies
	public const CAST_DEFAULT = 'default'; // no casting
	public const CAST_AUTO = 'auto';       // smart casting

	// explicit per-field casting types
	public const CAST_INT = 'int';
	public const CAST_FLOAT = 'float';
	public const CAST_BOOL = 'bool';
	public const CAST_STRING = 'string';

	// buffer for JSON output
	private array $output = [];
	
	private Trunk $trunk;
	
	private string $castBehavior = self::CAST_DEFAULT;
	
	/**
	 * Creates a response with HTTP 200, SUCCESS status, and the given casting policy.
	 *
	 * @param string $castBehavior CAST_DEFAULT or CAST_AUTO.
	 */
	public function __construct(string $castBehavior = self::CAST_DEFAULT)
	{
		$this->trunk = new Trunk(msg: '', httpStatusCode: 200, status: self::SUCCESS);
		$this->setAutoCasting($castBehavior);
	}
	
	/**
	 * Adds or replaces a top-level value.
	 *
	 * If $castAs is null, the object casting policy is used.
	 *
	 * @param string $key Top-level output key.
	 * @param mixed $value Value to store.
	 * @param ?string $castAs Optional cast override.
	 */
	public function add(string $key, mixed $value, ?string $castAs = null): Response
	{
		$this->output[$key] = $this->castValue($value, $castAs, $key);
		
		return $this;
	}
	
	/**
	 * Appends one item to a top-level array.
	 *
	 * If the array does not exist, it is created. Array/map values are appended
	 * as one item, not expanded.
	 *
	 * @param string $arrKey Top-level array key.
	 * @param mixed $val Item to append.
	 * @param ?string $castAs Optional cast override.
	 */
	public function addToArray(string $arrKey, mixed $val, ?string $castAs = null): Response
	{
		// first define the array with given key if we don't have already
		$this->addKey($arrKey);
		if (!is_array($this->output[$arrKey])) $this->output[$arrKey] = [];
		
		// add the passed value as ONE array item
		$this->addToArr($arrKey, $val, $castAs);
		
		return $this;
	}
	
	/**
	 * Copies map/object properties to the top-level output.
	 *
	 * Existing keys are overwritten. $castMap may define per-field cast rules.
	 * Fields missing from $castMap use the object casting policy.
	 *
	 * @param array|object $map Source map/object.
	 * @param array<string|int, string>|null $castMap Optional field cast rules.
	 */
	public function addFromMap(array|object $map, ?array $castMap = null): Response
	{
		$this->checkMap($map);
		
		foreach ($map as $key => $value) {
			$castAs = $castMap === null ? null : $this->getMapCastType($castMap, $key);
			$this->add((string) $key, $value, $castAs);
		}
		
		return $this;
	}
	
	/**
	 * Adds or replaces one property inside a nested object.
	 *
	 * If the nested object does not exist, it is created.
	 *
	 * @param string $mapKey Top-level object key.
	 * @param string $key Nested property key.
	 * @param mixed $val Value to store.
	 * @param ?string $castAs Optional cast override.
	 */
	public function addToMap(string $mapKey, string $key, mixed $val, ?string $castAs = null): Response
	{
		$this->addKey($mapKey);
		if ($this->output[$mapKey] == null) $this->output[$mapKey] = [];
		$this->output[$mapKey][$key] = $this->castValue($val, $castAs, $key);
		
		return $this;
	}
	
	/**
	 * Copies map/object properties into a nested object.
	 *
	 * Existing nested keys are overwritten. $castMap may define per-field cast rules.
	 * Fields missing from $castMap use the object casting policy.
	 *
	 * @param string $mapKey Top-level object key.
	 * @param array|object $map Source map/object.
	 * @param array<string|int, string>|null $castMap Optional field cast rules.
	 */
	public function addMapToMap(string $mapKey, array|object $map, ?array $castMap = null): Response
	{
		$this->checkMap($map);
		
		foreach ($map as $key => $value) {
			$castAs = $castMap === null ? null : $this->getMapCastType($castMap, $key);
			$this->addToMap($mapKey, (string) $key, $value, $castAs);
		}
		
		return $this;
	}
	
	/**
	 * Sets the HTTP status code.
	 *
	 * @throws InvalidArgumentException If the code is outside 100-599.
	 */
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
	
	/**
	 * Sets the object-level casting policy.
	 *
	 * Only CAST_DEFAULT and CAST_AUTO are allowed. Explicit scalar casts are
	 * per-value rules, not response-wide policies.
	 *
	 * @throws InvalidArgumentException If the policy is invalid.
	 */
	public function setAutoCasting(string $castBehavior): Response
	{
		if (!in_array($castBehavior, [self::CAST_DEFAULT, self::CAST_AUTO], true)) {
			throw new InvalidArgumentException('Invalid response cast behavior.');
		}
		
		$this->castBehavior = $castBehavior;
		
		return $this;
	}
	
	public function getAutoCasting(): string
	{
		return $this->castBehavior;
	}
	
	private function addKey(string $key): void
	{
		if (!array_key_exists($key, $this->output)) $this->output[$key] = null;
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
	
	private function addToArr(string $arrKey, mixed $val, ?string $castAs = null): void
	{
		$this->output[$arrKey][] = $this->castValue($val, $castAs, $arrKey);
	}
	
	private function castValue(mixed $value, ?string $castAs = null, ?string $key = null): mixed
	{
		$castAs ??= $this->castBehavior;
		
		return match ($castAs) {
			self::CAST_DEFAULT => $value,
			self::CAST_AUTO => $this->autoCastValue($value, $key),
			self::CAST_INT => $this->castDeep($value, fn(mixed $v): ?int => $v === null ? null : (int) $v),
			self::CAST_FLOAT => $this->castDeep($value, fn(mixed $v): ?float => $v === null ? null : (float) $v),
			self::CAST_BOOL => $this->castDeep($value, fn(mixed $v): ?bool => $this->castBool($v)),
			self::CAST_STRING => $this->castDeep($value, fn(mixed $v): ?string => $v === null ? null : (string) $v),
			default => throw new InvalidArgumentException('Invalid response cast type.'),
		};
	}
	
	private function castDeep(mixed $value, callable $caster): mixed
	{
		if (is_array($value)) {
			foreach ($value as $k => $v) {
				$value[$k] = $this->castDeep($v, $caster);
			}
			
			return $value;
		}
		
		if (is_object($value)) {
			return $value;
		}
		
		return $caster($value);
	}
	
	private function autoCastValue(mixed $value, ?string $key = null): mixed
	{
		if (is_array($value)) {
			foreach ($value as $k => $v) {
				$value[$k] = $this->autoCastValue($v, is_string($k) ? $k : null);
			}
			
			return $value;
		}
		
		if (!is_string($value)) {
			return $value;
		}
		
		$raw = $value;
		$value = trim($value);
		
		if ($value === '') {
			return $raw;
		}
		
		// Keep common numeric-looking string fields safe.
		if ($key !== null && preg_match('/(?:phone|mobile|postcode|zipcode|zip|code|otp|pin|token|secret|password|hash|uuid|slug)$/i', $key)) {
			return $raw;
		}
		
		// Safe integer. No leading zero except "0".
		if (preg_match('/^-?(?:0|[1-9][0-9]*)$/', $value)) {
			$int = filter_var($value, FILTER_VALIDATE_INT);
			return $int === false ? $raw : $int;
		}
		
		// Conservative decimal float. No exponent notation.
		if (preg_match('/^-?(?:0|[1-9][0-9]*)\.[0-9]+$/', $value)) {
			return (float) $value;
		}
		
		return $raw;
	}
	
	private function castBool(mixed $value): ?bool
	{
		if ($value === null) {
			return null;
		}
		
		if (is_bool($value)) {
			return $value;
		}
		
		if (is_int($value) || is_float($value)) {
			return $value != 0;
		}
		
		if (is_string($value)) {
			return match (strtolower(trim($value))) {
				'0', 'false', 'no', 'off', '' => false,
				default => true,
			};
		}
		
		return (bool) $value;
	}
	
	private function getMapCastType(array $castMap, string|int $key): ?string
	{
		return array_key_exists($key, $castMap) ? $castMap[$key] : null;
	}
	
}