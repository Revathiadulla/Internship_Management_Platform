<?php
// Status badge class mapping
function getStatusBadgeClass($status) {
    $status_lower = strtolower($status);
    // Green statuses (positive/completed)
    if (in_array($status_lower, ['selected', 'hod approved', 'offer sent', 'onboarding completed'])) {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    // Blue/Cyan statuses (in progress)
    if (in_array($status_lower, ['applied', 'test completed', 'interview scheduled', 'hr round'])) {
        return 'bg-blue-50 text-blue-700 border-blue-200';
    }
    // Red statuses (rejected)
    if (in_array($status_lower, ['rejected'])) {
        return 'bg-red-50 text-red-700 border-red-200';
    }
    // Default gray
    return 'bg-slate-50 text-slate-700 border-slate-200';
}

// Verification badge class
function getVerificationBadgeClass($verification_status) {
    $status_lower = strtolower($verification_status);
    if ($status_lower === 'verified') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    if ($status_lower === 'rejected') {
        return 'bg-red-50 text-red-700 border-red-200';
    }
    return 'bg-slate-50 text-slate-700 border-slate-200';
}

// Status icon mapping
function getStatusIcon($status) {
    $status_lower = strtolower($status);
    $map = [
        'selected' => 'check_circle',
        'hod approved' => 'check_circle',
        'onboarding completed' => 'check_circle',
        'applied' => 'send',
        'test completed' => 'quiz',
        'hr round' => 'manage_search',
        'under review' => 'manage_search',
        'hr approved' => 'pending_actions',
        'hod approval pending' => 'pending_actions',
        'interview scheduled' => 'event',
        'offer sent' => 'mail',
        'rejected' => 'cancel',
    ];
    return $map[$status_lower] ?? 'info';
}

// Workflow steps based on education status
function getWorkflowSteps($education_status) {
    $base_steps = [
        ['status' => 'Applied', 'label' => 'Applied', 'icon' => 'send'],
        ['status' => 'Test Completed', 'label' => 'Test Completed', 'icon' => 'quiz'],
        ['status' => 'HR Round', 'label' => 'HR Round', 'icon' => 'manage_search'],
    ];
    if ($education_status === 'Pursuing') {
        $base_steps[] = ['status' => 'HOD Approved', 'label' => 'HOD Approved', 'icon' => 'verified'];
    }
    $base_steps[] = ['status' => 'Selected', 'label' => 'Selected', 'icon' => 'check_circle'];
    return $base_steps;
}

// Get current step index in workflow
function getCurrentStepIndex($current_status, $workflow_steps) {
    foreach ($workflow_steps as $index => $step) {
        if (strtolower($step['status']) === strtolower($current_status)) {
            return $index;
        }
    }
    return -1;
}

// Format timestamp for display
function formatTimestamp($timestamp) {
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($date);
    if ($diff->days == 0) {
        if ($diff->h == 0) {
            return $diff->i . ' minutes ago';
        }
        return $diff->h . ' hours ago';
    } elseif ($diff->days == 1) {
        return 'Yesterday at ' . $date->format('g:i A');
    } elseif ($diff->days < 7) {
        return $diff->days . ' days ago';
    } else {
        return $date->format('M d, Y \a\t g:i A');
    }
}
?>
<<<<<<< HEAD
// Status badge color mapping function
function getStatusBadgeClass($status) {
    $status_lower = strtolower($status);
    
    // Green statuses (positive/completed)
    if (in_array($status_lower, ['selected', 'hod approved'])) {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    
    // Blue statuses (in progress)
    if (in_array($status_lower, ['applied', 'test completed', 'hr round'])) {
=======
function getStatusBadgeClass($status) {

    $status_lower = strtolower($status);
    
    // Green statuses (positive/completed)
    if (in_array($status_lower, ['selected', 'hod approved', 'offer sent', 'onboarding completed'])) {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    
    // Blue/Cyan statuses (in progress)
    if (in_array($status_lower, ['applied', 'test completed', 'interview scheduled', 'hr round'])) {
>>>>>>> dd7d2df (Add HR Mentor Company modules)
        return 'bg-blue-50 text-blue-700 border-blue-200';
    }
    
    // Red statuses (rejected)
    if (in_array($status_lower, ['rejected'])) {
        return 'bg-red-50 text-red-700 border-red-200';
    }
    
    // Default gray
    return 'bg-slate-50 text-slate-700 border-slate-200';
}

<<<<<<< HEAD
=======

function getVerificationBadgeClass($verification_status) {
    $status_lower = strtolower($verification_status);
    if ($status_lower === 'verified') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    if ($status_lower === 'rejected') {
        return 'bg-red-50 text-red-700 border-red-200';
    }
    return 'bg-slate-50 text-slate-700 border-slate-200';
}

>>>>>>> dd7d2df (Add HR Mentor Company modules)
// Get status icon
function getStatusIcon($status) {
    $status_lower = strtolower($status);
    
    if (in_array($status_lower, ['selected', 'hod approved', 'onboarding completed'])) {
        return 'check_circle';
    }
    
    if (in_array($status_lower, ['applied'])) {
        return 'send';
    }
    
    if (in_array($status_lower, ['test completed'])) {
        return 'quiz';
    }
    
    if (in_array($status_lower, ['hr round', 'under review'])) {
        return 'manage_search';
    }
    
    if (in_array($status_lower, ['hr approved', 'hod approval pending'])) {
        return 'pending_actions';
    }
    
    if (in_array($status_lower, ['interview scheduled'])) {
        return 'event';
    }
    
    if (in_array($status_lower, ['offer sent'])) {
        return 'mail';
    }
    
    if (in_array($status_lower, ['rejected'])) {
        return 'cancel';
    }
    
    return 'info';
}

// Get workflow steps based on education status
function getWorkflowSteps($education_status) {
    $base_steps = [
        ['status' => 'Applied', 'label' => 'Applied', 'icon' => 'send'],
        ['status' => 'Test Completed', 'label' => 'Test Completed', 'icon' => 'quiz'],
        ['status' => 'HR Round', 'label' => 'HR Round', 'icon' => 'manage_search'],
    ];
    
    // Add HOD Approved step only for Pursuing students
    if ($education_status === 'Pursuing') {
        $base_steps[] = ['status' => 'HOD Approved', 'label' => 'HOD Approved', 'icon' => 'verified'];
    }
    
    $base_steps[] = ['status' => 'Selected', 'label' => 'Selected', 'icon' => 'check_circle'];
    
    return $base_steps;
}

// Get current step index in workflow
function getCurrentStepIndex($current_status, $workflow_steps) {
    foreach ($workflow_steps as $index => $step) {
        if (strtolower($step['status']) === strtolower($current_status)) {
            return $index;
        }
    }
    return -1;
}

// Format timestamp for display
function formatTimestamp($timestamp) {
    $date = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days == 0) {
        if ($diff->h == 0) {
            return $diff->i . ' minutes ago';
        }
        return $diff->h . ' hours ago';
    } elseif ($diff->days == 1) {
        return 'Yesterday at ' . $date->format('g:i A');
    } elseif ($diff->days < 7) {
        return $diff->days . ' days ago';
    } else {
        return $date->format('M d, Y \a\t g:i A');
    }
}
