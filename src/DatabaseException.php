<?php

namespace Jitsu\Sql;

/**
 * An exception class for database-related errors.
 */
class DatabaseException extends \Exception {

	private $sql_error_code;
	private $errstr;
	private $state;
	private $sql;

	/**
	 * Construct a database exception object.
	 *
	 * @param string $msg A descriptive error message.
	 * @param string $errstr The error string returned by the database
	 *                       driver/library.
	 * @param int|null $code Optional error code.
	 * @param int|null $state Optional SQL state after the error.
	 * @param string|null $sql Optional SQL code which caused the error.
	 */
	public function __construct($msg, $errstr, $code = null, $state = null, $sql = null) {
		parent::__construct($msg);
		$this->sql_error_code = $code;
		$this->errstr = $errstr;
		$this->state = $state;
		$this->sql = $sql;
	}

	/**
	 * Get the SQL engine's error code.
	 *
	 * @return int|null
	 */
	public function getSqlErrorCode() {
		return $this->sql_error_code;
	}

	/**
	 * Get the SQL state abbreviation.
	 *
	 * @return string|null
	 */
	public function getSqlState() {
		return $this->state;
	}

	/**
	 * Get the error string reported by the database driver.
	 *
	 * @return string|null
	 */
	public function getErrorString() {
		return $this->errstr;
	}

	/**
	 * Get the SQL code which caused the error.
	 *
	 * @return string|null
	 */
	public function getSql() {
		return $this->sql;
	}

	/**
	 * Return a suitable string representation of the database error.
	 *
	 * @return string
	 */
	public function __toString() {
		$result = parent::__toString();
		if($this->errstr !== null) $result .= "\nerror string: " . $this->errstr;
		if($this->sql_error_code !== null) $result .= ' [' . $this->sql_error_code . ']';
		if($this->state !== null) $result .= "\nSQL state: " . $this->state;
		if($this->sql !== null) $result .= "\nSQL code: " . $this->sql;
		return $result;
	}
}
