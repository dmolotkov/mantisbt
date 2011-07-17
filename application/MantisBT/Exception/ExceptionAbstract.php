<?php
namespace MantisBT\Exception;
use \Exception;

abstract class ExceptionAbstract extends Exception {
	protected $code = 0;
	protected $message = 'Unknown exception';
	protected $file;
	protected $line;
	protected $responseCode = 500;
	private $trace;
	private $context = null;

	public function __construct($code = 0, $parameters = null, Exception $previous = null) {
		$message = var_export( $parameters, true);

		$this->context = $parameters;
		parent::__construct($message, $code, $previous);
	}

	public function __toString() {
		return get_class( $this ) . " '{$this->message}' in {$this->file}({$this->line})\n" . "{$this->getTraceAsString()}";
	}

	public function getContext() {
		return $this->context;
	}
}
