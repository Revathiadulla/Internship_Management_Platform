<?php
$host = "by7xxebmaxfwobqrh1ne-mysql.services.clever-cloud.com";
$user = "ujebqn1hlk9qd98k";
$pass = "zqPIiSbk9EU6l3KHrvml";
$db   = "by7xxebmaxfwobqrh1ne";
$port = 3306;

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
