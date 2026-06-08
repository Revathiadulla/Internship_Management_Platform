<?php
require_once __DIR__ . '/../includes/document_helper.php';

function test_assert($condition, $message) {
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        exit(1);
    }
}

echo "Running document helper test suite...\n\n";

// 1. Test getDocumentUrl with empty
test_assert(getDocumentUrl('') === 'unavailable', "Empty URL returns 'unavailable'");
test_assert(getDocumentUrl(null) === 'unavailable', "Null URL returns 'unavailable'");

// 2. Test getDocumentUrl with legacy broken PDF URL
$broken_url = "https://res.cloudinary.com/demo/image/upload/v123456/sample.pdf";
test_assert(getDocumentUrl($broken_url) === 'unavailable', "Legacy broken PDF Cloudinary URL returns 'unavailable'");

// 3. Test getDocumentUrl with valid remote URL
$valid_url = "https://res.cloudinary.com/demo/raw/upload/v123456/sample.pdf";
test_assert(getDocumentUrl($valid_url) === $valid_url, "Valid remote URL returns unchanged URL");

// 4. Test getDocumentName
test_assert(getDocumentName("My Resume.pdf", $valid_url) === "My Resume.pdf", "getDocumentName returns original name if present");
test_assert(getDocumentName("", $valid_url) === "sample.pdf", "getDocumentName extracts filename from URL if original name empty");
test_assert(getDocumentName("", "http://example.com/foo.docx?param=123") === "foo.docx", "getDocumentName handles query parameters correctly");

// 5. Test renderViewButton and renderDownloadButton outputs
$view_btn = renderViewButton($valid_url, 'btn-class', 'View File');
test_assert(strpos($view_btn, 'href="https://res.cloudinary.com/demo/raw/upload/v123456/sample.pdf"') !== false, "renderViewButton has correct href");
test_assert(strpos($view_btn, 'class="btn-class"') !== false, "renderViewButton includes custom class");
test_assert(strpos($view_btn, 'target="_blank"') !== false, "renderViewButton has target='_blank'");

$broken_view_btn = renderViewButton($broken_url, 'btn-class', 'View File');
test_assert(strpos($broken_view_btn, 'Document unavailable') !== false, "renderViewButton displays unavailable text for broken URL");
test_assert(strpos($broken_view_btn, '<a') === false, "renderViewButton does not render link for broken URL");

$download_btn = renderDownloadButton($valid_url, 'btn-dl', 'Download File');
test_assert(strpos($download_btn, 'download') !== false, "renderDownloadButton has download attribute");

$broken_download_btn = renderDownloadButton($broken_url, 'btn-dl', 'Download File');
test_assert($broken_download_btn === '', "renderDownloadButton returns empty string for broken URL");

echo "\nAll document helper tests PASSED successfully!\n";
