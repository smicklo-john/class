<?php
 
class mysqldb {

    /**
     * The host.
     *
     * @var string $hostname The name of the host.
     */
    private $hostname;
    
    /**
     * MySQL user.
     *
     * @var string $username MySQL user name.
     */
    private $username;
    
    /**
     * MySQL password.
     *
     * @var string $password Password for MySQL user.
     */
    private $password;
    
    /**
     * The database name.
     *
     * @var string $database The name of the database.
     */
    private $database;
    
    /**
     * The connection.
     *
     * @var object $_connect The connection object.
     */
    private $_connect;
    
    /**
     * The query.
     *
     * @var object $sqlquery The query object.
     */
    private $sqlquery;
    
    /**
     * Get the called instance.
     *
     * @static
     * @access private
     */
    private static $instance = array();
    
    /**
     * Get the single instance.
     *
     * @static
     * @access public
     * @return object The single instance of.
     */
    public static function &get_instance() {
        $call = get_called_class();
        if(!isset(self::$instance[$call]))
            self::$instance[$call] = new static;
        return self::$instance[$call];
    }
    
    /**
     * Setter for connection options.
     *
     * @access private
     * @param string $hostname The name of the host.
     * @param string $username MySQL user name.
     * @param string $password Password for MySQL user.
     * @param string $database The name of the database.
     */
    private function set_options($hostname, $username, $password, $database) {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    }
    
    /**
     * Verify that a connection exists.
     *
     * @access public
     * @return boolean Whether or not a connection exists.
     */
    public function has_connection() {
        return (isset($this->_connect) && is_object($this->_connect) === true && (!$this->_connect->connect_errno)) ? true : false;
    }
    
    /**
     * The global access point for the connection wrapper.
     *
     * @access public
     * @return object The connection wrapper.
     */
    public function _() {
        if($this->has_connection())
            return $this->_connect;
    }
    
    /**
     * Attempt to connect to the server.
     *
     * @access public
     * @param string $hostname The name of the host.
     * @param string $username MySQL user name.
     * @param string $password Password for MySQL user.
     * @param string $database The name of the database.
     * @return boolean Whether or not the connection was created.
     */
    public function connect($hostname, $username, $password, $database) {
        # An active connection already exists.
        if($this->has_connection()) return true;
        
        # A new connection is being requested.
        $this->set_options(
            $hostname,
            $username,
            $password,
            $database
        );
        
        # Try to contact the server.
        $this->_connect = new mysqli(
            $this->hostname,
            $this->username,
            $this->password,
            $this->database
        );
        
        # Successful connection.
        if(!$this->_connect->connect_errno) {
            
            # Load the character set.
            $this->_connect->set_charset('utf8'); // UTF-8 as standard.
            
            return true;
        }
        
        # Connection failed.
        return false;
    }
    
    /**
     * Escape special characters within a string for use in a MySQL statement.
     *
     * @access public
     * @param string $string The string to escape.
     * @return string The escaped string.
     */
    public function escape($string) {
        if(get_magic_quotes_gpc()) $string = stripslashes($string);
        return $this->_()->real_escape_string($string);
    }
    
    /**
     * Execute the query.
     *
     * @access public
     * @param string $query The query to execute.
     * @return object The result set.
     */
    public function execute($statement) {
        # Connection time-out.
        if(!$this->has_connection()) return false;
        
        # Send the request.
        $this->sqlquery = $this->_()->query($statement);
        
        # Get the result.
        return ($this->sqlquery === false) ? false : $this->sqlquery;
    }
    
    /**
     * The query returned a result set.
     *
     * @access private
     * @return boolean Whether or not a result was returned.
     */
    private function has_result() {
        return (isset($this->sqlquery) && is_object($this->sqlquery) === true) ? true : false;
    }
    
    /**
     * Get the result set.
     *
     * @access public
     * @return object The result set.
     */
    public function get_result() {
        if($this->has_result())
            return $this->sqlquery->fetch_object();
    }
    
    /**
     * The number of records returned.
     *
     * @access public
     * @return int The number of records returned by the query.
     */
    public function get_records() {
        if($this->has_result() && $this->sqlquery->num_rows > 0)
            return $this->sqlquery->num_rows;
        return false;
    }
    
    /**
     * Get the number of affected records.
     *
     * @access public
     * @return int The number of records affected by the most recent operation.
     */
    public function get_affected() {
        if($this->_()->affected_rows > 0)
            return $this->_()->affected_rows;
        return false;
    }
    
    /**
     * Get the INSERT ID.
     *
     * @access public
     * @return int The auto-generated ID used in the last query.
     */
    public function get_sequence() {
        return (int) $this->_()->insert_id;
    }
    
    /**
     * Free the result.
     *
     * @access public
     * @return void
     */
    public function free() {
        if($this->has_result())
            $this->sqlquery->free();
    }
    
    /**
     * Get the server time.
     *
     * @access public
     * @return string The timestamp, from the server.
     */
    public function get_server_time() {
        $request = $this->execute("SELECT NOW() AS `server_timestamp`");
        if($this->has_result() === true) {
            $timestamp = $this->get_result();
            return $timestamp->server_timestamp;
        }
        return date('Y-m-d H:i:s', strtotime('now'));
    }
    
    /**
     * Get a single field of data from a table.
     *
     * @access public
     * @param string $table The table to pull the data from.
     * @param string $field The field to pull.
     * @param string $where (optional) The conditional WHERE clause.
     * @param string $limit (optional) The number of records to pull.
     * @return string The field of data.
     */
    public function get_from_db($table, $field, $where = '', $limit = '') {
        # Create the query.
        $request = 'SELECT `'. $field .'` FROM `' . $table . '`';
        $request .= (!empty($where)) ? ' WHERE ' . $where : '';
        $request .= (!empty($limit)) ? ' LIMIT ' . $limit : '';
        
        # Run the query.
        $this->execute($request);
        
        # Get the result.
        if($this->has_result()) {
            $result = $this->get_result();
            return $result->$field;
        }
        
        return false;
    }
    
    /**
     * Close the connection.
     *
     * @access public
     * @return void
     */
    public function close() {
        # But, only if one exists.
        if($this->has_connection())
            $this->_()->close();
    }
}