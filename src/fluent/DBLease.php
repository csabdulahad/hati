<?php

namespace hati\fluent;

use PDO;

/**
 * Represents a borrowed database connection from a {@link DBPool}.
 *
 * DBLease is a small lifecycle guard around a PDO connection that has been
 * borrowed from a pool. The lease gives temporary access to the PDO instance
 * and is responsible for returning it to the pool exactly once.
 *
 * The main purpose of this class is to make pooled connection usage safe:
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
 * Calling {@link release()} multiple times is safe. Only the first call returns
 * the PDO connection to the pool; later calls are ignored.
 *
 * A DBLease should not be stored for long-term use. It should live only for the
 * duration of one database operation, callback, or transaction scope.
 */
final class DBLease
{
	
	/**
	 * Indicates whether this lease has already returned its PDO connection
	 * to the pool.
	 */
	private bool $released = false;
	
	/**
	 * Creates a new database connection lease.
	 *
	 * This constructor is expected to be called by {@link DBPool} when a
	 * connection is borrowed. Application code should normally not create
	 * DBLease objects directly.
	 *
	 * @param PDO $pdo The borrowed PDO connection.
	 * @param DBPool $pool The pool that owns the borrowed connection.
	 */
	public function __construct(
		private readonly PDO    $pdo,
		private readonly DBPool $pool,
	) {}
	
	/**
	 * Returns the borrowed PDO connection.
	 *
	 * The returned PDO belongs to the pool and must not be stored beyond the
	 * lease lifetime. Once {@link release()} is called, the PDO should be
	 * considered returned to the pool and no longer owned by the current caller.
	 *
	 * @return PDO The borrowed PDO connection.
	 */
	public function pdo(): PDO
	{
		return $this->pdo;
	}
	
	/**
	 * Returns the borrowed PDO connection to its pool.
	 *
	 * This method is idempotent. If it is called more than once, only the first
	 * call will release the connection. This protects callers from accidentally
	 * returning the same connection multiple times.
	 *
	 * The pool is responsible for deciding what happens during release, such as
	 * rolling back an open transaction, discarding a broken connection, or moving
	 * a healthy connection back to the idle list.
	 *
	 * @return void
	 */
	public function release(): void
	{
		if ($this->released) {
			return;
		}
		
		$this->released = true;
		$this->pool->release($this->pdo);
	}
	
}