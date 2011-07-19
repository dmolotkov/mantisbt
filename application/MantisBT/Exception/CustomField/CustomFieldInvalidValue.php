<?php
namespace MantisBT\Exception\CustomField;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class CustomFieldInvalidValue extends ExceptionAbstract {
	public function __construct($fieldName) {
		$errorMessage = lang_get(ERROR_CUSTOM_INVALID_VALUE, null, false);
		$errorMessage = sprintf($errorMessage, $fieldName);
		parent::__construct(ERROR_CUSTOM_INVALID_VALUE, $errorMessage, null);
		$this->responseCode = 400;
	}
}
