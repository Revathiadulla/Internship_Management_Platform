<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
    session_start();
    include 'db.php';
    include_once __DIR__ . '/includes/auth.php';
    include_once __DIR__ . '/includes/mail_helper.php';
    include_once __DIR__ . '/includes/workflow_helper.php';

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        echo json_encode([
            'success' => false,
            'title' => 'Failed',
            'message' => 'No exam links were sent.\nReason: Unauthorized access.',
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ]);
        exit();
    }

    $user_role = strtolower(trim($_SESSION['role'] ?? ''));
    if ($user_role !== 'hr' && $user_role !== 'coordinator' && $user_role !== 'admin') {
        echo json_encode([
            'success' => false,
            'title' => 'Failed',
            'message' => 'No exam links were sent.\nReason: You do not have permission to perform this action.',
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ]);
        exit();
    }

    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $selected_ids = [];
    if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        $selected_ids = $_POST['selected_ids'];
    } elseif (isset($_POST['application_ids']) && is_array($_POST['application_ids'])) {
        $selected_ids = $_POST['application_ids'];
    }
    $selected_ids = array_values(array_unique(array_filter(array_map('intval', $selected_ids), function ($id) {
        return $id > 0;
    })));
    error_log('HR bulk action called');
    error_log('Action: ' . $action);
    error_log('IDs: ' . print_r($selected_ids, true));

    if ($action !== 'send_confirmation_letter') {
        echo json_encode([
            'success' => false,
            'title' => 'Failed',
            'message' => 'Unsupported bulk action.',
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ]);
        exit();
    }

    if (empty($selected_ids)) {
        echo json_encode([
            'success' => false,
            'title' => 'Failed',
            'message' => 'No exam links were sent.\nReason: Please select at least one student.',
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ]);
        exit();
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    $base_url = $protocol . '://' . $host . ($script_dir !== '' && $script_dir !== '/' ? $script_dir : '');

    $total = count($selected_ids);

    if ($action === 'send_confirmation_letter') {
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $user_id = intval($_SESSION['user_id']);

        foreach ($selected_ids as $application_id) {
            $app_id = intval($application_id);
            $app_sql = "SELECT a.id AS application_id, a.status, a.user_id, a.internship_id, a.internship_name, a.confirmation_letter_path, a.confirmation_letter_sent_at, a.confirmation_letter_sent,
                               COALESCE(i.title, a.internship_name) AS internship_title,
                               COALESCE(sp.full_name, u.full_name, CONCAT('Student ', a.user_id)) AS student_name,
                               COALESCE(sp.email, u.email) AS student_email
                        FROM internship_applications a
                        LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                        LEFT JOIN users u ON a.user_id = u.id
                        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                        WHERE a.id = $app_id LIMIT 1";
            $app_result = mysqli_query($conn, $app_sql);

            if (!$app_result || mysqli_num_rows($app_result) === 0) {
                $failed++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => 'Unknown',
                    'student_email' => '',
                    'reason' => 'Application not found.'
                ];
                continue;
            }

            $app = mysqli_fetch_assoc($app_result);
            $current_status = trim((string) ($app['status'] ?? ''));
            $status_lower = strtolower($current_status);
            $selected_like_statuses = ['selected', 'hr selected', 'hr_selected', 'confirmation letter sent', 'confirmation_letter_sent', 'offer sent', 'offer_sent'];
            $already_sent = !empty($app['confirmation_letter_path']) || !empty($app['confirmation_letter_sent_at']) || (!empty($app['confirmation_letter_sent']) && (int) $app['confirmation_letter_sent'] === 1);

            if (!$already_sent && !in_array($status_lower, $selected_like_statuses, true)) {
                $skipped++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => $app['student_name'] ?? 'Student',
                    'student_email' => $app['student_email'] ?? '',
                    'reason' => 'Skipped because the application is not currently in a selected state.'
                ];
                continue;
            }

            if ($already_sent) {
                $skipped++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => $app['student_name'] ?? 'Student',
                    'student_email' => $app['student_email'] ?? '',
                    'reason' => 'Skipped because the confirmation letter has already been sent.'
                ];
                continue;
            }

            $student_name = trim((string) ($app['student_name'] ?? 'Student'));
            $student_email = trim((string) ($app['student_email'] ?? ''));
            $internship_title = trim((string) ($app['internship_title'] ?? 'Internship'));
            $student_user_id = intval($app['user_id']);

            if ($student_email === '') {
                $failed++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => $student_name,
                    'student_email' => '',
                    'reason' => 'No student email address available.'
                ];
                continue;
            }

            require_once __DIR__ . '/includes/fpdf.php';
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 24);
            $pdf->SetTextColor(0, 74, 198);
            $pdf->Cell(0, 15, 'IMP', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell(0, 10, 'Internship Management Platform', 0, 1, 'C');
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 10, 'INTERNSHIP SELECTION CONFIRMATION LETTER', 0, 1, 'C');
            $pdf->Ln(10);
            $pdf->SetFont('Arial', '', 12);
            $ref_no = 'IMP/' . date('Y') . '/' . str_pad($app_id, 4, '0', STR_PAD_LEFT);
            $pdf->Cell(0, 8, 'Reference No: ' . $ref_no, 0, 1);
            $pdf->Cell(0, 8, 'Date: ' . date('F j, Y'), 0, 1);
            $pdf->Ln(5);
            $pdf->Cell(0, 8, 'Student Name: ' . $student_name, 0, 1);
            $pdf->Cell(0, 8, 'Student Email: ' . $student_email, 0, 1);
            $pdf->Cell(0, 8, 'Application ID: ' . $app_id, 0, 1);
            $pdf->Cell(0, 8, 'Status: SELECTED', 0, 1);
            $pdf->Ln(10);
            $pdf->SetFont('Arial', '', 12);
            $msg = "Dear $student_name,\n\nWe are pleased to inform you that your application for the internship position \"$internship_title\" has been successful. You have been officially selected for this role.\n\nPlease note: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator. You do not need to take any action regarding these assignments until further notice.\n\nCongratulations on your selection!";
            $pdf->MultiCell(0, 8, $msg);
            $pdf->Ln(20);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, 'Best Regards,', 0, 1);
            $pdf->Cell(0, 8, 'HR Team, IMP', 0, 1);
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 10, 'This is a system-generated confirmation letter. No signature is required.', 0, 1, 'C');

            $dir = __DIR__ . '/uploads/offer_letters';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $pdf_filename = 'Offer_Letter_' . $app_id . '_' . time() . '.pdf';
            $local_pdf_path = $dir . DIRECTORY_SEPARATOR . $pdf_filename;
            $pdf->Output('F', $local_pdf_path);
            $relative_pdf_path = 'uploads/offer_letters/' . $pdf_filename;
            $final_pdf_path = $relative_pdf_path;

            require_once __DIR__ . '/includes/cloudinary_config.php';
            try {
                $cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
                $api_key = getenv('CLOUDINARY_API_KEY');
                $api_secret = getenv('CLOUDINARY_API_SECRET');
                if (!empty($cloud_name) && !empty($api_key) && !empty($api_secret)) {
                    $secure_url = uploadToCloudinary($local_pdf_path, 'offer_letters', true);
                    if (!empty($secure_url)) {
                        $final_pdf_path = $secure_url;
                    }
                }
            } catch (Exception $e) {
                error_log('Failed to upload confirmation letter to Cloudinary, falling back to local: ' . $e->getMessage());
            }

            $GLOBALS['mail_options_attachments'] = [[
                'path' => $local_pdf_path,
                'name' => $pdf_filename
            ]];

            $student_subject = 'Congratulations! You have been selected for the internship';
            $student_message = "Dear $student_name,\n\nWe are pleased to inform you that you have been selected for the internship: \"$internship_title\". Please find your Confirmation Letter attached.\n\nNote: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator.\n\nBest regards,\nIMP Team";

            $email_sent = sendStudentNotification($student_user_id, $student_name, $student_subject, $student_message, [
                'event' => 'Application Status Update',
                'internship' => $internship_title,
                'status' => 'Selected',
                'action_url' => $base_url . '/student_applications.php',
                'action_label' => 'View Application'
            ]);

            unset($GLOBALS['mail_options']);
            if (!$email_sent) {
                $failed++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => $student_name,
                    'student_email' => $student_email,
                    'reason' => 'Email delivery failed.'
                ];
                continue;
            }

            $esc_pdf_path = mysqli_real_escape_string($conn, $final_pdf_path);
            $update_sql = "UPDATE internship_applications SET status = 'Selected', confirmation_letter_path = '$esc_pdf_path', confirmation_letter_sent_at = NOW() WHERE id = $app_id";
            if (!mysqli_query($conn, $update_sql)) {
                $failed++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => $student_name,
                    'student_email' => $student_email,
                    'reason' => 'Email sent, but the application could not be updated. ' . mysqli_error($conn)
                ];
                continue;
            }

            $offer_col = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'offer_letter_path'");
            if ($offer_col && mysqli_num_rows($offer_col) > 0) {
                mysqli_query($conn, "UPDATE internship_applications SET offer_letter_path = '$esc_pdf_path' WHERE id = $app_id");
            }

            $confirmation_sent_col = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'confirmation_letter_sent'");
            if ($confirmation_sent_col && mysqli_num_rows($confirmation_sent_col) > 0) {
                mysqli_query($conn, "UPDATE internship_applications SET confirmation_letter_sent = 1 WHERE id = $app_id");
            }

            $name_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
            $name_res = mysqli_query($conn, $name_sql);
            $name_row = mysqli_fetch_assoc($name_res);
            $updated_by_name = $name_row ? mysqli_real_escape_string($conn, $name_row['full_name']) : strtoupper($user_role);
            $history_notes = mysqli_real_escape_string($conn, 'Bulk confirmation letter generated and emailed.');
            mysqli_query($conn, "INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) VALUES ($app_id, 'Selected', 'Selected', '$user_role', '$updated_by_name', '$history_notes')");

            $notif_msg = mysqli_real_escape_string($conn, "Your confirmation letter has been generated and sent for the internship: $internship_title.");
            mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message) VALUES ($student_user_id, 'Confirmation Letter Generated', 'success', '$notif_msg')");

            $sent++;
        }

        $summary = [
            'selected_count' => $total,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'failures' => $errors,
            'performed_by' => $user_id
        ];
        $workflow_log_ok = add_workflow_log('bulk_confirmation_letter', 'send_confirmation_letter', $user_id, json_encode($summary, JSON_UNESCAPED_SLASHES));

        if ($sent > 0 && $failed === 0) {
            $title = 'Success';
            $type = 'success';
            $message = "Successfully Sent: $sent\nFailed: $failed\nSkipped: $skipped";
            if ($workflow_log_ok === false) {
                $message .= "\nWarning: workflow logging could not be recorded.";
                $type = 'warning';
            }
        } elseif ($sent > 0) {
            $title = 'Completed with Warnings';
            $type = 'warning';
            $message = "Successfully Sent: $sent\nFailed: $failed\nSkipped: $skipped";
            if ($workflow_log_ok === false) {
                $message .= "\nWarning: workflow logging could not be recorded.";
            }
        } else {
            $title = 'Failed';
            $type = 'error';
            $reason = $errors[0]['reason'] ?? 'Unknown error while sending confirmation letters.';
            $message = "Successfully Sent: $sent\nFailed: $failed\nSkipped: $skipped\nReason: $reason";
        }

        echo json_encode([
            'success' => $sent > 0,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'selected_count' => $total,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'errors' => $errors,
            'failures' => $errors
        ]);
        exit();
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    $base_url = $protocol . '://' . $host . ($script_dir !== '' && $script_dir !== '/' ? $script_dir : '');

    $sent = 0;
    $failed = 0;
    $skipped = 0;
    $resend_count = 0;
    $errors = [];
    $user_id = intval($_SESSION['user_id']);

    $status_name = 'exam_sent';
    $default_subject = get_bulk_exam_default_subject();
    $default_message = get_bulk_exam_default_message();
    $compose_subject = $subject !== '' ? $subject : $default_subject;
    $compose_message = $message !== '' ? $message : $default_message;

    $attachment_path = null;
    $attachment_name = null;
    $attachment_relative_path = null;
    $attachment_error = null;
    $attachment_file = null;

    if (isset($_FILES['attachment_file']) && is_array($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
        $attachment_file = $_FILES['attachment_file'];
    } elseif (isset($_FILES['exam_attachment']) && is_array($_FILES['exam_attachment']) && $_FILES['exam_attachment']['error'] === UPLOAD_ERR_OK) {
        $attachment_file = $_FILES['exam_attachment'];
    }

    if ($attachment_file !== null) {
        $file_tmp = $attachment_file['tmp_name'];
        $file_name = $attachment_file['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024;
        if (!in_array($file_ext, $allowed_exts, true)) {
            $attachment_error = 'Invalid attachment type. Only PDF, DOC, DOCX, JPG, and PNG are allowed.';
        } elseif (!is_uploaded_file($file_tmp) || intval($attachment_file['size'] ?? 0) > $max_size) {
            $attachment_error = 'Invalid attachment. File size must be 5 MB or less.';
        } else {
            $upload_dir = __DIR__ . '/uploads/hr_email_attachments/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $new_file_name = time() . '_bulk_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
            $dest_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $attachment_path = $dest_path;
                $attachment_name = $file_name;
                $attachment_relative_path = 'uploads/hr_email_attachments/' . $new_file_name;
            } else {
                $attachment_error = 'Failed to upload the selected attachment.';
            }
        }
    }

    if ($attachment_error !== null) {
        echo json_encode([
            'success' => false,
            'title' => 'Failed',
            'message' => "No exam links were sent.\nReason: $attachment_error",
            'selected_count' => $total,
            'sent_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'failures' => []
        ]);
        exit();
    }
    $attachment_payload = [];
    if ($attachment_path !== null) {
        $attachment_payload = [[
            'path' => $attachment_path,
            'name' => $attachment_name ?: basename($attachment_path)
        ]];
    }
    $name_sql = "SELECT full_name FROM student_profiles WHERE user_id = $user_id LIMIT 1";
    $name_res = mysqli_query($conn, $name_sql);
    $name_row = mysqli_fetch_assoc($name_res);
    $updated_by_name = $name_row ? mysqli_real_escape_string($conn, $name_row['full_name']) : strtoupper($user_role);

    $hr_user_sql = "SELECT email, full_name FROM users WHERE id = $user_id LIMIT 1";
    $hr_user_res = mysqli_query($conn, $hr_user_sql);
    $hr_user_row = mysqli_fetch_assoc($hr_user_res);
    $reply_to_email = trim((string) ($hr_user_row['email'] ?? ''));
    $reply_to_name = trim((string) ($hr_user_row['full_name'] ?? 'HR Team'));
    $notes = 'Exam link sent in bulk.';
    $esc_notes = mysqli_real_escape_string($conn, $notes);

    foreach ($selected_ids as $application_id) {
        $app_id = intval($application_id);
        $app_sql = "SELECT a.id AS application_id, a.status, a.user_id,
                           COALESCE(i.title, a.internship_name, a.applied_subtype, a.preferred_domain, 'Internship') AS internship_name,
                           a.internship_name AS project_subtype,
                           COALESCE(sp.full_name, u.full_name, CONCAT('Student ', a.user_id)) AS student_name,
                           COALESCE(sp.email, u.email) AS student_email
                    FROM internship_applications a
                    LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                    LEFT JOIN users u ON a.user_id = u.id
                    LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                    WHERE a.id = $app_id LIMIT 1";
        $app_result = mysqli_query($conn, $app_sql);

        if (!$app_result || mysqli_num_rows($app_result) === 0) {
            $failed++;
            $errors[] = [
                'application_id' => $app_id,
                'student_name' => 'Unknown',
                'student_email' => '',
                'reason' => 'Application not found.'
            ];
            continue;
        }

        $app = mysqli_fetch_assoc($app_result);
        $old_status = trim((string) ($app['status'] ?? ''));
        $status_lower = strtolower($old_status);
        $initial_send_statuses = ['applied', 'hr review', 'hr_review', 'shortlisted'];
        $resend_statuses = ['exam_sent', 'exam mail sent', 'test completed', 'test_completed'];
        $blocked_statuses = ['selected', 'rejected', 'completed'];

        if (in_array($status_lower, $blocked_statuses, true)) {
            $skipped++;
            $errors[] = [
                'application_id' => $app_id,
                'student_name' => $app['student_name'] ?? 'Student',
                'student_email' => $app['student_email'] ?? '',
                'reason' => 'Skipped because the application is not eligible for a new exam link.'
            ];
            continue;
        }

        $is_resend = in_array($status_lower, $resend_statuses, true);
        if (!$is_resend && !in_array($status_lower, $initial_send_statuses, true)) {
            $skipped++;
            $errors[] = [
                'application_id' => $app_id,
                'student_name' => $app['student_name'] ?? 'Student',
                'student_email' => $app['student_email'] ?? '',
                'reason' => 'Skipped because the application is not eligible for a new exam link.'
            ];
            continue;
        }

        $student_name = trim((string) ($app['student_name'] ?? 'Student'));
        $student_email = trim((string) ($app['student_email'] ?? ''));
        $internship_name = trim((string) ($app['internship_name'] ?? 'Internship'));
        $student_user_id = intval($app['user_id']);

        if ($student_email === '') {
            $failed++;
            $errors[] = [
                'application_id' => $app_id,
                'student_name' => $student_name,
                'student_email' => '',
                'reason' => 'No student email address available.'
            ];
            continue;
        }

        $exam_link_to_send = build_bulk_exam_link($base_url, $app_id);
        $student_subject = $compose_subject;
        $student_message = render_bulk_exam_message($compose_message, $exam_link_to_send);

        $error_output = '';
        $GLOBALS['mail_options_attachments'] = $attachment_payload;
        $GLOBALS['mail_options'] = [
            'reply_to' => $reply_to_email !== '' ? $reply_to_email : null,
            'reply_to_name' => $reply_to_name,
            'from_name' => ($reply_to_name !== '' ? $reply_to_name : 'HR Team') . ' / HR'
        ];
        $email_sent = sendEmailNotification($student_email, $student_subject, $student_message, [
            'recipient_name' => $student_name,
            'event' => 'Exam Link Sent',
            'internship' => $internship_name,
            'status' => 'Exam Sent',
            'action_url' => $base_url . '/student_applications.php',
            'action_label' => 'Go to Applications'
        ], $error_output);

        unset($GLOBALS['mail_options']);
        if (!$email_sent) {
            $failed++;
            $errors[] = [
                'application_id' => $app_id,
                'student_name' => $student_name,
                'student_email' => $student_email,
                'reason' => $error_output !== '' ? $error_output : 'Email delivery failed.'
            ];
            continue;
        }

        if ($is_resend) {
            $update_sql = "UPDATE internship_applications
                           SET status = '$status_name',
                               exam_link = '" . mysqli_real_escape_string($conn, $exam_link_to_send) . "',
                               exam_sent_date = NOW()
                           WHERE id = $app_id";
            $resend_count++;
        } else {
            $update_sql = "UPDATE internship_applications
                           SET status = '$status_name',
                               exam_link = '" . mysqli_real_escape_string($conn, $exam_link_to_send) . "',
                               exam_sent_date = NOW()
                           WHERE id = $app_id";
        }

        if (!mysqli_query($conn, $update_sql)) {
            $failed++;
            $errors[] = [
                'application_id' => $app_id,
                'student_name' => $student_name,
                'student_email' => $student_email,
                'reason' => 'Email sent, but the application status could not be updated. ' . mysqli_error($conn)
            ];
            continue;
        }

        if (!$is_resend) {
            mysqli_query($conn, "INSERT INTO application_status_history
                (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
                VALUES ($app_id, '" . mysqli_real_escape_string($conn, $old_status) . "', '$status_name', '$user_role', '$updated_by_name', '$esc_notes')");
        }

        if (!empty($attachment_relative_path)) {
            $attachment_log_path = mysqli_real_escape_string($conn, $attachment_relative_path);
            $attachment_log_sql = "SHOW TABLES LIKE 'email_logs'";
            $attachment_log_res = mysqli_query($conn, $attachment_log_sql);
            if ($attachment_log_res && mysqli_num_rows($attachment_log_res) > 0) {
                $attachment_log_cols = mysqli_query($conn, "SHOW COLUMNS FROM email_logs LIKE 'attachment_path'");
                if ($attachment_log_cols && mysqli_num_rows($attachment_log_cols) > 0) {
                    mysqli_query($conn, "INSERT INTO email_logs (application_id, attachment_path, created_at) VALUES ($app_id, '$attachment_log_path', NOW())");
                }
            }
        }

        $notif_msg = mysqli_real_escape_string($conn, 'Your internship assessment link has been sent.');
        mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
            VALUES ($student_user_id, 'Exam Link Sent', 'info', '$notif_msg')");

        $sent++;
    }

    $summary = [
        'selected_count' => $total,
        'sent_count' => $sent,
        'failed_count' => $failed,
        'skipped_count' => $skipped,
        'resend_count' => $resend_count,
        'failures' => $errors,
        'performed_by' => $user_id
    ];
    $workflow_log_ok = add_workflow_log('bulk_exam_link', 'send_exam_link', $user_id, json_encode($summary, JSON_UNESCAPED_SLASHES));

    if ($sent > 0 && $failed === 0) {
        $title = 'Success';
        $type = 'success';
        $message = ($attachment_relative_path !== null ? 'Email sent with attachment.' : 'Exam link sent successfully.') . "\nSelected: $total\nDelivered: $sent\nFailed: $failed\nSkipped: $skipped";
        if ($workflow_log_ok === false) {
            $message .= "\nWarning: workflow logging could not be recorded.";
            $type = 'warning';
        }
    } elseif ($sent > 0) {
        $title = 'Completed with Warnings';
        $type = 'warning';
        $message = ($attachment_relative_path !== null ? 'Email sent with attachment.' : 'Exam link sent successfully.') . "\nSelected: $total\nDelivered: $sent\nFailed: $failed\nSkipped: $skipped";
        if ($workflow_log_ok === false) {
            $message .= "\nWarning: workflow logging could not be recorded.";
        }
    } else {
        $title = 'Failed';
        $type = 'error';
        $reason = $errors[0]['reason'] ?? 'Unknown error while sending exam links.';
        $message = "No exam links were delivered. Reason: $reason\nSelected: $total\nDelivered: $sent\nFailed: $failed\nSkipped: $skipped";
    }

    echo json_encode([
        'success' => $sent > 0,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'total' => $total,
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'selected_count' => $total,
        'sent_count' => $sent,
        'failed_count' => $failed,
        'skipped_count' => $skipped,
        'errors' => $errors,
        'failures' => $errors,
        'resend_count' => $resend_count
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'type' => 'error',
        'title' => 'Failed',
        'message' => 'No exam links were sent.\nReason: ' . $e->getMessage(),
        'total' => 0,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => []
    ]);
}
?>
