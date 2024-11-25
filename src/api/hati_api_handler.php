<?php

/*********************************************************************
 *                     !!! Handle CORS !!!
 * Modify the CORS request as per API requirement below.
 * Hati provided bare minimum header configurations to allow APIs
 * to be invoked from cross origin manner. For web apps, Cookies are
 * request to be attached in actual request
 *********************************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
	header('Access-Control-Allow-Headers: Accept, Content-Type, Authorization, X-Requested-With');

	// Cache preflight OPTIONS response valid for 86400 seconds [a day]
	header('Access-Control-Max-Age: 86400');

	http_response_code(204);
	exit();
}

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');

/********************************************************************
 *                     !!! Hati API Handler !!!
 * This file handles the API request coming to the '/api' folder on
 * the server. Make sure that all the request to this folder is
 * directed to this file using server configuration or .htaccess
 * file provided with the Hati library
 ********************************************************************/

// Control the HatiAPIHandler debug output
hati\api\HatiAPIHandler::$DEBUG = false;

// Define constant to prevent direct access to APIs handler files
const HATI_API_CALL = true;

// Invoke the Hati API handler!
hati\api\HatiAPIHandler::boot();