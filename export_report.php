<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('reports');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

$format = $_GET['format'] ?? 'csv';
$start = trim($_GET['start_date'] ?? '');
$end = trim($_GET['end_date'] ?? '');
$where = ['a.is_deleted = 0'];
if ($start !== '') $where[] = "a.applied_date >= '" . mysqli_real_escape_string($conn, $start) . " 00:00:00'";
if ($end !== '') $where[] = "a.applied_date <= '" . mysqli_real_escape_string($conn, $end) . " 23:59:59'";
$where_sql = implode(' AND ', $where);
$sql = "SELECT a.id, COALESCE(sp.full_name, u.full_name, a.full_name, 'Unknown') full_name, COALESCE(sp.email, u.email, a.email, '') email,
        COALESCE(i.title, a.internship_name, 'Untitled') posting, a.status, COALESCE(sp.college_name, a.college_name, '') college, a.applied_date
        FROM internship_applications a
        LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE $where_sql ORDER BY a.applied_date DESC";
$result = mysqli_query($conn, $sql);

if ($format === 'pdf') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><title>IMP Report</title><style>body{font-family:Arial,sans-serif;padding:24px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f1f5f9}@media print{button{display:none}}</style></head><body><button onclick="window.print()">Print / Save as PDF</button><h1>IMP Applications Report</h1><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Posting</th><th>Status</th><th>College</th><th>Applied</th></tr></thead><tbody>';
    while ($row = mysqli_fetch_assoc($result)) {
        echo '<tr><td>' . e($row['id']) . '</td><td>' . e($row['full_name']) . '</td><td>' . e($row['email']) . '</td><td>' . e($row['posting']) . '</td><td>' . e($row['status']) . '</td><td>' . e($row['college']) . '</td><td>' . e($row['applied_date']) . '</td></tr>';
    }
    echo '</tbody></table><script>setTimeout(function(){window.print()}, 400);</script></body></html>';
    exit();
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="imp_applications_report.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['ID', 'Name', 'Email', 'Posting', 'Status', 'College', 'Applied Date']);
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($out, [$row['id'], $row['full_name'], $row['email'], $row['posting'], $row['status'], $row['college'], $row['applied_date']]);
}
fclose($out);
exit();
