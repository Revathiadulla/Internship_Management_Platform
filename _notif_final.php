    <section id="sec-notifications" class="dashboard-section">
      <?php
        $notif_rows = [];
        while ($nr = mysqli_fetch_assoc($all_notif_result)) $notif_rows[] = $nr;
        $total_n  = count($notif_rows);
        $unread_n = count(array_filter($notif_rows, fn($n) => !$n['is_read']));
        $read_n   = $total_n - $unread_n;
        $type_cfg = [
          'test'        => ['icon'=>'quiz',              'icon_bg'=>'bg-amber-100',   'text'=>'text-amber-600',  'badge'=>'bg-amber-100 text-amber-700',   'dot'=>'bg-amber-500',   'label'=>'Assessment'],
          'warning'     => ['icon'=>'warning',           'icon_bg'=>'bg-amber-100',   'text'=>'text-amber-600',  'badge'=>'bg-amber-100 text-amber-700',   'dot'=>'bg-amber-500',   'label'=>'Warning'],
          'success'     => ['icon'=>'check_circle',      'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge'=>'bg-emerald-100 text-emerald-700','dot'=>'bg-emerald-500', 'label'=>'Success'],
          'approved'    => ['icon'=>'verified',          'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge'=>'bg-emerald-100 text-emerald-700','dot'=>'bg-emerald-500', 'label'=>'Application Update'],
          'selected'    => ['icon'=>'emoji_events',      'icon_bg'=>'bg-emerald-100', 'text'=>'text-emerald-600','badge'=>'bg-emerald-100 text-emerald-700','dot'=>'bg-emerald-500', 'label'=>'Selected'],
          'certificate' => ['icon'=>'workspace_premium', 'icon_bg'=>'bg-purple-100',  'text'=>'text-purple-600', 'badge'=>'bg-purple-100 text-purple-700',  'dot'=>'bg-purple-500',  'label'=>'Certificate'],
          'feedback'    => ['icon'=>'reviews',           'icon_bg'=>'bg-indigo-100',  'text'=>'text-indigo-600', 'badge'=>'bg-indigo-100 text-indigo-700',  'dot'=>'bg-indigo-500',  'label'=>'Feedback'],
          'mentor'      => ['icon'=>'person_pin',        'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge'=>'bg-blue-100 text-blue-700',      'dot'=>'bg-blue-500',    'label'=>'Mentor'],
          'internship'  => ['icon'=>'badge',             'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge'=>'bg-blue-100 text-blue-700',      'dot'=>'bg-blue-500',    'label'=>'Internship'],
          'error'       => ['icon'=>'cancel',            'icon_bg'=>'bg-red-100',     'text'=>'text-red-600',    'badge'=>'bg-red-100 text-red-700',        'dot'=>'bg-red-500',     'label'=>'Alert'],
          'rejected'    => ['icon'=>'cancel',            'icon_bg'=>'bg-red-100',     'text'=>'text-red-600',    'badge'=>'bg-red-100 text-red-700',        'dot'=>'bg-red-500',     'label'=>'Rejected'],
          'info'        => ['icon'=>'info',              'icon_bg'=>'bg-blue-100',    'text'=>'text-blue-600',   'badge'=>'bg-blue-100 text-blue-700',      'dot'=>'bg-blue-500',    'label'=>'Info'],
          'log'         => ['icon'=>'edit_note',         'icon_bg'=>'bg-amber-100',   'text'=>'text-amber-600',  'badge'=>'bg-amber-100 text-amber-700',   'dot'=>'bg-amber-500',   'label'=>'Daily Log Reminder'],
          'default'     => ['icon'=>'notifications',     'icon_bg'=>'bg-slate-100',   'text'=>'text-slate-500',  'badge'=>'bg-slate-100 text-slate-600',    'dot'=>'bg-slate-400',   'label'=>'Update'],
        ];
      ?>

      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight flex items-center gap-3">
            Recent Notifications
            <span id="notif-unread-badge" class="<?php echo $unread_n > 0 ? '' : 'hidden'; ?> inline-flex items-center justify-center min-w-[24px] h-6 px-1.5 bg-red-500 text-white text-[11px] font-extrabold rounded-full"><?php echo $unread_n; ?></span>
          </h2>
          <p class="text-sm text-slate-400 mt-0.5">Real-time updates about your internship, tests, feedback and more</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <!-- Filter tabs -->
          <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-1">
            <button data-filter="all"    class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all bg-white text-slate-700 shadow-sm">All</button>
            <button data-filter="unread" class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-500 hover:text-slate-700">Unread</button>
            <button data-filter="read"   class="notif-tab px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-500 hover:text-slate-700">Read</button>
          </div>
          <button id="btn-mark-all-read" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-[14px]">done_all</span> Mark all read
          </button>
          <button id="btn-clear-all" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-500 border border-red-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
            <span class="material-symbols-outlined text-[14px]">delete_sweep</span> Clear all
          </button>
          <button id="btn-refresh-notifs" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-500 rounded-xl transition-colors" title="Refresh">
            <span class="material-symbols-outlined text-[18px]">refresh</span>
          </button>
        </div>
      </div>

      <!-- Main grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Notification list -->
        <div class="lg:col-span-2">
          <!-- Empty state -->
          <div id="notif-empty-state" class="<?php echo $total_n > 0 ? 'hidden' : ''; ?> bg-white rounded-2xl border border-slate-100 shadow-sm p-14 text-center">
            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
              <span class="material-symbols-outlined text-[36px] text-slate-300">notifications_none</span>
            </div>
            <h3 class="text-base font-bold text-slate-600 mb-1">No Notifications Available</h3>
            <p class="text-slate-400 text-sm">You're all caught up! Updates will appear here automatically.</p>
          </div>
          <!-- Filter empty -->
          <div id="notif-filter-empty" class="hidden bg-white rounded-2xl border border-slate-100 shadow-sm p-10 text-center">
            <span class="material-symbols-outlined text-[32px] text-slate-300 block mb-2">filter_list_off</span>
            <p class="text-slate-500 text-sm font-medium">No notifications match this filter.</p>
          </div>
          <!-- Cards -->
          <div id="notif-list" class="space-y-3">
            <?php foreach ($notif_rows as $nr):
              $type   = strtolower($nr['type'] ?? 'default');
              $tc     = $type_cfg[$type] ?? $type_cfg['default'];
              $unread = !$nr['is_read'];
              $nid    = intval($nr['id']);
              $cr = new DateTime($nr['created_at']); $now2 = new DateTime(); $df = $now2->diff($cr);
              if ($df->days >= 7)     $ta = date('M d, Y', strtotime($nr['created_at']));
              elseif ($df->days >= 1) $ta = $df->days . 'd ago';
              elseif ($df->h >= 1)    $ta = $df->h . 'h ago';
              elseif ($df->i >= 1)    $ta = $df->i . 'm ago';
              else                    $ta = 'Just now';
            ?>
            <div id="notif-card-<?php echo $nid; ?>" data-id="<?php echo $nid; ?>" data-read="<?php echo $unread ? '0' : '1'; ?>"
                 class="notif-card group bg-white rounded-2xl border <?php echo $unread ? 'border-blue-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
              <div class="flex">
                <div class="w-1 shrink-0 rounded-l-2xl <?php echo $unread ? $tc['dot'] : 'bg-slate-200'; ?>"></div>
                <div class="flex-1 p-4 flex items-start gap-4">
                  <div class="w-11 h-11 rounded-xl <?php echo $tc['icon_bg']; ?> flex items-center justify-center shrink-0 mt-0.5">
                    <span class="material-symbols-outlined text-[22px] <?php echo $tc['text']; ?>"><?php echo $tc['icon']; ?></span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $tc['badge']; ?>"><?php echo htmlspecialchars($tc['label']); ?></span>
                        <?php if ($unread): ?><span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-600 text-white">New</span><?php endif; ?>
                      </div>
                      <?php if ($unread): ?><div class="w-2.5 h-2.5 <?php echo $tc['dot']; ?> rounded-full shrink-0 mt-1 animate-pulse"></div><?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-700 <?php echo $unread ? 'font-semibold' : 'font-medium'; ?> leading-snug"><?php echo htmlspecialchars($nr['message']); ?></p>
                    <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-slate-100">
                      <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <span class="material-symbols-outlined text-[13px]">schedule</span>
                        <span><?php echo $ta; ?></span>
                        <span class="text-slate-300 mx-1">·</span>
                        <span><?php echo date('M d, Y · g:i A', strtotime($nr['created_at'])); ?></span>
                      </div>
                      <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <?php if ($unread): ?>
                        <button onclick="notifMarkRead(<?php echo $nid; ?>, this)" class="flex items-center gap-1 px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-200 rounded-lg text-[11px] font-semibold transition-colors">
                          <span class="material-symbols-outlined text-[13px]">done</span> Mark read
                        </button>
                        <?php endif; ?>
                        <button onclick="notifDelete(<?php echo $nid; ?>, this)" class="flex items-center gap-1 px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-500 border border-red-200 rounded-lg text-[11px] font-semibold transition-colors">
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

        <!-- RIGHT: Summary card only (no static types) -->
        <div>
          <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 sticky top-24">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-4">Summary</h4>
            <div class="space-y-3">
              <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                <div class="flex items-center gap-2 text-sm text-slate-600"><span class="material-symbols-outlined text-[16px] text-slate-400">notifications</span> Total</div>
                <span id="stat-total" class="font-extrabold text-slate-800"><?php echo $total_n; ?></span>
              </div>
              <div class="flex items-center justify-between p-3 bg-red-50 rounded-xl border border-red-100">
                <div class="flex items-center gap-2 text-sm text-red-600 font-medium"><span class="material-symbols-outlined text-[16px] text-red-400">mark_email_unread</span> Unread</div>
                <span id="stat-unread" class="font-extrabold text-red-600"><?php echo $unread_n; ?></span>
              </div>
              <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                <div class="flex items-center gap-2 text-sm text-emerald-600 font-medium"><span class="material-symbols-outlined text-[16px] text-emerald-400">mark_email_read</span> Read</div>
                <span id="stat-read" class="font-extrabold text-emerald-600"><?php echo $read_n; ?></span>
              </div>
            </div>
            <div class="mt-5 pt-4 border-t border-slate-100 space-y-2">
              <button id="btn-mark-all-side" class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-200 rounded-xl text-sm font-semibold text-slate-700 hover:text-blue-700 transition-all">
                <span class="material-symbols-outlined text-[18px]">done_all</span> Mark All as Read
              </button>
              <button id="btn-clear-all-side" class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-slate-50 hover:bg-red-50 border border-slate-200 hover:border-red-200 rounded-xl text-sm font-semibold text-slate-700 hover:text-red-600 transition-all">
                <span class="material-symbols-outlined text-[18px]">delete_sweep</span> Clear All
              </button>
              <button id="btn-refresh-side" class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 transition-all">
                <span class="material-symbols-outlined text-[18px]">refresh</span> Refresh
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main><!-- /main -->
</div><!-- /pl-64 -->

<script>
// ── Section navigation ────────────────────────────────────────────────────────
(function () {
  const navItems = document.querySelectorAll('.nav-item[data-section]');
  const sections = document.querySelectorAll('.dashboard-section');
  function showSection(id) {
    sections.forEach(s => {
      if (s.id === id) { s.style.display = 'block'; requestAnimationFrame(() => requestAnimationFrame(() => s.classList.add('active'))); }
      else { s.classList.remove('active'); s.style.display = 'none'; }
    });
    navItems.forEach(btn => {
      if (btn.dataset.section === id) { btn.classList.add('bg-blue-50','text-blue-700','font-semibold'); btn.classList.remove('text-gray-600','hover:bg-gray-50','hover:text-blue-600'); }
      else { btn.classList.remove('bg-blue-50','text-blue-700','font-semibold'); btn.classList.add('text-gray-600','hover:bg-gray-50','hover:text-blue-600'); }
    });
    history.replaceState(null, '', '#' + id);
  }
  navItems.forEach(btn => btn.addEventListener('click', () => showSection(btn.dataset.section)));
  const hash = location.hash.replace('#', '');
  const validIds = Array.from(sections).map(s => s.id);
  showSection(hash && validIds.includes(hash) ? hash : 'sec-dashboard');
})();

// ── Profile dropdown ──────────────────────────────────────────────────────────
(function () {
  const toggle = document.getElementById('profile-toggle');
  const dropdown = document.getElementById('profile-dropdown');
  if (!toggle || !dropdown) return;
  toggle.addEventListener('click', e => { e.stopPropagation(); dropdown.classList.toggle('hidden'); });
  document.addEventListener('click', e => { if (!toggle.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('hidden'); });
})();

// ── Daily log form validation ─────────────────────────────────────────────────
(function () {
  const form = document.getElementById('log-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    const tasks = form.querySelector('[name="tasks_completed"]').value.trim();
    const hours = parseFloat(form.querySelector('[name="time_spent"]').value);
    const focus = form.querySelector('[name="focus_level"]').value;
    if (!tasks) { e.preventDefault(); alert('Please describe the tasks you completed today.'); return; }
    if (isNaN(hours) || hours < 0.5 || hours > 12) { e.preventDefault(); alert('Please enter a valid time between 0.5 and 12 hours.'); return; }
    if (!focus) { e.preventDefault(); alert('Please select a focus level.'); }
  });
})();

// ── Header search ─────────────────────────────────────────────────────────────
(function () {
  const input = document.getElementById('header-search');
  if (!input) return;
  const map = { 'dashboard':'sec-dashboard','internship':'sec-internship','log':'sec-daily-logs','daily':'sec-daily-logs','project':'sec-project','feedback':'sec-feedback','certificate':'sec-certificate','notification':'sec-notifications' };
  input.addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    const q = input.value.toLowerCase().trim();
    for (const [kw, id] of Object.entries(map)) { if (q.includes(kw)) { document.querySelector(`.nav-item[data-section="${id}"]`)?.click(); input.value = ''; input.blur(); return; } }
  });
})();

// ══════════════════════════════════════════════════════════════════════════════
// NOTIFICATIONS — fully functional AJAX system
// ══════════════════════════════════════════════════════════════════════════════
(function () {

  // ── Helpers ──────────────────────────────────────────────────────────────
  function updateStats() {
    const all    = document.querySelectorAll('.notif-card');
    const unread = document.querySelectorAll('.notif-card[data-read="0"]');
    const total  = all.length;
    const unreadCount = unread.length;
    const readCount   = total - unreadCount;

    document.getElementById('stat-total').textContent  = total;
    document.getElementById('stat-unread').textContent = unreadCount;
    document.getElementById('stat-read').textContent   = readCount;

    // Update header badge
    const badge = document.getElementById('notif-unread-badge');
    if (badge) {
      badge.textContent = unreadCount;
      badge.classList.toggle('hidden', unreadCount === 0);
    }

    // Update sidebar bell badge if present
    const sidebarBadge = document.querySelector('.nav-item[data-section="sec-notifications"] span.bg-red-100');
    if (sidebarBadge) {
      sidebarBadge.textContent = unreadCount;
      sidebarBadge.classList.toggle('hidden', unreadCount === 0);
    }

    // Disable/enable action buttons
    const markAllBtn  = document.getElementById('btn-mark-all-read');
    const clearAllBtn = document.getElementById('btn-clear-all');
    if (markAllBtn)  markAllBtn.disabled  = unreadCount === 0;
    if (clearAllBtn) clearAllBtn.disabled = total === 0;

    // Show/hide empty state
    const emptyState = document.getElementById('notif-empty-state');
    if (emptyState) emptyState.classList.toggle('hidden', total > 0);

    // Re-apply current filter
    applyFilter(currentFilter);
  }

  function showToast(msg, type = 'success') {
    const existing = document.getElementById('notif-toast');
    if (existing) existing.remove();
    const colors = type === 'success'
      ? 'bg-emerald-600 border-emerald-500'
      : type === 'error'
      ? 'bg-red-600 border-red-500'
      : 'bg-blue-600 border-blue-500';
    const icon = type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info';
    const toast = document.createElement('div');
    toast.id = 'notif-toast';
    toast.className = `fixed top-6 right-6 z-[999] ${colors} text-white rounded-2xl shadow-xl px-5 py-3.5 flex items-center gap-3 border transform translate-x-[420px] transition-transform duration-500 ease-out`;
    toast.innerHTML = `<span class="material-symbols-outlined text-[20px]">${icon}</span><span class="text-sm font-semibold">${msg}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-x-[420px]'), 50);
    setTimeout(() => { toast.classList.add('translate-x-[420px]'); setTimeout(() => toast.remove(), 500); }, 3000);
  }

  function slideOut(el, cb) {
    el.style.transition = 'all 0.3s ease';
    el.style.opacity    = '0';
    el.style.transform  = 'translateX(40px)';
    el.style.maxHeight  = el.offsetHeight + 'px';
    setTimeout(() => { el.style.maxHeight = '0'; el.style.marginBottom = '0'; el.style.padding = '0'; }, 150);
    setTimeout(() => { el.remove(); if (cb) cb(); }, 350);
  }

  // ── Filter ────────────────────────────────────────────────────────────────
  let currentFilter = 'all';

  function applyFilter(filter) {
    currentFilter = filter;
    const cards   = document.querySelectorAll('.notif-card');
    let visible   = 0;
    cards.forEach(card => {
      const isRead = card.dataset.read === '1';
      let show = filter === 'all' || (filter === 'unread' && !isRead) || (filter === 'read' && isRead);
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    const filterEmpty = document.getElementById('notif-filter-empty');
    if (filterEmpty) filterEmpty.classList.toggle('hidden', visible > 0 || cards.length === 0);
  }

  document.querySelectorAll('.notif-tab').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.notif-tab').forEach(t => {
        t.classList.remove('bg-white', 'text-slate-700', 'shadow-sm');
        t.classList.add('text-slate-500');
      });
      this.classList.add('bg-white', 'text-slate-700', 'shadow-sm');
      this.classList.remove('text-slate-500');
      applyFilter(this.dataset.filter);
    });
  });

  // ── Mark single as read ───────────────────────────────────────────────────
  window.notifMarkRead = async function (id, btn) {
    btn.disabled = true;
    try {
      const res  = await fetch(`mark_notification_read.php?action=read&id=${id}`);
      const data = await res.json();
      if (data.success) {
        const card = document.getElementById(`notif-card-${id}`);
        if (card) {
          card.dataset.read = '1';
          // Remove blue border + bg
          card.classList.remove('border-blue-200');
          card.classList.add('border-slate-100');
          // Remove left accent color
          const bar = card.querySelector('.w-1');
          if (bar) { bar.className = bar.className.replace(/bg-\w+-\d+/g, ''); bar.classList.add('bg-slate-200'); }
          // Remove "New" badge
          card.querySelectorAll('.bg-blue-600.text-white').forEach(el => el.remove());
          // Remove unread dot
          card.querySelectorAll('.animate-pulse').forEach(el => el.remove());
          // Remove mark-read button
          btn.closest('button')?.remove();
          // Make text normal weight
          const msg = card.querySelector('p.font-semibold');
          if (msg) { msg.classList.remove('font-semibold'); msg.classList.add('font-medium'); }
        }
        updateStats();
        showToast('Marked as read');
      }
    } catch { showToast('Failed to update', 'error'); btn.disabled = false; }
  };

  // ── Delete single ─────────────────────────────────────────────────────────
  window.notifDelete = async function (id, btn) {
    btn.disabled = true;
    try {
      const res  = await fetch(`mark_notification_read.php?action=delete&id=${id}`);
      const data = await res.json();
      if (data.success) {
        const card = document.getElementById(`notif-card-${id}`);
        if (card) slideOut(card, updateStats);
        showToast('Notification removed');
      } else { showToast('Failed to remove', 'error'); btn.disabled = false; }
    } catch { showToast('Failed to remove', 'error'); btn.disabled = false; }
  };

  // ── Mark ALL as read ──────────────────────────────────────────────────────
  async function markAllRead() {
    const unreadCards = document.querySelectorAll('.notif-card[data-read="0"]');
    if (unreadCards.length === 0) return;
    try {
      const res  = await fetch('mark_notification_read.php?action=read_all');
      const data = await res.json();
      if (data.success) {
        unreadCards.forEach(card => {
          card.dataset.read = '1';
          card.classList.remove('border-blue-200'); card.classList.add('border-slate-100');
          const bar = card.querySelector('.w-1');
          if (bar) { bar.className = bar.className.replace(/bg-\w+-\d+/g, ''); bar.classList.add('bg-slate-200'); }
          card.querySelectorAll('.bg-blue-600.text-white, .animate-pulse').forEach(el => el.remove());
          const msg = card.querySelector('p.font-semibold');
          if (msg) { msg.classList.remove('font-semibold'); msg.classList.add('font-medium'); }
          // Remove mark-read buttons
          card.querySelectorAll('button').forEach(b => { if (b.textContent.includes('Mark read')) b.remove(); });
        });
        updateStats();
        showToast(`${unreadCards.length} notification${unreadCards.length > 1 ? 's' : ''} marked as read`);
      }
    } catch { showToast('Failed to update', 'error'); }
  }

  // ── Clear ALL ─────────────────────────────────────────────────────────────
  async function clearAll() {
    const cards = document.querySelectorAll('.notif-card');
    if (cards.length === 0) return;
    if (!confirm(`Remove all ${cards.length} notification${cards.length > 1 ? 's' : ''}? This cannot be undone.`)) return;
    try {
      const res  = await fetch('mark_notification_read.php?action=delete_all');
      const data = await res.json();
      if (data.success) {
        const list = document.getElementById('notif-list');
        cards.forEach((card, i) => {
          setTimeout(() => slideOut(card, i === cards.length - 1 ? updateStats : null), i * 60);
        });
        showToast('All notifications cleared');
      } else { showToast('Failed to clear', 'error'); }
    } catch { showToast('Failed to clear', 'error'); }
  }

  // ── Refresh ───────────────────────────────────────────────────────────────
  function refreshNotifs() {
    const btn = document.getElementById('btn-refresh-notifs');
    const icon = btn?.querySelector('.material-symbols-outlined');
    if (icon) icon.style.animation = 'spin 0.6s linear';
    setTimeout(() => { location.reload(); }, 300);
  }

  // ── Wire up buttons ───────────────────────────────────────────────────────
  document.getElementById('btn-mark-all-read')?.addEventListener('click', markAllRead);
  document.getElementById('btn-clear-all')?.addEventListener('click', clearAll);
  document.getElementById('btn-refresh-notifs')?.addEventListener('click', refreshNotifs);
  document.getElementById('btn-mark-all-side')?.addEventListener('click', markAllRead);
  document.getElementById('btn-clear-all-side')?.addEventListener('click', clearAll);
  document.getElementById('btn-refresh-side')?.addEventListener('click', refreshNotifs);

  // ── Init ──────────────────────────────────────────────────────────────────
  updateStats();

})();
</script>
