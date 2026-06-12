<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Define the menu items
$in_admin = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$a_prefix = $in_admin ? '' : 'admin/';
$r_prefix = $in_admin ? '../' : '';
$menu_items = [
    $a_prefix . 'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
    $a_prefix . 'users.php' => ['icon' => 'group', 'label' => 'Users'],
    $a_prefix . 'user_approvals.php' => ['icon' => 'how_to_reg', 'label' => 'User Approvals'],
    $a_prefix . 'internships.php' => ['icon' => 'work', 'label' => 'Internships'],
    $a_prefix . 'applications.php' => ['icon' => 'assignment', 'label' => 'Applications'],
    $a_prefix . 'projects.php' => ['icon' => 'account_tree', 'label' => 'Projects'],
    $a_prefix . 'project_categories.php' => ['icon' => 'category', 'label' => 'Project Categories'],
    $a_prefix . 'coordinator_assignments.php' => ['icon' => 'assignment_ind', 'label' => 'Coordinator Assignments'],
    '/IMP/admin/student_logs.php' => ['icon' => 'monitoring', 'label' => 'Student Logs'],
    $a_prefix . 'dropout_requests.php' => ['icon' => 'person_remove', 'label' => 'Dropout Requests'],
    $a_prefix . 'reports.php' => ['icon' => 'analytics', 'label' => 'Reports'],
    '/IMP/admin/notifications.php' => ['icon' => 'campaign', 'label' => 'Notifications'],
    $a_prefix . 'talent_pool.php' => ['icon' => 'stars', 'label' => 'Talent Pool'],
    '/IMP/admin/confirmation_letter_template.php' => ['icon' => 'description', 'label' => 'Confirmation Letter Template'],
    '/IMP/admin/certificate_template.php' => ['icon' => 'workspace_premium', 'label' => 'Certificate Template']
];
?>
<aside class="w-64 bg-white border-r border-gray-200 p-6 flex flex-col justify-between overflow-y-auto shrink-0">
  <div class="space-y-6">
    <!-- Brand Header for Admin -->
    <div class="mb-8">
      <a href="<?php echo $r_prefix; ?>index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-blue-600 text-sm font-extrabold text-white">IMP</span>
        <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
      </a>
      <p class="ml-1 mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">ADMIN PORTAL</p>
    </div>
    <div>
      <h2 class="text-[10px] font-bold text-gray-400 tracking-widest mb-4 uppercase">Main Menu</h2>
      <nav class="flex flex-col gap-1">
        <?php foreach ($menu_items as $url => $item): ?>
          <?php 
            $is_active = ($current_page === basename($url));
            $active_classes = $is_active 
                ? 'bg-blue-50 text-blue-700 border-l-4 border-blue-600 font-bold px-4 py-2.5 rounded-r-lg' 
                : 'text-gray-700 px-4 py-2.5 rounded-lg hover:bg-gray-50 font-medium transition-colors';
          ?>
          <a href="<?php echo htmlspecialchars($url); ?>" class="flex items-center gap-3 text-sm <?php echo $active_classes; ?>">
            <span class="material-symbols-outlined text-xl"><?php echo htmlspecialchars($item['icon']); ?></span>
            <?php echo htmlspecialchars($item['label']); ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>
  <div>
    <nav class="flex flex-col gap-1 border-t border-gray-150 pt-4">
      <a href=<?php echo $r_prefix; ?>logout.php class="flex items-center gap-3 text-red-600 px-4 py-2.5 rounded-lg hover:bg-red-50 text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-xl">logout</span>
        Logout
      </a>
    </nav>
  </div>
</aside>
