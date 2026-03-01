<?php

namespace hati\cli;

use hati\cli\io\TerminalIO;

final class CLIContext
{
	public function __construct(
		public bool          $embedded = false,
		public ?TerminalIO   $io = null,
		public CLIExitPolicy $exitPolicy = CLIExitPolicy::PROPAGATE,
		public bool          $captureOutput = false,
	)
	{
	}
}