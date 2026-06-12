<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch profile details
$sql_profile = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$result_profile = mysqli_query($conn, $sql_profile);
$profile = mysqli_fetch_assoc($result_profile);

if (!$profile) {
    header("Location: student_profile_form.php");
    exit();
}

// Fetch unread notifications count for sidebar
$unread_sql = "SELECT COUNT(*) as count FROM student_notifications WHERE user_id = '$user_id' AND is_read = 0";
$unread_res = mysqli_query($conn, $unread_sql);
$unread_row = mysqli_fetch_assoc($unread_res);
$unread_count = isset($unread_row['count']) ? $unread_row['count'] : 0;

// Fetch active started internship (Started status)
$active_sql = "SELECT a.id as app_id 
               FROM internship_applications a 
               WHERE a.user_id = '$user_id' AND (a.status = 'Started' OR a.status = 'Internship Started' OR a.status = 'Active Intern') 
               LIMIT 1";
$active_result = mysqli_query($conn, $active_sql);
$has_active = mysqli_num_rows($active_result) > 0;

// Fetch email logs matching student email or user ID
$email_logs_sql = "SELECT * FROM email_notifications_log 
                   WHERE user_id = '$user_id' OR recipient_email = '" . mysqli_real_escape_string($conn, $profile['email']) . "' 
                   ORDER BY sent_at DESC";
$email_logs_result = mysqli_query($conn, $email_logs_sql);
$total_emails = mysqli_num_rows($email_logs_result);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Email Sandbox Logs - IMP</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-[#f8f9fa] text-[#191c1d] font-sans antialiased">
  
  <!-- SideNavBar -->
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
    
    <nav class="flex-1 space-y-1.5 px-4 overflow-y-auto">
      <?php if ($has_active): ?>
      <a class="flex items-center gap-3 text-gray-600 rounded-lg px-4 py-3 font-medium transition-all hover:bg-gray-50 hover:text-blue-600" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#active-internship-card">
        <span class="material-symbols-outlined">badge</span>
        <span class="text-sm font-medium">My Internship</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_daily_log.php">
        <span class="material-symbols-outlined">edit_note</span>
        <span class="text-sm font-medium">Daily Logs</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#assigned-project-card">
        <span class="material-symbols-outlined">terminal</span>
        <span class="text-sm font-medium">Project</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php#mentor-feedback-card">
        <span class="material-symbols-outlined">reviews</span>
        <span class="text-sm font-medium">Feedback</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <?php else: ?>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm font-medium">Dashboard</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_browse_internships.php">
        <span class="material-symbols-outlined">work</span>
        <span class="text-sm font-medium">Available Internships</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_applications.php">
        <span class="material-symbols-outlined">assignment</span>
        <span class="text-sm font-medium">My Applications</span>
      </a>
      <a class="flex items-center gap-3 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-50 hover:text-blue-600 transition-all" href="student_notifications.php">
        <span class="material-symbols-outlined">notifications</span>
        <span class="text-sm font-medium">Notifications</span>
        <?php if ($unread_count > 0): ?>
            <span class="ml-auto bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-[10px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
    </nav>
    
    <div class="mt-auto px-4 pt-4 border-t border-gray-100 space-y-1.5">
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

  <!-- Main Canvas -->
  <div class="pl-64 flex flex-col min-h-screen relative">
    
    <!-- TopNavBar -->
    <header class="w-full sticky top-0 z-30 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3">
      <div class="flex items-center gap-4 flex-1">
        <span class="text-xs font-semibold text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg">Email Notifications Sandbox</span>
      </div>
      
      <div class="flex items-center gap-6">
        <a href="student_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative">
          <span class="material-symbols-outlined">notifications</span>
          <?php if ($unread_count > 0): ?>
              <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
          <?php endif; ?>
        </a>
        <div class="h-8 w-[1px] bg-gray-200"></div>
        <div class="flex items-center gap-3 select-none">
          <div class="text-right hidden md:block">
            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($profile['full_name']); ?></p>
            <p class="text-xs text-gray-500">Student Account</p>
          </div>
          <img alt="User profile" class="w-10 h-10 rounded-full border border-gray-200 shadow-sm" src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['full_name']); ?>&background=0D8ABC&color=fff">
        </div>
      </div>
    </header>

    <!-- Main Content Grid -->
    <main class="flex-grow flex p-6 gap-6 h-[calc(100vh-64px)] overflow-hidden">
        
        <!-- Left Pane: Outgoing Emails List -->
        <div class="w-1/2 flex flex-col bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden h-full">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">outbox</span> Outbox Logs
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Showing all emails dispatched to your registered address.</p>
                </div>
                <span class="bg-blue-100 text-blue-800 text-[11px] font-extrabold px-3 py-1 rounded-full"><?php echo $total_emails; ?> Dispatched</span>
            </div>
            
            <div class="flex-grow overflow-y-auto divide-y divide-slate-100">
                <?php if ($total_emails > 0): ?>
                    <?php 
                    $first_email = null;
                    $counter = 0;
                    while($row = mysqli_fetch_assoc($email_logs_result)): 
                        if ($counter === 0) {
                            $first_email = $row;
                        }
                        $counter++;
                        
                        // Select type badge styling
                        $badge_class = 'bg-slate-100 text-slate-700';
                        if (strpos(strtolower($row['subject']), 'welcome') !== false || strpos(strtolower($row['subject']), 'register') !== false) {
                            $badge_class = 'bg-green-100 text-green-800';
                        } elseif (strpos(strtolower($row['subject']), 'applied') !== false || strpos(strtolower($row['subject']), 'application') !== false) {
                            if (strpos(strtolower($row['subject']), 'status') !== false) {
                                $badge_class = 'bg-orange-100 text-orange-800';
                            } else {
                                $badge_class = 'bg-blue-100 text-blue-800';
                            }
                        } elseif (strpos(strtolower($row['subject']), 'assessment') !== false || strpos(strtolower($row['subject']), 'test') !== false) {
                            $badge_class = 'bg-purple-100 text-purple-800';
                        } elseif (strpos(strtolower($row['subject']), 'log') !== false) {
                            $badge_class = 'bg-amber-100 text-amber-800';
                        }
                        
                        // JSON-encode HTML content to pass to Javascript
                        $js_html = json_encode($row['html_body']);
                        $js_subject = json_encode($row['subject']);
                        $js_recipient = json_encode($row['recipient_email']);
                        $js_date = json_encode(date('M d, Y - h:i A', strtotime($row['sent_at'])));
                    ?>
                        <div class="email-list-item p-5 cursor-pointer hover:bg-slate-50/50 transition-colors flex flex-col gap-2 relative group"
                             onclick='selectEmail(<?php echo $js_html; ?>, <?php echo $js_subject; ?>, <?php echo $js_recipient; ?>, <?php echo $js_date; ?>, this)'>
                            
                            <!-- Unread Indicator Bar -->
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-transparent group-hover:bg-blue-500 transition-colors rounded-l-full"></div>
                            
                            <div class="flex items-start justify-between">
                                <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full <?php echo $badge_class; ?>">
                                    <?php 
                                        if (strpos(strtolower($row['subject']), 'welcome') !== false) echo 'Onboarding';
                                        elseif (strpos(strtolower($row['subject']), 'status') !== false) echo 'Status Update';
                                        elseif (strpos(strtolower($row['subject']), 'assessment') !== false || strpos(strtolower($row['subject']), 'test') !== false) echo 'Assessment';
                                        elseif (strpos(strtolower($row['subject']), 'log') !== false) echo 'Daily Log';
                                        elseif (strpos(strtolower($row['subject']), 'applied') !== false) echo 'Apply';
                                        else echo 'System';
                                    ?>
                                </span>
                                <span class="text-[10px] text-slate-400 font-semibold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[12px]">schedule</span> 
                                    <?php echo date('M d, h:i A', strtotime($row['sent_at'])); ?>
                                </span>
                            </div>
                            
                            <h3 class="font-bold text-slate-800 text-sm leading-tight mt-1 truncate"><?php echo htmlspecialchars($row['subject']); ?></h3>
                            <p class="text-xs text-slate-500 leading-relaxed truncate"><?php echo htmlspecialchars($row['message_text']); ?></p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center p-12 text-center space-y-4">
                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300">
                            <span class="material-symbols-outlined text-4xl">inbox</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-700 text-sm">No Emails Sent Yet</h3>
                            <p class="text-xs text-slate-400 max-w-[240px] mt-1 mx-auto">Emails will appear here once you trigger a workflow event (e.g. log daily activity or apply).</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Pane: Real-time Email Preview HTML -->
        <div class="w-1/2 flex flex-col bg-slate-100/50 border border-slate-200/50 rounded-2xl overflow-hidden h-full relative">
            
            <!-- Placeholder state -->
            <div id="email-placeholder" class="absolute inset-0 flex flex-col items-center justify-center p-12 text-center bg-slate-50/50 z-10 transition-opacity duration-300">
                <div class="w-16 h-16 bg-white shadow-sm border border-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4 animate-pulse">
                    <span class="material-symbols-outlined text-3xl">mail_lock</span>
                </div>
                <h3 class="font-bold text-slate-700 text-sm">Preview Email Message</h3>
                <p class="text-xs text-slate-400 max-w-[240px] mt-1 mx-auto">Select any email log from the left listing to display its styled interactive HTML payload.</p>
            </div>
            
            <!-- Main preview window (hidden by default unless chosen) -->
            <div id="email-preview-box" class="hidden h-full flex flex-col bg-white">
                <div class="p-5 border-b border-slate-100 bg-slate-50 flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs text-slate-400 font-bold uppercase tracking-wider">HTML Sandbox Inspector</h3>
                        <span id="email-detail-date" class="text-[10px] text-slate-500 font-bold"></span>
                    </div>
                    <h2 id="email-detail-subject" class="font-black text-slate-800 text-base leading-snug"></h2>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mt-1">
                        <span class="font-semibold text-slate-400">Recipient Email:</span>
                        <span id="email-detail-recipient" class="font-bold text-slate-800"></span>
                    </div>
                </div>
                
                <div class="flex-grow p-4 bg-slate-100 relative">
                    <iframe id="email-preview-iframe" class="w-full h-full border border-slate-200 bg-white rounded-xl shadow-inner" src="about:blank"></iframe>
                </div>
            </div>
        </div>
        
    </main>
  </div>

  <script>
    function selectEmail(htmlContent, subject, recipient, date, element) {
        // Clear active styles
        document.querySelectorAll('.email-list-item').forEach(item => {
            item.classList.remove('bg-blue-50/40');
            item.classList.add('hover:bg-slate-50/50');
            item.querySelector('div').classList.replace('bg-blue-500', 'bg-transparent');
        });

        // Set active style for selected element
        if (element) {
            element.classList.add('bg-blue-50/40');
            element.classList.remove('hover:bg-slate-50/50');
            element.querySelector('div').classList.replace('bg-transparent', 'bg-blue-500');
        }

        // Fill visual preview details
        document.getElementById('email-detail-subject').textContent = subject;
        document.getElementById('email-detail-recipient').textContent = recipient;
        document.getElementById('email-detail-date').textContent = date;
        
        // Show iframe preview and hide placeholder
        document.getElementById('email-placeholder').classList.add('hidden');
        document.getElementById('email-preview-box').classList.remove('hidden');

        // Render HTML inside sandboxed iframe safely
        document.getElementById('email-preview-iframe').srcdoc = htmlContent;
    }

    // Auto-load first email if present
    document.addEventListener('DOMContentLoaded', () => {
        const firstItem = document.querySelector('.email-list-item');
        if (firstItem) {
            firstItem.click();
        }
    });
  </script>
</body>
</html>
