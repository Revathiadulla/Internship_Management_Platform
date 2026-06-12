    <section id="sec-dashboard" class="dashboard-section active">
      <?php if ($has_active):
        $progress_pct_d = min(100, round(($days_active / 90) * 100));
        $end_date_d = new DateTime($active_intern['applied_date']);
        $end_date_d->modify('+3 months');
        $days_left_d = max(0, (new DateTime())->diff($end_date_d)->days);
      ?>
      <!-- ── INTERN WORKSPACE ── -->

      <!-- Welcome Banner -->
      <div class="bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 rounded-2xl p-6 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-lg">
        <div>
          <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1">Intern Workspace 🚀</p>
          <h1 class="text-2xl font-extrabold text-white tracking-tight">Welcome back, <?php echo htmlspecialchars($profile['full_name']); ?>!</h1>
          <p class="text-blue-200 text-sm mt-1">Stay consistent, log daily, and grow every day.</p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <div class="flex items-center gap-2 bg-white/20 backdrop-blur-sm px-4 py-2 rounded-xl border border-white/30">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span class="text-white text-sm font-bold">Active Intern</span>
          </div>
        </div>
      </div>

      <!-- Row 1: Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">badge</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Status</p>
            <p class="text-sm font-extrabold text-emerald-600">Active</p>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">layers</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Phase</p>
            <p class="text-sm font-extrabold text-slate-800"><?php echo $phases[$current_phase_num]['short']; ?></p>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">schedule</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">This Week</p>
            <p class="text-sm font-extrabold text-slate-800"><?php echo number_format($weekly_hours,1); ?> hrs</p>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">notifications</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Alerts</p>
            <p class="text-sm font-extrabold text-slate-800"><?php echo $unread_count; ?></p>
          </div>
        </div>
      </div>

      <!-- Row 2: Internship Card + Project Card -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">

        <!-- Active Internship Card (4 cols) -->
        <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-5">
          <div class="flex items-center justify-between">
            <div class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
              <span class="material-symbols-outlined text-[24px]">badge</span>
            </div>
            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-extrabold rounded-full uppercase tracking-wider border border-emerald-100">Started</span>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Internship</p>
            <h3 class="text-base font-extrabold text-slate-800 mt-0.5 leading-snug"><?php echo htmlspecialchars($active_intern['title']); ?></h3>
            <p class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($active_intern['company_name']); ?></p>
          </div>
          <div class="grid grid-cols-2 gap-3 text-xs border-t border-slate-50 pt-4">
            <div>
              <p class="text-slate-400 font-semibold">Start Date</p>
              <p class="font-bold text-slate-700 mt-0.5"><?php echo date('M d, Y', strtotime($active_intern['applied_date'])); ?></p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold">End Date</p>
              <p class="font-bold text-slate-700 mt-0.5"><?php echo $end_date_d->format('M d, Y'); ?></p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold">Duration</p>
              <p class="font-bold text-slate-700 mt-0.5"><?php echo !empty($active_intern['duration']) ? htmlspecialchars($active_intern['duration']) : '3 Months'; ?></p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold">Mode</p>
              <p class="font-bold text-slate-700 mt-0.5 capitalize"><?php echo !empty($active_intern['mode']) ? htmlspecialchars($active_intern['mode']) : 'Remote'; ?></p>
            </div>
          </div>
          <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-2.5 flex items-center justify-between text-xs">
            <span class="text-blue-500 font-semibold">Current Phase</span>
            <span class="font-extrabold text-blue-800"><?php echo htmlspecialchars($phases[$current_phase_num]['label']); ?></span>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1.5">
              <span class="text-slate-400 font-semibold">Progress</span>
              <span class="font-bold text-slate-700"><?php echo $progress_pct_d; ?>% · <?php echo $days_left_d; ?>d left</span>
            </div>
            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
              <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full" style="width:<?php echo $progress_pct_d; ?>%"></div>
            </div>
          </div>
        </div>

        <!-- Assigned Project Card (8 cols) -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-5">
          <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[24px]">terminal</span>
              </div>
              <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assigned Project</p>
                <h3 class="text-base font-extrabold text-slate-800 mt-0.5"><?php echo htmlspecialchars($active_intern['project_name']); ?></h3>
              </div>
            </div>
            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 text-[10px] font-extrabold rounded-full uppercase border border-indigo-100 shrink-0">In Progress</span>
          </div>
          <p class="text-sm text-slate-500 leading-relaxed"><?php echo htmlspecialchars($active_intern['project_desc']); ?></p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($active_intern['project_stack'] as $tech): ?>
            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg"><?php echo htmlspecialchars($tech); ?></span>
            <?php endforeach; ?>
          </div>
          <div class="pt-4 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs items-center">
            <div class="flex items-center gap-2">
              <img alt="Mentor" class="w-8 h-8 rounded-full border" src="https://ui-avatars.com/api/?name=Sarah+Jenkins&background=6366F1&color=fff">
              <div>
                <span class="text-[9px] text-slate-400 block font-semibold uppercase">Mentor</span>
                <span class="font-bold text-slate-800">Dr. Sarah Jenkins</span>
              </div>
            </div>
            <div>
              <span class="text-[9px] text-slate-400 block font-semibold uppercase">Deadline</span>
              <span class="font-bold text-red-600">In <?php echo $days_left_d; ?> Days</span>
            </div>
            <div class="text-right">
              <button data-section="sec-project" class="nav-item text-blue-600 font-bold hover:underline text-xs">View Project →</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Row 3: Phase Tracker -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
        <div class="flex items-center justify-between border-b border-slate-50 pb-4 mb-6">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">account_tree</span>
            </div>
            <div>
              <h3 class="font-bold text-slate-800 text-sm">Internship Roadmap &amp; Phase Tracker</h3>
              <p class="text-xs text-slate-400">Track milestones and advance through each stage</p>
            </div>
          </div>
          <span class="px-2.5 py-1 bg-blue-50 text-blue-700 text-[10px] font-extrabold rounded">Stage <?php echo $current_phase_num; ?> of 6</span>
        </div>
        <div class="relative py-4">
          <div class="absolute top-1/2 left-4 right-4 h-1 bg-slate-100 -translate-y-1/2 z-0"></div>
          <?php $pct_line = round((($current_phase_num - 1) / 5) * 100); ?>
          <div class="absolute top-1/2 left-4 h-1 bg-gradient-to-r from-blue-500 to-indigo-600 -translate-y-1/2 z-0" style="width:calc(<?php echo $pct_line; ?>% - 32px)"></div>
          <div class="relative z-10 grid grid-cols-6 gap-2 text-center text-xs">
            <?php foreach ($phases as $pnum => $phase):
              $is_done    = $pnum < $current_phase_num;
              $is_current = $pnum === $current_phase_num;
              $node_cls   = $is_done ? 'bg-emerald-500 text-white' : ($is_current ? 'bg-blue-600 text-white ring-4 ring-blue-100 animate-pulse' : 'bg-slate-200 text-slate-500 opacity-50');
              $lbl_cls    = $is_done ? 'text-emerald-600 font-bold' : ($is_current ? 'text-blue-600 font-bold' : 'text-slate-400');
              $sub_cls    = $is_done ? 'text-emerald-500' : ($is_current ? 'text-blue-500' : 'text-slate-400');
            ?>
            <div class="space-y-2">
              <div class="w-10 h-10 rounded-full <?php echo $node_cls; ?> mx-auto flex items-center justify-center shadow-md">
                <span class="material-symbols-outlined text-[18px]"><?php echo $is_done ? 'check' : $phase['icon']; ?></span>
              </div>
              <div>
                <span class="<?php echo $lbl_cls; ?> block text-[10px]"><?php echo $phase['short']; ?></span>
                <span class="<?php echo $sub_cls; ?> text-[9px] uppercase tracking-wide block"><?php echo $is_done ? 'Done' : ($is_current ? 'Active' : 'Pending'); ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Row 4: Recent Activity Logbook + Progress Overview -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">

        <!-- Recent Logbook (8 cols) -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">edit_note</span>
              </div>
              <div>
                <h3 class="font-bold text-slate-800 text-sm">Recent Activity Logbook</h3>
                <p class="text-xs text-slate-400">Your last submitted daily logs</p>
              </div>
            </div>
            <button data-section="sec-daily-logs" class="nav-item text-blue-600 text-xs font-bold hover:underline">View All →</button>
          </div>
          <?php if (count($recent_logs) === 0): ?>
          <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">edit_note</span>
            <p class="text-slate-400 text-sm font-medium">No logs yet.</p>
            <button data-section="sec-daily-logs" class="nav-item mt-3 inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-colors">
              <span class="material-symbols-outlined text-[15px]">add</span> Submit Today's Log
            </button>
          </div>
          <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($recent_logs as $log):
              $focus_cls = match($log['focus_level']) {
                'High'   => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                'Medium' => 'bg-amber-100 text-amber-700 border-amber-200',
                'Low'    => 'bg-red-100 text-red-600 border-red-200',
                default  => 'bg-slate-100 text-slate-600 border-slate-200',
              };
            ?>
            <div class="flex items-start gap-4 p-4 bg-slate-50 rounded-xl border border-slate-100 hover:bg-slate-100 transition-colors">
              <div class="w-10 h-10 bg-white rounded-xl border border-slate-200 flex items-center justify-center shrink-0 shadow-sm">
                <span class="material-symbols-outlined text-amber-500 text-[20px]">task_alt</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2 mb-1">
                  <p class="text-xs font-bold text-slate-500"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></p>
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-600"><?php echo number_format($log['time_spent'], 1); ?>h</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?php echo $focus_cls; ?>"><?php echo htmlspecialchars($log['focus_level']); ?></span>
                  </div>
                </div>
                <p class="text-sm text-slate-700 font-medium leading-snug"><?php echo htmlspecialchars(mb_strimwidth($log['tasks_completed'], 0, 90, '…')); ?></p>
                <?php if (!empty($log['next_plan'])): ?>
                <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                  <span class="material-symbols-outlined text-[13px]">arrow_forward</span>
                  <?php echo htmlspecialchars(mb_strimwidth($log['next_plan'], 0, 70, '…')); ?>
                </p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
            <p class="text-xs text-slate-400">Showing last <?php echo count($recent_logs); ?> log<?php echo count($recent_logs) > 1 ? 's' : ''; ?></p>
            <button data-section="sec-daily-logs" class="nav-item inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
              <span class="material-symbols-outlined text-[15px]">add</span> Log Today
            </button>
          </div>
          <?php endif; ?>
        </div>

        <!-- Progress Overview (4 cols) -->
        <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-5">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">monitoring</span>
            </div>
            <div>
              <h3 class="font-bold text-slate-800 text-sm">Progress Overview</h3>
              <p class="text-xs text-slate-400"><?php echo $days_active; ?> days active</p>
            </div>
          </div>
          <!-- Circular progress -->
          <div class="flex items-center justify-center py-2">
            <div class="relative w-32 h-32">
              <svg class="w-32 h-32 -rotate-90" viewBox="0 0 128 128">
                <circle cx="64" cy="64" r="54" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                <circle cx="64" cy="64" r="54" fill="none" stroke="url(#prog-grad)" stroke-width="12"
                        stroke-dasharray="<?php echo round(2*3.14159*54); ?>"
                        stroke-dashoffset="<?php echo round(2*3.14159*54*(1-$progress_pct_d/100)); ?>"
                        stroke-linecap="round"/>
                <defs>
                  <linearGradient id="prog-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#3b82f6"/>
                    <stop offset="100%" stop-color="#6366f1"/>
                  </linearGradient>
                </defs>
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-2xl font-extrabold text-slate-800"><?php echo $progress_pct_d; ?>%</span>
                <span class="text-[10px] text-slate-400 font-semibold">Complete</span>
              </div>
            </div>
          </div>
          <div class="space-y-3 text-xs">
            <div class="flex justify-between items-center p-2.5 bg-slate-50 rounded-lg">
              <span class="text-slate-500 font-medium">Days Active</span>
              <span class="font-bold text-slate-800"><?php echo $days_active; ?> / 90</span>
            </div>
            <div class="flex justify-between items-center p-2.5 bg-slate-50 rounded-lg">
              <span class="text-slate-500 font-medium">Days Remaining</span>
              <span class="font-bold text-blue-600"><?php echo $days_left_d; ?></span>
            </div>
            <div class="flex justify-between items-center p-2.5 bg-slate-50 rounded-lg">
              <span class="text-slate-500 font-medium">Logs This Week</span>
              <span class="font-bold text-amber-600"><?php echo number_format($weekly_hours,1); ?> hrs</span>
            </div>
            <div class="flex justify-between items-center p-2.5 bg-slate-50 rounded-lg">
              <span class="text-slate-500 font-medium">Current Phase</span>
              <span class="font-bold text-indigo-600"><?php echo $phases[$current_phase_num]['short']; ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Row 5: Mentor Review + Upcoming Deadlines -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Mentor Review -->
        <div id="mentor-feedback-card" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">reviews</span>
              </div>
              <div>
                <h3 class="font-bold text-slate-800 text-sm">Mentor Review</h3>
                <p class="text-xs text-slate-400">Latest feedback from your mentor</p>
              </div>
            </div>
            <button data-section="sec-feedback" class="nav-item text-blue-600 text-xs font-bold hover:underline">View All →</button>
          </div>
          <?php if ($feedback_count === 0): ?>
          <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">rate_review</span>
            <p class="text-slate-500 text-sm font-medium">No feedback yet</p>
            <p class="text-slate-400 text-xs mt-1">Your mentor's review will appear here</p>
          </div>
          <?php else:
            // Show latest feedback
            $latest_fb_res = mysqli_query($conn, "SELECT * FROM mentor_feedback WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 1");
            $latest_fb = mysqli_fetch_assoc($latest_fb_res);
            $fb_rating = intval($latest_fb['rating'] ?? 0);
          ?>
          <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-100">
            <div class="flex items-start justify-between gap-3 mb-3">
              <div>
                <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($latest_fb['feedback_title'] ?? 'Performance Review'); ?></p>
                <p class="text-xs text-slate-500 mt-0.5">by <?php echo htmlspecialchars($latest_fb['given_by'] ?? 'Mentor'); ?> · <?php echo date('M d, Y', strtotime($latest_fb['created_at'])); ?></p>
              </div>
              <?php if ($fb_rating > 0): ?>
              <div class="flex items-center gap-0.5 shrink-0">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                <span class="material-symbols-outlined text-[16px] <?php echo $s <= $fb_rating ? 'text-amber-400' : 'text-slate-300'; ?>"
                      style="font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;">star</span>
                <?php endfor; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php if (!empty($latest_fb['comments'])): ?>
            <p class="text-sm text-slate-600 leading-relaxed"><?php echo htmlspecialchars(mb_strimwidth($latest_fb['comments'], 0, 120, '…')); ?></p>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center gap-2.5 mb-5">
            <div class="w-9 h-9 bg-red-50 text-red-500 rounded-lg flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">event_upcoming</span>
            </div>
            <div>
              <h3 class="font-bold text-slate-800 text-sm">Upcoming Deadlines</h3>
              <p class="text-xs text-slate-400">Key dates to keep in mind</p>
            </div>
          </div>
          <div class="space-y-3">
            <?php
              $today_dt   = new DateTime();
              $deadlines  = [
                [
                  'label'   => 'Internship End Date',
                  'date'    => $end_date_d->format('M d, Y'),
                  'days'    => $days_left_d,
                  'icon'    => 'flag',
                  'urgent'  => $days_left_d <= 14,
                ],
                [
                  'label'   => 'Phase ' . ($current_phase_num + 1 <= 6 ? ($current_phase_num + 1) . ' Start' : 'Completion'),
                  'date'    => 'Based on log count',
                  'days'    => null,
                  'icon'    => 'layers',
                  'urgent'  => false,
                ],
                [
                  'label'   => 'Daily Log Due',
                  'date'    => 'Today · ' . date('M d, Y'),
                  'days'    => 0,
                  'icon'    => 'edit_note',
                  'urgent'  => true,
                ],
              ];
              foreach ($deadlines as $dl):
                $dl_urgent = $dl['urgent'];
                $dl_bg     = $dl_urgent ? 'bg-red-50 border-red-100' : 'bg-slate-50 border-slate-100';
                $dl_icon_c = $dl_urgent ? 'text-red-500' : 'text-slate-400';
                $dl_days_c = $dl_urgent ? 'text-red-600 font-extrabold' : 'text-slate-500 font-semibold';
            ?>
            <div class="flex items-center gap-3 p-3 rounded-xl border <?php echo $dl_bg; ?>">
              <div class="w-9 h-9 rounded-lg <?php echo $dl_urgent ? 'bg-red-100' : 'bg-slate-100'; ?> flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[18px] <?php echo $dl_icon_c; ?>"><?php echo $dl['icon']; ?></span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800"><?php echo $dl['label']; ?></p>
                <p class="text-xs text-slate-400 mt-0.5"><?php echo $dl['date']; ?></p>
              </div>
              <?php if ($dl['days'] !== null): ?>
              <span class="text-xs <?php echo $dl_days_c; ?> shrink-0">
                <?php echo $dl['days'] === 0 ? 'Today' : $dl['days'] . 'd'; ?>
              </span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php else: ?>
      <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Dashboard</h2>
        <p class="text-sm text-slate-400 mt-0.5">Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>! Start your internship journey.</p>
      </div>

      <?php if ($is_selected && $selected_app): ?>
      <!-- Selection Confirmation Banner -->
      <div class="bg-gradient-to-r from-emerald-500 to-emerald-700 rounded-2xl p-6 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-lg">
        <div>
          <h3 class="text-xl font-extrabold text-white tracking-tight">Congratulations! You have been selected.</h3>
          <p class="text-emerald-100 text-sm mt-1">Project assignment, team formation, and mentor allocation are pending from the coordinator. Please wait for further instructions.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 shrink-0">
          <?php if (!empty($selected_app['confirmation_letter_path'])): ?>
          <?php echo renderViewButton($selected_app['confirmation_letter_path'], 'inline-flex items-center gap-2 bg-white text-emerald-700 font-bold text-sm px-4 py-2.5 rounded-xl hover:bg-emerald-50 transition-colors shadow-sm', '<span class="material-symbols-outlined text-[18px]">visibility</span> View Letter'); ?>
          <?php echo renderDownloadButton($selected_app['confirmation_letter_path'], 'inline-flex items-center gap-2 bg-white/20 text-white font-bold text-sm px-4 py-2.5 rounded-xl hover:bg-white/30 transition-colors shadow-sm border border-white/20', '<span class="material-symbols-outlined text-[18px]">download</span> Download'); ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <!-- Stats row -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">badge</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Status</p>
            <p class="text-sm font-extrabold text-slate-400">Not Started</p>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">assignment</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Applications</p>
            <p class="text-sm font-extrabold text-slate-800"><?php echo $app_count; ?></p>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">verified</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Shortlisted</p>
            <p class="text-sm font-extrabold text-slate-800"><?php echo $shortlist_count; ?></p>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">notifications</span>
          </div>
          <div>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Alerts</p>
            <p class="text-sm font-extrabold text-slate-800"><?php echo $unread_count; ?></p>
          </div>
        </div>
      </div>

      <!-- CTA + Recent Applications -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- CTA Card -->
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-6 text-white flex flex-col gap-4 shadow-lg">
          <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[26px]">rocket_launch</span>
          </div>
          <div>
            <h3 class="text-lg font-extrabold">Start Your Journey</h3>
            <p class="text-blue-200 text-sm mt-1 leading-relaxed">Browse available internships and apply to kickstart your career.</p>
          </div>
          <a href="student_browse_internships.php" class="inline-flex items-center gap-2 bg-white text-blue-700 font-bold text-sm px-4 py-2.5 rounded-xl hover:bg-blue-50 transition-colors shadow-sm w-fit">
            <span class="material-symbols-outlined text-[18px]">search</span> Browse Internships
          </a>
        </div>

        <!-- Recent Applications Table -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-800 text-sm">Recent Applications</h3>
            <a href="student_applications.php" class="text-blue-600 text-xs font-bold hover:underline">View All →</a>
          </div>
          <?php
            mysqli_data_seek($app_result, 0);
            $app_rows_d = [];
            while ($r = mysqli_fetch_assoc($app_result)) $app_rows_d[] = $r;
          ?>
          <?php if (count($app_rows_d) === 0): ?>
          <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">assignment_late</span>
            <p class="text-slate-400 text-sm">No applications yet.</p>
          </div>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100">
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Title</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                  <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Date</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach (array_slice($app_rows_d, 0, 5) as $app):
                  $st = $app['status'];
                  $badge = match(true) {
                    in_array($st, ['Started','Active Intern','Internship Started','Selected']) => 'bg-emerald-100 text-emerald-700',
                    in_array($st, ['Approved','Accepted','Shortlisted','HOD Approved'])        => 'bg-blue-100 text-blue-700',
                    in_array($st, ['Rejected','Declined'])                                     => 'bg-red-100 text-red-600',
                    in_array($st, ['HR Round','Test Completed'])                               => 'bg-indigo-100 text-indigo-700',
                    default                                                                     => 'bg-slate-100 text-slate-600',
                  };
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="py-3 px-3 font-medium text-slate-800 max-w-[180px] truncate"><?php echo htmlspecialchars($app['title']); ?></td>
                  <td class="py-3 px-3"><span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                  <td class="py-3 px-3 text-slate-400 text-xs"><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </section>
