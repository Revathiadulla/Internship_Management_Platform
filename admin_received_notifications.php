<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
include "db.php";
include "ensure_extended_schema.php";

$user_id = intval($_SESSION['user_id']);

// Fetch unread notifications count for badge
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND role = 'admin' AND is_read = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_res = $unread_stmt->get_result();
$unread_row = $unread_res->fetch_assoc();
$unread_count = $unread_row['count'] ?? 0;
$unread_stmt->close();

// Fetch all notifications for display
$notif_sql = "SELECT * FROM notifications WHERE user_id = ? AND role = 'admin' ORDER BY created_at DESC";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$total_notifications = $notif_result->num_rows;

// Fetch admin details
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Received Alerts – IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#003ea8",
            "primary-hover": "#002a75",
            surface: "#f8f9fa",
            "surface-container": "#ffffff",
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          }
        }
      }
    }
    </script>
    <style>
      body { background-color: #f8f9fa; color: #191c1d; }
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">
  <!-- Top Nav -->
  <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-8">
      <a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
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
      <div class="hidden md:flex gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest border-l border-gray-200 pl-6">
        Platform Administration
      </div>
    </div>
    
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl shadow-sm">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        <span class="font-semibold text-slate-700">System Online</span>
      </div>

      <!-- Notifications Bell -->
      <a href="admin_received_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative flex items-center justify-center">
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($unread_count > 0): ?>
          <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      
      <!-- Profile Button -->
      <div class="relative">
        <button onclick="document.getElementById('profile-dropdown').classList.toggle('hidden')" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
          <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline">
            <?php echo htmlspecialchars($header_name); ?> (Admin)
          </span>
          <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-400 transition-colors">
            <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
              <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
            <?php else: ?>
              <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=003ea8&color=fff" alt="Profile" class="w-full h-full object-cover">
            <?php endif; ?>
          </div>
          <span class="material-symbols-outlined text-gray-400 text-[18px]">arrow_drop_down</span>
        </button>
        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
          <a href="admin_dashboard.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <span class="material-symbols-outlined text-gray-400 text-[18px]">dashboard</span> Dashboard
          </a>
          <hr class="my-1 border-gray-100">
          <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
      <div class="max-w-4xl mx-auto space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-200 pb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    Received Alerts
                    <span id="unread-badge-title" class="bg-red-500 text-white text-xs font-bold px-2.5 py-0.5 rounded-full"><?php echo $unread_count; ?> New</span>
                </h1>
                <p class="text-gray-500 text-sm mt-1">Review system logs, background job statuses, and messages sent to you.</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($unread_count > 0): ?>
                    <button id="btn-mark-all-read" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">done_all</span> Mark All Read
                    </button>
                <?php endif; ?>
                <button id="btn-clear-read" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">delete_sweep</span> Clear Read
                </button>
            </div>
        </div>

        <!-- Categories Pills -->
        <div class="flex flex-wrap gap-2">
            <button data-filter="all" class="filter-pill px-4 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-full transition-all shadow-sm">All</button>
            <button data-filter="unread" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Unread</button>
            <button data-filter="info" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Info</button>
            <button data-filter="success" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Success</button>
            <button data-filter="alert" class="filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all">Alerts</button>
        </div>

        <!-- Notifications List -->
        <div id="notifications-container" class="space-y-4">
            <?php if ($total_notifications > 0): ?>
                <?php while ($row = $notif_result->fetch_assoc()): 
                    $type = $row['type'];
                    $is_read = $row['is_read'];
                    
                    // Style configs
                    $icon = 'notifications';
                    $bg_class = 'bg-blue-50 text-blue-600';
                    if (strtolower($type) == 'success') {
                        $icon = 'check_circle';
                        $bg_class = 'bg-green-50 text-green-600';
                    } elseif (strtolower($type) == 'alert' || strtolower($type) == 'error') {
                        $icon = 'warning';
                        $bg_class = 'bg-red-50 text-red-600';
                    }
                ?>
                    <?php $notif_link = !empty($row['link']) ? $row['link'] : ''; ?>
                    <?php if ($notif_link): ?>
                    <a href="mark_notification_read.php?action=read_redirect&id=<?php echo $row['id']; ?>&fallback=admin_received_notifications.php" class="notification-card block bg-white rounded-2xl border <?php echo $is_read ? 'border-gray-200' : 'border-blue-200 shadow-sm'; ?> p-5 transition-all flex items-start gap-4 hover:border-blue-400 cursor-pointer"
                         data-id="<?php echo $row['id']; ?>"
                         data-type="<?php echo htmlspecialchars(strtolower($type)); ?>"
                         data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                    <?php else: ?>
                    <div class="notification-card bg-white rounded-2xl border <?php echo $is_read ? 'border-gray-200' : 'border-blue-200 shadow-sm'; ?> p-5 transition-all flex items-start gap-4"
                         data-id="<?php echo $row['id']; ?>"
                         data-type="<?php echo htmlspecialchars(strtolower($type)); ?>"
                         data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                    <?php endif; ?>
                        
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo $bg_class; ?>">
                            <span class="material-symbols-outlined text-[20px]"><?php echo $icon; ?></span>
                        </div>
                        
                        <div class="flex-grow min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400"><?php echo htmlspecialchars($row['title']); ?></span>
                                <?php if (!$is_read): ?>
                                    <span class="new-dot w-2 h-2 bg-blue-600 rounded-full shrink-0 mt-1"></span>
                                <?php endif; ?>
                            </div>
                            <p class="font-semibold text-gray-800 text-sm mt-1 leading-relaxed"><?php echo htmlspecialchars($row['message']); ?></p>
                            <span class="text-[10px] text-gray-400 font-medium flex items-center gap-1 mt-3">
                                <span class="material-symbols-outlined text-[12px]">schedule</span> 
                                <?php echo date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                            </span>
                        </div>

                        <?php if (!$is_read): ?>
                            <button class="btn-mark-single-read self-center text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50/50 hover:bg-blue-50 border border-blue-100 rounded-lg px-3 py-1.5 transition-colors shrink-0 cursor-pointer z-10 relative" onclick="event.preventDefault(); event.stopPropagation();">
                                Mark Read
                            </button>
                        <?php endif; ?>
                    <?php if ($notif_link): ?>
                    </a>
                    <?php else: ?>
                    </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="py-16 text-center bg-white border border-gray-200 rounded-2xl shadow-sm">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-gray-300 text-3xl">notifications_off</span>
                    </div>
                    <h3 class="font-bold text-gray-700 mb-1">No Notifications Yet</h3>
                    <p class="text-gray-500 text-sm">We'll alert you as soon as submissions or reviews happen!</p>
                </div>
            <?php endif; ?>

            <div id="no-filtered-notifs" class="hidden py-16 text-center bg-white border border-gray-200 rounded-2xl shadow-sm">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-gray-300 text-3xl">filter_list_off</span>
                </div>
                <h3 class="font-bold text-gray-700 mb-1">No Notifications Found</h3>
                <p class="text-gray-500 text-sm">There are no notifications inside the selected category.</p>
            </div>
        </div>

      </div>
    </main>
  </div>

  <script>
      document.addEventListener('DOMContentLoaded', () => {
          // Categories Filter Logic
          const filterPills = document.querySelectorAll(".filter-pill");
          const cards = document.querySelectorAll(".notification-card");
          const noFilteredNotifs = document.getElementById("no-filtered-notifs");

          filterPills.forEach(pill => {
              pill.addEventListener("click", () => {
                  filterPills.forEach(p => {
                      p.className = "filter-pill px-4 py-1.5 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-bold rounded-full transition-all";
                  });
                  pill.className = "filter-pill px-4 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-full transition-all shadow-sm";

                  const filter = pill.getAttribute("data-filter");
                  let hasMatches = false;

                  cards.forEach(card => {
                      const type = card.getAttribute("data-type") || "";
                      const read = card.getAttribute("data-read") || "";

                      let matches = false;
                      if (filter === "all") {
                          matches = true;
                      } else if (filter === "unread") {
                          matches = (read === "false");
                      } else {
                          matches = (type === filter);
                      }

                      if (matches) {
                          card.style.display = "flex";
                          hasMatches = true;
                      } else {
                          card.style.display = "none";
                      }
                  });

                  if (hasMatches) {
                      if (noFilteredNotifs) noFilteredNotifs.classList.add("hidden");
                  } else {
                      if (noFilteredNotifs) noFilteredNotifs.classList.remove("hidden");
                  }
              });
          });

          // Mark Single Notification as Read
          const singleReadButtons = document.querySelectorAll(".btn-mark-single-read");
          singleReadButtons.forEach(btn => {
              btn.addEventListener("click", async () => {
                  const card = btn.closest(".notification-card");
                  const notifId = card.getAttribute("data-id");

                  try {
                      const response = await fetch(`mark_notification_read.php?action=read&id=${notifId}`);
                      const data = await response.json();

                      if (data.success) {
                          card.setAttribute("data-read", "true");
                          card.className = card.tagName.toLowerCase() === 'a'
                              ? "notification-card block bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4 hover:border-blue-400"
                              : "notification-card bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4";
                          const dot = card.querySelector(".new-dot");
                          if (dot) dot.remove();
                          btn.remove();
                          updateBadgeCounts();
                      } else {
                          alert("Error: " + data.message);
                      }
                  } catch (err) {
                      console.error("AJAX Error: ", err);
                  }
              });
          });

          // Mark All as Read
          const markAllButton = document.getElementById("btn-mark-all-read");
          if (markAllButton) {
              markAllButton.addEventListener("click", async () => {
                  try {
                      const response = await fetch("mark_notification_read.php?action=read_all");
                      const data = await response.json();

                      if (data.success) {
                          cards.forEach(card => {
                              card.setAttribute("data-read", "true");
                              card.className = card.tagName.toLowerCase() === 'a'
                                  ? "notification-card block bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4 hover:border-blue-400"
                                  : "notification-card bg-white rounded-2xl border border-gray-200 p-5 transition-all flex items-start gap-4";
                              const dot = card.querySelector(".new-dot");
                              if (dot) dot.remove();
                              const btn = card.querySelector(".btn-mark-single-read");
                              if (btn) btn.remove();
                          });
                          markAllButton.remove();
                          updateBadgeCounts(true);
                      } else {
                          alert("Error: " + data.message);
                      }
                  } catch (err) {
                      console.error("AJAX Error: ", err);
                  }
              });
          });

          // Clear All Read
          const clearReadButton = document.getElementById("btn-clear-read");
          if (clearReadButton) {
              clearReadButton.addEventListener("click", async () => {
                  if (!confirm("Are you sure you want to clear all read notifications?")) return;
                  try {
                      const response = await fetch("mark_notification_read.php?action=delete_read");
                      const data = await response.json();

                      if (data.success) {
                          cards.forEach(card => {
                              if (card.getAttribute("data-read") === "true") {
                                  card.remove();
                              }
                          });
                          window.location.reload();
                      } else {
                          alert("Error: " + data.message);
                      }
                  } catch (err) {
                      console.error("AJAX Error: ", err);
                  }
              });
          });

          function updateBadgeCounts(allRead = false) {
              const navBadge = document.querySelector("header a span");
              const titleBadge = document.getElementById("unread-badge-title");

              if (allRead) {
                  if (navBadge) navBadge.remove();
                  if (titleBadge) titleBadge.textContent = "0 New";
              } else {
                  let currentCount = parseInt(titleBadge.textContent) || 0;
                  if (currentCount > 0) {
                      currentCount--;
                  }

                  if (currentCount === 0) {
                      if (navBadge) navBadge.remove();
                      if (titleBadge) titleBadge.textContent = "0 New";
                      if (markAllButton) markAllButton.remove();
                  } else {
                      if (navBadge) navBadge.textContent = currentCount;
                      if (titleBadge) titleBadge.textContent = `${currentCount} New`;
                  }
              }
          }
      });
  </script>
</body>
</html>
