<?php
namespace MantisBT\Exception;
use \Exception;

abstract class ExceptionAbstract extends Exception {
    protected $message = 'Unknown exception';     // Exception message
    private   $string;                            // Unknown
    protected $code    = 0;                       // User-defined exception code
    protected $file;                              // Source filename of exception
    protected $line;                              // Source line of exception
    private   $trace;                             // Unknown

	private $context = null;		// Mantis Context
    public function __construct($code = 0, $parameters, Exception $previous = null) {
		$message = var_export( $parameters, true);

		$this->context = $parameters;
        parent::__construct($message, $code, $previous);
    }

    public function __toString() {
        return get_class( $this ) . " '{$this->message}' in {$this->file}({$this->line})\n"
                                . "{$this->getTraceAsString()}";
    }

	public function getContext() {
		return $this->context;
	}
}
