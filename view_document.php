<?php
/**
 * view_document.php
 * Secure document viewer displaying documents inside an iframe via Google Docs Viewer for remote files.
 */

session_start();
include_once __DIR__ . '/includes/auth.php';
include 'db.php';

// Authorization check: Require user to be logged in
require_login();

// Parameters
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$file = isset($_GET['file']) ? trim($_GET['file']) : '';
$mode = isset($_GET['mode']) && $_GET['mode'] === 'download' ? 'download' : 'view';

// Handle legacy/fallback parameters: if url is empty but file is specified
if ($url === '' && $file !== '') {
    if (strpos($file, 'http://') === 0 || strpos($file, 'https://') === 0) {
        $url = $file;
    } else {
        $url = $file;
    }
}

// Basic validation for empty URL
if ($url === '') {
    http_response_code(400);
    exit('Error: Missing document URL or file parameter.');
}

$is_remote = (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);

// For local files, we can stream them when stream=1 is passed
if (isset($_GET['stream']) && $_GET['stream'] == '1') {
    if (!$is_remote) {
        $filename = basename($url);
        $resolved_path = resolve_resume_file_path($filename);

        if ($resolved_path !== null && is_file($resolved_path)) {
            $ext = strtolower(pathinfo($resolved_path, PATHINFO_EXTENSION));
            $mime_map = [
                'pdf'  => 'application/pdf',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png'
            ];
            $mime = $mime_map[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($resolved_path));
            header("X-Content-Type-Options: nosniff");
            if ($mode === 'download') {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $filename . '"');
            }
            if (ob_get_level()) ob_end_clean();
            readfile($resolved_path);
            exit();
        }
    }
    http_response_code(404);
    exit('Error: Local document not found.');
}

// Build the IFrame Source URL
if ($is_remote) {
    $iframe_src = "https://docs.google.com/gview?embedded=true&url=" . urlencode($url);
} else {
    // Local files stream directly in the iframe
    $iframe_src = "view_document.php?url=" . urlencode($url) . "&stream=1";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Viewer | IMP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-slate-900 text-slate-100 antialiased overflow-hidden h-screen flex flex-col">
  <!-- Top Bar -->
  <header class="bg-slate-800 border-b border-slate-700 h-16 flex items-center justify-between px-6 shrink-0 shadow-md">
    <div class="flex items-center gap-3">
      <span class="material-symbols-outlined text-blue-400 text-[28px]">description</span>
      <div>
        <h1 class="text-sm font-bold text-white leading-tight">Document Viewer</h1>
        <p class="text-[11px] text-slate-400 truncate max-w-md"><?php echo htmlspecialchars(basename($url)); ?></p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($is_remote): ?>
        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow transition">
          <span class="material-symbols-outlined text-[16px]">download</span> Direct Link / Download
        </a>
      <?php else: ?>
        <a href="view_document.php?url=<?php echo urlencode($url); ?>&stream=1&mode=download" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow transition">
          <span class="material-symbols-outlined text-[16px]">download</span> Direct Link / Download
        </a>
      <?php endif; ?>
      <button onclick="window.close();" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 font-bold rounded-xl transition">
        <span class="material-symbols-outlined text-[16px]">close</span> Close
      </button>
    </div>
  </header>

  <!-- IFrame Container -->
  <div class="flex-1 bg-slate-950 relative h-full">
    <iframe src="<?php echo htmlspecialchars($iframe_src); ?>" class="w-full h-full border-none"></iframe>
  </div>
</body>
</html>
