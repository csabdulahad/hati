<?php

namespace hati\fluent;

interface DBConfig {
	function name(): string;

	function host(): string;

	function db(): string;

	function username(): string;

	function password(): string;
}