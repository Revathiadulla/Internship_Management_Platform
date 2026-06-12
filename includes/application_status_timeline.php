<?php
// This file generates the status timeline UI for an application
// Usage: include this file and call renderStatusTimeline($application_id, $conn)

include_once __DIR__ . '/status_utils.php';

function renderStatusTimeline($application_id, $conn) {
    // Fetch application details with profile fallback
    $app_sql = "SELECT a.status, a.education_status, a.applied_date, a.confirmation_letter_path, a.confirmation_letter_sent_at, a.user_id,
                       sp.education_status AS profile_edu_status, sp.student_type AS sp_student_type
                FROM internship_applications a
                LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                WHERE a.id = $application_id LIMIT 1";
    $app_result = mysqli_query($conn, $app_sql);
    
    if (mysqli_num_rows($app_result) == 0) {
        echo '<p class="text-sm text-slate-500">Application not found.</p>';
        return;
    }
    
    $app = mysqli_fetch_assoc($app_result);
    $current_status   = in_array(strtolower(trim((string) ($app['status'] ?? ''))), ['confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent']) ? 'Selected' : ($app['status'] ?? 'Applied');
    $education_status = !empty($app['education_status']) ? $app['education_status'] : (!empty($app['profile_edu_status']) ? $app['profile_edu_status'] : 'Pursuing');
    $student_type_raw = $app['sp_student_type'] ?? '';
    $st_lower         = strtolower(trim($student_type_raw));
    $edu_lower        = strtolower(trim($education_status));
    $is_passed_out    = ($st_lower === 'passed_out' || $st_lower === 'passed out' || $edu_lower === 'passed out' || $edu_lower === 'passed_out');
    $is_pursuing      = !$is_passed_out;
    
    $applied_date = $app['applied_date'];
    $has_letter = !empty($app['confirmation_letter_path']);
    $letter_emailed = !empty($app['confirmation_letter_sent_at']);
    
    // Status config for styling
        // Status config for styling (aligned with new official flow)
        $status_config = [
            'Applied'                  => ['bg' => 'bg-slate-500',   'icon' => 'send',            'color' => 'slate'],
            'HR Review'                => ['bg' => 'bg-indigo-500',  'icon' => 'rate_review',     'color' => 'indigo'],
            'Shortlisted'              => ['bg' => 'bg-amber-500',   'icon' => 'star',            'color' => 'amber'],
            'Exam Mail Sent'           => ['bg' => 'bg-purple-500',  'icon' => 'mail',            'color' => 'purple'],
            'HOD Pending'              => ['bg' => 'bg-orange-500',  'icon' => 'hourglass_empty', 'color' => 'orange'],
            'HOD Approved'             => ['bg' => 'bg-teal-500',    'icon' => 'verified',        'color' => 'teal'],
            'HR Selected'              => ['bg' => 'bg-emerald-500', 'icon' => 'how_to_reg',      'color' => 'emerald'],
            'Selected'                 => ['bg' => 'bg-emerald-500', 'icon' => 'how_to_reg',      'color' => 'emerald'],
            'Project Assigned'         => ['bg' => 'bg-blue-500',    'icon' => 'assignment',      'color' => 'blue'],
            'Active Intern'            => ['bg' => 'bg-green-500',   'icon' => 'work',            'color' => 'green'],
            'Completed'                => ['bg' => 'bg-emerald-700', 'icon' => 'task_alt',        'color' => 'emerald'],
            'Talent Pool'              => ['bg' => 'bg-rose-500',    'icon' => 'groups',          'color' => 'rose'],
            'Rejected'                 => ['bg' => 'bg-red-500',     'icon' => 'cancel',          'color' => 'red'],
            'HOD Rejected'             => ['bg' => 'bg-red-500',     'icon' => 'cancel',          'color' => 'red'],
        ];

    // Build timeline steps dynamically
    $raw_steps = getWorkflowSteps($education_status, $student_type_raw);
    $steps = [];
    foreach ($raw_steps as $ws) {
        $st = $ws['status'];
        $cfg = $status_config[$st] ?? ['bg' => 'bg-slate-500', 'icon' => 'info', 'color' => 'slate'];
        $steps[] = [
            'id' => strtolower(str_replace(' ', '_', $st)),
            'status' => $st,
            'label' => $ws['label'],
            'icon' => $ws['icon'],
            'color' => $cfg['color'],
            'bg' => $cfg['bg']
        ];
    }

    // Determine current index in timeline
    $current_index = getCurrentStepIndex($current_status, $raw_steps);

    foreach ($steps as $index => &$step) {
        if ($current_status === 'Internship Active' || in_array(strtolower($current_status), ['started', 'active intern', 'internship started'])) {
              $step['is_completed'] = true;
              $step['is_current'] = false;
        } else {
            if ($current_index !== -1) {
                if ($index < $current_index) {
                    $step['is_completed'] = true;
                    $step['is_current'] = false;
                } elseif ($index === $current_index) {
                    $step['is_completed'] = false;
                    $step['is_current'] = true;
                } else {
                    $step['is_completed'] = false;
                    $step['is_current'] = false;
                }
            } else {
                if ($index === 0) {
                    $step['is_completed'] = false;
                    $step['is_current'] = true;
                } else {
                    $step['is_completed'] = false;
                    $step['is_current'] = false;
                }
            }
        }
    }
    unset($step);

    // Fetch status history
    $history_sql = "SELECT * FROM application_status_history 
                    WHERE application_id = $application_id 
                    ORDER BY created_at DESC";
    $history_result = mysqli_query($conn, $history_sql);
    $history = [];
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history[] = $row;
    }
    
    // Get last updated timestamp
    $last_updated = !empty($history) ? $history[0]['created_at'] : $applied_date;
    
    // Top Card Human Friendly Status
    $display_status = $current_status;
    if ($current_status === 'HR Round') {
        $display_status = 'HR Review';
    }
    
    $header_icon = $status_config[$current_status]['icon'] ?? 'info';
    ?>
    <div class="bg-gradient-to-br from-white to-slate-50 rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider opacity-90 mb-1">Application Status</p>
                    <h3 class="text-2xl font-extrabold tracking-tight">
                        <?php echo htmlspecialchars($display_status); ?>
                    </h3>
                </div>
                <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                    <span class="material-symbols-outlined text-4xl"><?php echo $header_icon; ?></span>
                </div>
            </div>
            <p class="text-xs opacity-75 mt-3">
                <span class="material-symbols-outlined text-[14px] align-middle mr-1">schedule</span>
                Last updated <?php echo formatTimestamp($last_updated); ?>
            </p>
            <?php if ($has_letter || $letter_emailed): ?>
            <div class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                <span class="material-symbols-outlined text-[14px]">mail</span>
                Confirmation Letter: Sent
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Progress Timeline -->
        <div class="p-8">
            <div class="relative">
                <!-- Vertical Timeline -->
                <div class="space-y-6">
                    <?php foreach ($steps as $index => $step): 
                        $stage = $step['label'];
                        $is_completed = $step['is_completed'];
                        $is_current = $step['is_current'];
                        $is_rejected = ($current_status === 'Rejected');
                        
                        if ($is_rejected && $index > 0) {
                            $circle_class = 'bg-slate-200 text-slate-400 border-slate-300';
                            $line_class = 'bg-slate-200';
                        } elseif ($is_completed) {
                            $circle_class = $step['bg'] . ' text-white border-' . $step['color'] . '-600 shadow-lg';
                            $line_class = 'bg-gradient-to-b from-' . $step['color'] . '-500 to-' . $step['color'] . '-600';
                        } elseif ($is_current) {
                            $circle_class = $step['bg'] . ' text-white border-' . $step['color'] . '-600 shadow-xl ring-4 ring-' . $step['color'] . '-100 animate-pulse';
                            $line_class = 'bg-slate-200';
                        } else {
                            $circle_class = 'bg-slate-200 text-slate-400 border-slate-300';
                            $line_class = 'bg-slate-200';
                        }
                    ?>
                    <div class="flex items-start gap-4 relative">
                        <!-- Vertical Line -->
                        <?php if ($index < count($steps) - 1): ?>
                        <div class="absolute left-6 top-14 w-0.5 h-12 <?php echo $line_class; ?> transition-all"></div>
                        <?php endif; ?>
                        
                        <!-- Circle -->
                        <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold border-2 <?php echo $circle_class; ?> transition-all z-10 shrink-0">
                            <?php if ($is_completed && !$is_current): ?>
                                <span class="material-symbols-outlined text-[24px]">check</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-[24px]"><?php echo $step['icon']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1 pt-2">
                            <h4 class="font-bold text-slate-800 text-base"><?php echo $stage; ?></h4>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <?php if ($is_completed && !$is_current): ?>
                                    <span class="text-emerald-600 font-semibold">✓ Completed</span>
                                <?php elseif ($is_current): ?>
                                    <span class="text-blue-600 font-semibold">● Current Stage</span>
                                <?php else: ?>
                                    <span class="text-slate-400">○ Pending</span>
                                <?php endif; ?>
                            </p>

                            <?php if ($step['status'] === 'Selected' && ($has_letter || $letter_emailed)): ?>
                            <div class="mt-2 rounded-lg border border-emerald-100 bg-emerald-50 p-2.5 text-xs text-emerald-700 space-y-1">
                                <?php if ($has_letter): ?>
                                <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">check_circle</span> Confirmation Letter Generated</div>
                                <?php endif; ?>
                                <?php if ($letter_emailed): ?>
                                <div class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[14px]">check_circle</span> Confirmation Letter Emailed</div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Show timestamp for completed stages from history -->
                            <?php 
                            $step_history = null;
                            foreach ($history as $entry) {
                                $hist_status = $entry['new_status'];
                                $hist_status_lower = strtolower($hist_status);
                                $step_status_lower = strtolower($step['status']);
                                
                                $matched = false;
                                if ($step_status_lower === 'applied' && $hist_status_lower === 'applied') {
                                    $matched = true;
                                } elseif ($step_status_lower === 'hr review' && in_array($hist_status_lower, ['hr round', 'hr review'])) {
                                    $matched = true;
                                } elseif ($step_status_lower === 'exam mail sent' && in_array($hist_status_lower, ['exam link sent', 'test link sent', 'exam mail sent', 'exam completed', 'test completed', 'test submitted', 'test passed', 'test failed', 'qualified', 'not qualified'])) {
                                    $matched = true;
                                } elseif ($step_status_lower === 'shortlisted' && in_array($hist_status_lower, ['shortlisted'])) {
                                    $matched = true;
                                } elseif ($step_status_lower === 'hod pending' && in_array($hist_status_lower, ['hod pending', 'hod approval pending', 'forwarded to hod'])) {
                                    $matched = true;
                                } elseif ($step_status_lower === 'hod approved' && in_array($hist_status_lower, ['hod approved', 'hod approval'])) {
                                    $matched = true;
                                } elseif ($step_status_lower === 'selected' && in_array($hist_status_lower, ['selected', 'hr selected'])) {
                                    $matched = true;
                                } elseif ($step_status_lower === 'confirmation letter sent' && $hist_status_lower === 'confirmation letter sent') {
                                    $matched = true;
                                } elseif ($step_status_lower === 'project assigned' && $hist_status_lower === 'project assigned') {
                                    $matched = true;
                                } elseif ($step_status_lower === 'active intern' && in_array($hist_status_lower, ['internship active', 'started', 'active intern', 'internship started'])) {
                                    $matched = true;
                                }
                                
                                if ($matched) {
                                    $step_history = $entry;
                                    break;
                                }
                            }
                            
                            if ($step_history) {
                                echo '<p class="text-xs text-slate-400 mt-1">' . formatTimestamp($step_history['created_at']) . '</p>';
                                if (!empty($step_history['notes'])) {
                                    echo '<p class="text-xs text-slate-600 mt-1 italic bg-slate-50 p-2 rounded border border-slate-100">"' . htmlspecialchars($step_history['notes']) . '"</p>';
                                }
                            }
                            

                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Rejected Status (if applicable) -->
                    <?php if ($current_status === 'Rejected'): ?>
                    <div class="flex items-start gap-4 relative">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold border-2 bg-red-500 text-white border-red-600 shadow-xl z-10 shrink-0">
                            <span class="material-symbols-outlined text-[24px]">cancel</span>
                        </div>
                        <div class="flex-1 pt-2">
                            <h4 class="font-bold text-red-700 text-base">Rejected</h4>
                            <p class="text-xs text-red-600 mt-0.5 font-semibold">Application was not successful</p>
                            <?php 
                            foreach ($history as $entry) {
                                    if ($entry['new_status'] === 'Rejected') {
                                    echo '<p class="text-xs text-slate-400 mt-1">' . formatTimestamp($entry['created_at']) . '</p>';
                                    if (!empty($entry['notes'])) {
                                        echo '<p class="text-xs text-red-600 mt-2 bg-red-50 p-3 rounded-lg border border-red-100">' . htmlspecialchars($entry['notes']) . '</p>';
                                    }
                                    break;
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Status History -->
        <?php if (count($history) > 0): ?>
        <div class="border-t border-slate-200 bg-slate-50 p-6">
            <h4 class="font-bold text-slate-700 text-sm mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">history</span>
                Complete History
            </h4>
            <div class="space-y-2">
                <?php foreach ($history as $entry): 
                    $entry_status = $entry['new_status'];
                    $entry_config = $status_config[$entry_status] ?? ['bg' => 'bg-slate-500', 'icon' => 'info'];
                ?>
                <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-100 hover:shadow-sm transition-shadow">
                    <div class="w-8 h-8 rounded-lg <?php echo $entry_config['bg']; ?> text-white flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[16px]"><?php echo $entry_config['icon']; ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-800 text-xs">
                            <?php if ($entry['old_status']): ?>
                                <?php echo htmlspecialchars($entry['old_status']); ?> → <?php echo htmlspecialchars($entry['new_status']); ?>
                            <?php else: ?>
                                Application Submitted
                            <?php endif; ?>
                        </p>
                        <p class="text-[10px] text-slate-500">
                            by <span class="font-semibold"><?php echo htmlspecialchars($entry['updated_by_name']); ?></span> • <?php echo formatTimestamp($entry['created_at']); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="border-t border-slate-200 bg-slate-50 p-6">
            <div class="text-center py-6 bg-white rounded-lg border border-dashed border-slate-200">
                <span class="material-symbols-outlined text-slate-300 text-4xl">history_toggle_off</span>
                <p class="text-sm text-slate-400 mt-2">No status updates yet</p>
                <p class="text-xs text-slate-400">Applied on <?php echo date('M d, Y', strtotime($applied_date)); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
