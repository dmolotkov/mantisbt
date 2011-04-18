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

/**
 * MYSQL PDO driver class.
 * @package MantisBT
 * @subpackage classes
 */
class MantisDatabase_PDO_Mysql extends MantisDatabase_PDO {
    /**
     * Returns the driver-dependent DSN for PDO based on members stored by connect.
     * Must be called after connect (or after $dbname, $dbhost, etc. members have been set).
     * @return string driver-dependent DSN
     */
    protected function get_dsn() {
		return  'mysql:host=' . $this->dbhost . ';dbname=' . $this->dbname;
	}
	
	/**
	 * Returns whether driver is installed
	 * @return bool
	 */
    public function driver_installed() {
		return extension_loaded( 'pdo_mysql' );
	}	

	/**
	 * Returns db type string
	 * @return string
	 */
	public function get_dbtype() {
		return 'mysql';
	}
	
	/**
	 * Returns PDO options
	 * @return array
	 */
    protected function get_pdooptions() {
		$t_options = parent::get_pdooptions();
		$t_options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8';
        return $t_options;
    }
	
	/**
	 * @param string $sql
	 * @param int $p_limit
	 * @param int $p_offset
	 * @param array $arr_parms
	 * @return object
	 */
	public function SelectLimit( $sql, $p_limit, $p_offset, array $arr_parms = null) {
		$t_stroffset = ($p_offset>=0) ? " OFFSET $p_offset" : '';

		if ($p_limit < 0) $p_limit = '18446744073709551615'; 

		return $this->execute($sql . ' LIMIT ' . (int)$p_limit . $t_stroffset , $arr_parms);
	}

	/**
	 * @param string $p_name
	 * @return bool
	 */	
	public function database_exists( $p_name ) {
		$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?";
		try {
			$t_result = $this->execute( $sql, array( $p_name ) );
		} catch (PDOException $ex) {
			throw new MantisDatabaseException(ERROR_DB_QUERY_FAILED, $ex->getMessage());
			return false;
		}
		if ($t_result) {
			$t_value = $t_result->fetch();
			if( $t_value !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param bool $usecache
	 * @return array
	 */	
	public function get_tables($usecache=true) {
        if ($usecache and $this->tables !== null) {
            return $this->tables;
        }
        $this->tables = array();
        $sql = "SHOW TABLES";
		
		$t_result = $this->execute( $sql );
        if ($t_result) {
            while ($arr = $t_result->fetch()) {
                $this->tables[] = $arr[0];
            }
        }
        return $this->tables;	
	}

	/**
	 * @param string $table
	 * @return array
	 */
    public function get_indexes($table) {
        $t_indexes = array();
		$sql = "SHOW INDEXES FROM $table";
		$t_result = $this->execute( $sql );
		
        if ($t_result) {
            while ($arr = $t_result->fetch()) {
                $t_indexes[strtolower( $arr['key_name'] )] = array( strtolower( $arr['column_name'] ), $arr['non_unique'] );
            }
        }
		return $t_indexes;	
	}

	/**
	 * @param string $table
	 * @param bool $usecache
	 * @return array
	 */	
	public function get_columns($table, $usecache=true) {
		if ($usecache and isset($this->columns[$table])) {
            return $this->columns[$table];
        }

        $this->columns[$table] = array();

        $sql = "SHOW COLUMNS FROM $table";
		$t_result = $this->execute( $sql );
        if ($t_result) {
            while ($arr = $t_result->fetch()) {
                $this->columns[$table][] = strtolower( $arr[0] );
            }
        }
		return $this->columns[$table];
	}
}

