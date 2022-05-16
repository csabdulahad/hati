<?php

namespace hati;

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
 * data returning methods such as
 *      - dataArr   : returns the array containing array of the result/rows
 *      - datumArr  : returns first array of the data
 *      - datum     : returns specific property of the datumArr
 *
 * For better security and practice, it is recommended that call to any Fluent
 * method should be inside try-catch block to hide the throwing error message
 * or reactive to any error.
 *
 * */

use hati\trunk\TrunkErr;
use PDO;
use PDOStatement;
use stdClass;
use Throwable;

class Fluent {

    // it holds the PDO object handler for the connection
    private ?PDO $db = null;

    // indicates whether any query has already been executed
    private bool $executed = false;

    // an internal buffer; used to cache the result set of the query
    // helping to avoid iterator offset outbound exception
    private mixed $stmtBuffer = null;

    // holds the actual result set array of the query
    private mixed $data = null;

    // a Fluent instance for singleton pattern
    private static ?Fluent $INS = null;

    /**
     * The constructor method tries to establish a connection to the database
     * as specified by the DBMeta object under a specific namespace. The
     * namespace has to be data\DBMeta, and of type FluentConfig.
     *
     * Any error will be throw if happens while establishing the connection.
     */
    private function __construct() {
        try {
            $host = Hati::dbHost();
            $db = Hati::dbName();
            $user = Hati::dbUsername();
            $pass = Hati::dbPassword();

            $arg = "mysql:host=$host;dbname=$db;charset=utf8";
            $this -> db = new PDO($arg, $user, $pass);
            $this -> db -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Throwable) {
            throw new TrunkErr('Connection to database was failed.');
        }
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
     * @param $defaultValue mixed the value to be  returned when the key is
     * not set in the result set.
     *
     * @return mixed the value defined by the key
     */
    public static function datum(string $key, mixed $defaultValue = null): mixed {
        $datum = Fluent::datumArr();
        return $datum[$key] ?? $defaultValue;
    }

    /**
     * Fluent's execute method is more powerful at executing any prepared statement.
     * For a given query, it prepares the query then binds it on runtime using PDO
     * execute method. After execution, it remembers the output into a variable called
     * buffer internally.
     *
     * You should use this method if you embed external values to the query to avoid many
     * possible SQL injections.
     *
     * @param string $query the query to be executed
     * @param array $param array containing binding values to the query
     * @param string $msg any custom message to replace default system error message
     *
     * @return int indicates how many rows were affected by the query execution.
     * */
    public static function exePrepare(string $query, array $param = [], string $msg = ''): int {
        try {
            $ins = Fluent::get();

            // clear previous data buffer and execution success flag
            $ins -> data = null;
            $ins -> executed = false;


            $db = $ins -> getDB();
            $ins -> stmtBuffer = $db -> prepare($query);
            $ins -> executed = $ins -> stmtBuffer -> execute($param);
            return $ins -> stmtBuffer -> rowCount();
        } catch (Throwable $t) {
            $message = empty($msg) ? $t -> getMessage() : $msg;
            throw new TrunkErr($message);
        }
    }

    /**
     * This methods works similarly as @link exePrepare works. The only differenc
     * between them is that this method doesn't prepare the query. You should use
     * this for static query which doesn't embed any value to the query as this
     * can greatly improve the execution performance.
     *
     * @param string $query the query to be executed
     * @param string $msg any custom message to replace default system error message
     *
     * @return int indicates how many rows were affected by the query execution.
     * */
    public static function exeStatic(string $query, string $msg = ''): int {
        try {
            $ins = Fluent::get();

            // clear previous data buffer and execution success flag
            $ins -> data = null;
            $ins -> executed = false;

            $db = $ins -> getDB();
            $ins -> stmtBuffer = $db -> query($query);
            $ins -> executed = $ins -> stmtBuffer != false;
            return $ins -> stmtBuffer -> rowCount();
        } catch (Throwable $t) {
            $message = empty($msg) ? $t -> getMessage() : $msg;
            throw new TrunkErr($message);
        }
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
        $ins = Fluent::get();
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
        $ins = Fluent::get();
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
        foreach ($array as  $value)  {
            $count = $value;
            break;
        }
        return $count;
    }

    /**
     * In order to get a Fluent object, it needs a dependency of type FluentConfig
     * under exact namespace "data\DBMeta". From this it gets information about
     * the database that you want it to connect to.
     *
     * It throws runtime exception of HatiError if the dependency is not present at
     * the specific location or the configuration class isn't of type FluentConfig.
     * However, upon getting the right class it initializes a connection using the
     * information provided.
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
        return Fluent::getDB() -> lastInsertId();
    }

    /**
     * By using this method, you can get the PDO handler object to perform
     * various database query on demand, so that you have no limitation by
     * this fluent class.
     *
     * @return PDO a PDO handler object is returned.
     */
    public static function getDB(): PDO {
        return Fluent::get() -> db;
    }

    /**
     * this method firstly use the @link dataArrr method to get the data as
     * associative array out of the database using PDO fetchAll method. then
     * it only gets/access the first element of the array as this method is
     * used to get one single array result set object of the query.
     *
     * @param bool $triggerError indicates whether to throw error
     *
     * @return array it returns the array containing the result set of the query
     */
    public static function datumArr(bool $triggerError = false): array {
        $dataArr = Fluent::get() -> dataArr();
        if (count($dataArr) == 0) {
            if ($triggerError) throw new TrunkErr("Data don't have any datum array.");
            else return [];
        }

        $datumArr = $dataArr[0];
        if ($datumArr == null || count($datumArr) == 0) {
            if ($triggerError) throw new TrunkErr('Datum array is empty or null.');
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
     * @param bool $triggerError indicates whether to throw error
     *
     * @return stdClass it returns the object containing the result set of the query
     */
    public static function datumObj(bool $triggerError = false): stdClass {
        $dataObj = Fluent::get() -> dataObj();
        if (count($dataObj) == 0) {
            if ($triggerError) throw new TrunkErr('Data don\'t have any datum array.');
            else return new stdClass();
        }

        $datumObj = $dataObj[0];
        if ($datumObj == null) {
            if ($triggerError) throw new TrunkErr('Datum object is empty or null.');
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
        $ins = Fluent::get();
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
        $ins = Fluent::get();
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
        $ins = Fluent::get();
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
        $db = Fluent::get() -> db;
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
        $db = Fluent::get() -> db;
        return $db -> inTransaction() && $db -> commit();
    }

}