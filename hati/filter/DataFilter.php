<?php

namespace hati\filter;

use Closure;
use hati\api\Response;
use hati\util\Arr;
use hati\util\Request;
use hati\util\Util;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

/**
 * A filter class which helps validating user inputs from multiple sources such form data,
 * JSON data, or any array data easily. This could help sanitizing and reporting with error
 * messages to the user using this class which greatly minimizes the number of code required
 * to handle those cases. For validation, DataFilter can also check for request method. This
 * can be set when initializing a DataFilter.<br>
 * For each data input, define specific rule set using {@link addFilter()} method with various
 * options. To learn about available filter rule value see the method {@link addFilter()}. For
 * an example:
 * <code>
 * // validate request body
 * $filter = new DataFilter(DataFilter::SOURCE_REQ_BODY);
 * $filter -> addFilter([
 * 	['key' => 'username', 'name' => 'Username', 'type' => 'str', 'minLen' => 3],
 * 	['key' => 'email', 'name' => 'Email', 'type' => 'email']
 * ]);
 *
 * $filter -> validate();
 * </code>
 * By default, on input error, DataFilter outputs JSON following the {@link Response} structure. To
 * handle the error with custom error handler, use {@link setErrHandler()} and pass-in a closure
 * accepting three arguments matching the following signature:
 * <code>
 * $handler = function (FilterOut $type, string $key, string $errMsg) {
 * 	// handle the error
 * };
 *
 * $filter -> setErrHandler($handler);
 * </code>
 *
 * @since 5.0.0
 * */

class DataFilter {

	// Input data to be filtered as email
	const TYPE_EMAIL = 'email';

	// Input data to be filtered as ISO date such as 2012-01-21
	const TYPE_ISO_DATE = 'iso-date';

	// Input data to be filtered as ISO date-time such as 2019-09-31 12:34:33
	const TYPE_ISO_DATETIME = 'iso-datetime';

	// Input data to be filtered as string
	const TYPE_STRING = 'str';

	// Input data to be filtered as integer
	const TYPE_INTEGER = 'int';

	// Input data to be filtered as float
	const TYPE_FLOAT = 'float';

	// Input data to be filtered as boolean value
	const TYPE_BOOL = 'bool';

	// Request method to be GET
	const METHOD_GET = 'GET';

	// Request method to be POST
	const METHOD_POST = 'POST';

	// Request method to be PUT
	const METHOD_PUT = 'PUT';

	// Request method to be DELETE
	const METHOD_DELETE = 'DELETE';

	// When request method is not applicable; to filter custom data source
	const METHOD_NONE = 'NONE';

	// Constant for indicating the DataFilter to filter the request body as JSON input source
	const SOURCE_REQ_BODY = 'src-req-body';

	// Tracks which method it is to check
	private string $method;

	// The rules for validating input
	private array $rules = [];

	// Filtered data buffer
	private array $data = [];

	// Custom error handler closure
	private ?Closure $errorHandler = null;

	/**
	 * Constructs the DataFilter for specified data source. The data source method
	 * can be of the followings:
	 * - {@link DataFilter::METHOD_GET}
	 * - {@link DataFilter::METHOD_POST}
	 * - {@link DataFilter::METHOD_PUT}
	 * - {@link DataFilter::METHOD_DELETE}
	 * - {@link DataFilter::METHOD_NONE} : when filtering custom data source.
	 * */
	public function __construct(string $method = DataFilter::METHOD_NONE) {
		$this -> method = $method;
	}

	/**
	 * Setting a custom error handler to be invoked on encountering error in data filtering
	 * to handle edge those cases. The closure takes in three arguments and have the following
	 * order:
	 * <code>
	 *  $handler = function (FilterOut $type, string $key, string $errMsg) {
	 *    // handle the error
	 *  };
	 * </code>
	 *
	 * @param callable $handler The closure function to handle the error
	 * */
	public function setErrHandler(callable $handler): void {
		$this -> errorHandler = $handler;
	}

	/**
	 * Add filter for user input coming from various sources.
	 * A filter object can have the following keys:
	 * - key: the key which identifies the input in the input array source
	 * - name: optional name. It is used to in the friendly error reporting to user
	 * - type: input data type. It could be one of the following: str, int, float, bool, email, iso-date, iso-datetime
	 * - required: indicates whether the input is mandatory or not. By default every input is.
	 * - minLen: minimum length of the input to be considered valid
	 * - maxLen: maximum length(inclusive) of the input to be considered valid
	 * - minValue: applicable to number type. Minimum value the number can be(inclusive)
	 * - maxValue: applicable to number type. Maximum value the number can be(inclusive)
	 * - place: applicable to float data type. The length of the fractional part(inclusive)
	 * - option: array containing the valid inputs
	 * - default: for optional input, the specified value can be returned
	 *
	 * @param array $filter array of filters to validate input data
	 */
	public function addFilter(array ...$filter): void {
		foreach ($filter as $rules) {
			$this -> rules[] = $rules;
		}
	}

	/**
	 * Call this method to get the filtered input data by specified key.
	 *
	 * @param string $key The key of the input in the input data source
	 * @param mixed $default To be returned when optional input was not provided in the input source
	 * @return mixed The filtered input data
	 * */
	public function get(string $key, mixed $default = null): mixed {
		$v =  $this -> data[$key] ?? $default;

		if (is_null($v)) {
			$rule = null;
			foreach ($this -> rules as $r) {
				if ($r['key'] == $key) {
					$rule = $r;
				}
			}
			if (is_null($rule)) return null;

			$v = $rule['default'];
		}

		return $v;
	}

	/**
	 * A helper function which unpacks the validated input data as list
	 * which can be used to use the modern php syntax such as:
	 *
	 * <code>
	 *  [$name, $age] = $filter -> list();
	 * </code>
	 *
	 * @return array The filtered input data
	 * */
	public function list(): array {
		return array_values($this -> data);
	}

	/**
	 * Validates the input source as specified by the filter rules set.
	 * By default, on encountering error, it outputs in the standard API output form,
	 * setting correct http status, content type and a JSON object. The object has an
	 * object in it which looks like the following:
	 * <code>
	 * {
	 *  "response": {
	 *    "status": 1,
	 *    "msg": ""
	 *   }
	 * }
	 * </code>
	 *
	 * @param array|string $source The input data source. This could be {@link DataFilter::SOURCE_REQ_BODY}
	 * when you want to consider the request body to be parsed as JSON input. Otherwise it could be an
	 * array of data source.
	 *
	 * @param ?Closure $errHandler Custom error handler
	 */
	public function validate(array|string $source, ?Closure $errHandler = null): void {
		if (!is_null($errHandler))
			$this -> errorHandler = $errHandler;

		/*
		 * Check for valid request method
		 * */
		if ($this -> method != self::METHOD_NONE && ($_SERVER['REQUEST_METHOD'] ?? '') !== $this -> method) {
			$this -> handleErr(FilterOut::BAD_REQUEST_METHOD);
			return;
		}

		/*
		 * Get the request body if the source is REQ
		 * */
		if ($this -> method != self::METHOD_NONE && $source === self::SOURCE_REQ_BODY) {
			if (!Request::contentTypeJSON()) {
				$this -> handleErr(FilterOut::CONTENT_TYPE_INVALID);
				return;
			}

			$source = Request::body();
		}

		/*
		 * Now the data source has to be an array!
		 * */
		if(!is_array($source)) {
			$this -> handleErr(FilterOut::BAD_REQUEST_METHOD);
			return;
		}

		if (empty($source)) return;

		foreach ($this -> rules as $rule) {
			$required = $rule['required'] ?? true;
			$key = $rule['key'];
			$data = $source[$key] ?? null;

			if (!$required && !isset($data)) {
				$this -> data[$key] = null;
				continue;
			} else {
				if (!isset($data)) {
					$this -> handleErr(FilterOut::NULL, $rule);
					break;
				}
			}

			$type = $rule['type'];

			if ($type == 'str') {
				$result = $this -> checkString($data, $rule);
			} elseif ($type == 'int') {
				$result = $this -> checkInteger($data, $rule);
			} elseif ($type == 'float') {
				$result = $this -> checkFloat($data, $rule);
			} elseif ($type == 'bool') {
				$result = $this -> checkBool($data);
			} elseif ($type == 'email') {
				$result = $this -> checkEmail($data);
			} elseif ($type == 'iso-date') {
				$result = $this -> checkISODate($data);
			} elseif($type == 'iso-datetime') {
				$result = $this -> checkISODatetime($data);
			} else {
				throw new RuntimeException("Filter type $type isn't supported");
			}

			if (!Filter::ok($result)) {
				$this -> handleErr($result, $rule);
				return;
			}

			// If it is optional, has a value but not of valid options!
			// Check if it is in allowed options
			$x = $this -> inOption($data, $rule);
			if (!Filter::ok($x)) {
				$this -> handleErr($x, $rule);
				return;
			}

			$ok = Filter::ok($result);
			if (!$ok) {
				$this -> handleErr(FilterOut::INVALID, $rule);
				return;
			}

			$this -> data[$key] = $data;
		}
	}

	private function handleErr(FilterOut $err, ?array $rule = null): void {
		if ($err == FilterOut::BAD_REQUEST_METHOD) {
			$code = 405;
			$key = $code;
			$msg = 'Method not allowed';
		} elseif ($err == FilterOut::CONTENT_TYPE_INVALID) {
			$code = 415;
			$key = $code;
			$msg = 'Request body must be in JSON format';
		} elseif ($err == FilterOut::INVALID_REQUEST_DATA) {
			$code = 405;
			$key = $code;
			$msg = 'Invalid request data';
		} else {
			$code = 400;
			$key = $rule['key'];
			$name = $rule['name'] ?? $key;
			$msg =  match ($err) {
				FilterOut::NULL => "$name is required",
				FilterOut::EMPTY => "$name is empty",
				FilterOut::ILLEGAL => "$name contains illegal character",
				FilterOut::INVALID => "$name is invalid",
				FilterOut::RANGE_FRACTION_ERROR => "$name can't have more than " . $this -> pluralMsg($rule['place'], 'digit') . " after decimal point",
				FilterOut::VAL_LEN_ERROR => "$name can't be lower or higher than {$rule['minLen']}-{$rule['maxLen']} characters in length",
				FilterOut::VAL_LEN_OVER_ERROR => "$name can't exceed " . $this -> pluralMsg($rule['maxLen'], 'character') . " in length",
				FilterOut::VAL_LEN_UNDER_ERROR	=> "$name can't be less than " . $this -> pluralMsg($rule['minLen'], 'character') . " in length",
				FilterOut::RANGE_ERROR => "$name must have limit of {$rule['minValue']}-{$rule['maxValue']}",
				FilterOut::RANGE_OVER_ERROR => "$name can't be greater than {$rule['maxValue']}",
				FilterOut::RANGE_UNDER_ERROR => "$name can't be lower than {$rule['minValue']}",
				FilterOut::NOT_IN_OPTION => "$name must be any of the following: " . Arr::strList($rule['option']),
				default => "Unknown error"
			};
		}

		if (is_null($this -> errorHandler)) {
			$this -> output($code, $msg);
		} else {
			($this -> errorHandler)($err, $key, $msg);
		}
	}

	private function checkLen(mixed $data, array $rule): FilterOut {
		if (empty($rule['maxLen']) && empty($rule['minLen']))
			return FilterOut::OK;

		$max = $rule['maxLen'] ?? null;
		$min = $rule['minLen'] ?? null;

		$v = Filter::strLen($data, $min, $max);

		return is_string($v) ? FilterOut::OK : $v;
	}

	private function inOption(mixed $data, array $rule): FilterOut {
		if (empty($rule['option'])) return FilterOut::OK;

		if (in_array($data, $rule['option'])) return FilterOut::OK;
		else return FilterOut::NOT_IN_OPTION;
	}

	private function inRange(mixed $data, array $rule): FilterOut {
		if (empty($rule['minValue']) && empty($rule['maxValue']))
			return FilterOut::OK;

		$min = $rule['minValue'] ?? 0;
		$max = $rule['maxValue'] ?? -1;

		if ($data < $min) return FilterOut::RANGE_UNDER_ERROR;
		if ($max != -1 && $data > $max) return FilterOut::RANGE_OVER_ERROR;

		return FilterOut::OK;
	}

	private function checkString(mixed &$data, array $rule): FilterOut {
		$v = Filter::string($data);

		// Check if it is string
		if (!Filter::ok($v)) return $v;

		// Check the length
		$x = $this -> checkLen($v, $rule);
		if (!Filter::ok($x)) return $x;

		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}

	private function checkInteger(mixed &$data, array $rule): FilterOut {
		$v = Filter::int($data);

		// Check if it is valid integer
		if (!Filter::ok($v)) return $v;

		// Check the length
		$x = $this -> checkLen($v, $rule);
		if (!Filter::ok($x)) return $x;

		// Check the range
		$x = $this -> inRange($v, $rule);
		if (!Filter::ok($x)) return $x;

		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}

	private function checkFloat(mixed &$data, array $rule): FilterOut {
		$v = Filter::float($data);

		// Check if it is valid float
		if (!Filter::ok($v)) return $v;

		// Check the length
		$x = $this -> checkLen($v, $rule);
		if (!Filter::ok($x)) return $x;

		// Check the range
		$x = $this -> inRange($v, $rule);
		if (!Filter::ok($x)) return $x;

		// Check the decimal place
		if (!empty($rule['place'])) {
			$d = explode('.', $v);
			$d = $d[1] ?? 0;

			if (strlen($d) > $rule['place']) {
				return FilterOut::RANGE_FRACTION_ERROR;
			}
		}

		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}

	private function checkBool(mixed &$data): FilterOut {
		$v = Filter::bool($data);

		// Check if it is valid float
		if (!Filter::ok($v)) return $v;

		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}

	private function checkEmail(mixed &$data): FilterOut {
		$v = Filter::email($data);

		if (!Filter::ok($v)) return $v;

		$data = $v;
		return FilterOut::OK;
	}

	private function checkISODate(mixed &$data): FilterOut {
		$v = Filter::isoDate($data);

		if (!Filter::ok($v)) return $v;

		$data = $v;
		return FilterOut::OK;
	}

	private function checkISODatetime(mixed &$data): FilterOut {
		$v = Filter::isoDatetime($data);

		if (!Filter::ok($v)) return $v;

		$data = $v;
		return FilterOut::OK;
	}

	private function pluralMsg(int $count, string $noun): string {
		return $count > 1 ? "$count {$noun}s" : "$count $noun";
	}

	#[NoReturn]
	private function output(int $code, string $msg): void {
		if (!Util::cli()) {
			header('Content-Type: application/json');
			http_response_code($code);
		}

		echo json_encode([
			'response' => [
				'status' => Response::ERROR,
				'msg' => $msg,
			]
		]);

		exit(2);
	}

}