<?php

namespace Jitsu\Sql;

interface QueryResultInterface {

	/**
	 * The number of rows affected by the SQL statement.
	 *
	 * @return int
	 */
	public function affectedRows();
}
