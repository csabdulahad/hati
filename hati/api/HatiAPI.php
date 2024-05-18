<?php

namespace hati\api;

use hati\Hati;
use hati\Trunk;
use hati\util\Arr;
use hati\util\Request;

/**
 * An abstract implementation for APIs. Hati APIs must implement this class so that
 * the Hati API handler can invoke correct methods to server the API request.
 * It provides command useful methods needed while writing API implementations.
 *
 * The derived class must not have any namespace, as it will make the loader fail.
 *
 * @since 5.0.0
 * */

abstract class HatiAPI {

	/** Catches the request body for JSON & raw */
	private array $reqBody = [];

	/** Array containing headers came with the request */
	protected array $headers = [];

	/** Array containing segments after the API path */
	protected array $args = [];

	/** Array containing query parameters in the API URL */
	protected array $params = [];

	/** No authentication for API methods */
	private array $noAuthFor = [];

	/**
	 * Initialize the API with necessary stuff. Any API that needs to be public,
	 * you can do so by calling {@link openAccess()} in {@link publicMethod()} method.
	 * Additionally, any query parameters & API path segments are accessible in
	 * this method using {@link args()} & {@link queryParams()}
	 * */
	public function init(): void {

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
	public function authenticate(string $method): void {

	}

	/**
	 * Register any method which you want to make public.
	 * To register a method, call {@link openAccess()}.
	 * */
	public function publicMethod(): void {

	}

	/**
	 * Whitelist any API method, that can be accessed without authentication.
	 * HatiAPIHandler respects this by listing APIs access in the {@link init()}
	 * method as defined using {@link openAccess()} before calling authentication
	 * on any API serving method [HTTP verbs or extension functions] invocation.
	 *
	 * @param string|array $method HTTP verbs such as GET, POST, PUT, PATCH, DELETE
	 * or any extension function
	 * */
	protected function openAccess(string|array ...$method): void {
		$method = Arr::varargsAsArray($method);
		$this -> noAuthFor = array_merge($this -> noAuthFor, $method);
	}

	/**
	 * Helper method to load any resource file easily. By default, path
	 * is relative to the API handler file.
	 *
	 * @param string $path the file path
	 * @param bool $root when set true, resource is loaded relative to root
	 * directory where the vendor folder is found. Otherwise it is relative
	 * to API handler file.
	 *
	 * @return mixed resource
	 * */
	protected function loadResource(string $path, bool $root = false): mixed {
		if ($root) {
			$path = Hati::root($path);
		}

		return (require $path);
	}

	/**
	 * Indicate whether an API method is private or public.
	 *
	 * @param string $method The method to be checked whether it is private API method.
	 * @return true if the API method is private, needing no authentication; false otherwise.
	 * */
	public function isPrivateMethod(string $method): bool {
		return !in_array($method, $this -> noAuthFor);
	}

	/**
	 * Prevents direct access to the API handler file by checking whether
	 * the HATI_API_CALL constant was defined by the hati_api_handler.php
	 * file.
	 *
	 * This method should be the first call in the API handler files to
	 * prevent direct access.
	 * */
	public static function noDirectAccess(): void {
		if (!defined('HATI_API_CALL')) {
			$trunk = Trunk::error403('No direct access');
			$trunk -> report();
		}
	}

	/**
	 * Fetches the request body as either JSON or raw text.
	 *
	 * @param string $as The format the request body to be fetched in.
	 * @return string|array|null array for JSON type, string for raw type.
	 * Otherwise null is returned.
	 * */
	protected final function requestBody(string $as = 'json'): string|array|null {

		if (!in_array($as, ['json', 'raw'])) {
			throw Trunk::error400('Request body can only be fetched either as json or as raw value');
		}

		if (array_key_exists($as, $this -> reqBody)) {
			return $this -> reqBody[$as];
		}

		$data = Request::body($as);
		$this -> reqBody[$as] = $data;

		return $data;
	}

	/**
	 * Default handler method for GET request for the API.
	 * */
	public function get(Response $res): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for GET request for the API.

	 * */
	public function post(Response $res): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for POST request for the API.

	 * */
	public function put(Response $res): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for PATCH request for the API.
	 * */
	public function patch(Response $res): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Default handler method for DELETE request for the API.

	 * */
	public function delete(Response $res): void {
		throw Trunk::error501('API is not implemented yet');
	}

	/**
	 * Returns the header value specified by the key.
	 *
	 * @param string $key the header key
	 * @param mixed $default if the header wasn't set in the request
	 * @return mixed the header value
	 * */
	public function header(string $key, mixed $default = null): mixed {
		return $this -> headers[$key] ?? $default;
	}

	/**
	 * Returns whether a specified header was set in the request.
	 *
	 * @param string $key the header key
	 * @return bool true if the header key exists in the request, false otherwise.
	 * */
	public function headerSet(string $key): bool {
		return key_exists($key, $this -> headers);
	}

	/**
	 * Returns an array containing any segments found after the API path.
	 *
	 * @reutrn array containing segments after in the API path
	 * */
	public function args(): array {
		return $this -> args;
	}

	/**
	 * Returns an array containing any query parameters found in the
	 * request API URL.
	 *
	 * @reutrn array containing query parameters in the URL
	 * */
	public function queryParams(): array {
		return $this -> params;
	}

	/**
	 * Fetches a query parameter value from the request URL.
	 *
	 * @param string $key The query parameter key
	 * @param mixed $default Any value if the key doesn't exist
	 *
	 * @return mixed The value by the key from the query parameter
	 * */
	protected function param(string $key, mixed $default = null): mixed {
		return $this -> queryParams()[$key] ?? $default;
	}

	/**
	 * Retrieves the segment value at the specified position in the arguments array.
	 * Default value is returned if the segment value is missing.
	 *
	 * @param int $pos The position in the arguments array to retrieve the value from.
	 * @param mixed $default The default value to return if the position is not set.
	 * @return mixed The value at the specified position in the arguments array, or the default value.
	 */
	protected function arg(int $pos, mixed $default = null): mixed {
		return $this -> args()[$pos] ?? $default;
	}

	public function setHeaders(array $headers): void {
		$this -> headers = $headers;
	}

	public function setArgs(array $args): void {
		$this -> args = $args;
	}

	public function setParams(array $params): void {
		$this -> params = $params;
	}

}