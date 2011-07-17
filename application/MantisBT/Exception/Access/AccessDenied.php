<?php
namespace MantisBT\Exception\Access;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class AccessDenied extends ExceptionAbstract {
	public function __construct() {
		$errorMessage = lang_get(ERROR_ACCESS_DENIED, null, false);
		parent::__construct( ERROR_ACCESS_DENIED, $errorMessage, null );
	}
}
