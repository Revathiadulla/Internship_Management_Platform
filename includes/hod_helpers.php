<?php
// includes/hod_helpers.php
// Utility functions for HOD approval workflow

/** Generate a secure random token (64‑char hexadecimal) */
function generate_hod_token(): string {
    return bin2hex(random_bytes(32));
}

/** Return the application base URL for the current request host and project folder. */
function get_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($baseDir === '') {
        $baseDir = '/';
    }
    return $scheme . '://' . $host . $baseDir;
}

/** Build the HOD approval URL with optional decision parameter. */
function hod_approval_url(int $application_id, string $token, string $decision = ''): string {
    $baseUrl = get_base_url();
    $query = ['application_id' => $application_id, 'token' => $token];
    if (!empty($decision)) {
        $query['action'] = $decision;
    }
    return rtrim($baseUrl, '/') . '/hod_approval_action.php?' . http_build_query($query);
}
?>
