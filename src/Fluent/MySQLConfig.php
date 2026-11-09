<?php

namespace Hati\Fluent;

use Hati\Trunk;

/**
 * Immutable connection settings for the existing MySQL PDO driver.
 * Connection creation and pooling remain the responsibility of DBMan.
 */
final readonly class MySQLConfig
{
	
	public function __construct(
		public string $database,
		public string $user,
		public string $password,
		public string $host = 'localhost',
		public string $charset = 'utf8mb4',
		public int $port = 3306,
		public ?string $timezone = null
	)
	{
		if ($database === '') {
			throw new Trunk('Database name cannot be empty.');
		}
		
		if ($user === '') {
			throw new Trunk('Database username cannot be empty.');
		}
		
		if ($host === '') {
			throw new Trunk('Database host cannot be empty.');
		}
		
		if ($charset === '') {
			throw new Trunk('Database charset cannot be empty.');
		}
		
		if ($port < 1 || $port > 65535) {
			throw new Trunk('Database port is invalid.');
		}
	}
	
}
