<?php
define('INCLUDE_CHECK', true);
require_once 'c:/xampp/htdocs/Internship_Management_Platform-student_module/db.php';
$_GET['view'] = 'review';
include 'c:/xampp/htdocs/Internship_Management_Platform-student_module/hr_applications.php';
// The included file will set $review_total and $total_rows etc.
echo "Review count: $review_total\n";
echo "Total rows (pagination): $total_rows\n";
?>
