<?php

/**
 * Hati API Registry.
 * Register API endpoints here using the {@link HatiAPIHandler::register()} method.
 */

\hati\api\HatiAPIHandler::register([
	'method' => 'GET',
	'path' => 'example/v1/greet',
	'handler' => 'v1/GreetAPI.php',
	'extension' => 'greetInBengali',
	'description' => 'An example API'
]);
