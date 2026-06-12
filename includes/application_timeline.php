<?php
// Application Timeline Component
// Usage: include this file and call renderApplicationTimeline($app_id, $current_status, $education_status)

function renderApplicationTimeline($app_id, $current_status, $education_status) {
    global $conn;
    include_once __DIR__ . '/status_helper.php';
    
    // Fetch status history
    $history_sql = "SELECT * FROM application_status_history WHERE application_id = $app_id ORDER BY created_at ASC";
    $history_result = mysqli_query($conn, $history_sql);
    $history = [];
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history[] = $row;
    }
    
    // Get workflow steps
    $steps = getWorkflowSteps($education_status);
    
    ?>
    <div class="bg-gradient-to-br from-slate-50 to-blue-50/30 rounded-xl p-6 border border-slate-200">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-blue-600 text-xl">timeline</span>
            <h3 class="font-bold text-slate-800 text-sm">Application Progress</h3>
        </div>
        
        <div class="relative">
            <!-- Timeline Line -->
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-slate-200"></div>
            
            <!-- Timeline Steps -->
            <div class="space-y-4">
                <?php foreach ($steps as $index => $step): 
                    $step_status = $step['status'];
                    $step_label = $step['label'];
                    $step_icon = $step['icon'];
                    
                    $state = isStatusActive($current_status, $step_status, $history);
                    
                    // Find history entry for this step
                    $step_history = null;
                    foreach ($history as $h) {
                        if ($h['new_status'] === $step_status) {
                            $step_history = $h;
                            break;
                        }
                    }
                    
                    // Determine styling based on state
                    $circle_class = 'bg-slate-200 text-slate-400';
                    $text_class = 'text-slate-400';
                    $line_class = 'bg-slate-200';
                    
                    if ($state === 'completed') {
                        $circle_class = 'bg-emerald-500 text-white';
                        $text_class = 'text-slate-700';
                        $line_class = 'bg-emerald-500';
                    } elseif ($state === 'current') {
                        $circle_class = 'bg-blue-600 text-white ring-4 ring-blue-100';
                        $text_class = 'text-blue-700 font-bold';
                    }
                ?>
                <div class="relative flex items-start gap-4 pl-0">
                    <!-- Circle Icon -->
                    <div class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center shrink-0 <?php echo $circle_class; ?> transition-all duration-300">
                        <span class="material-symbols-outlined text-[16px]"><?php echo $step_icon; ?></span>
                    </div>
                    
                    <!-- Step Content -->
                    <div class="flex-1 pb-2">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-sm <?php echo $text_class; ?> transition-colors"><?php echo $step_label; ?></h4>
                            <?php if ($state === 'current'): ?>
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-bold rounded uppercase tracking-wide">Current</span>
                            <?php elseif ($state === 'completed'): ?>
                                <span class="material-symbols-outlined text-emerald-500 text-[18px]">check_circle</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($step_history): ?>
                            <p class="text-xs text-slate-500 mt-1">
                                <?php echo date('M d, Y • g:i A', strtotime($step_history['created_at'])); ?>
                            </p>
                            <?php if (!empty($step_history['notes'])): ?>
                                <p class="text-xs text-slate-600 mt-1 italic">"<?php echo htmlspecialchars($step_history['notes']); ?>"</p>
                            <?php endif; ?>
                        <?php elseif ($state === 'current'): ?>
                            <p class="text-xs text-blue-600 mt-1">In progress...</p>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 mt-1">Pending</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Status History Details (Collapsible) -->
        <?php if (count($history) > 0): ?>
        <div class="mt-6 pt-4 border-t border-slate-200">
            <button onclick="toggleHistory<?php echo $app_id; ?>()" class="text-xs text-blue-600 hover:text-blue-700 font-semibold flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">history</span>
                <span id="history-toggle-text-<?php echo $app_id; ?>">View Full History</span>
            </button>
            <div id="history-details-<?php echo $app_id; ?>" class="hidden mt-3 space-y-2">
                <?php foreach (array_reverse($history) as $h): 
                    $badge = getStatusBadge($h['new_status']);
                ?>
                <div class="flex items-start gap-3 text-xs bg-white rounded-lg p-3 border border-slate-100">
                    <span class="material-symbols-outlined text-slate-400 text-[16px] shrink-0"><?php echo $badge['icon']; ?></span>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-700">Status changed to:</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?php echo $badge['color']; ?>"><?php echo $badge['label']; ?></span>
                        </div>
                        <p class="text-slate-500 mt-1"><?php echo date('M d, Y • g:i A', strtotime($h['created_at'])); ?></p>
                        <?php if (!empty($h['notes'])): ?>
                            <p class="text-slate-600 mt-1 italic">"<?php echo htmlspecialchars($h['notes']); ?>"</p>
                        <?php endif; ?>
                        <p class="text-slate-400 mt-1">Updated by: <?php echo ucfirst($h['updated_by_role']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
        function toggleHistory<?php echo $app_id; ?>() {
            const details = document.getElementById('history-details-<?php echo $app_id; ?>');
            const text = document.getElementById('history-toggle-text-<?php echo $app_id; ?>');
            if (details.classList.contains('hidden')) {
                details.classList.remove('hidden');
                text.textContent = 'Hide Full History';
            } else {
                details.classList.add('hidden');
                text.textContent = 'View Full History';
            }
        }
        </script>
        <?php endif; ?>
    </div>
    <?php
}
?>
