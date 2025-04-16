<?php
// Database configuration
$hostname = "localhost";
$database = "dbvhmqnewv5wsv";
$username = "uklz9ew3hrop3";
$password = "zyrbspyjlzjb";

// Create connection
$conn = new mysqli($hostname, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

/**
 * Sanitize input data to prevent SQL injection
 * @param mixed $data - Data to sanitize
 * @return mixed - Sanitized data
 */
function sanitize($data, $conn = null) {
    global $conn;
    if (!$conn) {
        $conn = $GLOBALS['conn'];
    }
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value, $conn);
        }
        return $data;
    }
    
    if (is_string($data)) {
        return $conn->real_escape_string(trim($data));
    }
    
    return $data;
}

/**
 * Execute a query and return result
 * @param string $query - SQL query to execute
 * @return mixed - Query result
 */
function executeQuery($query) {
    global $conn;
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query Error: " . $conn->error . " - Query: " . $query);
        return false;
    }
    return $result;
}

/**
 * Get a single row from database
 * @param string $query - SQL query to execute
 * @return array|bool - Result row or false
 */
function getRow($query) {
    $result = executeQuery($query);
    if (!$result) return false;
    
    $row = $result->fetch_assoc();
    $result->free();
    return $row;
}

/**
 * Get multiple rows from database
 * @param string $query - SQL query to execute
 * @return array|bool - Result rows or false
 */
function getRows($query) {
    $result = executeQuery($query);
    if (!$result) return false;
    
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

/**
 * Insert data into a table
 * @param string $table - Table name
 * @param array $data - Associative array of column => value
 * @return int|bool - Inserted ID or false
 */
function insertData($table, $data) {
    global $conn;
    
    $columns = implode(", ", array_keys($data));
    $values = "'" . implode("', '", $data) . "'";
    
    $query = "INSERT INTO $table ($columns) VALUES ($values)";
    if ($conn->query($query)) {
        return $conn->insert_id;
    }
    
    error_log("Insert Error: " . $conn->error . " - Query: " . $query);
    return false;
}

/**
 * Update data in a table
 * @param string $table - Table name
 * @param array $data - Associative array of column => value
 * @param string $condition - WHERE condition
 * @return bool - Success or failure
 */
function updateData($table, $data, $condition) {
    global $conn;
    
    $setValues = [];
    foreach ($data as $column => $value) {
        $setValues[] = "$column = '$value'";
    }
    $setClause = implode(", ", $setValues);
    
    $query = "UPDATE $table SET $setClause WHERE $condition";
    if ($conn->query($query)) {
        return true;
    }
    
    error_log("Update Error: " . $conn->error . " - Query: " . $query);
    return false;
}

/**
 * Delete data from a table
 * @param string $table - Table name
 * @param string $condition - WHERE condition
 * @return bool - Success or failure
 */
function deleteData($table, $condition) {
    global $conn;
    
    $query = "DELETE FROM $table WHERE $condition";
    if ($conn->query($query)) {
        return true;
    }
    
    error_log("Delete Error: " . $conn->error . " - Query: " . $query);
    return false;
}
?>
