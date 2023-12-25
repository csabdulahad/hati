<?php

	/**
	 * A helper build tool to generate ids for database profiles of
	 * the project.
	 *
	 * @since 5.0.0
	 * */

	$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db.json';

	$data = file_get_contents($path);
	$data = json_decode($data, true);

	if (json_last_error() != JSON_ERROR_NONE)
		throw new RuntimeException("Couldn't parse db.json at: $path");

	$dbProfiles = $data['db_profiles'];
	$profiles = array_keys($dbProfiles);

	$constant = '';
	array_map(function($profile) use ($dbProfiles) {
		$dbNames = $dbProfiles[$profile]['db'];
		foreach ($dbNames as $db) {
			$value = "$profile:$db";
			$name = preg_replace('/[^a-zA-Z0-9_]/', '_', $value);

			global $constant;
			$constant .= "\tpublic const $name = \"$value\";\n";
		}
	}, $profiles);

	$class = <<<FILE
	<?php
		
	/**
	 * AUTO-GENERATED CLASS FILE BY HATI.
	 * FOR EVERY CHANGE TO db.json FILE, USE "hati/tool/dumpdb.php" 
	 * SCRIPT TO REGENERATE THIS CLASS FILE AUTOMATICALLY.
	 */	
	 
	class DBPro {
	
	$constant
	}
	FILE;

	chdir(dirname(__DIR__));
	$file = fopen('DBPro.php', 'w+');
	fputs($file, $class);
	fflush($file);
	fclose($file);