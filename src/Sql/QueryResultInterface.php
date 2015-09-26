<?php

namespace Jitsu\Sql;

interface QueryResultInterface {

	/**
	 * @return int
	 */
	public function affectedRows();
}
