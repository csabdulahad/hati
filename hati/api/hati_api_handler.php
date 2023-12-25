<?php

/********************************************************************
 *                     !!! Hati API Handler !!!						*
 * This file handles the API request coming to the '/api' folder on *
 * the server. Make sure that all the request to this folder is 	*
 * directed to this file using server configuration or .htaccess 	*
 * file provided with the Hati library								*
 ********************************************************************/

// Invoke the Hati API handler!
hati\api\HatiAPIHandler::boot();