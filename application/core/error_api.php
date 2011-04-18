<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Error API
 *
 * @package CoreAPI
 * @subpackage ErrorAPI
 * @copyright Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright (C) 2002 - 2011  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses compress_api.php
 * @uses config_api.php
 * @uses constant_api.php
 * @uses database_api.php
 * @uses html_api.php
 * @uses lang_api.php
 */

require_api( 'compress_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );

$g_error_parameters = array();
$g_error_proceed_url = null;
$g_error_send_page_header = true;

set_exception_handler(array('MantisError', 'exception_handler'));
set_error_handler(array('MantisError', 'error_handler'));
register_shutdown_function(array('MantisError', 'shutdown_error_handler'));


function exception_handler($exception) {
	global $g_error_parameters, $g_error_handled, $g_error_proceed_url;
	global $g_lang_overrides;
	global $g_error_send_page_header;

	$t_lang_pushed = false;

	$t_db_connected = false;
	if (!$exception instanceof MantisDatabaseException) {
	if( function_exists( 'db_is_connected' ) ) {
		if( db_is_connected() ) {
			$t_db_connected = true;
		}
	}
	}


	# flush any language overrides to return to user's natural default
	if( $t_db_connected ) {
		lang_push( lang_get_default() );
		$t_lang_pushed = true;
	}

		if( $t_lang_pushed ) {
			lang_pop();
	}

	$g_error_parameters = array();
	$g_error_proceed_url = null;

	//??? return false;
}


function error_handler( $p_type, $p_error, $p_file, $p_line, $p_context ) {
	global $g_error_parameters, $g_error_handled, $g_error_proceed_url;
	global $g_lang_overrides;
	global $g_error_send_page_header;



	$t_lang_pushed = false;

	$t_db_connected = false;
	if( function_exists( 'db_is_connected' ) ) {
		if( db_is_connected() ) {
			$t_db_connected = true;
	}
}

	# flush any language overrides to return to user's natural default
	if( $t_db_connected ) {
		lang_push( lang_get_default() );
		$t_lang_pushed = true;
	}






		if( $t_lang_pushed ) {
			lang_pop();
}

	$g_error_parameters = array();
	$g_error_proceed_url = null;
	
	return false;
}

/**
 * Check if we have handled an error during this page
 * Return true if an error has been handled, false otherwise
 * @return bool
 */
function error_handled() {
	return MantisError::error_handled();
}

/**
 * Set additional info parameters to be used when displaying the next error
 * This function takes a variable number of parameters
 *
 * When writing internationalized error strings, note that you can change the
 *  order of parameters in the string.  See the PHP manual page for the
 *  sprintf() function for more details.
 * @access public
 * @return null
 */
function error_parameters() {
	MantisError::error_parameters( func_get_args() );
}

/**
 * Set a url to give to the user to proceed after viewing the error
 * @access public
 * @param string p_url url given to user after viewing the error
 * @return null
 */
function error_proceed_url( $p_url ) {
	MantisError::error_proceed_url( $p_url );
}


