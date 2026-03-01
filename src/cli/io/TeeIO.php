<?php

namespace hati\cli\io;

final class TeeIO extends TerminalIO
{
	public function __construct(
		private readonly TerminalIO $a, // primary (read source)
		private readonly TerminalIO $b  // secondary
	) {}
	
	public function write(string $text = ''): void
	{
		$this->a->write($text . PHP_EOL);
		$this->b->write($text . PHP_EOL);
	}
	
	public function writeNoLine(string $text = ''): void
	{
		$this->a->writeNoLine($text);
		$this->b->writeNoLine($text);
	}
	
	public function error(string $text): void
	{
		$this->a->error($text);
		$this->b->error($text);
	}
	
	/**
	 * Read only from primary.
	 * (Optional: you can also echo prompt/output to both by relying on TerminalIO::input()).
	 */
	public function read(): string
	{
		return $this->a->read();
	}
	
	public function primary(): TerminalIO
	{
		return $this->a;
	}
	
	public function secondary(): TerminalIO
	{
		return $this->b;
	}
	
	public function isInteractive(): bool
	{
		return $this->a->isInteractive();
	}
	
}