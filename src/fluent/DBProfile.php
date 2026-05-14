<?php

namespace Hati\Fluent;

use Hati\Trunk;

/**
 * Describes one database connection profile for Hati Fluent.
 *
 * A DBProfile represents one actual database target, identified by a stable
 * application-facing id. The id is the name client code uses to request a
 * Fluent database instance, while dbName is the real database name used in the
 * PDO DSN.
 *
 * Example:
 * <code>
 * $profile = (new DBProfile(
 *     id: 'mama',
 *     dbName: 'mama_prod',
 *     user: $_ENV['DB_USER'],
 *     password: $_ENV['DB_PASS'],
 *     host: 'localhost',
 * ))
 *     ->enablePool()
 *     ->setMaxConnection(5)
 *     ->setAcquireTimeout(1000)
 *     ->enableBorrowValidation();
 * </code>
 *
 * DBProfile only stores configuration. It does not create PDO connections and
 * does not own a connection pool. Connection creation, pooling, borrowing, and
 * releasing are handled by {@link DBMan} and {@link DBPool}.
 *
 * Pooling is optional per profile. In classic PHP runtimes such as mod_php or
 * PHP-FPM, a profile may keep pooling disabled and let DBMan use simple
 * request-local connection caching. In long-running runtimes such as
 * OpenSwoole, pooling can be enabled so DBPool can enforce max connection
 * limits, acquire timeouts, and optional idle-connection validation.
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
	 * Creates a new database profile.
	 *
	 * The id is the application-facing alias used by Hati code. The dbName is
	 * the actual database name used by the database server.
	 *
	 * Credentials are intentionally passed directly through the constructor so
	 * the application can decide how secrets are loaded, such as from environment
	 * variables, systemd environment files, Docker secrets, or any other secret
	 * source. DBProfile does not read JSON configuration or environment files by
	 * itself.
	 *
	 * @param string $id Stable application-facing profile id.
	 * @param string $dbName Actual database name.
	 * @param string $user Database username.
	 * @param string $password Database password.
	 * @param string $host Database host. Defaults to localhost.
	 * @param string $charset Database charset. Defaults to utf8mb4.
	 * @param int $port Database port. Defaults to 3306.
	 * @param ?string $timezone Optional MySQL session timezone, such as '+00:00'.
	 *
	 * @throws Trunk If the profile configuration is invalid.
	 */
	public function __construct(
		private readonly string  $id,
		private readonly string  $dbName,
		private readonly string  $user,
		private readonly string  $password,
		private readonly string  $host = 'localhost',
		private readonly string  $charset = 'utf8mb4',
		private readonly int     $port = 3306,
		private readonly ?string $timezone = null,
	)
	{
		$this->validate();
	}
	
	/**
	 * Validates the basic profile configuration.
	 *
	 * This validation only checks the shape of the profile values. It does not
	 * attempt to connect to the database. Connection errors are handled later by
	 * DBMan/DBPool when the profile is actually used.
	 *
	 * @throws Trunk If any required profile value is invalid.
	 */
	private function validate(): void
	{
		if ($this->id === '') {
			throw new Trunk('DB profile id cannot be empty.');
		}
		
		if ($this->dbName === '') {
			throw new Trunk('Database name cannot be empty.');
		}
		
		if ($this->user === '') {
			throw new Trunk('Database username cannot be empty.');
		}
		
		if ($this->host === '') {
			throw new Trunk('Database host cannot be empty.');
		}
		
		if ($this->charset === '') {
			throw new Trunk('Database charset cannot be empty.');
		}
		
		if ($this->port < 1 || $this->port > 65535) {
			throw new Trunk('Database port is invalid.');
		}
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
	 * Returns the application-facing profile id.
	 *
	 * This is the alias used by client code to request this database profile,
	 * for example: <code>Hati::db('mama')</code>.
	 *
	 * @return string The profile id.
	 */
	public function id(): string
	{
		return $this->id;
	}
	
	/**
	 * Returns the actual database name.
	 *
	 * This value is used in the PDO DSN as the database/schema name.
	 *
	 * @return string The database name.
	 */
	public function dbName(): string
	{
		return $this->dbName;
	}
	
	/**
	 * Returns the database username.
	 *
	 * @return string The database username.
	 */
	public function user(): string
	{
		return $this->user;
	}
	
	/**
	 * Returns the database password.
	 *
	 * @return string The database password.
	 */
	public function password(): string
	{
		return $this->password;
	}
	
	/**
	 * Returns the database host.
	 *
	 * @return string The database host.
	 */
	public function host(): string
	{
		return $this->host;
	}
	
	/**
	 * Returns the database charset.
	 *
	 * @return string The database charset.
	 */
	public function charset(): string
	{
		return $this->charset;
	}
	
	/**
	 * Returns the database port.
	 *
	 * @return int The database port.
	 */
	public function port(): int
	{
		return $this->port;
	}
	
	/**
	 * Returns the optional MySQL session timezone.
	 *
	 * When this value is not null, DBMan may apply it after creating the PDO
	 * connection using a statement such as <code>SET time_zone = ?</code>.
	 *
	 * @return ?string The configured timezone, or null when not configured.
	 */
	public function timezone(): ?string
	{
		return $this->timezone;
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