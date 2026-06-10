<?php
include 'db.php';

echo "=== Fixing education_status conflicts for User 76 ===\n";

// Update student_profiles.student_type to 'passed_out' for user 76
$q1 = mysqli_query($conn, "UPDATE student_profiles SET student_type = 'passed_out' WHERE user_id = 76");
echo "Updated student_profiles: " . ($q1 ? "Success" : "Failed") . " (Affected: " . mysqli_affected_rows($conn) . ")\n";

// Update internship_applications.education_status to 'Passed Out' for user 76
$q2 = mysqli_query($conn, "UPDATE internship_applications SET education_status = 'Passed Out' WHERE user_id = 76");
echo "Updated internship_applications: " . ($q2 ? "Success" : "Failed") . " (Affected: " . mysqli_affected_rows($conn) . ")\n";

?>
