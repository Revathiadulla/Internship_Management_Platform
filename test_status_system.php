<?php
// Test page to demonstrate the status tracking system
include "db.php";
include "status_utils.php";

// Sample statuses to display
$sample_statuses = [
    'Applied',
    'Test Completed',
    'HR Round',
    'HR Approved',
    'HOD Approval Pending',
    'HOD Approved',
    'Selected',
    'Rejected',
    'Under Review',
    'Interview Scheduled',
    'Offer Sent',
    'Onboarding Completed'
];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Status Tracking System - Demo</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
  
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-[#f8f9fa] text-[#191c1d] font-sans antialiased p-8">
  
  <div class="max-w-6xl mx-auto space-y-8">
    
    <!-- Header -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8">
      <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-2">Application Status Tracking System</h1>
      <p class="text-slate-500">Comprehensive status management with timeline UI, history tracking, and role-based updates</p>
      <div class="mt-4 flex gap-2">
        <span class="px-3 py-1 bg-green-50 text-green-700 text-xs font-bold rounded-full border border-green-200">✓ Database Migration Complete</span>
        <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-full border border-blue-200">✓ Status Update Handler</span>
        <span class="px-3 py-1 bg-purple-50 text-purple-700 text-xs font-bold rounded-full border border-purple-200">✓ Timeline Component</span>
        <span class="px-3 py-1 bg-amber-50 text-amber-700 text-xs font-bold rounded-full border border-amber-200">✓ HR Dashboard</span>
      </div>
    </div>

    <!-- Status Badge Showcase -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8">
      <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-blue-600">palette</span>
        Status Badge Color System
      </h2>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($sample_statuses as $status): ?>
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-100 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-2 mb-2">
              <span class="material-symbols-outlined text-slate-400 text-[20px]"><?php echo getStatusIcon($status); ?></span>
              <span class="text-xs font-bold text-slate-500 uppercase">Status</span>
            </div>
            <span class="inline-flex px-3 py-1.5 rounded-full text-xs font-bold tracking-wide border <?php echo getStatusBadgeClass($status); ?>">
              <?php echo htmlspecialchars($status); ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Workflow Comparison -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Pursuing Student Workflow -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
        <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-blue-600">school</span>
          Pursuing Student Workflow
        </h3>
        <p class="text-sm text-slate-500 mb-6">Includes HOD approval step</p>
        <div class="space-y-3">
          <?php 
          $pursuing_steps = getWorkflowSteps('Pursuing');
          foreach ($pursuing_steps as $index => $step): 
          ?>
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100">
              <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                <?php echo $index + 1; ?>
              </div>
              <div class="flex-1">
                <p class="font-semibold text-slate-800"><?php echo $step['label']; ?></p>
              </div>
              <span class="material-symbols-outlined text-slate-400"><?php echo $step['icon']; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Passed Out Student Workflow -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
        <h3 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-purple-600">workspace_premium</span>
          Passed Out Student Workflow
        </h3>
        <p class="text-sm text-slate-500 mb-6">Skips HOD approval step</p>
        <div class="space-y-3">
          <?php 
          $passedout_steps = getWorkflowSteps('Passed Out');
          foreach ($passedout_steps as $index => $step): 
          ?>
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100">
              <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center font-bold text-sm">
                <?php echo $index + 1; ?>
              </div>
              <div class="flex-1">
                <p class="font-semibold text-slate-800"><?php echo $step['label']; ?></p>
              </div>
              <span class="material-symbols-outlined text-slate-400"><?php echo $step['icon']; ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- Key Features -->
    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border border-blue-100 shadow-sm p-8">
      <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-blue-600">star</span>
        Key Features Implemented
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-blue-100">
          <span class="material-symbols-outlined text-green-600 text-[24px]">check_circle</span>
          <div>
            <h4 class="font-bold text-slate-800 mb-1">Status History Tracking</h4>
            <p class="text-sm text-slate-600">Complete audit trail of all status changes with timestamps and user info</p>
          </div>
        </div>
        <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-blue-100">
          <span class="material-symbols-outlined text-green-600 text-[24px]">check_circle</span>
          <div>
            <h4 class="font-bold text-slate-800 mb-1">Visual Timeline UI</h4>
            <p class="text-sm text-slate-600">Interactive progress bar with color-coded workflow nodes</p>
          </div>
        </div>
        <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-blue-100">
          <span class="material-symbols-outlined text-green-600 text-[24px]">check_circle</span>
          <div>
            <h4 class="font-bold text-slate-800 mb-1">Conditional Workflow</h4>
            <p class="text-sm text-slate-600">Automatic HOD step handling based on education status</p>
          </div>
        </div>
        <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-blue-100">
          <span class="material-symbols-outlined text-green-600 text-[24px]">check_circle</span>
          <div>
            <h4 class="font-bold text-slate-800 mb-1">Role-Based Updates</h4>
            <p class="text-sm text-slate-600">HR and Coordinator dashboards with inline status controls</p>
          </div>
        </div>
        <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-blue-100">
          <span class="material-symbols-outlined text-green-600 text-[24px]">check_circle</span>
          <div>
            <h4 class="font-bold text-slate-800 mb-1">AJAX Status Updates</h4>
            <p class="text-sm text-slate-600">Real-time updates without page reload, with toast notifications</p>
          </div>
        </div>
        <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-blue-100">
          <span class="material-symbols-outlined text-green-600 text-[24px]">check_circle</span>
          <div>
            <h4 class="font-bold text-slate-800 mb-1">Reusable Components</h4>
            <p class="text-sm text-slate-600">Modular PHP functions and utility helpers for easy integration</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Links -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-8">
      <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-blue-600">link</span>
        Quick Access Links
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="student_applications.php" class="p-4 bg-blue-50 hover:bg-blue-100 rounded-xl border border-blue-200 transition-colors group">
          <div class="flex items-center gap-3 mb-2">
            <span class="material-symbols-outlined text-blue-600 text-[28px]">assignment</span>
            <h4 class="font-bold text-slate-800 group-hover:text-blue-600 transition-colors">My Applications</h4>
          </div>
          <p class="text-sm text-slate-600">View your applications with status timeline</p>
        </a>
        <a href="hr_applications.php" class="p-4 bg-purple-50 hover:bg-purple-100 rounded-xl border border-purple-200 transition-colors group">
          <div class="flex items-center gap-3 mb-2">
            <span class="material-symbols-outlined text-purple-600 text-[28px]">admin_panel_settings</span>
            <h4 class="font-bold text-slate-800 group-hover:text-purple-600 transition-colors">HR Dashboard</h4>
          </div>
          <p class="text-sm text-slate-600">Manage applications and update statuses</p>
        </a>
        <a href="STATUS_TRACKING_IMPLEMENTATION.md" class="p-4 bg-emerald-50 hover:bg-emerald-100 rounded-xl border border-emerald-200 transition-colors group">
          <div class="flex items-center gap-3 mb-2">
            <span class="material-symbols-outlined text-emerald-600 text-[28px]">description</span>
            <h4 class="font-bold text-slate-800 group-hover:text-emerald-600 transition-colors">Documentation</h4>
          </div>
          <p class="text-sm text-slate-600">Complete implementation guide</p>
        </a>
      </div>
    </div>

    <!-- Footer -->
    <div class="text-center py-6 text-slate-400 text-sm">
      <p>Application Status Tracking System v1.0</p>
      <p class="mt-1">Internship Management Platform (IMP) • May 19, 2026</p>
    </div>

  </div>

</body>
</html>
