<?php

namespace hati\trunk;

use hati\Response;

class TrunkErr extends Trunk {

    private const DEFAULT_MSG = 'An unknown error occurred.';

    public function __construct(string $msg = self::DEFAULT_MSG, int $lvl = Response::LVL_USER) {
        parent::__construct($msg, Response::ERROR, $lvl);
    }

}