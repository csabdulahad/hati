<?php

namespace hati\trunk;

use hati\Response;

class TrunkOK extends Trunk {

    private const DEFAULT_MSG = 'A successful operation has been executed.';

    public function __construct(string $msg = self::DEFAULT_MSG, int $lvl = Response::LVL_USER) {
        parent::__construct($msg, Response::SUCCESS, $lvl);
    }

}