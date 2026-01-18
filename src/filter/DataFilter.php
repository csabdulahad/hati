<?php

namespace hati\filter;

use Closure;
use hati\api\Response;
use hati\util\Request;
use hati\util\Util;
use InvalidArgumentException;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

/**
 * A filter class which helps validate user inputs from multiple sources such form data,
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
 * $filter->addFilter([
 * 	['key' => 'username', 'name' => 'Username', 'type' => 'str', 'minLen' => 3],
 * 	['key' => 'email', 'name' => 'Email', 'type' => 'email']
 * ]);
 *
 * $filter->validate();
 * </code>
 * By default, on input error, DataFilter outputs JSON following the {@link Response} structure. To
 * handle the error with custom error handler, use {@link setErrHandler()} and pass-in a closure
 * accepting array containing the validation output and the filter instance:
 * <code>
 * $handler = function (array $result, DataFilter $filter) {
 * 	// handle the error
 * };
 *
 * $filter->setErrHandler($handler);
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

	// Whether to continue filtering if validation error happens
	private bool $failFast;
	
	// The rules for validating input
	private array $rules = [];
	
	// Contains validation output objects
	private array $errList = [];

	// Filtered data buffer
	private array $data = [];

	// Custom error handler closure
	private ?Closure $errorHandler = null;
	
	// Localization handler for filte message
	private ?FilterLocalization $localization = null;

	/**
	 * Constructs the DataFilter for specified data source. The data source method
	 * can be of the followings:
	 * - {@link DataFilter::METHOD_GET}
	 * - {@link DataFilter::METHOD_POST}
	 * - {@link DataFilter::METHOD_PUT}
	 * - {@link DataFilter::METHOD_DELETE}
	 * - {@link DataFilter::METHOD_NONE} : when filtering custom data source.
	 * */
	public function __construct(string $method = DataFilter::METHOD_NONE, bool $failFast = false) {
		$this->method = $method;
		$this->failFast = $failFast;
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
		$this->errorHandler = $handler;
	}

	/**
	 * Add filter for user input coming from various sources.
	 * A filter object can have the following keys:
	 * - key: the key which identifies the input in the input array source
	 * - name: optional name. It is used to in the friendly error reporting to user
	 * - type: input data type. It could be one of the following: str, int, float, bool, email, iso-date, iso-datetime
	 * - required: indicates whether the input is mandatory or not. By default, every input is.
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
			$this->rules[] = $rules;
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
		$v =  $this->data[$key] ?? $default;

		if (is_null($v)) {
			$rule = null;
			foreach ($this->rules as $r) {
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
	 * Returns the filtered values by their keys. If the failFast param is true
	 * then the key may/may not be present in the returned array since failFast
	 * might have aborted the validation earlier before getting the change to validate
	 * that particular value.
	 *
	 * @return array
	 * */
	public function getValues(): array {
		return $this->data;
	}

	/**
	 * A helper function which unpacks the validated input data as list
	 * which can be used to use the modern php syntax such as:
	 *
	 * <code>
	 *  [$name, $age] = $filter->list();
	 * </code>
	 *
	 * @return array The filtered input data
	 * */
	public function list(): array {
		return array_values($this->data);
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
	 * when you want to consider the request body to be parsed as JSON input. Otherwise, it could be an
	 * array of data source.
	 *
	 * @param ?Closure $errHandler Custom error handler
	 */
	public function validate(array|string $source, ?Closure $errHandler = null): void {
		if (!is_null($errHandler))
			$this->errorHandler = $errHandler;

		/*
		 * Check for valid request method
		 * */
		if ($this->method != self::METHOD_NONE && ($_SERVER['REQUEST_METHOD'] ?? '') !== $this->method) {
			$this->handleErr(FilterOut::BAD_REQUEST_METHOD);
			return;
		}

		/*
		 * Get the request body if the source is REQ
		 * */
		if ($this->method != self::METHOD_NONE && $source === self::SOURCE_REQ_BODY) {
			if (!Request::contentTypeJSON()) {
				$this->handleErr(FilterOut::CONTENT_TYPE_INVALID);
				return;
			}

			$source = Request::body();
		}

		/*
		 * Now the data source has to be an array!
		 * */
		if(!is_array($source)) {
			$this->handleErr(FilterOut::BAD_REQUEST_METHOD);
			return;
		}

		if (empty($source)) return;

		foreach ($this->rules as $rule) {
			$required = $rule['required'] ?? true;
			$key = $rule['key'];
			$data = $source[$key] ?? $rule['default'] ?? null;

			if (!$required && empty($data)) {
				$this->data[$key] = null;
				continue;
			} else {
				if (!isset($data)) {
					$this->handleErr(FilterOut::NULL, $rule);
					
					if ($this->failFast) break;
					continue;
				}
			}

			$type = $rule['type'];

			if ($type == 'str') {
				$result = $this->checkString($data, $rule);
			} elseif ($type == 'str-raw') {
				$result = $this->checkRawString($data, $rule);
			} elseif ($type == 'int') {
				$result = $this->checkInteger($data, $rule);
			} elseif ($type == 'float') {
				$result = $this->checkFloat($data, $rule);
			} elseif ($type == 'bool') {
				$result = $this->checkBool($data);
			} elseif ($type == 'email') {
				$result = $this->checkEmail($data);
			} elseif ($type == 'iso-time') {
				$result = $this->checkISOTime($data);
			} elseif ($type == 'iso-date') {
				$result = $this->checkISODate($data);
			} elseif($type == 'iso-datetime') {
				$result = $this->checkISODatetime($data);
			} else {
				throw new RuntimeException("Filter type $type isn't supported");
			}

			if (!Filter::isOK($result)) {
				$this->handleErr($result, $rule);
				
				if ($this->failFast) break;
				continue;
			}

			// If it is optional, has a value but not of valid options!
			// Check if it is in allowed options
			$x = $this->inOption($data, $rule);
			if (!Filter::isOK($x)) {
				$this->handleErr($x, $rule);
				
				if ($this->failFast) break;
				continue;
			}

			$ok = Filter::isOK($result);
			if (!$ok) {
				$this->handleErr(FilterOut::INVALID, $rule);
				
				if ($this->failFast) break;
				continue;
			}

			$this->data[$key] = $data;
		}
		
		if (empty($this->errList)) return;
		
		($this->errorHandler)($this->errList, $this);
	}
	
	/**
	 * Set localization handler for filter output messages.
	 *
	 * @param string $handlerCls It must be subtype of class {@link FilterLocalization}
	 * @return void
	 * */
	public function setLocalization(string $handlerCls): void {
		if (!class_exists($handlerCls)) {
			throw new RuntimeException("Class doesn't exist: $handlerCls");
		}
		
		if (!is_subclass_of($handlerCls, FilterLocalization::class)) {
			throw new InvalidArgumentException("Invalid filter localization handler");
		}
		
		$this->localization = new $handlerCls;
	}

	private function handleErr(FilterOut $err, ?array $rule = null): void {
		$name = '';
		
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
			$msg = $this->getMst($err, $name, $rule);
		}

		if (is_null($this->errorHandler)) {
			$this->output($code, $msg);
		} else{
			$errInfo = [
				'key' => $key,
				'name' => $name,
				'message' => $msg,
				'type' => $err
			];
			
			if ($this->failFast) {
				($this->errorHandler)([$key => $errInfo], $this);
			} else {
				$this->errList[$key] = $errInfo;
			}
		}
	}
	
	private function getMst(FilterOut $err, string $name, array $rule): string {
		$locale = $this->getLocalization();
		
		return match ($err) {
			FilterOut::NULL 	=> $locale->nullInputErr($name),
			FilterOut::EMPTY 	=> $locale->emptyInputErr($name),
			FilterOut::ILLEGAL 	=> $locale->illegalInputErr($name),
			FilterOut::INVALID 	=> $locale->invalidInputErr($name),
			
			FilterOut::RANGE_FRACTION_ERROR => $locale->rangeFractionInputErr($name, $rule['place']),
			
			FilterOut::VAL_LEN_ERROR 		=> $locale->inputLengthErr($name, $rule['minLen'], $rule['maxLen']),
			FilterOut::VAL_LEN_OVER_ERROR 	=> $locale->inputLengthOverErr($name, $rule['maxLen']),
			FilterOut::VAL_LEN_UNDER_ERROR	=> $locale->inputLengthUnderErr($name, $rule['minLen']),
			
			FilterOut::RANGE_ERROR 			=> $locale->inputRangeErr($name, $rule['minValue'], $rule['maxValue']),
			FilterOut::RANGE_OVER_ERROR 	=> $locale->inputRangeOverErr($name, $rule['maxValue']),
			FilterOut::RANGE_UNDER_ERROR 	=> $locale->inputRangeUnderErr($name, $rule['minValue']),
			
			FilterOut::NOT_IN_OPTION => $locale->invalidInputOptionErr($name, $rule['option']),
			
			default => $locale->unknownErr()
		};
	}
	
	private function checkLen(mixed $data, array $rule): FilterOut {
		if (empty($rule['maxLen']) && empty($rule['minLen']))
			return FilterOut::OK;

		$max = $rule['maxLen'] ?? null;
		$min = $rule['minLen'] ?? null;

		$v = Filter::checkStrLen($data, $min, $max);

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
		$v = Filter::checkString($data);

		// Check if it is string
		if (!Filter::isOK($v)) return $v;

		// Check the length
		$x = $this->checkLen($v, $rule);
		if (!Filter::isOK($x)) return $x;

		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkRawString(mixed &$data, array $rule): FilterOut {
		$v = Filter::checkString($data, null);
		
		// Check if it is string
		if (!Filter::isOK($v)) return $v;
		
		// Check the length
		$x = $this->checkLen($v, $rule);
		if (!Filter::isOK($x)) return $x;
		
		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}

	private function checkInteger(mixed &$data, array $rule): FilterOut {
		$v = Filter::checkInt($data);

		// Check if it is valid integer
		if (!Filter::isOK($v)) return $v;

		// Check the length
		$x = $this->checkLen($v, $rule);
		if (!Filter::isOK($x)) return $x;

		// Check the range
		$x = $this->inRange($v, $rule);
		if (!Filter::isOK($x)) return $x;

		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}

	private function checkFloat(mixed &$data, array $rule): FilterOut {
		$v = Filter::checkFloat($data);

		// Check if it is valid float
		if (!Filter::isOK($v)) return $v;

		// Check the length
		$x = $this->checkLen($v, $rule);
		if (!Filter::isOK($x)) return $x;

		// Check the range
		$x = $this->inRange($v, $rule);
		if (!Filter::isOK($x)) return $x;

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
		$v = Filter::checkBool($data);

		// Check if it is valid float
		if (!Filter::isOK($v)) return $v;

		// Data is all valid!
		$data = $v;
		return FilterOut::OK;
	}

	private function checkEmail(mixed &$data): FilterOut {
		$v = Filter::checkEmail($data);

		if (!Filter::isOK($v)) return $v;

		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkISOTime(mixed &$data): FilterOut {
		$v = Filter::checkISOTime($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkISODate(mixed &$data): FilterOut {
		$v = Filter::checkISODate($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}

	private function checkISODatetime(mixed &$data): FilterOut {
		$v = Filter::checkISODatetime($data);

		if (!Filter::isOK($v)) return $v;

		$data = $v;
		return FilterOut::OK;
	}

	private function getLocalization(): FilterLocalization {
		if (is_null($this->localization)) {
			$this->localization = new FilterLocalization();
		}
		
		return $this->localization;
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