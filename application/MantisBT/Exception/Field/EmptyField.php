<?php
namespace MantisBT\Exception\Field;
use MantisBT\Exception\ExceptionAbstract;

require_api('lang_api.php');

class EmptyField extends ExceptionAbstract {
	public function __construct($fieldName) {
		$errorMessage = lang_get(ERROR_EMPTY_FIELD, null, false);
		$errorMessage = sprintf($errorMessage, $fieldName);
		parent::__construct(ERROR_EMPTY_FIELD, $errorMessage, null);
	}
}
