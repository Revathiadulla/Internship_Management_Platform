<?php
require_once __DIR__ . '/../db.php';

// Assign jaya@gmail.com (ID 3) to Design (Type ID 2) and Graphic Design (Subtype ID 959)
$check3 = mysqli_query($conn, "SELECT id FROM coordinator_assignments WHERE coordinator_id = 3 AND project_type_id = 2");
if (mysqli_num_rows($check3) == 0) {
    mysqli_query($conn, "INSERT INTO coordinator_assignments (coordinator_id, project_type_id, project_subtype_id) VALUES (3, 2, 959)");
    echo "Assigned Coordinator 3 to Design / Graphic Design.\n";
} else {
    echo "Coordinator 3 already assigned to Design.\n";
}

// Assign revathireddy2003@gmail.com (ID 79) to Development (Type ID 1) and Web Development (Subtype ID 252)
$check79 = mysqli_query($conn, "SELECT id FROM coordinator_assignments WHERE coordinator_id = 79 AND project_type_id = 1");
if (mysqli_num_rows($check79) == 0) {
    mysqli_query($conn, "INSERT INTO coordinator_assignments (coordinator_id, project_type_id, project_subtype_id) VALUES (79, 1, 252)");
    echo "Assigned Coordinator 79 to Development / Web Development.\n";
} else {
    echo "Coordinator 79 already assigned to Development.\n";
}
