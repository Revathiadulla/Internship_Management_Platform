<?php
// Status utility functions for badge classes, icons, workflow steps, and timestamp formatting.

if (!function_exists('formatStatusLabel')) {
    function formatStatusLabel($status) {
        if (empty($status)) return '';
        $status_str = (string)$status;
        
        // Remove "APPLICATION STATUS:" or "Application Status:" prefix (case-insensitive)
        $status_str = preg_replace('/^\s*application\s+status\s*:\s*/i', '', $status_str);
        
        // Replace underscores with spaces
        $status_str = str_replace('_', ' ', $status_str);
        
        // Trim spaces
        $status_str = trim($status_str);
        
        // Words to uppercase/capitalize properly (e.g. hr review -> Hr Review)
        $status_str = ucwords(strtolower($status_str));
        
        // Convert specific abbreviations to full uppercase
        $words = explode(' ', $status_str);
        $abbreviations = ['Hr' => 'HR', 'Hod' => 'HOD', 'Pan' => 'PAN', 'Smtp' => 'SMTP', 'Id' => 'ID'];
        foreach ($words as &$word) {
            if (isset($abbreviations[$word])) {
                $word = $abbreviations[$word];
            }
        }
        return implode(' ', $words);
    }
}

if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        $status_lower = strtolower(trim((string) $status));
        if (in_array($status_lower, ['confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent'])) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        // Green statuses (positive/completed)
        if (in_array($status_lower, ['selected', 'hod approved', 'approved', 'onboarding completed', 'active intern', 'completed', 'talent pool', 'verified'])) {
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        }
        // Yellow/Orange statuses (pending/awaiting decision)
        if (in_array($status_lower, ['pending', 'hod pending', 'hod approval pending', 'approval pending', 'forwarded to hod', 'shortlisted'])) {
            return 'bg-amber-50 text-amber-700 border-amber-200';
        }
        // Purple status for exam mail
        if (in_array($status_lower, ['exam mail sent', 'test link sent', 'exam link sent'])) {
            return 'bg-purple-50 text-purple-700 border-purple-200';
        }
        // Blue statuses (in progress)
        if (in_array($status_lower, ['applied', 'interview scheduled', 'hr round', 'hr review', 'project assigned'])) {
            return 'bg-blue-50 text-blue-700 border-blue-200';
        }
        // Red statuses (rejected)
        if (in_array($status_lower, ['rejected', 'hod rejected', 'not qualified'])) {
            return 'bg-red-50 text-red-700 border-red-200';
        }
        // Default gray
        return 'bg-slate-50 text-slate-700 border-slate-200';
    }
}

if (!function_exists('getVerificationBadgeClass')) {
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
}

if (!function_exists('getStatusIcon')) {
    function getStatusIcon($status) {
        $status_lower = strtolower(trim((string) $status));
        if (in_array($status_lower, ['confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent'])) {
            return 'check_circle';
        }
        $map = [
            'selected'             => 'check_circle',
            'hod approved'         => 'check_circle',
            'onboarding completed' => 'check_circle',
            'applied'              => 'send',
            'hr round'             => 'manage_search',
            'hr review'            => 'rate_review',
            'under review'         => 'manage_search',
            'hr approved'          => 'pending_actions',
            'hod pending'          => 'hourglass_empty',
            'hod approval pending' => 'pending_actions',
            'interview scheduled'  => 'event',
            'rejected'             => 'cancel',
            'hod rejected'         => 'cancel',
            'test link sent'       => 'mail',
            'exam mail sent'       => 'mail',
            'shortlisted'          => 'star',
            'project assigned'     => 'assignment',
            'active intern'        => 'work',
            'verified'             => 'fact_check'
        ];
        return $map[$status_lower] ?? 'info';
    }
}

require_once __DIR__ . '/includes/workflow_helper.php';

if (!function_exists('getWorkflowSteps')) {
    function getWorkflowSteps($education_status, $student_type = '') {
        $is_passed_out = is_passed_out_student($education_status, $student_type);
        $is_pursuing = !$is_passed_out;

        // Official status flow requested for the timeline and status views
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

if (!function_exists('getCurrentStepIndex')) {
    function getCurrentStepIndex($current_status, $workflow_steps) {
        // Map legacy/old statuses to canonical new statuses
        $status_aliases = [
            'hr round'              => 'hr review',
            'under review'          => 'hr review',
            'hod approval pending'  => 'hod pending',
            'hod pending'           => 'hod pending',
            'forwarded to hod'      => 'hod pending',
            'hod approval'          => 'hod approved',
            'hod approved'          => 'hod approved',
            // Map old exam statuses → exam mail sent
            'exam completed'        => 'exam mail sent',
            'test completed'        => 'exam mail sent',
            'test submitted'        => 'exam mail sent',
            'test passed'           => 'exam mail sent',
            'test failed'           => 'exam mail sent',
            'qualified'             => 'exam mail sent',
            'not qualified'         => 'exam mail sent',
            'exam link sent'        => 'exam mail sent',
            'test link sent'        => 'exam mail sent',
            'exam mail sent'        => 'exam mail sent',
            'selected'              => 'selected',
            'hr selected'           => 'selected',
            'internship active'     => 'active intern',
            'started'               => 'active intern',
            'internship started'    => 'active intern',
            'active intern'         => 'active intern'
        ];
        $mapped_status = strtolower($current_status);
        if (isset($status_aliases[$mapped_status])) {
            $mapped_status = $status_aliases[$mapped_status];
        }

        foreach ($workflow_steps as $index => $step) {
            if (strtolower($step['status']) === $mapped_status) {
                return $index;
            }
        }
        return -1;
    }
}

if (!function_exists('formatTimestamp')) {
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
}
?>
