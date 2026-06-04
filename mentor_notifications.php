<?php
session_start();
include "db.php";
include_once __DIR__ . '/includes/auth.php';
require_role('mentor');
include_once __DIR__ . '/includes/hr_module_helpers.php';
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

page_shell_start('notifications', 'Notifications', 'Stay updated on intern submissions, assignments, and milestones.');
?>
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Send Notification Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mt-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Send Notification to Students</h2>
        <form method="post" action="mentor_send_notification.php" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Team</label>
                <select name="team_id" id="notif-team-id" required class="w-full border border-slate-200 rounded p-2" onchange="updateNotifStudents(this.value)">
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
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Student</label>
                <select name="student_id" id="notif-student-id" required class="w-full border border-slate-200 rounded p-2">
                    <option value="all">All Students in Team</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Subject</label>
                <input type="text" name="subject" required class="w-full border border-slate-200 rounded p-2" placeholder="Enter message subject" />
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Message</label>
                <textarea name="message" rows="4" required class="w-full border border-slate-200 rounded p-2" placeholder="Type your message here..."></textarea>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Send Notification</button>
            </div>
        </form>
    </div>

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
                <a href="mark_notification_read.php?action=read_redirect&id=<?php echo $row['id']; ?>&fallback=mentor_notifications.php" class="notification-card block bg-white rounded-2xl border <?php echo $is_read ? 'border-slate-100' : 'border-blue-100 shadow-sm'; ?> p-5 transition-all flex items-start gap-4 hover:border-blue-300 cursor-pointer"
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
                        <span class="text-[10px] text-slate-400 font-medium flex items-center gap-1 mt-3">
                            <span class="material-symbols-outlined text-[12px]">schedule</span> 
                            <?php echo date('M d, Y - h:i A', strtotime($row['created_at'])); ?>
                        </span>
                    </div>

                    <!-- Action Button -->
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

<script>
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
                    const response = await fetch(`mark_notification_read.php?id=${notifId}`);
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

        // Mark All Notifications as Read
        const markAllButton = document.getElementById("btn-mark-all-read");
        if (markAllButton) {
            markAllButton.addEventListener("click", async () => {
                try {
                    const response = await fetch("mark_notification_read.php?all=1");
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

        // Help count badge decrease locally
        function updateBadgeCounts(allRead = false) {
            const pillCount = document.getElementById("unread-pill-count");
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
    });
</script>
<script>
const teamsData = <?= json_encode($teams_data) ?>;
function updateNotifStudents(teamId) {
    const studentSelect = document.getElementById('notif-student-id');
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
