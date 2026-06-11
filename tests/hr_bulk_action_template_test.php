<?php
require __DIR__ . '/../includes/exam_mail_helper.php';

$subject = get_bulk_exam_default_subject();
$message = get_bulk_exam_default_message();
$rendered = render_bulk_exam_message($message, 'https://example.test/student_test.php?application_id=42');

$assertions = [];
$assertions[] = ($subject === 'Internship Assessment Invitation') ? 'subject-ok' : 'subject-failed';
$assertions[] = (strpos($rendered, 'https://example.test/student_test.php?application_id=42') !== false) ? 'link-ok' : 'link-failed';
$assertions[] = (strpos($rendered, '{{EXAM_LINK}}') === false && strpos($rendered, '{EXAM_LINK}') === false) ? 'placeholder-ok' : 'placeholder-failed';

foreach ($assertions as $assertion) {
    if ($assertion !== 'subject-ok' && $assertion !== 'link-ok' && $assertion !== 'placeholder-ok') {
        fwrite(STDERR, "Assertion failed: $assertion\n");
        exit(1);
    }
}

echo "Bulk exam template tests passed\n";
