<?php
/**
 * migrate_approval_columns.php
 * Safe idempotent migration — adds approval workflow columns to users table.
 * Run once: http://localhost/IMP/scratch/migrate_approval_columns.php
 */
require __DIR__ . '/../db.php';

$results = [];

// Add columns if not present
$migrations = [
    "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER status",
    "ALTER TABLE users ADD COLUMN approval_status VARCHAR(30) NOT NULL DEFAULT 'approved' AFTER is_active",
    "ALTER TABLE users ADD COLUMN approved_by INT NULL AFTER approval_status",
    "ALTER TABLE users ADD COLUMN approved_at DATETIME NULL AFTER approved_by",
];

foreach ($migrations as $sql) {
    preg_match('/ADD COLUMN (\w+)/', $sql, $m);
    $col = $m[1] ?? '?';
    $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '$col'");
    if (mysqli_num_rows($check) > 0) {
        $results[] = "✅ Column `$col` already exists — skipped.";
    } else {
        if (mysqli_query($conn, $sql)) {
            $results[] = "✅ Column `$col` added successfully.";
        } else {
            $results[] = "❌ Failed to add `$col`: " . mysqli_error($conn);
        }
    }
}

// Sync existing pending users
$sync1 = mysqli_query($conn, "UPDATE users SET is_active = 0, approval_status = 'pending' WHERE status = 'pending_approval' AND (is_active = 1 OR approval_status = 'approved')");
$results[] = "✅ Synced pending users: " . mysqli_affected_rows($conn) . " row(s) updated.";

// Sync existing active users
$sync2 = mysqli_query($conn, "UPDATE users SET is_active = 1, approval_status = 'approved' WHERE status IN ('Active','active','approved') AND is_active = 0");
$results[] = "✅ Synced active users: " . mysqli_affected_rows($conn) . " row(s) updated.";

echo "<pre style='font-family:monospace;padding:20px;background:#0f172a;color:#e2e8f0;'>";
echo "=== IMP Approval Workflow DB Migration ===\n\n";
foreach ($results as $r) echo $r . "\n";
echo "\n✅ Migration complete.";
echo "</pre>";
