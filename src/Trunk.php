<?php

namespace hati;

use hati\api\HatiAPI;
use hati\api\Response;
use hati\util\Util;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

/**
 * Trunk is the native error type for Hati library. In various parts of the library, Trunk
 * is used to throw errors.
 *
 * Any implementation of {@link HatiAPI} can use this Trunk class to report back to the
 * requester with JSON. There are many static methods in this Trunk class to output
 * various types of error which start with Trunk::errorXXX where XXX is the HTTP status
 * code.
 * */

class Trunk extends RuntimeException {

	private const DEFAULT_MSG = 'An error occurred.';

	private string $msg;
	private int $status;
	private array $headers = [];
	private array $cookies = [];

	public function __construct(string $msg = self::DEFAULT_MSG) {
		parent::__construct($msg);

		$this->msg = $msg;
		$this->status = Response::ERROR;
	}

	public function getMsg(): string {
		return $this->msg;
	}

	public function getStatus(): int {
		return $this->status;
	}

	public function getHeaders(): array {
		return $this->headers;
	}
	
	public function getCookies(): array {
		return $this->cookies;
	}

	public function __toString(): string {
		return Response::buildResponse($this->msg, $this->status);
	}

	/**
	 * This method writes the error response as standard
	 * JSON output with proper HTTP status code (if not being called from CLI).
	 * */
	#[NoReturn]
	public function report(): void {
		if (!Util::isCLI()) {
			foreach ($this->headers as $h) {
				header($h);
			}
			
			foreach ($this->cookies as $c) {
				$name = $c('name', $c);
				$value = $c('value', $c);
				setcookie($name, $value, $c);
			}
		}

		echo $this;
		exit(2);
	}

	/**
	 * 200 Success
	 * A helper method which allows sending 200 response with message as API
	 * output
	 * */
	public static function send200(string $msg = 'Success', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 200 OK', $headers, $cookies);
		$trunk->status = Response::SUCCESS;
		throw $trunk;
	}
	
	/**
	 * 400 Client Error
	 * */
	public static function send400(string $msg = 'Client Error', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 400 Bad Request', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 401 Unauthorized
	 * */
	public static function send401(string $msg = 'Unauthorized', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 401 Unauthorized', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 403 Forbidden Access
	 * */
	public static function send403(string $msg = 'Forbidden Access', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 403 Forbidden', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 404 Not Found
	 * */
	public static function send404(string $msg = 'Not Found', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 404 Not Found', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 405 Method Not Allowed
	 * */
	public static function send405(string $msg = 'Method Not Allowed', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 405 Method Not Allowed', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 408 Request Timeout
	 * */
	public static function send408(string $msg = 'Request Timeout', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 408 Request Timeout', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 429 Too Many Requests
	 * */
	public static function send429(string $msg = 'Too Many Requests', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 429 Too Many Requests', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 500 Internal Server Error
	 * */
	public static function send500(string $msg = 'Internal Server Error', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 500 Internal Server Error', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 501 Not Implemented
	 * */
	public static function send501(string $msg = 'Not Implemented', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 503 Not Implemented', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 503 Service Unavailable
	 * */
	public static function send503(string $msg = 'Service Unavailable', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 503 Service Unavailable', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 504 Gateway Timeout
	 * */
	public static function send504(string $msg = 'Gateway Timeout', ?array $headers = null, ?array $cookies = null): void {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 504 Gateway Timeout', $headers, $cookies);
		$trunk->status = Response::ERROR;
		throw $trunk;
	}
	
	/**
	 * 400 Client Error
	 * */
	public static function error400(string $msg = 'Client Error', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 400 Bad Request', $headers, $cookies);
	}

	/**
	 * 401 Unauthorized
	 * */
	public static function error401(string $msg = 'Unauthorized', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 401 Unauthorized', $headers, $cookies);
	}

	/**
	 * 403 Forbidden Access
	 * */
	public static function error403(string $msg = 'Forbidden Access', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 403 Forbidden', $headers, $cookies);
	}

	/**
	 * 404 Not Found
	 * */
	public static function error404(string $msg = 'Not Found', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 404 Not Found', $headers, $cookies);
	}

	/**
	 * 405 Method Not Allowed
	 * */
	public static function error405(string $msg = 'Method Not Allowed', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 405 Method Not Allowed', $headers, $cookies);
	}

	/**
	 * 408 Request Timeout
	 * */
	public static function error408(string $msg = 'Request Timeout', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 408 Request Timeout', $headers, $cookies);
	}

	/**
	 * 429 Too Many Requests
	 * */
	public static function error429(string $msg = 'Too Many Requests', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 429 Too Many Requests', $headers, $cookies);
	}

	/**
	 * 500 Internal Server Error
	 * */
	public static function error500(string $msg = 'Internal Server Error', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 500 Internal Server Error', $headers, $cookies);
	}

	/**
	 * 501 Not Implemented
	 * */
	public static function error501(string $msg = 'Not Implemented', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 503 Not Implemented', $headers, $cookies);
	}

	/**
	 * 503 Service Unavailable
	 * */
	public static function error503(string $msg = 'Service Unavailable', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 503 Service Unavailable', $headers, $cookies);
	}

	/**
	 * 504 Gateway Timeout
	 * */
	public static function error504(string $msg = 'Gateway Timeout', ?array $headers = null, ?array $cookies = null): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 504 Gateway Timeout', $headers, $cookies);
	}

	private static function buildTrunkWithHeaders(string $msg, string $errorHeader, ?array $headers, ?array $cookies): Trunk {
		if (empty($headers)) $headers = [];
		if (empty($cookies)) $cookies = [];
		
		$trunk = new Trunk($msg);
		
		$trunk->headers = array_merge(['Content-Type: application/json'], $headers);
		$trunk->headers[] = $errorHeader;
		
		$trunk->cookies = $cookies;
		
		return $trunk;
	}
}