<?php

namespace hati\trunk;

use hati\Response;

class TrunkWarn extends Trunk {

    private const DEFAULT_MSG = 'Server raised an unknown warning';

    public function __construct(string $msg = self::DEFAULT_MSG, int $lvl = Response::LVL_USER) {
        parent::__construct($msg, Response::WARNING, $lvl);
    }

}