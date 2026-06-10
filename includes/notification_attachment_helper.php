<?php
// includes/notification_attachment_helper.php

function validateAndUploadNotificationAttachment($file, &$error) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed with error code: " . $file['error'];
        return false;
    }

    $fileName = basename($file['name']);
    $fileSize = $file['size'];
    $tmpName = $file['tmp_name'];
    $fileType = $file['type'];

    // Allowed extensions
    $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        $error = "File type not allowed. Allowed types: " . implode(', ', $allowedExts);
        return false;
    }

    // Unsafe content/extension check
    if (preg_match('/\.(php|phtml|php3|php4|php5|php7|phps|pht|phar|sh|bat|exe|cmd|pl|py|cgi)$/i', $fileName)) {
        $error = "Unsafe file type detected.";
        return false;
    }

    // Check file size (10 MB)
    $maxSize = 10 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        $error = "File size exceeds the 10 MB limit.";
        return false;
    }

    // Save uploaded file
    $uploadDir = __DIR__ . '/../uploads/notification_attachments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uniqueName = uniqid('notif_', true) . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $fileName);
    $destPath = $uploadDir . $uniqueName;

    if (move_uploaded_file($tmpName, $destPath)) {
        return [
            'path' => 'uploads/notification_attachments/' . $uniqueName,
            'name' => $fileName,
            'size' => $fileSize,
            'type' => $fileType
        ];
    } else {
        $error = "Failed to save the uploaded file.";
        return false;
    }
}
