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
 * Database API
 *
 * @package CoreAPI
 * @subpackage DatabaseAPI
 * @copyright Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright (C) 2002 - 2011  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses error_api.php
 * @uses logging_api.php
 * @uses utility_api.php
 * @uses adodb/adodb.inc.php
 */

require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'error_api.php' );
require_api( 'logging_api.php' );
require_api( 'utility_api.php' );

/**
 * An array in which all executed queries are stored.  This is used for profiling
 * @global array $g_queries_array
 */
$g_queries_array = array();

/**
 * Stores whether a database connection was succesfully opened.
 * @global bool $g_db_connected
 */
$g_db_connected = false;

/**
 * Store whether to log queries ( used for show_queries_count/query list)
 * @global bool $g_db_log_queries
 */
$g_db_log_queries = ( 0 != ( config_get_global( 'log_level' ) & LOG_DATABASE ) );

/**
 * Open a connection to the database.
 * @param string $p_dsn Database connection string ( specified instead of other params)
 * @param string $p_hostname Database server hostname
 * @param string $p_username database server username
 * @param string $p_password database server password
 * @param string $p_database_name database name
 * @param array $p_dboptions Database options
 * @return bool indicating if the connection was successful
 */
function db_connect( $p_dsn, $p_hostname = null, $p_username = null, $p_password = null, $p_database_name = null, $p_db_options = null ) {
	global $g_db_connected, $g_db;
	$t_db_type = config_get_global( 'db_type' );

	$g_db = MantisDatabase::get_driver_instance($t_db_type);
	$t_result = $g_db->connect( $p_dsn, $p_hostname, $p_username, $p_password, $p_database_name, $p_db_options );

	if( !$t_result ) {
		db_error();
		trigger_error( ERROR_DB_CONNECT_FAILED, ERROR );
		return false;
	}
	$g_db_connected = true;
	return true;
}

/**
 * Returns whether a connection to the database exists
 * @global stores database connection state
 * @return bool indicating if the a database connection has been made
 */
function db_is_connected() {
	global $g_db_connected;

	return $g_db_connected;
}


/**
 * Checks if the database driver is MySQL
 * @return bool true if mysql
 */
function db_is_mysql() {
	global $g_db;
	return ($g_db->get_dbtype() == 'mysql');
}

/**
 * Checks if the database driver is PostgreSQL
 * @return bool true if postgres
 */
function db_is_pgsql() {
	global $g_db;
	return ($g_db->get_dbtype() == 'postgres');
}

/**
 * Checks if the database driver is MS SQL
 * @return bool true if postgres
 */
function db_is_mssql() {
	global $g_db;
	return ($g_db->get_dbtype() == 'mssql');
}

/**
 * Checks if the database driver is DB2
 * @return bool true if db2
 */
function db_is_db2() {
	global $g_db;
	return ($g_db->get_dbtype() == 'db2');
}

/**
 * execute query, requires connection to be opened
 * An error will be triggered if there is a problem executing the query.
 * @global array of previous executed queries for profiling
 * @global adodb database connection object
 * @global boolean indicating whether queries array is populated
 * @param string $p_query Parameterlised Query string to execute
 * @param array $arr_parms Array of parameters matching $p_query
 * @param int $p_limit Number of results to return
 * @param int $p_offset offset query results for paging
 * @return ADORecordSet|bool adodb result set or false if the query failed.
 */
function db_query_bound( $p_query, $arr_parms = null, $p_limit = -1, $p_offset = -1 ) {
	global $g_queries_array, $g_db, $g_db_log_queries;

	static $s_check_params;
	if( $s_check_params === null ) {
		$s_check_params = ( db_is_pgsql() || config_get_global( 'db_type' ) == 'odbc_mssql' );
	}

	if(( $p_limit != -1 ) || ( $p_offset != -1 ) ) {
		$t_result = $g_db->SelectLimit( $p_query, $p_limit, $p_offset, $arr_parms );
	} else {
		$t_result = $g_db->Execute( $p_query, $arr_parms );
	}

	//$t_elapsed = number_format( microtime(true) - $t_start, 4 );

	if( ON == $g_db_log_queries ) {
		$t_db_type = config_get_global( 'db_type' );
		$lastoffset = 0;
		$i = 1;
		if( !( is_null( $arr_parms ) || empty( $arr_parms ) ) ) {
			while( preg_match( '/(\?)/', $p_query, $matches, PREG_OFFSET_CAPTURE, $lastoffset ) ) {
				if( $i <= count( $arr_parms ) ) {
					if( is_null( $arr_parms[$i - 1] ) ) {
						$replace = 'NULL';
					}
					else if( is_string( $arr_parms[$i - 1] ) ) {
						$replace = "'" . $arr_parms[$i - 1] . "'";
					}
					else if( is_integer( $arr_parms[$i - 1] ) || is_float( $arr_parms[$i - 1] ) ) {
						$replace = (float) $arr_parms[$i - 1];
					}
					else if( is_bool( $arr_parms[$i - 1] ) ) {
						switch( $t_db_type ) {
							case 'pgsql':
								$replace = "'" . $arr_parms[$i - 1] . "'";
							break;
						default:
							$replace = $arr_parms[$i - 1];
							break;
						}
					} else {
						echo( "Invalid argument type passed to query_bound(): $i" );
						exit( 1 );
					}
					$p_query = utf8_substr( $p_query, 0, $matches[1][1] ) . $replace . utf8_substr( $p_query, $matches[1][1] + utf8_strlen( $matches[1][0] ) );
					$lastoffset = $matches[1][1] + utf8_strlen( $replace );
				} else {
					$lastoffset = $matches[1][1] + 1;
				}
				$i++;
			}
		}
		//log_event( LOG_DATABASE, array( $p_query, $t_elapsed), debug_backtrace() );
		//array_push( $g_queries_array, array( $p_query, $t_elapsed ) );
	} else {
		//array_push( $g_queries_array, array( '', $t_elapsed ) );
	}

	if( !$t_result ) {
		db_error( $p_query );
		trigger_error( ERROR_DB_QUERY_FAILED, ERROR );
		return false;
	} else {
		return $t_result;
	}
}

/**
 * Generate a string to insert a parameter into a database query string
 * @return string 'wildcard' matching a paramater in correct ordered format for the current database.
 */
function db_param() {
	return '?';
}

/**
 * Retrieve number of rows affected for a specific database query
 * @param ADORecordSet $p_result Database Query Record Set to retrieve record count for.
 * @return int Record Count
 */
function db_affected_rows( $p_result ) {
	global $g_db;

	return $p_result->rowCount();
}

/**
 * Retrieve the next row returned from a specific database query
 * @param bool|ADORecordSet $p_result Database Query Record Set to retrieve next result for.
 * @return array Database result
 */
function db_fetch_array( &$p_result ) {
	global $g_db, $g_db_type;

	return $p_result->fetch();
}

/**
 * Retrieve a result returned from a specific database query
 * @param bool|ADORecordSet $p_result Database Query Record Set to retrieve next result for.
 * @param int $p_index1 Column to retrieve (optional)
 * @return mixed Database result
 */
function db_result( $p_result, $p_index1 = 0 ) {
	return $p_result->fetchColumn($p_index1);
}

/**
 * return the last inserted id for a specific database table
 * @param string $p_table a valid database table name
 * @return int last successful insert id
 */
function db_insert_id( $p_table = null, $p_field = "id" ) {
	global $g_db;

	return $g_db->get_insert_id( $p_table, $p_field );
}

/**
 * Check if the specified table exists.
 * @param string $p_table_name a valid database table name
 * @return bool indicating whether the table exists
 */
function db_table_exists( $p_table_name ) {
	if( is_blank( $p_table_name ) ) {
		return false;
	}

	$t_tables = db_get_table_list();

	# Can't use in_array() since it is case sensitive
	$t_table_name = utf8_strtolower( $p_table_name );
	foreach( $t_tables as $t_current_table ) {
		if( utf8_strtolower( $t_current_table ) == $t_table_name ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if the specified table index exists.
 * @param string $p_table_name a valid database table name
 * @param string $p_index_name a valid database index name
 * @return bool indicating whether the index exists
 */
function db_index_exists( $p_table_name, $p_index_name ) {
	global $g_db, $g_db_schema;

	if( is_blank( $p_index_name ) || is_blank( $p_table_name ) ) {
		return false;

		// no index found
	}

	$t_indexes = $g_db->get_indexes( $p_table_name );

	# Can't use in_array() since it is case sensitive
	$t_index_name = utf8_strtolower( $p_index_name );
	foreach( $t_indexes as $t_current_index_name => $t_current_index_obj ) {
		if( utf8_strtolower( $t_current_index_name ) == $t_index_name ) {
			return true;
		}
	}
	return false;
}

/**
 * Check if the specified field exists in a given table
 * @param string $p_field_name a database field name
 * @param string $p_table_name a valid database table name
 * @return bool indicating whether the field exists
 */
function db_field_exists( $p_field_name, $p_table_name ) {
	global $g_db;
	$columns = db_field_names( $p_table_name );
	return in_array( $p_field_name, $columns );
}

/**
 * Retrieve list of fields for a given table
 * @param string $p_table_name a valid database table name
 * @return array array of fields on table
 */
function db_field_names( $p_table_name ) {
	global $g_db;
	$columns = $g_db->get_columns( $p_table_name );
	return is_array( $columns ) ? $columns : array();
}

/**
 * send both the error number and error message and query (optional) as paramaters for a triggered error
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_error( $p_query = null ) {
	global $g_db;
	if( null !== $p_query ) {
		error_parameters( /* $g_db->ErrorNo(), */ $g_db->get_last_error(), $p_query );
	} else {
		error_parameters( /* $g_db->ErrorNo(), */ $g_db->get_last_error() );
	}
}

/**
 * close the connection.
 * Not really necessary most of the time since a connection is automatically closed when a page finishes loading.
 */
function db_close() {
	global $g_db;

	$t_result = $g_db->Close();
}

/**
 * prepare a string before DB insertion
 * @param string $p_string unprepared string
 * @return string prepared database query string
 * @deprecated db_query_bound should be used in preference to this function. This function may be removed in 1.2.0 final
 */
function db_prepare_string( $p_string ) {
	return $p_string;
}

/**
 * prepare a binary string before DB insertion
 * @param string $p_string unprepared binary data
 * @return string prepared database query string
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_prepare_binary_string( $p_string ) {
	global $g_db;
	$t_db_type = config_get_global( 'db_type' );

	switch( $t_db_type ) {
		case 'mssql':
		case 'odbc_mssql':
		case 'ado_mssql':
			$content = unpack( "H*hex", $p_string );
			return '0x' . $content['hex'];
			break;
		case 'postgres':
		case 'postgres64':
		case 'postgres7':
		case 'pgsql':
			return '\'' . pg_escape_bytea( $p_string ) . '\'';
			break;
		default:
			return '\'' . db_prepare_string( $p_string ) . '\'';
			break;
	}
}

/**
 * prepare a int for database insertion.
 * @param int $p_int integer
 * @return int integer
 * @deprecated db_query_bound should be used in preference to this function. This function may be removed in 1.2.0 final
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_prepare_int( $p_int ) {
	return (int) $p_int;
}

/**
 * prepare a boolean for database insertion.
 * @param boolean $p_boolean boolean
 * @return int integer representing boolean
 * @deprecated db_query_bound should be used in preference to this function. This function may be removed in 1.2.0 final
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_prepare_bool( $p_bool ) {
	return (int) (bool) $p_bool;
}

/**
 * return current timestamp for DB
 * @todo add param bool $p_gmt whether to use GMT or current timezone (default false)
 * @return string Formatted Date for DB insertion e.g. 1970-01-01 00:00:00 ready for database insertion
 */
function db_now() {
	global $g_db;

	return time();
}

/**
 * convert minutes to a time format [h]h:mm
 * @param int $p_min integer representing number of minutes
 * @return string representing formatted duration string in hh:mm format.
 */
function db_minutes_to_hhmm( $p_min = 0 ) {
	return sprintf( '%02d:%02d', $p_min / 60, $p_min % 60 );
}

/**
 * A helper function that generates a case-sensitive or case-insensitive like phrase based on the current db type.
 * The field name and value are assumed to be safe to insert in a query (i.e. already cleaned).
 * @param string $p_field_name The name of the field to filter on.
 * @param bool $p_case_sensitive true: case sensitive, false: case insensitive
 * @return string returns (field LIKE 'value') OR (field ILIKE 'value')
 */
function db_helper_like( $p_field_name, $p_case_sensitive = false ) {
	$t_like_keyword = 'LIKE';

	if( $p_case_sensitive === false ) {
		if( db_is_pgsql() ) {
			$t_like_keyword = 'ILIKE';
		}
	}

	return "($p_field_name $t_like_keyword " . db_param() . ')';
}

/**
 * A helper function to compare two dates against a certain number of days
 * @param $p_date1_id_or_column
 * @param $p_date2_id_or_column
 * @param $p_limitstring
 * @return string returns database query component to compare dates
 * @todo Check if there is a way to do that using ADODB rather than implementing it here.
 */
function db_helper_compare_days( $p_date1_id_or_column, $p_date2_id_or_column, $p_limitstring ) {
	$t_db_type = config_get_global( 'db_type' );

	$p_date1 = $p_date1_id_or_column;
	$p_date2 = $p_date2_id_or_column;
	if( is_int( $p_date1_id_or_column ) ) {
		$p_date1 = db_param();
	}
	if( is_int( $p_date2_id_or_column ) ) {
		$p_date2 = db_param();
	}

	return '((' . $p_date1 . ' - ' . $p_date2 .')' . $p_limitstring . ')';
}

/**
 * count queries
 * @return int
 */
function db_count_queries() {
	global $g_queries_array;

	return count( $g_queries_array );
}

/**
 * count unique queries
 * @return int
 */
function db_count_unique_queries() {
	global $g_queries_array;

	$t_unique_queries = 0;
	$t_shown_queries = array();
	foreach( $g_queries_array as $t_val_array ) {
		if( !in_array( $t_val_array[0], $t_shown_queries ) ) {
			$t_unique_queries++;
			array_push( $t_shown_queries, $t_val_array[0] );
		}
	}
	return $t_unique_queries;
}

/**
 * get total time for queries
 * @return int
 */
function db_time_queries() {
	global $g_queries_array;
	$t_count = count( $g_queries_array );
	$t_total = 0;
	for( $i = 0;$i < $t_count;$i++ ) {
		$t_total += $g_queries_array[$i][1];
	}
	return $t_total;
}

/**
 * get database table name
 * @return string containing full database table name
 */
function db_get_table( $p_option ) {
	$t_table = $p_option;
	$t_prefix = config_get_global( 'db_table_prefix' );
	$t_suffix = config_get_global( 'db_table_suffix' );
	if ( $t_prefix ) {
		$t_table = $t_prefix . '_' . $t_table;
	}
	if ( $t_suffix ) {
		$t_table .= $t_suffix;
	}
	return $t_table;
}

/**
 * get list database tables
 * @return array containing table names
 */
function db_get_table_list() {
	global $g_db;
	return $g_db->get_tables();
}