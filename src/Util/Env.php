<?php

namespace Hati\Util;

use RuntimeException;

final class Env
{
	
	private ?array $vars = null;
	
	private string $envFile {
		get {
			return $this->envFile;
		}
	}
	
	public function __construct(string $envFile)
	{
		$this->envFile = $envFile;
		
		if (!is_file($envFile) || !is_readable($envFile)) {
			throw new RuntimeException(".env file not found or not readable: $envFile");
		}
		
		$lines = file($envFile, FILE_IGNORE_NEW_LINES);
		if ($lines === false) {
			throw new RuntimeException("Failed to read .env file: $envFile");
		}
		
		$this->vars = [];
		
		foreach ($lines as $line) {
			$line = trim($line);
			
			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}
			
			$pos = strpos($line, '=');
			if ($pos === false) {
				continue;
			}
			
			$key = trim(substr($line, 0, $pos));
			$value = trim(substr($line, $pos + 1));
			
			if ($key === '') {
				continue;
			}
			
			$value = self::normalizeValue($value);
			
			$this->vars[$key] = $value;
		}
	}
	
	public function get(string $key, mixed $default = null): mixed
	{
		if ($this->vars === null) {
			throw new RuntimeException("Env not initialized");
		}
		
		if (array_key_exists($key, $this->vars)) {
			return $this->vars[$key];
		}
		
		if (func_num_args() === 1) {
			throw new RuntimeException("Missing required env key: $key in " . $this->envFile);
		}
		
		return $default;
	}
	
	public function getArray(string $key, ?array $default = null, string $separator = ','): array
	{
		if ($this->vars === null) {
			throw new RuntimeException("Env not initialized");
		}
		
		if (!array_key_exists($key, $this->vars)) {
			if ($default === null) {
				throw new RuntimeException("Missing required env key: $key in " . $this->envFile);
			}
			
			return $default;
		}
		
		$value = $this->vars[$key];
		
		if (!is_string($value)) {
			throw new RuntimeException("Env key '$key' must be a string to use getArray()");
		}
		
		return array_map(
			static fn(string $item) => trim($item),
			explode($separator, $value)
		);
	}
	
	private static function normalizeValue(string $value): mixed
	{
		// Strip quotes
		$len = strlen($value);
		
		if ($len >= 2) {
			$first = $value[0];
			$last = $value[$len - 1];
			
			if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
				$value = substr($value, 1, -1);
			}
		}
		
		$lower = strtolower($value);
		
		return match (true) {
			$lower === 'true' => true,
			$lower === 'false' => false,
			$lower === 'null' => null,
			$lower === '' => '',
			is_numeric($value) && ctype_digit(ltrim($value, '-')) => (int)$value,
			is_numeric($value) => (float)$value,
			default => $value,
		};
	}
	
}