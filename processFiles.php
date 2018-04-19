<?php

error_reporting(E_ALL);

spl_autoload_register(function($className) {
    include_once $className . '.php';
});

$lockFilename = 'process_files.lock';
if(file_exists($lockFilename)) {
    echo 'Process files script already running';
    syslog(LOG_INFO, 'Process files script already running');
    return;
}

$handle = fopen($lockFilename, 'w');
fwrite($handle, 'LOCKED');
fclose($handle);

$files = scandir('./uploaded');
if(empty($files)) {
    syslog(LOG_INFO, 'No files found');
    return;
}

foreach($files as $file) {
    
    if(preg_match('/.{1,}\.csv/', $file)) {
        
        syslog(LOG_INFO, 'Processing file ' . $file);
        
        $fileProcessor = new FileProcessor($file);
        $fileProcessor->processFile();
        
        syslog(LOG_INFO, 'Finished processing file ' .$file);
        
        break;
    }
}

unlink($lockFilename);