<?php
function admin_template_header($title) {
    global $conn;
    $header_uid = $_SESSION['user_id'];
    $header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
    $header_user = mysqli_fetch_assoc($header_res);
    $header_name = $header_user['full_name'] ?? 'Admin';
    $header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo htmlspecialchars($title); ?> - Admin IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-slate-800 h-screen flex flex-col overflow-hidden">
<header class="bg-white border-b border-gray-200 z-10 shrink-0 shadow-sm">
  <div class="flex items-center justify-between px-6 py-3">
    <div class="flex items-center gap-4">
      <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-inner">
        <span class="material-symbols-outlined text-white text-xl">admin_panel_settings</span>
      </div>
      <div>
        <h1 class="text-xl font-bold text-gray-900 tracking-tight leading-none"><?php echo htmlspecialchars($title); ?></h1>
        <p class="text-xs text-gray-500 font-medium mt-0.5">Admin Portal Template Manager</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-full bg-blue-100 flex justify-center items-center text-blue-700 font-bold text-sm">
         <?php echo substr($header_name, 0, 1); ?>
      </div>
    </div>
  </div>
</header>
<div class="flex flex-1 overflow-hidden">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
<?php
}

function admin_template_footer() {
?>
    </main>
</div>
</body>
</html>
<?php
}
