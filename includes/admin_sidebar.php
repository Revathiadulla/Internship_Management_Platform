<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Define the menu items
$menu_items = [
    'admin_dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
    'admin_users.php' => ['icon' => 'group', 'label' => 'Users'],
    'admin_user_approvals.php' => ['icon' => 'how_to_reg', 'label' => 'User Approvals'],
    'admin_internships.php' => ['icon' => 'work', 'label' => 'Internships'],
    'admin_applications.php' => ['icon' => 'assignment', 'label' => 'Applications'],
    'admin_projects.php' => ['icon' => 'account_tree', 'label' => 'Projects'],
    'admin_project_categories.php' => ['icon' => 'category', 'label' => 'Project Categories'],
    'admin_coordinator_assignments.php' => ['icon' => 'assignment_ind', 'label' => 'Coordinator Assignments'],
    'admin_daily_logs.php' => ['icon' => 'monitoring', 'label' => 'Daily Logs'],
    'admin_dropout_requests.php' => ['icon' => 'person_remove', 'label' => 'Dropout Requests'],
    'admin_reports.php' => ['icon' => 'analytics', 'label' => 'Reports'],
    'admin_received_notifications.php' => ['icon' => 'campaign', 'label' => 'Notifications'],
    'admin_talent_pool.php' => ['icon' => 'stars', 'label' => 'Talent Pool'],
    'confirmation_letter_template.php' => ['icon' => 'description', 'label' => 'Confirmation Letter Template'],
    'certificate_template.php' => ['icon' => 'workspace_premium', 'label' => 'Certificate Template']
];
?>
<aside class="w-64 bg-white border-r border-gray-200 p-6 flex flex-col justify-between overflow-y-auto shrink-0">
  <div class="space-y-6">
    <div>
      <h2 class="text-[10px] font-bold text-gray-400 tracking-widest mb-4 uppercase">Main Menu</h2>
      <nav class="flex flex-col gap-1">
        <?php foreach ($menu_items as $url => $item): ?>
          <?php 
            $is_active = ($current_page === $url);
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
      <a href="logout.php" class="flex items-center gap-3 text-red-600 px-4 py-2.5 rounded-lg hover:bg-red-50 text-sm font-medium transition-colors">
        <span class="material-symbols-outlined text-xl">logout</span>
        Logout
      </a>
    </nav>
  </div>
</aside>
