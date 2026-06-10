-- Migration: Update status values to new official workflow
-- Run this in a safe maintenance window. Back up your DB first.

START TRANSACTION;

-- Map internship_applications.status
UPDATE internship_applications SET status = 'HR Review' WHERE LOWER(status) IN ('hr round','hr review');
UPDATE internship_applications SET status = 'Test Link Sent' WHERE LOWER(status) IN ('exam link sent');
UPDATE internship_applications SET status = 'Test Completed' WHERE LOWER(status) IN ('qualified','not qualified','test completed');
UPDATE internship_applications SET status = 'HOD Approved' WHERE LOWER(status) IN ('hod approval','hod approved','hod approval pending');
UPDATE internship_applications SET status = 'HR Selected' WHERE LOWER(status) IN ('selected');
UPDATE internship_applications SET status = 'Active Intern' WHERE LOWER(status) IN ('internship active','internship started','started','active intern');
UPDATE internship_applications SET status = 'Completed' WHERE LOWER(status) IN ('completed','internship completed');
UPDATE internship_applications SET status = 'Applied' WHERE LOWER(status) IN ('applied');
UPDATE internship_applications SET status = 'Confirmation Letter Sent' WHERE LOWER(status) IN ('confirmation letter sent');
UPDATE internship_applications SET status = 'Project Assigned' WHERE LOWER(status) IN ('project assigned');
UPDATE internship_applications SET status = 'Talent Pool' WHERE LOWER(status) IN ('talent pool');

-- Map application_status_history.old_status and new_status
UPDATE application_status_history SET old_status = 'HR Review' WHERE LOWER(old_status) IN ('hr round','hr review');
UPDATE application_status_history SET new_status = 'HR Review' WHERE LOWER(new_status) IN ('hr round','hr review');

UPDATE application_status_history SET old_status = 'Test Link Sent' WHERE LOWER(old_status) IN ('exam link sent');
UPDATE application_status_history SET new_status = 'Test Link Sent' WHERE LOWER(new_status) IN ('exam link sent');

UPDATE application_status_history SET old_status = 'Test Completed' WHERE LOWER(old_status) IN ('qualified','not qualified','test completed');
UPDATE application_status_history SET new_status = 'Test Completed' WHERE LOWER(new_status) IN ('qualified','not qualified','test completed');

UPDATE application_status_history SET old_status = 'HOD Approved' WHERE LOWER(old_status) IN ('hod approval','hod approved','hod approval pending');
UPDATE application_status_history SET new_status = 'HOD Approved' WHERE LOWER(new_status) IN ('hod approval','hod approved','hod approval pending');

UPDATE application_status_history SET old_status = 'HR Selected' WHERE LOWER(old_status) IN ('selected');
UPDATE application_status_history SET new_status = 'HR Selected' WHERE LOWER(new_status) IN ('selected');

UPDATE application_status_history SET old_status = 'Active Intern' WHERE LOWER(old_status) IN ('internship active','internship started','started','active intern');
UPDATE application_status_history SET new_status = 'Active Intern' WHERE LOWER(new_status) IN ('internship active','internship started','started','active intern');

UPDATE application_status_history SET old_status = 'Completed' WHERE LOWER(old_status) IN ('completed','internship completed');
UPDATE application_status_history SET new_status = 'Completed' WHERE LOWER(new_status) IN ('completed','internship completed');

-- Notifications table (if status stored)
UPDATE notifications SET data = REPLACE(data, '"status":"Exam Link Sent"', '"status":"Test Link Sent"') WHERE data LIKE '%Exam Link Sent%';
UPDATE notifications SET data = REPLACE(data, '"status":"Qualified"', '"status":"Test Completed"') WHERE data LIKE '%Qualified%';
UPDATE notifications SET data = REPLACE(data, '"status":"Selected"', '"status":"HR Selected"') WHERE data LIKE '%Selected%';

COMMIT;

-- End of migration
