<?php

namespace hati\api;

use hati\filter\Filter;
use hati\Trunk;
use hati\util\Request;

/**
 * Default handler implementation for API requests. This class makes it super simple
 * to build up APIs. It handles the API request routing to right class down to right
 * method. It validates request method and API path matching. For any error, it handles
 * the reporting back to the requester with correct HTTP status code & message.
 *
 * @since 5.0.0
 * */

final class HatiAPIHandler {

	private static ?HatiAPIHandler $handler = null;

	/** Internal buffer for API configurations */
	private array $apis = [];

	private function __construct() {

	}

	private static function get(): HatiAPIHandler {
		if (is_null(self::$handler)) {
			self::$handler = new HatiAPIHandler();
		}
		return self::$handler;
	}

	/**
	 * Initialize the API handler. It performs various checks such as request method
	 * validation and API path checks, calling appropriate handler with correct method.
	 * To register APIs, use {@link HatiAPIHandler::register()} in 'api/hati_api_registry.php'
	 * file.
	 *
	 * For any error while booting up, error message is written out as standard API response with
	 * proper HTTP status code.
	 *
	 * <br> This method should only be called from the 'api/hati_api_handler.php' file.
	 * */
	public static function boot(): void {
		try {
			$handler = self::get();

			/*
			 * Register all the API endpoints path
			 * */
			$registry = getcwd() . DIRECTORY_SEPARATOR . 'hati_api_registry.php';
			if (!file_exists($registry)) {
				throw Trunk::error500('API registry is missing');
			}

			require_once $registry;

			// Is it an API thing?
			if (empty($_GET['api'])) {
				throw Trunk::error400('Bad request');
			}

			$path = Filter::string($_GET['api']);
			if (!Filter::ok($path)) {
				throw Trunk::error400('Bad request');
			}

			// Check the request method
			$method = strtoupper(Request::method());
			if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
				throw Trunk::error405('Unacceptable request method');
			}

			$paths = $handler -> apis[$method] ?? null;
			if (empty($paths)) {
				throw Trunk::error400('API is not supported');
			}

			// Calculate which API request it is
			$path = trim($path, '/');
			$paths = array_keys($paths);
			$apiPathKey = null;

			foreach ($paths as $apiPath) {
				if (str_starts_with($path, $apiPath)) {
					$apiPathKey = $apiPath;
					break;
				}
			}

			if (empty($apiPathKey)) {
				throw Trunk::error400('Unknown API');
			}

			// Extract arguments from the request API path
			$registeredAPIPathLen = strlen($apiPathKey);
			$arguments = substr($path, $registeredAPIPathLen);
			$arguments = trim($arguments, '/');
			$arguments = explode('/', $arguments);

			// Build up the query params
			$queryParams = $_REQUEST;
			unset($queryParams['api']);

			// Get the API endpoint details
			$arr = $handler -> apis[$method][$apiPathKey] ?? null;
			if (is_null($arr)) {
				throw Trunk::error500('Server failed to create a response');
			}

			// Load the API class file!
			$class = basename($arr['handler']);
			$class = substr($class, 0, strpos($class, '.'));

			// Import the class & test it exists
			require $arr['handler'];
			$class = new $class;

			if (!$class instanceof HatiAPI) {
				throw Trunk::error500('Unsupported API implementation');
			}

			// Invoke the right method
			$functions = $arr['func'] ?? [];
			$func = null;
			foreach ($functions as $f) {
				foreach ($arguments as $a) {
					if ($f == $a) {
						$func = $f;
						break 2;
					}
				}
			}

			// Adjust the arguments
			if (!is_null($func)) {
				$arguments = implode('/', $arguments);

				$start = strpos($arguments, $func);
				$len = strlen($func);

				$arguments = substr($arguments, $start + $len);
				$arguments = trim($arguments, '/');
				$arguments = explode('/', $arguments);

				if (!method_exists($class, $func)) {
					throw Trunk::error501('Unimplemented API');
				}

				$class -> $func($arguments, $queryParams);
			} else {

				if (!method_exists($class, $method)) {
					throw Trunk::error405('Unacceptable request method');
				}

				$class -> $method($arguments, $queryParams);
			}
		} catch (Trunk $e) {
			$e -> report();
		}
	}

	/**
	 * Registers API endpoints with handler. An API can be registered with various configurations.
	 * Default Hati API handler supports the following HTTP verbs: GET, POST, PUT, PATCH, DELETE.
	 * See below as an example:
	 * <code>
	 * HatiAPIHandler::register([
	 * 	// HTTP verb the API wants the request method of
	 * 	'method' => 'GET',
	 *
	 * 	// API endpoint path which can be called as: http://example.com/api/v1/test
	 * 	'path' => 'v1/test',
	 *
	 * 	// Relative folder path found in the api folder; here 'v1' is the version folder.
	 * 	'handler' => 'v1/TestAPI.php',
	 *
	 * 	// Any function to be invoked via api.
	 * 	// It can also be an array, for example: 'func' => ['method1', 'method2']
	 * 	// e.g: http://example.com/api/v1/test/testFun/arg1?param1=value1 will invoke
	 * 	// 'testFun' public method in v1/TestAPI.php with argument array ['arg1'], and
	 * 	// query parameters array ['param1' => 'value1'].
	 * 	'func' => 'testFun'
	 * ]);
	 * </code>
	 *
	 * Functions registered for the API and default methods for supported HTTP verb, will be invoked with two array
	 * arguments. One for the segment array (anything after the API path), another for the query parameters found in
	 * the API url.
	 *
	 * The handler class file (for example TestAPI.php) must be an implementation of {@link HatiAPI} with the methods
	 * defined by that 'func' field.
	 *
	 * For any error while registering an API, this method throws error like an API response with 'API-Registry'
	 * appended to error message and 500 as HTTP status code to indicate that the api registration failed to the
	 * developer/tester.
	 *
	 * @param array $api The API endpoint configuration
	 * */
	public static function register(array $api): void {
		try {
			$handler = self::get();

			$method = $api['method'] ?? null;
			if (empty($method)) {
				throw Trunk::error500('API-Registry: API must define a request method');
			}

			if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
				throw Trunk::error500('API-Registry: API request method must be of: GET, POST, PUT, PATCH, DELETE');
			}

			$path = $api['path'] ?? null;
			if (empty($path)) {
				throw Trunk::error500('API-Registry: API path is missing');
			}

			$file = $api['handler'] ?? null;
			if (empty($file)) {
				throw Trunk::error500('API-Registry: API must have a handler file');
			}

			$filePath = getcwd() . DIRECTORY_SEPARATOR . $file;
			if (!file_exists($filePath)) {
				throw Trunk::error500("API-Registry: Handler is missing for: $method $path");
			}

			$func = $api['func'] ?? null;
			if (!empty($func)) {
				$func = is_string($func) ? [$func] : $func;
			}

			// Register the API!
			$handler->apis[$method] = [
				$path => [
					'handler' => $filePath,
					'func' => $func
				]
			];
		} catch (Trunk $e) {
			$e -> report();
		}
	}

}