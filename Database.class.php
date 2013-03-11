<?php

/**
 * Database class
 * Allows lazy connection, usually methods return null when disconnected.
 * 
 * @author Jochem Kuijpers - 2013
 */
class Database {
    private $link;
    private $stmt;
    private $result;
    private $numQueries;
    
    /**
     * If the optional parameters are set, it will call connect()
     * 
     * @param type $host
     * @param type $user
     * @param type $pass
     * @param type $db
     * @param type $port
     * @param type $charset
     */
    public function __construct($host = '', $user = '', $pass = '', $db = '', $port = 3306, $charset = '') {
        $this->numQueries = 0;
        if (!empty($host) && !empty($user) && !empty($db)) {
            $this->connect($host, $user, $pass, $db, $port, $charset);
        }
    }
    
    /**
     * Just a counter
     */
    public function getNumQueries() {
        return $this->numQueries;
    }
    
    /**
     * Calls disconnect()
     */
    public function __destruct() {
        $this->disconnect();
    }
    
    /**
     * Disconnects the database, cleans up
     */
    public function disconnect() {
        if (is_object($this->link)) {
            if (is_object($this->stmt)) {
                $this->close_statement();
            }
            $this->link->close();
            $this->link = null;
            return true;
        }
        return null;
    }
    
    /**
     * Tries to connect to the mysql server, throws exception when failed
     * 
     * @param type $host
     * @param type $user
     * @param type $pass
     * @param type $db
     * @param type $port
     * @param type $charset
     */
    public function connect($host, $user, $pass, $db, $port = 3306, $charset = '') {
        if (!is_object($this->link)) {
            $this->link = new mysqli($host, $user, $pass, $db, $port);
            if ($this->link->connect_error !== null) {
                die($this->link->connect_error);
            }
            if ($charset != '') {
                if (!$this->link->set_charset($charset)) {
                    die($this->link->error);
                }
            }
            return true;
        }
        return null;
    }
    
    /**
     * Internal use only, used to determine which type the parameter fits best
     * 
     * @param type $data
     */
    private function type(&$data) {
        if (is_numeric($data)) {
            if (is_int($data) || is_bool($data) || is_null($data)) {
                $data = intval($data);
                return 'i';
            }
            if (is_float($data) || is_double($data) || is_string($data)) {
                $data = doubleval($data);
                return 'd';
            }
            die('Invalid datatype!');
        } else {
            if (is_string($data)) {
                return 's';
            }
            die('Invalid datatype!');
        }
    }
    
    /**
     * Escapes strings for SQL
     * 
     * @param type $data
     */
    public function escape($data) {
        if (is_object($this->link)) {
            return $this->link->real_escape_string($data);
        }
        return null;
    }
    
    /**
     * Simple query, returns MySQLi Result
     * 
     * @param type $sql
     */
    public function query($sql) {
        if (is_object($this->link)) {
            $this->result = $this->link->query($sql);
            $this->numQueries += 1;
            if ($this->link->error != '') {
                die($this->link->error);
            }
            return $this->result;
        }
        return null;
    }
    
    /**
     * Simple query, returns an associated array of the first row
     * 
     * @param type $sql
     */
    public function query_fetch($sql) {
        if (is_object($this->link)) {
            return mysqli_fetch_assoc($this->query($sql));
        }
        return null;
    }
    
    /**
     * Simple query, returns a 2D associated array of all rows
     * 
     * @param type $sql
     */
    public function query_fetch_all($sql) {
        if (is_object($this->link)) {
            $this->query($sql);
            while($return[] = mysqli_fetch_assoc($this->result));
            // delete last element (because it's a null)
            array_pop($return);
            return $return;
        }
        return null;
    }
    
    /**
     * Simple query, returns only one field: the very first one (e.g. for operations like COUNT(*))
     * 
     * @param type $sql
     */
    public function query_fetch_field($sql) {
        if (is_object($this->link)) {
            $return = mysqli_fetch_row($this->query($sql));
            return ((is_array($return))?array_shift($return):$return);
        }
        return null;
    }
    
    /**
     * Prepares a SQL statement
     * 
     * @param type $sql
     */
    public function prepare($sql) {
        if (is_object($this->link)) {
            if (is_object($this->stmt)) {
                $this->close_statement();
            }
            if (!($this->stmt = $this->link->prepare($sql))) {
                die($this->link->error);
            }
            return true;
        }
        return null;
    }
    
    /**
     * Executes previously prepared statement, returns MySQLi Result
     * 
     * @param type $data
     */
    public function execute($data = null) {
        if (is_object($this->link)) {
            if (!is_array($data)) {
                die('SQL data should be an array!');
            }
            if (count($data)) {
                $types = '';
                $params = array();
                foreach($data as $var => $value) {
                    $types .= $this->type($value);
                    $params[] = &$value;
                }
                call_user_func_array(array($this->stmt, 'bind_param'), array_merge(array($types), $params));
            }
            $this->stmt->execute();
            $this->numQueries += 1;
            $this->stmt->store_result();
            $this->result = $this->stmt->result_metadata();
            return $this->result;
        }
        return null;
    }
    
    /**
     * Executes previously prepared statement, returns an associated array of the first row
     * 
     * @param type $data
     */
    public function execute_fetch($data = null) {
        if (is_object($this->link)) {
            $this->execute($data);
            
            
            while ($column = $this->result->fetch_field()) {
                $bindVarsArray[] = &$refArray[$column->name];
            }       
            call_user_func_array(array($this->stmt, 'bind_result'), $bindVarsArray);
            
            if (!$this->stmt->fetch()) {
                return false;
            }

            foreach($refArray as $column => $value) {
                $return[$column] = $value;
            }
            return $return;
        }
        return null;
    }
    
    /**
     * Executes previously prepared statement, returns a 2D associated array of all rows
     * 
     * @param type $data
     */
    public function execute_fetch_all($data = null) {
        if (is_object($this->link)) {
            $this->execute($data);

            while ($column = $this->result->fetch_field()) {
                $bindVarsArray[] = &$refArray[$column->name];
            }       
            call_user_func_array(array($this->stmt, 'bind_result'), $bindVarsArray);

            $i = 0;
            $return = array();
            while ($this->stmt->fetch()) {
                foreach($refArray as $column => $value) {
                    $return[$i][$column] = $value;
                }
                $i++;
            }
            if (empty($return)) { return false; }
            return $return;
        }
        return null;
    }
    
    /**
     * Executes previously prepared statement, returns only one field: the very first one (e.g. for operations like COUNT(*))
     * 
     * @param type $sql
     */
    public function execute_fetch_field($data = null) {
        if (is_object($this->link)) {
            $return = $this->execute_fetch($data);
            return ((is_array($return))?array_shift($return):$return);
        }
        return null;
    }
    
    /**
     * Closes the prepared statement, cleans up
     */
    public function close_statement() {
        if (is_object($this->link) && is_object($this->stmt)) {
            $this->stmt->free_result();
            $this->stmt->close();
            $this->stmt = null;
            $this->result = null;
            return true;
        }
        return null;
    }
    
    /**
     * Returns last inserted id
     */
    public function insertId() {
        if (is_object($this->link)) {
            return $this->link->insert_id;
        }
        return null;
    }
}