<?php

class FileProcessor
{
    private $_path;
    private $_filename;
    private $_db;
    
    public function __construct($filename)
    {
        if(empty($filename)) {
            throw new Exception('Filepath is empty');
        }
        
        if(!file_exists('uploaded/' . $filename)) {
            throw new Exception('Filepath does not exist');
        }
        
        $this->_filename = $filename;
        $this->_path = 'uploaded/' . $filename;
        $this->_db = new MysqlLibrary();
    }
    
    private function _getDb()
    {
        return $this->_db;
    }
    
    private function validateFile()
    {
        $handle = fopen($this->_path, 'r');
        $headers = fgetcsv($handle);
        
        $this->_validateColumnsExist($headers);
        
        $mapping = $this->_mapValues($headers);
        
        while(($row = fgetcsv($handle)) !== FALSE) {
            
            // Blank line
            if($row == array(NULL)) {
                continue;
            }
            
            $this->_validateEventDateTime($row[$mapping['eventDatetime']]);
            $this->_validateEventAction($row[$mapping['eventAction']]);
            $this->_validateCalRef($row[$mapping['callRef']]);
            $this->_validateEventValue($row[$mapping['eventValue']]);
            $this->_validateEventCurrencyCode($row[$mapping['eventCurrencyCode']], $row[$mapping['eventValue']]);
        }
        
        fclose($handle);
        
        return true;
    }
    
    public function processFile()
    {
        try
        {
            $this->validateFile($this->_path);
        } catch (Exception $ex) {
            echo "File failed to import due to the following error: " . $ex->getMessage();
            return false;
        }
        
        syslog(LOG_INFO, 'File validated successfully');
        
        $handle = fopen($this->_path, 'r');
        $headers = fgetcsv($handle);
        
        $mapping = $this->_mapValues($headers);
        
        $this->_createTable();
        
        while(($row = fgetcsv($handle)) !== FALSE) {
            
            if($row == array(NULL)) {
                continue;
            }
            
            $data = array(
                'event_date_time' => $this->_getDb()->escape($row[$mapping['eventDatetime']]),
                'event_action' => $this->_getDb()->escape($row[$mapping['eventAction']]),
                'call_ref' => $this->_getDb()->escape($row[$mapping['callRef']]),
                'event_value' => $this->_getDb()->escape($row[$mapping['eventValue']]),
                'event_currency_code' => $this->_getDb()->escape($row[$mapping['eventCurrencyCode']])
            );
            
            $this->_getDb()->query("INSERT INTO `uploaded_data` (`event_date_time`, `event_action`, `call_ref`, `event_value`,`event_currency_code`)"
                    . " VALUES ('{$data['event_date_time']}', '{$data['event_action']}', '{$data['call_ref']}', '{$data['event_value']}',"
                    . "'{$data['event_currency_code']}')");
                    
            syslog(LOG_INFO, 'Inserted data for call ref: ' . $data['call_ref']);
        }
        
        if(!file_exists('processed')) {
            mkdir('processed');
        }
        
        fclose($handle);
        
        rename('uploaded/' . $this->_filename, 'processed/' . $this->_filename);
    }
    
    private function _createTable()
    {
        $this->_getDb()->query(
                "CREATE TABLE IF NOT EXISTS `uploaded_data` (
                    `event_date_time` DATETIME NOT NULL,
                    `event_action` VARCHAR(20),
                    `call_ref` INT(11),
                    `event_value` DECIMAL(9,2),
                    `event_currency_code` CHAR(3)
                
                ) ENGINE=InnoDB");
    }
    
    private function _mapValues($headers)
    {
        $columnNames = $this->_getExpectedColumns();
        
        $mapping = array();
        
        foreach($columnNames as $column) {
            $mapping[$column] = array_search($column, $headers);
        }        
                
        return $mapping;
    }
    
    private function _getExpectedColumns()
    {
        return array('eventDatetime','eventAction','callRef','eventValue','eventCurrencyCode');
    }
    
    private function _validateColumnsExist($headers)
    {
        if(!is_array($headers)) {
            throw new Exception('Validation error: Could not find any file headers');
        }
        
        $expectedColumns = $this->_getExpectedColumns();
        foreach($expectedColumns as $expectedColumn) {
            if(!in_array($expectedColumn, $headers)) {
                throw new Exception('Validation error: Column ' . $expectedColumn . ' was expected but not found');
            }
        }
        
        return true;
    }
    
    private function _validateEventDateTime($eventDateTime)
    {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $eventDateTime);
        if(!$date) {
            throw new Exception('Validation error: eventDateTime is not valid');
        }
        
        return true;
    }
    
    private function _validateEventAction($eventAction)
    {
        if(!is_string($eventAction)) {
            throw new Exception('Validation error: eventAction is not a string');
        }
        
        if(strlen($eventAction) < 1) {
            throw new Exception('Validation error: eventAction is less than 1 character');
        }
        
        if(strlen($eventAction) > 20) {
            throw new Exception('Validation error: eventAction is greater than 20 characters');
        }
        
        return true;
    }
    
    private function _validateCalRef($calRef)
    {
        if(!preg_match('/^[0-9]{1,}$/', $calRef)) {
            throw new Exception('Validation error: calRef is not an integer');
        }
        
        return true;
    }
    
    private function _validateEventValue($eventValue)
    {
        if(!empty($eventValue) && !preg_match('/^[0-9]{1,}\.[0-9]{1,}$/', $eventValue)) {
            throw new Exception('Validation error: eventValue exists and is not a decimal value');
        }
        
        return true;
    }
    
    private function _validateEventCurrencyCode($eventCurrencyCode, $eventValue)
    {
        if($eventValue != 0 && !preg_match('/^[A-Z]{3}$/', $eventCurrencyCode)) {
            throw new Exception('Validation error: eventCurrencyCode is not valid');
        }
        
        return true;
    }
}