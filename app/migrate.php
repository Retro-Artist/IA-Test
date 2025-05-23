<?php
/**
 * Simple PHP Initialization - Database Migration Script
 * 
 * This script imports the database structure and sample data from database.sql.
 * Run it with: docker-compose exec app php database/migrate.php
 */

// First connect to the database using environment variables or default values
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_DATABASE') ?: 'simple_php';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: 'root_password';

try {
    // Create database connection
    echo "Connecting to MySQL...\n";
    
    // Connect without database first (in case the database doesn't exist yet)
    $pdo = new PDO("mysql:host={$host}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Create database if it doesn't exist
    echo "Creating database '{$dbname}' if it doesn't exist...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    
    echo "Database connection established successfully!\n";
    
    // Path to the SQL file
    $sqlFilePath = __DIR__ . '/database.sql';
    
    if (!file_exists($sqlFilePath)) {
        throw new Exception("SQL file not found: {$sqlFilePath}");
    }
    
    echo "Importing database structure and data from database.sql...\n";
    
    // Read the SQL file
    $sql = file_get_contents($sqlFilePath);
    
    // Split SQL file into individual queries
    $queries = preg_split('/;\s*$/m', $sql);
    
    // Execute each query
    $successCount = 0;
    $totalQueries = count($queries);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $pdo->exec($query);
                $successCount++;
            } catch (PDOException $e) {
                echo "Error executing query: " . $e->getMessage() . "\n";
                echo "Query: " . substr($query, 0, 100) . "...\n";
            }
        }
    }
    
    echo "Executed {$successCount} of {$totalQueries} queries successfully!\n";
    
    echo "\nDatabase migration completed successfully!\n";
    echo "If you're running Docker on your local machine, you can access phpMyAdmin at: http://localhost:8081\n";
    echo "Your PHPMyAdmin access credentials on Docker are: Username: root, Password: root_password\n\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}