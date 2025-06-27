<?php
/**
 * Database Connection File
 * 
 * This file establishes a connection to the database using PDO.
 * It's included in other PHP files that need database access.
 */

// Include the database configuration file
require_once __DIR__ . '/../config/database.php';

// Get the database connection
$pdo = getDbConnection();
