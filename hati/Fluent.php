<?php

namespace hati;

/**
 * Fluent is wrapper class around PDO extension to allow simple, flawless
 * easy access and manipulation of the database query operations. It uses
 * singleton pattern to permits its instance.
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
            throw new HatiError('Connection to database was failed.');
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
     * @return mixed the value defined by the key
     */
    public static function datum(string $key): mixed {
        $datum = Fluent::datumArr();
        if(!isset($datum[$key])) throw new HatiError('Invalid key given for the datum.');
        return $datum[$key];
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
     * @return void
     * */
    public static function exePrepared(string $query, array $param = []): void {
        try {
            $ins = Fluent::get();

            // clear previous data buffer and execution success flag
            $ins -> data = null;
            $ins -> executed = false;


            $db = $ins -> getDB();
            $ins -> stmtBuffer = $db -> prepare($query);
            $ins -> executed = $ins -> stmtBuffer -> execute($param);
        } catch (Throwable $t) {
            throw new HatiError($t -> getMessage());
        }
    }

    /**
     * This methods works similarly as @link exePrepared works. The only differenc
     * between them is that this method doesn't prepare the query. You should use
     * this for static query which doesn't embed any value to the query as this
     * can greatly improve the execution performance.
     *
     * @param string $query the query to be executed
     * @return void
     * */
    public static function exeStatic(string $query): void {
        try {
            $ins = Fluent::get();

            // clear previous data buffer and execution success flag
            $ins -> data = null;
            $ins -> executed = false;

            $db = $ins -> getDB();
            $ins -> stmtBuffer = $db -> query($query);
            $ins -> executed = $ins -> stmtBuffer != false;
        } catch (Throwable $t) {
            throw new HatiError($t -> getMessage());
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
        if ($ins == null || !$ins -> stmtBuffer)
            throw new HatiError('PDOStatement was failed to be obtained as encountered error in query preparation.');
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
        if (!$ins -> executed) throw new HatiError('Failed to count as no query has been executed.');
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
     * @return array it returns the array containing the result set of the query
     */
    public static function datumArr(): array {
        if (!is_array(Fluent::get() -> dataArr()) || count(Fluent::get() -> dataArr()) == 0)
            throw new HatiError("Data don't have any datum array.");

        $datumArr = Fluent::get() -> dataArr()[0];
        if ($datumArr == null || count($datumArr) == 0)
            throw new HatiError('Datum array is empty or null.');
        return $datumArr;
    }

    /**
     * this method firstly use the @link dataObj method to get the data as
     * an array of php std class out of the database using PDO fetchAll method.
     * Then it only gets/access the first element of the array as this method is
     * used to get one single result set object of the query.
     *
     * @return stdClass it returns the object containing the result set of the query
     */
    public static function datumObj(): stdClass {
        if (!is_array(Fluent::get() -> dataObj()) || count(Fluent::get() -> dataObj()) == 0)
            throw new HatiError('Data don\'t have any datum array.');

        $datumObj = Fluent::get() -> dataObj()[0];
        if ($datumObj == null)
            throw new HatiError('Datum object is empty or null.');
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
        if (!$ins -> executed) throw new HatiError('No query has been executed.');
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
        if (!$ins -> executed) throw new HatiError('No query has been executed.');
        $buffer = $ins -> stmtBuffer;
        if ($ins -> data == null) $ins -> data = $buffer -> fetchAll(PDO::FETCH_OBJ);
        return $ins -> data;
    }

}