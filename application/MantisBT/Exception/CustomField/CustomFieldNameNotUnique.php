<?php
namespace MantisBT\Exception\CustomField;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class CustomFieldNameNotUnique extends ExceptionAbstract {
	public function __construct($fieldName) {
		$errorMessage = lang_get(ERROR_CUSTOM_FIELD_NAME_NOT_UNIQUE, null, false);
		$errorMessage = sprintf($errorMessage, $fieldName);
		parent::__construct(ERROR_CUSTOM_FIELD_NAME_NOT_UNIQUE, $errorMessage, null);
		$this->responseCode = 400;
	}
}
