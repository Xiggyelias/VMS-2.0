<?php
/**
 * Database Configuration
 * 
 * This file contains all database-related configuration settings.
 * Centralized configuration makes it easier to manage database connections
 * and switch between different environments (development, production, etc.).
 */

// Load environment variables
require_once __DIR__ . '/env.php';

// Database configuration constants
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USERNAME', env('DB_USERNAME', 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_NAME', env('DB_NAME', 'vehicleregistrationsystem'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Database connection options
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
]);

/**
 * Get Database Connection
 * 
 * Creates and returns a PDO database connection with proper error handling.
 * Uses singleton pattern to ensure only one connection is created per request.
 * 
 * @return PDO Database connection object
 * @throws Exception If connection fails
 */
function getDatabaseConnection() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD, DB_OPTIONS);
        } catch (PDOException $e) {
            // Log error and throw user-friendly exception
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }
    
    return $connection;
}

/**
 * Get Legacy MySQL Connection (for backward compatibility)
 * 
 * Creates and returns a mysqli connection for existing code that hasn't been
 * migrated to PDO yet. This should be gradually replaced with PDO.
 * 
 * @return mysqli Database connection object
 * @throws Exception If connection fails
 */
function getLegacyDatabaseConnection() {
    $connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($connection->connect_error) {
        error_log("Legacy database connection failed: " . $connection->connect_error);
        throw new Exception("Database connection failed. Please try again later.");
    }
    
    $connection->set_charset(DB_CHARSET);
    return $connection;
}

/**
 * Close Database Connection
 * 
 * Properly closes the database connection to free up resources.
 * 
 * @param PDO|mysqli $connection Database connection to close
 */
function closeDatabaseConnection($connection) {
    if ($connection instanceof PDO) {
        $connection = null;
    } elseif ($connection instanceof mysqli) {
        $connection->close();
    }
} 