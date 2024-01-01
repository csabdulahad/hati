<?php

/************************************************************************************
 *                        !!! Hati API Registry !!!
 * Register API endpoints here using the {@link HatiAPIHandler::register()} method.
 ************************************************************************************/

// An example API:
// GET http://example.com/api/example/v1/greet
\hati\api\HatiAPIHandler::register([
	'method' => 'GET',
	'path' => 'example/v1/greet',
	'handler' => 'v1/GreetAPI.php',
	'extension' => 'greetInBengali',
	'description' => 'An example API'
]);
