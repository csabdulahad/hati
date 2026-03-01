<?php

namespace hati\cli;

final class CLIResult
{
	public function __construct(
		public int    $code = 0,
		public string $stdout = '',
		public string $stderr = '',
	)
	{
	}
	
	public function ok(): bool
	{
		return $this->code === 0;
	}
}