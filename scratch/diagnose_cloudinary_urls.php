<?php
require_once __DIR__ . '/../db.php';

echo "=== Cloudinary Database Diagnostics ===\n\n";

function analyze_url($label, $url) {
    if (empty($url)) {
        return;
    }
    
    echo "[$label]: $url\n";
    
    if (strpos($url, 'cloudinary.com') !== false) {
        // Parse resource_type
        if (strpos($url, '/image/upload/') !== false) {
            echo "  -> Resource Type: image/upload (Images or PDF treated as image)\n";
        } elseif (strpos($url, '/raw/upload/') !== false) {
            echo "  -> Resource Type: raw/upload (Raw files/documents)\n";
        } else {
            echo "  -> Resource Type: Other/Unknown upload path\n";
        }
        
        // Parse file extension
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        echo "  -> Extension: $ext\n";
        
        if ($ext === 'pdf' && strpos($url, '/image/upload/') !== false) {
            echo "  -> WARNING: PDF file served from /image/upload/ - this is a known broken combination!\n";
        }
    } else {
        echo "  -> Non-Cloudinary / local path.\n";
    }
    echo "\n";
}

// 1. Check Student Profiles
echo "--- student_profiles ---\n";
$sql = "SELECT user_id, full_name, resume_file, resume_url, aadhaar_file, pan_file FROM student_profiles WHERE resume_file IS NOT NULL OR resume_url IS NOT NULL OR aadhaar_file IS NOT NULL OR pan_file IS NOT NULL LIMIT 10";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        echo "Student: #{$row['user_id']} {$row['full_name']}\n";
        analyze_url("resume_file", $row['resume_file']);
        analyze_url("resume_url", $row['resume_url']);
        analyze_url("aadhaar_file", $row['aadhaar_file']);
        analyze_url("pan_file", $row['pan_file']);
        echo "-----------------------------------------\n";
    }
} else {
    echo "Failed to query student_profiles: " . mysqli_error($conn) . "\n";
}

// 2. Check Internship Applications (Offer Letters / Certificates / snaps)
echo "\n--- internship_applications ---\n";
$sql = "SELECT id, user_id, status, confirmation_letter_path, certificate_path FROM internship_applications WHERE confirmation_letter_path IS NOT NULL OR certificate_path IS NOT NULL LIMIT 10";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        echo "Application #{$row['id']} (User #{$row['user_id']}), Status: {$row['status']}\n";
        analyze_url("confirmation_letter", $row['confirmation_letter_path']);
        analyze_url("certificate", $row['certificate_path']);
        echo "-----------------------------------------\n";
    }
} else {
    echo "Failed to query internship_applications: " . mysqli_error($conn) . "\n";
}
