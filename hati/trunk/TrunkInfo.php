<?php

namespace hati\trunk;

use hati\Response;

class TrunkInfo extends Trunk {

    private const DEFAULT_MSG = 'Server had something to inform about.';

    public function __construct(string $msg = self::DEFAULT_MSG, int $lvl = Response::LVL_USER) {
        parent::__construct($msg, Response::INFO, $lvl);
    }

}