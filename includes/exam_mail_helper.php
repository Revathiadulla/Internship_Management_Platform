<?php
/**
 * Safe exam mail helpers for HR bulk exam invitations.
 * These functions avoid fatal errors when the supporting helper file is missing.
 */

if (!function_exists('get_bulk_exam_default_subject')) {
    function get_bulk_exam_default_subject() {
        return 'Internship Assessment Invitation';
    }
}

if (!function_exists('get_bulk_exam_default_message')) {
    function get_bulk_exam_default_message() {
        return "Dear Student,\n\nYou are invited to complete the internship assessment. Please review the instructions carefully and use the exam link below to begin.\n\nExam link: {{EXAM_LINK}}\n\nInstructions:\n- Complete the assessment within the given deadline.\n- Use a stable internet connection.\n- Contact HR if you face any issues.\n\nRegards,\nHR Team";
    }
}

if (!function_exists('render_bulk_exam_message')) {
    function render_bulk_exam_message($template, $exam_link) {
        $template = (string) ($template ?? '');
        if ($template === '') {
            $template = get_bulk_exam_default_message();
        }

        $replacements = [
            '{{EXAM_LINK}}' => $exam_link,
            '{EXAM_LINK}' => $exam_link,
            '{{EXAM_URL}}' => $exam_link,
            '{EXAM_URL}' => $exam_link,
        ];

        return strtr($template, $replacements);
    }
}

if (!function_exists('build_bulk_exam_link')) {
    function build_bulk_exam_link($base_url, $application_id) {
        $base_url = rtrim((string) ($base_url ?? ''), '/');
        $application_id = (int) $application_id;

        if ($base_url === '') {
            $base_url = 'http://localhost/IMP';
        }

        return $base_url . '/application_status_timeline.php?application_id=' . $application_id;
    }
}
