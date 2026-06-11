<?php
require_once __DIR__ . '/../includes/workflow_helper.php';

$cases = [
    ['Exam Link Sent', 'exam_sent'],
    ['Exam Mail Sent', 'exam_mail_sent'],
    ['Exam Qualified', 'exam_qualified'],
    ['Exam Completed', 'exam_completed'],
    ['HR Review', 'hr_review'],
    ['HOD Pending', 'hod_pending'],
    ['HOD Approved', 'hod_approved'],
    ['Selected', 'selected'],
    ['Rejected', 'rejected'],
];

foreach ($cases as [$input, $expected]) {
    $actual = normalize_workflow_status($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "Workflow status normalization failed for $input: expected $expected got $actual\n");
        exit(1);
    }
}

echo "Workflow status tests passed" . PHP_EOL;
