<?php

namespace hati\fluent;

use hati\Trunk;
use PDO;
use Throwable;

/**
 * Manages a bounded pool of PDO connections for one DBProfile.
 *
 * DBPool is used when connection pooling is enabled on a {@link DBProfile}.
 * It owns a small set of reusable PDO connections for that profile and controls
 * how connections are borrowed, released, reused, validated, and closed.
 *
 * The pool is lazy. Creating a DBPool object does not immediately create PDO
 * connections. A connection is created only when {@link borrow()} is called and
 * the pool has no idle connection available.
 *
 * Borrowing rules:
 * <code>
 * if idle connection exists:
 *     optionally validate it
 *     return it if alive
 *     discard it if stale
 *
 * else if opened connections < maxConnection:
 *     create a new PDO connection and return it
 *
 * else:
 *     wait until acquire timeout is reached
 *     throw Trunk if no connection becomes available
 * </code>
 *
 * Released connections are returned to the idle list. If a connection is returned
 * while a transaction is still open, DBPool rolls it back before making the
 * connection idle again. This protects future borrowers from inheriting a dirty
 * transaction state.
 *
 * When borrow validation is enabled on the profile, idle connections are checked
 * before reuse with a lightweight database query. If validation fails, the idle
 * connection is discarded and the pool may create a replacement connection if
 * the max connection limit allows it.
 *
 * DBPool is intentionally profile-scoped. One DBPool manages connections for
 * one DBProfile only.
 */
final class DBPool
{
	
	/**
	 * Idle PDO connections available for borrowing.
	 *
	 * @var PDO[]
	 */
	private array $idle = [];
	
	/**
	 * Total number of currently opened PDO connections owned by this pool.
	 *
	 * This includes both idle and busy connections. Busy connections are not kept
	 * in a separate list; they are represented by DBLease objects currently held
	 * by callers.
	 */
	private int $opened = 0;
	
	/**
	 * Creates a new connection pool for a database profile.
	 *
	 * The connection factory is called whenever the pool needs to create a new
	 * PDO connection. This keeps DBPool focused only on pooling rules while DBMan
	 * remains responsible for the actual connection creation logic.
	 *
	 * @param DBProfile $profile The database profile this pool belongs to.
	 * @param callable $connectionFactory Callable that creates and returns a PDO connection.
	 */
	public function __construct(
		private readonly DBProfile $profile,
		private $connectionFactory,
	) {}
	
	/**
	 * Borrows a PDO connection from the pool.
	 *
	 * If an idle connection is available, it is returned immediately after
	 * optional validation. If no idle connection exists and the pool has not
	 * reached the profile's maximum connection limit, a new PDO connection is
	 * created through the connection factory.
	 *
	 * If the pool is already at maximum capacity, this method waits and retries
	 * until either a connection is released or the profile's acquire timeout is
	 * reached.
	 *
	 * The returned PDO is wrapped in a {@link DBLease}. The caller must release
	 * the lease when the operation is finished, ideally in a finally block:
	 *
	 * <code>
	 * $lease = $pool->borrow();
	 *
	 * try {
	 *     $pdo = $lease->pdo();
	 *     // use PDO
	 * } finally {
	 *     $lease->release();
	 * }
	 * </code>
	 *
	 * @return DBLease Lease containing the borrowed PDO connection.
	 *
	 * @throws Trunk If a new PDO connection cannot be created.
	 * @throws Trunk If the pool is exhausted and acquire timeout is reached.
	 */
	public function borrow(): DBLease
	{
		$started = microtime(true);
		
		while (true) {
			$pdo = $this->borrowIdleConnection();
			
			if ($pdo !== null) {
				return new DBLease($pdo, $this);
			}
			
			if ($this->opened < $this->profile->maxConnection()) {
				$this->opened++;
				
				try {
					$pdo = ($this->connectionFactory)();
					
					return new DBLease($pdo, $this);
				} catch (Throwable $t) {
					$this->opened--;
					
					throw new Trunk(
						'Failed to create database connection for profile ' .
						$this->profile->id() . ': ' . $t->getMessage()
					);
				}
			}
			
			$elapsedMs = (int) ((microtime(true) - $started) * 1000);
			
			if ($elapsedMs >= $this->profile->acquireTimeout()) {
				throw new Trunk(
					'Database connection pool exhausted for profile ' .
					$this->profile->id() .
					' after waiting ' .
					$this->profile->acquireTimeout() .
					'ms.'
				);
			}
			
			$remainingMs = $this->profile->acquireTimeout() - $elapsedMs;
			$sleepMs = min($this->profile->acquireRetryDelay(), max(1, $remainingMs));
			
			usleep($sleepMs * 1000);
		}
	}
	
	/**
	 * Attempts to borrow an idle connection from the pool.
	 *
	 * This method checks the idle stack first. If borrow validation is disabled
	 * on the profile, the first idle PDO is returned immediately.
	 *
	 * If borrow validation is enabled, each idle PDO is tested with
	 * {@link isConnectionAlive()} before being returned. Stale or broken idle
	 * connections are discarded by reducing the opened connection count, allowing
	 * the pool to create a replacement connection later if the max connection
	 * limit permits it.
	 *
	 * This method may inspect and discard multiple idle connections before either
	 * returning a healthy PDO or returning null.
	 *
	 * @return ?PDO A healthy idle PDO connection, or null if no reusable idle connection exists.
	 */
	private function borrowIdleConnection(): ?PDO
	{
		while (!empty($this->idle)) {
			$pdo = array_pop($this->idle);
			
			if (!$this->profile->validateOnBorrow()) {
				return $pdo;
			}
			
			if ($this->isConnectionAlive($pdo)) {
				return $pdo;
			}
			
			$this->opened--;
		}
		
		return null;
	}
	
	/**
	 * Checks whether a PDO connection is still usable.
	 *
	 * This is a lightweight health check used before reusing an idle pooled
	 * connection. It runs a simple <code>SELECT 1</code> query. If the query
	 * succeeds, the connection is considered alive. If PDO throws, the connection
	 * is considered stale or broken and should be discarded.
	 *
	 * This method intentionally catches all Throwables because a failed health
	 * check should not break the pool itself. The caller decides how to discard
	 * and replace the failed connection.
	 *
	 * @param PDO $pdo The PDO connection to validate.
	 * @return bool True if the connection is alive; false if it appears broken.
	 */
	private function isConnectionAlive(PDO $pdo): bool
	{
		try {
			$pdo->query('SELECT 1');
			return true;
		} catch (Throwable) {
			return false;
		}
	}
	
	/**
	 * Returns a borrowed PDO connection back to the pool.
	 *
	 * If the connection still has an open transaction, the transaction is rolled
	 * back before the connection is returned to the idle list. This is a safety
	 * cleanup to prevent leaked transaction state from affecting future borrowers.
	 *
	 * If rollback or release cleanup fails, the connection is considered unusable
	 * and is discarded by reducing the opened connection count. A future borrow
	 * may then create a replacement connection if the pool limit allows it.
	 *
	 * @param PDO $pdo The PDO connection being returned to the pool.
	 * @return void
	 */
	public function release(PDO $pdo): void
	{
		try {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			
			$this->idle[] = $pdo;
		} catch (Throwable) {
			$this->opened--;
		}
	}
	
	/**
	 * Closes the pool by dropping all idle connection references.
	 *
	 * This does not directly close busy connections currently held by active
	 * leases. Those connections will naturally be handled when their leases are
	 * released. This method is mainly intended for DBMan cleanup when a profile
	 * is unregistered or when the runtime is shutting down.
	 *
	 * @return void
	 */
	public function close(): void
	{
		$idleCount = count($this->idle);
		
		$this->idle = [];
		$this->opened = max(0, $this->opened - $idleCount);
	}
	
	/**
	 * Returns the number of currently opened connections owned by the pool.
	 *
	 * This includes both idle and busy connections.
	 *
	 * @return int Opened connection count.
	 */
	public function openedCount(): int
	{
		return $this->opened;
	}
	
	/**
	 * Returns the number of idle connections currently available for borrowing.
	 *
	 * @return int Idle connection count.
	 */
	public function idleCount(): int
	{
		return count($this->idle);
	}
	
	/**
	 * Returns the number of connections currently borrowed by callers.
	 *
	 * Busy count is calculated as opened connections minus idle connections.
	 *
	 * @return int Busy connection count.
	 */
	public function busyCount(): int
	{
		return $this->opened - $this->idleCount();
	}
	
	/**
	 * Returns the maximum connection limit configured for this pool.
	 *
	 * This value comes from the attached DBProfile.
	 *
	 * @return int Maximum allowed opened connections.
	 */
	public function maxConnection(): int
	{
		return $this->profile->maxConnection();
	}
	
}