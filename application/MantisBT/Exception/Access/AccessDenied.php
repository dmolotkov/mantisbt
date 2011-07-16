<?php
namespace MantisBT\Exception\Access;
use MantisBT\Exception\ExceptionAbstract;
use MantisBT\Error;

class AccessDenied extends ExceptionAbstract {
	public function __construct() {
		parent::__construct( ERROR_ACCESS_DENIED, Error::error_string(ERROR_ACCESS_DENIED), null );
	}
}
