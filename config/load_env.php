<?php
/**
 * Simple environment variable loader
 */
function loadEnv($path = '.env') {
    // Check if file exists
    if (!file_exists($path)) {
        return false;
    }
    
    // Read file line by line
    $handle = fopen($path, 'r');
    if (!$handle) return false;
    
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line) || $line[0] == '#') continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
    fclose($handle);
    
    return true;
}