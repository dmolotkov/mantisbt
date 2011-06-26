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
use MantisBT\Db\PDO\Mysql;
use MantisBT\Exception\Db;

/**
 * Abstract database driver class.
 * @package MantisBT
 * @subpackage classes
 */
abstract class DriverAbstract {
    /**
	 * array - cache of column info 
	 */
    protected $columns = array(); 
    /**
	 * array - cache of table info 
	 */
    protected $tables  = null;

    /** 
	 * string - db host name 
	 */
    protected $dbHost;
    /** 
	 * string - db host user 
	 */
    protected $dbUser;
    /** 
	 * string - db host password 
	 */
    protected $dbPass;
    /** 
	 * string - db name 
	 */
    protected $dbName;
    /** 
	 * string - db dsn
	 */
    protected $dbDsn;
	
    /** @var array Database or driver specific options, such as sockets or TCPIP db connections */
    protected $dbOptions;

    /** @var int Database query counter (performance counter).*/
    protected $queries = 0;

    /** @var bool Debug level */
    protected $debug  = false;

    /**
     * Destructor
     */
    public function __destruct() {
        $this->dispose();
    }

    /**
     * Loads and returns a database instance with the specified type and library.
     * @param string $type database type of the driver (e.g. pdo_pgsql)
     * @return MantisBT\Db object or null if error
     * @todo throw an error if $type doesn't match a supported driver type
     */
    public static function getDriverInstance($type) {
        static $driver = null;
        if( is_null( $driver ) ) {
		    $type = explode( '_', $type );
		    switch( strtolower( $type[0] ) ) {
			    case 'pdo':
				    $driverType = 'PDO';
		    }
            $classname = 'MantisBT\\Db\\' . $driverType . '\\' . ucfirst($type[1]);
            $driver = new $classname();
        }
        return $driver;
    }

    /**
     * Diagnose database and tables, this function is used
     * to verify database and driver settings, db engine types, etc.
     *
     * @return string null means everything ok, string means problem found.
     */
    public function diagnose() {
        return null;
    }

    /**
     * Attempt to create the database
     * @param string $dbHost
     * @param string $dbUser
     * @param string $dbPass
     * @param string $dbName
     *
     * @return bool success
     */
    public function createDatabase( $dbHost, $dbUser, $dbPass, $dbName, array $dbOptions=null ) {
        return false;
    }

    /**
     * Close database connection and release all resources
     * and memory (especially circular memory references).
     * Do NOT use connect() again, create a new instance if needed.
     * @return void
     */
    public function dispose() {
        $this->columns = array();
        $this->tables  = null;
    }

    /**
     * Called before each db query.
     * @param string $sql
     * @param array array of parameters
     * @param int $type type of query
     * @param mixed $extrainfo driver specific extra information
     * @return void
     */
    protected function queryStart( $sql, array $params=null ) {
        $this->last_sql       = $sql;
        $this->last_params    = $params;
        $this->last_time      = microtime(true);

		$this->queries++;
    }

    /**
     * Called immediately after each db query.
     * @param mixed db specific result
     * @return void
     */
    public function queryEnd( $result ) {
        if ( $result !== false ) {
            return;
        }
    }

    /**
     * Reset internal column details cache
     * @param string $table - empty means all, or one if name of table given
     * @return void
     */
    public function resetCaches() {
        $this->columns = array();
        $this->tables  = null;
    }

    /**
     * Attempt to change db encoding toUTF-8 if possible
     * @return bool success
     */
    public function changeDbEncoding() {
        return false;
    }

    /**
     * Enable/disable debugging mode
     * @param bool $state
     * @return void
     */
    public function setDebug($state) {
        $this->debug = $state;
    }

    /**
     * Returns debug status
     * @return bool $state
     */
    public function getDebug() {
        return $this->debug;
    }
	
    /**
     * Returns number of queries done by this database
     * @return int
     */
    public function perfGetQueries() {
        return $this->queries;
    }
	
	/**
     * Verify sql parameters
     * @param string $sql query or part of it
     * @param array $params query parameters
     * @return array (sql, params, type of params)
     */
    public function checkSqlParameters($sql, array $params=null) {
        $params = (array)$params; // make null array if needed

        // cast booleans to 1/0 int
        foreach ($params as $key => $value) {
            $params[$key] = is_bool($value) ? (int)$value : $value;
        }

        $t_count = substr_count($sql, '?');

        if (!$t_count) {
			return array($sql, array() );
        }

		if ($t_count == count($params)) {
			return array($sql, array_values($params));
		}

		$a = new stdClass;
		$a->expected = $t_count;
		$a->actual = count($params);
		$a->sql = $sql;
		$a->params = $params;
		throw new Db( ERROR_DB_QUERY_FAILED, $a );
    }
	
	/* legacy functions */
	public function legacyNullDate() {
        return "1970-01-01 00:00:01";
    }
	
	public function legacyTimestamp( $date ) {
		$timestamp = strtotime( $date );
		if ( $timestamp == false ) {
			trigger_error( ERROR_GENERIC, ERROR );
		}
		return $timestamp;
	}
}
