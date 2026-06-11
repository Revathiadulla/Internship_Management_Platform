<?php
require 'db.php';
$sql = "SELECT a.id as app_id, a.id as id, a.status, a.applied_date, a.education_status,
               COALESCE(i.title, a.internship_name) as title, u.full_name as student_name, u.email as student_email, sp.phone, sp.college_name, sp.skills as student_skills, ss.percentage as test_percentage
        FROM internship_applications a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        JOIN internships i ON a.internship_id = i.id
        LEFT JOIN student_scores ss ON ss.application_id = a.id
        LEFT JOIN project_teams pt ON i.id = pt.internship_id";
$res = mysqli_query($conn, $sql);
if (!$res) {
    echo "ERROR: " . mysqli_error($conn);
} else {
    echo "SUCCESS: " . mysqli_num_rows($res) . " rows";
}
