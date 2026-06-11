<?php
require_once __DIR__ . '/../db.php';

// Set application status to Selected and empty confirmation letter paths
$q = mysqli_query($conn, "UPDATE internship_applications SET status = 'Selected', confirmation_letter_path = NULL, confirmation_letter_sent_at = NULL, confirmation_letter_sent = 0 WHERE id = 46");
if ($q) {
    echo "Updated application 46 to Selected with no confirmation letter sent.\n";
} else {
    echo "Failed to update: " . mysqli_error($conn) . "\n";
}
