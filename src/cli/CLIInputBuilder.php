<?php

namespace hati\cli;

use hati\util\Arr;
use InvalidArgumentException;

final class CLIInputBuilder
{
	private HatiCLI $cli;
	
	public function __construct(HatiCLI $cli)
	{
		$this->cli = $cli;
	}
	
	private array $data = [];
	
	public function addFlag(): void
	{
		$this->checkCommonProperties('Flag');
		
		unset($this->data['type']);
		unset($this->data['required']);
		unset($this->data['allow_extra']);
		unset($this->data['options']);
		unset($this->data['default']);
		
		$this->cli->addFlag($this->data);
		$this->data = [];
	}
	
	public function addOption(): void
	{
		$this->checkCommonProperties('Option');
		
		$this->cli->addOption($this->data);
		$this->data = [];
	}
	
	public function name(string $name): self
	{
		$this->data['name'] = ltrim($name, '-');
		return $this;
	}
	
	public function alias(string $alias): self
	{
		$this->data['alias'] = ltrim($alias, '-');
		return $this;
	}
	
	public function description(string $description): self
	{
		$this->data['description'] = $description;
		return $this;
	}
	
	/**
	 * Sets the data type for this input
	 *
	 * @param string $type data type. It can be string, int and float
	 * @return self
	 * */
	public function type(string $type): self
	{
		$allowedTypes = ['str', 'string', 'int', 'float'];
		
		if (!in_array($type, $allowedTypes)) {
			$typeAsStr = Arr::strList($allowedTypes);
			throw new InvalidArgumentException("Invalid type $type. Allowed types are: $typeAsStr");
		}
		
		$this->data['type'] = $type;
		return $this;
	}
	
	public function allowExtra(): self
	{
		$this->data['allow_extra'] = true;
		return $this;
	}
	
	public function required(): self
	{
		$this->data['required'] = true;
		return $this;
	}
	
	public function notRequired(): self
	{
		$this->data['required'] = false;
		return $this;
	}
	
	public function validOptions(array $options): self
	{
		$this->data['options'] = $options;
		return $this;
	}
	
	public function optional(mixed $value = null): self
	{
		$this->data['default'] = $value;
		return $this;
	}
	
	private function checkCommonProperties(string $type): void
	{
		if (empty($this->data['name'])) {
			$this->cli->error("$type must have a name");
		}
		
		/*
		 * Check flag alias length
		 * */
		if ($type === 'Flag') {
			$alias = $this->data['alias'] ?? '';
			
			if (strlen($alias) > 1) {
				$this->cli->error("$type alias must a single character");
			}
		}
	}
	
}