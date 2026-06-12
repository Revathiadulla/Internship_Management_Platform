# Internal Test Module Removal Report

## Updated application flow
- Application status flow now follows: Applied -> HR Review -> HOD Approval -> Selected -> Project Assignment.

## Removed UI entry points
- Removed coordinator "Generate Test" navigation links.
- Removed student-facing "Start Test" actions.
- Removed HR exam-link actions from application management screens.

## Removed application pages
- coordinator_generate_test.php
- bulk_send_exam.php
- send_exam_link.php
- student_test.php

## Removed helper files
- includes/exam_mail_helper.php

## Database cleanup
- A cleanup script was added at remove_test_module.php.
- It attempts to drop test-related columns from internship_applications and remove legacy test tables if present.
