<?php
namespace MantisBT\Exception\CustomField;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class CustomFieldNotFound extends ExceptionAbstract {
	public function __construct($fieldID) {
		$errorMessage = lang_get(ERROR_CUSTOM_FIELD_NOT_FOUND, null, false);
		$errorMessage = sprintf($errorMessage, $fieldID);
		parent::__construct(ERROR_CUSTOM_FIELD_NOT_FOUND, $errorMessage, null);
		$this->responseCode = 500;
	}
}
