<?php

namespace Hati\Fluent;

use Hati\Trunk;

/**
 * One named database profile with one engine-specific, immutable configuration.
 *
 * Use DBProfile::mysql() or DBProfile::sqlite() to select the PDO driver.
 * DBMan creates connections lazily and manages reuse or optional pooling.
 * Fluent exposes the same query interface for both database engines.
 *
 * Pooling is opt-in. The default is one cached PDO connection per profile
 * for the lifetime of DBMan, generally one PHP-FPM request.
 */
final class DBProfile
{
	
	/**
	 * Indicates whether this profile should use DBPool instead of simple
	 * DBMan-level connection caching.
	 */
	private bool $poolEnabled = false;
	
	/**
	 * Minimum number of connections intended for this profile's pool.
	 *
	 * The first implementation may keep pools lazy and not pre-warm this amount.
	 * This value still belongs to the profile because future pool warm-up logic
	 * can use it without changing the public API.
	 */
	private int $minConnection = 0;
	
	/**
	 * Maximum number of open connections allowed for this profile's pool.
	 */
	private int $maxConnection = 5;
	
	/**
	 * Maximum time, in milliseconds, DBPool should wait while trying to acquire
	 * a connection when the pool is already at max capacity.
	 */
	private int $acquireTimeout = 1000;
	
	/**
	 * Delay, in milliseconds, between acquire retries while DBPool is waiting
	 * for a busy connection to be released.
	 */
	private int $acquireRetryDelay = 10;
	
	/**
	 * Indicates whether DBPool should validate idle connections before handing
	 * them out again.
	 *
	 * When enabled, DBPool performs a lightweight health check, such as
	 * <code>SELECT 1</code>, before reusing an idle PDO connection. If the check
	 * fails, the connection is discarded and the pool may create a replacement
	 * connection if the max connection limit allows it.
	 *
	 * This is especially useful in long-running runtimes where MySQL may close
	 * idle connections while the PHP worker remains alive.
	 */
	private bool $validateOnBorrow = true;
	
	/**
	 * Profiles are created using the mysql() and sqlite() factories.
	 * Driver-specific settings cannot be mixed across database engines.
	 */
	private function __construct(
		private readonly string $id,
		private readonly MySQLConfig|SQLiteConfig $config
	)
	{
		if ($id === '') {
			throw new Trunk('DB profile id cannot be empty.');
		}
	}
	
	/**
	 * Creates a MySQL connection profile.
	 *
	 * This method uses the same defaults as the former MySQL-only constructor.
	 */
	public static function mysql(
		string $id,
		string $database,
		string $user,
		string $password,
		string $host = 'localhost',
		string $charset = 'utf8mb4',
		int $port = 3306,
		?string $timezone = null
	): self
	{
		$config = new MySQLConfig($database, $user, $password, $host, $charset, $port, $timezone);
		return new self($id, $config);
	}
	
	/**
	 * Creates an SQLite connection profile for a file or ':memory:' database.
	 *
	 * SQLite database creation and SQL dialect choices belong to the application.
	 * The busy timeout controls how long SQLite waits for database locks.
	 */
	public static function sqlite(string $id, string $path, int $busyTimeoutMs = 5000): self
	{
		$config = new SQLiteConfig($path, $busyTimeoutMs);
		return new self($id, $config);
	}
	
	/**
	 * Enables or disables validation of idle pooled connections before reuse.
	 *
	 * Borrow validation helps protect long-running runtimes from stale MySQL
	 * connections. When enabled, DBPool checks an idle connection before handing
	 * it out. If the connection is no longer alive, DBPool discards it and tries
	 * to acquire another connection.
	 *
	 * This setting only affects pooled profiles. Non-pooled profiles use DBMan's
	 * simple cached connection path and do not borrow from DBPool.
	 *
	 * @param bool $enabled Whether idle connections should be validated before reuse.
	 * @return self This profile instance for fluent configuration.
	 */
	public function enableBorrowValidation(bool $enabled = true): self
	{
		$this->validateOnBorrow = $enabled;
		return $this;
	}
	
	/**
	 * Returns whether idle pooled connections should be validated before reuse.
	 *
	 * @return bool True when borrow validation is enabled.
	 */
	public function validateOnBorrow(): bool
	{
		return $this->validateOnBorrow;
	}
	
	/**
	 * Returns the application-facing identifier used by DBMan.
	 */
	public function id(): string
	{
		return $this->id;
	}
	
	/**
	 * Returns the database-specific configuration.
	 */
	public function config(): MySQLConfig|SQLiteConfig
	{
		return $this->config;
	}
	
	/**
	 * Enables or disables connection pooling for this profile.
	 *
	 * When pooling is disabled, DBMan may use a simple cached PDO connection for
	 * the profile. When pooling is enabled, DBMan delegates connection acquisition
	 * to DBPool.
	 *
	 * @param bool $enabled Whether pooling should be enabled.
	 * @return self This profile instance for fluent configuration.
	 */
	public function enablePool(bool $enabled = true): self
	{
		$this->poolEnabled = $enabled;
		return $this;
	}
	
	/**
	 * Returns whether connection pooling is enabled for this profile.
	 *
	 * @return bool True when pooling is enabled.
	 */
	public function poolEnabled(): bool
	{
		return $this->poolEnabled;
	}
	
	/**
	 * Sets the minimum connection count for this profile's pool.
	 *
	 * The minimum cannot be negative and cannot be greater than the configured
	 * maximum connection count.
	 *
	 * @param int $total Minimum connection count.
	 * @return self This profile instance for fluent configuration.
	 *
	 * @throws Trunk If the minimum connection count is invalid.
	 */
	public function setMinConnection(int $total): self
	{
		if ($total < 0) {
			throw new Trunk('Minimum DB connection cannot be negative.');
		}
		
		if ($total > $this->maxConnection) {
			throw new Trunk('Minimum DB connection cannot be greater than maximum DB connection.');
		}
		
		$this->minConnection = $total;
		
		return $this;
	}
	
	/**
	 * Sets the maximum connection count for this profile's pool.
	 *
	 * The maximum must be at least 1 and cannot be lower than the configured
	 * minimum connection count.
	 *
	 * @param int $total Maximum connection count.
	 * @return self This profile instance for fluent configuration.
	 *
	 * @throws Trunk If the maximum connection count is invalid.
	 */
	public function setMaxConnection(int $total): self
	{
		if ($total < 1) {
			throw new Trunk('Maximum DB connection must be at least 1.');
		}
		
		if ($total < $this->minConnection) {
			throw new Trunk('Maximum DB connection cannot be lower than minimum DB connection.');
		}
		
		$this->maxConnection = $total;
		
		return $this;
	}
	
	/**
	 * Returns the minimum connection count configured for this profile.
	 *
	 * @return int Minimum connection count.
	 */
	public function minConnection(): int
	{
		return $this->minConnection;
	}
	
	/**
	 * Returns the maximum connection count configured for this profile.
	 *
	 * @return int Maximum connection count.
	 */
	public function maxConnection(): int
	{
		return $this->maxConnection;
	}
	
	/**
	 * Sets the pool acquire timeout in milliseconds.
	 *
	 * This controls how long DBPool should wait for a busy connection to become
	 * available when the pool is already at maximum capacity.
	 *
	 * A value of 0 means DBPool should not wait and should fail immediately when
	 * no connection can be acquired.
	 *
	 * @param int $milliseconds Timeout in milliseconds.
	 * @return self This profile instance for fluent configuration.
	 *
	 * @throws Trunk If the timeout is negative.
	 */
	public function setAcquireTimeout(int $milliseconds): self
	{
		if ($milliseconds < 0) {
			throw new Trunk('DB pool acquire timeout cannot be negative.');
		}
		
		$this->acquireTimeout = $milliseconds;
		
		return $this;
	}
	
	/**
	 * Returns the pool acquire timeout in milliseconds.
	 *
	 * @return int Acquire timeout in milliseconds.
	 */
	public function acquireTimeout(): int
	{
		return $this->acquireTimeout;
	}
	
	/**
	 * Sets the delay between pool acquire retries in milliseconds.
	 *
	 * When the pool is full, DBPool waits this long between checks until either
	 * a connection becomes available or the acquire timeout is reached.
	 *
	 * @param int $milliseconds Retry delay in milliseconds.
	 * @return self This profile instance for fluent configuration.
	 *
	 * @throws Trunk If the retry delay is less than 1 millisecond.
	 */
	public function setAcquireRetryDelay(int $milliseconds): self
	{
		if ($milliseconds < 1) {
			throw new Trunk('DB pool acquire retry delay must be at least 1 millisecond.');
		}
		
		$this->acquireRetryDelay = $milliseconds;
		
		return $this;
	}
	
	/**
	 * Returns the delay between pool acquire retries in milliseconds.
	 *
	 * @return int Acquire retry delay in milliseconds.
	 */
	public function acquireRetryDelay(): int
	{
		return $this->acquireRetryDelay;
	}
	
}