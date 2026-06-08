<?php
require_once __DIR__ . '/../db.php';

echo "=== Comprehensive Cloudinary URL Scan ===\n\n";

// 1. Scan student_profiles
echo "--- student_profiles ---\n";
$sql = "SELECT id, user_id, full_name, resume_file, resume_url, aadhaar_file, pan_file FROM student_profiles";
$res = mysqli_query($conn, $sql);
$sp_count = 0;
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $found = false;
        $urls = [];
        foreach (['resume_file', 'resume_url', 'aadhaar_file', 'pan_file'] as $col) {
            $val = trim($row[$col] ?? '');
            if (strpos($val, 'cloudinary.com') !== false || strpos($val, 'http') === 0) {
                $urls[$col] = $val;
                $found = true;
            }
        }
        if ($found) {
            $sp_count++;
            echo "Student #{$row['user_id']} ({$row['full_name']}):\n";
            foreach ($urls as $col => $url) {
                $type = 'unknown';
                if (strpos($url, '/image/upload/') !== false) $type = 'image/upload';
                elseif (strpos($url, '/raw/upload/') !== false) $type = 'raw/upload';
                elseif (strpos($url, '/auto/') !== false) $type = 'auto';
                
                $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                echo "  $col: $url\n";
                echo "    Type: $type, Extension: $ext\n";
            }
            echo "-------------------------------\n";
        }
    }
}
echo "Total student profiles with Cloudinary URLs: $sp_count\n\n";

// 2. Scan internship_applications
echo "--- internship_applications ---\n";
$sql = "SELECT id, user_id, status, confirmation_letter_path, certificate_path, resume_file, pan_file FROM internship_applications";
$res = mysqli_query($conn, $sql);
$ia_count = 0;
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $found = false;
        $urls = [];
        foreach (['confirmation_letter_path', 'certificate_path', 'resume_file', 'pan_file'] as $col) {
            $val = trim($row[$col] ?? '');
            if (strpos($val, 'cloudinary.com') !== false || strpos($val, 'http') === 0) {
                $urls[$col] = $val;
                $found = true;
            }
        }
        if ($found) {
            $ia_count++;
            echo "Application #{$row['id']} (User #{$row['user_id']}), Status: {$row['status']}:\n";
            foreach ($urls as $col => $url) {
                $type = 'unknown';
                if (strpos($url, '/image/upload/') !== false) $type = 'image/upload';
                elseif (strpos($url, '/raw/upload/') !== false) $type = 'raw/upload';
                elseif (strpos($url, '/auto/') !== false) $type = 'auto';
                
                $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                echo "  $col: $url\n";
                echo "    Type: $type, Extension: $ext\n";
            }
            echo "-------------------------------\n";
        }
    }
}
echo "Total applications with Cloudinary URLs: $ia_count\n";
