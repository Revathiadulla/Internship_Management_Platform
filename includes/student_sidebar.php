<?php
// Shared sidebar for student intern workspace
// Requires: $profile, $unread_count, $active_page (string: dashboard|internship|dailylogs|project|feedback|certificate|notifications)
?>
<aside class="fixed left-0 top-0 h-screen w-64 z-40 bg-white border-r border-gray-200 flex flex-col py-6 shadow-sm">
  <div class="px-6 mb-8">
    <a href="index.html" class="flex items-center gap-2">
      <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white font-bold text-xl shadow-sm">I</div>
      <span class="text-xl font-bold text-slate-800 tracking-tight">IMP</span>
    </a>
    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Student Portal</p>
  </div>

  <nav class="flex-1 space-y-1 px-4 overflow-y-auto">
    <?php
    $nav = [
      ['page'=>'dashboard',     'href'=>'student_dashboard.php',     'icon'=>'dashboard',          'label'=>'Dashboard'],
      ['page'=>'internship',    'href'=>'student_internship.php',    'icon'=>'badge',              'label'=>'My Internship'],
      ['page'=>'dailylogs',     'href'=>'student_daily_log.php',     'icon'=>'edit_note',          'label'=>'Daily Logs'],
      ['page'=>'project',       'href'=>'student_project.php',       'icon'=>'terminal',           'label'=>'Project'],
      ['page'=>'feedback',      'href'=>'student_feedback.php',      'icon'=>'reviews',            'label'=>'Feedback'],
      ['page'=>'certificate',   'href'=>'student_certificate.php',   'icon'=>'workspace_premium',  'label'=>'Certificate'],
      ['page'=>'notifications', 'href'=>'student_notifications.php', 'icon'=>'notifications',      'label'=>'Notifications'],
    ];
    foreach ($nav as $item):
      $is_active = ($active_page === $item['page']);
      $cls = $is_active
        ? 'flex items-center gap-3 bg-blue-50 text-blue-700 rounded-lg px-4 py-3 font-medium shadow-sm'
        : 'flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all';
    ?>
    <a class="<?php echo $cls; ?>" href="<?php echo $item['href']; ?>">
      <span class="material-symbols-outlined"><?php echo $item['icon']; ?></span>
      <span class="text-sm font-medium"><?php echo $item['label']; ?></span>
      <?php if ($item['page'] === 'notifications' && $unread_count > 0): ?>
        <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1">
    <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="#">
      <span class="material-symbols-outlined">help</span>
      <span class="text-sm font-medium">Help Center</span>
    </a>
    <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all" href="login.php">
      <span class="material-symbols-outlined">logout</span>
      <span class="text-sm font-medium">Logout</span>
    </a>
  </div>
</aside>
