<?php
/**
 * resume_serve.php
 * Secure resume viewer/downloader for HR and Admin roles only.
 *
 * Usage:
 *   View:     resume_serve.php?file=filename.pdf&mode=view
 *   Download: resume_serve.php?file=filename.pdf&mode=download
 *
 * Security:
 *   - HR / Admin only (auth guard)
 *   - Filename is basename-sanitised — no directory traversal possible
 *   - Only files inside the two known upload directories are served
 *   - Only PDF, DOC, DOCX extensions allowed
 */

session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('candidates');
include 'db.php';

// ── Input validation ──────────────────────────────────────────────────────────
$raw_file = isset($_GET['file']) ? trim($_GET['file']) : '';
$mode     = isset($_GET['mode']) && $_GET['mode'] === 'download' ? 'download' : 'view';

if ($raw_file === '') {
    http_response_code(400);
    exit('Missing file parameter.');
}

// Strip any path components — only the bare filename is accepted
$filename = basename($raw_file);

// Whitelist extensions
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowed_ext = ['pdf', 'doc', 'docx'];
if (!in_array($ext, $allowed_ext, true)) {
    http_response_code(403);
    exit('File type not allowed.');
}

// ── Locate the file — check both upload directories ──────────────────────────
$search_dirs = [
    __DIR__ . '/uploads/resumes/',
    __DIR__ . '/uploads/secure/',
    __DIR__ . '/uploads/',
];

$resolved_path = null;
foreach ($search_dirs as $dir) {
    $candidate = $dir . $filename;
    // realpath() resolves symlinks and normalises — then verify it stays inside the dir
    $real = realpath($candidate);
    $real_dir = realpath($dir);
    if ($real !== false && $real_dir !== false && strncmp($real, $real_dir, strlen($real_dir)) === 0) {
        $resolved_path = $real;
        break;
    }
}

if ($resolved_path === null || !is_file($resolved_path)) {
    http_response_code(404);
    exit('Resume not found.');
}

// ── MIME type map ─────────────────────────────────────────────────────────────
$mime_map = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mime_map[$ext] ?? 'application/octet-stream';

// ── Serve the file ────────────────────────────────────────────────────────────
$display_name = $filename; // safe — already basename'd

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($resolved_path));
header('X-Content-Type-Options: nosniff');

if ($mode === 'download') {
    header('Content-Disposition: attachment; filename="' . $display_name . '"');
} else {
    // Inline for PDF (browser renders it); force download for Word docs
    if ($ext === 'pdf') {
        header('Content-Disposition: inline; filename="' . $display_name . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $display_name . '"');
    }
}

// Disable output buffering and send
if (ob_get_level()) ob_end_clean();
readfile($resolved_path);
exit;
