<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json');

try {
    session_start();
    include 'db.php';
    include_once __DIR__ . '/includes/auth.php';
    include_once __DIR__ . '/includes/mail_helper.php';
    include_once __DIR__ . '/includes/exam_mail_helper.php';
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
    $to_recipients = isset($_POST['to']) ? trim((string) $_POST['to']) : '';
    $cc_recipients = isset($_POST['cc']) ? trim((string) $_POST['cc']) : '';
    $bcc_recipients = isset($_POST['bcc']) ? trim((string) $_POST['bcc']) : '';
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

    if ($action !== 'send_confirmation_letter' && $action !== 'send_exam_link' && $action !== 'send_email' && $action !== 'send_exam_email' && $action !== 'send_exam_mail') {
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
            'message' => 'Please select at least one student.',
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ]);
        exit();
    }

    // Check if SMTP is configured
    if (!isSmtpConfigured()) {
        echo json_encode([
            'success' => false,
            'title' => 'SMTP Configuration Missing',
            'message' => 'SMTP settings are incomplete. Please define SMTP_HOST, SMTP_PORT, SMTP_USERNAME, and SMTP_PASSWORD in config or environment.',
            'total' => count($selected_ids),
            'sent' => 0,
            'failed' => count($selected_ids),
            'skipped' => 0,
            'errors' => []
        ]);
        exit();
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    $base_url = $protocol . '://' . $host . ($script_dir !== '' && $script_dir !== '/' ? $script_dir : '');

    $user_id = intval($_SESSION['user_id']);
    $smtp_cfg = getSmtpConfig();
    $sender_name_sql = "SELECT full_name FROM users WHERE id = $user_id LIMIT 1";
    $sender_name_row = mysqli_fetch_assoc(mysqli_query($conn, $sender_name_sql));
    $sender_name = trim((string) ($sender_name_row['full_name'] ?? 'HR'));
    $sender_role = strtoupper($user_role);
    $portal_from_email = trim((string) ($smtp_cfg['from_email'] ?? ''));
    $portal_from_name = trim((string) ($smtp_cfg['from_name'] ?? 'Internship Management Platform'));

    $total = count($selected_ids);

    if ($action === 'send_email' || $action === 'send_exam_email' || $action === 'send_exam_mail' || $action === 'send_exam_link') {
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        $compose_subject = $subject !== '' ? $subject : 'Internship Update';
        $compose_message = $message !== '' ? $message : "Dear Student,\n\nThis is an update from the HR team.\n\nRegards,\nHR Team";

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
                'type' => 'error',
                'title' => 'Failed',
                'message' => 'Email could not be sent. Reason: ' . $attachment_error,
                'selected_count' => $total,
                'sent_count' => 0,
                'failed_count' => 0,
                'skipped_count' => 0,
                'errors' => []
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

        $hr_user_sql = "SELECT email, full_name FROM users WHERE id = $user_id LIMIT 1";
        $hr_user_res = mysqli_query($conn, $hr_user_sql);
        $hr_user_row = mysqli_fetch_assoc($hr_user_res);
        $reply_to_email = trim((string) ($hr_user_row['email'] ?? ''));
        $reply_to_name = trim((string) ($hr_user_row['full_name'] ?? 'HR Team'));

        $modal_to_addresses = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', (string) $to_recipients)), function ($address) {
            return $address !== '';
        }));
        $modal_cc_addresses = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', (string) $cc_recipients)), function ($address) {
            return $address !== '';
        }));
        $modal_bcc_addresses = array_values(array_filter(array_map('trim', preg_split('/[;,]+/', (string) $bcc_recipients)), function ($address) {
            return $address !== '';
        }));

        foreach ($selected_ids as $application_id) {
            $app_id = intval($application_id);
            $app_sql = "SELECT a.id AS application_id, a.status, a.user_id,
                               COALESCE(i.title, a.internship_name, a.applied_subtype, a.preferred_domain, 'Internship') AS internship_name,
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
            $student_name = trim((string) ($app['student_name'] ?? 'Student'));
            $student_email = trim((string) ($app['student_email'] ?? ''));
            $internship_name = trim((string) ($app['internship_name'] ?? 'Internship'));
            $student_user_id = intval($app['user_id']);
            $old_status = trim((string) ($app['status'] ?? ''));

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

            // Respect To field edits:
            if (count($selected_ids) === 1 && !empty($modal_to_addresses)) {
                $student_email = $modal_to_addresses[0];
            } else {
                if (!in_array($student_email, $modal_to_addresses, true)) {
                    $skipped++;
                    continue;
                }
            }

            $student_message = $compose_message;
            $exam_link_to_send = '';
            $status_name = 'Exam Mail Sent';

            if (in_array($action, ['send_exam_email', 'send_exam_mail', 'send_exam_link'], true)) {
                $exam_link_to_send = build_bulk_exam_link($base_url, $app_id);
                $student_message = render_bulk_exam_message($compose_message, $exam_link_to_send);
            }

            $GLOBALS['mail_options'] = [
                'reply_to' => $reply_to_email !== '' ? $reply_to_email : null,
                'reply_to_name' => $reply_to_name,
                'from_email' => $portal_from_email,
                'from_name' => $portal_from_name,
                'cc' => $modal_cc_addresses,
                'bcc' => $modal_bcc_addresses,
            ];
            $GLOBALS['mail_context'] = [
                'sender_id' => $user_id,
                'sender_role' => $sender_role,
                'sender_name' => $sender_name,
                'from_email' => $portal_from_email,
                'from_name' => $portal_from_name,
                'application_id' => $app_id,
            ];
            $GLOBALS['mail_options_attachments'] = $attachment_payload;

            $debugInfo = '';
            $mail_sent = sendEmail($student_email, $student_name, $compose_subject, $student_message, $debugInfo);

            if ($mail_sent) {
                $sent++;

                if (in_array($action, ['send_exam_email', 'send_exam_mail', 'send_exam_link'], true)) {
                    $update_sql = "UPDATE internship_applications
                                   SET status = '" . mysqli_real_escape_string($conn, $status_name) . "',
                                       exam_link = '" . mysqli_real_escape_string($conn, $exam_link_to_send) . "',
                                       exam_sent_date = NOW()
                                   WHERE id = $app_id";
                    mysqli_query($conn, $update_sql);

                    $esc_notes = mysqli_real_escape_string($conn, 'Exam invitation email sent by ' . $sender_name . ' (' . $sender_role . ')');
                    mysqli_query($conn, "INSERT INTO application_status_history
                        (application_id, old_status, new_status, updated_by_role, updated_by_name, notes)
                        VALUES ($app_id, '" . mysqli_real_escape_string($conn, $old_status) . "', '" . mysqli_real_escape_string($conn, $status_name) . "', '$user_role', '" . mysqli_real_escape_string($conn, $sender_name) . "', '$esc_notes')");

                    if (!empty($attachment_relative_path)) {
                        $attachment_log_path = mysqli_real_escape_string($conn, $attachment_relative_path);
                        mysqli_query($conn, "UPDATE email_logs SET attachment_path = '$attachment_log_path' WHERE application_id = $app_id AND status = 'sent' ORDER BY id DESC LIMIT 1");
                    }

                    $notif_msg = mysqli_real_escape_string($conn, 'Your internship assessment invitation has been sent.');
                    mysqli_query($conn, "INSERT INTO student_notifications (user_id, title, type, message)
                        VALUES ($student_user_id, 'Exam Link Sent', 'info', '$notif_msg')");
                } else {
                    // For non-exam email, if attachment was uploaded, we make sure it is saved in the log
                    if (!empty($attachment_relative_path)) {
                        $attachment_log_path = mysqli_real_escape_string($conn, $attachment_relative_path);
                        mysqli_query($conn, "UPDATE email_logs SET attachment_path = '$attachment_log_path' WHERE application_id = $app_id AND status = 'sent' ORDER BY id DESC LIMIT 1");
                    }
                }
            } else {
                $failed++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => $student_name,
                    'student_email' => $student_email,
                    'reason' => $debugInfo ?: 'Email sending failed.'
                ];
            }

            $GLOBALS['mail_options'] = [];
            $GLOBALS['mail_options_attachments'] = [];
            $GLOBALS['mail_context'] = [];
        }

        $errMsgs = [];
        foreach ($errors as $err) {
            $errMsgs[] = $err['student_name'] . ': ' . $err['reason'];
        }
        $message_detail = !empty($errMsgs) ? "\nReasons:\n" . implode("\n", $errMsgs) : '';

        echo json_encode([
            'success' => $sent > 0,
            'type' => $sent > 0 && $failed === 0 ? 'success' : ($sent > 0 ? 'warning' : 'error'),
            'title' => $sent > 0 && $failed === 0 ? 'Success' : ($sent > 0 ? 'Partial Success' : 'Failed'),
            'message' => 'Email sent successfully to ' . $sent . ' student' . ($sent === 1 ? '' : 's') . ' by ' . htmlspecialchars($sender_name, ENT_QUOTES) . ' (' . htmlspecialchars($sender_role, ENT_QUOTES) . ').' . ($failed > 0 ? ' ' . $failed . ' failed.' . $message_detail : ''),
            'selected_count' => $total,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'errors' => $errors
        ]);
        exit();
    }

    if ($action === 'send_confirmation_letter') {
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $user_id = intval($_SESSION['user_id']);

        // Fetch active confirmation letter template once
        $template_res = mysqli_query($conn, "SELECT * FROM confirmation_letter_templates WHERE is_active = 1 LIMIT 1");
        $template = mysqli_fetch_assoc($template_res);

        $subject_template = $template ? $template['subject'] : "Congratulations! You have been selected for the internship";
        $content_template = $template ? $template['content'] : "Dear {student_name},\n\nWe are pleased to inform you that your application for the internship position \"{project_title}\" has been successful. You have been officially selected for this role.\n\nPlease note: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator. You do not need to take any action regarding these assignments until further notice.\n\nCongratulations on your selection!";
        $sig_name = $template ? $template['signature_name'] : "HR Team";
        $sig_designation = $template ? $template['signature_designation'] : "IMP Platform";
        $logo_path = $template ? $template['logo_path'] : "";

        if (!function_exists('get_local_image_path')) {
            function get_local_image_path($path) {
                if (empty($path)) return '';
                if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
                    $temp_dir = sys_get_temp_dir();
                    $filename = 'fpdf_img_' . md5($path) . '.' . pathinfo($path, PATHINFO_EXTENSION);
                    $local = $temp_dir . DIRECTORY_SEPARATOR . $filename;
                    if (!file_exists($local)) {
                        $ch = curl_init($path);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        $data = curl_exec($ch);
                        curl_close($ch);
                        if ($data) {
                            @file_put_contents($local, $data);
                        }
                    }
                    if (file_exists($local) && filesize($local) > 0) {
                        return $local;
                    }
                } else {
                    $local = __DIR__ . '/' . ltrim($path, '/');
                    if (file_exists($local)) {
                        return $local;
                    }
                }
                return '';
            }
        }

        foreach ($selected_ids as $application_id) {
            $app_id = intval($application_id);
            $app_sql = "SELECT a.id AS application_id, a.status, a.user_id, a.internship_id, a.internship_name, a.applied_subtype, a.confirmation_letter_path, a.confirmation_letter_sent_at, a.confirmation_letter_sent,
                               a.internship_duration AS app_duration, a.team_name, a.start_date, a.applied_date, a.project_subtype AS app_project_subtype, a.tech_stack AS app_tech_stack,
                               COALESCE(i.title, a.internship_name) AS internship_title, i.project_subtype, i.duration AS intern_duration, i.mode AS intern_mode, i.technology_stack AS intern_tech_stack,
                               COALESCE(sp.full_name, u.full_name, CONCAT('Student ', a.user_id)) AS student_name,
                               COALESCE(sp.email, u.email) AS student_email,
                               m.full_name AS mentor_name
                        FROM internship_applications a
                        LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
                        LEFT JOIN users u ON a.user_id = u.id
                        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
                        LEFT JOIN users m ON a.mentor_id = m.id
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

            $project_subtype  = trim($app['project_subtype'] ?? ($app['app_project_subtype'] ?? ($app['applied_subtype'] ?? '')));
            $duration         = trim($app['intern_duration'] ?? ($app['app_duration'] ?? ''));
            $mode             = trim($app['intern_mode'] ?? '');
            $tech_stack       = trim($app['intern_tech_stack'] ?? ($app['app_tech_stack'] ?? ''));

            $resolved_internship_name = !empty($project_subtype) ? $project_subtype : 'Not specified';
            $resolved_duration        = !empty($duration) ? $duration : 'Not specified';
            $resolved_mode            = !empty($mode) ? $mode : 'Not specified';
            $resolved_tech_stack      = !empty($tech_stack) ? $tech_stack : 'Not specified';

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

            // Resolve Placeholders
            $resolved_mentor = !empty($app['mentor_name']) ? $app['mentor_name'] : 'Not assigned';
            $resolved_team = !empty($app['team_name']) ? $app['team_name'] : 'Not assigned';
            $resolved_joining_date = !empty($app['start_date']) ? date('F d, Y', strtotime($app['start_date'])) : (!empty($app['applied_date']) ? date('F d, Y', strtotime($app['applied_date'])) : date('F d, Y'));
            $resolved_selection_date = date('F d, Y');
            $resolved_company = "Internship Management Platform (IMP)";

            $placeholders = [
                '{student_name}' => $student_name,
                '{application_id}' => $app_id,
                '{project_title}' => $resolved_internship_name,
                '{project_subtype}' => $resolved_internship_name,
                '{duration}' => $resolved_duration,
                '{mode}' => $resolved_mode,
                '{technology_stack}' => $resolved_tech_stack,
                '{mentor_name}' => $resolved_mentor,
                '{team_name}' => $resolved_team,
                '{company_name}' => $resolved_company,
                '{joining_date}' => $resolved_joining_date,
                '{selection_date}' => $resolved_selection_date
            ];

            $resolved_subject = $subject_template;
            $resolved_content = $content_template;

            foreach ($placeholders as $ph => $val) {
                $resolved_subject = str_replace($ph, $val, $resolved_subject);
                $resolved_content = str_replace($ph, $val, $resolved_content);
            }

            require_once __DIR__ . '/includes/fpdf.php';
            $pdf = new FPDF();
            $pdf->AddPage();

            // Render Custom Logo on Letterhead if provided
            $local_logo = get_local_image_path($logo_path);
            if (!empty($local_logo)) {
                $pdf->Image($local_logo, 160, 15, 30);
            }

            // Header Branding
            $pdf->SetFont('Arial', 'B', 24);
            $pdf->SetTextColor(0, 74, 198); // Blue
            $pdf->Cell(0, 15, 'IMP', 0, 1, 'L');
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(0, 5, 'INTERNSHIP MANAGEMENT PLATFORM', 0, 1, 'L');
            $pdf->Ln(15);
            
            // Title
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 10, 'INTERNSHIP SELECTION CONFIRMATION LETTER', 0, 1, 'C');
            $pdf->Ln(5);
            
            // Details
            $pdf->SetFont('Arial', '', 11);
            $ref_no = 'IMP/' . date('Y') . '/' . str_pad($app_id, 4, '0', STR_PAD_LEFT);
            $pdf->Cell(0, 6, 'Reference No: ' . $ref_no, 0, 1);
            $pdf->Cell(0, 6, 'Date: ' . $resolved_selection_date, 0, 1);
            $pdf->Ln(5);
            
            $pdf->Cell(0, 6, 'Student Name: ' . $student_name, 0, 1);
            $pdf->Cell(0, 6, 'Student Email: ' . $student_email, 0, 1);
            $pdf->Cell(0, 6, 'Application ID: ' . $app_id, 0, 1);
            $pdf->Cell(0, 6, 'Internship Name: ' . $resolved_internship_name, 0, 1);
            $pdf->Cell(0, 6, 'Duration: ' . $resolved_duration, 0, 1);
            $pdf->Cell(0, 6, 'Mode: ' . $resolved_mode, 0, 1);
            $pdf->Cell(0, 6, 'Technology Stack: ' . $resolved_tech_stack, 0, 1);
            $pdf->Cell(0, 6, 'Status: SELECTED', 0, 1);
            $pdf->Ln(8);
            
            // Body Text Paragraphs
            $pdf->SetFont('Arial', '', 11);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->MultiCell(0, 6, $resolved_content);
            $pdf->Ln(15);
            
            // Signature block
            $pdf->SetFont('Arial', '', 11);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 6, 'Sincerely,', 0, 1);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, $sig_name, 0, 1);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 6, $sig_designation, 0, 1);
            $pdf->Ln(10);
            
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->SetTextColor(140, 140, 140);
            $pdf->Cell(0, 10, 'This is a system-generated confirmation letter. No physical signature is required.', 0, 1, 'C');

            $dir = __DIR__ . '/uploads/offer_letters';
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0777, true)) {
                    throw new Exception("Unable to create uploads folder: $dir");
                }
            }
            if (!is_writable($dir)) {
                @chmod($dir, 0777);
                if (!is_writable($dir)) {
                    throw new Exception("Uploads folder is not writable: $dir");
                }
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

            $student_subject = $resolved_subject;
            $student_message = "Dear $student_name,\n\nWe are pleased to inform you that you have been selected for the internship: \"$resolved_internship_name\". Please find your Confirmation Letter attached.\n\nNote: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator.\n\nBest regards,\nIMP Team";

            $errorOutput = '';
            $email_sent = sendEmailNotification($student_email, $student_subject, $student_message, [
                'recipient_name' => $student_name,
                'event' => 'Application Status Update',
                'internship' => $resolved_internship_name,
                'status' => 'Selected',
                'action_url' => $base_url . '/student_applications.php',
                'action_label' => 'View Application',
                'application_id' => $app_id,
                'attachments' => [
                    ['path' => $local_pdf_path, 'name' => $pdf_filename]
                ]
            ], $errorOutput);

            unset($GLOBALS['mail_options']);
            if (!$email_sent) {
                $failed++;
                $errors[] = [
                    'application_id' => $app_id,
                    'student_name' => $student_name,
                    'student_email' => $student_email,
                    'reason' => 'Email delivery failed: ' . $errorOutput
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

            $notif_msg = mysqli_real_escape_string($conn, "Your confirmation letter has been generated and sent for the internship: $resolved_internship_name.");
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
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'type' => 'error',
        'title' => 'Failed',
        'message' => 'An unexpected error occurred:\nReason: ' . $e->getMessage(),
        'total' => 0,
        'sent' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => []
    ]);
}
?>
