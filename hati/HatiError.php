<?php

namespace hati;

use RuntimeException;

class HatiError extends RuntimeException {

    private const DEFAULT_MSG = 'An unknown error occurred.';

    private string $msg;
    private int $status;
    private int $level;

    public function __construct(string $msg = self::DEFAULT_MSG , int $status = Response::ERROR, int $level = Response::LEVEL_SYSTEM) {
        parent::__construct($msg);
        $this -> msg = $msg;
        $this -> status = $status;
        $this -> level = $level;
    }

    public function __toString() {
        return Response::reportJSON($this -> msg, $this -> level, $this -> status);
    }

    public function report() {
        exit($this);
    }

    public function getMsg(): string {
        return $this -> msg;
    }

    public function getStatus(): int {
        return $this -> status;
    }

    public function getLevel(): int {
        return $this -> level;
    }

}