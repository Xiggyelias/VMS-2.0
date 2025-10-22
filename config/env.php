<?php
/**
 * Environment Configuration Loader
 * 
 * This file loads environment variables from .env file.
 * Uses vlucas/phpdotenv if available, otherwise falls back to simple parser.
 */

// Check if .env file exists
$envFile = dirname(__DIR__) . '/.env';
$envFileExists = file_exists($envFile);

// Load Composer's autoloader if available
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
$composerInstalled = file_exists($autoloadPath);

if ($composerInstalled) {
    require_once $autoloadPath;
    
    // Use Dotenv library if available
    if (class_exists('Dotenv\Dotenv') && $envFileExists) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $dotenv->load();
        } catch (Exception $e) {
            // Fall back to simple parser
            loadEnvFileFallback($envFile);
        }
    } else {
        loadEnvFileFallback($envFile);
    }
} else {
    // Composer not installed, use fallback parser
    loadEnvFileFallback($envFile);
}

/**
 * Fallback function to load .env file without Dotenv library
 * 
 * @param string $envFile Path to .env file
 */
function loadEnvFileFallback($envFile) {
    if (!file_exists($envFile)) {
        return; // Silently fail, use defaults
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes from value
            $value = trim($value, '"\'');
            
            // Set environment variables
            if (!empty($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

/**
 * Get Environment Variable with Default
 * 
 * @param string $key Environment variable key
 * @param mixed $default Default value if key doesn't exist
 * @return mixed Environment variable value or default
 */
function env($key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string representations to actual types
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}

/**
 * Get Required Environment Variable
 * 
 * @param string $key Environment variable key
 * @return mixed Environment variable value
 * @throws Exception If variable is not set
 */
function env_required($key) {
    $value = env($key);
    
    if ($value === null) {
        throw new Exception("Required environment variable '{$key}' is not set.");
    }
    
    return $value;
}

/**
 * Get Boolean Environment Variable
 * 
 * @param string $key Environment variable key
 * @param bool $default Default value
 * @return bool
 */
function env_bool($key, $default = false) {
    $value = env($key, $default);
    
    if (is_bool($value)) {
        return $value;
    }
    
    return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
}

/**
 * Get Integer Environment Variable
 * 
 * @param string $key Environment variable key
 * @param int $default Default value
 * @return int
 */
function env_int($key, $default = 0) {
    return (int) env($key, $default);
}

/**
 * Get Array Environment Variable (comma-separated)
 * 
 * @param string $key Environment variable key
 * @param array $default Default value
 * @return array
 */
function env_array($key, $default = []) {
    $value = env($key);
    
    if ($value === null) {
        return $default;
    }
    
    return array_map('trim', explode(',', $value));
}
