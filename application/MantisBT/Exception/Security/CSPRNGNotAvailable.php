<?php
namespace MantisBT\Exception\Security;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

/* CSPRNG = Cryptographically secure pseudorandom number generator */
class CSPRNGNotAvailable extends ExceptionAbstract {
	public function __construct() {
		$errorMessage = lang_get(ERROR_CRYPTO_CAN_NOT_GENERATE_STRONG_RANDOMNESS, null, false);
		parent::__construct(ERROR_CRYPTO_CAN_NOT_GENERATE_STRONG_RANDOMNESS, $errorMessage, null);
		$this->responseCode = 500;
	}
}
