# Hati 🐘

**Practical PHP building blocks, without getting in the developer's way.**

Hati is a PHP library for building APIs and applications. It provides a request/response layer, input validation, a PDO-backed database helper, CLI tools, and everyday utilities. Some class names draw inspiration from Bengali.

**Hati handles the plumbing. Developers control the database.**

[Packagist](https://packagist.org/packages/rootdata21/hati) · [Source](https://github.com/csabdulahad/hati) · [MIT License](LICENSE.md)

## Requirements

- PHP **8.5 or newer**, per `composer.json`.
- Composer and the required PHP extensions listed in `composer.json`.
- `pdo_mysql` **only if using MySQL**; `pdo_sqlite` **only if using SQLite**. Neither driver is a mandatory dependency for applications using the other engine.
- A database managed by your application. Hati does **not** create schemas or migrate databases automatically.

For the currently published package, see [Packagist](https://packagist.org/packages/rootdata21/hati):

~~~bash
composer require rootdata21/hati
~~~

To try the unpublished `sqlite` branch, use an explicitly configured Composer VCS repository or a local checkout, rather than assuming Packagist already contains the new API. Do not point production applications at a development branch without testing.

## Contents

- [Database access with Fluent](#database-access-with-fluent)
- [MySQL configuration](#mysql-configuration)
- [SQLite configuration](#sqlite-configuration)
- [Prepared queries and transactions](#prepared-queries-and-transactions)
- [Connection reuse and pooling](#connection-reuse-and-pooling)
- [API routing and responses](#api-routing-and-responses)
- [Other Hati modules](#other-hati-modules)
- [Upgrading from the original DBProfile](#upgrading-from-the-original-dbprofile)
- [Tests](#tests)

## Database access with Fluent

Hati's database module has four cooperating components:

| Component | Responsibility |
| --- | --- |
| `DBProfile` | Named database configuration, created with an engine-specific factory |
| `DBMan` | Register profiles, lazily create PDO connections, optionally manage pools |
| `Fluent` | Execute SQL with parameters, fetch rows, perform transactions |
| `DBPool` / `DBLease` | Optional bounded reuse of multiple PDO connections |

The application chooses the database engine and writes its own SQL. **Hati does not rewrite MySQL SQL into SQLite SQL or vice versa.**

Every profile is registered with the same manager and provides the same `Fluent` interface:

~~~php
use Hati\Fluent\DBMan;
use Hati\Fluent\DBProfile;

$dbMan = new DBMan();
$dbMan->register($profile);

$db = $dbMan->fluent($profile->id());
~~~

`DBMan` does not connect immediately when registering a profile or creating a lazy `Fluent`; the connection is opened on first use.

### MySQL configuration

~~~php
use Hati\Fluent\DBProfile;

$profile = DBProfile::mysql(
	id: 'mama',
	database: 'chutomama',
	user: $_ENV['DB_USER'],
	password: $_ENV['DB_PASS'],
	host: 'localhost',
	charset: 'utf8mb4',
	port: 3306,
	timezone: '+00:00'
);
~~~

The MySQL connector uses the same PDO connection attributes and session timezone behaviour as Hati's earlier MySQL-only implementation. Defaults: host `localhost`, charset `utf8mb4`, port `3306`, timezone `null`.

### SQLite configuration

~~~php
use Hati\Fluent\DBProfile;

$profile = DBProfile::sqlite(
	id: 'notes',
	path: '/var/lib/personal-apps/notes.sqlite',
	busyTimeoutMs: 5000
);
~~~

SQLite settings are applied to every new connection:

- `PRAGMA foreign_keys = ON`
- `PRAGMA busy_timeout = 5000` (configurable, 0–60000 milliseconds)

SQLite may create a new database file if the path does not exist. **Provision and back up your database deliberately**; Hati doesn't create tables. Use a private, writable directory outside any public web root.

To use an in-memory SQLite database:

~~~php
$profile = DBProfile::sqlite('test', ':memory:');
~~~

Each independent SQLite `:memory:` connection has its **own** database. The default single cached connection works for a temporary test database; a pool of distinct `:memory:` connections does not magically share its contents.

WAL mode is an application/database provisioning decision, **not** something Hati forces on every new connection. If appropriate, your installer can execute `PRAGMA journal_mode = WAL`.

### Prepared queries and transactions

The `Fluent` API is the same for both engines:

~~~php
use Hati\Fluent\DBMan;

$dbMan = new DBMan();
$dbMan->register($profile);

$db = $dbMan->fluent($profile->id());

$query = <<<SQL
	SELECT id, title, body_md
	FROM notes
	WHERE id = ?
	LIMIT 1
SQL;

$db->exePrepare($query, [$noteID]);
$note = $db->fetchFirst();
~~~

Convenience methods such as `insertPrepare()`, `updatePrepare()`, `deletePrepare()`, `query()`, `fetchAll()`, `read()`, `sqlCount()`, and `lastInsertRowId()` remain part of `Fluent`.

Managed transactions keep the entire callback on the same pinned PDO connection:

~~~php
$db->atomic(function ($tx) use ($title, $body): void {
	$query = <<<SQL
		INSERT INTO notes (title, body_md)
		VALUES (?, ?)
	SQL;

	$tx->exePrepare($query, [$title, $body]);
});
~~~

`atomic()` commits when the callback succeeds and rolls back if it throws. `withTransaction()` supports explicit `commit()`/`rollback()`; `withPDO()` provides controlled access to the underlying PDO connection.

SQL syntax is the developer's choice. For example, MySQL uses `INSERT IGNORE`, while SQLite uses `INSERT OR IGNORE`. Hati executes the SQL you supply without translating it.

### Connection reuse and pooling

**Connection reuse (default)**: `DBMan` caches one PDO instance per profile for its own lifetime. This suits conventional request-based PHP applications.

**Connection pooling (opt-in)**: `DBPool` borrows and returns several reusable PDO connections, up to a configured limit. This primarily benefits long-running processes.

~~~php
$profile = DBProfile::mysql('mama', 'chutomama', $user, $password);

$profile
	->enablePool()
	->setMaxConnection(5)
	->setAcquireTimeout(1000)
	->enableBorrowValidation();

$dbMan->register($profile);
~~~

A file-backed SQLite profile may also use the same pool:

~~~php
$profile = DBProfile::sqlite('notes', '/var/lib/personal-apps/notes.sqlite');
$profile->enablePool()->setMaxConnection(3);
~~~

Pooling does not increase SQLite's intrinsic write concurrency: SQLite still has a single writer at a time. A pool is an application-level connection lifecycle tool, not a database server.

The pool belongs to a `DBMan` instance. It is **not** a cross-process PHP-FPM connection pool. Avoid assuming ordinary PHP-FPM requests share an in-memory pool.

## API routing and responses

Hati provides `HatiAPI`, `HatiAPIHandler`, `Response`, and `Trunk`. The current router uses **class-based handlers**, registered on a `HatiAPIHandler` instance.

~~~php
<?php

use Hati\API\HatiAPI;
use Hati\API\HatiAPIHandler;
use Hati\API\Response;

require __DIR__ . '/vendor/autoload.php';

final class HelloAPI extends HatiAPI
{
	public function get(Response $res): void
	{
		$res->add('hello', 'world');
		$res->reply('Hello from Hati');
	}
}

$handler = new HatiAPIHandler();

$handler->register([
	'path' => 'hello',
	'method' => 'GET',
	'handler' => HelloAPI::class
]);

// Override the request here for a CLI/demo invocation.
// In a real HTTP entry point, the handler can use the native request.
$result = $handler->handle([
	'api' => 'hello',
	'method' => 'GET'
]);

http_response_code($result['code']);
echo $result['body'];
~~~

`Response::reply()` finalises a structured response by throwing a controlled `Trunk`, which `HatiAPIHandler` catches. Don't manually catch and swallow it in your API methods.

An actual web front controller should also emit headers and cookies from the handler's result array and implement authentication appropriate to the application.

## Other Hati modules

The library also includes:

- `Hati\Filter\DataFilter` for request validation.
- `Hati\CLI\HatiCLI` and `CLIInputBuilder` for command-line applications.
- `Hati\Util\Shomoy` and date/time helpers.
- `Hati\Util\Crypto`, `Text`, `Arr`, `File`, `Zip`, and other utilities.
- Email support using PHPMailer.

See the corresponding source classes in `src/` for the current public methods. Hati does not require an old `hati/hati.json`, `hati/db.json`, or the static Fluent APIs shown in older README revisions.

## Upgrading from the original DBProfile

The `sqlite` development branch changes **profile construction**, not the main `Fluent` query API.

**Before (Hati 7.0.66-beta):**

~~~php
$profile = new DBProfile($dbName, $dbName, $user, $password, $host, timezone: $timezone);
~~~

**After (this branch):**

~~~php
$profile = DBProfile::mysql(
	id: $dbName,
	database: $dbName,
	user: $user,
	password: $password,
	host: $host,
	timezone: $timezone
);
~~~

`DBMan::register()`, `DBMan::fluent()`, `Fluent` operations, and existing MySQL SQL remain the same. Review any direct calls to removed MySQL-only `DBProfile` getters.

For ChutoMama, its centralized `App::setupDB()` is expected to require just the profile initialization change. Upgrade and test ChutoMama **separately**; this Hati branch does not modify that repository.

## Tests

A standalone integration test runner is provided at `tests/fluent/run.php`:

~~~bash
php tests/fluent/run.php
~~~

The runner checks factory configuration, SQLite query helpers, foreign keys, rollback, transactions, and optional file-backed pooling.

- SQLite integration checks require `pdo_sqlite`; otherwise they print `SKIP`.
- MySQL integration checks are opt-in and should use a **disposable test database**. Set `HATI_TEST_MYSQL_DB`, `HATI_TEST_MYSQL_USER`, `HATI_TEST_MYSQL_PASSWORD`, and optionally `HATI_TEST_MYSQL_HOST`.
- Before publishing, run the tests with both drivers enabled and run dependent application suites (especially ChutoMama's MySQL regression tests).

**No GitHub Actions are used or configured.**

## License

Hati is licensed under the [MIT License](LICENSE.md).
