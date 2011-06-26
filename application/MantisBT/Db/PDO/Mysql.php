<?php
# MantisBT - a php based bugtracking system

# Copyright (C) 2002 - 2011  MantisBT Team - mantisbt-dev@lists.sourceforge.

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
use MantisBT\Db\DriverInterface;
use MantisBT\Db\PDO\PDOAbstract;
use MantisBT\Exception\Db AS DbException;
use \PDO;
use \PDOException;

/**
 * MYSQL PDO driver class.
 * @package MantisBT
 * @subpackage classes
 */
class Mysql extends PDOAbstract implements DriverInterface {
    /**
     * Returns the driver-dependent DSN for PDO based on members stored by connect.
     * Must be called after connect (or after $dbname, $dbhost, etc. members have been set).
     * @return string driver-dependent DSN
     */
    protected function getDsn() {
		return  'mysql:host=' . $this->dbHost . ';dbname=' . $this->dbName;
	}
	
	/**
	 * Returns whether driver is installed
	 * @return bool
	 */
    public function driverInstalled() {
		return extension_loaded( 'pdo_mysql' );
	}	

	/**
	 * Returns db type string
	 * @return string
	 */
	public function getDbType() {
		return 'mysql';
	}
	
	/**
	 * Returns PDO options
	 * @return array
	 */
    protected function getPdoOptions() {
		$options = parent::getPdoOptions();
		$options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8';
        return $options;
    }
	
	/**
	 * @param string $sql
	 * @param int $limit
	 * @param int $offset
	 * @param array $arrParms
	 * @return object
	 */
	public function selectLimit( $sql, $limit, $offset, array $arrParms = null) {
		$stroffset = ($offset>=0) ? " OFFSET $offset" : '';

		if ($limit < 0) $limit = '18446744073709551615'; 

		return $this->execute( $sql . ' LIMIT ' . (int)$limit . $stroffset , $arrParms );
	}

	/**
	 * @param string $p_name
	 * @return bool
	 */	
	public function databaseExists( $name ) {
		$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?";
		try {
			$result = $this->execute( $sql, array( $name ) );
		} catch (PDOException $ex) {
			throw new DbException(ERROR_DB_QUERY_FAILED, $ex->getMessage());
			return false;
		}
		if ($result) {
			$value = $result->fetch();
			if( $value !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param bool $useCache
	 * @return array
	 */	
	public function getTables($useCache=true) {
        if ($useCache and $this->tables !== null) {
            return $this->tables;
        }
        $this->tables = array();
        $sql = "SHOW TABLES";
		
		$result = $this->execute( $sql );
        if ( $result ) {
            while( $arr = $result->fetch() ) {
                $this->tables[] = $arr[0];
            }
        }
        return $this->tables;	
	}

	/**
	 * @param string $table
	 * @return array
	 */
    public function getIndexes( $table ) {
        $indexes = array();
		$sql = "SHOW INDEXES FROM $table";
		$result = $this->execute( $sql );

        if ($result) {
            while ($arr = $result->fetch()) {
                $indexes[strtolower( $arr['key_name'] )] = array( strtolower( $arr['column_name'] ), $arr['non_unique'] );
            }
        }
		return $indexes;
	}

	/**
	 * @param string $table
	 * @param bool $usecache
	 * @return array
	 */	
	public function getColumns( $table, $useCache=true ) {
		if ( $useCache and isset( $this->columns[$table] ) ) {
            return $this->columns[$table];
        }

        $this->columns[$table] = array();

        $sql = "SHOW COLUMNS FROM $table";
		$result = $this->execute( $sql );
        if ($result) {
            while( $arr = $result->fetch() ) {
                $this->columns[$table][] = strtolower( $arr[0] );
            }
        }
		return $this->columns[$table];
	}
}
