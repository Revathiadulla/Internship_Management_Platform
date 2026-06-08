<?php
// Status color and badge mapping function
function getStatusBadge($status) {
    $badges = [
        'Applied' => ['color' => 'bg-blue-50 text-blue-700 border-blue-200', 'icon' => 'send', 'label' => 'Applied'],
        'Test Completed' => ['color' => 'bg-purple-50 text-purple-700 border-purple-200', 'icon' => 'task_alt', 'label' => 'Test Completed'],
        'HR Round' => ['color' => 'bg-indigo-50 text-indigo-700 border-indigo-200', 'icon' => 'groups', 'label' => 'HR Round'],
        'HOD Approval Pending' => ['color' => 'bg-amber-50 text-amber-700 border-amber-200', 'icon' => 'pending', 'label' => 'HOD Approval Pending'],
        'HOD Approved' => ['color' => 'bg-teal-50 text-teal-700 border-teal-200', 'icon' => 'verified', 'label' => 'HOD Approved'],
        'Selected' => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'check_circle', 'label' => 'Selected'],
        'Rejected' => ['color' => 'bg-red-50 text-red-700 border-red-200', 'icon' => 'cancel', 'label' => 'Rejected'],
        'Under Review' => ['color' => 'bg-slate-50 text-slate-700 border-slate-200', 'icon' => 'rate_review', 'label' => 'Under Review'],
        'Interview Scheduled' => ['color' => 'bg-cyan-50 text-cyan-700 border-cyan-200', 'icon' => 'event', 'label' => 'Interview Scheduled'],
        'Offer Sent' => ['color' => 'bg-lime-50 text-lime-700 border-lime-200', 'icon' => 'mail', 'label' => 'Offer Sent'],
        'Onboarding Completed' => ['color' => 'bg-green-50 text-green-700 border-green-200', 'icon' => 'done_all', 'label' => 'Onboarding Completed'],
        
        // Legacy statuses (backward compatibility)
        'Pending' => ['color' => 'bg-gray-50 text-gray-700 border-gray-200', 'icon' => 'schedule', 'label' => 'Pending'],
        'HR Screening' => ['color' => 'bg-blue-50 text-blue-700 border-blue-200', 'icon' => 'search', 'label' => 'HR Screening'],
        'HR Review' => ['color' => 'bg-indigo-50 text-indigo-700 border-indigo-200', 'icon' => 'rate_review', 'label' => 'HR Review'],
        'HR Approved' => ['color' => 'bg-teal-50 text-teal-700 border-teal-200', 'icon' => 'thumb_up', 'label' => 'HR Approved'],
        'Waiting for HOD Approval' => ['color' => 'bg-amber-50 text-amber-700 border-amber-200', 'icon' => 'hourglass_empty', 'label' => 'Waiting for HOD'],
        'Approved' => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'check_circle', 'label' => 'Approved'],
        'Accepted' => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'check_circle', 'label' => 'Accepted'],
        'Started' => ['color' => 'bg-green-50 text-green-700 border-green-200', 'icon' => 'play_circle', 'label' => 'Started'],
        'Internship Started' => ['color' => 'bg-green-50 text-green-700 border-green-200', 'icon' => 'play_circle', 'label' => 'Internship Started'],
        'Active Intern' => ['color' => 'bg-green-50 text-green-700 border-green-200', 'icon' => 'work', 'label' => 'Active Intern'],
    ];
    
    return isset($badges[$status]) ? $badges[$status] : ['color' => 'bg-gray-50 text-gray-700 border-gray-200', 'icon' => 'info', 'label' => $status];
}

// Get workflow steps based on education status
function getWorkflowSteps($education_status) {
    $steps = [
        ['status' => 'Applied', 'label' => 'Applied', 'icon' => 'send'],
        ['status' => 'Test Completed', 'label' => 'Test Completed', 'icon' => 'quiz'],
        ['status' => 'HR Review', 'label' => 'HR Review', 'icon' => 'manage_search'],
    ];
    
    if (strtolower($education_status) === 'pursuing') {
        $steps[] = ['status' => 'HOD Approval', 'label' => 'HOD Approval', 'icon' => 'verified'];
    }
    
    $steps[] = ['status' => 'Selected by HR', 'label' => 'Selected by HR', 'icon' => 'check_circle'];
    $steps[] = ['status' => 'Confirmation Letter Sent', 'label' => 'Confirmation Letter Sent', 'icon' => 'mail'];
    
    return $steps;
}

// Check if status is active in workflow
function isStatusActive($current_status, $step_status, $all_history) {
    $status_map = [
        'applied' => ['applied'],
        'test completed' => ['test completed'],
        'hr review' => ['hr round', 'hr review'],
        'hod approval' => ['hod approval pending', 'hod approved'],
        'selected by hr' => ['selected'],
        'confirmation letter sent' => ['active intern']
    ];
    
    $current_status_lower = strtolower($current_status);
    $step_status_lower = strtolower($step_status);
    
    // Check if current status matches
    $current_matched = false;
    if (isset($status_map[$step_status_lower])) {
        if (in_array($current_status_lower, $status_map[$step_status_lower])) {
            $current_matched = true;
        }
    } else {
        if ($current_status_lower === $step_status_lower) {
            $current_matched = true;
        }
    }
    
    if ($current_matched) {
        return 'current';
    }
    
    // Check if this status was completed in history
    foreach ($all_history as $history) {
        $hist_status_lower = strtolower($history['new_status']);
        if (isset($status_map[$step_status_lower])) {
            if (in_array($hist_status_lower, $status_map[$step_status_lower])) {
                return 'completed';
            }
        } else {
            if ($hist_status_lower === $step_status_lower) {
                return 'completed';
            }
        }
    }
    
    return 'pending';
}
