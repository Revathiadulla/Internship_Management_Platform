<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth.php';
require_role('mentor');
include_once __DIR__ . '/../includes/hr_module_helpers.php';
ensure_module_schema($conn);

$mentor_id = current_user_id();

// Fetch unread notifications count
$unread_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$mentor_id' AND is_read = 0";
$unread_res = mysqli_query($conn, $unread_sql);
$unread_row = mysqli_fetch_assoc($unread_res);
$unread_count = isset($unread_row['count']) ? $unread_row['count'] : 0;

// Fetch all notifications for display
$notifications_sql = "SELECT * FROM notifications WHERE user_id = '$mentor_id' ORDER BY created_at DESC";
$notifications_result = mysqli_query($conn, $notifications_sql);
$total_notifications = mysqli_num_rows($notifications_result);

// Ensure link column exists
$check_link_col = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'link'");
if ($check_link_col && mysqli_num_rows($check_link_col) === 0) {
    mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN link VARCHAR(255) DEFAULT NULL");
}

// Fetch active assigned teams and students for notification form
$teams_data = [];
$teams_res = mysqli_query($conn, "SELECT id, team_name FROM project_teams WHERE mentor_id = $mentor_id AND status = 'Active'");
$assigned_teams_count = mysqli_num_rows($teams_res);
while ($t = mysqli_fetch_assoc($teams_res)) {
    $t_id = intval($t['id']);
    $teams_data[$t_id] = [
        'name' => $t['team_name'],
        'students' => []
    ];
    $st_res = mysqli_query($conn, "SELECT u.id, u.full_name FROM project_team_members ptm JOIN users u ON ptm.student_id = u.id WHERE ptm.project_team_id = $t_id");
    while ($s = mysqli_fetch_assoc($st_res)) {
        $teams_data[$t_id]['students'][] = [
            'id' => intval($s['id']),
            'name' => $s['full_name']
        ];
    }
}

$action_html = '<button onclick="openSendNotifModal()" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm cursor-pointer"><span class="material-symbols-outlined text-[20px]">add</span> Send Notification</button>';
page_shell_start('notifications', 'Notifications', 'Stay updated on intern submissions, assignments, and milestones.', $action_html);
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between border-b border-slate-100 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span id="unread-pill-count" class="bg-red-500 text-white text-xs font-extrabold px-2.5 py-0.5 rounded-full shadow-sm"><?php echo $unread_count; ?> New</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($unread_count > 0): ?>
                <button id="btn-mark-all-read" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">done_all</span> Mark All as Read
                </button>
            <?php endif; ?>
            <?php if ($total_notifications > 0): ?>
                <button id="btn-clear-all" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">delete_sweep</span> Clear All
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Categories -->
    <div class="flex flex-wrap gap-2">
        <button data-filter="all" class="filter-pill px-4 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-full transition-all shadow-sm">All</button>
        <button data-filter="unread" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Unread</button>
        <button data-filter="log_submission" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Log Submissions</button>
        <button data-filter="intern_assignment" class="filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all">Assignments</button>
    </div>

    <!-- Notification List -->
    <div id="notifications-container" class="space-y-4">
        <?php if ($total_notifications > 0): ?>
            <?php mysqli_data_seek($notifications_result, 0); ?>
            <?php while($row = mysqli_fetch_assoc($notifications_result)): 
                $type = $row['type'];
                $is_read = $row['is_read'];
                
                // Style configurations based on type
                $icon = 'notifications';
                $bg_class = 'bg-blue-50 text-blue-600';
                if (strtolower($type) == 'log_submission' || strtolower($type) == 'log_resubmission') {
                    $icon = 'assignment_turned_in';
                    $bg_class = 'bg-purple-50 text-purple-600';
                } elseif (strtolower($type) == 'intern_assignment') {
                    $icon = 'person_add';
                    $bg_class = 'bg-green-50 text-green-600';
                }
            ?>
                <?php $notif_link = !empty($row['link']) ? $row['link'] : ''; ?>
                <?php if ($notif_link): ?>
                <a href="../mark_notification_read.php?action=read_redirect&id=<?php echo $row['id']; ?>&fallback=notifications.php&source=global" class="notification-card block bg-white rounded-2xl border <?php echo $is_read ? 'border-slate-100' : 'border-blue-100 shadow-sm'; ?> p-5 transition-all flex items-start gap-4 hover:border-blue-300 cursor-pointer"
                     data-id="<?php echo $row['id']; ?>"
                     data-type="<?php echo htmlspecialchars(strtolower($type)); ?>"
                     data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                <?php else: ?>
                <div class="notification-card bg-white rounded-2xl border <?php echo $is_read ? 'border-slate-100' : 'border-blue-100 shadow-sm'; ?> p-5 transition-all flex items-start gap-4"
                     data-id="<?php echo $row['id']; ?>"
                     data-type="<?php echo htmlspecialchars(strtolower($type)); ?>"
                     data-read="<?php echo $is_read ? 'true' : 'false'; ?>">
                <?php endif; ?>
                    
                    <!-- Icon -->
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?php echo $bg_class; ?>">
                        <span class="material-symbols-outlined text-[20px]"><?php echo $icon; ?></span>
                    </div>
                    
                    <!-- Main Details -->
                    <div class="flex-grow min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400"><?php echo htmlspecialchars($type); ?> Notification</span>
                            <?php if (!$is_read): ?>
                                <span class="new-dot w-2 h-2 bg-blue-600 rounded-full shrink-0 mt-1 shadow-sm"></span>
                            <?php endif; ?>
                        </div>
                        <p class="font-semibold text-slate-800 text-sm mt-1 leading-relaxed"><?php echo htmlspecialchars($row['message']); ?></p>
                        <?php if (!empty($row['attachment_path'])): ?>
                            <div class="mt-3 flex items-center gap-2 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100 max-w-fit">
                                <span class="material-symbols-outlined text-[16px] text-gray-500">attachment</span>
                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($row['attachment_name']); ?></span>
                                <span class="text-gray-400"> (<?php echo round($row['attachment_size'] / 1024, 1); ?> KB)</span>
                                <span class="text-gray-300">|</span>
                                <a href="<?php echo htmlspecialchars($row['attachment_path']); ?>" target="_blank" class="text-blue-600 font-bold hover:underline">View</a>
                                <a href="<?php echo htmlspecialchars($row['attachment_path']); ?>" download class="text-indigo-600 font-bold hover:underline ml-1">Download</a>
                            </div>
                        <?php endif; ?>
                        <span class="text-[10px] text-slate-400 font-medium flex items-center gap-1 mt-3">
                            <span class="material-symbols-outlined text-[12px]">schedule</span> 
                            <?php echo date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                        </span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 shrink-0 self-center">
                        <?php if (!$is_read): ?>
                            <button class="btn-mark-single-read text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50/50 hover:bg-blue-50 border border-blue-100 rounded-lg px-3 py-1.5 transition-colors shrink-0 cursor-pointer z-10 relative" onclick="event.preventDefault(); event.stopPropagation();">
                                Mark Read
                            </button>
                        <?php endif; ?>
                        <button class="btn-delete-single text-xs font-bold text-red-600 hover:text-red-700 bg-red-50/50 hover:bg-red-50 border border-red-100 rounded-lg p-1.5 transition-colors shrink-0 cursor-pointer z-10 relative" title="Delete Notification" onclick="event.preventDefault(); event.stopPropagation();">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    </div>
                <?php if ($notif_link): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="py-16 text-center bg-white border border-slate-100 rounded-2xl shadow-sm">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-slate-300 text-3xl">notifications_off</span>
                </div>
                <h3 class="font-bold text-slate-700 mb-1">No Notifications Yet</h3>
                <p class="text-slate-500 text-sm">We'll alert you as soon as you receive updates regarding your assigned interns!</p>
            </div>
        <?php endif; ?>
        
        <div id="no-filtered-notifs" class="hidden py-16 text-center bg-white border border-slate-100 rounded-2xl shadow-sm">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-slate-300 text-3xl">filter_list_off</span>
            </div>
            <h3 class="font-bold text-slate-700 mb-1">No Notifications Found</h3>
            <p class="text-slate-500 text-sm font-medium">There are no notifications inside the selected category.</p>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div id="send-notif-modal" class="fixed inset-0 z-50 overflow-y-auto hidden animate-fade-in" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
    <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeSendNotifModal()"></div>
    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">
      <form id="modal-send-notif-form" enctype="multipart/form-data" class="p-6 space-y-4">
        <input type="hidden" name="ajax" value="1">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-lg font-bold text-slate-800" id="modal-title">Send Notification to Students</h3>
          <button type="button" onclick="closeSendNotifModal()" class="text-slate-400 hover:text-slate-600 transition flex items-center justify-center">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Select Team</label>
                <select name="team_id" id="modal-notif-team-id" required class="w-full border border-slate-200 rounded-lg p-2.5 bg-white text-sm outline-none focus:ring-2 focus:ring-blue-500" onchange="updateModalNotifStudents(this.value)">
                    <option value="">Select Team</option>
                    <?php
                    if ($assigned_teams_count > 0) {
                        mysqli_data_seek($teams_res, 0);
                        while ($t = mysqli_fetch_assoc($teams_res)) {
                            echo "<option value='{$t['id']}'>" . htmlspecialchars($t['team_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Select Student</label>
                <select name="student_id" id="modal-notif-student-id" required class="w-full border border-slate-200 rounded-lg p-2.5 bg-white text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="all">All Students in Team</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Subject</label>
                <input type="text" name="subject" required class="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter message subject" />
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Message</label>
                <textarea name="message" rows="4" required class="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500" placeholder="Type your message here..."></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Attachment (Optional)</label>
                <input type="file" name="attachment" class="w-full border border-slate-200 rounded-lg p-2 text-sm outline-none bg-white file:border-0 file:bg-blue-50 file:text-blue-700 file:px-3 file:py-1 file:rounded-md file:text-xs file:font-semibold hover:file:bg-blue-100" />
                <p class="text-xs text-slate-400 mt-1">Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, JPG, JPEG, PNG. Max: 10 MB.</p>
            </div>
        </div>
        
        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
          <button type="button" onclick="closeSendNotifModal()" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition cursor-pointer">Cancel</button>
          <button type="submit" id="modal-submit-btn" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition shadow-sm cursor-pointer">Send Notification</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    window.openSendNotifModal = function() {
        document.getElementById('send-notif-modal').classList.remove('hidden');
    }
    
    window.closeSendNotifModal = function() {
        document.getElementById('send-notif-modal').classList.add('hidden');
        document.getElementById('modal-send-notif-form').reset();
        document.getElementById('modal-notif-student-id').innerHTML = '<option value="all">All Students in Team</option>';
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Filter Pills toggle logic
        const filterPills = document.querySelectorAll(".filter-pill");
        const cards = document.querySelectorAll(".notification-card");
        const noFilteredNotifs = document.getElementById("no-filtered-notifs");

        filterPills.forEach(pill => {
            pill.addEventListener("click", () => {
                // Set active pill design
                filterPills.forEach(p => {
                    p.className = "filter-pill px-4 py-1.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold rounded-full transition-all";
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
                        // Support log_submission grouping
                        if (filter === 'log_submission') {
                            matches = (type === 'log_submission' || type === 'log_resubmission');
                        } else {
                            matches = (type === filter);
                        }
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
            btn.addEventListener("click", async (e) => {
                const card = btn.closest(".notification-card");
                const notifId = card.getAttribute("data-id");

                try {
                    const response = await fetch(`mark_notification_read.php?action=read&id=${notifId}&source=global`);
                    const data = await response.json();

                    if (data.success) {
                        // Soft fade new visual indicators
                        card.setAttribute("data-read", "true");
                        card.className = card.tagName.toLowerCase() === 'a'
                            ? "notification-card block bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4 hover:border-blue-300"
                            : "notification-card bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4";
                        
                        const dot = card.querySelector(".new-dot");
                        if (dot) dot.remove();
                        btn.remove();

                        // Decrease badge counters smoothly
                        updateBadgeCounts();
                    } else {
                        alert("Error: " + data.message);
                    }
                } catch (err) {
                    console.error("AJAX Error: ", err);
                }
            });
        });

        // Delete Single Notification
        const deleteButtons = document.querySelectorAll(".btn-delete-single");
        deleteButtons.forEach(btn => {
            btn.addEventListener("click", async (e) => {
                if (!confirm("Are you sure you want to delete this notification?")) return;
                const card = btn.closest(".notification-card");
                const notifId = card.getAttribute("data-id");
                const isUnread = card.getAttribute("data-read") === "false";

                try {
                    const response = await fetch(`mark_notification_read.php?action=delete&id=${notifId}&source=global`);
                    const data = await response.json();

                    if (data.success) {
                        card.remove();
                        if (isUnread) {
                            updateBadgeCounts();
                        }
                        // If no notifications left, reload or show empty state
                        if (document.querySelectorAll(".notification-card").length === 0) {
                            location.reload();
                        }
                    } else {
                        alert("Error: " + data.message);
                    }
                } catch (err) {
                    console.error("AJAX Error: ", err);
                }
            });
        });

        // Mark All Notifications as Read
        const markAllButton = document.getElementById("btn-mark-all-read");
        if (markAllButton) {
            markAllButton.addEventListener("click", async () => {
                try {
                    const response = await fetch("../mark_notification_read.php?action=read_all&source=global");
                    const data = await response.json();

                    if (data.success) {
                        cards.forEach(card => {
                            card.setAttribute("data-read", "true");
                            card.className = card.tagName.toLowerCase() === 'a'
                                ? "notification-card block bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4 hover:border-blue-300"
                                : "notification-card bg-white rounded-2xl border border-slate-100 p-5 transition-all flex items-start gap-4";
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
        }

        // Clear All Notifications
        const clearAllButton = document.getElementById("btn-clear-all");
        if (clearAllButton) {
            clearAllButton.addEventListener("click", async () => {
                if (!confirm("Are you sure you want to clear all notifications? This cannot be undone.")) return;
                try {
                    const response = await fetch("../mark_notification_read.php?action=delete_all&source=global");
                    const data = await response.json();

                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                    }
                } catch (err) {
                    console.error("AJAX Error: ", err);
                }
            });
        }

        // Help count badge decrease locally
        function updateBadgeCounts(allRead = false) {
            const pillCount = document.getElementById("unread-pill-count");
            if (!pillCount) return;
            if (allRead) {
                pillCount.textContent = "0 New";
            } else {
                let currentCount = parseInt(pillCount.textContent) || 0;
                if (currentCount > 0) {
                    currentCount--;
                }
                pillCount.textContent = `${currentCount} New`;
                if (currentCount === 0 && markAllButton) {
                    markAllButton.remove();
                }
            }
        }
        
        // Modal Form AJAX submission
        const modalForm = document.getElementById('modal-send-notif-form');
        if (modalForm) {
            modalForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitBtn = document.getElementById('modal-submit-btn');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Sending...';
                
                try {
                    const formData = new FormData(modalForm);
                    const response = await fetch('send_notification.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const text = await response.text();
                    let data = {};
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        data = { success: false, message: 'Server returned non-JSON response.' };
                    }
                    
                    if (data.success) {
                        alert(data.message);
                        closeSendNotifModal();
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Send Notification';
                    }
                } catch (err) {
                    console.error("AJAX Error: ", err);
                    alert("An error occurred while sending the notification.");
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Send Notification';
                }
            });
        }
    });
</script>
<script>
const teamsData = <?= json_encode($teams_data) ?>;
function updateModalNotifStudents(teamId) {
    const studentSelect = document.getElementById('modal-notif-student-id');
    studentSelect.innerHTML = '<option value="all">All Students in Team</option>';
    if (teamsData[teamId] && teamsData[teamId].students) {
        teamsData[teamId].students.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            studentSelect.appendChild(opt);
        });
    }
}
</script>
<?php
page_shell_end();
?>
