<?php
session_start();
include_once __DIR__ . '/includes/auth.php';

echo "<h2>Session Debugger</h2>";
echo "<pre>";
echo "Session Data:\n";
print_r($_SESSION);
echo "\n";
echo "Current User Role: " . (current_user_role() ?? 'NULL') . "\n";
echo "Can Access 'student_logs': " . (can_access_module('student_logs') ? 'YES' : 'NO') . "\n";
echo "Can Access 'applications': " . (can_access_module('applications') ? 'YES' : 'NO') . "\n";
echo "Default Modules for Role: ";
print_r(role_default_modules(current_user_role()));
echo "</pre>";
?>
