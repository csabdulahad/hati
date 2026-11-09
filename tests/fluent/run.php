<?php

declare(strict_types=1);

/**
 * Standalone Fluent integration checks.
 *
 * Run from the repository root: php tests/fluent/run.php
 * SQLite tests are skipped when pdo_sqlite is unavailable.
 * MySQL checks run only when HATI_TEST_MYSQL_DB is configured.
 */

use Hati\Fluent\DBMan;
use Hati\Fluent\DBProfile;
use Hati\Fluent\Fluent;
use Hati\Fluent\MySQLConfig;
use Hati\Fluent\SQLiteConfig;
use Hati\Trunk;

if (PHP_SAPI !== 'cli') {
	exit(1);
}

$root = dirname(__DIR__, 2);

spl_autoload_register(static function (string $class) use ($root): void {
	if (!str_starts_with($class, 'Hati\\')) return;

	$file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen('Hati\\'))) . '.php';

	if (is_file($file)) require_once $file;
});

$passed = 0;
$skipped = 0;

function check(bool $condition, string $description): void
{
	global $passed;

	if (!$condition) {
		throw new RuntimeException('FAIL: ' . $description);
	}

	$passed++;
	echo 'PASS ' . $description . PHP_EOL;
}

function skipCheck(string $description): void
{
	global $skipped;

	$skipped++;
	echo 'SKIP ' . $description . PHP_EOL;
}

function expectTrunk(callable $callback, string $description): void
{
	try {
		$callback();
	} catch (Trunk) {
		check(true, $description);
		return;
	}

	throw new RuntimeException('FAIL: ' . $description . ' (did not throw Trunk)');
}

// Profile creation does not open any database connection.
$mysql = DBProfile::mysql('mama', 'mama_db', 'mama_user', 'pass');
$mysqlConfig = $mysql->config();

check($mysqlConfig instanceof MySQLConfig, 'MySQL profile factory selects MySQL');
check($mysql->id() === 'mama', 'MySQL profile ID is preserved');
check($mysqlConfig->host === 'localhost', 'MySQL default host is unchanged');
check($mysqlConfig->port === 3306 && $mysqlConfig->charset === 'utf8mb4', 'MySQL defaults are unchanged');
check(!$mysql->poolEnabled(), 'Connection reuse is the default');

$mysql->enablePool()->setMaxConnection(3)->setAcquireTimeout(250)->enableBorrowValidation();
check($mysql->poolEnabled() && $mysql->maxConnection() === 3, 'Existing pooling API remains available');

$sqlite = DBProfile::sqlite('notes', ':memory:', 2000);
$sqliteConfig = $sqlite->config();
check($sqliteConfig instanceof SQLiteConfig, 'SQLite profile factory selects SQLite');
check($sqliteConfig->path === ':memory:' && $sqliteConfig->busyTimeoutMs === 2000, 'SQLite settings are retained');

expectTrunk(static fn() => DBProfile::sqlite('', ':memory:'), 'Empty profile ID rejected');
expectTrunk(static fn() => DBProfile::sqlite('notes', ''), 'Empty SQLite path rejected');
expectTrunk(static fn() => DBProfile::sqlite('notes', "file\0name"), 'NUL in SQLite path rejected');
expectTrunk(static fn() => DBProfile::sqlite('notes', ':memory:', -1), 'Negative SQLite timeout rejected');
expectTrunk(static fn() => DBProfile::mysql('mama', 'db', '', 'pass'), 'Missing MySQL username rejected');

$manager = new DBMan();
$manager->register($mysql);
$manager->register($sqlite);
check($manager->getProfile('notes') === $sqlite, 'Multiple engine profiles share one DBMan');
check($manager->fluent('notes') instanceof Fluent, 'DBMan returns the same Fluent type for SQLite');

if (!extension_loaded('pdo_sqlite')) {
	skipCheck('SQLite integration (pdo_sqlite not installed)');
} else {
	// The cached :memory: connection is retained for this DBMan lifetime.
	$db = $manager->fluent('notes');

	$db->exec('CREATE TABLE parent (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
	$db->exec('CREATE TABLE child (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES parent(id))');

	$db->insertPrepare('parent', ['name' => 'First note']);
	$id = (int) $db->lastInsertRowId();

	check($id === 1, 'SQLite prepared insert and last insert ID');
	check($db->query('SELECT name FROM parent WHERE id = ?', [$id])->read('name') === 'First note',
		'SQLite prepared select and fetch');

	$db->updatePrepare('parent', ['name' => 'Updated note'], [], 'id = ?', [$id]);
	check($db->query('SELECT name FROM parent WHERE id = ?', [$id])->read('name') === 'Updated note',
		'SQLite prepared update');

	$foreignKeyFailure = false;

	try {
		$db->exePrepare('INSERT INTO child(parent_id) VALUES (?)', [999]);
	} catch (Trunk) {
		$foreignKeyFailure = true;
	}

	check($foreignKeyFailure, 'SQLite foreign keys are enforced');

	$db->atomic(static function (Fluent $tx): void {
		$tx->exePrepare('INSERT INTO parent(name) VALUES (?)', ['Committed']);
	});

	check($db->query('SELECT COUNT(*) AS total FROM parent')->sqlCount() === 2,
		'SQLite atomic transaction commits');

	$rolledBack = false;

	try {
		$db->atomic(static function (Fluent $tx): void {
			$tx->exePrepare('INSERT INTO parent(name) VALUES (?)', ['Must roll back']);
			throw new RuntimeException('Test transaction rollback');
		});
	} catch (RuntimeException) {
		$rolledBack = true;
	}

	check($rolledBack, 'Test rollback exception was propagated');
	check($db->query('SELECT COUNT(*) AS total FROM parent')->sqlCount() === 2,
		'SQLite atomic transaction rolls back');

	$db->withTransaction(static function (Fluent $tx): void {
		$tx->exePrepare('INSERT INTO parent(name) VALUES (?)', ['Manual transaction']);
		$tx->commit();
	});

	check($db->query('SELECT COUNT(*) AS total FROM parent')->sqlCount() === 3,
		'SQLite manual transaction commits');

	$db->deletePrepare('parent', 'id = ?', [$id]);
	check($db->query('SELECT COUNT(*) AS total FROM parent')->sqlCount() === 2,
		'SQLite prepared delete');

	$pragma = $db->query('PRAGMA busy_timeout')->read('timeout');
	check((int) $pragma === 2000, 'SQLite busy timeout is applied');

	$pragma = $db->query('PRAGMA foreign_keys')->read('foreign_keys');
	check((int) $pragma === 1, 'SQLite foreign keys enabled for each connection');

	// File-backed SQLite supports a shared pool because each PDO opens the same file.
	$path = sys_get_temp_dir() . '/hati-fluent-' . bin2hex(random_bytes(8)) . '.sqlite';

	try {
		$diskProfile = DBProfile::sqlite('disk', $path)->enablePool()->setMaxConnection(2);
		$manager->register($diskProfile);
		$disk = $manager->fluent('disk');

		$disk->exec('CREATE TABLE item (id INTEGER PRIMARY KEY, value TEXT)');
		$disk->exePrepare('INSERT INTO item(value) VALUES (?)', ['Visible across connections']);

		$manager->withConnection('disk', static function (PDO $first) use ($manager): void {
			$manager->withConnection('disk', static function (PDO $second) use ($first): void {
				check($first !== $second, 'SQLite file profile can borrow two pooled PDO connections');
				check((int) $second->query('SELECT COUNT(*) FROM item')->fetchColumn() === 1,
					'Pooled SQLite connections see the same file database');
			});
		});

		$stats = $manager->poolStats('disk');
		check($stats['opened'] === 2 && $stats['idle'] === 2,
			'Existing DBPool tracks SQLite connections');

		$manager->unregisterByID('disk');
	} finally {
		foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
			if (is_file($file)) unlink($file);
		}
	}
}

// Optional MySQL integration: use an isolated test database, never production credentials.
$mysqlDatabase = getenv('HATI_TEST_MYSQL_DB');

if (!is_string($mysqlDatabase) || $mysqlDatabase === '') {
	skipCheck('MySQL integration (set HATI_TEST_MYSQL_DB to opt in)');
} elseif (!extension_loaded('pdo_mysql')) {
	skipCheck('MySQL integration (pdo_mysql not installed)');
} else {
	$host = getenv('HATI_TEST_MYSQL_HOST') ?: 'localhost';
	$user = getenv('HATI_TEST_MYSQL_USER') ?: '';
	$password = getenv('HATI_TEST_MYSQL_PASSWORD') ?: '';
	$profile = DBProfile::mysql('mysql-test', $mysqlDatabase, $user, $password, $host, timezone: '+00:00');
	$manager->register($profile);

	$mysqlDb = $manager->fluent('mysql-test');
	check((int) $mysqlDb->query('SELECT 1 AS result')->read('result') === 1,
		'MySQL connection and Fluent remain functional');
	check($mysqlDb->query('SELECT @@session.time_zone AS timezone')->read('timezone') === '+00:00',
		'MySQL session timezone still applies');
}

echo PHP_EOL . "Passed: $passed; Skipped: $skipped" . PHP_EOL;
echo 'SQLite feature checks must run with pdo_sqlite before release.' . PHP_EOL;
