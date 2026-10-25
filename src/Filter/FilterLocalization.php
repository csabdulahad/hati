<?php

namespace Hati\Filter;

use Hati\Util\Arr;
use Hati\Util\Text;

class FilterLocalization
{
	
	protected function pluralMsg(int $count, string $noun): string
	{
		if ($count > 1) {
			$plural = Text::plural($noun);
			return "$count $plural";
		}
		
		return "$count $noun";
	}
	
	public function nullInputErr(string $name): string
	{
		return "$name is required";
	}
	
	public function emptyInputErr(string $name): string
	{
		return "$name is empty";
	}
	
	public function illegalInputErr(string $name): string
	{
		return "$name contains illegal characters";
	}
	
	public function invalidInputErr(string $name): string
	{
		return "$name is invalid";
	}
	
	public function rangeFractionInputErr(string $name, int $decimalPlaces): string
	{
		return
			"$name can't have more than " .
			$this->pluralMsg($decimalPlaces, 'digit') .
			" after decimal point";
	}
	
	public function inputLengthErr(string $name, int $minLen, int $maxLen): string
	{
		return "$name must be between $minLen and $maxLen characters";
	}
	
	public function inputLengthOverErr(string $name, int $maxLen): string
	{
		return "$name can't exceed " . $this->pluralMsg($maxLen, 'character') . " in length";
	}
	
	public function inputLengthUnderErr(string $name, int $minLen): string
	{
		return "$name can't be less than " . $this->pluralMsg($minLen, 'character') . " in length";
	}
	
	/**
	 * Byte-length validation messages.
	 */
	public function inputByteLengthErr(
		string $name,
		int $minByte,
		int $maxByte
	): string
	{
		return "$name must be between $minByte and $maxByte bytes";
	}
	
	public function inputByteLengthUnderErr(
		string $name,
		int $minByte
	): string
	{
		return "$name must contain at least $minByte bytes";
	}
	
	public function inputByteLengthOverErr(
		string $name,
		int $maxByte
	): string
	{
		return "$name must not exceed $maxByte bytes";
	}
	
	/**
	 * Unicode code-point validation messages.
	 */
	public function inputCodePointLengthErr(string $name, int $minCodePoint, int $maxCodePoint): string
	{
		return "$name must contain between $minCodePoint and $maxCodePoint Unicode code points";
	}
	
	public function inputCodePointLengthUnderErr(string $name, int $minCodePoint): string
	{
		return "$name must contain at least $minCodePoint Unicode code points";
	}
	
	public function inputCodePointLengthOverErr(string $name, int $maxCodePoint): string
	{
		return "$name must not exceed $maxCodePoint Unicode code points";
	}
	
	/**
	 * Regular-expression validation message.
	 */
	public function regexMismatchErr(string $name): string
	{
		return "$name has an invalid format";
	}
	
	public function inputRangeErr(string $name, int|float $minValue, int|float $maxValue): string
	{
		return "$name must be between $minValue and $maxValue";
	}
	
	public function inputRangeOverErr(string $name, int|float $maxValue): string
	{
		return "$name can't be greater than $maxValue";
	}
	
	public function inputRangeUnderErr(string $name, int|float $minValue): string
	{
		return "$name can't be less than $minValue";
	}
	
	public function invalidInputOptionErr(string $name, array $options): string
	{
		return "$name must be one of the following: " . Arr::strList($options);
	}
	
	public function validationFailed(): string
	{
		return "Data validation failed";
	}
	
	public function unknownErr(): string
	{
		return "Unknown error";
	}
	
}