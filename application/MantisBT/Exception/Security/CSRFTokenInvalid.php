<?php
namespace MantisBT\Exception\Security;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class CSRFTokenInvalid extends ExceptionAbstract {
	public function __construct() {
		$errorMessage = lang_get(ERROR_FORM_TOKEN_INVALID, null, false);
		parent::__construct(ERROR_FORM_TOKEN_INVALID, $errorMessage, null);
		$this->responseCode = 403;
	}
}
