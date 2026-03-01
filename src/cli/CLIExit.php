<?php

namespace hati\cli;

use RuntimeException;

/**
 * Internal exception used by HatiCLI to handle error/exit
 * function properly for cases when CLI is called from within
 * another script.
 * */
final class CLIExit extends RuntimeException
{
	
	public function __construct(public $code, public $message = '')
	{
		parent::__construct($message, $code);
	}
	
}