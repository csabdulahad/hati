<?php

namespace hati;

use hati\api\HatiAPI;
use hati\api\Response;
use hati\util\Arr;
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

	public function __construct(string $msg = self::DEFAULT_MSG) {
		parent::__construct($msg);

		$this -> msg = $msg;
		$this -> status = Response::ERROR;
	}

	public function getMsg(): string {
		return $this -> msg;
	}

	public function getStatus(): int {
		return $this -> status;
	}

	public function getHeaders(): array {
		return $this -> headers;
	}

	public function __toString(): string {
		return Response::buildResponse($this -> msg, $this -> status);
	}

	/**
	 * This method writes the error response as standard
	 * JSON output with proper HTTP status code (if not being called from CLI).
	 * */
	#[NoReturn]
	public function report(): void {
		if (!Util::cli()) {
			foreach ($this -> headers as $h) {
				header($h);
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
	public static function send200(string $msg = 'Success', string|array ...$header): Trunk {
		$trunk = self::buildTrunkWithHeaders($msg, 'HTTP/1.0 200 OK', $header);
		$trunk -> status = Response::SUCCESS;
		throw $trunk;
	}

	/**
	 * 400 Client Error
	 * */
	public static function error400(string $msg = 'Client Error', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 400 Bad Request', $header);
	}

	/**
	 * 401 Unauthorized
	 * */
	public static function error401(string $msg = 'Unauthorized', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 401 Unauthorized', $header);
	}

	/**
	 * 403 Forbidden Access
	 * */
	public static function error403(string $msg = 'Forbidden Access', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 403 Forbidden', $header);
	}

	/**
	 * 404 Not Found
	 * */
	public static function error404(string $msg = 'Not Found', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 404 Not Found', $header);
	}

	/**
	 * 405 Method Not Allowed
	 * */
	public static function error405(string $msg = 'Method Not Allowed', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 405 Method Not Allowed', $header);
	}

	/**
	 * 408 Request Timeout
	 * */
	public static function error408(string $msg = 'Request Timeout', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 408 Request Timeout', $header);
	}

	/**
	 * 429 Too Many Requests
	 * */
	public static function error429(string $msg = 'Too Many Requests', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 429 Too Many Requests', $header);
	}

	/**
	 * 500 Internal Server Error
	 * */
	public static function error500(string $msg = 'Internal Server Error', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 500 Internal Server Error', $header);
	}

	/**
	 * 501 Not Implemented
	 * */
	public static function error501(string $msg = 'Not Implemented', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 503 Not Implemented', $header);
	}

	/**
	 * 503 Service Unavailable
	 * */
	public static function error503(string $msg = 'Service Unavailable', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 503 Service Unavailable', $header);
	}

	/**
	 * 504 Gateway Timeout
	 * */
	public static function error504(string $msg = 'Gateway Timeout', string|array ...$header): Trunk {
		return self::buildTrunkWithHeaders($msg, 'HTTP/1.0 504 Gateway Timeout', $header);
	}

	private static function buildTrunkWithHeaders(string $msg, string $errorHeader, string|array ...$header): Trunk {
		$trunk = new Trunk($msg);
		$trunk -> headers = array_merge(['Content-Type: application/json'], Arr::varargsAsArray($header));
		$trunk -> headers[] = $errorHeader;
		return $trunk;
	}
}