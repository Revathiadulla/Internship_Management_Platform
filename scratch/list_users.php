<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "SELECT id, full_name, email, role FROM users");
while ($row = mysqli_fetch_assoc($res)) {
    echo "ID: {$row['id']} | Name: {$row['full_name']} | Email: {$row['email']} | Role: {$row['role']}\n";
}
