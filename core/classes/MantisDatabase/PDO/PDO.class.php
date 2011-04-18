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
 * Abstract PDO database driver class.
 * @package MantisBT
 * @subpackage classes
 */
abstract class MantisDatabase_PDO extends MantisDatabase {
    protected $pdb;
    protected $lastError = null;

	/**
	 */	
    public function __construct() {
        parent::__construct();
    }

	/**
	 */	
    public function connect($dsn, $dbhost, $dbuser, $dbpass, $dbname, array $dboptions=null) {
        $driverstatus = $this->driver_installed();

        if ($driverstatus !== true) {
			error_parameters( 0, 'PHP Support for database is not enabled' );
			trigger_error( ERROR_DB_CONNECT_FAILED, ERROR );

            //throw new MantisDatabaseException('DatabaseDriverProblem', $driverstatus);
        }

		$this->dbhost = $dbhost;
		$this->dbuser = $dbuser;
		$this->dbpass = $dbpass;
		$this->dbname = $dbname;

        try{
            $this->pdb = new PDO($this->get_dsn(), $this->dbuser, $this->dbpass, $this->get_pdooptions());

            $this->pdb->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
            $this->pdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->post_connect();
            return true;
        } catch (PDOException $ex) {
            throw new MantisDatabaseException(ERROR_DB_QUERY_FAILED, $ex->getMessage());
            return false;
        }
    }

    /**
     * Returns the DSN for PDO.
     * Must be called after $dbname, $dbhost, etc. have been set.
     * @return string DSN string
     */
    abstract protected function get_dsn();

    /**
     * Returns connection attributes for PDO.
     * @return array array of PDO connection options
     */
    protected function get_pdooptions() {
        return array(PDO::ATTR_PERSISTENT => !empty($this->dboptions['dbpersist']));
    }

    /**
     * Post-Connect processing (if any)
     */
	
    protected function post_connect() {        
    }

    /**
     * Returns general database library name
     * @return string db type: pdo
     */
    protected function get_dblibrary() {
        return 'pdo';
    }

    /**
     * Returns localised database type name
     * Note: can be used before connect()
     * @return string
     */
    public function get_name() {
        return get_string('pdo'.$this->get_dbtype(), 'install');
    }

    /**
     * Returns database server info array
     * @return array
     */
    public function get_server_info() {
        $result = array();
        try {
            $result['information'] = $this->pdb->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch(PDOException $ex) {}
        try {
            $result['version'] = $this->pdb->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch(PDOException $ex) {}
        return $result;
    }

    /**
     * Get last Insert ID
	 * @param string $p_table
     * @return int
     */
	
	public function get_insert_id( $p_table ) {
		if ($id = $this->pdb->lastInsertId()) {
			return (int)$id;
		}
	}
	
    /**
     * Returns last error reported by database engine.
     * @return string error message
     */
    public function get_last_error() {
        return $this->lastError;
    }

    /**
     * Execute SQL query. 
     * @param string $sql query
     * @param array $params query parameters
     * @return bool success
     */
    public function execute($sql, array $params=null) {
        list($sql, $params) = $this->check_sql_parameters($sql, $params);

        $result = true;
        $this->query_start($sql, $params);

        try {
            $sth = $this->pdb->prepare($sql);
            $sth->execute($params);
        } catch (PDOException $ex) {
            $this->lastError = $ex->getMessage();
            $result = false;
        }

        $this->query_end($result);
        return $result == true ? $sth : false;
    }

    /**
     */
    protected function query_start($sql, array $params=null) {
        $this->lastError = null;
        parent::query_start($sql, $params);
    }

    /**
     * Indicates if database is connected
     * @return bool
     */
	public function IsConnected() {
		return true;
	}
}

