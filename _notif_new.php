    <section id="sec-notifications" class="dashboard-section">
      <?php
        $notif_rows = [];
        while ($nr = mysqli_fetch_assoc($all_notif_result)) $notif_rows[] = $nr;
        $total_n  = count($notif_rows);
        $unread_n = count(array_filter($notif_rows, fn($n) => !$n['is_read']));
        $read_n   = $total_n - $unread_n;

        // Type config — icon + colors + dynamic label
        $type_cfg = [
          'test'        => ['icon'=>'quiz',              'icon_bg'=>'bg-amber-100',   'text'=>'text-amber-600',  'badge_bg'=>'bg-amber-100',   'badge_text'=>'text-amber-700',   'border'=>'border-amber-200',  'dot'=>'bg-amber-500',   'label'=>'Assessment'],
          'warning'     => ['icon'=>'warning',           'icon_bg'=>'bg-amber-100',   'text'=>'text-amber-600',  'badge_bg'=>'bg-amber-100',   'badge_text'=>'text-amber-700',   'border'=>'border-amber-200',  'dot'=>'bg-amber-500',   'label'=>'Warning'],
          'success'     => ['icon'=>'check_circle',      'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge_bg'=>'bg-emerald-100', 'badge_text'=>'text-emerald-700', 'border'=>'border-emerald-200','dot'=>'bg-emerald-500', 'label'=>'Success'],
          'approved'    => ['icon'=>'verified',          'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge_bg'=>'bg-emerald-100', 'badge_text'=>'text-emerald-700', 'border'=>'border-emerald-200','dot'=>'bg-emerald-500', 'label'=>'Application Update'],
          'selected'    => ['icon'=>'emoji_events',      'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge_bg'=>'bg-emerald-100', 'badge_text'=>'text-emerald-700', 'border'=>'border-emerald-200','dot'=>'bg-emerald-500', 'label'=>'Selected'],
          'certificate' => ['icon'=>'workspace_premium', 'icon_bg'=>'bg-purple-100',  'text'=>'text-purple-600', 'badge_bg'=>'bg-purple-100',  'badge_text'=>'text-purple-700',  'border'=>'border-purple-200', 'dot'=>'bg-purple-500',  'label'=>'Certificate'],
          'feedback'    => ['icon'=>'reviews',           'icon_bg'=>'bg-indigo-100',  'text'=>'text-indigo-600', 'badge_bg'=>'bg-indigo-100',  'badge_text'=>'text-indigo-700',  'border'=>'border-indigo-200', 'dot'=>'bg-indigo-500',  'label'=>'Feedback'],
          'mentor'      => ['icon'=>'person_pin',        'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge_bg'=>'bg-blue-100',    'badge_text'=>'text-blue-700',    'border'=>'border-blue-200',   'dot'=>'bg-blue-500',    'label'=>'Mentor'],
          'internship'  => ['icon'=>'badge',             'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge_bg'=>'bg-blue-100',    'badge_text'=>'text-blue-700',    'border'=>'border-blue-200',   'dot'=>'bg-blue-500',    'label'=>'Internship'],
          'error'       => ['icon'=>'cancel',            'icon_bg'=>'bg-red-100',     'text'=>'text-red-600',    'badge_bg'=>'bg-red-100',     'badge_text'=>'text-red-700',     'border'=>'border-red-200',    'dot'=>'bg-red-500',     'label'=>'Alert'],
          'rejected'    => ['icon'=>'cancel',            'icon_bg'=>'bg-red-100',     'text'=>'text-red-600',    'badge_bg'=>'bg-red-100',     'badge_text'=>'text-red-700',     'border'=>'border-red-200',    'dot'=>'bg-red-500',     'label'=>'Rejected'],
          'info'        => ['icon'=>'info',              'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge_bg'=>'bg-blue-100',    'badge_text'=>'text-blue-700',    'border'=>'border-blue-200',   'dot'=>'bg-blue-500',    'label'=>'Info'],
          'log'         => ['icon'=>'edit_note',         'icon_bg'=>'bg-amber-100',   'text'=>'text-amber-600',  'badge_bg'=>'bg-amber-100',   'badge_text'=>'text-amber-700',   'border'=>'border-amber-200',  'dot'=>'bg-amber-500',   'label'=>'Daily Log Reminder'],
          'default'     => ['icon'=>'notifications',     'icon_bg'=>'bg-slate-100',   'text'=>'text-slate-500',  'badge_bg'=>'bg-slate-100',   'badge_text'=>'text-slate-600',   'border'=>'border-slate-200',  'dot'=>'bg-slate-400',   'label'=>'Update'],
        ];
      ?>

      <!-- ── Page Header ── -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight flex items-center gap-3">
            Recent Notifications
            <span id="notif-unread-badge"
                  class="<?php echo $unread_n > 0 ? '' : 'hidden'; ?> inline-flex items-center justify-center min-w-[24px] h-6 px-1.5 bg-red-500 text-white text-[11px] font-extrabold rounded-full">
              <?php echo $unread_n; ?>
            </span>
          </h2>
          <p class="text-sm text-slate-400 mt-0.5">Real-time updates about your internship, tests, feedback and more</p>
        </div>

        <!-- Controls -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Filter tabs -->
          <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-1">
            <button data-filter="all"    class="notif-tab active-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all bg-white text-slate-700 shadow-sm">All</button>
            <button data-filter="unread" class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-500 hover:text-slate-700">Unread</button>
            <button data-filter="read"   class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-500 hover:text-slate-700">Read</button>
          </div>
          <!-- Action buttons -->
          <button id="btn-mark-all-read"
                  class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?php echo $unread_n === 0 ? 'opacity-40 cursor-not-allowed' : ''; ?>">
            <span class="material-symbols-outlined text-[14px]">done_all</span> Mark all read
          </button>
          <button id="btn-clear-all"
                  class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 border border-red-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?php echo $total_n === 0 ? 'opacity-40 cursor-not-allowed' : ''; ?>">
            <span class="material-symbols-outlined text-[14px]">delete_sweep</span> Clear all
          </button>
          <button id="btn-refresh-notifs"
                  class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-500 rounded-xl transition-colors" title="Refresh">
            <span class="material-symbols-outlined text-[18px]">refresh</span>
          </button>
        </div>
      </div>

      <!-- ── Main layout ── -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Notification list (2 cols) -->
        <div class="lg:col-span-2">

          <!-- Empty state (shown when no notifications or all filtered out) -->
          <div id="notif-empty-state" class="<?php echo $total_n > 0 ? 'hidden' : ''; ?> bg-white rounded-2xl border border-slate-100 shadow-sm p-14 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
              <span class="material-symbols-outlined text-[36px] text-slate-300">notifications_none</span>
            </div>
            <h3 class="text-base font-bold text-slate-600 mb-1">No Notifications Available</h3>
            <p class="text-slate-400 text-sm">You're all caught up! Updates will appear here automatically.</p>
          </div>

          <!-- Filter empty state -->
          <div id="notif-filter-empty" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm p-10 text-center">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">filter_list_off</span>
            <p class="text-slate-500 text-sm font-medium">No notifications match this filter.</p>
          </div>

          <!-- Notification cards -->
          <div id="notif-list" class="space-y-3">
            <?php foreach ($notif_rows as $nr):
              $type   = strtolower($nr['type'] ?? 'default');
              $tc     = $type_cfg[$type] ?? $type_cfg['default'];
              $unread = !$nr['is_read'];
              $nid    = intval($nr['id']);
              // Time ago
              $cr  = new DateTime($nr['created_at']);
              $now2= new DateTime();
              $df  = $now2->diff($cr);
              if ($df->days >= 7)     $ta = date('M d, Y', strtotime($nr['created_at']));
              elseif ($df->days >= 1) $ta = $df->days . 'd ago';
              elseif ($df->h >= 1)    $ta = $df->h . 'h ago';
              elseif ($df->i >= 1)    $ta = $df->i . 'm ago';
              else                    $ta = 'Just now';
            ?>
            <div id="notif-card-<?php echo $nid; ?>"
                 data-id="<?php echo $nid; ?>"
                 data-read="<?php echo $unread ? '0' : '1'; ?>"
                 class="notif-card group bg-white rounded-2xl border <?php echo $unread ? 'border-blue-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
              <div class="flex">
                <!-- Left accent bar -->
                <div class="w-1 shrink-0 rounded-l-2xl <?php echo $unread ? $tc['dot'] : 'bg-slate-200'; ?>"></div>
                <div class="flex-1 p-4 flex items-start gap-4">
                  <!-- Icon -->
                  <div class="w-11 h-11 rounded-xl <?php echo $tc['icon_bg']; ?> flex items-center justify-center shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-[22px] <?php echo $tc['text']; ?>"><?php echo $tc['icon']; ?></span>
                  </div>
                  <!-- Body -->
                  <div class="flex-1 min-w-0">
                    <!-- Top row: badge + new pill + unread dot -->
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                      <div class="flex items-center gap-2 flex-wrap">
                        <!-- Dynamic type badge from DB type field -->
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $tc['badge_bg'].' '.$tc['badge_text']; ?>">
                          <?php echo htmlspecialchars($tc['label']); ?>
                        </span>
                        <?php if ($unread): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-600 text-white">New</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($unread): ?>
                      <div class="w-2.5 h-2.5 <?php echo $tc['dot']; ?> rounded-full shrink-0 mt-1 animate-pulse"></div>
                      <?php endif; ?>
                    </div>
                    <!-- Message — dynamic from DB -->
                    <p class="text-sm text-slate-700 <?php echo $unread ? 'font-semibold' : 'font-medium'; ?> leading-snug">
                      <?php echo htmlspecialchars($nr['message']); ?>
                    </p>
                    <!-- Footer: time + action buttons -->
                    <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-slate-100">
                      <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <span class="material-symbols-outlined text-[13px]">schedule</span>
                        <span><?php echo $ta; ?></span>
                        <span class="text-slate-300 mx-1">·</span>
                        <span><?php echo date('M d, Y · g:i A', strtotime($nr['created_at'])); ?></span>
                      </div>
                      <!-- Action buttons — appear on hover -->
                      <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <?php if ($unread): ?>
                        <button onclick="notifMarkRead(<?php echo $nid; ?>, this)"
                                class="flex items-center gap-1 px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 rounded-lg text-[11px] font-semibold transition-colors">
                          <span class="material-symbols-outlined text-[13px]">done</span> Mark read
                        </button>
                        <?php endif; ?>
                        <button onclick="notifDelete(<?php echo $nid; ?>, this)"
                                class="flex items-center gap-1 px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-500 border border-red-200 rounded-lg text-[11px] font-semibold transition-colors">
                          <span class="material-symbols-outlined text-[13px]">close</span> Remove
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- RIGHT: Summary card only -->
        <div>
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 sticky top-24">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-4">Summary</h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                <div class="flex items-center gap-2 text-sm text-slate-600">
                  <span class="material-symbols-outlined text-[16px] text-slate-400">notifications</span> Total
                </div>
                <span id="stat-total" class="font-extrabold text-slate-800"><?php echo $total_n; ?></span>
              </div>
              <div class="flex items-center justify-between p-3 bg-red-50 rounded-xl border border-red-100">
                <div class="flex items-center gap-2 text-sm text-red-600 font-medium">
                  <span class="material-symbols-outlined text-[16px] text-red-400">mark_email_unread</span> Unread
                </div>
                <span id="stat-unread" class="font-extrabold text-red-600"><?php echo $unread_n; ?></span>
              </div>
              <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                <div class="flex items-center gap-2 text-sm text-emerald-600 font-medium">
                  <span class="material-symbols-outlined text-[16px] text-emerald-400">mark_email_read</span> Read
                </div>
                <span id="stat-read" class="font-extrabold text-emerald-600"><?php echo $read_n; ?></span>
              </div>
            </div>

            <!-- Quick actions in summary card -->
            <div class="mt-5 pt-4 border-t border-slate-100 space-y-2">
              <button id="btn-mark-all-side"
                      class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-200 rounded-xl text-sm font-semibold text-slate-700 hover:text-blue-700 transition-all">
                <span class="material-symbols-outlined text-[18px]">done_all</span> Mark All as Read
              </button>
              <button id="btn-clear-all-side"
                      class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-slate-50 hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-xl text-sm font-semibold text-slate-700 hover:text-red-600 transition-all">
                <span class="material-symbols-outlined text-[18px]">delete_sweep</span> Clear All
              </button>
              <button id="btn-refresh-side"
                      class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 transition-all">
                <span class="material-symbols-outlined text-[18px]">refresh</span> Refresh
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

