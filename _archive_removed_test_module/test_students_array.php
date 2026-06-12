<?php
// Test script to check if students array is populated correctly in manual_message
require_once __DIR__ . '/includes/db.php';

function fetchUsersByRole(mysqli $conn, string $role): array {
    $roleEsc = mysqli_real_escape_string($conn, strtolower(trim($role)));
    $sql = "SELECT id, full_name, email, role FROM users WHERE role = '$roleEsc' ORDER BY full_name ASC";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    return $rows;
}

echo "=== Testing Student Array Population ===\n\n";

$students = fetchUsersByRole($conn, 'student');

echo "Students fetched: " . count($students) . "\n\n";

echo "First 10 students in array:\n";
foreach (array_slice($students, 0, 10) as $student) {
    echo "  ID: " . $student['id'] . ", Name: '" . $student['full_name'] . "', Email: " . $student['email'] . ", Role: " . $student['role'] . "\n";
}

echo "\nDropdown HTML that would be generated:\n";
foreach (array_slice($students, 0, 5) as $student) {
    $selected = '';
    echo "<option value=\"" . htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($student['full_name'] . ' (' . $student['email'] . ')', ENT_QUOTES, 'UTF-8') . "</option>\n";
}

echo "\n=== END TEST ===\n";
?>
