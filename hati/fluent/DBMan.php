<?php

namespace hati\fluent;

use hati\Hati;
use hati\Trunk;
use PDO;
use Throwable;

/**
 * Database Manger or DBMan for short is a class which is used by {@link Fluent} class
 * to maintain various db connections. DBMan reads the db configuration file and fetches
 * the db profile to connect to. Each database connection is defined in config/db.json
 * file.
 *
 * Upon successful connection to a db, DBMan caches the connection as PDO object to
 * reuse it & the PDO object is identified by the db profile id which looks like:
 * DB_PROFILE:DB_NAME
 *
 * @since 5.0.0
 * */

class DBMan {

	// Loaded db configuration object
	private array $dbConfig;

	// Connection cache pool
	private array $dbPool= [];

	public function __construct() {
		$this -> dbConfig = Hati::dbConfigObj();
	}

	/**
	 * Connection to databases are cached to avoid memory leaks. This method
	 * fetches the db profile from the db json file as specified by the id
	 * argument. If cache is hit, the connection is returned, else it tries
	 * to connect to the database.
	 *
	 * @param ?string $id Id for the db profile. The profile name and the db name
	 * are separated by a colon in the id argument.
	 * @return ?PDO PDO object upon cache hit or successful connection, null
	 * otherwise
	 * */
	public function connect(?string $id): ?PDO {
		if (empty($id))
			throw new Trunk("DB profile with id $id was not found in the config");

		/*
		 * Extract profile name & db name
		 * */
		$segment = explode(':', $id);
		$proId = $segment[0] ?? '';
		$dbName = $segment[1] ?? '';

		/*
		 * See if the id is valid
		 * */
		$profile = $this -> dbConfig['db_profiles'][$proId] ?? null;
		if (empty($profile))
			throw new Trunk("Unknown db profile $id");

		if (!in_array($dbName, $profile['db'])) {
			$msg = empty($dbName) ?
				"Database name is not specified in the id $id followed by a colon"
				: "Database $dbName is not defined for in the config for $id";

			throw new Trunk($msg);
		}

		/*
		 * Check the cache
		 * */
		if (array_key_exists($id, $this -> dbPool)) {
			return $this -> dbPool[$id];
		}

		/*
		 * Connect to the db
		 * */
		try {
			$host = $profile['address'];
			$user = $profile['username'];
			$pass = $profile['password'];
			$charset = $profile['charset'] ?? 'utf8';

			// get the timezone offset
			$timeZone = date('P');

			$arg = "mysql:host=$host;dbname=$dbName;charset=$charset";
			$db = new PDO($arg, $user, $pass);
			$db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// set the timezone offset
			$db -> exec("SET time_zone = '$timeZone';");

			/*
			 * Cache the connection
			 * */
			$this -> dbPool[$id] = $db;

			return $db;
		} catch (Throwable $t) {
			throw new Trunk("Connection to database was failed: {$t -> getMessage()}");
		}
	}

}