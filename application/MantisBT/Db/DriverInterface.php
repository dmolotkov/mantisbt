<?php
# MantisBT - a php based bugtracking system

# Copyright (C) 2002 - 2009  MantisBT Team - mantisbt-dev@lists.sourceforge.

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

namespace MantisBT\Db;

/**
 * Abstract database driver class.
 * @package MantisBT
 * @subpackage classes
 */
interface DriverInterface {
    /**
     * Detects if all needed PHP stuff installed.
     * Note: can be used before connect()
     * @return mixed true if ok, string if something
     */
    public function driverInstalled();

    /**
     * Returns database driver type
     * Note: can be used before connect()
     * @return string db type mysql, pgsql, sqlsrv
     */
    public function getDbType();

    /**
     * Diagnose database and tables, this function is used
     * to verify database and driver settings, db engine types, etc.
     *
     * @return string null means everything ok, string means problem found.
     */
    public function diagnose();

    /**
     * Connect to db
     * Must be called before other methods.
     * @param string $dbhost
     * @param string $dbuser
     * @param string $dbpass
     * @param string $dbname
     * @param mixed $prefix string means moodle db prefix, false used for external databases where prefix not used
     * @param array $dboptions driver specific options
     * @return bool true
     * @throws dml_connection_exception if error
     */
    public function connect( $dsn, $dbHost, $dbUser, $dbPass, $dbName, array $dbOptions=null );

    /**
     * Attempt to create the database
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPass
     * @param string $dbName
     *
     * @return bool success
     */
    public function createDatabase( $dbHost, $dbUser, $dbPass, $dbName, array $dbOptions=null );

    /**
     * Close database connection and release all resources
     * and memory (especially circular memory references).
     * Do NOT use connect() again, create a new instance if needed.
     * @return void
     */
    public function dispose();

    /**
     * Called before each db query.
     * @param string $sql
     * @param array array of parameters
     * @param int $type type of query
     * @param mixed $extrainfo driver specific extra information
     * @return void
     */
    public function queryStart( $sql, array $params=null );

    /**
     * Called immediately after each db query.
     * @param mixed db specific result
     * @return void
     */
    public function queryEnd( $result );

    /**
     * Returns database server info array
     * @return array
     */
    public function getServerInfo();

    /**
     * Returns last error reported by database engine.
     * @return string error message
     */
    public function getLastError();

    /**
     * Return tables in database WITHOUT current prefix
     * @return array of table names in lowercase and without prefix
     */
    public function getTables( $useCache=true );

    /**
     * Return table indexes - everything lowercased
     * @return array of arrays
     */
    public function getIndexes( $table );

    /**
     * Returns detailed information about columns in table. This information is cached internally.
     * @param string $table name
     * @param bool $useCache
     * @return array of database_column_info objects indexed with column names
     */
    public function getColumns( $table, $useCache=true );


    /**
     * Reset internal column details cache
     * @param string $table - empty means all, or one if name of table given
     * @return void
     */
    public function resetCaches();

    /**
     * Attempt to change db encoding toUTF-8 if possible
     * @return bool success
     */
    public function changeDbEncoding();

	public function getInsertId( $table );

    /**
     * Enable/disable debugging mode
     * @param bool $state
     * @return void
     */
    public function setDebug( $state );

    /**
     * Returns debug status
     * @return bool $state
     */
    public function getDebug();

    /**
     * Execute general sql query. Should be used only when no other method suitable.
     * Do NOT use this to make changes in db structure, use database_manager::execute_sql() instead!
     * @param string $sql query
     * @param array $params query parameters
     * @return bool true
     * @throws MantisBT\Exception\Db if error
     */
    public function execute( $sql, array $params=null );

    /**
     * @param string $sql query
	 * @param int $p_limit Number of results to return
	 * @param int $p_offset offset query results for paging
     * @param array $params query parameters
     * @return bool true
     * @throws MantisBT\Exception\Db if error
     */
    public function selectLimit( $sql, $limit, $offset, array $arrParms = null );

    /**
     * Returns number of queries done by this database
     * @return int
     */
    public function perfGetQueries();

    /**
     * Returns whether database is connected
     * @return bool
     */
    public function isConnected();

	/**
     * Verify sql parameters
     * @param string $sql query or part of it
     * @param array $params query parameters
     * @return array (sql, params, type of params)
     */
    public function checkSqlParameters( $sql, array $params=null );

	/* legacy functions */
	public function legacyNullDate();
	public function legacyTimestamp( $date );
}
