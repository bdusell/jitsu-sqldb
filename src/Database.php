<?php

namespace Jitsu\Sql;

/**
 * An object-oriented interface to a SQL database.
 *
 * This is essentially a useful wrapper around the PDO library.
 */
abstract class Database {

	private $conn = null;
	private $mode = \PDO::FETCH_OBJ;

	/**
	 * Connect to a database upon construction.
	 *
	 * @param string $driver_str A PDO driver string.
	 * @param string|null $username An optional username.
	 * @param string|null $password An optional password.
	 * @param array $options An optional array of PDO options.
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function __construct(
		$driver_str,
		$username = null,
		$password = null,
		$options = array()
	) {
		try {
			$this->conn = new \PDO(
				$driver_str,
				$username,
				$password,
				array(
					/* Check error codes and throw our own
					* `Error` exceptions. */
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT
				) + $options
			);
		} catch(\PDOException $e) {
			/* The PDO constructor always throws an exception on
			 * error. */
			self::_exceptionError('database connection failed', $e);
		}
	}

	/**
	 * Execute a SQL query.
	 *
	 * Executes a one-shot query and returns the resulting rows in an
	 * iterable `Statement` object. The remaining parameters may be used to
	 * pass arguments to the query. If there is only a single array passed
	 * as an additional argument, its contents are used as the parameters.
	 *
	 * For example,
	 *
	 *     $stmt = $db->query($sql_code);
	 *     $stmt = $db->query($sql_code, $arg1, $arg2, ...);
	 *     $stmt = $db->query($sql_code, $arg_array);
	 *     foreach($stmt as $row) { $row->column_name ... }
	 *
	 * @param string $query The SQL query.
	 * @param mixed $arg,... Arguments to be interpolated into the query.
	 * @return \Jitsu\Sql\Statement
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function query(/* $query, [ $args | $arg1, $arg2, ... ] */) {
		self::_normalizeArgs(func_get_args(), $query, $args);
		return $this->queryWith($query, $args);
	}

	/**
	 * Same as `query`, but arguments are always passed in a single `$args`
	 * array.
	 *
	 * @param string $query
	 * @param array $args
	 * @return \Jitsu\Sql\Statement
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function queryWith($query, $args) {
		if($args) {
			/* If there are arguments, prepare the statement and
			 * execute it. */
			$stmt = $this->prepare($query);
			$stmt->assignWith($args);
			$stmt->execute();
			return $stmt;
		} else {
			/* Otherwise, use the one-shot query method, without
			 * using a prepared statement. */
			if(($result = $this->conn->query($query)) === false) {
				$this->_resultError('unable to execute SQL query', $query);
			}
			return $this->_wrapStatement($result);
		}
	}

	/**
	 * Return the first row of a query and ignore the rest.
	 *
	 * @param string $query
	 * @param mixed $arg,...
	 * @return \Jitsu\Sql\Statement
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function row() {
		self::_normalizeArgs(func_get_args(), $query, $args);
		return $this->rowWith($query, $args);
	}

	/**
	 * @param string $query
	 * @param array $args
	 * @return \Jitsu\Sql\Statement
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function rowWith($query, $args) {
		return self::queryWith($query, $args)->first();
	}

	/**
	 * Return the first column of the first row and ignore everything
	 * else.
	 *
	 * @param string $query
	 * @param mixed $arg,...
	 * @return \Jitsu\Sql\Statement
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function evaluate() {
		self::_normalizeArgs(func_get_args(), $query, $args);
		return $this->evaluateWith($query, $args);
	}

	/**
	 * @param string $query
	 * @param array $args
	 * @return \Jitsu\Sql\Statement
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function evaluateWith($query, $args) {
		return $this->queryWith($query, $args)->value();
	}

	/**
	 * Execute a SQL statement.
	 *
	 * If called with arguments, returns a `Statement`. Note that the
	 * number of affected rows is available via
	 * `Statement->affectedRows()`. If called with no arguments,
	 * returns a `StatementStub` object instead, which provides only the
	 * `affectedRows()` method.
	 *
	 * @param string $query
	 * @param mixed $arg,...
	 * @return \Jitsu\Sql\QueryResultInterface
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function execute() {
		self::_normalizeArgs(func_get_args(), $statement, $args);
		return $this->executeWith($statement, $args);
	}

	/**
	 * @param string $query
	 * @param array $args
	 * @return \Jitsu\Sql\QueryResultInterface
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function executeWith($statement, $args) {
		if($args) {
			/* If there are arguments, prepare the statement and
			 * execute it. */
			return $this->queryWith($statement, $args);
		} else {
			/* Otherwise, use the one-shot exec method, without
			 * using a prepared statement. */
			if(($result = $this->conn->exec($statement)) === false) {
				$this->_resultError('unable to execute SQL statement', $statement);
			}
			return new StatementStub($result);
		}
	}

	/**
	 * Prepare a SQL statement and return it as a `Statement`.
	 *
	 * @param string $statement
	 * @return \Jitsu\Sql\Statement
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function prepare($statement) {
		if(($result = $this->conn->prepare($statement)) === false) {
			$this->_resultError('unable to prepare statement', $statement);
		}
		return $this->_wrapStatement($result);
	}

	/**
	 * Escape and quote a string value for interpolation in a SQL query.
	 *
	 * Note that the result *includes* quotes added around the string.
	 *
	 * @param string $s
	 * @return string
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function quote($s) {
		if(($result = $this->conn->quote($s)) === false) {
			$this->_resultError('driver does not implement string quoting');
		}
		return $result;
	}

	/**
	 * Escape characters in a string that have special meaning in SQL
	 * "like" patterns. Note that this should be coupled with an `ESCAPE`
	 * clause in the SQL; for example,
	 *
	 *     "column" LIKE '%foo\%bar%' ESCAPE '\'
	 *
	 * A `\` is the default escape character.
	 *
	 * @param string $s
	 * @param string $esc
	 * @return string
	 */
	public static function escapeLike($s, $esc = '\\') {
		return str_replace(
			array('%', '_'),
			array($esc . '%', $esc . '_'),
			$s
		);
	}

	/**
	 * Get the id of the last inserted record.
	 *
	 * *Note that the result is always a string*.
	 *
	 * @return string
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function lastInsertId() {
		$result = $this->conn->lastInsertId();
		if($this->conn->errorCode() === 'IM001') {
			$this->_resultError('driver does not support getting last insert ID');
		}
		return $result;
	}

	/**
	 * Begin a transaction.
	 *
	 * Note that uncommitted transactions are automatically rolled back
	 * when the script terminates.
	 *
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function begin() {
		try {
			$r = $this->conn->beginTransaction();
		} catch(\PDOException $e) {
			$this->_exceptionError('database does not support transactions');
		}
		if(!$r) {
			$this->_resultError('unable to begin transaction');
		}
	}

	/**
	 * Determine whether a transaction is active.
	 *
	 * @return bool
	 */
	public function inTransaction() {
		return $this->conn->inTransaction();
	}

	/**
	 * Roll back the current transaction.
	 *
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function rollback() {
		try {
			$r = $this->conn->rollBack();
		} catch(\PDOException $e) {
			self::_exceptionError('unable to roll back transaction because no transaction is active', $e);
		}
		if(!$r) {
			$this->_resultError('unable to roll back transaction');
		}
	}

	/**
	 * Commit the current transaction.
	 *
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function commit() {
		if(!$this->conn->commit()) {
			$this->_resultError('unable to commit transaction');
		}
	}

	/**
	 * Run a callback safely in a transaction.
	 *
	 * If the callback throws an exception, the transaction will be rolled
	 * back, and the exception will be re-thrown.
	 *
	 * @param callable $callback
	 * @throws \Exception
	 */
	public function transaction($callback) {
		$this->begin();
		try {
			call_user_func($callback);
		} catch(\Exception $e) {
			$this->rollback();
			throw $e;
		}
		$this->commit();
	}

	// Database connection attributes
	private static $attrs = array(
		'autocommit',
		'case',
		'client_version',
		'connection_status',
		'driver_name',
		'errmode',
		'oracle_nulls',
		'persistent',
		'prefetch',
		'server_info',
		'server_version',
		'timeout'
	);

	/**
	 * Get a database connection attribute.
	 *
	 * The name passed should be a string (case-insensitive) and
	 * correspond to a PDO constant with the `PDO::ATTR_` prefix
	 * dropped.
	 *
	 * @param string $name
	 * @return mixed
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function attribute($name) {
		if(($result = $this->conn->getAttribute(self::_attrValue($name))) === null) {
			$this->_resultError("unable to get attribute '$name'");
		}
		return $result;
	}

	/**
	 * Set a database connection attribute.
	 *
	 * Uses the same attribute name convention as `attribute()`. The value
	 * should be a string (case-insensitive) corresponding to a PDO
	 * constant with the `PDO::` prefix dropped.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function setAttribute($name, $value) {
		if(!$this->conn->setAttribute(self::_attrValue($name), constant('PDO::' . strtoupper($value)))) {
			$this->_resultError("unable to set attribute '$name' to '$value'");
		}
	}

	/**
	 * Generate a mapping of all attribute names and values.
	 *
	 * @return array
	 * @throws \Jitsu\Sql\DatabaseException
	 */
	public function attributes() {
		$result = array();
		foreach(self::$attrs as $attr) {
			$result[$attr] = $this->attribute($attr);
		}
		return $result;
	}

	/**
	 * Get a list of the available database drivers.
	 *
	 * @return string[]
	 */
	public static function drivers() {
		return \PDO::getAvailableDrivers();
	}

	/**
	 * Get the underlying PDO connection object.
	 *
	 * @return \PDO
	 */
	public function connection() {
		return $this->conn;
	}

	/**
	 * Set the fetch mode.
	 *
	 * The fetch mode determines the form in which rows are fetched. Use
	 * the `PDO::FETCH_` constants directly. The default, `PDO::FETCH_OBJ`,
	 * causes rows to be returned as `stdClass` objects with property
	 * names corresponding to column names.
	 *
	 * @param mixed $mode A `PDO::FETCH_` constant.
	 */
	public function setFetchMode($mode) {
		$this->mode = $mode;
	}

	/**
	 * Get the fetch mode.
	 *
	 * @return mixed
	 */
	public function fetchMode($mode) {
		return $this->mode;
	}

	// Parse the argument list to a method.
	private static function _normalizeArgs($args, &$query, &$sql_args) {
		$query = array_shift($args);
		if(count($args) === 1 && is_array($args[0])) {
			$sql_args = $args[0];
		} else {
			$sql_args = $args;
		}
	}

	// Raise an error based on a false return value.
	private function _resultError($msg, $sql = null) {
		list($state, $code, $errstr) = $this->conn->errorInfo();
		self::_raiseError($msg, $errstr, $code, $state, $sql);
	}

	// Raise an error based on a caught exception.
	private static function _exceptionError($msg, $e, $sql = null) {
		self::_raiseError($msg, $e->getMessage(), $e->getCode(), null, $sql);
	}

	// Raise an error.
	private static function _raiseError($msg, $errstr, $code = null, $state = null, $sql = null) {
		throw new DatabaseException("$msg: $errstr", $errstr, $code, $state, $sql);
	}

	// Convert an attribute name to its integer constant.
	private static function _attrValue($name) {
		return constant('PDO::ATTR_' . strtoupper($name));
	}

	// Wrap a statement.
	private function _wrapStatement($stmt) {
		return new Statement($stmt, $this->mode);
	}
}
