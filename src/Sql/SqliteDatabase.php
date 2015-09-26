<?php

namespace Jitsu\Sql;

class SqliteDatabase extends Database {

	public function __construct($filename, $options = null) {
		parent::__construct('sqlite:' . $filename, null, null, $options);
		$this->execute('pragma foreign_keys = on');
	}
}
