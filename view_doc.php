<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied. Please log in first.");
}

if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("HTTP/1.1 400 Bad Request");
    exit("No file specified.");
}

$file = basename($_GET['file']);
$filepath = __DIR__ . '/uploads/secure/' . $file;

if (file_exists($filepath)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
    if (!in_array($ext, $allowed_exts)) {
        header("HTTP/1.1 403 Forbidden");
        exit("FileType not allowed.");
    }
    
    $mime_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    $mime = $mime_types[$ext] ?? 'application/octet-stream';
    
    header("Content-Type: " . $mime);
    header("Content-Length: " . filesize($filepath));
    header("Content-Disposition: inline; filename=\"" . $file . "\"");
    readfile($filepath);
    exit();
} else {
    header("HTTP/1.1 404 Not Found");
    exit("File not found.");
}
?>
