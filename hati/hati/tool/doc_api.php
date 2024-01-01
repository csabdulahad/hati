<?php

	/**
	 * A helper tool to generate API listing found in the api registry for
	 * the project.
	 *
	 * @since 5.0.0
	 * */

	/*
	 * Load hati!
	 * */
	use hati\util\Shomoy;

	require __DIR__ . '/func.php';
	$rootPath = loadHati();

	if (empty($rootPath)) {
		echo 'Failed to locate vendor folder';
		exit;
	}

	/*
	 * Get the API registry file
	 * */
	$root = $rootPath . 'api' . DIRECTORY_SEPARATOR;
	$registryPath = "{$root}hati_api_registry.php";

	if (!file_exists($registryPath)) {
		echo 'API registry file is missing at: ' . $registryPath;
		exit;
	}

	/*
	 * Get the API config
	 * */
	$apis = [];
	$data = file_get_contents($registryPath);
	$val = str_replace(['\hati\api\HatiAPIHandler::', 'HatiAPIHandler::', '<?php'], [''], $data);

	try {
		eval($val);
	} catch (Throwable) {
		echo 'Failed to generate API documentation';
		exit;
	}

	/*
	 * Flatten the API methods
	 * */
	const HATI_API_CALL = true;
	$x = [];
	$openAPIs = [];

	foreach ($apis as $i => $api) {

		$methods = $api['method'];
		if (is_string($methods)) {
			$methods = [$methods];
		}

		foreach ($methods as $m) {
			$x[] = [
				'method' => $m,
				'path' => $api['path'],
				'handler' => $api['handler'],
				'description' => $api['description'] ?? '-',
			];
		}

		// Get extensions as API method
		$ext = $api['extension'] ?? [];
		if (is_string($ext)) {
			$ext = [$ext];
		}

		foreach ($ext as $e) {
			$x[] = [
				'method' => $e,
				'path' => $api['path'],
				'handler' => $api['handler'],
				'description' => $api['description'] ?? '-'
			];

			$methods[] = $e;
		}

		/*
		 * Build up the in-memory API
		 * */
		$class = basename($api['handler']);
		$class = substr($class, 0, strpos($class, '.'));

		require $root . $api['handler'];
		$instance = new $class;
		$instance -> publicMethod();

		$access = [];
		foreach ($methods as $method) {
			$private = $instance -> isPrivateMethod($method);
			if (!$private) $access[] = $method;
		}

		if (!empty($access))
			$openAPIs[$api['path']] = $access;
	}

	/*
	 * Add open APIs as either public/private if handler & method match with
	 * open APIs array.
	 * */
	$y = [];
	foreach ($x as $api) {

		$openList = $openAPIs[$api['path']] ?? null;

		$access = '🔒';
		if (!empty($openList)) {
			$access = in_array($api['method'], $openList) ? '🌏' : '🔒';
		}

		$api['access'] = $access;
		$y[] = $api;

	}

	/*
	 * BUILD THE TABLE DATA
	 * */
	$html = "<!DOCTYPE html>
					<html lang=\"en\">
					<head>
					<title>🐘 API Documentation</title>
					<meta charset='utf-8'>
					<style>
						body {
							margin: 0;
							padding: 0 16px;
							font-family: Calibri, serif;
							background-color: whitesmoke;
						}
						
						h1 {
							background: white;
							border-left: 5px solid greenyellow;
							padding: 3px 3px 3px 8px;
							margin-top: 16px;    
							margin-bottom: 16px;
							font-weight: normal;
						}
								
						table, td, th {
							border:1px solid lightgray;
						}
						
						tr:hover {
							background-color: #ECECEC;
						}
						
						th {
							font-size: 20px;
							border: 0;
						}
						
						td, th {
							padding: 10px;
						}
						
						table {
							width: 100%;
							table-layout: auto;
							border-collapse: collapse;
							background-color: white;
						}
						
						td {
							border: 1px solid lightgray;
						}
						
						.GET, .POST, .PUT, .PATCH, .DELETE, .EXT {
							display: block;
							width: 96%;
							text-align: center;
							padding: 4px 2%;
							border-radius: 3px;
							font-weight: bold;
						}
						
						.GET {
							background: #198754;
							color: white;
						}
						
						.POST {
							background: #0D6EFD;
							color: white;
						}
						
						.PUT {
							background: #0DCAF0;
							color: black;
						}
						
						.PATCH {
							background: #6C757D;
							color: white;
						}
						
						.DELETE {
							background: #DC3545;
							color: white;
						}
						
						.EXT {
							background-color: #FFC107;
							color: black;
						}
					</style>
					</head>
					<body>
					 <h1>🐘 API Documentation - " . count($y) . " API</h1>
					 <table>
						<tr style='background-color: #A6C3CA; color: #055160;'>
							<th>Method</th>
							<th>Access</th>
							<th>API</th>
							<th>Handler</th>
							<th>Description</th>
						</tr>
				";

	$html .= "<p style='color: #242424; margin-bottom: 4px; text-align: right;'>Updated on: " . (new Shomoy()) -> strDateTime() . "</p>";

	foreach ($y as $api) {
		$extension = !in_array($api['method'], ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
		$methodCls = $extension ? 'EXT' : $api['method'];
		$apiPath = $extension ? "{$api['path']}/<b>{$api['method']}</b>" : $api['path'];

		$access = "<td style='text-align: left ; border-right: 0; width: auto;'><span class='$methodCls'>{$api['method']}</span></td>";
		$path = "<td style='text-align: center;'>{$api['access']}</td><td style='border-left: 0;'>api/$apiPath</td>";
		$handler = "<td>api/{$api['handler']}</td>";
		$description = "<td>" . ($api['description'] ?? '-') . "</td>";

		$html .= "<tr>$access $path $handler $description</tr>";
	}
	$html .= "</table>";
	$html .= "</body></html>";

	/*
	 * SAVE THE HTML AS FILE
	 * */
	chdir($root);
	$file = fopen('hati_api_doc.html', 'w+');
	fputs($file, $html);
	fflush($file);
	fclose($file);

	echo "API doc was saved at: $root" . DIRECTORY_SEPARATOR . "hati_api_doc.html";

	/**
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	function register(array $api) {
		global $apis;
		$apis[] = $api;
	}
