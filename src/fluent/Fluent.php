<?php /** @noinspection SqlNoDataSourceInspection */

namespace hati\fluent;

use hati\Hati;
use hati\Trunk;
use hati\util\Arr;
use hati\util\Util;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Fluent is wrapper class around PDO extension to allow simple, flawless
 * easy access and manipulation of the database query operations. It uses
 * singleton pattern to permits its instance. This also supports transaction
 * operations on database using various methods such as beginTrans, rollback
 * & commit.
 *
 *
 * Any data returning method assumes that a successful connection has made to
 * the database and a query has already been executed. Check out fetch* or read*
 * methods to learn how to fetch data.
 *
 * For better security and practice, it is recommended that call to any Fluent
 * method should be inside try-catch block to hide the throwing error message
 * or reactive to any error.
 * */

class Fluent {
	
	// manages multiple db connections
	private DBMan $dbMan;
	
	// holds the in-use PDO object to the db connection
	private ?PDO $db = null;
	
	// indicates whether any query has already been executed
	private bool $executed = false;
	
	// an internal buffer; used to cache the result set of the query
	// helping to avoid iterator offset outbound exception
	private mixed $stmtBuffer = null;
	
	// holds the actual result set array of the query
	private mixed $data = null;
	
	// indicates whether to show query in the error output
	private bool $debugSql = false;
	
	private bool $sqlErrInOutput = false;
	
	// track which profile id in use
	private ?string $profileId;
	
	// a Fluent instance for singleton pattern
	private static ?Fluent $INS = null;
	
	private function __construct() {
		$this->dbMan = new DBMan();
		$this->profileId = self::defaultProfileId();
	}
	
	/**
	 * The Fluent wrapper object is created by this call. It creates database connection
	 * as specified in the hati.json file. It uses singleton pattern to cache the
	 * connection object.
	 *
	 * @return Fluent a fluent instance
	 */
	public static function get(): Fluent {
		// create new instance if there is not any already
		if (Fluent::$INS == null) Fluent::$INS = new Fluent();
		return Fluent::$INS;
	}
	
	/**
	 * With Hati 5, multiple database connections can be used. Using this method,
	 * the current in-use db profile id can be fetched. See {@link Fluent::use()}
	 * for more details.
	 *
	 * @return ?string The current database profile id in use
	 * */
	public static function currentDBId(): ?string {
		return self::get()->profileId;
	}
	
	/**
	 * Shows the query with params for debugging
	 * */
	public static function debugSQL(): void {
		$ins = self::get();
		$ins->debugSql = true;
	}
	
	/**
	 * Show SQL error in the error output
	 * */
	public static function sqlErrInOutput(): void {
		$ins = self::get();
		$ins->sqlErrInOutput = true;
	}
	
	/**
	 * Since Hati 5, Fluent can work with multiple database connection profiles.
	 * Database connections are specified by objects in the <b>hati/db.json</b>
	 * file where each object is identified by their profile names.
	 *
	 * A db profile can have many database. Connection to each database is
	 * represented by id. Id naming has a strict convention. It should be composed
	 * of database profile name followed by colon, followed by the database name it
	 * connects to. For example, to represent a connection to database test2 on
	 * localhost, using root @ pass, the profile id would be:
	 * <b>Example-Localhost:test2</b>
	 * <br>
	 * <code>
	 * {
	 * 	"db_profiles": {
	 * 		"Example-Localhost": {
	 * 			"host": "localhost",
	 * 			"username": "root",
	 * 			"password": "pass",
	 * 			"port": 3306,
	 * 			"charset": "utf8",
	 * 			"timezone": "+06:00", // optional
	 * 			"db": ["test1","test2"] // can be a single db string
	 * 		}
	 * 	}
	 * }
	 *  </code>
	 *
	 * @param string $dbProfile The db profile id
	 * @return ?PDO  The pdo connection object to the database
	 * */
	public static function use(string $dbProfile): ?PDO {
		$ins = self::get();
		
		$ins->profileId = $dbProfile;
		$ins->db = $ins->dbMan->connect($dbProfile);
		
		return $ins->db;
	}
	
	/**
	 * After the call to this method, Fluent is going to use the default database profile
	 * as specified in the <b>hati/db.json</b> file for the subsequent queries where the PDO
	 * object is optional until another call is made to change the db connection using
	 * {@link Fluent::use()}
	 *
	 * <br>Note: If no profile is specified, Fluent always uses the default profile as per
	 * configuration in all cases.
	 *
	 * @return ?PDO The default PDO connection object
	 * */
	public static function useDefault(): ?PDO {
		return self::use(self::defaultProfileId());
	}
	
	/**
	 * Execute method is more powerful at executing any prepared statement.
	 * For a given query, it prepares the query then binds it on runtime using PDO
	 * execute method. After execution, it caches the output into a variable called
	 * buffer internally.
	 *
	 * You should use this method if you embed external values to the query to avoid many
	 * possible SQL injections.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $query the query to be executed
	 * @param array $param array containing binding values to the query
	 * @param string $msg any custom message to replace default system error message
	 *
	 * @return int indicates how many rows were affected by the query execution.
	 * */
	public static function exePrepareWith(PDO $pdo, string $query, array $param = [], string $msg = ''): int {
		$ins = self::get();
		
		try {
			$ins->data = null;
			$ins->executed = false;
			
			if ($ins->debugSql) {
				vd([$query, $param]);
			}
			
			$ins->stmtBuffer = $pdo->prepare($query);
			$ins->executed = $ins->stmtBuffer->execute($param);
			return $ins->stmtBuffer->rowCount();
		} catch (Throwable $t) {
			$message = self::buildErrMsg($msg, $query, $ins->sqlErrInOutput, $t->getMessage());
			throw new Trunk($message);
		}
	}
	
	/**
	 * Using default database profile, executes query as prepared statement to avoid
	 * SQL injections. This method calls on exePrepareWith method internally. See
	 * {@link Fluent::exePrepareWith} for more details.
	 *
	 * @param string $query the query to be executed
	 * @param array $param array containing binding values to the query
	 * @param string $msg any custom message to replace default system error message
	 *
	 * @return int indicates how many rows were affected by the query execution.
	 * */
	public static function exePrepare(string $query, array $param = [], string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::exePrepareWith($ins->db, $query, $param, $msg);
	}
	
	/**
	 * This method works similarly as {@link Fluent::exePrepare} works. The only difference
	 * between them is that this method doesn't prepare the query. You should use
	 * this for static query which doesn't embed any value to the query as this
	 * can greatly improve the execution performance.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $query the query to be executed
	 * @param string $msg any custom message to replace default system error message
	 *
	 * @return int indicates how many rows were affected by the query execution.
	 * */
	public static function exeStaticWith(PDO $pdo, string $query, string $msg = ''): int {
		$ins = self::get();
		try {
			$ins->data = null;
			$ins->executed = false;
			
			if ($ins->debugSql) {
				vd([$query]);
			}
			
			$ins->stmtBuffer = $pdo->query($query);
			$ins->executed = $ins->stmtBuffer != false;
			return $ins->stmtBuffer->rowCount();
		} catch (Throwable $t) {
			$message = self::buildErrMsg($msg, $query, $ins->sqlErrInOutput, $t -> getMessage());
			throw new Trunk($message);
		}
	}
	
	/**
	 * Using default database profile, executes a raw query without using prepared
	 * statement and there will be no parameter binding during query execution. For
	 * using prepared statements, use {@link Fluent::exePrepare} method instead
	 *
	 * @param string $query the query to be executed
	 * @param string $msg any custom message to replace default system error message
	 *
	 * @return int indicates how many rows were affected by the query execution.
	 * */
	public static function exeStatic(string $query, string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::exeStaticWith($ins->db, $query, $msg);
	}
	
	/**
	 * Helper method with allows easy data insertion with prepare statement to default db
	 * connection.
	 *
	 * @param string $table The name of the table to perform this insert operation to
	 * @param array $columns It can be either:
	 * <br>- a normal array containing columns for prepare statement
	 * <br>- an associative array of column-value mapping
	 * <br>- mixed of both.
	 * <br>If associative array is passed-in then, these column-value pairs are not used in
	 * data binding of the prepare statement. They will be just added as part of normal
	 * insert query.
	 * <br>For mixed array, it tries to match the missing value from the values array as
	 * prepare statement.
	 *
	 * @param array $values Array containing the values for the prepared statement data binding
	 * @param string $msg Any message to replace the default mysql error with
	 *
	 * @throws RuntimeException If the number of bind columns don't match with the number of values passed-in
	 * @return int indicates how many rows were affected by the query execution.
	 **/
	public static function insertPrepare(string $table, array $columns, array $values = [], string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::insertData($ins->db, $table, $columns, $values, true, $msg);
	}
	
	/**
	 * Insert data using prepared statement to a specified PDO connection object.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $table The name of the table to perform this insert operation to
	 * @param array $columns It can be either:
	 * <br>- a normal array containing columns for prepare statement
	 * <br>- an associative array of column-value mapping
	 * <br>- mixed of both.
	 * <br>If associative array is passed-in then, these column-value pairs are not used in
	 * data binding of the prepare statement. They will be just added as part of normal
	 * insert query.
	 * <br>For mixed array, it tries to match the missing value from the values array as
	 * prepare statement.
	 *
	 * @param array $values Array containing the values for the prepared statement data binding
	 * @param string $msg Any message to replace the default mysql error with
	 *
	 * @return int indicates how many rows were affected by the query execution.
	 * *@throws RuntimeException If the number of bind columns don't match with the number of values passed-in
	 */
	public static function insertPrepareWith(PDO $pdo, string $table, array $columns, array $values = [], string $msg = ''): int {
		return self::insertData($pdo, $table, $columns, $values, true, $msg);
	}
	
	/**
	 * Allows easy data insertion to default database profile.
	 *
	 * @param string $table The name of the table to perform this insert operation to
	 * @param array $columns It can be either:
	 * <br>- a normal array containing columns for the query
	 * <br>- an associative array of column-value mapping
	 * <br>- mixed of both.
	 * <br>If associative array is passed-in then, these column-value mapping happens
	 * as they are defined by the array.
	 * <br>For mixed array where some columns don't have value pair, then it tries to
	 * match the missing value from the values array to complete the array
	 *
	 * @param array $values Array containing the values for the query
	 * @param string $msg Any message to replace the default mysql error with
	 *
	 * @throws RuntimeException If the number of columns which have missing values don't
	 * match with the number of values passed-in
	 * @return int indicates how many rows were affected by the query execution.
	 **/
	public static function insert(string $table, array $columns, array $values = [], string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::insertData($ins->db, $table, $columns, $values, false, $msg);
	}
	
	/**
	 * Insert data without using prepared statement to specified PDO connection.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $table The name of the table to perform this insert operation to
	 * @param array $columns It can be either:
	 * <br>- a normal array containing columns for the query
	 * <br>- an associative array of column-value mapping
	 * <br>- mixed of both.
	 * <br>If associative array is passed-in then, these column-value mapping happens
	 * as they are defined by the array.
	 * <br>For mixed array where some columns don't have value pair, then it tries to
	 * match the missing value from the values array to complete the array
	 *
	 * @param array $values Array containing the values for the query
	 * @param string $msg Any message to replace the default mysql error with
	 *
	 * @return int indicates how many rows were affected by the query execution.
	 * *@throws RuntimeException If the number of columns which have missing values don't
	 * match with the number of values passed-in
	 */
	public static function insertWith(PDO $pdo, string $table, array $columns, array $values = [], string $msg = ''): int {
		return self::insertData($pdo, $table, $columns, $values, false, $msg);
	}
	
	/**
	 * Helper method allows easy update operation to default database profile. It doesn't use prepare statement.
	 * No values are prepared for the query. Use {@link Fluent::updatePrepare()} method instead.
	 *
	 * @param string $table The table name
	 * @param array $cols It the column which needs to be updated. Can contain key-value mapping too. The value for a
	 * can be left out, be passed-in in values array.
	 * @param string $where Optional where clause to control the update operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @return int The number of raws were updated by the query
	 * @throws RuntimeException It throws run time exception when number cols-values or where-whereValue pair don't
	 * match
	 */
	public static function update(string $table, array $cols, array $values = [], string $where = '', array $whereValues = [], string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::updateData($ins->db, $table, $cols, $values, false, $where, $whereValues, $msg);
	}
	
	/**
	 * Helper method allows easy update operation to specified PDO connection. It doesn't use prepare statement.
	 * No values are prepared for the query. Use {@link Fluent::updatePrepare()} method instead.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $table The table name
	 * @param array $cols It the column which needs to be updated. Can contain key-value mapping too. The value for a
	 * can be left out, be passed-in in values array.
	 * @param string $where Optional where clause to control the update operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @return int The number of raws were updated by the query
	 **@throws RuntimeException It throws run time exception when number cols-values or where-whereValue pair don't
	 * match
	 */
	public static function updateWith(PDO $pdo, string $table, array $cols, array $values = [], string $where = '', array $whereValues = [], string $msg = ''): int {
		return self::updateData($pdo, $table, $cols, $values, false, $where, $whereValues, $msg);
	}
	
	/**
	 * Helper method allows easy update operation to default database profile. It doesn't use prepare statement.
	 * No values are prepared for the query. Use {@link Fluent::updatePrepare()} method instead.
	 *
	 * @param string $table The table name
	 * @param array $cols It the column which needs to be updated. Can contain key-value mapping too. The value for a
	 * can be left out, be passed-in in values array.
	 * @param string $where Optional where clause to control the update operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @return int The number of raws were updated by the query
	 **@throws RuntimeException It throws run time exception when number cols-values or where-whereValue pair don't
	 * match
	 */
	public static function updatePrepare(string $table, array $cols, array $values = [], string $where = '', array $whereValues = [], string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::updateData($ins->db, $table, $cols, $values, true, $where, $whereValues,  $msg);
	}
	
	/**
	 * Helper method allows easy update operation to specified PDO connection. It uses prepare statement to bind values
	 * for column values and where clauses.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $table The table name
	 * @param array $cols It the column which needs to be updated. Can contain key-value mapping too. The value for a
	 * can be left out, be passed-in in values array.
	 * @param string $where Optional where clause to control the update operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @return int The number of raws were updated by the query
	 **@throws RuntimeException It throws run time exception when number cols-values or where-whereValue pair don't
	 * match
	 */
	public static function updatePrepareWith(PDO $pdo, string $table, array $cols, array $values = [], string $where = '', array $whereValues = [], string $msg = ''): int {
		return self::updateData($pdo, $table, $cols, $values, true, $where, $whereValues,  $msg);
	}
	
	/**
	 * Helper method allows easy delete operation to default database profile. It doesn't use prepare statement.
	 * No values are prepared for the query. Use {@link Fluent::deletePrepare()} method instead.
	 *
	 * @param string $table The table name
	 * @param string $where The where clause to control the update operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @return int The number of raws were deleted by the query
	 **@throws RuntimeException It throws run time exception when number of  where-whereValue pair don't match
	 */
	public static function delete(string $table, string $where = '', array $whereValues = [], string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::deleteData($ins->db, $table, $where, $whereValues, false, $msg);
	}
	
	/**
	 * Helper method allows easy delete operation to specified PDO connection. It doesn't use prepare statement.
	 * No values are prepared for the query. Use {@link Fluent::deletePrepare()} method instead.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $table The table name
	 * @param string $where The where clause to control the update operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @return int The number of raws were deleted by the query
	 **@throws RuntimeException It throws run time exception when number of  where-whereValue pair don't match
	 */
	public static function deleteWith(PDO $pdo, string $table, string $where = '', array $whereValues = [], string $msg = ''): int {
		return self::deleteData($pdo, $table, $where, $whereValues, false, $msg);
	}
	
	/**
	 * Helper method allows easy delete operation to default database profile. It uses prepare statement
	 * to bind values for where clauses.
	 *
	 * @param string $table The table name
	 * @param string $where The where clause to control the delete operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @throws RuntimeException It throws run time exception when number where-whereValue pair don't match
	 * @return int The number of raws were deleted by the query
	 **/
	public static function deletePrepare(string $table, string $where = '', array $whereValues = [], string $msg = ''): int {
		$ins = self::get();
		
		if (is_null($ins->db)) {
			$ins->db = $ins->dbMan->connect(self::defaultProfileId());
		}
		
		return self::deleteData($ins->db, $table, $where, $whereValues, true, $msg);
	}
	
	/**
	 * Helper method allows easy delete operation to specified PDO connection. It uses prepare statement to bind values
	 * for where clauses.
	 *
	 * @param PDO $pdo the PDO is to be used for the query to be executed
	 * @param string $table The table name
	 * @param string $where The where clause to control the delete operation. Column here can be set as part of
	 * the string or be marked with ? mark which can be passed in by whereValues array.
	 * @param array $whereValues Array containing the values for the where clauses
	 * @param string $msg Any custom message to replace SQL error message
	 *
	 * @throws RuntimeException It throws run time exception when number where-whereValue pair don't match
	 * @return int The number of raws were deleted by the query
	 **/
	public static function deletePrepareWith(PDO $pdo, string $table, string $where = '', array $whereValues = [], string $msg = ''): int {
		return self::deleteData($pdo, $table, $where, $whereValues, true, $msg);
	}
	
	/**
	 * When a query get successfully prepared with the query string, a PDOStatement
	 * can be achieved for further processing. This method first checks whether it
	 * has already been prepared by null checking on the stmt internal buffer.
	 *
	 * @return PDOStatement a PDOStatement object is returned upon successful query
	 * preparation.
	 */
	public static function stmtBuffer(): PDOStatement {
		$ins = self::get();
		if (!$ins->stmtBuffer)
			throw new Trunk('PDOStatement was failed to be obtained as encountered error in query preparation.');
		return $ins->stmtBuffer;
	}
	
	/**
	 * Often times, code wants to know whether the query is affecting any row/result
	 * at all. This method uses @link rowCount method internally to calculate the
	 * zero count.
	 *
	 * @return bool indicating whether the query affecting zero query or not.
	 * */
	public static function zeroRow(): bool {
		return Fluent::rowCount() == 0;
	}
	
	/**
	 * This method can count the number of row/result returned by the execution
	 * of a query. Before, counting it assesses whether there has been any query
	 * executed. If not, then throws a runtime exception of type HatiError.
	 *
	 * @return int number of rows/result was affected by the query.
	 */
	public static function rowCount(): int {
		$ins = self::get();
		if (!$ins->executed) throw new Trunk('Failed to count as no query has been executed.');
		return $ins->stmtBuffer->rowCount();
	}
	
	/**
	 * This method is used to get the returned value of the sql query count.
	 * It is not like rowCount method. This works on the query where the query
	 * uses the COUNT() function from SQL syntax.
	 *
	 * @return int number of result counted by the sql query
	 * */
	public static function sqlCount(): int{
		$count = 0;
		$array = Fluent::fetchFirst();
		
		if (empty($array)) return $count;
		
		foreach ($array as $value)  {
			$count = $value;
			break;
		}
		return $count;
	}
	
	/**
	 * This method returns the id of the last inserted row by the query.
	 *
	 * @return int returns the last inserted row of last sql query
	 */
	public static function lastInsertRowId(): int {
		return self::getPDO()->lastInsertId();
	}
	
	/**
	 * By using this method, you can get the PDO handler object to perform
	 * various database query on demand, so that you have no limitation by
	 * this fluent class.
	 *
	 * @return ?PDO a PDO handler object is returned.
	 */
	public static function getPDO(): ?PDO {
		return self::get()->db;
	}
	
	/**
	 * Returns the specific row of the result set. By default, it returns
	 * the very first row.
	 *
	 * @return ?array it returns the row of result set by the row number.
	 * Returns null if the index is not present in the result set.
	 */
	public static function fetch(int $row = 0): ?array {
		$dataArr = self::get()->dataArr();
		$count = count($dataArr);
		
		if ($count == 0 || $row >= $count) return null;
		
		return $dataArr[$row];
	}
	
	/**
	 * Returns the first row of the result set
	 *
	 * @return ?array it returns the first row of result set. Returns null if none found.
	 */
	public static function fetchFirst(): ?array {
		return self::fetch();
	}
	
	/**
	 * Returns the last row of the result set
	 *
	 * @return ?array it returns the last row of result set. Returns empty array if none found.
	 */
	public static function fetchLast(): ?array {
		$dataArr = self::get()->dataArr();
		$count = count($dataArr);
		
		if ($count == 0) return [];
		return $dataArr[$count - 1];
	}
	
	/**
	 * Fetches the rows as associative array from the result set using PDO fetchAll method.
	 *
	 * @return array the array containing the data. Returns empty array if there is nothing to fetch!
	 */
	public static function fetchAll(): array {
		$dataArr = self::get()->dataArr();
		return empty($dataArr) ? [] : $dataArr;
	}
	
	/**
	 * A database query result set can be extracted by columns. It returns an associative
	 * array of data by columns. For single column, it returns 1D array containing data in
	 * the same order as the query. If more than single column are selected, then column
	 * values are returned as array of vectors of columns values.
	 *
	 * @param string|array $column The columns to be extracted from the result set
	 * @throws InvalidArgumentException when invalid column name was passed in
	 * @returns array containing column values
	 * */
	public static function fetchColumns(string|array ...$column): array {
		$data = self::dataArr();
		
		if (empty($data)) return [];
		
		$bufferCol = [];
		foreach ($column as $col) {
			if (is_array($col)) {
				$bufferCol = array_merge($bufferCol, $col);
				continue;
			}
			$bufferCol[] = $col;
		}
		
		$singleCol = count($bufferCol) == 1;
		$colArr = [];
		
		foreach ($data as $datum) {
			$arr = [];
			foreach ($bufferCol as $col) {
				$v = $datum[$col] ?? '-null';
				if ($v == '-null') throw new InvalidArgumentException("No such column as $col");
				$arr[] = $datum[$col];
			}
			
			if ($singleCol) {
				$colArr[] = $arr[0];
			} else {
				$colArr[] = $arr;
			}
		}
		
		return $colArr;
	}
	
	/**
	 * Rearranges dataset by grouping it based on a specified key.
	 *
	 * This function processes an array of associative arrays, rearranging the data
	 * by grouping it based on a specified key within each associative array.
	 *
	 * @param string $key The key based on which the data will be grouped.
	 * @param bool $keepKeyInDataset (Optional) Indicates whether to keep the key in each dataset after grouping. Default is true.
	 *
	 * @return array An associative array where the keys are the values extracted from the dataset based on the specified key,
	 *               and the values are the corresponding datasets with optional key removal.
	 *
	 * @throws Trunk If the dataset is empty or null, or if the specified key doesn't exist in the dataset's first element.
	 */
	public static function fetchByKey(string $key, bool $keepKeyInDataset = true): array {
		$data = Fluent::dataArr();
		
		if (empty($data))
			throw new Trunk('The query result is either null or empty');
		
		if (!array_key_exists($key, $data[0]))
			throw new Trunk("The query result dataset doesn't have key $key");
		
		$returnArr = [];
		foreach ($data as $d) {
			$k = $d[$key];
			
			if (!$keepKeyInDataset)
				unset($d[$key]);
			
			$returnArr[$k] = $d;
		}
		
		return $returnArr;
	}
	
	/**
	 * Returns rows indexed by a particular column value. It works like
	 * {@link Fluent::fetchByKey()}, but gives freedom to further selects
	 * the columns to fetch.
	 *
	 * There must at least be 2 columns selected by the query. Otherwise,
	 * an empty array will be returned.
	 *
	 * @param string $colKey the column whose value will be the key
	 * @param string|array $cols columns to be fetched
	 * */
	public static function fetchColumnsByKey(string $colKey, string|array ...$cols): array {
		$data = self::dataArr();
		if (empty($data) || count($data[0]) < 2) return [];
		
		$cols = Arr::varargsAsArray($cols);
		
		// Make sure there is the key column exists
		if (empty($data[0][$colKey])) return [];
		
		/*
		 * Remove the colKey from the result and make that key
		 * */
		$return = [];
		
		foreach ($data as $row) {
			$key = null;
			$buffer = [];
			
			foreach ($row as $k => $v) {
				if ($k == $colKey) {
					$key = $v;
					continue;
				}
				
				if (!in_array($k, $cols)) continue;
				
				$buffer[$k] = $v;
			}
			
			if (empty($key)) continue;
			
			$return[$key] = $buffer;
		}
		
		return $return;
	}
	
	/**
	 * Returns the value of the specified column of the specified row.
	 * If the column or the row doesn't exist, it returns default value.
	 *
	 * @param $col string the key for the value
	 * @param $defVal mixed the value to be returned when the key is
	 * not set in the result set.
	 *
	 * @return mixed the value defined by the key
	 */
	public static function read(string $col, mixed $defVal = null, int $row = 0): mixed {
		$data = self::fetch($row);
		if (empty($data)) return $defVal;
		
		return $data[$col] ?? $defVal;
	}
	
	/**
	 * dataArr method will first get the Fluent instance by call get() method
	 * then it checks for the flag whether any query has already been executed.
	 * if not, then it throws a runtime exception of type HatiError. otherwise it fetch
	 * the data as associative array from the result set using PDO fetchAll method.
	 *
	 * @return array the array containing the data
	 */
	private static function dataArr(): array {
		$ins = self::get();
		if (!$ins->executed) throw new Trunk('No query has been executed.');
		$buffer = $ins->stmtBuffer;
		if (is_null($ins->data)) $ins->data = $buffer->fetchAll(PDO::FETCH_ASSOC);
		return $ins->data;
	}
	
	/**
	 * This method initiates a transaction for the database query. This prevents
	 * auto commit features of the queries unless it is said by @link commit method.
	 *
	 * @return bool returns true if a transaction tunnel was able to open; false
	 * otherwise.
	 * */
	public static function beginTrans(): bool {
		$ins = self::get();
		try {
			$ins->db->beginTransaction();
			return true;
		} catch (Throwable) {
			return false;
		}
	}
	
	/**
	 * Any changes to the database using @link beginTrans method executed beforehand
	 * can be rolled back using this method. This method checks whether the database
	 * is in transaction mode before roll backing. It save code from throwing exception.
	 *
	 * @return bool returns true on success; false otherwise.
	 * */
	public static function rollback(): bool {
		$db = self::get()->db;
		return $db->inTransaction() && $db->rollback();
	}
	
	/**
	 * Any changes made during transaction is written off to the database
	 * using commit method. This function save from throwing exception by
	 * first checking whether the database is in any active transaction mode.
	 *
	 * @return true if it can commit changes to the database; false otherwise.
	 * */
	public static function commit(): bool {
		$db = self::get()->db;
		return $db->inTransaction() && $db->commit();
	}

	/**
	 * For a column value, it adds appropriate single quotes to be query friendly
	 * */
	private static function typedValue($val): mixed {
		$pdo = self::getPDO();
		
		if (is_string($val)) return $pdo->quote($val);
		else if (is_object($val)) return $pdo->quote((string) $val);
		return $val;
	}
	
	/**
	 * This method breaks the key-value pairs into SQL syntax like fragments so that they
	 * can easily be processed by insert/update/delete methods.
	 *
	 * @param array $columns The list of columns
	 * @param array $values The values for those columns
	 * @param string $sign any extra separator between column-value such as = for update query
	 * @param bool $usePrepare Indicates whether the values need to be marked resolved after the sign directly
	 * or be left with ? mark so that it can easily be plugged into exePrepare method.
	 *
	 * @throws RuntimeException When there is a mismatch between number of column-values pair combination
	 * @return array Containing two items, first one for the column values and the second one including values with
	 * any specified sign in front such as = ?, = 'X_VALUE'
	 * */
	private static function toQueryStruct(array $columns, array $values, string $sign, bool $usePrepare): array {
		/*
		 * Normalize columns array
		 * */
		$qCount = 0;
		
		$cols = [];
		$vals = [];
		
		foreach ($columns as $i => $v) {
			
			if (!is_integer($i)) {
				$cols[] = $i;
				
				$typedV = self::typedValue($v);
				$vals[] = "$sign{$typedV}";
			} else {
				$cols[] = $v;
				$vals[] = [];
				
				$qCount ++;
			}
		}
		
		if ($qCount !== count($values)) {
			throw new RuntimeException('The number columns that are missing values did not match the number of values provided');
		}
		
		foreach ($vals as $i => $v) {
			if (!is_array($v)) continue;
			
			if ($usePrepare) {
				$vals[$i] = "$sign?";
			} else {
				$typedV = self::typedValue(array_shift($values));
				$vals[$i] = "$sign{$typedV}";
			}
		}
		
		return [$cols, $vals];
	}
	
	/**
	 * This method is used particularly for DELETE & UPDATE SQL statements. It substitutes values of the WHERE
	 * clause with  ? sign or the value meant to be provided as part param binding.
	 *
	 * @param bool $usePrepare Indicates whether the values need to be marked resolved after the sign directly
	 * @param string $query The WHERE part of query, where this binding operation to be performed
	 * @param array $values Values for those ? mark
	 * */
	private static function bindWhere(bool $usePrepare, string $query, array $values): string {
		return preg_replace_callback('/\s*=\s*\?/', function () use ($usePrepare, &$values) {
			return $usePrepare ? ' = ? ' : ' = ' . self::typedValue(array_shift($values));
		}, $query);
	}
	
	/**
	 * This method performs the update operation based on the argument values.
	 *
	 * @param string $table The table name where this update operation is going to be performed
	 * @param array $columns Array containing column-values pari. Values can be left out. Missing values will be picked
	 * up from the values array.
	 * @param array $values Values for the columns.
	 * @param bool $usePrepare Indicates whether to use the prepare statement for the query. This method does all the
	 * tricks for marking values with ? and binding them before query execution.
	 * @param string $where Where clauses to control the update operation. Values can be left out by ? mark be picked
	 * up from the whereValues array.
	 * @param array $whereVal Array containing the values for the where clauses
	 * @param string $msg Any message to replace SQL error
	 *
	 * @throws RuntimeException When column-value pair mismatched
	 * @return int Number of raw were updated by this query
	 **/
	private static function updateData(PDO $pdo, string $table, array $columns, array $values, bool $usePrepare, string $where, array $whereVal, string $msg): int {
		[$cols, $val] = self::toQueryStruct($columns, $values, ' = ', $usePrepare);
		
		$sets = '';
		foreach ($cols as $i => $v) {
			$sets .= "$v$val[$i], ";
		}
		$sets = substr($sets, 0, strlen($sets) - 2);
		
		$q = "UPDATE $table SET $sets";
		
		if (!empty($where)) {
			$w = self::bindWhere($usePrepare, $where, $whereVal);
			$q .= " WHERE $w";
		}
		
		if ($usePrepare) {
			$values = array_merge($values, $whereVal);
		}
		
		return $usePrepare ? self::exePrepareWith($pdo, $q, $values, $msg) : self::exeStaticWith($pdo, $q, $msg);
	}
	
	/**
	 * This method performs the update operation based on the argument values.
	 *
	 * @param string $table The table name
	 * @param string $where The where clause of the query. This can have column with values as regular queries have. But
	 * can also be left out with ? mark so that the values can be fetched from the whereValues.
	 * @param array $whereValues Array containing values for the ? marked columns
	 * @param bool $usePrepare Indicates whether the value should use prepare statement or regular query
	 * @param string $msg Any message to replace SQL query error
	 *
	 * @throws RuntimeException When the number of values doesn't with the number of question mark for binding
	 * @return int The number of rows were affected by this query
	 **/
	private static function deleteData(PDO $pdo, string $table, string $where, array $whereValues, bool $usePrepare, string $msg): int {
		$q = "DELETE FROM $table";
		
		if (!empty($where)) {
			$w = self::bindWhere($usePrepare, $where, $whereValues);
			$q .= " WHERE $w";
		}
		
		return $usePrepare ? self::exePrepareWith($pdo, $q, $whereValues, $msg) : self::exeStaticWith($pdo, $q, $msg);
	}
	
	/**
	 * This method build up the query string based on how the columns & values array are set.
	 * It is private only because of function signature.
	 *
	 * @param string $table The name of the table to perform this insert operation to
	 * @param array $columns It can be a normal array containing columns for prepare statement
	 * or can be an associative array containing name-value mapping.
	 * @param array $values Array containing the values for the prepared statement data binding or columns
	 * @param bool $usePrepare Indicates whether the query it to use prepare statement or not
	 * @param string $msg Any message to replace the default mysql error with
	 *
	 * @throws RuntimeException If the number of bind columns don't match with the number of values passed-in
	 * @return int indicates how many rows were affected by the query execution.
	 **/
	private static function insertData(PDO $pdo, string $table, array $columns, array $values = [], bool $usePrepare = false, string $msg = ''): int {
		[$cols, $vals] = self::toQueryStruct($columns, $values, '', $usePrepare);
		
		$cols = join(',', $cols);
		$vals = join(',', $vals);
		
		// build up the query string & execute as per request
		$q = "INSERT INTO $table($cols) VALUES($vals)";
		
		return $usePrepare ? self::exePrepareWith($pdo, $q, $values, $msg) : self::exeStaticWith($pdo, $q, $msg);
	}
	
	/**
	 * With introduction of Hati 5, Fluent now supports multiple db connections to be
	 * used for various database operations. To have backward compatibility with older
	 * versions, Hati can be configured to use some default db connections based on
	 * where the Hati is running (i.e. CLI, Apache Server)
	 *
	 * @return ?String The default db profile id based on the execution environment
	 * */
	private static function defaultProfileId(): ?string {
		$dbConfig = Hati::dbConfigObj();
		
		// Figure out where we are running!
		if (Util::cli()) {
			$key = 'default_cli_db';
		} elseif (str_contains(Util::host(), '://localhost')) {
			$key = 'default_test_db';
		} else {
			$key = 'default_prod_db';
		}
		
		return $dbConfig[$key] ?? null;
	}
	
	private static function buildErrMsg(string $customMsg, string $query, bool $debug, string $throwableMsg): string {
		if ($debug) {
			$query = str_replace(["\n", "\t"], '', $query);
			$message = "$throwableMsg: $query";
		} else {
			$message = empty($customMsg) ? $throwableMsg : $customMsg;
		}
		
		return $message;
	}
	
}