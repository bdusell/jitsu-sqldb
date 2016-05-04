<?php

namespace Jitsu\Sql;

/**
 * Specialization of `Database` for SQLite.
 */
class SqliteDatabase extends Database {

	/**
	 * Connect to a SQLite database.
	 *
	 * Note that this always enables foreign key constraints. If for some
	 * strange reason you actually want to turn this off, you can run
	 *
	 *     $db = new SqliteDatabase('foo.db');
	 *     $db->execute('pragma foreign_keys = off');
	 *
	 * @param string $filename Name of the database file.
	 * @param array|null Extra PDO options.
	 */
	public function __construct($filename, $options = null) {
		parent::__construct('sqlite:' . $filename, null, null, $options);
		$this->execute('pragma foreign_keys = on');
	}
}
