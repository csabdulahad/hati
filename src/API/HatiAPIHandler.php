<?php

/** @noinspection SpellCheckingInspection */

namespace Hati\API;

use Hati\Trunk;
use Hati\Util\Request;
use Hati\Util\Text;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Default handler implementation for API requests. This class makes it super simple
 * to build up APIs. It handles the API request routing to right class down to right
 * method. It validates request method and API path matching. For any error, it handles
 * the reporting back to the requester with correct HTTP status code & message.
 * */

final class HatiAPIHandler
{

	private const SUPPORTED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

	private bool $debug;
	
	// default casting policy for Response objects created by the handler
	private string $responseCastBehavior = Response::CAST_DEFAULT;

	/**
	 * Path-keyed route registry.
	 *
	 * [
	 *     'v1/user' => [
	 *         'path' => 'v1/user',
	 *         'handler' => UserAPI::class,
	 *         'methods' => ['GET' => true, 'POST' => true],
	 *         'extensions' => ['reset-password' => 'resetPassword']
	 *     ]
	 * ]
	 */
	private array $apis = [];
	
	/**
	 * Creates a new API handler instance. The handler owns its own API route registry.
	 *
	 * When debug mode is enabled, unexpected implementation errors are returned with
	 * file and line information. When disabled, implementation errors are returned as
	 * a generic 500 API response.
	 *
	 * @param bool $debug Whether implementation errors should expose detailed debug information.
	 * @param string $responseCastBehavior Default Response casting policy.
	 *                                     Allowed: Response::CAST_DEFAULT, Response::CAST_AUTO.
	 */
	public function __construct(bool $debug = false, string $responseCastBehavior = Response::CAST_DEFAULT)
	{
		$this->debug = $debug;
		$this->setDefaultResponseCasting($responseCastBehavior);
	}
	
	/**
	 * Handles an API request and returns a response array.
	 *
	 * If no request array is provided, the handler builds one from the native PHP
	 * request environment. If a request array is provided, it should contain the API
	 * path, request method, query params, headers, cookies, and optional body data.
	 *
	 * Request array shape:
	 * [
	 *     'method' => 'GET|POST|PUT|PATCH|DELETE',
	 *     'api' => 'v1/user/login?id=10',
	 *     'params' => [],
	 *     'headers' => [],
	 *     'cookies' => [],
	 *     'body' => null,
	 *     'raw_body' => null
	 * ]
	 *
	 * The handler resolves the registered API route, checks extension functions before
	 * HTTP verb methods, injects request data into the API class, runs the API lifecycle,
	 * and catches Trunk responses.
	 *
	 * Returned response array shape:
	 * [
	 *     'code' => 200,
	 *     'headers' => [],
	 *     'cookies' => [],
	 *     'body' => ''
	 * ]
	 *
	 * @param ?array $request Optional request array. If omitted, native PHP request data is used.
	 * @return array Response array containing HTTP status code, headers, cookies, and body.
	 */
	public function handle(?array $request = null): array
	{
		try {
			$request = $this->normalizeRequest($request);
			
			$route = $this->matchRoute($request['api']);
			
			if ($route === null) {
				Trunk::http400('Unknown API');
			}
			
			$segments = $this->extraSegments($request['api'], $route['path']);
			$target = $this->resolveTarget($route, $segments, $request['method']);
			
			$api = $this->createAPI($route['handler']);
			
			$api->setHeaders($request['headers']);
			$api->setCookies($request['cookies']);
			$api->setArgs($target['args']);
			$api->setParams($request['params']);
			
			$api->setRequestMethod($request['method']);
			
			if (method_exists($api, 'setBody')) {
				$api->setBody($request['body']);
			}
			
			if (method_exists($api, 'setRawBody')) {
				$api->setRawBody($request['raw_body']);
			}
			
			$api->init();
			$api->publicMethod();
			
			if ($api->isPrivateMethod($target['auth_name'])) {
				$api->authenticate($target['auth_name']);
			}
			
			$response = $this->createResponse();
			
			$method = $target['target'];
			$api->$method($response);
			
			Trunk::http501('API did not produce a response');
			
		} catch (Trunk $e) {
			return $e->toArray();
			
		} catch (Throwable $e) {
			return $this->internalError($e);
		}
	}
	
	/**
	 * Registers an API endpoint with this handler.
	 *
	 * Each API must define a path, a fully qualified handler class name, and one or
	 * more supported HTTP methods. The handler class must exist, be instantiable, and
	 * extend {@link HatiAPI}. File-path handlers are not supported.
	 *
	 * The `extension` value is optional and may be either a string or an array of
	 * strings. Extensions are matched as the first path segment after the registered
	 * API path and take precedence over HTTP verb dispatch.
	 *
	 * Extension names are registered as endpoint path segments. If an extension uses
	 * kebab-case, it is converted to camelCase when resolving the PHP method name.
	 *
	 * Example:
	 * <code>
	 * [
	 *     'path' => 'v1/user',
	 *     'handler' => \App\Api\UserAPI::class,
	 *     'method' => ['GET', 'POST'],
	 *     'extension' => ['login', 'reset-password']
	 * ]
	 * </code>
	 *
	 * This registers:
	 * - GET/POST /v1/user to the get()/post() methods
	 * - /v1/user/login to login()
	 * - /v1/user/reset-password to resetPassword()
	 *
	 * Re-registering the same path with the same handler merges methods and extensions.
	 * Re-registering the same path with a different handler is treated as a registry error.
	 *
	 * @param array $api API endpoint configuration.
	 * @return HatiAPIHandler Returns this handler for method chaining.
	 */
	public function register(array $api): HatiAPIHandler
	{
		$handlerClass = $api['handler'] ?? null;
		
		if (!is_string($handlerClass) || trim($handlerClass) === '') {
			Trunk::http500('API-Registry: API must have a handler class');
		}
		
		if (str_ends_with($handlerClass, '.php')) {
			Trunk::http500('API-Registry: Handler must be a fully qualified class name, not a PHP file path');
		}
		
		if (!class_exists($handlerClass)) {
			Trunk::http500('API-Registry: Handler class does not exist: ' . $handlerClass);
		}
		
		if (!is_subclass_of($handlerClass, HatiAPI::class)) {
			Trunk::http500('API-Registry: Handler must extend ' . HatiAPI::class);
		}
		
		try {
			$reflection = new ReflectionClass($handlerClass);
		} catch (ReflectionException $e) {
			Trunk::http500('API-Registry: Failed to register API: ' . $e->getMessage());
		}
		
		if (!$reflection->isInstantiable()) {
			Trunk::http500('API-Registry: Handler class is not instantiable: ' . $handlerClass);
		}
		
		$path = $this->normalizePath($api['path'] ?? '');
		
		if ($path === '') {
			Trunk::http500('API-Registry: API path is missing');
		}
		
		$methods = $this->normalizeMethods($api['method'] ?? null);
		$extensions = $this->normalizeExtensions($handlerClass, $api['extension'] ?? null);
		
		if (isset($this->apis[$path])) {
			$existing = $this->apis[$path];
			
			if ($existing['handler'] !== $handlerClass) {
				Trunk::http500('API-Registry: API path already registered with a different handler: ' . $path);
			}
			
			$this->apis[$path]['methods'] = array_values(array_unique([
				...$existing['methods'],
				...$methods
			]));
			
			foreach ($extensions as $endpoint => $method) {
				if (
					isset($this->apis[$path]['extensions'][$endpoint]) &&
					$this->apis[$path]['extensions'][$endpoint] !== $method
				) {
					Trunk::http500('API-Registry: Extension already registered differently: ' . $endpoint);
				}
				
				$this->apis[$path]['extensions'][$endpoint] = $method;
			}
			
			return $this;
		}
		
		$this->apis[$path] = [
			'path' => $path,
			'handler' => $handlerClass,
			'methods' => $methods,
			'extensions' => $extensions
		];
		
		return $this;
	}

	/**
	 * Calls a registered API internally and returns the response array.
	 *
	 * This method is the internal-call equivalent of handling an HTTP request. It does
	 * not use HTTP, does not emit output, and does not terminate the process. The call
	 * is routed through the same handler logic as a normal request.
	 *
	 * The API path may contain a query string. Query params found in the API path are
	 * merged with the provided params array, with explicitly provided params taking
	 * precedence.
	 *
	 * @param string $method HTTP request method such as GET, POST, PUT, PATCH, or DELETE.
	 * @param string $api API path, optionally including query string.
	 * @param array $params Query parameters to inject into the API.
	 * @param array $headers Request headers to inject into the API.
	 * @param array $cookies Request cookies to inject into the API.
	 * @param mixed $body Parsed request body value, if available.
	 * @param ?string $rawBody Raw request body string, if available.
	 * @return array Response array containing HTTP status code, headers, cookies, and body.
	 */
	public function call(string $method, string $api, array $params = [], array $headers = [], array $cookies = [], mixed $body = null, ?string $rawBody = null): array
	{
		return $this->handle([
			'method' => $method,
			'api' => $api,
			'params' => $params,
			'headers' => $headers,
			'cookies' => $cookies,
			'body' => $body,
			'raw_body' => $rawBody
		]);
	}
	
	/**
	 * Runs a callable as a Hati-style API action.
	 *
	 * The callable receives a {@link Response} object and is expected to finalize the
	 * response by calling {@link Response::reply()}. If the callable does not produce
	 * a response, a 501 API response is returned.
	 *
	 * This method is useful for small API-like tasks, tests, scripts, or adapters that
	 * want the Hati response flow without registering a full API class and route.
	 *
	 * @param callable $fun Function to execute. It receives a Response object.
	 * @return array Response array containing HTTP status code, headers, cookies, and body.
	 */
	public function run(callable $fun): array
	{
		try {
			$response = $this->createResponse();
			
			$fun($response);
			
			Trunk::http501('API did not produce a response');
			
		} catch (Trunk $e) {
			return $e->toArray();
			
		} catch (Throwable $e) {
			return $this->internalError($e);
		}
	}
	
	/**
	 * Sets the default casting policy for Response objects created by this handler.
	 *
	 * This affects responses created inside handle() and run(). Only response-level
	 * policies are allowed; explicit scalar casts such as CAST_INT, CAST_FLOAT,
	 * CAST_BOOL, and CAST_STRING remain per-value Response rules.
	 *
	 * @param string $responseCastBehavior Response::CAST_DEFAULT or Response::CAST_AUTO.
	 * @return HatiAPIHandler
	 *
	 * @throws InvalidArgumentException If the casting policy is invalid.
	 */
	public function setDefaultResponseCasting(string $responseCastBehavior): HatiAPIHandler
	{
		if (!in_array($responseCastBehavior, [Response::CAST_DEFAULT, Response::CAST_AUTO], true)) {
			throw new InvalidArgumentException('Invalid response cast behavior.');
		}
		
		$this->responseCastBehavior = $responseCastBehavior;
		return $this;
	}
	
	/**
	 * Returns the default casting policy for Response objects created by this handler.
	 */
	public function getDefaultResponseCasting(): string
	{
		return $this->responseCastBehavior;
	}
	
	private static function getFullErrorMsg(Throwable $e): string
	{
		return sprintf(
			'%s in %s at line %s',
			ucfirst($e->getMessage()),
			$e->getFile(),
			$e->getLine()
		);
	}
	
	private function normalizeMethods(mixed $methods): array
	{
		if (empty($methods)) {
			Trunk::http500('API-Registry: API must define a request method');
		}
		
		if (is_string($methods)) {
			$methods = [$methods];
		}
		
		if (!is_array($methods)) {
			Trunk::http500('API-Registry: API request method must be a string or array');
		}
		
		$out = [];
		
		foreach ($methods as $method) {
			if (!is_string($method)) {
				Trunk::http500('API-Registry: API request method must be a string');
			}
			
			$method = strtoupper(trim($method));
			
			if (!in_array($method, self::SUPPORTED_METHODS, true)) {
				Trunk::http500('API-Registry: API request method must be one of: ' . implode(', ', self::SUPPORTED_METHODS));
			}
			
			$out[] = $method;
		}
		
		return array_values(array_unique($out));
	}
	
	private function normalizeExtensions(string $handlerClass, mixed $extensions): array
	{
		if (empty($extensions)) {
			return [];
		}
		
		if (is_string($extensions)) {
			$extensions = [$extensions];
		}
		
		if (!is_array($extensions)) {
			Trunk::http500('API-Registry: Extension must be a string or array');
		}
		
		$map = [];
		
		foreach ($extensions as $extension) {
			if (!is_string($extension)) {
				Trunk::http500('API-Registry: Extension must be a string');
			}
			
			$endpoint = trim($extension, '/');
			
			if ($endpoint === '') {
				Trunk::http500('API-Registry: Extension cannot be empty');
			}
			
			if (str_contains($endpoint, '/')) {
				Trunk::http500('API-Registry: Extension must be a single path segment: ' . $endpoint);
			}
			
			$method = str_contains($endpoint, '-')
				? Text::toCamelCase($endpoint)
				: $endpoint;
			
			if (!method_exists($handlerClass, $method)) {
				Trunk::http500("API-Registry: Extension method is missing: $handlerClass::$method()");
			}
			
			try {
				$reflection = new ReflectionMethod($handlerClass, $method);
			} catch (ReflectionException $e) {
				Trunk::http500('API-Registry: Failed to register API: ' . $e->getMessage());
			}
			
			if (!$reflection->isPublic()) {
				Trunk::http500("API-Registry: Extension method must be public: $handlerClass::$method()");
			}
			
			$map[$endpoint] = $method;
		}
		
		return $map;
	}
	
	private function normalizeRequest(?array $request): array
	{
		$request ??= $this->nativeRequest();
		
		[$api, $apiQueryParams] = $this->splitApiAndQuery($request['api'] ?? '');
		
		if ($api === '') {
			Trunk::http400('Bad request');
		}
		
		$method = strtoupper(trim((string) ($request['method'] ?? '')));
		
		if (!in_array($method, self::SUPPORTED_METHODS, true)) {
			Trunk::http405('Unacceptable request method');
		}
		
		$params = $request['params'] ?? [];
		$headers = $request['headers'] ?? [];
		$cookies = $request['cookies'] ?? [];
		
		if (!is_array($params)) {
			Trunk::http400('Invalid request params');
		}
		
		if (!is_array($headers)) {
			Trunk::http400('Invalid request headers');
		}
		
		if (!is_array($cookies)) {
			Trunk::http400('Invalid request cookies');
		}
		
		// Query params embedded in API path are supported for internal calls/tests.
		// Explicit params win over query-string params.
		$params = array_merge($apiQueryParams, $params);
		
		return [
			'method' => $method,
			'api' => $api,
			'params' => $params,
			'headers' => $headers,
			'cookies' => $cookies,
			'body' => $request['body'] ?? null,
			'raw_body' => $request['raw_body'] ?? null
		];
	}
	
	private function normalizePath(mixed $path): string
	{
		if (!is_string($path)) {
			return '';
		}
		
		$path = trim($path);
		$path = explode('?', $path, 2)[0];
		$path = trim($path, '/');
		$path = preg_replace('#/+#', '/', $path);
		
		return $path ?? '';
	}
	
	private function nativeRequest(): array
	{
		$params = $_GET ?? [];
		$api = $params['api'] ?? null;
		unset($params['api']);
		
		return [
			'method' => Request::method(),
			'api' => $api,
			'params' => $params,
			'headers' => $this->nativeHeaders(),
			'cookies' => $_COOKIE ?? [],
			'body' => null,
			'raw_body' => file_get_contents('php://input') ?: null
		];
	}
	
	private function nativeHeaders(): array
	{
		if (function_exists('getallheaders')) {
			$headers = getallheaders();
			
			return is_array($headers) ? $headers : [];
		}
		
		$headers = [];
		
		foreach ($_SERVER ?? [] as $key => $value) {
			if (str_starts_with($key, 'HTTP_')) {
				$name = substr($key, 5);
				$name = str_replace('_', '-', strtolower($name));
				$name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
				
				$headers[$name] = $value;
			}
		}
		
		return $headers;
	}
	
	private function matchRoute(string $path): ?array
	{
		$routes = $this->apis;
		
		uksort($routes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
		
		foreach ($routes as $routePath => $route) {
			if ($this->pathMatches($path, $routePath)) {
				return $route;
			}
		}
		
		return null;
	}
	
	private function pathMatches(string $path, string $routePath): bool
	{
		return $path === $routePath || str_starts_with($path, $routePath . '/');
	}
	
	private function extraSegments(string $path, string $routePath): array
	{
		if ($path === $routePath) {
			return [];
		}
		
		$extra = trim(substr($path, strlen($routePath)), '/');
		
		if ($extra === '') {
			return [];
		}
		
		return explode('/', $extra);
	}
	
	private function resolveTarget(array $route, array $segments, string $httpMethod): array
	{
		$firstSegment = $segments[0] ?? null;
		
		if ($firstSegment !== null && isset($route['extensions'][$firstSegment])) {
			return [
				'target' => $route['extensions'][$firstSegment],
				'auth_name' => $route['extensions'][$firstSegment],
				'args' => array_slice($segments, 1),
				'is_extension' => true
			];
		}
		
		if (!in_array($httpMethod, $route['methods'], true)) {
			Trunk::http405('Unacceptable request method');
		}
		
		return [
			'target' => strtolower($httpMethod),
			'auth_name' => $httpMethod,
			'args' => $segments,
			'is_extension' => false
		];
	}
	
	private function internalError(Throwable $e): array
	{
		$msg =
			$this->debug
			? self::getFullErrorMsg($e)
			: 'Error in API implementation';
		
		return (new Trunk(
			msg: $msg,
			httpStatusCode: 500,
			status: Response::ERROR,
			previous: $e
		))->toArray();
	}
	
	private function createAPI(string $handlerClass): HatiAPI
	{
		$api = new $handlerClass();
		
		if (!$api instanceof HatiAPI) {
			Trunk::http500('Unsupported API implementation');
		}
		
		return $api;
	}
	
	private function splitApiAndQuery(mixed $api): array
	{
		if (!is_string($api)) {
			return ['', []];
		}
		
		$api = trim($api);
		
		[$path, $queryString] = array_pad(explode('?', $api, 2), 2, '');
		
		$queryParams = [];
		
		if ($queryString !== '') {
			parse_str($queryString, $queryParams);
		}
		
		return [
			$this->normalizePath($path),
			$queryParams
		];
	}
	
	private function createResponse(): Response
	{
		return new Response($this->responseCastBehavior);
	}
	
}