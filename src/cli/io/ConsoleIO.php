<?php

namespace hati\cli\io;

final class ConsoleIO extends TerminalIO
{
	public function write(string $text = ''): void
	{
		fwrite(STDOUT, $text . PHP_EOL);
	}
	
	public function writeNoLine(string $text = ''): void
	{
		fwrite(STDOUT, $text);
	}
	
	public function error(string $text): void
	{
		$text = $this->color($text, 'light_red');
		fwrite(STDERR, $text . PHP_EOL);
	}
	
	public function read(): string
	{
		$line = fgets(fopen('php://stdin', 'rb'));
		
		if ($line === false) return '';
		return $line; // keep newline like real fgets
	}
	
	public function isInteractive(): bool
	{
		return true;
	}
	
}