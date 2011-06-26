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

use MantisBT\Db\DriverAbstract;

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
function db_connect( $dsn, $hostname = null, $username = null, $password = null, $databaseName = null, $dbOptions = null ) {
	global $g_db_connected, $g_db;
	$dbType = config_get_global( 'db_type' );

	$g_db = DriverAbstract::getDriverInstance($dbType);
	$result = $g_db->connect( $dsn, $hostname, $username, $password, $databaseName, $dbOptions );

	if( !$result ) {
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
	return ($g_db->getDbType() == 'mysql');
}

/**
 * Checks if the database driver is PostgreSQL
 * @return bool true if postgres
 */
function db_is_pgsql() {
	global $g_db;
	return ($g_db->getDbType() == 'postgres');
}

/**
 * Checks if the database driver is MS SQL
 * @return bool true if postgres
 */
function db_is_mssql() {
	global $g_db;
	return ($g_db->getDbType() == 'mssql');
}

/**
 * Checks if the database driver is DB2
 * @return bool true if db2
 */
function db_is_db2() {
	global $g_db;
	return ($g_db->getDbType() == 'db2');
}

/**
 * execute query, requires connection to be opened
 * An error will be triggered if there is a problem executing the query.
 * @global array of previous executed queries for profiling
 * @global adodb database connection object
 * @global boolean indicating whether queries array is populated
 * @param string $query Parameterlised Query string to execute
 * @param array $arrParms Array of parameters matching $p_query
 * @param int $limit Number of results to return
 * @param int $offset offset query results for paging
 * @return ADORecordSet|bool adodb result set or false if the query failed.
 */
function db_query_bound( $query, $arrParms = null, $limit = -1, $offset = -1 ) {
	global $g_queries_array, $g_db, $g_db_log_queries;

	static $s_check_params;
	if( $s_check_params === null ) {
		$s_check_params = ( db_is_pgsql() || config_get_global( 'db_type' ) == 'odbc_mssql' );
	}

	if(( $limit != -1 ) || ( $offset != -1 ) ) {
		$result = $g_db->selectLimit( $query, $limit, $offset, $arrParms );
	} else {
		$result = $g_db->execute( $query, $arrParms );
	}

	//$elapsed = number_format( microtime(true) - $start, 4 );

	if( ON == $g_db_log_queries ) {
		$dbType = config_get_global( 'db_type' );
		$lastOffset = 0;
		$i = 1;
		if( !( is_null( $arrParms ) || empty( $arrParms ) ) ) {
			while( preg_match( '/(\?)/', $query, $matches, PREG_OFFSET_CAPTURE, $lastOffset ) ) {
				if( $i <= count( $arrParms ) ) {
					if( is_null( $arrParms[$i - 1] ) ) {
						$replace = 'NULL';
					}
					else if( is_string( $arrParms[$i - 1] ) ) {
						$replace = "'" . $arrParms[$i - 1] . "'";
					}
					else if( is_integer( $arrParms[$i - 1] ) || is_float( $arrParms[$i - 1] ) ) {
						$replace = (float) $arrParms[$i - 1];
					}
					else if( is_bool( $arrParms[$i - 1] ) ) {
						switch( $dbType ) {
							case 'pgsql':
								$replace = "'" . $arrParms[$i - 1] . "'";
							break;
						default:
							$replace = $arrParms[$i - 1];
							break;
						}
					} else {
						echo( "Invalid argument type passed to query_bound(): $i" );
						exit( 1 );
					}
					$query = utf8_substr( $query, 0, $matches[1][1] ) . $replace . utf8_substr( $query, $matches[1][1] + utf8_strlen( $matches[1][0] ) );
					$lastoffset = $matches[1][1] + utf8_strlen( $replace );
				} else {
					$lastOffset = $matches[1][1] + 1;
				}
				$i++;
			}
		}
		//log_event( LOG_DATABASE, array( $query, $elapsed), debug_backtrace() );
		//array_push( $g_queries_array, array( $query, $elapsed ) );
	} else {
		//array_push( $g_queries_array, array( '', $elapsed ) );
	}

	if( !$result ) {
		db_error( $query );
		trigger_error( ERROR_DB_QUERY_FAILED, ERROR );
		return false;
	} else {
		return $result;
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
 * @param ADORecordSet $result Database Query Record Set to retrieve record count for.
 * @return int Record Count
 */
function db_affected_rows( $result ) {
	global $g_db;

	return $result->rowCount();
}

/**
 * Retrieve the next row returned from a specific database query
 * @param bool|ADORecordSet $p_result Database Query Record Set to retrieve next result for.
 * @return array Database result
 */
function db_fetch_array( &$result ) {
	global $g_db, $g_db_type;

	return $result->fetch();
}

/**
 * Retrieve a result returned from a specific database query
 * @param bool|ADORecordSet $result Database Query Record Set to retrieve next result for.
 * @param int $index1 Column to retrieve (optional)
 * @return mixed Database result
 */
function db_result( $result, $index1 = 0 ) {
	return $result->fetchColumn($index1);
}

/**
 * return the last inserted id for a specific database table
 * @param string $table a valid database table name
 * @return int last successful insert id
 */
function db_insert_id( $table = null, $field = "id" ) {
	global $g_db;

	return $g_db->getInsertId( $table, $field );
}

/**
 * Check if the specified table exists.
 * @param string $tableName a valid database table name
 * @return bool indicating whether the table exists
 */
function db_table_exists( $tableName ) {
	if( is_blank( $tableName ) ) {
		return false;
	}

	$tables = db_get_table_list();

	# Can't use in_array() since it is case sensitive
	$tableName = utf8_strtolower( $tableName );
	foreach( $tables as $currentTable ) {
		if( utf8_strtolower( $currentTable ) == $tableName ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if the specified table index exists.
 * @param string $tableName a valid database table name
 * @param string $indexName a valid database index name
 * @return bool indicating whether the index exists
 */
function db_index_exists( $tableName, $indexName ) {
	global $g_db, $g_db_schema;

	if( is_blank( $indexName ) || is_blank( $tableName ) ) {
		return false;

		// no index found
	}

	$indexes = $g_db->getIndexes( $tableName );

	# Can't use in_array() since it is case sensitive
	$indexName = utf8_strtolower( $indexName );
	foreach( $indexes as $currentIndexName => $currentIndexObj ) {
		if( utf8_strtolower( $currentIndexName ) == $indexName ) {
			return true;
		}
	}
	return false;
}

/**
 * Check if the specified field exists in a given table
 * @param string $fieldName a database field name
 * @param string $tableName a valid database table name
 * @return bool indicating whether the field exists
 */
function db_field_exists( $fieldName, $tableName ) {
	global $g_db;
	$columns = db_field_names( $tableName );
	return in_array( $fieldName, $columns );
}

/**
 * Retrieve list of fields for a given table
 * @param string $tableName a valid database table name
 * @return array array of fields on table
 */
function db_field_names( $tableName ) {
	global $g_db;
	$columns = $g_db->getColumns( $tableName );
	return is_array( $columns ) ? $columns : array();
}

/**
 * send both the error number and error message and query (optional) as paramaters for a triggered error
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_error( $query = null ) {
	global $g_db;
	if( null !== $query ) {
		error_parameters( /* $g_db->ErrorNo(), */ $g_db->getLastError(), $query );
	} else {
		error_parameters( /* $g_db->ErrorNo(), */ $g_db->getLastError() );
	}
}

/**
 * close the connection.
 * Not really necessary most of the time since a connection is automatically closed when a page finishes loading.
 */
function db_close() {
	global $g_db;

	$result = $g_db->close();
}

/**
 * prepare a string before DB insertion
 * @param string $string unprepared string
 * @return string prepared database query string
 * @deprecated db_query_bound should be used in preference to this function. This function may be removed in 1.2.0 final
 */
function db_prepare_string( $string ) {
	return $string;
}

/**
 * prepare a binary string before DB insertion
 * @param string $string unprepared binary data
 * @return string prepared database query string
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_prepare_binary_string( $string ) {
	global $g_db;
	$dbType = config_get_global( 'db_type' );

	switch( $dbType ) {
		case 'mssql':
		case 'odbc_mssql':
		case 'ado_mssql':
			$content = unpack( "H*hex", $string );
			return '0x' . $content['hex'];
			break;
		case 'postgres':
		case 'postgres64':
		case 'postgres7':
		case 'pgsql':
			return '\'' . pg_escape_bytea( $string ) . '\'';
			break;
		default:
			return '\'' . db_prepare_string( $string ) . '\'';
			break;
	}
}

/**
 * prepare a int for database insertion.
 * @param int $int integer
 * @return int integer
 * @deprecated db_query_bound should be used in preference to this function. This function may be removed in 1.2.0 final
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_prepare_int( $int ) {
	return (int) $int;
}

/**
 * prepare a boolean for database insertion.
 * @param boolean $p_boolean boolean
 * @return int integer representing boolean
 * @deprecated db_query_bound should be used in preference to this function. This function may be removed in 1.2.0 final
 * @todo Use/Behaviour of this function should be reviewed before 1.2.0 final
 */
function db_prepare_bool( $bool ) {
	return (int) (bool) $bool;
}

/**
 * return current timestamp for DB
 * @todo add param bool $gmt whether to use GMT or current timezone (default false)
 * @return string Formatted Date for DB insertion e.g. 1970-01-01 00:00:00 ready for database insertion
 */
function db_now() {
	global $g_db;

	return time();
}

/**
 * convert minutes to a time format [h]h:mm
 * @param int $min integer representing number of minutes
 * @return string representing formatted duration string in hh:mm format.
 */
function db_minutes_to_hhmm( $min = 0 ) {
	return sprintf( '%02d:%02d', $min / 60, $min % 60 );
}

/**
 * A helper function that generates a case-sensitive or case-insensitive like phrase based on the current db type.
 * The field name and value are assumed to be safe to insert in a query (i.e. already cleaned).
 * @param string $fieldName The name of the field to filter on.
 * @param bool $caseSensitive true: case sensitive, false: case insensitive
 * @return string returns (field LIKE 'value') OR (field ILIKE 'value')
 */
function db_helper_like( $fieldName, $caseSensitive = false ) {
	$likeKeyword = 'LIKE';

	if( $caseSensitive === false ) {
		if( db_is_pgsql() ) {
			$likeKeyword = 'ILIKE';
		}
	}

	return "($fieldName $likeKeyword " . db_param() . ')';
}

/**
 * A helper function to compare two dates against a certain number of days
 * @param $date1IdOrColumn
 * @param $date2IdOrColumn
 * @param $limitString
 * @return string returns database query component to compare dates
 * @todo Check if there is a way to do that using ADODB rather than implementing it here.
 */
function db_helper_compare_days( $date1IdOrColumn, $date2IdOrColumn, $limitString ) {
	$dbType = config_get_global( 'db_type' );

	$date1 = $date1IdOrColumn;
	$date2 = $date2IdOrColumn;
	if( is_int( $date1IdOrColumn ) ) {
		$date1 = db_param();
	}
	if( is_int( $date2IdOrColumn ) ) {
		$date2 = db_param();
	}

	return '((' . $date1 . ' - ' . $date2 .')' . $limitString . ')';
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

	$uniqueQueries = 0;
	$shownQueries = array();
	foreach( $g_queries_array as $valArray ) {
		if( !in_array( $valArray[0], $shownQueries ) ) {
			$uniqueQueries++;
			array_push( $shownQueries, $valArray[0] );
		}
	}
	return $uniqueQueries;
}

/**
 * get total time for queries
 * @return int
 */
function db_time_queries() {
	global $g_queries_array;
	$count = count( $g_queries_array );
	$total = 0;
	for( $i = 0;$i < $count;$i++ ) {
		$total += $g_queries_array[$i][1];
	}
	return $total;
}

/**
 * get database table name
 * @return string containing full database table name
 */
function db_get_table( $option ) {
	$table = $option;
	$prefix = config_get_global( 'db_table_prefix' );
	$suffix = config_get_global( 'db_table_suffix' );
	if ( $prefix ) {
		$table = $prefix . '_' . $table;
	}
	if ( $suffix ) {
		$table .= $suffix;
	}
	return $table;
}

/**
 * get list database tables
 * @return array containing table names
 */
function db_get_table_list() {
	global $g_db;
	return $g_db->getTables();
}
