<?php

namespace Jitsu\Sql;

/**
 * A query result consisting only of the number of affected rows.
 */
class StatementStub implements QueryResultInterface {

	private $affected_rows;

	public function __construct($affected_rows) {
		$this->affected_rows = $affected_rows;
	}

	public function affectedRows() {
		return $this->affected_rows;
	}
}
