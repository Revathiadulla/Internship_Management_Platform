<?php
/**
 * includes/document_helper.php
 * Common helper functions for secure and reliable document handling.
 */

if (!function_exists('getDocumentUrl')) {
    /**
     * Inspects and returns a clean, secure document URL.
     * Logs errors for empty or invalid paths.
     *
     * @param string $url The document URL or local path.
     * @return string The valid URL, or 'unavailable' if invalid/broken.
     */
    function getDocumentUrl($url) {
        $url = ($url !== null) ? trim($url) : '';

        if ($url === '') {
            error_log("Document helper: empty file URL or path.");
            return 'unavailable';
        }

        // Check for old broken Cloudinary PDF URLs (PDF uploaded to image folder)
        if (strpos($url, '/image/upload/') !== false && preg_match('/\.pdf$/i', $url)) {
            error_log("Document helper: legacy broken PDF URL detected (pdf in /image/upload/): " . $url);
            return 'unavailable';
        }

        // Check if it's a remote URL
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }

        // It is a local path. Check environment to see if it should be rejected.
        $db_host = getenv('DB_HOST') ?: "by7xxebmaxfwobqrh1ne-mysql.services.clever-cloud.com";
        $is_local = (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
        $is_production = !$is_local && (getenv('APP_ENV') === 'production' || strpos($db_host, 'clever-cloud.com') !== false || strpos($db_host, 'render.com') !== false);

        if ($is_production) {
            $local_root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : __DIR__ . '/..';
            $file_on_disk = rtrim($local_root, '/\\') . '/' . ltrim($url, '/\\');
            $file_relative = __DIR__ . '/../' . ltrim($url, '/\\');
            if (!file_exists($file_on_disk) && !file_exists($file_relative)) {
                error_log("Document helper: invalid local path in production (file not found): " . $url);
                return 'unavailable';
            }
        }

        return $url;
    }
}

if (!function_exists('getDocumentName')) {
    /**
     * Returns the original document name if present, otherwise extracts the basename from the URL/path.
     *
     * @param string $originalName The stored original filename.
     * @param string $url The document URL or path.
     * @return string The clean name.
     */
    function getDocumentName($originalName, $url) {
        $originalName = ($originalName !== null) ? trim($originalName) : '';
        if ($originalName !== '') {
            return $originalName;
        }

        $url = ($url !== null) ? trim($url) : '';
        if ($url === '' || $url === 'unavailable') {
            return 'Document';
        }

        // Extract basename from URL (discarding query parameters)
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            return basename($path);
        }
        return basename($url);
    }
}

if (!function_exists('renderViewButton')) {
    /**
     * Renders a target="_blank" view link, or an "unavailable" label.
     *
     * @param string $url The document URL or path.
     * @param string $customClass Custom HTML classes for the anchor tag.
     * @param string $label The text/HTML content of the link.
     * @return string HTML output.
     */
    function renderViewButton($url, $customClass = '', $label = 'View') {
        $resolved = getDocumentUrl($url);
        if ($resolved === 'unavailable') {
            return '<span class="text-xs text-red-650 font-semibold bg-red-50 border border-red-200 rounded px-2.5 py-1.5 inline-block">Document unavailable. Please ask student to update/reupload document.</span>';
        }
        $classAttr = ($customClass !== '') ? ' class="' . htmlspecialchars($customClass) . '"' : '';
        return '<a href="' . htmlspecialchars($resolved) . '" target="_blank" rel="noopener noreferrer"' . $classAttr . '>' . $label . '</a>';
    }
}

if (!function_exists('renderDownloadButton')) {
    /**
     * Renders a download link or returns empty string if unavailable.
     *
     * @param string $url The document URL or path.
     * @param string $customClass Custom HTML classes for the anchor tag.
     * @param string $label The text/HTML content of the link.
     * @return string HTML output.
     */
    function renderDownloadButton($url, $customClass = '', $label = 'Download') {
        $resolved = getDocumentUrl($url);
        if ($resolved === 'unavailable') {
            return '';
        }
        $classAttr = ($customClass !== '') ? ' class="' . htmlspecialchars($customClass) . '"' : '';
        return '<a href="' . htmlspecialchars($resolved) . '" download' . $classAttr . '>' . $label . '</a>';
    }
}
