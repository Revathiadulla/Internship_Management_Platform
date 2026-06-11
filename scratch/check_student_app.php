<?php
require_once __DIR__ . '/../db.php';

$res = mysqli_query($conn, "SELECT a.id, a.user_id, u.email, u.role, a.internship_id, a.internship_name, a.status, a.confirmation_letter_path, a.certificate_path FROM internship_applications a JOIN users u ON a.user_id = u.id");
while ($r = mysqli_fetch_assoc($res)) {
    print_r($r);
}
