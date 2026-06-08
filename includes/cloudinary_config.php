<?php
/**
 * includes/cloudinary_config.php
 * Configuration and helper for Cloudinary File Storage.
 */

// Load Composer autoloader
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // In case autoload is in root and we are executed from elsewhere
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

// Retrieve environment variables
$cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
$api_key    = getenv('CLOUDINARY_API_KEY');
$api_secret = getenv('CLOUDINARY_API_SECRET');

// Initialize Cloudinary Configuration
if (!empty($cloud_name) && !empty($api_key) && !empty($api_secret)) {
    Configuration::instance([
        'cloud' => [
            'cloud_name' => $cloud_name,
            'api_key'    => $api_key,
            'api_secret' => $api_secret,
        ],
        'url' => [
            'secure' => true
        ]
    ]);
}

/**
 * Uploads a local file to Cloudinary.
 *
 * @param string $file_path The absolute or relative path to the local file.
 * @param string $folder The Cloudinary folder to store the file in.
 * @param bool $is_raw Force resource_type = "raw" (for PDF, DOC, DOCX files).
 * @return string The secure URL of the uploaded file.
 * @throws Exception If upload fails or configuration is missing.
 */
function uploadToCloudinary($file_path, $folder, $is_raw = false, $original_filename = null) {
    $cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
    $api_key    = getenv('CLOUDINARY_API_KEY');
    $api_secret = getenv('CLOUDINARY_API_SECRET');

    if (empty($cloud_name) || empty($api_key) || empty($api_secret)) {
        throw new Exception("Cloudinary environment variables are not configured.");
    }

    if (!file_exists($file_path)) {
        throw new Exception("Local file not found for upload: " . $file_path);
    }

    try {
        $uploadApi = new UploadApi();
        
        $upload_file_path = $file_path;
        $temp_created = false;
        
        if (!empty($original_filename)) {
            // Clean original filename to prevent path traversal
            $clean_name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $original_filename);
            $temp_dir = sys_get_temp_dir();
            $upload_file_path = $temp_dir . DIRECTORY_SEPARATOR . $clean_name;
            if (copy($file_path, $upload_file_path)) {
                $temp_created = true;
            } else {
                $upload_file_path = $file_path; // Fallback
            }
        }
        
        $options = [
            'folder' => $folder,
            'resource_type' => 'raw',
            'use_filename' => true,
            'unique_filename' => false
        ];

        $response = $uploadApi->upload($upload_file_path, $options);
        
        if ($temp_created) {
            @unlink($upload_file_path);
        }

        if (isset($response['secure_url']) && !empty($response['secure_url'])) {
            return $response['secure_url'];
        }

        throw new Exception("Upload response did not contain secure_url.");
    } catch (Exception $e) {
        error_log("Cloudinary Upload Error: " . $e->getMessage());
        throw new Exception("Failed to upload file to Cloudinary: " . $e->getMessage());
    }
}

/**
 * Returns a Google Docs Viewer URL for PDF files or raw Cloudinary files.
 *
 * @param string $url The original file URL.
 * @return string The viewer URL or original URL.
 */
if (!function_exists('getDocumentViewUrl')) {
    function getDocumentViewUrl($url) {
        if (empty($url)) return '#';
        return $url;
    }
}
