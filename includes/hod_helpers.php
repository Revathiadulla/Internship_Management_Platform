<?php
// includes/hod_helpers.php
// Utility functions for HOD approval workflow

/** Generate a secure random token (64‑char hexadecimal) */
function generate_hod_token(): string {
    return bin2hex(random_bytes(32));
}

/** Build the HOD approval URL */
function hod_approval_url(int $application_id, string $token): string {
    // Assuming the platform runs on the same host as accessed by the user
    $host = $_SERVER['HTTP_HOST'];
    return "https://$host/hod_approval.php?app_id=$application_id&token=$token";
}
?>
