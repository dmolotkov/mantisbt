<?php
namespace MantisBT\Exception\Security;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class MasterSaltInvalid extends ExceptionAbstract {
	public function __construct() {
		$errorMessage = lang_get(ERROR_CRYPTO_MASTER_SALT_INVALID, null, false);
		parent::__construct(ERROR_CRYPTO_MASTER_SALT_INVALID, $errorMessage, null);
		$this->responseCode = 500;
	}
}
