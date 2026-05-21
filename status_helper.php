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
    $base_steps = [
        ['status' => 'Applied', 'label' => 'Applied', 'icon' => 'send'],
        ['status' => 'Test Completed', 'label' => 'Test Completed', 'icon' => 'task_alt'],
        ['status' => 'HR Round', 'label' => 'HR Round', 'icon' => 'groups'],
    ];
    
    if ($education_status === 'Pursuing') {
        $base_steps[] = ['status' => 'HOD Approval Pending', 'label' => 'HOD Approval', 'icon' => 'pending'];
        $base_steps[] = ['status' => 'HOD Approved', 'label' => 'HOD Approved', 'icon' => 'verified'];
    }
    
    $base_steps[] = ['status' => 'Selected', 'label' => 'Selected', 'icon' => 'check_circle'];
    
    return $base_steps;
}

// Check if status is active in workflow
function isStatusActive($current_status, $step_status, $all_history) {
    // Check if current status matches
    if ($current_status === $step_status) {
        return 'current';
    }
    
    // Check if this status was completed in history
    foreach ($all_history as $history) {
        if ($history['new_status'] === $step_status) {
            return 'completed';
        }
    }
    
    return 'pending';
}
