<?php

namespace hati\cli\io;

use RuntimeException;

final class BufferedIO extends TerminalIO
{
	
	private string $out = '';
	private string $err = '';
	
	private array $inputQueue = [];
	
	private bool $echoInput = false;
	
	/**
	 * @param list<string> $inputs  Pre-seeded inputs to be consumed by read()/input()/prompt()/confirm().
	 * @param bool $echoInput       If true, simulate terminal echo by writing the input to stdout when read.
	 */
	public function __construct(array $inputs = [], bool $echoInput = true)
	{
		$this->inputQueue = array_values($inputs);
		$this->echoInput  = $echoInput;
	}
	
	public function write(string $text = ''): void
	{
		$this->out .= $text . PHP_EOL;
	}
	
	public function writeNoLine(string $text = ''): void
	{
		$this->out .= $text;
	}
	
	public function error(string $text): void
	{
		$this->err .= $text;
	}
	
	/**
	 * Simulated STDIN.
	 * Returns a line *including* newline (like fgets), so TerminalIO::input() can decide trimming.
	 */
	public function read(): string
	{
		if ($this->inputQueue === []) {
			throw new RuntimeException('BufferedIO input queue exhausted (no more scripted input).');
		}
		
		$next = array_shift($this->inputQueue);
		
		// mimic real terminal input line endings
		$line = $next . PHP_EOL;
		
		// optional: echo the typed input to stdout (handy for golden output tests)
		if ($this->echoInput) {
			$this->writeNoLine($line);
		}
		
		return $line;
	}
	
	/**
	 * Push inputs to the front (so they are consumed next).
	 * @param list<string> $inputs
	 */
	public function unshiftInputs(array $inputs): void
	{
		if ($inputs === []) return;
		array_unshift($this->inputQueue, ...array_values($inputs));
	}
	
	/**
	 * Push inputs to the end (FIFO queue).
	 * @param array $inputs
	 */
	public function pushInputs(array $inputs): void
	{
		if ($inputs === []) return;
		foreach ($inputs as $v) {
			$this->inputQueue[] = $v;
		}
	}
	
	public function stdout(): string
	{
		return $this->out;
	}
	
	public function stderr(): string
	{
		return $this->err;
	}
	
	/** @return list<string> */
	public function remainingInputs(): array
	{
		return $this->inputQueue;
	}
	
	public function clear(): void
	{
		$this->out = '';
		$this->err = '';
	}
	
	public function isInteractive(): bool
	{
		return false;
	}
	
}