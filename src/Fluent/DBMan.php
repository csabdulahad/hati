<?php

namespace Hati\Fluent;

use Hati\Trunk;
use PDO;
use Throwable;

/**
 * Manages database profiles, connections, pools, and Fluent instances.
 *
 * DBMan is the central database manager for the new Hati Fluent architecture.
 * It does not represent a single database connection. Instead, it acts as a
 * registry and gateway for multiple {@link DBProfile} objects.
 *
 * Responsibilities:
 * <ul>
 *     <li>Register and unregister database profiles.</li>
 *     <li>Resolve profiles by their application-facing id.</li>
 *     <li>Create lazy {@link Fluent} instances for normal query usage.</li>
 *     <li>Create pinned {@link Fluent} instances for callback/transaction usage.</li>
 *     <li>Manage simple cached PDO connections when pooling is disabled.</li>
 *     <li>Manage {@link DBPool} instances when pooling is enabled.</li>
 *     <li>Create PDO connections from DBProfile configuration.</li>
 * </ul>
 *
 * Runtime behaviour depends on the profile:
 *
 * <code>
 * Pool disabled:
 *     DBMan creates/reuses one cached PDO connection for the profile.
 *
 * Pool enabled:
 *     DBMan creates a DBPool lazily and borrows/releases PDO connections
 *     through DBLease.
 * </code>
 *
 * DBMan itself does not read JSON configuration, environment files, or secret
 * stores. The application is expected to create DBProfile objects with already
 * resolved credentials and register them with DBMan.
 *
 * Example:
 * <code>
 * $dbMan = new DBMan();
 *
 * $dbMan->register(
 *     (new DBProfile(
 *         id: 'mama',
 *         dbName: 'mama_prod',
 *         user: $_ENV['DB_USER'],
 *         password: $_ENV['DB_PASS'],
 *     ))->enablePool()
 * );
 *
 * $student = $dbMan
 *     ->fluent('mama')
 *     ->query('SELECT * FROM student WHERE id = ?', [$id])
 *     ->fetchFirst();
 * </code>
 */
final class DBMan
{
	
	/**
	 * Registered database profiles indexed by profile id.
	 *
	 * @var array<string, DBProfile>
	 */
	private array $profiles = [];
	
	/**
	 * Lazily-created connection pools indexed by profile id.
	 *
	 * A pool is created only when a pooled profile is first used.
	 *
	 * @var array<string, DBPool>
	 */
	private array $pools = [];
	
	/**
	 * Cached PDO connections for profiles that do not use pooling.
	 *
	 * This is the simple connection-cache path intended for classic PHP
	 * runtimes such as mod_php and PHP-FPM.
	 *
	 * @var array<string, PDO>
	 */
	private array $connections = [];
	
	/**
	 * Registers a database profile.
	 *
	 * If another profile already exists with the same id, it will be replaced.
	 * This method does not create a PDO connection and does not create a pool.
	 * Connections remain lazy and are created only when the profile is used.
	 *
	 * @param DBProfile $profile The profile to register.
	 * @return void
	 */
	public function register(DBProfile $profile): void
	{
		$id = $profile->id();
		
		if (isset($this->pools[$id])) {
			$this->pools[$id]->close();
			unset($this->pools[$id]);
		}
		
		unset($this->connections[$id]);
		
		$this->profiles[$id] = $profile;	}
	
	/**
	 * Returns a registered database profile by id.
	 *
	 * @param string $id The application-facing profile id.
	 * @return DBProfile The registered database profile.
	 *
	 * @throws Trunk If no profile exists for the given id.
	 */
	public function getProfile(string $id): DBProfile
	{
		return $this->profiles[$id]?? throw new Trunk("Unknown DB profile: $id");
	}
	
	/**
	 * Unregisters a database profile by id.
	 *
	 * If the profile has an active pool, the pool is closed and removed. If the
	 * profile has a cached non-pooled PDO connection, that cached connection is
	 * removed as well.
	 *
	 * Busy pooled connections that are currently borrowed cannot be forcibly
	 * closed here. They will be handled when their leases are released.
	 *
	 * @param string $id The profile id to unregister.
	 * @return void
	 */
	public function unregisterByID(string $id): void
	{
		unset($this->profiles[$id]);
		
		if (isset($this->pools[$id])) {
			$this->pools[$id]->close();
			unset($this->pools[$id]);
		}
		
		unset($this->connections[$id]);
	}
	
	/**
	 * Unregisters a database profile object.
	 *
	 * This is a convenience wrapper around {@link unregisterByID()}.
	 *
	 * @param DBProfile $profile The profile to unregister.
	 * @return void
	 */
	public function unregister(DBProfile $profile): void
	{
		$this->unregisterByID($profile->id());
	}
	
	/**
	 * Creates a lazy Fluent instance for a registered profile.
	 *
	 * The returned Fluent instance is bound to the given profile id but does not
	 * immediately borrow or create a PDO connection. A connection is acquired only
	 * when the Fluent instance executes a query or calls {@link Fluent::withPDO()}.
	 *
	 * Lazy Fluent instances are suitable for normal one-off query operations:
	 *
	 * <code>
	 * $user = $dbMan
	 *     ->fluent('mama')
	 *     ->query('SELECT * FROM user WHERE id = ?', [$id])
	 *     ->fetchFirst();
	 * </code>
	 *
	 * @param string $id The profile id.
	 * @return Fluent A lazy Fluent instance.
	 *
	 * @throws Trunk If the profile id is unknown.
	 */
	public function fluent(string $id): Fluent
	{
		$this->getProfile($id);
		
		return Fluent::lazy($this, $id);
	}
	
	/**
	 * Runs a callback with a pinned Fluent instance.
	 *
	 * A pinned Fluent instance is backed by one concrete PDO connection for the
	 * full duration of the callback.
	 *
	 * When pooling is enabled, DBMan borrows one connection from the profile's
	 * pool, creates a pinned Fluent around that connection, runs the callback,
	 * and releases the connection in a finally block.
	 *
	 * When pooling is disabled, DBMan uses the cached PDO connection for the
	 * profile and creates a pinned Fluent around it.
	 *
	 * This method is useful when:
	 * <ul>
	 *     <li>Multiple operations should use the same connection.</li>
	 *     <li>Raw PDO access is needed through {@link Fluent::pdo()}.</li>
	 *     <li>Connection/session-specific behaviour is required.</li>
	 * </ul>
	 *
	 * @param string $id The profile id.
	 * @param callable $callback Function receiving a pinned Fluent instance.
	 * @return mixed The value returned by the callback.
	 *
	 * @throws Trunk If the profile id is unknown.
	 * @throws Trunk If pooled connection acquisition fails.
	 */
	public function withFluent(string $id, callable $callback): mixed
	{
		return $this->withConnection($id, function (PDO $pdo) use ($id, $callback) {
			return $callback(Fluent::pinned($this, $id, $pdo));
		});
	}
	
	/**
	 * Runs a callback with a PDO connection for the given profile.
	 *
	 * This is the low-level connection gateway used internally by Fluent.
	 *
	 * Behavior:
	 * <code>
	 * if profile pool is disabled:
	 *     create/reuse cached PDO connection
	 *     run callback
	 *
	 * if profile pool is enabled:
	 *     borrow PDO lease from DBPool
	 *     run callback
	 *     release lease in finally block
	 * </code>
	 *
	 * Normal Hati application code should usually use {@link fluent()} or
	 * {@link withFluent()} instead of this method. This method exists as a safe
	 * lower-level escape hatch for code that needs direct PDO access while still
	 * respecting DBMan's cache/pool rules.
	 *
	 * @param string $id The profile id.
	 * @param callable $callback Function receiving a PDO connection.
	 * @return mixed The value returned by the callback.
	 *
	 * @throws Trunk If the profile id is unknown.
	 * @throws Trunk If connection creation or pool acquisition fails.
	 */
	public function withConnection(string $id, callable $callback): mixed
	{
		$profile = $this->getProfile($id);
		
		if (!$profile->poolEnabled()) {
			$pdo = $this->connections[$id] ??= $this->createConnection($profile);
			return $callback($pdo);
		}
		
		$lease = $this->pool($profile)->borrow();
		
		try {
			return $callback($lease->pdo());
		} finally {
			$lease->release();
		}
	}
	
	/**
	 * Returns the pool for a profile, creating it lazily if needed.
	 *
	 * The pool is keyed by profile id. Creating the pool does not necessarily
	 * create PDO connections; DBPool creates connections lazily during borrow.
	 *
	 * @param DBProfile $profile The profile whose pool should be returned.
	 * @return DBPool The profile-scoped connection pool.
	 */
	private function pool(DBProfile $profile): DBPool
	{
		return $this->pools[$profile->id()] ??= new DBPool(
			profile: $profile,
			connectionFactory: fn () => $this->createConnection($profile),
		);
	}
	
	/**
	 * Creates a PDO connection from a database profile.
	 *
	 * The connection uses the profile's host, database name, port, charset,
	 * username, and password. PDO is configured to throw exceptions, disable
	 * emulated prepares, and return associative arrays by default.
	 *
	 * If the profile defines a timezone, DBMan applies it to the MySQL session
	 * after connection creation.
	 *
	 * @param DBProfile $profile The profile to connect with.
	 * @return PDO The created PDO connection.
	 *
	 * @throws Trunk If PDO connection creation fails.
	 */
	private function createConnection(DBProfile $profile): PDO
	{
		$dsn = sprintf(
			'mysql:host=%s;dbname=%s;port=%d;charset=%s',
			$profile->host(),
			$profile->dbName(),
			$profile->port(),
			$profile->charset()
		);
		
		try {
			$pdo = new PDO($dsn, $profile->user(), $profile->password(), [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]);
			
			if ($profile->timezone() !== null) {
				$stmt = $pdo->prepare('SET time_zone = ?');
				$stmt->execute([$profile->timezone()]);
			}
			
			return $pdo;
		} catch (Throwable $t) {
			throw new Trunk('Failed to connect to database profile ' . $profile->id() . ': ' . $t->getMessage());
		}
	}
	
	/**
	 * Returns connection pool statistics for a profile.
	 *
	 * If the profile uses pooling but its pool has not been created yet, opened,
	 * idle, and busy counts will all be zero. This preserves lazy pool creation
	 * while still allowing callers to inspect profile-level pool configuration.
	 *
	 * For non-pooled profiles, enabled will be false and counts will be zero.
	 *
	 * Returned keys:
	 * <code>
	 * [
	 *     'profile' => string,
	 *     'enabled' => bool,
	 *     'opened' => int,
	 *     'idle' => int,
	 *     'busy' => int,
	 *     'max' => int,
	 *     'acquire_timeout' => int,
	 *     'acquire_retry_delay' => int,
	 * ]
	 * </code>
	 *
	 * @param string $id The profile id.
	 * @return array{
	 *     profile: string,
	 *     enabled: bool,
	 *     opened: int,
	 *     idle: int,
	 *     busy: int,
	 *     max: int,
	 *     acquire_timeout: int,
	 *     acquire_retry_delay: int
	 * } Pool and profile statistics.
	 *
	 * @throws Trunk If the profile id is unknown.
	 */
	public function poolStats(string $id): array
	{
		$profile = $this->getProfile($id);
		$pool = $this->pools[$id] ?? null;
		
		return [
			'profile' => $id,
			'enabled' => $profile->poolEnabled(),
			'opened' => $pool?->openedCount() ?? 0,
			'idle' => $pool?->idleCount() ?? 0,
			'busy' => $pool?->busyCount() ?? 0,
			'max' => $profile->maxConnection(),
			'acquire_timeout' => $profile->acquireTimeout(),
			'acquire_retry_delay' => $profile->acquireRetryDelay(),
			'validate_on_borrow' => $profile->validateOnBorrow(),
		];
	}
	
}