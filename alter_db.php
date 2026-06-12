<?php
require 'includes/db.php';
mysqli_query($conn, "ALTER TABLE hiring_requests ADD COLUMN student_id INT DEFAULT NULL AFTER company_id");
echo "Column added successfully.\n";
