<?php

/********************************************************************
 *                     !!! Hati API Handler !!!						*
 * This file handles the API request coming to the '/api' folder on *
 * the server. Make sure that all the request to this folder is 	*
 * directed to this file using server configuration or .htaccess 	*
 * file provided with the Hati library								*
 ********************************************************************/

// Define constant to prevent direct access to APIs handler files
const HATI_API_CALL = true;

// Invoke the Hati API handler!
hati\api\HatiAPIHandler::boot();