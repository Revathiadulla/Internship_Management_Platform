<?php
// This file generates the status timeline UI for an application
// Usage: include this file and call renderStatusTimeline($application_id, $conn)

include_once "status_utils.php";

function renderStatusTimeline($application_id, $conn) {
    // Fetch application details
    $app_sql = "SELECT status, education_status, applied_date, test_status, test_submitted_date FROM internship_applications WHERE id = $application_id LIMIT 1";
    $app_result = mysqli_query($conn, $app_sql);
    
    if (mysqli_num_rows($app_result) == 0) {
        echo '<p class="text-sm text-slate-500">Application not found.</p>';
        return;
    }
    
    $app = mysqli_fetch_assoc($app_result);
    $current_status = $app['status'];
    $education_status = $app['education_status'] ?? 'Pursuing';
    $applied_date = $app['applied_date'];
    $test_status = $app['test_status'] ?? 'Pending';
    $test_submitted_date = $app['test_submitted_date'] ?? null;
    
    // Calculate test deadline (48 hours from application)
    $applied_time = strtotime($applied_date);
    $deadline_time = $applied_time + (48 * 60 * 60);
    $current_time = time();
    $time_remaining = $deadline_time - $current_time;
    $is_deadline_expired = ($time_remaining <= 0);
    $hours_left = floor($time_remaining / 3600);
    $minutes_left = floor(($time_remaining % 3600) / 60);
    
    // Build workflow based on education status
    $workflow = ['Applied', 'Test Completed', 'HR Round'];
    if ($education_status === 'Pursuing') {
        $workflow[] = 'HOD Approved';
    }
    $workflow[] = 'Selected';
    
    // Find current step
    $current_step = array_search($current_status, $workflow);
    if ($current_step === false && $current_status === 'Rejected') {
        $current_step = count($workflow); // Rejected is terminal
    }
    
    // Status colors and icons
    $status_config = [
        'Applied' => ['bg' => 'bg-slate-500', 'icon' => 'send', 'color' => 'slate'],
        'Test Completed' => ['bg' => 'bg-purple-500', 'icon' => 'quiz', 'color' => 'purple'],
        'HR Round' => ['bg' => 'bg-orange-500', 'icon' => 'manage_search', 'color' => 'orange'],
        'HOD Approved' => ['bg' => 'bg-cyan-500', 'icon' => 'verified', 'color' => 'cyan'],
        'Selected' => ['bg' => 'bg-emerald-500', 'icon' => 'check_circle', 'color' => 'emerald'],
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
    
    ?>
    <div class="bg-gradient-to-br from-white to-slate-50 rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider opacity-90 mb-1">Application Status</p>
                    <h3 class="text-2xl font-extrabold tracking-tight">
                        <?php echo htmlspecialchars($current_status); ?>
                    </h3>
                </div>
                <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                    <span class="material-symbols-outlined text-4xl"><?php echo $status_config[$current_status]['icon'] ?? 'info'; ?></span>
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
                    <?php foreach ($workflow as $index => $stage): 
                        $is_completed = ($index < $current_step) || ($current_status === $stage);
                        $is_current = ($current_status === $stage);
                        $is_rejected = ($current_status === 'Rejected');
                        $config = $status_config[$stage];
                        
                        if ($is_rejected && $index > 0) {
                            $circle_class = 'bg-slate-200 text-slate-400 border-slate-300';
                            $line_class = 'bg-slate-200';
                        } elseif ($is_completed) {
                            $circle_class = $config['bg'] . ' text-white border-' . $config['color'] . '-600 shadow-lg';
                            $line_class = 'bg-gradient-to-b from-' . $config['color'] . '-500 to-' . $config['color'] . '-600';
                        } elseif ($is_current) {
                            $circle_class = $config['bg'] . ' text-white border-' . $config['color'] . '-600 shadow-xl ring-4 ring-' . $config['color'] . '-100 animate-pulse';
                            $line_class = 'bg-slate-200';
                        } else {
                            $circle_class = 'bg-slate-200 text-slate-400 border-slate-300';
                            $line_class = 'bg-slate-200';
                        }
                    ?>
                    <div class="flex items-start gap-4 relative">
                        <!-- Vertical Line -->
                        <?php if ($index < count($workflow) - 1): ?>
                        <div class="absolute left-6 top-14 w-0.5 h-12 <?php echo $line_class; ?> transition-all"></div>
                        <?php endif; ?>
                        
                        <!-- Circle -->
                        <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold border-2 <?php echo $circle_class; ?> transition-all z-10 shrink-0">
                            <?php if ($is_completed && !$is_current): ?>
                                <span class="material-symbols-outlined text-[24px]">check</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-[24px]"><?php echo $config['icon']; ?></span>
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
                            foreach ($history as $entry) {
                                if ($entry['new_status'] === $stage) {
                                    echo '<p class="text-xs text-slate-400 mt-1">' . formatTimestamp($entry['created_at']) . '</p>';
                                    if (!empty($entry['notes'])) {
                                        echo '<p class="text-xs text-slate-600 mt-1 italic bg-slate-50 p-2 rounded border border-slate-100">"' . htmlspecialchars($entry['notes']) . '"</p>';
                                    }
                                    break;
                                }
                            }
                            
                            // Show test deadline info for Applied stage
                            if ($stage === 'Applied' && $is_current && $test_status !== 'Completed') {
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
                            if ($stage === 'Test Completed' && $test_submitted_date && $is_completed) {
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
