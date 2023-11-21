<?php /** @noinspection SqlNoDataSourceInspection */

namespace hati\fluent;

use hati\Hati;
use hati\trunk\TrunkErr;
use hati\Util;
use PDO;
use PDOStatement;
use RuntimeException;
use stdClass;
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
 * the database and a query has already been executed. Currently it has three
 * data returning methods such as:<br>
 * - {@link Fluent::dataArr()}   : returns the array containing array of the result/rows
 * - {@link Fluent::datumArr()}  : returns first array of the data
 * - {@link Fluent::datum()}     : returns specific property of the datumArr
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

	// track which profile id in use
	private ?string $profileId;

	// a Fluent instance for singleton pattern
	private static ?Fluent $INS = null;

	private function __construct() {
		$this -> dbMan = new DBMan();
		$this -> profileId = self::defaultProfileId();
	}

	/**
	 * With Hati 5, multiple database connections can be used. Using this method,
	 * the current in-use db profile id can be fetched. See {@link Fluent::use()}
	 * for more details.
	 *
	 * @return ?string The current database profile id in use
	 * */
	public static function currentDBId(): ?string {
		return self::get() -> profileId;
	}

	/**
	 * Show sql which runs into SQL error in to debug
	 * */
	public static function debugSQL(): void {
		$ins = self::get();
		$ins -> debugSql = true;
	}

	/**
	 * Since Hati 5, Fluent can work with multiple database connection profiles.
	 *  Database connections are specified by objects in the <b>hati/db.json</b>
	 *  file where each object is identified by their profile names.
	 *
	 *  A db profile can have many database. Connection to each database is
	 *  represented by id. Id naming has a strict convention. It should be composed
	 *  of database profile name followed by colon, followed by the database name it
	 *  connects to. For example, to represent a connection to database test2 on
	 *  localhost, using root @ pass, the profile id would be:
	 *  <b>Example-Localhost:test2</b>
	 *  <br>
	 *  <code>
	 * {
	 * 	"db_profiles": {
	 * 		"Example-Localhost": {
	 * 			"address": "localhost",
	 * 			"username": "root",
	 * 			"password": "pass",
	 * 			"db": ["test1","test2"]
	 * 		}
	 * 	}
	 * }
	 *  </code>
	 * Note: Use "<b>hati/tool/dumpdb.php</b>" tool to generate these profile ids
	 * automatically for your project's db.json file.
	 *
	 * @param string $dbProfile The db profile id
	 * @return ?PDO  The pdo connection object to the database
	 * */
	public static function use(string $dbProfile): ?PDO {
		$ins = self::get();

		$ins -> profileId = $dbProfile;
		$ins -> db = $ins -> dbMan -> connect($dbProfile);

		return $ins -> db;
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
	 * Using datum method on Fluent object, any single piece of information or
	 * in other words, any property of the result set array of query execution
	 * can be obtained.
	 *
	 * This methods checks whether the key is present in the result set array
	 * before returning.
	 *
	 * @param $key string the key for the value
	 * @param $defVal mixed the value to be returned when the key is
	 * not set in the result set.
	 *
	 * @return mixed the value defined by the key
	 */
	public static function datum(string $key, mixed $defVal = null): mixed {
		$datum = Fluent::datumArr();
		return $datum[$key] ?? $defVal;
	}

	/**
	 * This method prints the column value defined by the key in the first row
	 * of the query result set. Optional default value is printed when the it
	 * can't find the column name in the result set.
	 *
	 * @param $key string the key for the value
	 * @param $defVal mixed the value to be returned when the key is
	 * not set in the result set.
	 *
	 * @return void
	 * */
	public static function echo(string $key, mixed $defVal = ''): void {
		echo self::datum($key, $defVal);
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
			$ins -> data = null;
			$ins -> executed = false;

			$ins -> stmtBuffer = $pdo -> prepare($query);
			$ins -> executed = $ins -> stmtBuffer -> execute($param);
			return $ins -> stmtBuffer -> rowCount();
		} catch (Throwable $t) {
			$message = self::buildErrMsg($msg, $query, $ins -> debugSql, $t -> getMessage());
			throw new TrunkErr($message);
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

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::exePrepareWith($ins -> db, $query, $param, $msg);
	}

	/**
	 * This methods works similarly as {@link Fluent::exePrepare} works. The only difference
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
			$ins -> data = null;
			$ins -> executed = false;

			$ins -> stmtBuffer = $pdo -> query($query);
			$ins -> executed = $ins -> stmtBuffer != false;
			return $ins -> stmtBuffer -> rowCount();
		} catch (Throwable $t) {
			$message = self::buildErrMsg($msg, $query, $ins -> debugSql, $t -> getMessage());
			throw new TrunkErr($message);
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

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::exeStaticWith($ins -> db, $query, $msg);
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

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::insertData($ins -> db, $table, $columns, $values, true, $msg);
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

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::insertData($ins -> db, $table, $columns, $values, false, $msg);
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
	 **@throws RuntimeException It throws run time exception when number cols-values or where-whereValue pair don't
	 * match
	 */
	public static function update(string $table, array $cols, array $values = [], string $where = '', array $whereValues = [], string $msg = ''): int {
		$ins = self::get();

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::updateData($ins -> db, $table, $cols, $values, false, $where, $whereValues, $msg);
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

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::updateData($ins -> db, $table, $cols, $values, true, $where, $whereValues,  $msg);
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

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::deleteData($ins -> db, $table, $where, $whereValues, false, $msg);
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

		if (is_null($ins -> db)) {
			$ins -> db = $ins -> dbMan -> connect(self::defaultProfileId());
		}

		return self::deleteData($ins -> db, $table, $where, $whereValues, true, $msg);
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
		if (!$ins -> stmtBuffer)
			throw new TrunkErr('PDOStatement was failed to be obtained as encountered error in query preparation.');
		return $ins -> stmtBuffer;
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
	 * of a query. Before counting it assesses whether there has been any query
	 * executed. If not, then throws runtime exception of HatiError.
	 *
	 * @return int number of rows/result was affected by the query.
	 */
	public static function rowCount(): int {
		$ins = self::get();
		if (!$ins -> executed) throw new TrunkErr('Failed to count as no query has been executed.');
		return $ins -> stmtBuffer -> rowCount();
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
		$array = Fluent::datumArr();
		foreach ($array as $value)  {
			$count = $value;
			break;
		}
		return $count;
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
	 * This method returns the id of the last inserted row by the query.
	 *
	 * @return int returns the last inserted row of last sql query
	 */
	public static function lastInsertRowId(): int {
		return self::getPDO() -> lastInsertId();
	}

	/**
	 * By using this method, you can get the PDO handler object to perform
	 * various database query on demand, so that you have no limitation by
	 * this fluent class.
	 *
	 * @return ?PDO a PDO handler object is returned.
	 */
	public static function getPDO(): ?PDO {
		return self::get() -> db;
	}

	/**
	 * this method firstly use the @link dataArrr method to get the data as
	 * associative array out of the database using PDO fetchAll method. then
	 * it only gets/access the first element of the array as this method is
	 * used to get one single array result set object of the query.
	 *
	 * @param bool $throwErr indicates whether to throw error
	 *
	 * @return array it returns the array containing the result set of the query
	 */
	public static function datumArr(bool $throwErr = false): array {
		$dataArr = self::get() -> dataArr();
		if (count($dataArr) == 0) {
			if ($throwErr) throw new TrunkErr("Data don't have any datum array.");
			else return [];
		}

		$datumArr = $dataArr[0];
		if ($datumArr == null || count($datumArr) == 0) {
			if ($throwErr) throw new TrunkErr('Datum array is empty or null.');
			else return [];
		}

		return $datumArr;
	}

	/**
	 * this method firstly use the @link dataObj method to get the data as
	 * an array of php std class out of the database using PDO fetchAll method.
	 * Then it only gets/access the first element of the array as this method is
	 * used to get one single result set object of the query.
	 *
	 * @param bool $throwErr indicates whether to throw error
	 *
	 * @return stdClass it returns the object containing the result set of the query
	 */
	public static function datumObj(bool $throwErr = false): stdClass {
		$dataObj = self::get() -> dataObj();
		if (count($dataObj) == 0) {
			if ($throwErr) throw new TrunkErr('Data don\'t have any datum array.');
			else return new stdClass();
		}

		$datumObj = $dataObj[0];
		if ($datumObj == null) {
			if ($throwErr) throw new TrunkErr('Datum object is empty or null.');
			else return new stdClass();
		}

		return $datumObj;
	}

	/**
	 * dataArr method will first get the Fluent instance by call get() method
	 * then it checks for the flag whether any query has already been executed.
	 * if not, then it throws a runtime exception of HatiError. otherwise it fetch
	 * the data as associative array from the result set using PDO fetchAll method.
	 *
	 * @return array the array containing the data
	 */
	public static function dataArr(): array {
		$ins = self::get();
		if (!$ins -> executed) throw new TrunkErr('No query has been executed.');
		$buffer = $ins -> stmtBuffer;
		if ($ins -> data == null) $ins -> data = $buffer -> fetchAll(PDO::FETCH_ASSOC);
		return $ins -> data;
	}

	/**
	 * dataObj method will first get the Fluent instance by call get() method
	 * then it checks for the flag whether any query has already been executed.
	 * if not, then it throws a runtime exception of HatiError. otherwise it fetch
	 * the data inside an array as php object from the result set using PDO fetchAll
	 * method.
	 *
	 * @return array the array containing the data
	 */
	public static function dataObj(): array {
		$ins = self::get();
		if (!$ins -> executed) throw new TrunkErr('No query has been executed.');
		$buffer = $ins -> stmtBuffer;
		if ($ins -> data == null) $ins -> data = $buffer -> fetchAll(PDO::FETCH_OBJ);
		return $ins -> data;
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
			$ins -> db -> beginTransaction();
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
		$db = self::get() -> db;
		return $db -> inTransaction() && $db -> rollback();
	}

	/**
	 * Any changes made during transaction is written off to the database
	 * using commit method. This function save from throwing exception by
	 * first checking whether the database is in any active transaction mode.
	 *
	 * @return true if it can commit changes to the database; false otherwise.
	 * */
	public static function commit(): bool {
		$db = self::get() -> db;
		return $db -> inTransaction() && $db -> commit();
	}

	/**
	 * Replace ? mark with bind values array. if it is for non-prepare statement the values get directly added
	 * as part of the query, otherwise left out with ? mark so that it can be uses as prepare statement.
	 *
	 * @param bool $usePrepare Indicates whether the calculated returned query be used for prepare statement
	 * @param string $query The query where this binding operation to be performed
	 * @param array $values Values for those ? mark
	 * @param string $errMsg Any message in case there is mismatch between number of values and ? marks
	 *
	 * @throws RuntimeException If there is a mismatch between number of values and ? marks
	 * @return string Completed query where ? are either replaced with values or left out as is based on $usePrepare
	 * flag
	 **/
	public static function bind(bool $usePrepare, string $query, array $values, string $errMsg): string {
		if (substr_count($query, '?') !== count($values)) {
			throw new RuntimeException($errMsg);
		}

		$i = 0;
		return preg_replace_callback('/\?/', function () use ($usePrepare, $values, &$i) {
			return $usePrepare ? '?' : self::typedValue($values[$i++]);
		}, $query);
	}

	/**
	 * For a column value, it adds appropriate single quotes to be query friendly
	 * */
	private static function typedValue($val): int|string {
		if (is_string($val)) return "'$val'";
		else if (is_object($val)) return "'{$val->__toSting()}'";
		return $val;
	}

	/**
	 * This method breaks the key-value pairs into SQL syntax like fragments so that they
	 * can easily be processed by insert/update/delete methods.
	 *
	 * @param array $columns The list of columns
	 * @param array $values The values for those columns
	 * @param string $sign any extra separator between column-value such as = for update query
	 * @param bool $usePrepare Indicates whether the values needs to be marked resolved after the sign directly
	 * or be left with ? mark so that it can easily be plugged into exePrepare method.
	 *
	 * @throws RuntimeException When there is a mismatch between number of column-values pair combination
	 * @return array Containing two items, first one for the column values and the second one including values with
	 * any specified sign in front such as = ?, = 'X_VALUE'
	 * */
	private static function toQueryStruct(array $columns, array $values, string $sign, bool $usePrepare): array {
		$cols = '';
		$val = '';

		foreach ($columns as $c => $v) {
			if (is_integer($c)) {
				$cols .= "$v, ";
				$val .= "$sign?, ";
			} else {
				$cols .= "$c, ";
				$v = self::typedValue($v);
				$val .= "$sign$v, ";
			}
		}

		// Remove the extra ', ' from the end of both cols, values
		$cols = substr($cols, 0, strlen($cols) - 2);
		$val = substr($val, 0, strlen($val) - 2);

		$val = self::bind($usePrepare, $val, $values, 'The number of values for columns that are missing values did not match');

		return [$cols, $val];
	}

	/**
	 * This method performs the update operation based on the argument values.
	 *
	 * @param string $table The table name where this update operation is going to be performed
	 * @param array $columns Array containing column-values pari. Values can be left out. Missing values will be picked
	 * up from the values array.
	 * @param array $values Values for the columns.
	 * @param bool $usePrepare Indicates to whether use the prepare statement for the query. This method does all the
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
		list($cols, $val) = self::toQueryStruct($columns, $values, ' = ', $usePrepare);
		$cols = explode(',', $cols);
		$val = explode(',', $val);

		$sets = '';
		foreach ($cols as $i => $v) {
			$sets .= "$v$val[$i], ";
		}
		$sets = substr($sets, 0, strlen($sets) - 2);

		$q = "UPDATE $table SET $sets";

		if (!empty($where)) {
			$w = self::bind($usePrepare, $where, $whereVal, 'The number of values and where clause columns requiring values do not match');
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
	 * can also be left out with ? mark so that the values can fetched from the whereValues.
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
			$w = self::bind($usePrepare, $where, $whereValues, "Number of values passed for where clause don't match");
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
		[$cols, $val] = self::toQueryStruct($columns, $values, '', $usePrepare);

		// build up the query string & execute as per request
		$q = "INSERT INTO $table($cols) VALUES($val)";
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
			$message = "$throwableMsg: $query";
		} else {
			$message = empty($customMsg) ? $throwableMsg : $customMsg;
		}

		return $message;
	}

}