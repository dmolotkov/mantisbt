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
namespace MantisBT\Db\PDO;
use MantisBT\Db\DriverAbstract;
use MantisBT\Exception\Db AS DbException;
use \PDO;
use \PDOException;

/**
 * Abstract PDO database driver class.
 * @package MantisBT
 * @subpackage classes
 */
abstract class PDOAbstract extends DriverAbstract {
    protected $pdb;
    protected $lastError = null;

	/**
	 */	
    public function connect($dsn, $dbHost, $dbUser, $dbPass, $dbName, array $dbOptions=null) {
        $driverstatus = $this->driverInstalled();

        if ($driverstatus !== true) {
			error_parameters( 0, 'PHP Support for database is not enabled' );
			trigger_error( ERROR_DB_CONNECT_FAILED, ERROR );

            //throw new DbException('DatabaseDriverProblem', $driverstatus);
        }

		$this->dbHost = $dbHost;
		$this->dbUser = $dbUser;
		$this->dbPass = $dbPass;
		$this->dbName = $dbName;

        try{
            $this->pdb = new PDO( $this->getDsn(), $this->dbUser, $this->dbPass, $this->getPdoOptions());

            $this->pdb->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
            $this->pdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->postConnect();
            return true;
        } catch ( PDOException $ex ) {
            throw new DbException( ERROR_DB_QUERY_FAILED, $ex->getMessage() );
            return false;
        }
    }

    /**
     * Returns the DSN for PDO.
     * Must be called after $dbname, $dbhost, etc. have been set.
     * @return string DSN string
     */
    abstract protected function getDsn();

    /**
     * Returns connection attributes for PDO.
     * @return array array of PDO connection options
     */
    protected function getPdoOptions() {
        return array(PDO::ATTR_PERSISTENT => !empty($this->dboptions['dbpersist']));
    }

    /**
     * Post-Connect processing (if any)
     */
    protected function postConnect() {        
    }

    /**
     * Returns general database library name
     * @return string db type: pdo
     */
    protected function getDbLibrary() {
        return 'pdo';
    }

    /**
     * Returns localised database type name
     * Note: can be used before connect()
     * @return string
     * @todo where does get_string come from?
     */
    public function getName() {
        return get_string('pdo'.$this->getDbType(), 'install');
    }

    /**
     * Returns database server info array
     * @return array
     */
    public function getServerInfo() {
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
	 * @param string $table
     * @return int
     */
	public function getInsertId( $table ) {
		if ($id = $this->pdb->lastInsertId()) {
			return (int)$id;
		}
	}
	
    /**
     * Returns last error reported by database engine.
     * @return string error message
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Execute SQL query. 
     * @param string $sql query
     * @param array $params query parameters
     * @return bool success
     */
    public function execute( $sql, array $params=null ) {
        list( $sql, $params ) = $this->checkSqlParameters($sql, $params);

        $result = true;
        $this->queryStart( $sql, $params );

        try {
            $sth = $this->pdb->prepare( $sql );
            $sth->execute( $params );
        } catch ( PDOException $ex ) {
            $this->lastError = $ex->getMessage();
            $result = false;
        }

        $this->queryEnd( $result );
        return $result == true ? $sth : false;
    }

    /**
     */
    public function queryStart( $sql, array $params=null ) {
        $this->lastError = null;
        parent::queryStart( $sql, $params );
    }

    /**
     * Indicates if database is connected
     * @return bool
     */
	public function isConnected() {
		return true;
	}
}
