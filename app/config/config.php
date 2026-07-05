<?php
/**
 * Global configuration + BASE_URL auto-detection.
 * Works from any XAMPP sub-folder without hard-coding the host.
 */

// ---- Database ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'coffee_game');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- App ----
define('APP_NAME', 'Brew Master');

// Base URL points at the /public folder, e.g. http://localhost/CoffeeGame_PLT/public
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Directory of the front controller (public/)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptDir = rtrim($scriptDir, '/');
define('BASE_URL', $scheme . '://' . $host . $scriptDir);

// Filesystem paths
define('APP_PATH',    dirname(__DIR__));                 // /app
define('ROOT_PATH',   dirname(APP_PATH));                // project root
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// Error reporting (development)
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');
