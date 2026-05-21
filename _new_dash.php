    <section id="sec-dashboard" class="dashboard-section active">
      <?php
        $d_end = null; $d_left = 0; $d_pct = 0;
        if ($has_active) {
          $d_end  = new DateTime($active_intern['applied_date']); $d_end->modify('+3 months');
          $d_left = max(0,(new DateTime())->diff($d_end)->days);
          $d_pct  = min(100,round(($days_active/90)*100));
        }
      ?>
      <?php if ($has_active): ?>

      <!-- ══ INTERN WORKSPACE BANNER ══ -->
      <div class="bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 rounded-2xl p-6 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-lg">
        <div>
          <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-1">Intern Workspace</p>
          <h1 class="text-2xl font-extrabold text-white tracking-tight">Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>! 🎉</h1>
          <p class="text-blue-200 text-sm mt-1">Your internship has started. Stay consistent, log daily, and grow every day.</p>
        </div>
        <div class="shrink-0">
          <span class="flex items-center gap-2 bg-white/20 border border-white/30 px-4 py-2.5 rounded-xl text-white text-sm font-bold">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
            Status: Active Intern
          </span>
        </div>
      </div>

      <!-- ══ ROW 1: Internship Card + Project Card ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-5">

        <!-- Internship Card -->
        <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4">
          <div class="flex items-center justify-between">
            <div class="w-11 h-11 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
              <span class="material-symbols-outlined text-[24px]">badge</span>
            </div>
            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-extrabold rounded-full uppercase border border-emerald-100">Started</span>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Internship</p>
            <h3 class="text-base font-extrabold text-slate-800 mt-0.5 leading-snug"><?php echo htmlspecialchars($active_intern['title']); ?></h3>
          </div>
          <div class="grid grid-cols-2 gap-3 text-xs border-t border-slate-100 pt-4">
            <div><p class="text-slate-400 font-semibold">Start Date</p><p class="font-bold text-slate-700 mt-0.5"><?php echo date('M d, Y',strtotime($active_intern['applied_date'])); ?></p></div>
            <div><p class="text-slate-400 font-semibold">End Date</p><p class="font-bold text-slate-700 mt-0.5"><?php echo $d_end->format('M d, Y'); ?></p></div>
            <div><p class="text-slate-400 font-semibold">Duration</p><p class="font-bold text-slate-700 mt-0.5"><?php echo !empty($active_intern['duration'])?htmlspecialchars($active_intern['duration']):'3 Months'; ?></p></div>
            <div><p class="text-slate-400 font-semibold">Mode</p><p class="font-bold text-slate-700 mt-0.5 capitalize"><?php echo !empty($active_intern['mode'])?htmlspecialchars($active_intern['mode']):'Hybrid'; ?></p></div>
          </div>
          <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-2.5 flex items-center justify-between text-xs">
            <span class="text-blue-500 font-semibold">Current Phase</span>
            <span class="font-extrabold text-blue-800"><?php echo htmlspecialchars($phases[$current_phase_num]['label']); ?></span>
          </div>
          <div>
            <div class="flex justify-between text-xs mb-1.5">
              <span class="text-slate-400 font-semibold">Overall Progress</span>
              <span class="font-bold text-slate-700"><?php echo $d_pct; ?>%</span>
            </div>
            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
              <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full" style="width:<?php echo $d_pct; ?>%"></div>
            </div>
            <p class="text-[10px] text-slate-400 mt-1"><?php echo $days_active; ?> days active · <?php echo $d_left; ?> days remaining</p>
          </div>
        </div>

        <!-- Project Card -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4">
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
            <?php foreach($active_intern['project_stack'] as $tech): ?>
            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg"><?php echo htmlspecialchars($tech); ?></span>
            <?php endforeach; ?>
          </div>
          <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-2.5 flex items-center gap-2 text-sm">
            <span class="material-symbols-outlined text-blue-500 text-[18px]">layers</span>
            <span class="text-blue-600 font-semibold text-xs">Current Phase:</span>
            <span class="font-extrabold text-blue-800 text-xs"><?php echo htmlspecialchars($phases[$current_phase_num]['label']); ?></span>
          </div>
          <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-4 text-xs">
            <div class="flex items-center gap-2">
              <img src="https://ui-avatars.com/api/?name=Sarah+Jenkins&background=6366F1&color=fff" class="w-8 h-8 rounded-full border" alt="Mentor">
              <div>
                <p class="text-[9px] text-slate-400 font-semibold uppercase">Assigned Mentor</p>
                <p class="font-bold text-slate-700"><?php echo ($feedback_count > 0) ? 'Dr. Sarah Jenkins' : 'Mentor assignment pending'; ?></p>
              </div>
            </div>
            <div>
              <p class="text-[9px] text-slate-400 font-semibold uppercase">Project Deadline</p>
              <p class="font-bold text-red-600">In <?php echo $d_left; ?> Days</p>
            </div>
            <button data-section="sec-project" class="nav-item text-blue-600 font-bold hover:underline">View Project →</button>
          </div>
        </div>
      </div>

      <!-- ══ ROW 2: Phase Tracker ══ -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-5">
        <div class="flex items-center justify-between mb-5">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
              <span class="material-symbols-outlined text-[20px]">account_tree</span>
            </div>
            <div>
              <h3 class="font-bold text-slate-800 text-sm">Internship Phase Tracker</h3>
              <p class="text-xs text-slate-400">Complete each phase to advance your internship progress.</p>
            </div>
          </div>
          <span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs font-extrabold rounded-full border border-blue-100">Phase <?php echo $current_phase_num; ?> of 6</span>
        </div>
        <div class="grid grid-cols-6 gap-3">
          <?php foreach($phases as $pnum => $phase):
            $is_done    = $pnum < $current_phase_num;
            $is_current = $pnum === $current_phase_num;
            $box_cls    = $is_done    ? 'bg-emerald-50 border-emerald-200' : ($is_current ? 'bg-blue-50 border-blue-300 ring-2 ring-blue-100' : 'bg-slate-50 border-slate-200');
            $icon_cls   = $is_done    ? 'bg-emerald-500 text-white' : ($is_current ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-400');
            $lbl_cls    = $is_done    ? 'text-emerald-700 font-bold' : ($is_current ? 'text-blue-700 font-extrabold' : 'text-slate-400');
            $sub_cls    = $is_done    ? 'text-emerald-500' : ($is_current ? 'text-blue-500 font-bold' : 'text-slate-400');
          ?>
          <div class="flex flex-col items-center gap-2 p-3 rounded-xl border <?php echo $box_cls; ?> text-center">
            <div class="w-10 h-10 rounded-full <?php echo $icon_cls; ?> flex items-center justify-center shadow-sm">
              <span class="material-symbols-outlined text-[18px]"><?php echo $is_done ? 'check' : $phase['icon']; ?></span>
            </div>
            <div>
              <p class="text-[10px] font-bold <?php echo $lbl_cls; ?>">P<?php echo $pnum; ?></p>
              <p class="text-[10px] <?php echo $lbl_cls; ?>"><?php echo $phase['short']; ?></p>
              <p class="text-[9px] uppercase tracking-wide <?php echo $sub_cls; ?> mt-0.5"><?php echo $is_done ? 'Done' : ($is_current ? 'Current' : 'Pending'); ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ══ ROW 3: Activity Logbook + Progress/Mentor/Deadlines ══ -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- Activity Logbook (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">edit_note</span>
              </div>
              <div>
                <h3 class="font-bold text-slate-800 text-sm">Recent Activity Logbook</h3>
                <p class="text-xs text-slate-400">Document your daily milestones, technical blocks, and timelines.</p>
              </div>
            </div>
            <button data-section="sec-daily-logs" class="nav-item inline-flex items-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-colors shadow-sm">
              <span class="material-symbols-outlined text-[15px]">add</span> Submit Today's Log
            </button>
          </div>

          <?php if (count($recent_logs) === 0): ?>
          <div class="text-center py-10 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[36px] text-slate-300 block mb-2">edit_note</span>
            <p class="text-slate-400 text-sm font-medium">No logs yet. Start logging your daily work!</p>
          </div>
          <?php else: ?>
          <div class="space-y-4">
            <?php foreach($recent_logs as $log):
              $fc = match($log['focus_level']) {
                'High'   => 'bg-emerald-100 text-emerald-700',
                'Medium' => 'bg-blue-100 text-blue-700',
                'Low'    => 'bg-red-100 text-red-600',
                default  => 'bg-slate-100 text-slate-600',
              };
            ?>
            <div class="border border-slate-100 rounded-xl p-4 hover:bg-slate-50 transition-colors">
              <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-extrabold text-blue-600 uppercase tracking-widest"><?php echo date('M d, Y', strtotime($log['log_date'])); ?></span>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-600"><?php echo number_format($log['time_spent'],1); ?> Hrs spent</span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $fc; ?>"><?php echo htmlspecialchars($log['focus_level']); ?> Progress</span>
                </div>
              </div>
              <div class="space-y-1.5 text-xs">
                <div>
                  <p class="text-slate-400 font-bold uppercase tracking-wide text-[9px]">Tasks Done:</p>
                  <p class="text-slate-700"><?php echo htmlspecialchars(mb_strimwidth($log['tasks_completed'],0,100,'…')); ?></p>
                </div>
                <?php if (!empty($log['issues_faced'])): ?>
                <div>
                  <p class="text-red-400 font-bold uppercase tracking-wide text-[9px]">Blockers:</p>
                  <p class="text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($log['issues_faced'],0,80,'…')); ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($log['next_plan'])): ?>
                <div>
                  <p class="text-blue-400 font-bold uppercase tracking-wide text-[9px]">Roadmap Next:</p>
                  <p class="text-slate-600"><?php echo htmlspecialchars(mb_strimwidth($log['next_plan'],0,80,'…')); ?></p>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Right column: Progress + Mentor + Deadlines (5 cols) -->
        <div class="lg:col-span-5 flex flex-col gap-5">

          <!-- Progress Overview -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-emerald-500 text-[20px]">monitoring</span>
              <h4 class="font-bold text-slate-800 text-sm">Progress Overview</h4>
            </div>
            <div class="space-y-3 text-sm">
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-slate-500">Overall Progress</span>
                  <span class="font-bold text-slate-700"><?php echo $d_pct; ?>%</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                  <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-full rounded-full" style="width:<?php echo $d_pct; ?>%"></div>
                </div>
              </div>
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-slate-500">Weekly Hours</span>
                  <span class="font-bold text-emerald-600"><?php echo number_format($weekly_hours,1); ?>h logged</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                  <div class="bg-emerald-400 h-full rounded-full" style="width:<?php echo min(100,round($weekly_hours/40*100)); ?>%"></div>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                <div class="text-center p-3 bg-slate-50 rounded-xl">
                  <p class="text-lg font-extrabold text-slate-800"><?php echo count($recent_logs); ?></p>
                  <p class="text-[10px] text-slate-400 font-semibold uppercase">Logs Filed</p>
                </div>
                <div class="text-center p-3 bg-slate-50 rounded-xl">
                  <p class="text-lg font-extrabold text-slate-800"><?php echo $days_active; ?></p>
                  <p class="text-[10px] text-slate-400 font-semibold uppercase">Days Active</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Mentor Review -->
          <div id="mentor-feedback-card" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-500 text-[20px]">rate_review</span>
                <h4 class="font-bold text-slate-800 text-sm">Mentor Review</h4>
              </div>
              <?php if ($feedback_count > 0): ?>
              <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full">Approved</span>
              <?php endif; ?>
            </div>
            <?php if ($feedback_count === 0): ?>
            <div class="bg-slate-50 rounded-xl p-4 text-center border border-dashed border-slate-200">
              <p class="text-slate-400 text-xs">No mentor feedback yet.</p>
            </div>
            <?php else:
              $lf = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM mentor_feedback WHERE user_id='$user_id' ORDER BY created_at DESC LIMIT 1"));
            ?>
            <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
              <p class="text-sm text-slate-700 italic leading-relaxed">"<?php echo htmlspecialchars(mb_strimwidth($lf['comments']??'Great work!',0,120,'…')); ?>"</p>
              <p class="text-xs text-slate-500 mt-2 text-right font-semibold">— <?php echo htmlspecialchars($lf['given_by']??'Dr. Sarah Jenkins'); ?></p>
            </div>
            <?php endif; ?>
          </div>

          <!-- Upcoming Deadlines -->
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-red-500 text-[20px]">notifications_active</span>
              <h4 class="font-bold text-slate-800 text-sm">Upcoming Deadlines</h4>
            </div>
            <div class="space-y-3">
              <?php
                $deadlines = [
                  ['label'=>'Development Phase Review','sub'=>'Submit code for approval','days'=>min($d_left,5),'urgent'=>$d_left<=7],
                  ['label'=>'Final Project Submission','sub'=>'Complete project deployment check','days'=>min($d_left,20),'urgent'=>false],
                  ['label'=>'Daily Log Due','sub'=>'Submit today\'s activity log','days'=>0,'urgent'=>true],
                ];
                foreach($deadlines as $dl):
                  $dl_bg  = $dl['urgent'] ? 'bg-red-50 border-red-100' : 'bg-slate-50 border-slate-100';
                  $dl_day = $dl['urgent'] ? 'text-red-600 font-extrabold' : 'text-slate-500 font-semibold';
              ?>
              <div class="flex items-center justify-between p-3 rounded-xl border <?php echo $dl_bg; ?>">
                <div>
                  <p class="text-xs font-bold text-slate-800"><?php echo $dl['label']; ?></p>
                  <p class="text-[10px] text-slate-400 mt-0.5"><?php echo $dl['sub']; ?></p>
                </div>
                <span class="text-xs <?php echo $dl_day; ?> shrink-0 ml-3">
                  <?php echo $dl['days'] === 0 ? 'Today' : 'In '.$dl['days'].' Days'; ?>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div><!-- /right col -->
      </div><!-- /row 3 -->

      <?php else: ?>
      <!-- ══ NO ACTIVE INTERNSHIP ══ -->
      <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-slate-800">Dashboard</h2>
        <p class="text-sm text-slate-400 mt-0.5">Welcome, <?php echo htmlspecialchars($profile['full_name']); ?>! Start your internship journey.</p>
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">badge</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Status</p><p class="text-sm font-extrabold text-slate-400">Not Started</p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">assignment</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Applications</p><p class="text-sm font-extrabold text-slate-800"><?php echo $app_count; ?></p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">verified</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Shortlisted</p><p class="text-sm font-extrabold text-slate-800"><?php echo $shortlist_count; ?></p></div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
          <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[20px]">notifications</span></div>
          <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">Alerts</p><p class="text-sm font-extrabold text-slate-800"><?php echo $unread_count; ?></p></div>
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-6 text-white flex flex-col gap-4 shadow-lg">
          <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"><span class="material-symbols-outlined text-[26px]">rocket_launch</span></div>
          <div><h3 class="text-lg font-extrabold">Start Your Journey</h3><p class="text-blue-200 text-sm mt-1 leading-relaxed">Browse available internships and apply to kickstart your career.</p></div>
          <a href="student_browse_internships.php" class="inline-flex items-center gap-2 bg-white text-blue-700 font-bold text-sm px-4 py-2.5 rounded-xl hover:bg-blue-50 transition-colors shadow-sm w-fit">
            <span class="material-symbols-outlined text-[18px]">search</span> Browse Internships
          </a>
        </div>
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-slate-800 text-sm">Recent Applications</h3>
            <a href="student_applications.php" class="text-blue-600 text-xs font-bold hover:underline">View All →</a>
          </div>
          <?php mysqli_data_seek($app_result,0); $app_rows=[]; while($r=mysqli_fetch_assoc($app_result)) $app_rows[]=$r; ?>
          <?php if(count($app_rows)===0): ?>
          <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">assignment_late</span>
            <p class="text-slate-400 text-sm">No applications yet.</p>
          </div>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead><tr class="border-b border-slate-100">
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Title</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Status</th>
                <th class="text-left py-2 px-3 text-xs font-semibold text-slate-400 uppercase tracking-wide">Date</th>
              </tr></thead>
              <tbody class="divide-y divide-slate-50">
                <?php foreach(array_slice($app_rows,0,5) as $app):
                  $st=$app['status'];
                  $bg=match(true){
                    in_array($st,['Started','Active Intern','Internship Started','Selected'])=>'bg-emerald-100 text-emerald-700',
                    in_array($st,['Approved','Accepted','Shortlisted','HOD Approved'])=>'bg-blue-100 text-blue-700',
                    in_array($st,['Rejected','Declined'])=>'bg-red-100 text-red-600',
                    in_array($st,['HR Round','Test Completed','HR Screening'])=>'bg-indigo-100 text-indigo-700',
                    default=>'bg-slate-100 text-slate-600',
                  };
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="py-3 px-3 font-medium text-slate-800 max-w-[180px] truncate"><?php echo htmlspecialchars($app['title']); ?></td>
                  <td class="py-3 px-3"><span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $bg; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                  <td class="py-3 px-3 text-slate-400 text-xs"><?php echo date('M d, Y',strtotime($app['applied_date'])); ?></td>
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
