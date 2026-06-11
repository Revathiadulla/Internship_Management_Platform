<?php
require_once __DIR__ . '/includes/workflow_helper.php';

// Status color and badge mapping function
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $status_value = trim((string) $status);
        $status_key = $status_value;
        if (in_array(strtolower($status_value), ['confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent'])) {
            $status_key = 'Selected';
        }

        $badges = [
            'Applied'                  => ['color' => 'bg-blue-50 text-blue-700 border-blue-200',       'icon' => 'send',               'label' => 'Applied'],
            'Verified'                 => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'fact_check',      'label' => 'Verified'],
            'HR Review'                => ['color' => 'bg-indigo-50 text-indigo-700 border-indigo-200',  'icon' => 'rate_review',        'label' => 'HR Review'],
            'Shortlisted'              => ['color' => 'bg-amber-50 text-amber-700 border-amber-200',     'icon' => 'star',               'label' => 'Shortlisted'],
            'Exam Mail Sent'           => ['color' => 'bg-purple-50 text-purple-700 border-purple-200',  'icon' => 'mail',               'label' => 'Exam Sent'],
            'HOD Pending'              => ['color' => 'bg-amber-50 text-amber-700 border-amber-200',     'icon' => 'hourglass_empty',    'label' => 'HOD Pending'],
            'HOD Approval Pending'     => ['color' => 'bg-amber-50 text-amber-700 border-amber-200',     'icon' => 'pending',            'label' => 'HOD Pending'],
            'Forwarded to HOD'         => ['color' => 'bg-amber-50 text-amber-700 border-amber-200',     'icon' => 'pending',            'label' => 'HOD Pending'],
            'HOD Approved'             => ['color' => 'bg-teal-50 text-teal-700 border-teal-200',        'icon' => 'verified',           'label' => 'HOD Approved'],
            'Selected'                 => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'check_circle',    'label' => 'Selected'],
            'Project Assigned'         => ['color' => 'bg-blue-50 text-blue-700 border-blue-200',        'icon' => 'assignment',         'label' => 'Project Assigned'],
            'Active Intern'            => ['color' => 'bg-green-50 text-green-700 border-green-200',     'icon' => 'work',               'label' => 'Active Intern'],
            'Completed'                => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'task_alt',        'label' => 'Completed'],
            'Talent Pool'              => ['color' => 'bg-rose-50 text-rose-700 border-rose-200',        'icon' => 'groups',             'label' => 'Talent Pool'],
            'Rejected'                 => ['color' => 'bg-red-50 text-red-700 border-red-200',           'icon' => 'cancel',             'label' => 'Rejected'],
            'HOD Rejected'             => ['color' => 'bg-red-50 text-red-700 border-red-200',           'icon' => 'cancel',             'label' => 'HOD Rejected'],
 
            // Legacy compatibility — old test/exam statuses map to nearest new equivalent
            'Exam Completed'           => ['color' => 'bg-purple-50 text-purple-700 border-purple-200',  'icon' => 'mail',               'label' => 'Exam Mail Sent'],
            'Test Completed'           => ['color' => 'bg-purple-50 text-purple-700 border-purple-200',  'icon' => 'mail',               'label' => 'Exam Mail Sent'],
            'Test Submitted'           => ['color' => 'bg-purple-50 text-purple-700 border-purple-200',  'icon' => 'mail',               'label' => 'Exam Mail Sent'],
            'Test Passed'              => ['color' => 'bg-purple-50 text-purple-700 border-purple-200',  'icon' => 'mail',               'label' => 'Exam Mail Sent'],
            'Test Failed'              => ['color' => 'bg-red-50 text-red-700 border-red-200',           'icon' => 'cancel',             'label' => 'Rejected'],
 
            // Other legacy compatibility
            'Under Review'             => ['color' => 'bg-slate-50 text-slate-700 border-slate-200',     'icon' => 'rate_review',        'label' => 'Under Review'],
            'Interview Scheduled'      => ['color' => 'bg-cyan-50 text-cyan-700 border-cyan-200',        'icon' => 'event',              'label' => 'Interview Scheduled'],
            'Offer Sent'               => ['color' => 'bg-lime-50 text-lime-700 border-lime-200',        'icon' => 'mail',               'label' => 'Offer Sent'],
            'Onboarding Completed'     => ['color' => 'bg-green-50 text-green-700 border-green-200',     'icon' => 'done_all',           'label' => 'Onboarding Completed'],
            'HR Round'                 => ['color' => 'bg-indigo-50 text-indigo-700 border-indigo-200',  'icon' => 'groups',             'label' => 'HR Round'],
            'Pending'                  => ['color' => 'bg-gray-50 text-gray-700 border-gray-200',        'icon' => 'schedule',           'label' => 'Pending'],
            'HR Screening'             => ['color' => 'bg-blue-50 text-blue-700 border-gray-200',        'icon' => 'search',             'label' => 'HR Screening'],
            'HR Approved'              => ['color' => 'bg-teal-50 text-teal-700 border-gray-200',        'icon' => 'thumb_up',           'label' => 'HR Approved'],
            'Waiting for HOD Approval' => ['color' => 'bg-amber-50 text-amber-700 border-amber-200',     'icon' => 'hourglass_empty',    'label' => 'Waiting for HOD'],
            'Approved'                 => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'check_circle',    'label' => 'Approved'],
            'Accepted'                 => ['color' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'check_circle',    'label' => 'Accepted'],
            'Started'                  => ['color' => 'bg-green-50 text-green-700 border-green-200',     'icon' => 'play_circle',        'label' => 'Started'],
            'Internship Started'       => ['color' => 'bg-green-50 text-green-700 border-green-200',     'icon' => 'play_circle',        'label' => 'Internship Started'],
        ];
 
        return isset($badges[$status_key]) ? $badges[$status_key] : ['color' => 'bg-gray-50 text-gray-700 border-gray-200', 'icon' => 'info', 'label' => $status_value];
    }
}
 
// Get workflow steps based on education status and student type
if (!function_exists('getWorkflowSteps')) {
    function getWorkflowSteps($education_status, $student_type = '') {
        $is_passed_out = is_passed_out_student($education_status, $student_type);
        $is_pursuing = !$is_passed_out;

        // Official workflow used by the UI timelines
        $steps = [
            ['status' => 'Applied',         'label' => 'Applied',         'icon' => 'send'],
            ['status' => 'HR Review',       'label' => 'HR Review',       'icon' => 'rate_review'],
            ['status' => 'Exam Mail Sent',  'label' => 'Exam Mail Sent',  'icon' => 'mail'],
            ['status' => 'Selected',        'label' => 'Selected',        'icon' => 'how_to_reg'],
            ['status' => 'Project Assigned','label' => 'Project Assigned','icon' => 'assignment'],
            ['status' => 'Completed',       'label' => 'Completed',       'icon' => 'task_alt'],
        ];

        return $steps;
    }
}

// Check if status is active in workflow
if (!function_exists('isStatusActive')) {
    function isStatusActive($current_status, $step_status, $all_history) {
        $status_map = [
            'applied'       => ['applied'],
            'hr review'     => ['hr review', 'hr round'],
            'shortlisted'   => ['shortlisted'],
            'exam mail sent'=> ['exam mail sent', 'test link sent', 'exam link sent',
                                // legacy exam statuses treated as exam mail sent stage
                                'exam completed', 'test completed', 'test submitted', 'test passed', 'test failed', 'qualified', 'not qualified'],
            'hod pending'   => ['hod pending', 'hod approval pending', 'forwarded to hod'],
            'hod approved'  => ['hod approved', 'hod approval'],
            'selected'      => ['selected', 'hr selected', 'confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent'],
            'project assigned' => ['project assigned'],
            'active intern' => ['internship active', 'started', 'active intern', 'internship started']
        ];

        $current_status_lower = strtolower($current_status);
        $step_status_lower    = strtolower($step_status);

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
}
?>
