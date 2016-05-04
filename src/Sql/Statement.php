<?php

namespace Jitsu\Sql;

/**
 * An object-oriented interface to a prepared or executed SQL statement.
 *
 * This is a convenient wrapper around the PDO statement class.
 */
class Statement implements \Iterator, QueryResultInterface {

	private $stmt;
	private $current;

	/**
	 * Construct a SQL statement object.
	 *
	 * Optionally specify a fetch mode, which determines the form in which
	 * rows are fetched. Use the `PDO::FETCH_` constants directly. The
	 * default is `PDO::FETCH_OBJ`, which causes rows to be returned as
	 * `stdClass` objects with property names corresponding to column
	 * names.
	 *
	 * @param \PDOStatement $stmt
	 * @param mixed $mode
	 */
	public function __construct($stmt, $mode = \PDO::FETCH_OBJ) {
		$this->stmt = $stmt;
		$this->current = null;
		$this->mode = $mode;
	}

	/**
	 * Bind a result column to a variable.
	 *
	 * The column can be 1-indexed or referenced by name.
	 *
	 *     $stmt = $db->prepare('select id, name from users');
	 *     $stmt->bind_output('name', $name);
	 *     foreach($stmt as $row) echo $name, "\n";
	 *
	 * A type may optionally be specified. The following values may be
	 * passed as strings:
	 *
	 * * `bool`
	 * * `null`
	 * * `int`
	 * * `str`
	 * * `lob` (large object)
	 *
	 * The `$inout` parameter specifies whether the column is an
	 * `INOUT` parameter for a stored procedure.
	 *
	 * @param int|string $col
	 * @param mixed $var
	 * @param string|null $type
	 * @param bool $inout
	 */
	public function bindOutput($col, &$var, $type = null, $inout = false) {
		if($type === null) {
			$result = $this->stmt->bindColumn($col, $var);
		} else {
			$result = $this->stmt->bindColumn($col, $var, self::_typeValue($type, $inout));
		}
		if(!$result) {
			$this->_raiseError("unable to bind variable to column '$col'");
		}
	}

	/**
	 * Bind an input parameter of a prepared statement to a variable.
	 *
	 * The parameter can be 1-indexed or referenced by name (include the
	 * colon).
	 *
	 * Example 1:
	 *
	 *     $stmt = $db->prepare('select id, name from users where phone = ?');
	 *     $stmt->bind_input(1, $phone);
	 *     $phone = '5551234567';
	 *     $stmt->execute();
	 *
	 * Example 2:
	 *
	 *     $stmt = $db->prepare('select id, name from users where phone = :phone');
	 *     $stmt->bind_input(':phone', $phone);
	 *
	 * @param int|string $param
	 * @param mixed $var
	 * @param string|null $type
	 * @param bool $inout
	 */
	public function bindInput($param, &$var, $type = null, $inout = false) {
		if($type === null) {
			$result = $this->stmt->bindParam($param, $var);
		} else {
			$result = $this->stmt->bindParam($param, $var, self::_typeValue($type, $inout));
		}
		if(!$result) {
			$this->_raiseError("unable to bind variable to prepared statement parameter '$param'");
		}
	}

	/**
	 * Assign a value to an input parameter of a prepared statement.
	 *
	 * If `$type` is null, the appropriate type is used based on the PHP
	 * type of the value.
	 *
	 * @param int|string $param
	 * @param mixed $value
	 * @param string|null $type
	 * @param bool $type
	 */
	public function assignInput($param, $value, $type = null, $inout = false) {
		if($type === null) {
			$result = $this->stmt->bindValue($param, $value, self::_intuitType($value));
		} else {
			$result = $this->stmt->bindValue($param, $value, self::_typeValue($type, $inout));
		}
		if(!$result) {
			$this->_raiseError("unable to assign value to prepared statement parameter '$param'");
		}
	}

	/**
	 * Assign an array of values to the inputs of a prepared statement.
	 *
	 * For named parameters, pass an array mapping names (no colons) to
	 * values. For positional parameters, pass a sequential array
	 * (0-indexed). A positional parameter array may be passed as a single
	 * array argument or as a variadic argument list.
	 *
	 *     $stmt = $db->prepare('select * from users where first = ? and last = ?');
	 *     $stmt->assign('John', 'Doe');
	 *
	 * @param mixed $values,...
	 */
	public function assign(/* $values,... */) {
		$this->assignWith(
			func_num_args() > 1 || !is_array(func_get_arg(0)) ?
			func_get_args() :
			func_get_arg(0)
		);
	}

	/**
	 * Like `assign`, but arguments are always passed in an array.
	 *
	 * @param array $values
	 */
	public function assignWith($values) {
		if($values) {
			if(array_key_exists(0, $values)) {
				foreach($values as $i => $value) {
					$this->assignInput($i + 1, $value);
				}
			} else {
				foreach($values as $name => $value) {
					$this->assignInput(':' . $name, $value);
				}
			}
		}
	}

	/**
	 * Execute this statement, optionally providing a 0-indexed array of
	 * values.
	 *
	 * The values array may be convenient in simple cases, but it only
	 * works for positional parameters and casts all values to the SQL
	 * string type (`'str'`). A more flexible alternative is to call
	 * `assign` beforehand.
	 *
	 * @param array|null $values
	 */
	public function execute($values = null) {
		if(!($values === null ? $this->stmt->execute() : $this->stmt->execute($values))) {
			$this->_raiseError('unable to execute prepared statement');
		}
	}

	/**
	 * Equivalent to running `$this->execute()` and returning
	 * `$this->value()`.
	 *
	 * @param array|null $values
	 * @return mixed
	 */
	public function evaluate($values = null) {
		$this->execute($values);
		return $this->value();
	}

	/**
	 * Ignore the rest of the records returned by this statement so as to
	 * make it executable again.
	 */
	public function close() {
		if(!$this->stmt->closeCursor()) {
			$this->_raiseError('unable to close cursor');
		}
	}

	/**
	 * Return the first row and ignore the rest.
	 *
	 * If there are no records returned, return `null`.
	 *
	 * Note that this won't work with some fetch modes.
	 *
	 * @return mixed
	 */
	public function first() {
		foreach($this as $row) {
			$this->close();
			return $row;
		}
		return null;
	}

	/**
	 * Return the first cell of the first row and ignore anything else.
	 *
	 * If no records are returned, return `null`.
	 *
	 * @return mixed
	 */
	public function value() {
		$result = $this->stmt->fetchColumn();
		if($result === false) return null;
		$this->close();
		return $result;
	}

	/**
	 * Return the number of columns in the result set, or 0 if there is no
	 * result set.
	 *
	 * @return int
	 */
	public function columnCount() {
		return $this->stmt->columnCount();
	}

	/**
	 * Return the number of rows affected by the last execution of this
	 * statement.
	 *
	 * @return int
	 */
	public function affectedRows() {
		return $this->stmt->rowCount();
	}

	/**
	 * Return all of the rows copied into an array.
	 *
	 * @return mixed[]
	 */
	public function toArray() {
		return $this->stmt->fetchAll($this->mode);
	}

	/**
	 * Return all of the rows passed through a function and copied into an
	 * array.
	 *
	 * The columns of each row are passed as positional parameters to the
	 * function.
	 *
	 * @param callable $callback
	 * @return mixed[]
	 */
	public function map($callback) {
		return $this->stmt->fetchAll(\PDO::FETCH_FUNC, $callback);
	}

	/**
	 * Advance to the next set of rows returned by the query, which is
	 * supported by some stored procedures.
	 */
	public function nextRowset() {
		if(!$this->stmt->nextRowset()) {
			$this->_raiseError('unable to advance to next rowset');
		}
	}

	public function current() {
		return $this->current;
	}

	public function key() {
		return null;
	}

	public function next() {
		$this->current = $this->stmt->fetch($this->mode);
	}

	public function rewind() {
		// Called before the first iteration
		if($this->current === null) {
			// Pre-load the first row
			$this->next();
		} else {
			// Rewind the cursor to the beginning
			$this->current = $this->stmt->fetch($this->mode, \PDO::FETCH_ORI_ABS, 0);
		}
	}

	public function valid() {
		return $this->current !== false;
	}

	/**
	 * Print debugging information to *stdout*.
	 *
	 * @return $this
	 */
	public function debug() {
		$this->stmt->debugDumpParams();
		return $this;
	}

	private static function _intuitType($value) {
		if(is_string($value)) {
			return \PDO::PARAM_STR;
		} elseif(is_int($value)) {
			return \PDO::PARAM_INT;
		} elseif(is_bool($value)) {
			return \PDO::PARAM_BOOL;
		} elseif(is_null($value)) {
			return \PDO::PARAM_NULL;
		} else {
			return \PDO::PARAM_STR;
		}
	}

	private static function _typeValue($name, $inout) {
		return constant('PDO::PARAM_' . strtoupper($name)) | ($inout ? \PDO::PARAM_INPUT_OUTPUT : 0);
	}

	private function _raiseError($msg) {
		list($state, $code, $errstr) = $this->stmt->errorInfo();
		throw new DatabaseException($msg, $errstr, $code, $state);
	}
}
