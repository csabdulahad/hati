<?php

	/**
	 * A helper tool to generate API listing found in the api registry for
	 * the project.
	 *
	 * @since 5.0.0
	 * */

	/*
	 * Get the API registry file
	 * */
	$root = dirname(__DIR__, 2) . '/api';
	$registryPath = "$root/hati_api_registry.php";
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
	eval($val);

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
					background-color: #212223;
					color: white;
				}
				
				h1 {
					background: black;
					border-left: 5px solid greenyellow;
					padding: 3px 3px 3px 8px;    
					margin-bottom: 6px;
					font-weight: normal;
				}
						
				table, td, th {
					border:1px solid #3A3E47;
				}
				
				th {
					font-size: 20px;
				}
				
				td, th {
					padding: 8px;
				}
				
				table {
					width: 100%;
					border-collapse: collapse;
				}
				
				td {
					border: 1px solid #3A3E47;
				}	
				
				.GET, .POST, .PUT, .PATCH, .DELETE {
					padding: 4px;
					border-radius: 3px;
					color: white;
				}
				
				.GET {
					background: #0EBF43;
				}
				
				.POST {
					background: #346BE5;
				}
				
				.PUT {
					background: #7314A0;
				}
				
				.PATCH {
					background: #E7751A;
				}
				
				.DELETE {
					background: red;
				}
			</style>
			</head>
			<body>
			 <h1> 🐘 API Documentation</h1>
			 <table>
				<tr style='background-color: #293C40; color: white;'>
					<th colspan='2'>API</th>
					<th>Handler</th>
					<th>Extension</th>
					<th>Description</th>
				</tr>
		";

	foreach ($apis as $api) {
		$method = "<td style='text-align: right; border-right: 0;'><span class='{$api['method']}'>{$api['method']}</span></td>";
		$path = "<td style='border-left: 0;'>api/{$api['path']}</td>";
		$handler = "<td>api/{$api['handler']}</td>";
		$extension = "<td>" . ($api['extension'] ?? 'N/A') . "</td>";
		$description = "<td>" . ($api['description'] ?? 'N/A') . "</td>";

		$html .= "<tr>$method $path $handler $extension $description</tr>";
	}
	$html .= "</table></body></html>";

	/*
	 * SAVE THE HTML AS FILE
	 * */
	chdir($root);
	$file = fopen('hati_api_doc.html', 'w+');
	fputs($file, $html);
	fflush($file);
	fclose($file);

	/**
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	function register(array $api) {
		global $apis;
		$apis[] = $api;
	}
