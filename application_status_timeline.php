<?php
// This file generates the status timeline UI for an application
// Usage: include this file and call renderStatusTimeline($application_id, $conn)

include_once "status_utils.php";

function renderStatusTimeline($application_id, $conn) {
    // Fetch application details with profile fallback
    $app_sql = "SELECT a.status, a.education_status, a.applied_date, a.test_status, a.test_submitted_date, a.confirmation_letter_path, a.user_id,
                       sp.education_status AS profile_edu_status
                FROM internship_applications a
                LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                WHERE a.id = $application_id LIMIT 1";
    $app_result = mysqli_query($conn, $app_sql);
    
    if (mysqli_num_rows($app_result) == 0) {
        echo '<p class="text-sm text-slate-500">Application not found.</p>';
        return;
    }
    
    $app = mysqli_fetch_assoc($app_result);
    $current_status = $app['status'];
    $education_status = !empty($app['education_status']) ? $app['education_status'] : (!empty($app['profile_edu_status']) ? $app['profile_edu_status'] : 'Pursuing');
    $is_pursuing = (strtolower($education_status) === 'pursuing' || strtolower($education_status) === 'currently pursuing');
    
    $applied_date = $app['applied_date'];
    $test_status = $app['test_status'] ?? 'Pending';
    $test_submitted_date = $app['test_submitted_date'] ?? null;
    $has_letter = !empty($app['confirmation_letter_path']);
    
    // Calculate test deadline (48 hours from application)
    $applied_time = strtotime($applied_date);
    $deadline_time = $applied_time + (48 * 60 * 60);
    $current_time = time();
    $time_remaining = $deadline_time - $current_time;
    $is_deadline_expired = ($time_remaining <= 0);
    $hours_left = floor($time_remaining / 3600);
    $minutes_left = floor(($time_remaining % 3600) / 60);
    
    // Build steps based on student type
    $steps = [
        [
            'id' => 'applied',
            'label' => 'Applied',
            'icon' => 'send',
            'color' => 'slate',
            'bg' => 'bg-slate-500'
        ],
        [
            'id' => 'test_completed',
            'label' => 'Test Completed',
            'icon' => 'quiz',
            'color' => 'purple',
            'bg' => 'bg-purple-500'
        ],
        [
            'id' => 'hr_review',
            'label' => 'HR Review',
            'icon' => 'manage_search',
            'color' => 'orange',
            'bg' => 'bg-orange-500'
        ]
    ];
    
    if ($is_pursuing) {
        $steps[] = [
            'id' => 'hod_approval',
            'label' => 'HOD Approval',
            'icon' => 'verified',
            'color' => 'cyan',
            'bg' => 'bg-cyan-500'
        ];
    }
    
    $steps[] = [
        'id' => 'selected_by_hr',
        'label' => 'Selected by HR',
        'icon' => 'check_circle',
        'color' => 'emerald',
        'bg' => 'bg-emerald-500'
    ];
    
    $steps[] = [
        'id' => 'confirmation_letter',
        'label' => 'Confirmation Letter Sent',
        'icon' => 'mail',
        'color' => 'lime',
        'bg' => 'bg-lime-500'
    ];

    // Determine the current step dynamically based on status (case-insensitive)
    $status_lc = strtolower($current_status);
    $test_lc = strtolower($test_status);
    $current_step_id = 'applied';

    if ($status_lc === 'applied') {
        if ($test_lc === 'completed') {
            $current_step_id = 'test_completed';
        } else {
            $current_step_id = 'applied';
        }
    } elseif ($status_lc === 'test completed') {
        $current_step_id = 'test_completed';
    } elseif (in_array($status_lc, ['hr round', 'hr review'])) {
        $current_step_id = 'hr_review';
    } elseif ($status_lc === 'hod approval pending') {
        $current_step_id = $is_pursuing ? 'hod_approval' : 'selected_by_hr';
    } elseif (in_array($status_lc, ['hod approved', 'hod_approved'])) {
        $current_step_id = 'selected_by_hr';
    } elseif ($status_lc === 'selected') {
        if ($has_letter) {
            $current_step_id = 'confirmation_letter_completed';
        } else {
            $current_step_id = 'confirmation_letter';
        }
    } elseif (in_array($status_lc, ['active intern', 'active_intern', 'internship started', 'started'])) {
        $current_step_id = 'confirmation_letter_completed';
    }

    // Find the index of the current step in the steps array
    $current_index = -1;
    foreach ($steps as $index => $step) {
        if ($step['id'] === $current_step_id) {
            $current_index = $index;
            break;
        }
    }

    foreach ($steps as $index => &$step) {
        if ($current_step_id === 'confirmation_letter_completed') {
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
                // Fallback: Default to first step being current
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

    // Status config for history
    $status_config = [
        'Applied' => ['bg' => 'bg-slate-500', 'icon' => 'send', 'color' => 'slate'],
        'Test Completed' => ['bg' => 'bg-purple-500', 'icon' => 'quiz', 'color' => 'purple'],
        'HR Round' => ['bg' => 'bg-orange-500', 'icon' => 'manage_search', 'color' => 'orange'],
        'HR Review' => ['bg' => 'bg-orange-500', 'icon' => 'manage_search', 'color' => 'orange'],
        'HOD Approval Pending' => ['bg' => 'bg-cyan-500', 'icon' => 'pending', 'color' => 'cyan'],
        'HOD Approved' => ['bg' => 'bg-cyan-500', 'icon' => 'verified', 'color' => 'cyan'],
        'Selected' => ['bg' => 'bg-emerald-500', 'icon' => 'check_circle', 'color' => 'emerald'],
        'Active Intern' => ['bg' => 'bg-lime-500', 'icon' => 'mail', 'color' => 'lime'],
        'Rejected' => ['bg' => 'bg-red-500', 'icon' => 'cancel', 'color' => 'red']
    ];

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
    } elseif ($current_status === 'Active Intern' || ($current_status === 'Selected' && $has_letter)) {
        $display_status = 'Confirmation Letter Sent';
    }
    
    $header_icon = $status_config[$current_status]['icon'] ?? 'info';
    if (($current_status === 'Selected' && $has_letter) || $current_status === 'Active Intern') {
        $header_icon = 'mail';
    }
    
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
                            
                            <!-- Show timestamp for completed stages from history -->
                            <?php 
                            $step_history = null;
                            foreach ($history as $entry) {
                                $hist_status = $entry['new_status'];
                                if ($step['id'] === 'applied' && $hist_status === 'Applied') {
                                    $step_history = $entry;
                                } elseif ($step['id'] === 'test_completed' && $hist_status === 'Test Completed') {
                                    $step_history = $entry;
                                } elseif ($step['id'] === 'hr_review' && in_array($hist_status, ['HR Round', 'HR Review'])) {
                                    $step_history = $entry;
                                } elseif ($step['id'] === 'hod_approval' && in_array($hist_status, ['HOD Approval Pending', 'HOD Approved'])) {
                                    $step_history = $entry;
                                } elseif ($step['id'] === 'selected_by_hr' && $hist_status === 'Selected') {
                                    $step_history = $entry;
                                } elseif ($step['id'] === 'confirmation_letter' && $hist_status === 'Active Intern') {
                                    $step_history = $entry;
                                }
                                if ($step_history) {
                                    break;
                                }
                            }
                            
                            if ($step_history) {
                                echo '<p class="text-xs text-slate-400 mt-1">' . formatTimestamp($step_history['created_at']) . '</p>';
                                if (!empty($step_history['notes'])) {
                                    echo '<p class="text-xs text-slate-600 mt-1 italic bg-slate-50 p-2 rounded border border-slate-100">"' . htmlspecialchars($step_history['notes']) . '"</p>';
                                }
                            }
                            
                            // Show test deadline info for Applied stage
                            if ($step['id'] === 'applied' && $is_current && $test_status !== 'Completed') {
                                if ($is_deadline_expired) {
                                    echo '<div class="mt-2 p-2 bg-red-50 border border-red-200 rounded-lg">';
                                    echo '<p class="text-xs font-bold text-red-700">⚠️ Test Deadline Expired</p>';
                                    echo '<p class="text-[10px] text-red-600 mt-0.5">The 48-hour test window has expired. Please contact HR.</p>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded-lg">';
                                    echo '<p class="text-xs font-bold text-amber-700">⏱️ Test Deadline</p>';
                                    echo '<p class="text-[10px] text-amber-600 mt-0.5">Complete within: ' . $hours_left . 'h ' . $minutes_left . 'm</p>';
                                    echo '<p class="text-[10px] text-amber-500 mt-0.5">Deadline: ' . date('M d, Y \a\t g:i A', $deadline_time) . '</p>';
                                    echo '</div>';
                                }
                            }
                            
                            // Show test submitted date for Test Completed stage
                            if ($step['id'] === 'test_completed' && $test_submitted_date && $is_completed) {
                                echo '<p class="text-xs text-slate-400 mt-1">Submitted: ' . formatTimestamp($test_submitted_date) . '</p>';
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
                <?php foreach ($history as $entry): ?>
                <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-100 hover:shadow-sm transition-shadow">
                    <div class="w-8 h-8 rounded-lg <?php echo $status_config[$entry['new_status']]['bg'] ?? 'bg-slate-500'; ?> text-white flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[16px]"><?php echo $status_config[$entry['new_status']]['icon'] ?? 'info'; ?></span>
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
