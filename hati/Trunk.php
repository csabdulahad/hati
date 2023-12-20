<?php

namespace hati;

use hati\api\Response;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;

class Trunk extends RuntimeException {

	private const DEFAULT_MSG = 'An unknown error occurred.';

	private string $msg;
	private int $status;

	public function __construct(string $msg = self::DEFAULT_MSG) {
		parent::__construct($msg);

		$this -> msg = $msg;
		$this -> status = Response::ERROR;
	}

	public function getMsg(): string {
		return $this -> msg;
	}

	public function getStatus(): int {
		return $this -> status;
	}

	public function __toString(): string {
		return Response::reportJSON($this -> msg, $this -> status);
	}

	#[NoReturn]
	public function report(): void {
		echo $this;
		exit(2);
	}

}