<?php

namespace hati\filter;

use Closure;
use hati\Trunk;
use InvalidArgumentException;
use RuntimeException;

/**
 * DataFilter validates an array source using rule definitions.
 *
 * It does not know anything about HTTP, request methods, content types,
 * request body parsing, headers, JSON output, or process termination.
 *
 * DataFilter's job is simple:
 * - apply rules to an array source
 * - store cleaned values
 * - collect validation errors
 * - localize validation messages
 * - optionally fail fast
 * - call error handler only from validate()
 */
class DataFilter
{
	
	// Input data to be filtered as email
	public const TYPE_EMAIL = 'email';
	
	// Input data to be filtered as URL
	public const TYPE_URL = 'url';
	
	// Input data to be filtered as ISO time such as 12:34:56
	public const TYPE_ISO_TIME = 'iso-time';
	
	// Input data to be filtered as ISO date such as 2012-01-21
	public const TYPE_ISO_DATE = 'iso-date';
	
	// Input data to be filtered as ISO date-time such as 2019-09-31 12:34:33
	public const TYPE_ISO_DATETIME = 'iso-datetime';
	
	// Input data to be filtered as string
	public const TYPE_STRING = 'str';
	
	// Input data to be filtered as raw string without output escaping/sanitization
	public const TYPE_STRING_RAW = 'str-raw';
	
	// Input data to be filtered as integer
	public const TYPE_INTEGER = 'int';
	
	// Input data to be filtered as float
	public const TYPE_FLOAT = 'float';
	
	// Input data to be filtered as boolean value
	public const TYPE_BOOL = 'bool';
	
	// Input data can be any primitive/mixed value.
	// No type validation is performed. Usually used with oneOf.
	public const TYPE_MIXED = 'mixed';
	
	/**
	 * Whether validation should stop after the first error.
	 */
	private bool $failFast;
	
	/**
	 * Normalized rule list.
	 */
	private array $rules = [];
	
	/**
	 * Validation error list.
	 */
	private array $errList = [];
	
	/**
	 * Clean validated data.
	 */
	private array $data = [];
	
	/**
	 * Error handler called by validate() only.
	 *
	 * Signature:
	 * function (array $errors, DataFilter $filter): mixed
	 */
	private Closure $errorHandler;
	
	/**
	 * Localization handler for validation messages.
	 */
	private ?FilterLocalization $localization = null;
	
	public function __construct(bool $failFast = true)
	{
		$this->failFast = $failFast;
		$this->errorHandler = $this->defaultErrHandler(...);
	}
	
	/**
	 * Sets whether validation should stop after the first error.
	 */
	public function setFailFast(bool $failFast): void
	{
		$this->failFast = $failFast;
	}
	
	/**
	 * Returns whether validation stops after the first error.
	 */
	public function isFailFast(): bool
	{
		return $this->failFast;
	}
	
	/**
	 * Sets custom error handler.
	 *
	 * The handler receives:
	 * - array $errors
	 * - DataFilter $filter
	 */
	public function setErrHandler(callable $handler): void
	{
		$this->errorHandler = $handler(...);
	}
	
	/**
	 * Add one or more validation rules.
	 *
	 * Supported rule keys:
	 *
	 * - key: required string. Source array key.
	 * - name: optional friendly name used in messages.
	 * - type: required validation type.
	 * - required: bool. Default true.
	 * - nullable: bool. Default false.
	 * - minLen: int|null.
	 * - maxLen: int|null.
	 * - minValue: int|float|null.
	 * - maxValue: int|float|null.
	 * - place: int|null. Decimal places for float.
	 * - options: array|null. Allowed values.
	 * - oneOf: array. Must be provided if mixed type validation is used.
	 * - default: mixed. Used for optional missing/blank/null values.
	 */
	public function addFilter(array ...$filter): static
	{
		foreach ($filter as $rule) {
			$this->rules[] = $this->normalizeRule($rule);
		}
		
		return $this;
	}
	
	/**
	 * Removes all rules.
	 */
	public function clearFilters(): void
	{
		$this->rules = [];
		$this->reset();
	}
	
	/**
	 * Validates the source and calls error handler if validation fails.
	 */
	public function validate(array $source, ?callable $errHandler = null): void
	{
		if ($errHandler !== null) {
			$this->setErrHandler($errHandler);
		}
		
		$this->scan($source);
		
		if ($this->hasError()) {
			($this->errorHandler)($this->errList, $this);
		}
	}
	
	/**
	 * Validates the source without calling the error handler.
	 *
	 * Use this when caller wants to manually inspect errors.
	 */
	public function scan(array $source): void
	{
		$this->reset();
		
		foreach ($this->rules as $rule) {
			$this->processRule($source, $rule);
			
			if ($this->failFast && $this->hasError()) {
				break;
			}
		}
	}
	
	/**
	 * Returns true if validation has errors.
	 */
	public function hasError(): bool
	{
		return !empty($this->errList);
	}
	
	/**
	 * Returns validation errors.
	 */
	public function getErrors(): array
	{
		return $this->errList;
	}
	
	/**
	 * Call this method to get the filtered input data by specified key.
	 *
	 * @param string $key The key of the input in the input data source
	 * @param mixed $default To be returned when optional input was not provided in the input source
	 * @return mixed The filtered input data
	 * */
	public function get(string $key, mixed $default = null): mixed
	{
		return
			array_key_exists($key, $this->data)
			? $this->data[$key]
			: $default;
	}
	
	/**
	 * Returns the filtered values by their keys. If the failFast param is true
	 * then the key may/may not be present in the returned array since failFast
	 * might have aborted the validation earlier before getting the change to
	 * validate that particular value.
	 *
	 * @return array
	 * */
	public function getValues(): array
	{
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
	public function list(): array
	{
		return array_values($this->data);
	}
	
	/**
	 * Set localization handler for filter output messages.
	 */
	public function setLocalization(FilterLocalization $localization): static
	{
		$this->localization = $localization;
		return $this;
	}
	
	private function reset(): void
	{
		$this->errList = [];
		$this->data = [];
	}
	
	private function processRule(array $source, array $rule): void
	{
		$key = $rule['key'];
		
		if (!array_key_exists($key, $source)) {
			$this->handleMissing($rule);
			return;
		}
		
		$data = $source[$key];
		
		if ($data === null) {
			$this->handleNull($rule);
			return;
		}
		
		if (is_string($data) && trim($data) === '') {
			$this->handleBlank($rule);
			return;
		}
		
		if ($rule['type'] === self::TYPE_MIXED) {
			$result = $this->inOneOf($data, $rule);
			
			if (!Filter::isOK($result)) {
				$this->addError($result, $rule);
				return;
			}
			
			$this->data[$key] = $data;
			return;
		}
		
		$result = $this->checkByType($data, $rule);
		
		if (!Filter::isOK($result)) {
			$this->addError($result, $rule);
			return;
		}
		
		$result = $this->inOptions($data, $rule);
		
		if (!Filter::isOK($result)) {
			$this->addError($result, $rule);
			return;
		}
		
		$this->data[$key] = $data;
	}
	
	private function handleMissing(array $rule): void
	{
		if ($rule['required']) {
			$this->addError(FilterOut::NULL, $rule);
			return;
		}
		
		$this->data[$rule['key']] =
			$rule['hasDefault']
			? $rule['default']
			: null;
	}
	
	private function handleNull(array $rule): void
	{
		if ($rule['nullable']) {
			$this->data[$rule['key']] = null;
			return;
		}
		
		if ($rule['required']) {
			$this->addError(FilterOut::NULL, $rule);
			return;
		}
		
		$this->data[$rule['key']] =
			$rule['hasDefault']
			? $rule['default']
			: null;
	}
	
	private function handleBlank(array $rule): void
	{
		if ($rule['required']) {
			$this->addError(FilterOut::EMPTY, $rule);
			return;
		}
		
		$this->data[$rule['key']] =
			$rule['hasDefault']
			? $rule['default']
			: null;
	}
	
	private function checkByType(mixed &$data, array $rule): FilterOut
	{
		return match ($rule['type']) {
			self::TYPE_STRING => $this->checkString($data, $rule),
			self::TYPE_STRING_RAW => $this->checkRawString($data, $rule),
			self::TYPE_INTEGER => $this->checkInteger($data, $rule),
			self::TYPE_FLOAT => $this->checkFloat($data, $rule),
			self::TYPE_BOOL => $this->checkBool($data),
			self::TYPE_EMAIL => $this->checkEmail($data),
			self::TYPE_URL => $this->checkURL($data),
			self::TYPE_ISO_TIME => $this->checkISOTime($data),
			self::TYPE_ISO_DATE => $this->checkISODate($data),
			self::TYPE_ISO_DATETIME => $this->checkISODatetime($data),
			default => throw new RuntimeException("Filter type {$rule['type']} isn't supported"),
		};
	}
	
	private function checkString(mixed &$data, array $rule): FilterOut
	{
		$v = Filter::checkString($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$x = $this->checkLen($v, $rule);
		if (!Filter::isOK($x)) return $x;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkRawString(mixed &$data, array $rule): FilterOut
	{
		$v = Filter::checkString($data, null);
		
		if (!Filter::isOK($v)) return $v;
		
		$x = $this->checkLen($v, $rule);
		if (!Filter::isOK($x)) return $x;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkInteger(mixed &$data, array $rule): FilterOut
	{
		$raw = $data;
		
		$v = Filter::checkInt($data);
		
		if (!Filter::isOK($v)) return $v;
		
		
		$x = $this->checkLen((string) $raw, $rule);
		if (!Filter::isOK($x)) return $x;
		
		$x = $this->inRange($v, $rule);
		if (!Filter::isOK($x)) return $x;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkFloat(mixed &$data, array $rule): FilterOut
	{
		$raw = $data;
		
		$v = Filter::checkFloat($data);
		if (!Filter::isOK($v)) return $v;
		
		$x = $this->checkLen((string) $raw, $rule);
		if (!Filter::isOK($x)) return $x;
		
		$x = $this->inRange($v, $rule);
		if (!Filter::isOK($x)) return $x;
		
		if ($rule['place'] !== null) {
			$x = $this->checkDecimalPlace($raw, $rule['place']);
			
			if (!Filter::isOK($x)) {
				return $x;
			}
		}
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkBool(mixed &$data): FilterOut
	{
		$v = Filter::checkBool($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkEmail(mixed &$data): FilterOut
	{
		$v = Filter::checkEmail($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkURL(mixed &$data): FilterOut
	{
		$v = Filter::checkURL($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkISOTime(mixed &$data): FilterOut
	{
		$v = Filter::checkISOTime($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkISODate(mixed &$data): FilterOut
	{
		$v = Filter::checkISODate($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkISODatetime(mixed &$data): FilterOut
	{
		$v = Filter::checkISODatetime($data);
		
		if (!Filter::isOK($v)) return $v;
		
		$data = $v;
		return FilterOut::OK;
	}
	
	private function checkLen(mixed $data, array $rule): FilterOut
	{
		if ($rule['minLen'] === null && $rule['maxLen'] === null) {
			return FilterOut::OK;
		}
		
		$v = Filter::checkStrLen((string) $data, $rule['minLen'], $rule['maxLen']);
		
		return is_string($v) ? FilterOut::OK : $v;
	}
	
	private function inRange(int|float $data, array $rule): FilterOut
	{
		$min = $rule['minValue'];
		$max = $rule['maxValue'];
		
		if ($min === null && $max === null) {
			return FilterOut::OK;
		}
		
		if ($min !== null && $max !== null && ($data < $min || $data > $max)) {
			return FilterOut::RANGE_ERROR;
		}
		
		if ($min !== null && $data < $min) {
			return FilterOut::RANGE_UNDER_ERROR;
		}
		
		if ($max !== null && $data > $max) {
			return FilterOut::RANGE_OVER_ERROR;
		}
		
		return FilterOut::OK;
	}
	
	private function inOptions(mixed $data, array $rule): FilterOut
	{
		if ($rule['options'] === null) {
			return FilterOut::OK;
		}
		
		return
			in_array($data, $rule['options'], true)
			? FilterOut::OK
			: FilterOut::NOT_IN_OPTION;
	}
	
	private function inOneOf(mixed $data, array $rule): FilterOut
	{
		return
			in_array($data, $rule['oneOf'], true)
			? FilterOut::OK
			: FilterOut::NOT_IN_OPTION;
	}
	
	private function checkDecimalPlace(mixed $raw, int $place): FilterOut
	{
		$value = trim((string) $raw);
		
		if (!str_contains($value, '.')) {
			return FilterOut::OK;
		}
		
		$fraction = explode('.', $value, 2)[1] ?? '';
		
		return
			strlen($fraction) <= $place
			? FilterOut::OK
			: FilterOut::RANGE_FRACTION_ERROR;
	}
	
	private function addError(FilterOut $err, array $rule): void
	{
		if ($err === FilterOut::OK) {
			return;
		}
		
		$key  = $rule['key'];
		$name = $rule['name'];
		
		$this->errList[$key] = [
			'key' => $key,
			'name' => $name,
			'message' => $this->getMsg($err, $name, $rule),
			'code' => $err->name
		];
	}
	
	private function getMsg(FilterOut $err, string $name, array $rule): string
	{
		$locale = $this->getLocalization();
		
		return match ($err) {
			FilterOut::NULL => $locale->nullInputErr($name),
			FilterOut::EMPTY => $locale->emptyInputErr($name),
			FilterOut::ILLEGAL => $locale->illegalInputErr($name),
			FilterOut::INVALID => $locale->invalidInputErr($name),
			
			FilterOut::RANGE_FRACTION_ERROR => $locale->rangeFractionInputErr(
				$name,
				$rule['place']
			),
			
			FilterOut::VAL_LEN_ERROR => $locale->inputLengthErr(
				$name,
				$rule['minLen'],
				$rule['maxLen']
			),
			
			FilterOut::VAL_LEN_OVER_ERROR => $locale->inputLengthOverErr(
				$name,
				$rule['maxLen']
			),
			
			FilterOut::VAL_LEN_UNDER_ERROR => $locale->inputLengthUnderErr(
				$name,
				$rule['minLen']
			),
			
			FilterOut::RANGE_ERROR => $locale->inputRangeErr(
				$name,
				$rule['minValue'],
				$rule['maxValue']
			),
			
			FilterOut::RANGE_OVER_ERROR => $locale->inputRangeOverErr(
				$name,
				$rule['maxValue']
			),
			
			FilterOut::RANGE_UNDER_ERROR => $locale->inputRangeUnderErr(
				$name,
				$rule['minValue']
			),
			
			FilterOut::NOT_IN_OPTION => $locale->invalidInputOptionErr(
				$name,
				$rule['type'] === self::TYPE_MIXED ? $rule['oneOf'] : $rule['options']
			),
			
			default => $locale->unknownErr(),
		};
	}
	
	private function getLocalization(): FilterLocalization
	{
		if ($this->localization === null) {
			$this->localization = new FilterLocalization();
		}
		
		return $this->localization;
	}
	
	private function normalizeRule(array $rule): array
	{
		if (!array_key_exists('key', $rule) || !is_string($rule['key']) || trim($rule['key']) === '') {
			throw new InvalidArgumentException('Filter rule requires a non-empty string key');
		}
		
		if (!array_key_exists('type', $rule) || !is_string($rule['type']) || trim($rule['type']) === '') {
			throw new InvalidArgumentException("Filter rule '{$rule['key']}' requires a non-empty string type");
		}
		
		$key = $rule['key'];
		$type = $rule['type'];
		
		if (!$this->isSupportedType($type)) {
			throw new InvalidArgumentException("Filter type '$type' is not supported for rule '$key'");
		}
		
		$normalized = [
			'key' => $key,
			'name' => $this->normalizeName($rule['name'] ?? null, $key),
			'type' => $type,
			
			'required' => $rule['required'] ?? true,
			'nullable' => $rule['nullable'] ?? false,
			
			'hasDefault' => array_key_exists('default', $rule),
			'default' => $rule['default'] ?? null,
			
			'minLen' => $rule['minLen'] ?? null,
			'maxLen' => $rule['maxLen'] ?? null,
			
			'minValue' => $rule['minValue'] ?? null,
			'maxValue' => $rule['maxValue'] ?? null,
			
			'place' => $rule['place'] ?? null,
			'options' => $rule['options'] ?? null,
			'oneOf' => $rule['oneOf'] ?? null,
		];
		
		$this->validateRuleConfig($normalized);
		
		return $normalized;
	}
	
	private function normalizeName(mixed $name, string $key): string
	{
		if (!is_string($name) || trim($name) === '') {
			return $key;
		}
		
		return $name;
	}
	
	private function validateRuleConfig(array $rule): void
	{
		$key = $rule['key'];
		
		if (!is_bool($rule['required'])) {
			throw new InvalidArgumentException("Rule '$key' requires 'required' to be boolean");
		}
		
		if (!is_bool($rule['nullable'])) {
			throw new InvalidArgumentException("Rule '$key' requires 'nullable' to be boolean");
		}
		
		$this->assertNullableInt($rule, 'minLen');
		$this->assertNullableInt($rule, 'maxLen');
		
		if ($rule['minLen'] !== null && $rule['minLen'] < 0) {
			throw new InvalidArgumentException("Rule '$key' minLen cannot be negative");
		}
		
		if ($rule['maxLen'] !== null && $rule['maxLen'] < 0) {
			throw new InvalidArgumentException("Rule '$key' maxLen cannot be negative");
		}
		
		if ($rule['minLen'] !== null && $rule['maxLen'] !== null && $rule['minLen'] > $rule['maxLen']) {
			throw new InvalidArgumentException("Rule '$key' minLen cannot be greater than maxLen");
		}
		
		$this->assertNullableNumber($rule, 'minValue');
		$this->assertNullableNumber($rule, 'maxValue');
		
		if ($rule['minValue'] !== null && $rule['maxValue'] !== null && $rule['minValue'] > $rule['maxValue']) {
			throw new InvalidArgumentException("Rule '$key' minValue cannot be greater than maxValue");
		}
		
		$this->assertNullableInt($rule, 'place');
		
		if ($rule['place'] !== null && $rule['place'] < 0) {
			throw new InvalidArgumentException("Rule '$key' place cannot be negative");
		}
		
		if ($rule['options'] !== null && !is_array($rule['options'])) {
			throw new InvalidArgumentException("Rule '$key' options must be an array");
		}
		
		if ($rule['oneOf'] !== null && !is_array($rule['oneOf'])) {
			throw new InvalidArgumentException("Rule '$key' oneOf must be an array");
		}
		
		if ($rule['type'] === self::TYPE_MIXED) {
			if ($rule['oneOf'] === null) {
				throw new InvalidArgumentException("Rule '$key' with mixed type requires oneOf");
			}
			
			if ($rule['options'] !== null) {
				throw new InvalidArgumentException("Rule '$key' with mixed type cannot use options; use oneOf");
			}
			
			return;
		}
		
		if ($rule['oneOf'] !== null) {
			throw new InvalidArgumentException("Rule '$key' cannot use oneOf unless type is mixed");
		}
	}
	
	private function assertNullableInt(array $rule, string $property): void
	{
		if ($rule[$property] !== null && !is_int($rule[$property])) {
			throw new InvalidArgumentException("Rule '{$rule['key']}' $property must be integer");
		}
	}
	
	private function assertNullableNumber(array $rule, string $property): void
	{
		if (
			$rule[$property] !== null &&
			!is_int($rule[$property]) &&
			!is_float($rule[$property])
		) {
			throw new InvalidArgumentException("Rule '{$rule['key']}' $property must be integer or float");
		}
	}
	
	private function isSupportedType(string $type): bool
	{
		$supportedTypes = [
			self::TYPE_EMAIL,
			self::TYPE_URL,
			self::TYPE_ISO_TIME,
			self::TYPE_ISO_DATE,
			self::TYPE_ISO_DATETIME,
			self::TYPE_STRING,
			self::TYPE_STRING_RAW,
			self::TYPE_INTEGER,
			self::TYPE_FLOAT,
			self::TYPE_BOOL,
			self::TYPE_MIXED,
		];
		
		return in_array($type, $supportedTypes, true);
	}
	
	private function defaultErrHandler(array $errList, DataFilter $filter): never
	{
		if (count($errList) > 1) {
			Trunk::http400($this->getLocalization()->validationFailed());
		}
		
		$err = reset($errList);
		
		$message =
			is_array($err) &&
			isset($err['message']) &&
			is_string($err['message']) &&
			trim($err['message']) !== ''
				? $err['message']
				: $this->getLocalization()->unknownErr();
		
		Trunk::http400($message);
	}
	
}