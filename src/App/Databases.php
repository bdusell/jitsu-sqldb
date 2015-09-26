<?php

namespace Jitsu\App;

trait Databases {

	public function database($data_prop = 'database', $config = null) {
		$this->handler(new Handlers\ConfigureDatabase(
			$data_prop,
			$config === null ? $data_prop : $config
		));
	}
}
