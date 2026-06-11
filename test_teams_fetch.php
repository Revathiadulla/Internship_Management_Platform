<?php
require 'db.php';
$coord_id = 3;
$students_sql = "
    SELECT DISTINCT u.id, u.full_name, u.email, sp.college_name,
           a.id AS application_id,
           a.internship_id, a.team_name AS assigned_team,
           COALESCE(NULLIF(TRIM(a.applied_subtype), ''), NULLIF(TRIM(a.internship_name), '')) AS applied_subtype,
           a.status,
           CASE WHEN EXISTS (
                SELECT 1
                FROM project_team_members ptm2
                WHERE ptm2.student_id = u.id
           ) THEN 1 ELSE 0 END AS assigned_to_any_team
    FROM users u
    JOIN internship_applications a ON u.id = a.user_id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN internships i ON a.internship_id = i.id
    LEFT JOIN project_subtypes ps ON LOWER(TRIM(COALESCE(a.applied_subtype, a.internship_name))) = LOWER(TRIM(ps.subtype_name))
    LEFT JOIN project_types pt2 ON ps.project_type_id = pt2.id
    LEFT JOIN coordinator_assignments ca ON pt2.id = ca.project_type_id
    WHERE u.role = 'student'
      AND (i.coordinator_id = $coord_id OR ca.coordinator_id = $coord_id)
      AND LOWER(TRIM(COALESCE(a.status, ''))) IN ('selected', 'confirmation letter sent', 'confirmation_letter_sent')
    ORDER BY assigned_to_any_team DESC, u.full_name ASC
";
$res = mysqli_query($conn, $students_sql);
if(!$res) die(mysqli_error($conn));
while($r = mysqli_fetch_assoc($res)) {
   print_r($r);
}
?>
