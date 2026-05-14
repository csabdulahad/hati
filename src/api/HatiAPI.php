<?php

namespace Hati\API;

use Hati\Trunk;
use Hati\Util\Arr;
use Hati\Util\Request;
use Hati\Util\Text;

/**
 * An abstract implementation for APIs. Hati APIs must implement this class so that
 * the Hati API handler can invoke correct methods to server the API request.
 * It provides command useful methods needed while writing API implementations.
 *
 * API classes may be namespaced and should be registered with their fully qualified class name.
 *
 * */

abstract class HatiAPI
{

	/** Catches the request body for JSON & raw */
	private array $reqBody = [];

	/** Array containing headers came with the request */
	protected array $headers = [];
	
	/** Array containing cookies came with the request */
	protected array $cookies = [];

	/** Array containing segments after the API path */
	protected array $args = [];

	/** Array containing query parameters in the API URL */
	protected array $params = [];

	/** No authentication for API methods */
	private array $noAuthFor = [];
	
	/** Tell which HTTP verb the API is handling */
	private string $requestMethod = '';

	/**
	 * Initialize the API with necessary stuff. Any API that needs to be public,
	 * you can do so by calling {@link openAccess()} in {@link publicMethod()} method.
	 * Additionally, any query parameters & API path segments are accessible in
	 * this method using {@link args()} & {@link queryParams()}
	 * */
	public function init(): void
	{

	}

	/**
	 * Any authentication method that API requires to check. You don't need to
	 * call this method manually. {@link HatiAPIHandler} invokes this method
	 * behind the scene.
	 *
	 * Authentication check can be bypassed for any API serving method. Call
	 * {@link openAccess()} method in {@link publicMethod()} method.
	 *
	 * @param string $method Which method it is invoked for
	 * */
	public function authenticate(string $method): void
	{

	}

	/**
	 * Register any method which you want to make public.
	 * To register a method, call {@link openAccess()}.
	 * */
	public function publicMethod(): void
	{

	}

	/**
	 * Whitelist any API method, that can be accessed without authentication.
	 * HatiAPIHandler calls publicMethod() before authentication, allowing APIs
	 * to mark selected methods as public using openAccess().
	 *
	 * @param string|array $method HTTP verbs such as GET, POST, PUT, PATCH, DELETE
	 * or any extension function
	 * */
	protected function openAccess(string|array ...$method): void
	{
		$methods = Arr::varargsAsArray($method);
		
		foreach ($methods as $m) {
			if (!is_string($m)) {
				continue;
			}
			
			$m = trim($m);
			
			if ($m === '') {
				continue;
			}
			
			$m = str_contains($m, '-') ? Text::toCamelCase($m) : $m;
			
			$upper = strtoupper($m);
			
			if (in_array($upper, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
				$m = $upper;
			}
			
			$this->noAuthFor[] = $m;
		}
		
		$this->noAuthFor = array_values(array_unique($this->noAuthFor));
	}

	/**
	 * Indicates whether an API method is private or public.
	 *
	 * @param string $method The method to be checked whether it is private API method.
	 * @return true if the API method is private and requires authentication
	 * false if it is public/open-access
	 * */
	public function isPrivateMethod(string $method): bool
	{
		return !in_array($method, $this->noAuthFor, true);
	}

	/**
	 * Fetches the request body as either JSON or raw text.
	 *
	 * @param string $as The format the request body to be fetched in.
	 * @return mixed array for JSON type, string for raw type.
	 * Otherwise, null is returned.
	 * */
	protected final function requestBody(string $as = 'json'): mixed
	{
		if (!in_array($as, ['json', 'raw'], true)) {
			Trunk::http400('Request body can only be fetched either as json or as raw value');
		}

		if (array_key_exists($as, $this->reqBody)) {
			return $this->reqBody[$as];
		}

		$data = Request::body($as);
		$this->reqBody[$as] = $data;

		return $data;
	}

	/**
	 * Default handler method for GET request for the API.
	 * */
	public function get(Response $res): void
	{
		Trunk::http501('API is not implemented yet');
	}

	/**
	 * Default handler method for POST request for the API.
	 * */
	public function post(Response $res): void
	{
		Trunk::http501('API is not implemented yet');
	}

	/**
	 * Default handler method for PUT request for the API.
	 * */
	public function put(Response $res): void
	{
		Trunk::http501('API is not implemented yet');
	}

	/**
	 * Default handler method for PATCH request for the API.
	 * */
	public function patch(Response $res): void
	{
		Trunk::http501('API is not implemented yet');
	}

	/**
	 * Default handler method for DELETE request for the API.
	 * */
	public function delete(Response $res): void
	{
		Trunk::http501('API is not implemented yet');
	}

	/**
	 * Returns the header value specified by the key.
	 *
	 * @param string $key the header key
	 * @param mixed $default if the header wasn't set in the request
	 * @return mixed the header value
	 * */
	public function header(string $key, mixed $default = null): mixed
	{
		$name = $this->findHeaderName($key);
		return $name === null ? $default : $this->headers[$name];
	}
	
	/**
	 * Returns whether a specified header was set in the request.
	 *
	 * @param string $key the header key
	 * @return bool true if the header key exists in the request, false otherwise.
	 * */
	public function headerSet(string $key): bool
	{
		return $this->findHeaderName($key) !== null;
	}
	
	public function cookie(string $key, mixed $default = null): mixed
	{
		return $this->cookies[$key] ?? $default;
	}
	
	/**
	 * Returns whether a specified cookie was set in the request.
	 *
	 * @param string $key the cookie key
	 * @return bool true if the cookie key exists in the request, false otherwise.
	 * */
	public function cookieSet(string $key): bool
	{
		return array_key_exists($key, $this->cookies);
	}

	/**
	 * Returns an array containing any segments found after the API path.
	 *
	 * @return array containing segments after in the API path
	 * */
	public function args(): array
	{
		return $this->args;
	}

	/**
	 * Returns an array containing any query parameters found in the
	 * request API URL.
	 *
	 * @return array containing query parameters in the URL
	 * */
	public function queryParams(): array
	{
		return $this->params;
	}

	/**
	 * Fetches a query parameter value from the request URL.
	 *
	 * @param string $key The query parameter key
	 * @param mixed $default Any value if the key doesn't exist
	 *
	 * @return mixed The value by the key from the query parameter
	 * */
	protected function param(string $key, mixed $default = null): mixed
	{
		return $this->queryParams()[$key] ?? $default;
	}

	/**
	 * Retrieves the segment value at the specified position in the arguments array.
	 * Default value is returned if the segment value is missing.
	 *
	 * @param int $pos The position in the arguments array to retrieve the value from.
	 * @param mixed $default The default value to return if the position is not set.
	 * @return mixed The value at the specified position in the arguments array, or the default value.
	 */
	protected function arg(int $pos, mixed $default = null): mixed
	{
		return $this->args()[$pos] ?? $default;
	}

	public function setHeaders(array $headers): void
	{
		$this->headers = $headers;
	}
	
	public function setCookies(array $cookies): void
	{
		$this->cookies = $cookies;
	}

	public function setArgs(array $args): void
	{
		$this->args = $args;
	}

	public function setParams(array $params): void
	{
		$this->params = $params;
	}
	
	public function setRequestMethod(string $method): void
	{
		$this->requestMethod = strtoupper(trim($method));
	}
	
	public function setBody(mixed $body): void
	{
		if ($body !== null) {
			$this->reqBody['json'] = $body;
		}
	}
	
	public function setRawBody(?string $rawBody): void
	{
		if ($rawBody !== null) {
			$this->reqBody['raw'] = $rawBody;
		}
	}
	
	public function requestMethod(): string
	{
		return $this->requestMethod;
	}
	
	public function isMethod(string|array $methods): bool
	{
		if (is_string($methods)) {
			$methods = [$methods];
		}
		
		$methods = array_map(
			static fn(string $method): string => strtoupper(trim($method)),
			$methods
		);
		
		return in_array($this->requestMethod, $methods, true);
	}
	
	public function requireMethod(string|array $methods, string $msg = 'Unacceptable request method'): void
	{
		if (!$this->isMethod($methods)) {
			Trunk::http405($msg);
		}
	}
	
	private function findHeaderName(string $key): ?string
	{
		$key = strtolower(trim($key));
		
		foreach ($this->headers as $name => $_) {
			if (strtolower((string) $name) === $key) {
				return (string) $name;
			}
		}
		
		return null;
	}
	
}