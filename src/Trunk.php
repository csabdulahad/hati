<?php

namespace hati;

use hati\api\Response;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Trunk is the native controlled error/response type for Hati.
 *
 * It is thrown to stop the current execution flow and carry a response
 * back to catch handler.
 *
 * Trunk does not emit headers, cookies, body content, or terminate the PHP process.
 */
class Trunk extends RuntimeException
{

	private const DEFAULT_MSG = 'An error occurred.';

	public mixed $msg;
	public int $status;
	public int $httpStatusCode;
	public array $headers = [];
	public array $cookies = [];
	public ?string $body = null;
	
	public function __construct(
		mixed $msg = self::DEFAULT_MSG,
		int $httpStatusCode = 500,
		int $status = Response::ERROR,
		array $headers = [],
		array $cookies = [],
		?string $body = null,
		int $exceptionCode = 0,
		?Throwable $previous = null
	) {
		parent::__construct(self::toExceptionMessage($msg), $exceptionCode, $previous);
		
		$this->msg = $msg;
		$this->status = $status;
		$this->httpStatusCode = $httpStatusCode;
		$this->body = $body;
		
		$this->addHeaders($headers);
		$this->addCookies($cookies);
	}
	
	public function responseObject(): array
	{
		return [
			'status' => $this->status,
			'msg' => $this->msg
		];
	}
	
	public function toArray(): array
	{
		return [
			'code' => $this->httpStatusCode,
			'headers' => $this->normalizedHeaders(),
			'cookies' => $this->cookies,
			'body' => $this->getBody()
		];
	}
	
	public function addHeader(string $name, string $value): Trunk
	{
		$name = trim($name);
		
		if ($name === '') {
			throw new RuntimeException('Header name cannot be empty.');
		}
		
		if (str_contains($name, ':')) {
			throw new RuntimeException('Header name must not contain colon.');
		}
		
		if (str_contains($name, "\r") || str_contains($name, "\n")) {
			throw new RuntimeException('Header name must not contain new line characters.');
		}
		
		if (str_contains($value, "\r") || str_contains($value, "\n")) {
			throw new RuntimeException('Header value must not contain new line characters.');
		}
		
		$existingName = $this->findHeaderName($name);
		
		if ($existingName !== null && $existingName !== $name) {
			unset($this->headers[$existingName]);
		}
		
		$this->headers[$name] = $value;
		
		return $this;
	}
	
	public function addHeaders(array $headers): Trunk
	{
		foreach ($headers as $name => $value) {
			if (!is_string($name)) {
				throw new RuntimeException('Headers must be provided as key-value pairs.');
			}
			
			$this->addHeader($name, (string) $value);
		}
		
		return $this;
	}
	
	public function addCookie(
		string $name,
		mixed $value,
		int $expire = 0,
		bool $secure = true,
		bool $httpOnly = true,
		string $path = '/',
		string $domain = '',
		string $sameSite = 'Strict'
	): Trunk
	{
		$this->cookies[] = [
			'name' => $name,
			'value' => $value,
			'expires' => $expire,
			'secure' => $secure,
			'httponly' => $httpOnly,
			'path' => $path,
			'domain' => $domain,
			'samesite' => $sameSite
		];
		
		return $this;
	}
	
	public function addCookies(array $cookies): Trunk
	{
		foreach ($cookies as $cookie) {
			if (!is_array($cookie)) {
				throw new RuntimeException('Cookie must be an array.');
			}
			
			$this->cookies[] = $cookie;
		}
		
		return $this;
	}
	
	public function getMsg(): mixed {
		return $this->msg;
	}
	
	public function getStatus(): int {
		return $this->status;
	}
	
	public function getHttpStatusCode(): int {
		return $this->httpStatusCode;
	}
	
	public function getHeaders(): array {
		return $this->headers;
	}
	
	public function getCookies(): array {
		return $this->cookies;
	}
	
	public function __toString(): string {
		return $this->getBody();
	}
	
	public function getBody(): string {
		return $this->body ?? $this->buildBody();
	}
	
	/**
	 * 200 Success
	 * */
	public static function http200(string $msg = 'Success', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 200, Response::SUCCESS, $headers, $cookies);
	}
	
	/**
	 * 400 Client Error
	 * */
	public static function http400(string $msg = 'Client Error', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 400, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 401 Unauthorized
	 * */
	public static function http401(string $msg = 'Unauthorized', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 401, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 403 Forbidden Access
	 * */
	public static function http403(string $msg = 'Forbidden Access', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 403, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 404 Not Found
	 * */
	public static function http404(string $msg = 'Not Found', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 404, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 405 Method Not Allowed
	 * */
	public static function http405(string $msg = 'Method Not Allowed', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 405, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 408 Request Timeout
	 * */
	public static function http408(string $msg = 'Request Timeout', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 408, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 429 Too Many Requests
	 * */
	public static function http429(string $msg = 'Too Many Requests', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 429, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 500 Internal Server Error
	 * */
	public static function http500(string $msg = 'Internal Server Error', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 500, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 501 Not Implemented
	 * */
	public static function http501(string $msg = 'Not Implemented', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 501, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 503 Service Unavailable
	 * */
	public static function http503(string $msg = 'Service Unavailable', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 503, Response::ERROR, $headers, $cookies);
	}

	/**
	 * 504 Gateway Timeout
	 * */
	public static function http504(string $msg = 'Gateway Timeout', ?array $headers = null, ?array $cookies = null): never
	{
		throw self::buildTrunk($msg, 504, Response::ERROR, $headers, $cookies);
	}

	private static function buildTrunk(string $msg, int $httpStatusCode, int $status = Response::ERROR, ?array $headers = null, ?array $cookies = null): Trunk
	{
		$trunk = new Trunk(msg: $msg, httpStatusCode: $httpStatusCode, status: $status);
		
		if (!empty($headers)) {
			$trunk->addHeaders($headers);
		}
		
		if (!empty($cookies)) {
			$trunk->addCookies($cookies);
		}
		
		return $trunk;
	}
	
	private static function toExceptionMessage(mixed $msg): string
	{
		if (is_string($msg)) {
			return $msg;
		}
		
		if ($msg === null) {
			return '';
		}
		
		if (is_scalar($msg)) {
			return (string) $msg;
		}
		
		return self::DEFAULT_MSG;
	}
	
	private function normalizedHeaders(): array
	{
		if ($this->findHeaderName('Content-Type') === null) {
			$this->headers['Content-Type'] = 'application/json; charset=utf-8';
		}
		
		return $this->headers;
	}
	
	private function findHeaderName(string $name): ?string
	{
		$name = strtolower(trim($name));
		
		foreach ($this->headers as $headerName => $_) {
			if (strtolower((string) $headerName) === $name) {
				return (string) $headerName;
			}
		}
		
		return null;
	}
	
	private function buildBody(): string
	{
		try {
			$data =['response' => $this->responseObject()];
			return json_encode($data,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return '{"response":{"status":-1,"msg":"Unable to encode error response."}}';
		}
	}
	
}