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

/**
 * Abstract database driver class.
 * @package MantisBT
 * @subpackage classes
 */
abstract class MantisDatabase {
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
    protected $dbhost;
    /** 
	 * string - db host user 
	 */
    protected $dbuser;
    /** 
	 * string - db host password 
	 */
    protected $dbpass;
    /** 
	 * string - db name 
	 */
    protected $dbname;
    /** 
	 * string - db dsn
	 */
    protected $dbdsn;
	
    /** @var array Database or driver specific options, such as sockets or TCPIP db connections */
    protected $dboptions;

    /** @var int Database query counter (performance counter).*/
    protected $queries = 0;

    /** @var bool Debug level */
    protected $debug  = false;

    /**
     * Contructor
     */
    public function __construct() {
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->dispose();
    }

    /**
     * Detects if all needed PHP stuff installed.
     * Note: can be used before connect()
     * @return mixed true if ok, string if something
     */
    public abstract function driver_installed();

    /**
     * Loads and returns a database instance with the specified type and library.
     * @param string $type database type of the driver (e.g. pdo_pgsql)
     * @return MantisDatabase driver object or null if error
     */
    public static function get_driver_instance($type) {
		$t_type = explode( '_', $type );
		switch( strtolower( $t_type[0] ) ) {
			case 'pdo':
				$t_driver_type = 'PDO';
		}
        $classname = 'MantisDatabase_' . $t_driver_type . '_' . ucfirst($t_type[1]);
        return new $classname();
    }

    /**
     * Returns database driver type
     * Note: can be used before connect()
     * @return string db type mysql, pgsql, sqlsrv
     */
    protected abstract function get_dbtype();


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
    public abstract function connect($dsn, $dbhost, $dbuser, $dbpass, $dbname, array $dboptions=null);


    /**
     * Attempt to create the database
     * @param string $dbhost
     * @param string $dbuser
     * @param string $dbpass
     * @param string $dbname
     *
     * @return bool success
     */
    public function create_database($dbhost, $dbuser, $dbpass, $dbname, array $dboptions=null) {
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
    protected function query_start($sql, array $params=null ) {
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
    protected function query_end($result) {
        if ($result !== false) {
            return;
        }
    }

    /**
     * Returns database server info array
     * @return array
     */
    public abstract function get_server_info();

    /**
     * Returns last error reported by database engine.
     * @return string error message
     */
    public abstract function get_last_error();

    /**
     * Return tables in database WITHOUT current prefix
     * @return array of table names in lowercase and without prefix
     */
    public abstract function get_tables($usecache=true);

    /**
     * Return table indexes - everything lowercased
     * @return array of arrays
     */
    public abstract function get_indexes($table);

    /**
     * Returns detailed information about columns in table. This information is cached internally.
     * @param string $table name
     * @param bool $usecache
     * @return array of database_column_info objects indexed with column names
     */
    public abstract function get_columns($table, $usecache=true);


    /**
     * Reset internal column details cache
     * @param string $table - empty means all, or one if name of table given
     * @return void
     */
    public function reset_caches() {
        $this->columns = array();
        $this->tables  = null;
    }

    /**
     * Attempt to change db encoding toUTF-8 if possible
     * @return bool success
     */
    public function change_db_encoding() {
        return false;
    }

	abstract public function get_insert_id( $p_table );

    /**
     * Enable/disable debugging mode
     * @param bool $state
     * @return void
     */
    public function set_debug($state) {
        $this->debug = $state;
    }

    /**
     * Returns debug status
     * @return bool $state
     */
    public function get_debug() {
        return $this->debug;
    }

    /**
     * Execute general sql query. Should be used only when no other method suitable.
     * Do NOT use this to make changes in db structure, use database_manager::execute_sql() instead!
     * @param string $sql query
     * @param array $params query parameters
     * @return bool true
     * @throws MantisDatabaseException if error
     */
    public abstract function execute($sql, array $params=null);

    /**
     * @param string $sql query
	 * @param int $p_limit Number of results to return
	 * @param int $p_offset offset query results for paging
     * @param array $params query parameters
     * @return bool true
     * @throws MantisDatabaseException if error
     */
    public abstract function SelectLimit( $sql, $p_limit, $p_offset, array $arr_parms = null );
	
    /**
     * Returns number of queries done by this database
     * @return int
     */
    public function perf_get_queries() {
        return $this->queries;
    }

    /**
     * Returns whether database is connected
     * @return bool
     */	
    public abstract function IsConnected();
	
	
	/**
     * Verify sql parameters
     * @param string $sql query or part of it
     * @param array $params query parameters
     * @return array (sql, params, type of params)
     */
    protected function check_sql_parameters($sql, array $params=null) {
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
		throw new MantisDatabaseException(ERROR_DB_QUERY_FAILED, $a);
    }
	
	/* legacy functions */
	public function legacy_null_date() {
        return "1970-01-01 00:00:01";
    }
	
	public function legacy_timestamp( $p_date ) {
		$p_timestamp = strtotime( $p_date );
		if ( $p_timestamp == false ) {
			trigger_error( ERROR_GENERIC, ERROR );
		}
		return $p_timestamp;
	}
}
