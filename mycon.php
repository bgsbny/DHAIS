<?php
$dbhost = "localhost";
$dbuser = "root";
$dbpass = "";
$db = "dhautocare";

$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
?>