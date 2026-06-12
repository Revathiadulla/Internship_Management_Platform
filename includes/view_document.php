<?php
/**
 * view_document.php
 * Secure document viewer/downloader for HR and Admin roles.
 */

session_start();
include_once __DIR__ . '/auth.php';
include __DIR__ . '/db.php';

// Authorization check
require_login();
if (!can_access_module('applications') && current_user_role() !== 'hr' && current_user_role() !== 'admin') {
    http_response_code(403);
    exit('Unauthorized access.');
}

$raw_file = isset($_GET['file']) ? trim($_GET['file']) : '';
$mode     = isset($_GET['mode']) && $_GET['mode'] === 'download' ? 'download' : 'view';

if ($raw_file === '') {
    http_response_code(400);
    exit('Missing file parameter.');
}

// Redirect immediately if the file is a remote URL (Cloudinary URL)
if (strpos($raw_file, 'http://') === 0 || strpos($raw_file, 'https://') === 0) {
    header("Location: " . $raw_file);
    exit();
}

// Strip any path components
$filename = basename($raw_file);

// Whitelist extensions
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
if (!in_array($ext, $allowed_ext, true)) {
    http_response_code(403);
    exit('File type not allowed.');
}

// Locate the file using the shared helper
$resolved_path = resolve_resume_file_path($filename);

if ($resolved_path === null || !is_file($resolved_path)) {
    http_response_code(404);
    exit('Document not found.');
}

// MIME type map
$mime_map = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png'
];
$mime = $mime_map[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($resolved_path));
header('X-Content-Type-Options: nosniff');

if ($mode === 'download') {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
}

// Disable output buffering and send
if (ob_get_level()) ob_end_clean();
readfile($resolved_path);
exit;
