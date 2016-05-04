<?php

namespace Jitsu\App;

/**
 * Mixin for `Jitsu\App\Application` which adds configurable database
 * connection functionality.
 */
trait Databases {

	/**
	 * The configuration settings should be defined in an array. The
	 * following keys are recognized:
	 *
	 * * `driver`: The name of the SQL software used by the database.
	 *   Either `'sqlite'` or `'mysql'`. Mandatory.
	 * * `persistent`: Whether to use persistent database connections.
	 *   Default is `false`.
	 * * `host`: (MySQL) The name of the host hosting the database.
	 * * `database`: (MySQL) The name of the MySQL database.
	 * * `user`: (MySQL) The name of the MySQL user used to log in.
	 * * `password`: (MySQL) The password used to log in.
	 * * `charset`: (MySQL) The character set used by the connection.
	 *   Default is `'utf8mb4'`, which supports all Unicode characters
	 *   encoded in UTF-8.
	 * * `file`: (SQLite) The name of the database file.
	 *
	 * @param string $data_prop Name of the property on `$data` where the
	 *        database connection object will be assigned. By default, it
	 *        is assigned to `$data->database`.
	 * @param array|string|null $config Configuration settings for the
	 *        database connection. Either an array or the name of a
	 *        property on `$data->config`. By default, this is set to the
	 *        same string as `$data_prop`.
	 */
	public function database($data_prop = 'database', $config = null) {
		$this->handler(new Handlers\ConfigureDatabase(
			$data_prop,
			$config === null ? $data_prop : $config
		));
	}
}
