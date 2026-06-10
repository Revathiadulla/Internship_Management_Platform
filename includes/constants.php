<?php
// Prevent multiple inclusions
if (!defined('IMP_CONSTANTS_LOADED')) {
    define('IMP_CONSTANTS_LOADED', true);

    // Daily Log Statuses
    define('LOG_STATUS_SUBMITTED', 'Submitted');
    define('LOG_STATUS_APPROVED', 'Approved');
    define('LOG_STATUS_REVIEWED', 'Reviewed');
    define('LOG_STATUS_NEEDS_UPDATE', 'Needs Update');

    // Candidate Pipeline Statuses
    define('APP_STATUS_APPLIED', 'Applied');
    define('APP_STATUS_HR_REVIEW', 'HR Review');
    define('APP_STATUS_SHORTLISTED', 'Shortlisted');
    define('APP_STATUS_EXAM_MAIL_SENT', 'Exam Mail Sent');
    define('APP_STATUS_HOD_PENDING', 'HOD Pending');
    define('APP_STATUS_HOD_APPROVED', 'HOD Approved');
    define('APP_STATUS_SELECTED', 'Selected');
    define('APP_STATUS_REJECTED', 'Rejected');
    // Legacy alias (kept for backward compat — do not use in new code)
    define('APP_STATUS_TEST_COMPLETED', 'Exam Mail Sent');

    // Active Internship Statuses (used for tracking supervision list and workspaces)
    define('ACTIVE_INTERNSHIP_STATUSES', ['Selected', 'Started', 'Active Intern', 'Internship Started', 'Confirmation Letter Sent', 'Project Assigned', 'Internship Active']);
}
