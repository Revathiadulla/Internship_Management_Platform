<?php
require_once __DIR__ . '/../db.php';
$res = mysqli_query($conn, "
    SELECT ca.coordinator_id, u.email, pt.type_name, ps.subtype_name 
    FROM coordinator_assignments ca
    JOIN users u ON ca.coordinator_id = u.id
    LEFT JOIN project_types pt ON ca.project_type_id = pt.id
    LEFT JOIN project_subtypes ps ON ca.project_subtype_id = ps.id
");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Coord ID: {$row['coordinator_id']} | Email: {$row['email']} | Type: {$row['type_name']} | Subtype: {$row['subtype_name']}\n";
}
