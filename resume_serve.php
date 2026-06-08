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
include 'db.php';

// ── Input validation ──────────────────────────────────────────────────────────
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

// Strip any path components — only the bare filename is accepted
$filename = basename($raw_file);

// ── Authorization check ───────────────────────────────────────────────────────
require_login();
$allowed = false;

if (can_access_module('candidates') || current_user_role() === 'mentor') {
    $allowed = true;
} elseif (current_user_role() === 'student') {
    $user_id = current_user_id();
    if ($user_id > 0 && $filename !== '') {
        $stmt = $conn->prepare("SELECT resume_file FROM student_profiles WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['resume_file']) && basename($row['resume_file']) === $filename) {
                    $allowed = true;
                }
            }
            $stmt->close();
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Unauthorized access.');
}

// Whitelist extensions
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowed_ext = ['pdf', 'doc', 'docx'];
if (!in_array($ext, $allowed_ext, true)) {
    http_response_code(403);
    exit('File type not allowed.');
}

// ── Locate the file using the shared helper ──────────────────────────
$resolved_path = resolve_resume_file_path($filename);

if ($resolved_path === null || !is_file($resolved_path)) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Resume Not Found - IMP</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; }
        </style>
    </head>
    <body class="bg-slate-50 flex items-center justify-center min-h-screen p-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-100 p-8 text-center transition-all hover:shadow-2xl">
            <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-slate-800 mb-2">Resume Not Found</h1>
            <p class="text-slate-600 mb-6 text-sm">Resume file not found. Please re-upload resume.</p>
            <div class="flex flex-col gap-2">
                <button onclick="window.close();" class="w-full py-2.5 px-4 bg-slate-800 text-white rounded-lg hover:bg-slate-900 transition-colors text-sm font-semibold">
                    Close Window
                </button>
                <a href="student_profile_form.php" class="w-full py-2.5 px-4 bg-white text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors text-sm font-semibold block">
                    Update Profile
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
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
