<?php

class EUser_Message extends Exception { public $type;
	function __construct($message, $type = 'text'){
		parent::__construct($message);
		$this->type = $type;
	}
};

