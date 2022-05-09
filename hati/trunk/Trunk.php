<?php

namespace hati\trunk;

use hati\Response;
use RuntimeException;

abstract class Trunk extends RuntimeException {

    private string $msg;
    private int $status;
    private int $level;

    public function __construct(string $msg, int $status, int $level) {
        parent::__construct($msg);
        $this -> msg = $msg;
        $this -> status = $status;
        $this -> level = $level;
    }

    public function __toString() {
        return Response::reportJSON($this -> msg, $this -> status, $this -> level);
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