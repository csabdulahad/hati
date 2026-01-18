<?php

namespace hati\filter;

use hati\util\Arr;
use hati\util\Text;

class FilterLocalization {
	
	protected function pluralMsg(int $count, string $noun): string {
		if ($count > 1) {
			$plural = Text::plural($noun);
			return "$count $plural";
		}
		
		return "$count $noun";
	}
	
	public function nullInputErr(string $name): string {
		return "$name is required";
	}
	
	public function emptyInputErr(string $name): string {
		return "$name is empty";
	}
	
	public function illegalInputErr(string $name): string {
		return "$name contains illegal character";
	}
	
	public function invalidInputErr(string $name): string {
		return "$name is invalid";
	}
	
	public function rangeFractionInputErr(string $name, int $decimalPlaces): string {
		return
			"$name can't have more than " .
			$this->pluralMsg($decimalPlaces, 'digit') .
			" after decimal point";
	}
	
	public function inputLengthErr(string $name, int $minLen, int $maxLen): string {
		return "$name can't be lower or higher than $minLen-$maxLen characters in length";
	}
	
	public function inputLengthOverErr(string $name, int $maxLen): string {
		return "$name can't exceed " . $this->pluralMsg($maxLen, 'character') . " in length";
	}
	
	public function inputLengthUnderErr(string $name, int $minLen): string {
		return "$name can't be less than " . $this->pluralMsg($minLen, 'character') . " in length";
	}
	
	public function inputRangeErr(string $name, int|float $minValue, int|float $maxValue): string {
		return "$name must have limit of $minValue-$maxValue";
	}
	
	public function inputRangeOverErr(string $name, int|float $maxValue): string {
		return "$name can't be greater than $maxValue";
	}
	
	public function inputRangeUnderErr(string $name, int|float $minValue): string {
		return "$name can't be lower than $minValue";
	}
	
	public function invalidInputOptionErr(string $name, array $options): string {
		return "$name must be one of the following: " . Arr::strList($options);
	}
	
	public function unknownErr(): string {
		return "Unknown error";
	}
	
}