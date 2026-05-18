<?php

$conn = mysqli_connect("localhost", "root", "", "imp_db");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

?>