<?php

namespace hati\fluent;

use hati\Trunk;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Fluent is the developer-facing database query API for Hati.
 *
 * A Fluent instance is bound to one DB profile id managed by DBMan. It is either
 * lazy or pinned:
 *
 * - Lazy Fluent does not directly own a PDO connection. It borrows a connection
 *   from DBMan only while a query/callback is running, stores the result locally,
 *   and then releases the connection.
 * - Pinned Fluent wraps an already-borrowed PDO connection. It is used inside
 *   DBMan::withFluent(), Fluent::withTransaction(), and Fluent::atomic(), where
 *   several operations must use the same physical connection.
 *
 * Public query methods intentionally preserve the old Hati Fluent method names
 * such as exePrepare(), insertPrepare(), fetchFirst(), read(), rowCount(), and
 * lastInsertRowId(), but they are now instance methods instead of static/global
 * state.
 */
final class Fluent
{
	
	private ?PDO $pinnedPDO;
	
	private ?PDOStatement $stmtBuffer = null;
	
	private ?array $data = null;
	
	private bool $executed = false;
	
	private int $affectedRows = 0;
	
	private ?string $lastInsertId = null;
	
	private bool $debugSql = false;
	
	private array $errorInfo = [
		'code' => 0,
		'message' => '',
	];
	
	/** @var ?callable */
	private $errorHandler = null;
	
	private function __construct(
		private readonly DBMan $dbMan,
		private string         $profileId,
		?PDO                   $pdo = null,
	) {
		$this->pinnedPDO = $pdo;
	}
	
	/**
	 * Creates a lazy Fluent instance for a registered DB profile.
	 *
	 * The returned object stores the profile id and DBMan reference, but it does not
	 * open or hold a database connection until a query/callback is executed.
	 *
	 * @param DBMan $dbMan The database manager that owns profiles, caches, and pools.
	 * @param string $profileId The registered DB profile id/alias.
	 * @return self A lazy Fluent instance bound to the given profile id.
	 */
	public static function lazy(DBMan $dbMan, string $profileId): self
	{
		return new self($dbMan, $profileId);
	}
	
	/**
	 * Creates a pinned Fluent instance around an already-borrowed PDO connection.
	 *
	 * Pinned instances are used internally by DBMan::withFluent(), withTransaction(),
	 * and atomic(). They allow pdo(), beginTrans(), commit(), and rollback() to operate
	 * directly on the same physical connection.
	 *
	 * @param DBMan $dbMan The database manager that owns the profile.
	 * @param string $profileId The registered DB profile id/alias.
	 * @param PDO $pdo The PDO connection pinned to this Fluent instance.
	 * @return self A pinned Fluent instance.
	 */
	public static function pinned(DBMan $dbMan, string $profileId, PDO $pdo): self
	{
		return new self($dbMan, $profileId, $pdo);
	}
	
	/**
	 * Returns the DB profile id/alias this Fluent instance is bound to.
	 *
	 * @return string The registered DB profile id.
	 */
	public function profileId(): string
	{
		return $this->profileId;
	}
	
	/**
	 * Enables or disables SQL debug output for this Fluent instance.
	 *
	 * When enabled, query methods call vd() with the SQL string and parameters before
	 * execution. This is intended for local debugging only.
	 *
	 * @param bool $enabled Whether SQL debug output should be enabled.
	 * @return self This Fluent instance for chaining.
	 */
	public function debugSQL(bool $enabled = true): self
	{
		$this->debugSql = $enabled;
		return $this;
	}
	
	/**
	 * Registers an error handler for query failures on this Fluent instance.
	 *
	 * The handler is called before Fluent throws Trunk. Its signature is:
	 *
	 * <code>
	 * function (string $query, int|string $code, string $message, string $profileId): void
	 * </code>
	 *
	 * @param callable $handler Handler invoked when a query fails.
	 * @return self This Fluent instance for chaining.
	 */
	public function setErrorHandler(callable $handler): self
	{
		$this->errorHandler = $handler;
		return $this;
	}
	
	/**
	 * Returns whether the most recent query attempt on this instance recorded an error.
	 *
	 * Query failures throw Trunk, so this is mainly useful after catching an exception
	 * while keeping the same Fluent object around.
	 *
	 * @return bool True when the last query failure stored an error message.
	 */
	public function hasError(): bool
	{
		return $this->errorInfo['message'] !== '';
	}
	
	/**
	 * Returns the last recorded query error message for this instance.
	 *
	 * @return string The last error message, or an empty string when none exists.
	 */
	public function getLastErrMsg(): string
	{
		return $this->errorInfo['message'] ?? '';
	}
	
	/**
	 * Returns the last recorded query error code for this instance.
	 *
	 * @return int The last error code, or 0 when none exists.
	 */
	public function getLastErrCode(): int
	{
		return (int) ($this->errorInfo['code'] ?? 0);
	}
	
	/**
	 * Executes a SQL query and returns this Fluent instance for chaining.
	 *
	 * If $params is empty, the query is executed as raw SQL through exec(). If params
	 * are provided, it is executed as a prepared statement through exePrepare().
	 *
	 * @param string $query SQL query to execute.
	 * @param array $params Positional parameters for a prepared statement.
	 * @return self This Fluent instance with its result state updated.
	 */
	public function query(string $query, array $params = []): self
	{
		if (empty($params)) {
			$this->exec($query);
		} else {
			$this->exePrepare($query, $params);
		}
		
		return $this;
	}
	
	/**
	 * Executes a raw SQL query without parameter binding.
	 *
	 * Use this only for static SQL or SQL that has already been safely constructed.
	 * For external values, use exePrepare() or query() with parameters.
	 *
	 * @param string $query Raw SQL query to execute.
	 * @return int Number of rows affected, as reported by PDOStatement::rowCount().
	 */
	public function exec(string $query): int
	{
		return $this->execute($query, null);
	}
	
	/**
	 * Executes a prepared SQL statement with positional parameters.
	 *
	 * This is the preserved old Hati method name. It prepares the query, executes it
	 * with the provided values, stores result rows locally when the statement returns
	 * columns, and stores affected row count and last insert id.
	 *
	 * @param string $query SQL statement containing positional placeholders.
	 * @param array $params Values to bind to the positional placeholders.
	 * @return int Number of rows affected, as reported by PDOStatement::rowCount().
	 */
	public function exePrepare(string $query, array $params = []): int
	{
		return $this->execute($query, $params);
	}
	
	/**
	 * Alias for exePrepare().
	 *
	 * @param string $query SQL statement containing positional placeholders.
	 * @param array $params Values to bind to the positional placeholders.
	 * @return int Number of rows affected, as reported by PDOStatement::rowCount().
	 */
	public function execPrepare(string $query, array $params = []): int
	{
		return $this->exePrepare($query, $params);
	}
	
	private function execute(string $query, ?array $params): int
	{
		$this->resetQueryState();
		
		if ($query === '') {
			throw new Trunk('SQL query cannot be empty.');
		}
		
		if ($params !== null && $this->isListArray($params)) {
			$this->assertPlaceholderCount($query, count($params));
		}
		
		if ($this->debugSql) {
			vd($params === null ? [$query] : [$query, $params]);
		}
		
		try {
			$this->withPDO(function (PDO $pdo) use ($query, $params) {
				if ($params === null) {
					$stmt = $pdo->query($query);
				} else {
					$stmt = $pdo->prepare($query);
					$stmt->execute($params);
				}
				
				$this->stmtBuffer = $stmt;
				$this->executed = true;
				$this->affectedRows = $stmt->rowCount();
				$this->lastInsertId = $pdo->lastInsertId();
				
				/**
				 * Do not guess SELECT by parsing SQL.
				 * Let PDO tell us whether the statement has result columns.
				 */
				if ($stmt->columnCount() > 0) {
					$this->data = $stmt->fetchAll(PDO::FETCH_ASSOC);
				}
			});
			
			return $this->affectedRows;
		} catch (Throwable $t) {
			$this->handleError($t, $query);
		}
	}
	
	/**
	 * Inserts a row using raw SQL literals.
	 *
	 * The table and column identifiers are validated and quoted. Values are converted
	 * to SQL literals using PDO::quote() where appropriate. Prefer insertPrepare()
	 * for values coming from users or external input.
	 *
	 * @param string $table Table name. Supports dotted identifiers such as schema.table.
	 * @param array $columns Column list, associative column-value map, or a mix of both.
	 * @param array $values Values for numeric column entries in $columns.
	 * @return int Number of inserted rows affected.
	 */
	public function insert(string $table, array $columns, array $values = []): int
	{
		return $this->insertData($table, $columns, $values, false);
	}
	
	/**
	 * Inserts a row using a prepared INSERT statement.
	 *
	 * The table and column identifiers are validated and quoted. Every value is sent as
	 * a bound placeholder, including associative column-value entries.
	 *
	 * Supported forms:
	 *
	 * <code>
	 * $db->insertPrepare('user', ['name', 'email'], ['Ahad', 'a@example.com']);
	 * $db->insertPrepare('user', ['name' => 'Ahad', 'email' => 'a@example.com']);
	 * $db->insertPrepare('user', ['name' => 'Ahad', 'email'], ['a@example.com']);
	 * </code>
	 *
	 * @param string $table Table name. Supports dotted identifiers such as schema.table.
	 * @param array $columns Column list, associative column-value map, or a mix of both.
	 * @param array $values Values for numeric column entries in $columns.
	 * @return int Number of inserted rows affected.
	 */
	public function insertPrepare(string $table, array $columns, array $values = []): int
	{
		return $this->insertData($table, $columns, $values, true);
	}
	
	/**
	 * Updates rows using raw SQL literals.
	 *
	 * The table and SET column identifiers are validated and quoted. Values are converted
	 * to SQL literals. $where is appended as provided; placeholders in $where are replaced
	 * with SQL literals from $whereValues. Prefer updatePrepare() for external values.
	 *
	 * @param string $table Table name. Supports dotted identifiers such as schema.table.
	 * @param array $columns SET columns as a list, associative map, or mixed form.
	 * @param array $values Values for numeric column entries in $columns.
	 * @param string $where Optional WHERE clause without the WHERE keyword.
	 * @param array $whereValues Values for ? placeholders in $where.
	 * @return int Number of rows affected.
	 */
	public function update(
		string $table,
		array $columns,
		array $values = [],
		string $where = '',
		array $whereValues = [],
	): int {
		return $this->updateData($table, $columns, $values, false, $where, $whereValues);
	}
	
	/**
	 * Updates rows using a prepared UPDATE statement.
	 *
	 * The table and SET column identifiers are validated and quoted. SET values and
	 * WHERE values are bound as positional parameters. The WHERE clause is appended as
	 * provided and must contain exactly the same number of ? placeholders as $whereValues.
	 *
	 * @param string $table Table name. Supports dotted identifiers such as schema.table.
	 * @param array $columns SET columns as a list, associative map, or mixed form.
	 * @param array $values Values for numeric column entries in $columns.
	 * @param string $where Optional WHERE clause without the WHERE keyword.
	 * @param array $whereValues Values for ? placeholders in $where.
	 * @return int Number of rows affected.
	 */
	public function updatePrepare(
		string $table,
		array $columns,
		array $values = [],
		string $where = '',
		array $whereValues = [],
	): int {
		return $this->updateData($table, $columns, $values, true, $where, $whereValues);
	}
	
	/**
	 * Deletes rows using a raw DELETE statement.
	 *
	 * The table identifier is validated and quoted. The WHERE clause is appended as
	 * provided; placeholders in $where are replaced with SQL literals from $whereValues.
	 * Prefer deletePrepare() for external values.
	 *
	 * @param string $table Table name. Supports dotted identifiers such as schema.table.
	 * @param string $where Optional WHERE clause without the WHERE keyword.
	 * @param array $whereValues Values for ? placeholders in $where.
	 * @return int Number of rows affected.
	 */
	public function delete(string $table, string $where = '', array $whereValues = []): int
	{
		return $this->deleteData($table, $where, $whereValues, false);
	}
	
	/**
	 * Deletes rows using a prepared DELETE statement.
	 *
	 * The table identifier is validated and quoted. The WHERE clause is appended as
	 * provided and must contain exactly the same number of ? placeholders as $whereValues.
	 *
	 * @param string $table Table name. Supports dotted identifiers such as schema.table.
	 * @param string $where Optional WHERE clause without the WHERE keyword.
	 * @param array $whereValues Values for ? placeholders in $where.
	 * @return int Number of rows affected.
	 */
	public function deletePrepare(string $table, string $where = '', array $whereValues = []): int
	{
		return $this->deleteData($table, $where, $whereValues, true);
	}
	
	/**
	 * Returns the PDOStatement from the most recent successful query execution.
	 *
	 * Result rows are already fetched into this Fluent instance when the statement has
	 * columns, so this should mainly be used for advanced inspection.
	 *
	 * @return PDOStatement Last successful PDO statement.
	 */
	public function stmtBuffer(): PDOStatement
	{
		if (!$this->stmtBuffer) {
			throw new Trunk('PDOStatement is not available because no query has been executed successfully.');
		}
		
		return $this->stmtBuffer;
	}
	
	/**
	 * Returns the affected row count from the most recent successful query.
	 *
	 * @return int Number of affected rows.
	 */
	public function rowCount(): int
	{
		if (!$this->executed) {
			throw new Trunk('No query has been executed.');
		}
		
		return $this->affectedRows;
	}
	
	/**
	 * Alias for rowCount().
	 *
	 * @return int Number of affected rows from the last successful query.
	 */
	public function affectedRows(): int
	{
		return $this->rowCount();
	}
	
	/**
	 * Returns whether the most recent successful query affected zero rows.
	 *
	 * @return bool True when rowCount() is zero.
	 */
	public function zeroRow(): bool
	{
		return $this->rowCount() === 0;
	}
	
	/**
	 * Reads the first value from the first row of the current result set as an integer.
	 *
	 * This is intended for SQL COUNT() style queries.
	 *
	 * @return int First column value of the first row, cast to int, or 0 when no row exists.
	 */
	public function sqlCount(): int
	{
		$row = $this->fetchFirst();
		
		if (empty($row)) {
			return 0;
		}
		
		foreach ($row as $value) {
			return (int) $value;
		}
		
		return 0;
	}
	
	/**
	 * Returns the last insert id captured immediately after the most recent query.
	 *
	 * The value is cached on the Fluent instance so it remains available after a lazy
	 * pooled connection has been released.
	 *
	 * @return ?string Last insert id, or null when no query has set it.
	 */
	public function lastInsertRowId(): ?string
	{
		return $this->lastInsertId;
	}
	
	/**
	 * Returns the pinned PDO connection.
	 *
	 * Direct PDO access is only allowed on pinned Fluent instances created by
	 * DBMan::withFluent(), withTransaction(), or atomic(). Lazy Fluent instances must
	 * use withPDO() so the connection can be safely borrowed and released.
	 *
	 * @return PDO The pinned PDO connection.
	 */
	public function pdo(): PDO
	{
		if ($this->pinnedPDO === null) {
			throw new Trunk('PDO is only directly available inside withFluent(), withTransaction(), or atomic().');
		}
		
		return $this->pinnedPDO;
	}
	
	/**
	 * Runs a callback with a PDO connection.
	 *
	 * Lazy Fluent instances borrow a connection through DBMan for the duration of the
	 * callback. Pinned Fluent instances reuse their pinned PDO. This is the safe escape
	 * hatch for lower-level PDO operations.
	 *
	 * @param callable $callback Function invoked as callback(PDO $pdo): mixed.
	 * @return mixed The callback return value.
	 */
	public function withPDO(callable $callback): mixed
	{
		if ($this->pinnedPDO !== null) {
			return $callback($this->pinnedPDO);
		}
		
		return $this->dbMan->withConnection($this->profileId, $callback);
	}
	
	/**
	 * Returns one row from the current result set by zero-based index.
	 *
	 * @param int $row Zero-based row index.
	 * @return ?array The row as an associative array, or null when the row does not exist.
	 */
	public function fetch(int $row = 0): ?array
	{
		$data = $this->dataArr();
		
		return $data[$row] ?? null;
	}
	
	/**
	 * Returns the first row from the current result set.
	 *
	 * @return ?array The first row as an associative array, or null when no row exists.
	 */
	public function fetchFirst(): ?array
	{
		return $this->fetch();
	}
	
	/**
	 * Returns the last row from the current result set.
	 *
	 * @return ?array The last row as an associative array, or null when no row exists.
	 */
	public function fetchLast(): ?array
	{
		$data = $this->dataArr();
		
		if (empty($data)) {
			return null;
		}
		
		return $data[array_key_last($data)];
	}
	
	/**
	 * Returns all rows from the current result set.
	 *
	 * @return array List of associative rows. Empty array when the result set has no rows.
	 */
	public function fetchAll(): array
	{
		return $this->dataArr();
	}
	
	/**
	 * Extracts one or more columns from the current result set.
	 *
	 * When one column is requested, the return value is a flat list. When multiple
	 * columns are requested, the return value is a list of row vectors in the requested
	 * column order.
	 *
	 * @param string|array ...$column Column names or arrays of column names.
	 * @return array Extracted column values.
	 */
	public function fetchColumns(string|array ...$column): array
	{
		$data = $this->dataArr();
		
		if (empty($data)) {
			return [];
		}
		
		$columns = $this->varargsAsArray($column);
		$singleColumn = count($columns) === 1;
		
		$result = [];
		
		foreach ($data as $row) {
			$buffer = [];
			
			foreach ($columns as $col) {
				if (!array_key_exists($col, $row)) {
					throw new Trunk("No such column in query result: $col");
				}
				
				$buffer[] = $row[$col];
			}
			
			$result[] = $singleColumn ? $buffer[0] : $buffer;
		}
		
		return $result;
	}
	
	/**
	 * Indexes the current result set by the value of one column.
	 *
	 * @param string $key Column whose value becomes the result array key.
	 * @param bool $keepKeyInDataset Whether to keep the key column inside each row.
	 * @return array Associative array keyed by the selected column value.
	 */
	public function fetchByKey(string $key, bool $keepKeyInDataset = true): array
	{
		$data = $this->dataArr();
		
		if (empty($data)) {
			return [];
		}
		
		if (!array_key_exists($key, $data[0])) {
			throw new Trunk("The query result dataset does not have key: $key");
		}
		
		$result = [];
		
		foreach ($data as $row) {
			$groupKey = $row[$key];
			
			if (!$keepKeyInDataset) {
				unset($row[$key]);
			}
			
			$result[$groupKey] = $row;
		}
		
		return $result;
	}
	
	/**
	 * Indexes selected columns from the current result set by a key column.
	 *
	 * The key column is used as the result array key and is not included in each
	 * selected row unless it is also listed in $cols.
	 *
	 * @param string $colKey Column whose value becomes the result array key.
	 * @param string|array ...$cols Columns to include in each result entry.
	 * @return array Associative array keyed by $colKey values.
	 */
	public function fetchColumnsByKey(string $colKey, string|array ...$cols): array
	{
		$data = $this->dataArr();
		
		if (empty($data) || count($data[0]) < 2) {
			return [];
		}
		
		if (!array_key_exists($colKey, $data[0])) {
			return [];
		}
		
		$cols = $this->varargsAsArray($cols);
		$result = [];
		
		foreach ($data as $row) {
			$key = $row[$colKey];
			
			$buffer = [];
			
			foreach ($cols as $col) {
				if (!array_key_exists($col, $row)) {
					throw new Trunk("No such column in query result: $col");
				}
				
				$buffer[$col] = $row[$col];
			}
			
			$result[$key] = $buffer;
		}
		
		return $result;
	}
	
	/**
	 * Reads a single column value from one row of the current result set.
	 *
	 * @param string $col Column name to read.
	 * @param mixed $defVal Value returned when the row or column does not exist.
	 * @param int $row Zero-based row index.
	 * @return mixed The column value or $defVal.
	 */
	public function read(string $col, mixed $defVal = null, int $row = 0): mixed
	{
		$data = $this->fetch($row);
		
		if (empty($data)) {
			return $defVal;
		}
		
		return $data[$col] ?? $defVal;
	}
	
	/**
	 * Runs a managed atomic transaction.
	 *
	 * Hati starts the transaction, passes a pinned Fluent instance to the callback,
	 * commits automatically when the callback returns normally, and rolls back when the
	 * callback throws. Manual commit()/rollback() inside atomic() is rejected; use
	 * withTransaction() when the developer must decide when to close the transaction.
	 *
	 * @param callable $callback Function invoked as callback(Fluent $db): mixed.
	 * @return mixed The callback return value after successful commit.
	 */
	public function atomic(callable $callback): mixed
	{
		return $this->withPDO(function (PDO $pdo) use ($callback) {
			if ($pdo->inTransaction()) {
				throw new Trunk('Nested transactions are not supported by Fluent::atomic().');
			}
			
			$tx = self::pinned($this->dbMan, $this->profileId, $pdo);
			
			$pdo->beginTransaction();
			
			try {
				$result = $callback($tx);
				
				/**
				 * atomic() means Hati controls commit/rollback.
				 *
				 * If the developer manually closes the transaction inside atomic(),
				 * that is ambiguous, so we reject it. Use withTransaction() for manual control.
				 */
				if (!$pdo->inTransaction()) {
					throw new Trunk(
						'Atomic transaction was manually committed or rolled back. Use withTransaction() for manual transaction control.'
					);
				}
				
				$pdo->commit();
				
				return $result;
			} catch (Throwable $t) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				
				throw $t;
			}
		});
	}
	
	/**
	 * Runs a manual transaction callback.
	 *
	 * Hati starts the transaction and passes a pinned Fluent instance to the callback,
	 * but the developer must call commit() or rollback() before the callback returns.
	 * If the callback finishes while the transaction is still open, Hati rolls back and
	 * throws Trunk so the mistake is visible.
	 *
	 * @param callable $callback Function invoked as callback(Fluent $db): mixed.
	 * @return mixed The callback return value after the developer closed the transaction.
	 */
	public function withTransaction(callable $callback): mixed
	{
		return $this->withPDO(function (PDO $pdo) use ($callback) {
			if ($pdo->inTransaction()) {
				throw new Trunk('Nested transactions are not supported by Fluent::withTransaction().');
			}
			
			$tx = self::pinned($this->dbMan, $this->profileId, $pdo);
			
			$pdo->beginTransaction();
			
			try {
				$result = $callback($tx);
				
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
					
					throw new Trunk(
						'Transaction callback finished without commit() or rollback().'
					);
				}
				
				return $result;
			} catch (Throwable $t) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				
				throw $t;
			}
		});
	}
	
	/**
	 * Begins a manual transaction on a pinned Fluent instance.
	 *
	 * This method requires direct access to a pinned PDO and is therefore intended for
	 * code running inside DBMan::withFluent(). For callback transactions, prefer
	 * withTransaction() or atomic().
	 *
	 * @return void
	 */
	public function beginTrans(): void
	{
		$pdo = $this->pdo();
		$pdo->beginTransaction();
	}
	
	/**
	 * Rolls back the current transaction on a pinned Fluent instance.
	 *
	 * @return bool True when a transaction was active and rollback succeeded; false otherwise.
	 */
	public function rollback(): bool
	{
		$pdo = $this->pdo();
		
		return $pdo->inTransaction() && $pdo->rollBack();
	}
	
	/**
	 * Commits the current transaction on a pinned Fluent instance.
	 *
	 * @return bool True when a transaction was active and commit succeeded; false otherwise.
	 */
	public function commit(): bool
	{
		$pdo = $this->pdo();
		
		return $pdo->inTransaction() && $pdo->commit();
	}
	
	private function dataArr(): array
	{
		if (!$this->executed) {
			throw new Trunk('No query has been executed.');
		}
		
		return $this->data ?? [];
	}
	
	private function insertData(string $table, array $columns, array $values, bool $usePrepare): int
	{
		[$cols, $vals, $params] = $this->columnValueStruct($columns, $values, $usePrepare);
		
		$table = $this->quoteIdentifier($table);
		
		$query = sprintf(
			'INSERT INTO %s(%s) VALUES(%s)',
			$table,
			implode(', ', $cols),
			implode(', ', $vals),
		);
		
		return $usePrepare
			? $this->exePrepare($query, $params)
			: $this->exec($query);
	}
	
	private function updateData(
		string $table,
		array $columns,
		array $values,
		bool $usePrepare,
		string $where,
		array $whereValues,
	): int {
		[$cols, $vals, $params] = $this->columnValueStruct($columns, $values, $usePrepare);
		
		$sets = [];
		
		foreach ($cols as $i => $col) {
			$sets[] = $col . ' = ' . $vals[$i];
		}
		
		$table = $this->quoteIdentifier($table);
		
		$query = sprintf(
			'UPDATE %s SET %s',
			$table,
			implode(', ', $sets),
		);
		
		if ($where !== '') {
			if ($usePrepare) {
				$this->assertPlaceholderCount($where, count($whereValues));
				$query .= ' WHERE ' . $where;
				$params = array_merge($params, $whereValues);
			} else {
				$query .= ' WHERE ' . $this->bindRawPlaceholders($where, $whereValues);
			}
		}
		
		return $usePrepare
			? $this->exePrepare($query, $params)
			: $this->exec($query);
	}
	
	private function deleteData(string $table, string $where, array $whereValues, bool $usePrepare): int
	{
		$table = $this->quoteIdentifier($table);
		
		$query = 'DELETE FROM ' . $table;
		
		if ($where !== '') {
			if ($usePrepare) {
				$this->assertPlaceholderCount($where, count($whereValues));
				$query .= ' WHERE ' . $where;
			} else {
				$query .= ' WHERE ' . $this->bindRawPlaceholders($where, $whereValues);
			}
		}
		
		return $usePrepare
			? $this->exePrepare($query, $whereValues)
			: $this->exec($query);
	}
	
	/**
	 * Supports:
	 *
	 * ['name', 'email'], ['Ahad', 'a@b.com']
	 * ['name' => 'Ahad', 'email' => 'a@b.com']
	 * ['name' => 'Ahad', 'email'], ['a@b.com']
	 */
	private function columnValueStruct(array $columns, array $values, bool $usePrepare): array
	{
		$cols = [];
		$vals = [];
		$params = [];
		
		$valueIndex = 0;
		
		foreach ($columns as $key => $value) {
			if (is_string($key)) {
				$column = $key;
				$columnValue = $value;
			} else {
				$column = $value;
				
				if (!array_key_exists($valueIndex, $values)) {
					throw new Trunk('Column name-value pairs provided did not match.');
				}
				
				$columnValue = $values[$valueIndex];
				$valueIndex++;
			}
			
			if (!is_string($column) || $column === '') {
				throw new Trunk('Invalid database column name.');
			}
			
			$cols[] = $this->quoteIdentifier($column);
			
			if ($usePrepare) {
				$vals[] = '?';
				$params[] = $columnValue;
			} else {
				$vals[] = $this->sqlLiteral($columnValue);
			}
		}
		
		if ($valueIndex !== count($values)) {
			throw new Trunk('Too many values were provided for the given columns.');
		}
		
		return [$cols, $vals, $params];
	}
	
	private function quoteIdentifier(string $identifier): string
	{
		$identifier = trim($identifier);
		
		if ($identifier === '') {
			throw new Trunk('SQL identifier cannot be empty.');
		}
		
		$parts = explode('.', $identifier);
		
		foreach ($parts as $part) {
			if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part)) {
				throw new Trunk("Invalid SQL identifier: $identifier");
			}
		}
		
		return '`' . implode('`.`', $parts) . '`';
	}
	
	private function sqlLiteral(mixed $value): string
	{
		return $this->withPDO(function (PDO $pdo) use ($value) {
			return match (true) {
				$value === null => 'NULL',
				is_bool($value) => $value ? '1' : '0',
				is_int($value), is_float($value) => (string) $value,
				is_string($value) => $pdo->quote($value),
				is_object($value) && method_exists($value, '__toString') => $pdo->quote((string) $value),
				default => throw new Trunk('Unsupported SQL literal type. Use prepared statements instead.'),
			};
		});
	}
	
	private function bindRawPlaceholders(string $query, array $values): string
	{
		$this->assertPlaceholderCount($query, count($values));
		
		return $this->replaceSqlPlaceholders($query, $values);
	}
	
	private function assertPlaceholderCount(string $query, int $valueCount): void
	{
		$count = $this->countSqlPlaceholders($query);
		
		if ($count !== $valueCount) {
			throw new Trunk(
				"Placeholder count does not match value count. Expected $count value(s), got $valueCount."
			);
		}
	}
	
	private function resetQueryState(): void
	{
		$this->stmtBuffer = null;
		$this->data = null;
		$this->executed = false;
		$this->affectedRows = 0;
		$this->lastInsertId = null;
		
		$this->errorInfo = [
			'code' => 0,
			'message' => '',
		];
	}
	
	private function handleError(Throwable $t, string $query): never
	{
		$this->errorInfo = [
			'code' => $t->getCode(),
			'message' => $t->getMessage(),
		];
		
		if ($this->errorHandler !== null) {
			($this->errorHandler)(
				$query,
				$t->getCode(),
				$t->getMessage(),
				$this->profileId,
			);
		}
		
		throw new Trunk(
			'Database query failed for profile ' . $this->profileId . ': ' . $t->getMessage()
		);
	}
	
	private function varargsAsArray(array $args): array
	{
		$result = [];
		
		foreach ($args as $arg) {
			if (is_array($arg)) {
				foreach ($arg as $item) {
					$result[] = $item;
				}
				
				continue;
			}
			
			$result[] = $arg;
		}
		
		return $result;
	}
	
	private function countSqlPlaceholders(string $sql): int
	{
		$count = 0;
		$length = strlen($sql);
		
		$inSingle = false;
		$inDouble = false;
		$inBacktick = false;
		$inLineComment = false;
		$inBlockComment = false;
		
		for ($i = 0; $i < $length; $i++) {
			$char = $sql[$i];
			$next = $sql[$i + 1] ?? '';
			
			if ($inLineComment) {
				if ($char === "\n") {
					$inLineComment = false;
				}
				
				continue;
			}
			
			if ($inBlockComment) {
				if ($char === '*' && $next === '/') {
					$inBlockComment = false;
					$i++;
				}
				
				continue;
			}
			
			if (!$inSingle && !$inDouble && !$inBacktick) {
				if ($char === '-' && $next === '-' && $this->isMysqlLineCommentStart($sql, $i)) {
					$inLineComment = true;
					$i++;
					continue;
				}
				
				if ($char === '#') {
					$inLineComment = true;
					continue;
				}
				
				if ($char === '/' && $next === '*') {
					$inBlockComment = true;
					$i++;
					continue;
				}
			}
			
			if (!$inDouble && !$inBacktick && $char === "'" && !$this->isEscaped($sql, $i)) {
				$inSingle = !$inSingle;
				continue;
			}
			
			if (!$inSingle && !$inBacktick && $char === '"' && !$this->isEscaped($sql, $i)) {
				$inDouble = !$inDouble;
				continue;
			}
			
			if (!$inSingle && !$inDouble && $char === '`') {
				$inBacktick = !$inBacktick;
				continue;
			}
			
			if (!$inSingle && !$inDouble && !$inBacktick && $char === '?') {
				$count++;
			}
		}
		
		return $count;
	}
	
	private function isEscaped(string $sql, int $index): bool
	{
		$slashes = 0;
		
		for ($i = $index - 1; $i >= 0 && $sql[$i] === '\\'; $i--) {
			$slashes++;
		}
		
		return $slashes % 2 === 1;
	}
	
	private function isListArray(array $array): bool
	{
		$expected = 0;
		
		foreach ($array as $key => $_) {
			if ($key !== $expected) {
				return false;
			}
			
			$expected++;
		}
		
		return true;
	}
	
	private function replaceSqlPlaceholders(string $sql, array $values): string
	{
		$result = '';
		$valueIndex = 0;
		$length = strlen($sql);
		
		$inSingle = false;
		$inDouble = false;
		$inBacktick = false;
		$inLineComment = false;
		$inBlockComment = false;
		
		for ($i = 0; $i < $length; $i++) {
			$char = $sql[$i];
			$next = $sql[$i + 1] ?? '';
			
			if ($inLineComment) {
				$result .= $char;
				
				if ($char === "\n") {
					$inLineComment = false;
				}
				
				continue;
			}
			
			if ($inBlockComment) {
				$result .= $char;
				
				if ($char === '*' && $next === '/') {
					$result .= $next;
					$inBlockComment = false;
					$i++;
				}
				
				continue;
			}
			
			if (!$inSingle && !$inDouble && !$inBacktick) {
				if ($char === '-' && $next === '-' && $this->isMysqlLineCommentStart($sql, $i)) {
					$result .= $char . $next;
					$inLineComment = true;
					$i++;
					continue;
				}
				
				if ($char === '#') {
					$result .= $char;
					$inLineComment = true;
					continue;
				}
				
				if ($char === '/' && $next === '*') {
					$result .= $char . $next;
					$inBlockComment = true;
					$i++;
					continue;
				}
			}
			
			if (!$inDouble && !$inBacktick && $char === "'" && !$this->isEscaped($sql, $i)) {
				$inSingle = !$inSingle;
				$result .= $char;
				continue;
			}
			
			if (!$inSingle && !$inBacktick && $char === '"' && !$this->isEscaped($sql, $i)) {
				$inDouble = !$inDouble;
				$result .= $char;
				continue;
			}
			
			if (!$inSingle && !$inDouble && $char === '`') {
				$inBacktick = !$inBacktick;
				$result .= $char;
				continue;
			}
			
			if (!$inSingle && !$inDouble && !$inBacktick && $char === '?') {
				$result .= $this->sqlLiteral($values[$valueIndex++]);
				continue;
			}
			
			$result .= $char;
		}
		
		return $result;
	}
	
	private function isMysqlLineCommentStart(string $sql, int $index): bool
	{
		if (($sql[$index] ?? '') !== '-' || ($sql[$index + 1] ?? '') !== '-') {
			return false;
		}
		
		$after = $sql[$index + 2] ?? '';
		
		return $after === ''
			|| $after === ' '
			|| $after === "\t"
			|| $after === "\n"
			|| $after === "\r";
	}
	
}
