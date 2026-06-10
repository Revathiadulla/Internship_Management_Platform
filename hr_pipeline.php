<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_hr_or_admin();
include "db.php";
include "status_utils.php";

$status_options    = ['Applied', 'HR Review', 'Shortlisted', 'Exam Mail Sent', 'HOD Pending', 'HOD Approved', 'Selected', 'Rejected'];
$verification_options = ['Pending', 'Verified', 'Rejected'];
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$verification_filter = isset($_GET['verification_status']) ? trim($_GET['verification_status']) : '';
$title_filter = isset($_GET['title']) ? trim($_GET['title']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$pipeline_statuses = [
    'Applied',
    'HR Review',
    'Shortlisted',
    'Exam Mail Sent',
    'HOD Pending',
    'HOD Approved',
    'Selected',
    'Rejected'
];

$pipeline = array_fill_keys($pipeline_statuses, []);

$where_clauses = ["a.is_deleted = 0"];
if (in_array($status_filter, $status_options, true)) {
    $where_clauses[] = "a.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if (in_array($verification_filter, $verification_options, true)) {
    $where_clauses[] = "a.verification_status = '" . mysqli_real_escape_string($conn, $verification_filter) . "'";
}
if ($title_filter !== '') {
    $title_escaped = mysqli_real_escape_string($conn, $title_filter);
    $where_clauses[] = "COALESCE(i.title, a.internship_name) LIKE '%$title_escaped%'";
}
if ($search_query !== '') {
    $search_escaped = mysqli_real_escape_string($conn, $search_query);
    $where_clauses[] = "(sp.full_name LIKE '%$search_escaped%' OR sp.email LIKE '%$search_escaped%')";
}
$app_sql = "SELECT a.id as app_id,
                   a.user_id,
                   a.status,
                   a.verification_status,
                   a.education_status,
                   COALESCE(i.title, a.internship_name) as title,
                   COALESCE(sp.full_name, 'Unknown Student') as full_name,
                   COALESCE(sp.college_name, '') as college_name,
                   a.applied_date
            FROM internship_applications a
            LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            WHERE " . implode(' AND ', $where_clauses) . "
            ORDER BY a.applied_date DESC";
$app_result = mysqli_query($conn, $app_sql);

if ($app_result) {
    while ($app = mysqli_fetch_assoc($app_result)) {
        $status = trim($app['status']);
        if (!in_array($status, $pipeline_statuses, true)) {
            $status = 'Applied';
        }
        $pipeline[$status][] = $app;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>HR Pipeline View - IMP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet" />
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
    .pipeline-board { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 1.25rem; }
    .pipeline-column { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1rem; min-height: 24rem; display: flex; flex-col: column; }
    .pipeline-column h3 { font-size: 0.85rem; letter-spacing: 0.12em; text-transform: uppercase; color: #64748b; margin-bottom: 0.75rem; }
    .pipeline-column p.count { font-size: 0.95rem; color: #0f172a; font-weight: 700; margin-top: 0.25rem; }
    .pipeline-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1rem; margin-bottom: 0.9rem; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: grab; }
    .pipeline-card:active { cursor: grabbing; }
    .pipeline-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
    .pipeline-card h4 { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.35rem; }
    .pipeline-card p { margin: 0; color: #475569; font-size: 0.88rem; }
    .badge-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.3rem 0.6rem; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
    .pipeline-column .empty-state { color: #94a3b8; font-size: 0.9rem; padding: 1rem 0.5rem; text-align: center; border: 2px dashed #f1f5f9; border-radius: 0.75rem; margin-top: 0.5rem; }
    .pipeline-column.drag-over { background-color: #eff6ff; border-color: #3b82f6; border-style: dashed; }
    @media (max-width: 1600px) { .pipeline-board { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (max-width: 1280px) { .pipeline-board { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 900px) { .pipeline-board { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .pipeline-board { grid-template-columns: 1fr; } }
  </style>
</head>
<body class="bg-[#f8f9fa] text-[#111827] antialiased">
  <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 text-sm font-medium">
    <div class="px-6 mb-8">
      <a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
        <svg class="w-8 h-8 text-blue-600" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="currentColor"/><circle cx="16" cy="16" r="3" fill="white"/><line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/><circle cx="16" cy="8" r="1.5" fill="white"/><line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/><circle cx="23.5" cy="13.5" r="1.5" fill="white"/><line x1="17.8" y1="18.4" x2="20" y2="21.5" stroke="white" stroke-width="1.5"/><circle cx="20.7" cy="22.5" r="1.5" fill="white"/><line x1="14.2" y1="18.4" x2="12" y2="21.5" stroke="white" stroke-width="1.5"/><circle cx="11.3" cy="22.5" r="1.5" fill="white"/><line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/><circle cx="8.5" cy="13.5" r="1.5" fill="white"/></svg>
        <span class="text-xl font-bold text-blue-600">IMP</span>
      </a>
      <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-2">HR Portal</p>
    </div>
    <nav class="flex-1 flex flex-col gap-1 px-4">
      <a href="hr_dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition"> <span class="material-symbols-outlined">dashboard</span> Dashboard</a>
      <a href="hr_applications.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition"> <span class="material-symbols-outlined">assignment</span> Applications</a>
      <a href="candidates.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition"> <span class="material-symbols-outlined">group</span> Candidates</a>
      <a href="student_logs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition"> <span class="material-symbols-outlined">description</span> Student Logs</a>
      <a href="hr_hiring_requests.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition"> <span class="material-symbols-outlined">handshake</span> Hiring Requests</a>
      <a href="hr_reports.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition"> <span class="material-symbols-outlined">analytics</span> Reports</a>
      <a href="admin_received_notifications.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition"> <span class="material-symbols-outlined">notifications</span> Notifications</a>
    </nav>
    <div class="mt-auto border-t border-gray-200 pt-4 px-4">
      <a href="logout.php" class="flex items-center gap-3 text-gray-600 py-3 hover:bg-gray-100 rounded-xl transition"><span class="material-symbols-outlined">logout</span> Logout</a>
    </div>
  </aside>
  <main class="pl-60">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40 px-8 py-4 flex items-center justify-between shadow-sm">
      <div>
        <h1 class="text-3xl font-extrabold text-slate-900">HR Pipeline</h1>
        <p class="text-sm text-slate-500 mt-1">Kanban-style view grouped by current application status.</p>
      </div>
      <div class="flex items-center gap-3">
        <button onclick="location.reload()" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition font-semibold text-sm">Refresh Board</button>
      </div>
    </header>
    <section class="px-8 py-8 space-y-6">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <form method="get" class="grid gap-4 xl:grid-cols-5">
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Status</label>
            <select name="status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-white">
              <option value="">All Statuses</option>
              <?php foreach ($status_options as $status_option): ?>
                <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $status_filter === $status_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Verification</label>
            <select name="verification_status" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 bg-white">
              <option value="">All Verifications</option>
              <?php foreach ($verification_options as $verification_option): ?>
                <option value="<?php echo htmlspecialchars($verification_option); ?>" <?php echo $verification_filter === $verification_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($verification_option); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Internship title</label>
            <input name="title" value="<?php echo htmlspecialchars($title_filter); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700" placeholder="Filter by title" />
          </div>
          <div>
            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Search</label>
            <input name="search" value="<?php echo htmlspecialchars($search_query); ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700" placeholder="Name or email" />
          </div>
          <div class="flex items-end gap-2">
            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">Apply</button>
            <a href="hr_pipeline.php" class="w-full text-center border border-slate-200 rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition">Reset</a>
          </div>
        </form>
      </div>

      <div class="pipeline-board">
        <?php foreach ($pipeline_statuses as $column_status): ?>
          <div class="pipeline-column transition-colors duration-200" 
               ondragover="allowDrop(event)" 
               ondragenter="dragEnter(event)"
               ondragleave="dragLeave(event)"
               ondrop="drop(event)" 
               data-status="<?php echo htmlspecialchars($column_status); ?>">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
              <div>
                <h3 class="font-bold text-xs tracking-wider text-slate-500"><?php echo htmlspecialchars($column_status); ?></h3>
                <p class="count text-slate-900 font-extrabold text-sm"><?php echo count($pipeline[$column_status]); ?> candidates</p>
              </div>
            </div>

            <div class="space-y-3 flex-1 min-h-[300px]">
              <?php if (empty($pipeline[$column_status])): ?>
                <div class="empty-state">No candidates</div>
              <?php else: ?>
                <?php foreach ($pipeline[$column_status] as $item): ?>
                  <div class="pipeline-card" 
                       draggable="true" 
                       ondragstart="drag(event)" 
                       id="card-<?php echo $item['app_id']; ?>" 
                       data-app-id="<?php echo $item['app_id']; ?>" 
                       data-education="<?php echo htmlspecialchars($item['education_status']); ?>">
                    <h4 class="font-bold text-slate-800 text-sm tracking-tight"><?php echo htmlspecialchars($item['full_name']); ?></h4>
                    <p class="text-xs text-slate-500 mt-1 truncate"><?php echo htmlspecialchars($item['title']); ?></p>
                    
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                      <span class="badge-pill border <?php echo getVerificationBadgeClass($item['verification_status'] ?: 'Pending'); ?>">
                        <?php echo htmlspecialchars($item['verification_status'] ?: 'Pending'); ?>
                      </span>
                    </div>

                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-100">
                      <a href="hr_applicant_detail.php?app_id=<?php echo $item['app_id']; ?>" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700 hover:underline">
                        View Profile
                      </a>
                      <span class="text-[10px] text-slate-400 font-medium"><?php echo date('M d', strtotime($item['applied_date'])); ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-6 right-6 z-50 bg-white rounded-xl shadow-xl px-5 py-4 border flex items-center gap-3 transform translate-x-[400px] transition-transform duration-500 ease-out hidden">
    <div class="w-8 h-8 rounded-lg flex items-center justify-center" id="toast-icon-container">
      <span class="material-symbols-outlined text-[20px]" id="toast-icon">check_circle</span>
    </div>
    <div>
      <p class="text-xs font-bold uppercase tracking-wider" id="toast-title">Success</p>
      <p class="text-sm font-bold tracking-tight mt-0.5" id="toast-message">Status updated successfully</p>
    </div>
  </div>

  <script>
    const toast = document.getElementById('toast');
    function showToast(type, title, message) {
      const toastIcon = document.getElementById('toast-icon');
      const toastIconContainer = document.getElementById('toast-icon-container');
      const toastTitle = document.getElementById('toast-title');
      const toastMessage = document.getElementById('toast-message');
      
      if (type === 'success') {
        toast.classList.remove('border-red-200');
        toast.classList.add('border-green-200');
        toastIconContainer.classList.remove('bg-red-100');
        toastIconContainer.classList.add('bg-green-100');
        toastIcon.classList.remove('text-red-600');
        toastIcon.classList.add('text-green-600');
        toastIcon.textContent = 'check_circle';
        toastTitle.classList.remove('text-red-600');
        toastTitle.classList.add('text-green-600');
      } else {
        toast.classList.remove('border-green-200');
        toast.classList.add('border-red-200');
        toastIconContainer.classList.remove('bg-green-100');
        toastIconContainer.classList.add('bg-red-100');
        toastIcon.classList.remove('text-green-600');
        toastIcon.classList.add('text-red-600');
        toastIcon.textContent = 'error';
        toastTitle.classList.remove('text-green-600');
        toastTitle.classList.add('text-red-600');
      }
      
      toastTitle.textContent = title;
      toastMessage.textContent = message;
      
      toast.classList.remove('hidden');
      setTimeout(() => {
        toast.classList.remove('translate-x-[400px]');
      }, 100);
      
      setTimeout(() => {
        toast.classList.add('translate-x-[400px]');
        setTimeout(() => {
          toast.classList.add('hidden');
        }, 500);
      }, 3000);
    }

    // HTML5 Drag and Drop Handlers
    function allowDrop(ev) {
      ev.preventDefault();
    }

    function dragEnter(ev) {
      const column = ev.target.closest('.pipeline-column');
      if (column) {
        column.classList.add('drag-over');
      }
    }

    function dragLeave(ev) {
      const column = ev.target.closest('.pipeline-column');
      if (column) {
        column.classList.remove('drag-over');
      }
    }

    function drag(ev) {
      ev.dataTransfer.setData("text", ev.target.id);
      ev.dataTransfer.setData("appId", ev.target.dataset.appId);
      ev.dataTransfer.setData("education", ev.target.dataset.education);
    }

    async function drop(ev) {
      ev.preventDefault();
      
      // Clean up column styling
      document.querySelectorAll('.pipeline-column').forEach(col => {
        col.classList.remove('drag-over');
      });

      const cardId = ev.dataTransfer.getData("text");
      const appId = ev.dataTransfer.getData("appId");
      const education = ev.dataTransfer.getData("education");
      
      const column = ev.target.closest('.pipeline-column');
      if (!column) return;
      
      const newStatus = column.dataset.status;
      if (!newStatus || !appId) return;

      // HOD Approved validation rule
      if (newStatus === 'HOD Approved' && education !== 'Pursuing') {
        showToast('error', 'Invalid Transition', 'HOD Approved is only applicable for Pursuing students.');
        return;
      }

      if (!confirm(`Are you sure you want to transition candidate status to "${newStatus}"?`)) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('application_id', appId);
        formData.append('new_status', newStatus);
        formData.append('notes', 'Status updated via drag-and-drop on pipeline board.');

        const response = await fetch('update_application_status.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();

        if (result.success) {
          showToast('success', 'Pipeline Updated', result.message);
          setTimeout(() => location.reload(), 1300);
        } else {
          showToast('error', 'Update Failed', result.message);
        }
      } catch (error) {
        showToast('error', 'Error', 'Failed to communicate status change.');
      }
    }
  </script>
</body>
</html>
