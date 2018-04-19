<?php

class MysqlLibrary implements DBInterface
{
    private $_host = '127.0.0.1';
    private $_username = 'root';
    private $_password = '';
    private $_database = 'files';
    private $_connection;
    
    public function __construct()
    {
        $this->connect();
    }
    
    public function escape($string)
    {
        return $this->_connection->real_escape_string($string);
    }
    
    public function connect()
    {
        $this->_connection = new mysqli($this->_host, $this->_username, $this->_password, $this->_database);
        
        syslog(LOG_INFO, 'Connected to MySQL database');
    }
    
    public function query($query)
    {
        if(!$this->_connection->query($query)) {
            throw new Exception('Error executing query: ' . $query);
        }
    }
}
