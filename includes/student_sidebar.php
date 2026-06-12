<?php
// Shared sidebar for student intern workspace
// Requires: $profile, $unread_count, $active_page (string: dashboard|internship|dailylogs|project|feedback|certificate|notifications)
?>
<aside class="fixed left-0 top-0 h-screen w-64 z-40 bg-white border-r border-gray-200 flex flex-col py-6 shadow-sm">
  <div class="px-6 mb-8">
    <a href="../index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
      <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="32" height="32" rx="8" fill="currentColor"/>
        <circle cx="16" cy="16" r="3" fill="white"/>
        <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
        <circle cx="16" cy="8" r="1.5" fill="white"/>
        <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
        <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
        <line x1="17.8" y1="18.4" x2="20.0" y2="21.5" stroke="white" stroke-width="1.5"/>
        <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
        <line x1="14.2" y1="18.4" x2="12.0" y2="21.5" stroke="white" stroke-width="1.5"/>
        <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
        <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
        <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
      </svg>
      <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
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
    <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-red-50 hover:text-red-600 transition-all" href="../login.php">
      <span class="material-symbols-outlined">logout</span>
      <span class="text-sm font-medium">Logout</span>
    </a>
  </div>
</aside>
