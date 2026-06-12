<?php
require_once __DIR__ . '/includes/db.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed: " . ($db_connection_error ?? 'Unknown error'));
}

echo "=== DATABASE USERS ===\n";
$res = mysqli_query($conn, "SELECT id, email, password, role, 
    LOWER(TRIM(COALESCE(status, ''))) as status, 
    COALESCE(is_active, 0) as is_active, 
    LOWER(TRIM(COALESCE(approval_status, ''))) as approval_status 
    FROM users");

if (!$res) {
    die("Query failed: " . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($res)) {
    $is_hashed = (strpos($row['password'], '$2y$') === 0 || strpos($row['password'], '$2a$') === 0 || strpos($row['password'], '$2b$') === 0) ? 'HASHED' : 'PLAIN';
    printf("ID: %d | Email: %s | Pass: %s | Role: %s | Status: %s | Active: %d | Approved: %s\n",
        $row['id'],
        $row['email'],
        $is_hashed,
        $row['role'],
        $row['status'],
        $row['is_active'],
        $row['approval_status']
    );
}
?>
