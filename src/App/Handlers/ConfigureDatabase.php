<?php

namespace Jitsu\App\Handlers;

use Jitsu\ArrayUtil;

/**
 * Handler for adding a database connection object to `$data` based on
 * configuration settings.
 */
class ConfigureDatabase implements \Jitsu\App\Handler {

	private $data_prop;
	private $config;

	/**
	 * @param string $data_prop Name of the property on `$data` where the
	 *        connection object will be assigned.
	 * @param array|string Configuration settings for the database
	 *        connection. Either an array or the name of a property on
	 *        `$data->config`.
	 */
	public function __construct($data_prop, $config) {
		$this->data_prop = $data_prop;
		$this->config = $config;
	}

	public function handle($data) {
		if(is_string($this->config)) {
			$config = Util::requireProp($data, 'config');
			$prop = $this->config;
			$db_config = $config->$prop;
		} else {
			$db_config = $this->config;
		}
		$db_prop = $this->data_prop;
		$data->$db_prop = self::getDatabase((array) $db_config);
		return false;
	}

	private static function getDatabase($config) {
		$options = array(
			\PDO::ATTR_PERSISTENT =>
				ArrayUtil::get($config, 'persistent', false)
		);
		switch($config['driver']) {
		case 'sqlite':
			return new SqliteDatabase(
				$config['file'],
				$options
			);
		case 'mysql':
			return new MysqlDatabase(
				$config['host'],
				$config['database'],
				$config['user'],
				$config['password'],
				ArrayUtil::get($config, 'charset', 'utf8mb4'),
				$options
			);
		}
		throw new \InvalidArgumentException(
			'SQL driver ' . $config['driver'] . ' not recognized'
		);
	}
}
