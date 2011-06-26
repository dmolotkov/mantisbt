<?php
namespace MantisBT;
use \stdClass;

class Error {
	/**
	 * Indicates previous errors
	 */
	private static $allErrors = array();
		
	/**
	 * Indicates if an error/exception has been handled
	 * Note: this also indicates additional shutdown functions have been setup.
	 */
	private static $handled = false;
	
	private static $errorConstants = array(
										'1'=>'E_ERROR',
										'2'=>'E_WARNING',
										'4'=>'E_PARSE',
										'8'=>'E_NOTICE',
										'16'=>'E_CORE_ERROR',
										'32'=>'E_CORE_WARNING',
										'64'=>'E_COMPILE_ERROR',
										'128'=>'E_COMPILE_WARNING',
										'256'=>'APPLICATION ERROR', 	// E_USER_ERROR
										'512'=>'APPLICATION WARNING', 	// E_USER_WARNING
										'1024'=>'E_USER_NOTICE',
										'2048'=>'E_STRICT',
										'4096'=>'E_RECOVERABLE_ERROR',
										'8192'=>'E_DEPRECATED',
										'16384'=>'E_USER_DEPRECATED',
									);

	private static $parameters = array();
	
	private static $proceedUrl = null;
									
	public static function init(){
		if( self::$handled === false ) {
			// first run
			register_shutdown_function(array('MantisBT\Error', 'display_errors'));
			
			self::$handled = true;
		}
	}

	public static function exception_handler( Exception $ex) {
		self::init();

        $errorInfo = new stdClass();
        $errorInfo->time = time();
        $errorInfo->type = 'EXCEPTION';
        $errorInfo->name = get_class($ex);
        $errorInfo->code = $ex->getCode();
        $errorInfo->message = $ex->getMessage();
        $errorInfo->file = $ex->getFile();
        $errorInfo->line = $ex->getLine();
        $errorInfo->trace = $ex->getTrace();
		$errorInfo->context = $ex->getContext();

        self::$allErrors[] = $errorInfo;
	}
	
	public static function error_handler( $type, $error, $file, $line, $context ) {
        $errorInfo = new stdClass();
        $errorInfo->time = time();
        $errorInfo->type = 'ERROR';
        $errorInfo->name = isset( self::$errorConstants[$type] ) ? self::$errorConstants[$type] : 'UNKNOWN';
        $errorInfo->code = $type;
        $errorInfo->message = is_numeric( $error ) ? self::error_string( $error ) : $error;
        $errorInfo->file = $file;
        $errorInfo->line = $line;
        $errorInfo->context = $context;
		$errorInfo->trace = debug_backtrace();

        self::$allErrors[] = $errorInfo;
		
		if( 0 == error_reporting() ) {
			return false;
		}		

		// historically we inline warnings
		if( $type != E_WARNING && $type != E_USER_WARNING ) {
			self::init();
		}
		
		if( $type == E_WARNING || $type == E_USER_WARNING || null !== self::$proceedUrl ) {
		
			switch( $type ) {
				case E_WARNING:
					$errorType = 'SYSTEM WARNING';
					$errorDescription = $error;
					break;
				case E_USER_WARNING:
					$errorType = "APPLICATION WARNING #$error";
					$errorDescription = self::error_string( $error );
					break;
			}
			$errorDescription = nl2br( $errorDescription );
			echo '<p style="color:red">', $errorType, ': ', $errorDescription, '</p>';
			if ( null !== self::$proceedUrl ) {
				echo '<a href="', self::$proceedUrl, '">', lang_get( 'proceed' ), '</a>';
			}
			
			return true; // @todo true|false??
		}
		
		exit();
	}
	
	public static function shutdown_error_handler() { 
		$error = error_get_last();
		if( $error === null ) {
			return;
		}

		self::init();
		
		$errorInfo = new stdClass();
        $errorInfo->time = time();
        $errorInfo->type = 'ERROR_LAST';
        $errorInfo->name = isset( self::$errorConstants[$error['type']] ) ? self::$errorConstants[$error['type']] : 'UNKNOWN';
        $errorInfo->code = $error['type'];
        $errorInfo->message = $error['message'];
        $errorInfo->file = $error['file'];
        $errorInfo->line = $error['line'];
        $errorInfo->trace = debug_backtrace();
		$errorInfo->context = null;

        self::$allErrors[] = $errorInfo;
	}
	
	public static function display_errors( $noHeader = false ) {
		# disable any further event callbacks
		if ( function_exists( 'event_clear_callbacks' ) ) {
			event_clear_callbacks();
		}
			
		$oblen = ob_get_length();
		if( error_handled() && $oblen > 0 ) {
			$oldContents = ob_get_contents();
		}

		# We need to ensure compression is off - otherwise the compression headers are output.
		compress_disable();	

		# then clean the buffer, leaving output buffering on.
		if( $oblen > 0 ) {
			ob_clean();
		}

		echo '<?xml version="1.0" encoding="utf-8"?>';
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
		echo '<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" >';
		echo '<head><title>Error Page</title>';
		echo '<style>table.width70		{ width: 70%;  border: solid 1px #000000; }</style></head><body>';
		echo '<p align="center"><img src="' . helper_mantis_url('images/mantis_logo.gif') . '" /></p>';
		echo '<hr />';

		echo '<div align="center">';
		echo lang_get( 'error_no_proceed' );
		echo '<br />';
		
		foreach ( self::$allErrors as $key => $errorInfo ) {
			self::display_error( $errorInfo );

			if ( $key == 0 && sizeof( self::$allErrors ) > 1 ) {
				echo '<p>Previous non-fatal errors occurred:</p>';
			}
		}
		echo '</div>';
		
		if ( !config_get( 'show_friendly_errors' ) ) {
			if( isset( $oldContents ) ) {
				echo '<p>Page contents follow.</p>';
				echo '<div style="border: solid 1px black;padding: 4px"><pre>';
				echo htmlspecialchars($oldContents);
				echo '</pre></div>';
			}
		}
		echo '<hr /><br /><br /><br />';
		echo '</body></html>', "\n";
		exit();
	}
	
	public static function display_error( $error) {
		echo '<br /><div><table class="width70" cellspacing="1">';
		echo '<tr><td class="form-title">' . $error->name . '</td></tr>';
		echo '<tr><td><p class="center" style="color:red">' . nl2br( $error->message ) . '</p></td></tr>';
		echo '<tr><td>';
		self::error_print_details( basename( $error->file ), $error->line, $error->context );
		echo '</td></tr>';
		if ( !config_get( 'show_friendly_errors' ) ) {
			echo '<tr><td>';
			self::error_print_stack_trace( $error->trace );
			echo '</td></tr>';
		}
		echo '</table></div>';
	}
	
	/**
	 * Print out the error details including context
	 * @param string $file
	 * @param int $line
	 * @param string $context
	 * @return null
	 */
	public static function error_print_details( $file, $line, $context ) {
		if ( !config_get( 'show_friendly_errors' ) ) {
		?>
			<table class="width75">
				<tr>
					<td>Filename: <?php echo htmlentities( $file, ENT_COMPAT, 'UTF-8' );?></td>
				</tr>
				<tr>
					<td>Line: <?php echo $line?></td>
				</tr>
				<tr>
					<td>
						<?php self::error_print_context( $context )?>
					</td>
				</tr>
			</table>
		<?php
		} else {
			if( strpos( $file, '.' ) !== false ) {
				$components = explode( '.', $file );
				$file = current( $components );
			}
		?>
			<table class="width75">
				<tr>
					<td>ID: <?php echo htmlentities( $file, ENT_COMPAT, 'UTF-8' );?>:<?php echo $line?></td>
				</tr>
			</table>				
		<?php
		}
	}
	
	/**
	 * Print out the variable context given
	 * @param string $context
	 * @return null
	 */
	public static function error_print_context( $context ) {
		if( !is_array( $context ) && !is_object( $context )) {
			return;
		}

		echo '<table class="width100"><tr><th>Variable</th><th>Value</th><th>Type</th></tr>';

		# print normal variables
		foreach( $context as $var => $val ) {
			if( !is_array( $val ) && !is_object( $val ) ) {
				$type = gettype( $val );
				$val = htmlentities( (string) $val, ENT_COMPAT, 'UTF-8' );

				# Mask Passwords
				if( strpos( $var, 'pass' ) !== false ) {
					$val = '**********';
				}

				echo '<tr><td>', $var, '</td><td>', $val, '</td><td>', $type, '</td></tr>', "\n";
			}
		}

		# print arrays
		foreach( $context as $var => $val ) {
			if( is_array( $val ) && ( $var != 'GLOBALS' ) ) {
				echo '<tr><td colspan="3"><br /><strong>', $var, '</strong></td></tr>';
				echo '<tr><td colspan="3">';
				self::error_print_context( $val );
				echo '</td></tr>';
			}
		}

		echo '</table>';
		}

		public static function error_print_stack_trace( $stack ) {
		echo '<table class="width75">';
		echo '<tr><th>Filename</th><th>Line</th><th></th><th></th><th>Function</th><th>Args</th></tr>';

		# remove the call to the error handler from the stack trace
		array_shift( $stack );
	

		foreach( $stack as $frame ) {
			echo '<tr ', self::error_alternate_class(), '>';
			echo '<td>', ( isset( $frame['file'] ) ? htmlentities( $frame['file'], ENT_COMPAT, 'UTF-8' ) : '-' ), '</td><td>', ( isset( $frame['line'] ) ? $frame['line'] : '-' ), '</td><td>', ( isset( $frame['class'] ) ? $frame['class'] : '-' ), '</td><td>', ( isset( $frame['type'] ) ? $frame['type'] : '-' ), '</td><td>', ( isset( $frame['function'] ) ? $frame['function'] : '-' ), '</td>';

			$args = array();
			if( isset( $frame['args'] ) && !empty( $frame['args'] ) ) {
				foreach( $frame['args'] as $value ) {
					$args[] = self::error_build_parameter_string( $value );
				}
				echo '<td>( ', htmlentities( implode( $args, ', ' ), ENT_COMPAT, 'UTF-8' ), ' )</td></tr>';
			} else {
				echo '<td>-</td></tr>';
			}
		}
		echo '</table>';
	}


	public static function error_build_parameter_string( $param, $showType = true, $depth = 0 ) {
		if( $depth++ > 10 ) {
			return '<strong>***Nesting Level Too Deep***</strong>';
		}

		if( is_array( $param ) ) {
			$results = array();

			foreach( $param as $t_key => $value ) {
				# Mask Passwords
				if( strpos( $t_key, 'pass' ) !== false ) {
					$value = '**********';
				}
				$results[] = '[' . self::error_build_parameter_string( $t_key, false, $depth ) . ']' . ' => ' . self::error_build_parameter_string( $value, false, $depth );
			}

			return '<Array> { ' . implode( $results, ', ' ) . ' }';
		}
		else if( is_object( $param ) ) {
			$results = array();

			$className = get_class( $param );
			$instVars = get_object_vars( $param );

			foreach( $instVars as $name => $value ) {
				$results[] = "[$name]" . ' => ' . self::error_build_parameter_string( $value, false, $depth );
			}

			return '<Object><' . $className . '> ( ' . implode( $results, ', ' ) . ' )';
		} else {
			if( $showType ) {
				return '<' . gettype( $param ) . '>' . var_export( $param, true );
			} else {
				return var_export( $param, true );
			}
		}
	}
	
	/**
	 * Return an error string (in the current language) for the given error.
	 * @param int $error
	 * @return string
	 * @access public
	 */
	public static function error_string( $error ) {
		# We pad the parameter array to make sure that we don't get errors if
		#  the caller didn't give enough parameters for the error string
		$padding = array_pad( array(), 10, '' );

		$error = lang_get( $error, null, false );

		if( $error == '' ) {
			return lang_get( 'missing_error_string' ) . $error;
		}
		
		# ripped from string_api
		$string = call_user_func_array( 'sprintf', array_merge( array( $error ), self::$parameters, $padding ) );
		return preg_replace( "/&amp;(#[0-9]+|[a-z]+);/i", "&$1;", @htmlspecialchars( $string, ENT_COMPAT, 'UTF-8' ) );
	}
	
	
	/**
	 * Simple version of helper_alternate_class for use by error api only.
	 * @access private
	 * @return string representing css class
	 */
	public static function error_alternate_class() {
		static $errIndex = 1;

		if( 1 == $errIndex++ % 2 ) {
			return 'class="row-1"';
		} else {
			return 'class="row-2"';
		}
	}
	
	public static function error_parameters( $args ) {
		self::$parameters = $args;
	}

	public static function error_proceed_url( $url ) {
		self::$proceedUrl = $url();
	}
	
	public static function error_handled() {
		return self::$handled;
	}
}
