<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "DESCRIBE notifications");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
