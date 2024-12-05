<?php

namespace hati\api;

use hati\config\Key;
use hati\filter\Filter;
use hati\Hati;
use hati\Trunk;
use hati\util\Arr;
use hati\util\Request;
use hati\util\Text;
use ReflectionClass;
use Throwable;

/**
 * Default handler implementation for API requests. This class makes it super simple
 * to build up APIs. It handles the API request routing to right class down to right
 * method. It validates request method and API path matching. For any error, it handles
 * the reporting back to the requester with correct HTTP status code & message.
 *
 * @since 5.0.0
 * */

final class HatiAPIHandler {

	/**
	 * When set true, any php syntax/runtime error will be sent back
	 * as API response message. Otherwise, simple error message will
	 * be sent with 500 error code.
	 * */
	public static bool $DEBUG = false;

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
	 * <br> This method should only be called from the 'api/hati_api_handler.php' file or by the
	 * Hati library internally for example HatiAPI class.
	 *
	 * @param ?array $augment Array contains augmented values to call API by code.
	 * */
	public static function boot(?array $augment = null): ?array {
		$res = new Response();
		$cwd = getcwd();
		
		try {
			$handler = self::get();
			
			/*
			 * Figure out the registry location
			 * */
			$registry = is_null($augment) ? getcwd() : Hati::root(Hati::config(Key::API_REGISTRY_FOLDER));

			/*
			 * Register all the API endpoints path
			 * */
			$registry .= DIRECTORY_SEPARATOR . 'hati_api_registry.php';
			
			if (!file_exists($registry)) {
				throw Trunk::error500('API registry is missing');
			}

			require_once $registry;

			// Is it an API thing?
			if (empty($augment['api']) && empty($_GET['api'])) {
				throw Trunk::error400('Bad request');
			}

			$path = Filter::checkString($augment['api'] ?? $_GET['api']);
			if (!Filter::isOK($path)) {
				throw Trunk::error400('Bad request');
			}

			// Check the request method
			$method = strtoupper($augment['method'] ?? Request::method());

			if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
				throw Trunk::error405('Unacceptable request method');
			}

			$paths = $handler->apis[$method] ?? null;
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
			
			// Remove query params from the path including ? mark
			$arguments = strtok($arguments, '?');
			
			$arguments = explode('/', $arguments);

			// Build up the query params
			$queryParams = $augment['params'] ?? $_REQUEST;
			unset($queryParams['api']);

			// Get the API endpoint details
			$arr = $handler->apis[$method][$apiPathKey] ?? null;
			if (is_null($arr)) {
				throw Trunk::error500('Server failed to create a response');
			}

			// Load the API class file!
			$class = $arr['handler'];

			if (str_ends_with($class, '.php')) {
				$class = basename($arr['handler']);
				$class = substr($class, 0, strpos($class, '.'));

				// Import the class file
				require $arr['handler'];
			}

			$class = new $class;

			if (!$class instanceof HatiAPI) {
				throw Trunk::error500('Unsupported API implementation');
			}

			// Invoke the right method
			$functions = $arr['extension'] ?? [];
			$func = null;
			foreach ($functions as $f) {
				foreach ($arguments as $a) {
					// Do we need to make it camel cased function/method name?
					if (str_contains($a, '-')) {
						$a = Text::toCamelCase($a);
					}
					
					if ($f == $a) {
						$func = $f;
						break 2;
					}
				}
			}

			// Adjust the arguments
			if (!is_null($func)) {
				$arguments = implode('/', $arguments);

				/*
				 * Let's try with camel case version
				 * */
				if (str_contains($arguments, Text::deCamelCase($func))) {
					$caseNeutral = Text::deCamelCase($func);
					$start = strpos($arguments, $caseNeutral);
					$len = strlen($caseNeutral);
				} else {
					/*
					 * path has the extension name as is in the API class
					 * */
					$start = strpos($arguments, $func);
					$len = strlen($func);
				}

				$arguments = substr($arguments, $start + $len);
				$arguments = trim($arguments, '/');
				$arguments = explode('/', $arguments);

				if (!method_exists($class, $func)) {
					throw Trunk::error501('Unimplemented API');
				}

				$method = $func;
			} else {

				if (!method_exists($class, $method)) {
					throw Trunk::error405('Unacceptable request method');
				}

			}

			// Adjust empty segments
			if (count($arguments) == 1 && empty($arguments[0])) {
				$arguments = [];
			}

			// #1 Set various properties
			$headers = array_merge(getallheaders(), $augment['headers'] ?? []);
			$cookies = array_merge($_COOKIE ?? [], $augment['cookies'] ?? []);
			
			$class->setHeaders($headers);
			$class->setCookies($cookies);
			$class->setArgs($arguments);
			$class->setParams($queryParams);

			// #1.1 Set the handler as working directory
			$reflection = new ReflectionClass($class);
			$directory  = pathinfo($reflection->getFileName(), PATHINFO_DIRNAME);
			chdir($directory);

			// #2 Initialize the API
			$class->init();

			// #2.2 Prepare public methods
			$class->publicMethod();

			// #3 Check authentication
			$privateMethod = $class->isPrivateMethod($method);

			if ($privateMethod) {
				$class->authenticate($method);
			}

			// #4 Ready to call the API serving method with a ready response object!
			if (!is_null($augment)) $res->disableReply();

			$class->$method($res);
			
			// #5 Restore the CWD
			chdir($cwd);

		} catch (Throwable $e) {
			// If it was an API implementation error then restore CWD
			chdir($cwd);

			if (!$e instanceof Trunk) {
				$msg = self::$DEBUG ?  self::getFullErrorMsg($e) : 'Error in API implementation';
				$e = Trunk::error500($msg);
			}

			if (is_null($augment)) {
				$e->report();
			}
			
			if ($e->getMessage() == 'HATI_API_CALL') {
				return [
					'headers' => $res->getHeaders(),
					'cookies' => $res->getCookies(),
					'body' => $res->getJSON()
				];
			}
			
			return [
				'headers' => $e->getHeaders(),
				'cookies' => $e->getCookies(),
				'body' => $e->__toString()
			];
		}

		return null;
	}

	/**
	 * Registers API endpoints with handler. An API can be registered with various configurations.
	 * Default Hati API handler supports the following HTTP verbs: GET, POST, PUT, PATCH, DELETE.
	 * See below as an example:
	 * <code>
	 * HatiAPIHandler::register([
	 * 	// HTTP verb the API wants the request method of.
	 * 	// This can be an array of method such as: 'method' => ['GET', 'POST', 'PUT']
	 * 	'method' => 'GET',
	 *
	 * 	// API endpoint path which can be called as: http://example.com/api/v1/test
	 * 	'path' => 'v1/test',
	 *
	 * 	// Relative folder path found in the api folder; here 'v1' is the version folder.
	 * 	'handler' => 'v1/TestAPI.php',
	 *
	 *  // Or it can be a fully qualified class name
	 * 	'handler' => \YOUR\APP\SRC\TestAPI::class,
	 *
	 * 	// Any php function can be invoked via api.
	 * 	// It can also be an array functions. For example: 'extension' => ['method1', 'method2']
	 * 	// e.g: http://example.com/api/v1/test/testFun/arg1?param1=value1 will invoke
	 * 	// 'testFun' public method in v1/TestAPI.php with argument array ['arg1'], and
	 * 	// query parameters array ['param1' => 'value1'].
	 * 	'extension' => 'testFun'
	 * ]);
	 * </code>
	 *
	 * Functions registered for the API and default methods for supported HTTP verb, will be invoked by HatiHandler.
	 *
	 * The handler class file (for example TestAPI.php) must be an implementation of {@link HatiAPI} with the methods
	 * defined by that 'extension' field.
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

			if (is_string($method)) {
				$method = [$method];
			}

			if (!Arr::in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
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

			/*
			 * Check if the handler class/file exists
			 * */
			if (str_ends_with($file, '.php')) {
				$filePath = getcwd() . DIRECTORY_SEPARATOR . $file;
				$exists = file_exists($filePath);
			} else {
				$filePath = $file;
				$exists = class_exists($filePath);
			}

			if (!$exists) {
				throw Trunk::error500("API-Registry: Handler is missing for: " . Arr::strList($method) . " $path");
			}

			$func = $api['extension'] ?? null;
			if (!empty($func)) {
				$func = is_string($func) ? [$func] : $func;
			}

			// Register the API!
			foreach ($method as $m) {
				$handler->apis[$m][$path] = [
					'handler' => $filePath,
					'extension' => $func
				];
			}

		} catch (Throwable $e) {

			if (!$e instanceof Trunk) {
				$msg = self::$DEBUG ? self::getFullErrorMsg($e) : 'Error in API implementation';
				$e = Trunk::error500($msg);
			}

			$e->report();
		}
	}

	private static function getFullErrorMsg(Throwable $e): string {
		return sprintf("%s in %s at line %s",
			ucfirst($e ->getMessage()),
			$e->getFile(),
			$e->getLine()
		);
	}

}