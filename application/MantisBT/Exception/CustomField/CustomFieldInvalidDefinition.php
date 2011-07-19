<?php
namespace MantisBT\Exception\CustomField;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class CustomFieldInvalidDefinition extends ExceptionAbstract {
	public function __construct() {
		$errorMessage = lang_get(ERROR_CUSTOM_INVALID_DEFINITION, null, false);
		parent::__construct(ERROR_CUSTOM_INVALID_DEFINITION, $errorMessage, null);
		$this->responseCode = 400;
	}
}
