<?php

namespace Jitsu\Sql;

/**
 * Specialization of `Database` for MySQL.
 */
class MysqlDatabase extends Database {

	/**
	 * @param string $host Host name.
	 * @param string $database Database name.
	 * @param string $username Username.
	 * @param string $password Password.
	 * @param string $charset Character set used by the connection. The
	 *                        default is `utf8mb4`, which supports all
	 *                        Unicode characters encoded in UTF-8.
	 * @param array|null Extra PDO options.
	 */
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
