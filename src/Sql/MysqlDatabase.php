<?php

namespace Jitsu\Sql;

class MysqlDatabase extends Database {

	public function __construct(
		$host,
		$database,
		$username,
		$password,
		$charset = 'utf8mb4',
		$options = null
	) {
		parent::__construct(
			'mysql:host=' . $host .
			';dbname=' . $database .
			($charset === null ? '' : ';charset=' . $charset),
			$username,
			$password,
			$options
		);
		if($charset !== null) {
			$this->execute('set names ' . $charset);
		}
	}
}
