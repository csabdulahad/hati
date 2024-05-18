<?php
/** @noinspection PhpIllegalPsrClassPathInspection */
/** @noinspection PhpUnused */


use hati\cli\CLI;
use hati\cli\HatiCLI;
use hati\util\Console;


/*
 * Load HATI!
 * */
require __DIR__ . DIRECTORY_SEPARATOR . 'func.php';
loadHati();


/**
 * A helper build tool to generate ids for database profiles of
 * the project.
 *
 * @since 5.0.0
 * */
class dump_db extends HatiCLI {
	
	public function describeCLI(): void {
		$this->version = '2.0.0';
		$this->name = 'dump_db';
		$this->description = 'A tool which came with Hati library to generate database profile class with ease!';
	}
	
	public function setup(): void {
		$this->addOption([
			'shortName' 	=> 'j',
			'longName'  	=> 'json',
			'type'			=> 'str',
			'required'		=> false,
			'description' 	=> 'Tha path to the db.json file. Default location: "hati/db.json"'
		]);
		
		$this->addOption([
			'shortName'		=> 'ns',
			'longName'		=> 'namespace',
			'type'			=> 'str',
			'required'		=> false,
			'description'	=> 'The namespace for DB profile class'
		]);

		$this->addOption([
			'shortName'		=> 'p',
			'longName'		=> 'path',
			'typed'			=> 'str',
			'required'		=> false,
			'description' 	=> 'Path where to save the DB profile class. Default location: "hati" folder'
		]);
	}
	
	public function run(array $args): void {
		chdir(dirname(__DIR__));
		
		$savePath  = $args['--p'] ?? '';
		$namespace = $args['--ns'] ?? '';
		
		$fileLocation = $args['--j'] ?? 'db.json';
		$path = realpath($fileLocation);

		if (!file_exists($path)) {
			$this->error("The db file doesn't exist at: $fileLocation");
		}
		
		CLI::progress("Reading db.json: $path");
		$data = file_get_contents($path);
		$data = json_decode($data, true);
		$this->sleep(.5);

		if (json_last_error() != JSON_ERROR_NONE) {
			CLI::progress(null);
			$this->error("Couldn't parse db.json at: $path");
		}

		CLI::progress('Building up profile constants');
		$dbProfiles = $data['db_profiles'];
		$profiles = array_keys($dbProfiles);
		$this->sleep(.5);

		$constant = '';
		array_map(function($profile) use ($dbProfiles, &$constant) {
			$dbNames = $dbProfiles[$profile]['db'];
			foreach ($dbNames as $db) {
				$value = "$profile:$db";
				$name = preg_replace('/[^a-zA-Z0-9_]/', '_', $value);

				$constant .= "\tpublic const $name = \"$value\";\n";
			}
		}, $profiles);

		$class = $this->getClsContent($constant, $namespace);
		
		CLI::progress('Saving constants in class file');
		$savePath = realpath($savePath) . DIRECTORY_SEPARATOR . 'DBPro.php';
		
		if (file_exists($savePath)) {
			CLI::progress(null);
			if (!CLI::confirm('Another file already exists! Confirm override')) {
				Console::warn('Writing class file was canceled');
				$this->exit();
			}
		}
		
		$file = fopen($savePath, 'w+');
		$output = fputs($file, $class);
		$this->sleep(.5);
		CLI::progress(null);
		
		if (!$output) {
			$this->error("Can't write to location: $savePath");
		}
		
		fflush($file);
		fclose($file);
		
		Console::success("Successfully generate DBPro class at: $savePath");
	}
	
	private function getClsContent(string $constant, string $ns): string {
		$ns = !empty($ns) ? "namespace $ns;": '';
		
		return <<<FILE
<?php

/**
* AUTO-GENERATED CLASS FILE BY HATI.
* FOR EVERY CHANGE TO db.json FILE, USE "hati/tool/dumpdb.php"
* SCRIPT TO REGENERATE THIS CLASS FILE AUTOMATICALLY.
*/

$ns

class DBPro {

$constant
}
FILE;
	}

}

HatiCLI::start(dump_db::class);