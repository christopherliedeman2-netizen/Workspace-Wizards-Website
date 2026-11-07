<?php

ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);
    
$host = "sql309.infinityfree.com";
$username = "[redacted]";
$password = "[redacted]";
$database = "if0_43095321_WorkspaceWizards";
$port = "3306";

$conn = new mysqli($host, $username, $password, $database,$port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

?>