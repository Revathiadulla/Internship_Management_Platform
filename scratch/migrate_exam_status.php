<?php
include 'db.php';

// Migrate 'Exam Completed' and 'Test Completed' records to 'Exam Mail Sent'
$old_statuses = ['Exam Completed', 'Test Completed', 'Test Submitted', 'Test Passed', 'Test Failed'];
$placeholders = implode(',', array_fill(0, count($old_statuses), '?'));

// Check how many records will be affected
$check = $conn->prepare("SELECT id, status FROM internship_applications WHERE status IN ($placeholders)");
$check->bind_param(str_repeat('s', count($old_statuses)), ...$old_statuses);
$check->execute();
$res = $check->get_result();
$affected = [];
while ($row = $res->fetch_assoc()) {
    $affected[] = $row;
}
echo "Records to migrate: " . count($affected) . PHP_EOL;
foreach ($affected as $r) {
    echo "  ID " . $r['id'] . " => " . $r['status'] . PHP_EOL;
}

if (count($affected) === 0) {
    echo "Nothing to migrate." . PHP_EOL;
    exit;
}

// Perform migration
$update = $conn->prepare("UPDATE internship_applications SET status = 'Exam Mail Sent' WHERE status IN ($placeholders)");
$update->bind_param(str_repeat('s', count($old_statuses)), ...$old_statuses);
$update->execute();
echo "Updated " . $conn->affected_rows . " records to 'Exam Mail Sent'" . PHP_EOL;

// Also log status changes for migrated records
foreach ($affected as $r) {
    $app_id = (int) $r['id'];
    $old_st = $conn->real_escape_string($r['status']);
    $conn->query("INSERT INTO application_status_history (application_id, old_status, new_status, notes, updated_by_name, created_at)
                  VALUES ($app_id, '$old_st', 'Exam Mail Sent', 'Migrated from legacy exam status during workflow cleanup', 'System Migration', NOW())");
}
echo "Status history entries inserted: " . count($affected) . PHP_EOL;
echo "Migration complete." . PHP_EOL;
