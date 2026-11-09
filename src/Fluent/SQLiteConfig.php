<?php

namespace Hati\Fluent;

use Hati\Trunk;

/**
 * Immutable SQLite connection settings.
 *
 * The path may point to a database file or be ':memory:'. Opening a file-based
 * SQLite connection may create the file; Hati does not run schema migrations.
 */
final readonly class SQLiteConfig
{
	
	public function __construct(
		public string $path,
		public int $busyTimeoutMs = 5000
	)
	{
		if ($path === '' || str_contains($path, "\0")) {
			throw new Trunk('SQLite database path is invalid.');
		}
		
		if ($busyTimeoutMs < 0 || $busyTimeoutMs > 60000) {
			throw new Trunk('SQLite busy timeout must be between 0 and 60000 milliseconds.');
		}
	}
	
}
