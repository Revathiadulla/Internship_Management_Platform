<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";
include_once __DIR__ . '/includes/mail_helper.php';
// Ensure extended schema (safe to include multiple times)
@include_once __DIR__ . '/ensure_extended_schema.php';

// Auto-migration: Ensure notifications table has 'role' column
try {
    $check_role = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'role'");
    if ($check_role && mysqli_num_rows($check_role) === 0) {
        mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'coordinator'");
    }
} catch (Throwable $e) {
    // Log or handle error silently
}

$unread_count = 0;
try {
    $notif_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND role = 'coordinator' AND is_read = 0");
    if ($notif_stmt) {
        $notif_stmt->bind_param("i", $_SESSION['user_id']);
        $notif_stmt->execute();
        $notif_res = $notif_stmt->get_result();
        if ($notif_row = $notif_res->fetch_assoc()) {
            $unread_count = intval($notif_row['count']);
        }
        $notif_stmt->close();
    }
} catch (Throwable $e) {
    $unread_count = 0;
}

$success_msg = "";
$error_msg = "";

function broadcastAutomatedTeamNotification($conn, $team_id, $team_name, $event_group, $subject, $message, $student_ids, $mentor_id) {
    if (!isset($_SESSION['user_id'])) return;
    $sender_id = $_SESSION['user_id'];
    $sender_role = 'coordinator';
    
    $recipients = [];
    foreach ($student_ids as $sid) {
        $recipients[] = ['id' => $sid, 'role' => 'student'];
    }
    if ($mentor_id > 0) {
        $recipients[] = ['id' => $mentor_id, 'role' => 'mentor'];
    }
    if (empty($recipients)) return;

    $any_fail = false;
    foreach ($recipients as $rec) {
        $res = sendManualMessage($sender_id, $sender_role, $rec['id'], $rec['role'], $subject, $message, true, true);
        if ($res['email_status'] === 'failed') {
            $any_fail = true;
        }
    }
    $status = $any_fail ? 'Partial/Failed' : 'Sent';

    $stmt = mysqli_prepare($conn, "INSERT INTO message_logs (sender_id, sender_role, receiver_id, receiver_role, team_id, recipient_group, subject, message, send_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $receiver_id = 0;
        $receiver_role = 'team';
        $send_type = 'both';
        $logged_group = "Automated: " . $event_group;
        mysqli_stmt_bind_param($stmt, "isisisssss", $sender_id, $sender_role, $receiver_id, $receiver_role, $team_id, $logged_group, $subject, $message, $send_type, $status);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Process POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'send_message') {
        $team_id = intval($_POST['team_id'] ?? null);
        $recipient_group = trim($_POST['recipient_group'] ?? null);
        $subject = trim($_POST['subject'] ?? null);
        $message = trim($_POST['message'] ?? null);
        $send_type = trim($_POST['send_type'] ?? null); // 'in-app', 'email', 'both'

        if ($team_id <= 0 || empty($recipient_group) || empty($subject) || empty($message) || empty($send_type)) {
            $error_msg = "Please fill in all required message fields.";
        } else {
            $sender_id = $_SESSION['user_id'];
            $sender_role = 'coordinator';

            // Gather recipient IDs
            $recipients = []; // Array of ['id' => X, 'role' => 'student'/'mentor']
            
            // Get squad's mentor ID and validate ownership
            $mentor_id = null;
            $team_query = mysqli_query($conn, "
                SELECT pt.mentor_id 
                FROM project_teams pt
                JOIN internships i ON pt.internship_id = i.id
                WHERE pt.id = $team_id AND i.coordinator_id = $sender_id
                LIMIT 1
            ");
            if ($team_query && $team_row = mysqli_fetch_assoc($team_query)) {
                $mentor_id = $team_row['mentor_id'];
            } else {
                $error_msg = "Invalid team or you do not have permission to send messages to this squad.";
            }

            // Get squad's students
            $student_ids = [];
            if (empty($error_msg)) {
                $students_query = mysqli_query($conn, "SELECT student_id FROM project_team_members WHERE project_team_id = $team_id");
                if ($students_query) {
                    while ($st = mysqli_fetch_assoc($students_query)) {
                        $student_ids[] = intval($st['student_id']);
                    }
                }
            }

            if (empty($error_msg)) {
                if ($recipient_group === 'all_students' || $recipient_group === 'mentor_and_students') {
                    foreach ($student_ids as $sid) {
                        $recipients[] = ['id' => $sid, 'role' => 'student'];
                    }
                }
                if ($recipient_group === 'mentor' || $recipient_group === 'mentor_and_students') {
                    if ($mentor_id) {
                        $recipients[] = ['id' => $mentor_id, 'role' => 'mentor'];
                    }
                }

                if (empty($recipients)) {
                    $error_msg = "No recipients found for the selected group.";
                } else {
                    $status = 'Sent';
                    $sendNotification = in_array($send_type, ['in-app', 'both']);
                    $sendEmail = in_array($send_type, ['email', 'both']);
                    
                    $any_fail = false;
                    foreach ($recipients as $rec) {
                        $result = sendManualMessage($sender_id, $sender_role, $rec['id'], $rec['role'], $subject, $message, $sendNotification, $sendEmail);
                        if ($sendEmail && $result['email_status'] === 'failed') {
                            $any_fail = true;
                        }
                    }
                    
                    if ($any_fail) {
                        $status = 'Partial/Failed';
                        $error_msg = "In-app message sent, but email delivery failed for some or all recipients.";
                    } else {
                        $success_msg = "Message sent successfully to " . count($recipients) . " recipient(s).";
                    }

                    // Log as a single broadcast record in message_logs
                    $stmt = mysqli_prepare($conn, "INSERT INTO message_logs (sender_id, sender_role, receiver_id, receiver_role, team_id, recipient_group, subject, message, send_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $receiver_id = 0; // 0 for broadcast
                        $receiver_role = 'team';
                        // Map internal code to readable name for UI
                        $logged_group = match($recipient_group) {
                            'all_students' => 'All Squad Members',
                            'mentor' => 'Assigned Mentor',
                            'mentor_and_students' => 'Mentor + All Squad Members',
                            default => $recipient_group
                        };
                        mysqli_stmt_bind_param($stmt, "isisisssss", $sender_id, $sender_role, $receiver_id, $receiver_role, $team_id, $logged_group, $subject, $message, $send_type, $status);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    } else {
        // Create/Edit/Delete Team Actions
        $team_name = isset($_POST['team_name']) ? trim($_POST['team_name']) : '';
        $internship_id = isset($_POST['internship_id']) ? intval($_POST['internship_id']) : 0;
        $mentor_id = isset($_POST['mentor_id']) ? intval($_POST['mentor_id']) : 0;
        $team_status = isset($_POST['team_status']) ? trim($_POST['team_status']) : '';
        $students = isset($_POST['students']) ? $_POST['students'] : [];

    if (empty($team_name) || $internship_id <= 0 || $mentor_id <= 0) {
        $error_msg = "Please fill in all required fields.";
    } else {
        mysqli_begin_transaction($conn);
        $success = true;
        $last_db_err = '';
        $last_query = '';

        // Detect whether internship_applications stores project type/subtype and assignment tracking columns
        $app_has_project_type = false;
        $app_has_project_subtype = false;
        $app_has_assigned_project_id = false;
        $app_has_team_id = false;
        $check_app_type = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'project_type'");
        if ($check_app_type && mysqli_num_rows($check_app_type) > 0) {
            $app_has_project_type = true;
        }
        $check_app_subtype = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'project_subtype'");
        if ($check_app_subtype && mysqli_num_rows($check_app_subtype) > 0) {
            $app_has_project_subtype = true;
        }
        $check_assigned_project_id = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'assigned_project_id'");
        if ($check_assigned_project_id && mysqli_num_rows($check_assigned_project_id) > 0) {
            $app_has_assigned_project_id = true;
        }
        $check_team_id = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE 'team_id'");
        if ($check_team_id && mysqli_num_rows($check_team_id) > 0) {
            $app_has_team_id = true;
        }

        // Helper to execute prepared statements and capture error details
        function _capture_execute($stmt) {
            global $conn, $last_db_err, $last_query;
            if (!$stmt) {
                $last_db_err = mysqli_error($conn);
                return false;
            }
            if (!mysqli_stmt_execute($stmt)) {
                $s_err = mysqli_stmt_error($stmt);
                $c_err = mysqli_error($conn);
                $last_db_err = trim(($s_err ?: '') . ' ' . ($c_err ?: ''));
                return false;
            }
            return true;
        }

        // Logging helper for team save errors
        function _log_team_error($data) {
            $log_dir = __DIR__ . '/logs';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }
            $log_file = $log_dir . '/team_save_errors.log';
            global $conn;
            if (empty($data['mysql_error'])) {
                $data['mysql_error'] = mysqli_error($conn);
            }
            $data['mysql_errno'] = mysqli_errno($conn);
            // include raw POST for easier debugging (avoid sensitive fields)
            $data['post'] = isset($_POST) ? $_POST : null;
            $entry = date('Y-m-d H:i:s') . " | " . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
        }

        function _prepare_application_assignment_update_stmt($student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $project_team_id) {
            global $conn, $app_has_project_type, $app_has_project_subtype, $app_has_assigned_project_id, $app_has_team_id, $last_query;
            $set_parts = [
                'team_name = ?',
                'mentor_id = ?',
                'team_status = ?',
                'status = ?',
                'internship_status = ?'
            ];
            $types = 'sisss';
            $values = [$team_name, $mentor_id, $team_status, 'Active Intern', 'Active'];

            if ($app_has_project_type) {
                $set_parts[] = 'project_type = ?';
                $types .= 's';
                $values[] = $project_type;
            }
            if ($app_has_project_subtype) {
                $set_parts[] = 'project_subtype = ?';
                $types .= 's';
                $values[] = $project_subtype;
            }
            if ($app_has_assigned_project_id) {
                $set_parts[] = 'assigned_project_id = ?';
                $types .= 'i';
                $values[] = $internship_id;
            }
            if ($app_has_team_id) {
                $set_parts[] = 'team_id = ?';
                $types .= 'i';
                $values[] = $project_team_id;
            }

            $sql = 'UPDATE internship_applications SET ' . implode(', ', $set_parts) . ' WHERE user_id = ? AND internship_id = ?';
            $last_query = $sql;
            $types .= 'ii';
            $values[] = $student_id;
            $values[] = $internship_id;

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$values);
            }
            return $stmt;
        }

        function _prepare_application_assignment_insert_stmt($student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $project_team_id) {
            global $conn, $app_has_project_type, $app_has_project_subtype, $app_has_assigned_project_id, $app_has_team_id, $last_query;
            $columns = [
                'user_id',
                'internship_id',
                'status',
                'internship_status',
                'team_name',
                'mentor_id',
                'team_status'
            ];
            $placeholders = ['?', '?', '?', '?', '?', '?', '?'];
            $types = 'iisssis';
            $values = [$student_id, $internship_id, 'Active Intern', 'Active', $team_name, $mentor_id, $team_status];

            if ($app_has_project_type) {
                $columns[] = 'project_type';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $project_type;
            }
            if ($app_has_project_subtype) {
                $columns[] = 'project_subtype';
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $project_subtype;
            }
            if ($app_has_assigned_project_id) {
                $columns[] = 'assigned_project_id';
                $placeholders[] = '?';
                $types .= 'i';
                $values[] = $internship_id;
            }
            if ($app_has_team_id) {
                $columns[] = 'team_id';
                $placeholders[] = '?';
                $types .= 'i';
                $values[] = $project_team_id;
            }

            $sql = 'INSERT INTO internship_applications (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $last_query = $sql;
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$values);
            }
            return $stmt;
        }

        $project_type = isset($_POST['project_type']) ? trim($_POST['project_type']) : '';
        $project_subtype = isset($_POST['project_subtype']) ? trim($_POST['project_subtype']) : '';

            if ($action !== 'delete_team' && (empty($project_type) || empty($project_subtype))) {
            $proj_stmt = mysqli_prepare($conn, "SELECT project_type, project_subtype FROM internships WHERE id = ?");
            mysqli_stmt_bind_param($proj_stmt, "i", $internship_id);
            if (_capture_execute($proj_stmt)) {
                mysqli_stmt_bind_result($proj_stmt, $db_type, $db_subtype);
                if (mysqli_stmt_fetch($proj_stmt)) {
                    $project_type = $db_type ?: '';
                    $project_subtype = $db_subtype ?: '';
                }
            } else {
                $success = false;
            }
            mysqli_stmt_close($proj_stmt);
        }

        if ($action === 'create_team') {
            // Check if team name already exists
            $check_team_sql = "SELECT id FROM internship_applications WHERE team_name = ? LIMIT 1";
            $last_query = $check_team_sql;
            $check_team_stmt = mysqli_prepare($conn, $check_team_sql);
            if ($check_team_stmt) {
                mysqli_stmt_bind_param($check_team_stmt, "s", $team_name);
                if (_capture_execute($check_team_stmt)) {
                    mysqli_stmt_store_result($check_team_stmt);
                    if (mysqli_stmt_num_rows($check_team_stmt) > 0) {
                        $error_msg = "A team with name '" . htmlspecialchars($team_name) . "' already exists.";
                        $success = false;
                    }
                } else {
                    $success = false;
                }
                mysqli_stmt_close($check_team_stmt);
            } else {
                $last_db_err = mysqli_error($conn);
                $success = false;
            }

            if ($action !== 'delete_team') {
            // Verify the selected internship/project is Active (admin-approved)
            $proj_status_sql = "SELECT status, approval_status FROM internships WHERE id = " . intval($internship_id) . " LIMIT 1";
            $last_query = $proj_status_sql;
            $proj_status_res = mysqli_query($conn, $proj_status_sql);
            if ($proj_status_res === false) {
                $last_db_err = mysqli_error($conn);
                $success = false;
            } else {
                $proj_status_row = mysqli_fetch_assoc($proj_status_res);
                $raw_status = trim((string)($proj_status_row['status'] ?? ''));
                $raw_approval = trim((string)($proj_status_row['approval_status'] ?? ''));
                $allowed_status = preg_match('/\b(active|approved|admin-approved|admin approved)\b/i', $raw_status);
                $allowed_approval = preg_match('/\b(approved|admin-approved|admin approved)\b/i', $raw_approval);
                if ($raw_status === '' && $raw_approval === '') {
                    $error_msg = "Cannot create a team for this project. Project status is unknown. Please wait for Admin approval.";
                    _log_team_error(['note' => 'empty_project_status', 'internship_id' => $internship_id]);
                    $success = false;
                } elseif (!($allowed_status || $allowed_approval)) {
                    // Log the raw status and approval_status for debugging
                    _log_team_error([
                        'note' => 'project_not_active_or_approved',
                        'internship_id' => $internship_id,
                        'project_status' => $raw_status,
                        'approval_status' => $raw_approval
                    ]);
                    $current_status = $raw_status ?: $raw_approval ?: 'Unknown';
                    $error_msg = "Cannot create a team for this project. This project is not yet Active (Admin-approved). Current status: " . htmlspecialchars($current_status) . ". Please wait for Admin approval.";
                    $success = false;
                }
            }
        }

            // Prepare to create a project_teams record so we can insert members
            $project_team_id = null;
            if ($success) {
                $team_ins_sql = "INSERT INTO project_teams (team_name, project_type, project_subtype, internship_id, mentor_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $last_query = $team_ins_sql;
                $team_ins_stmt = mysqli_prepare($conn, $team_ins_sql);
                if ($team_ins_stmt) {
                    mysqli_stmt_bind_param($team_ins_stmt, "sssiisi", $team_name, $project_type, $project_subtype, $internship_id, $mentor_id, $team_status, $_SESSION['user_id']);
                    if (!_capture_execute($team_ins_stmt)) {
                        $success = false;
                    } else {
                        $project_team_id = mysqli_insert_id($conn);
                    }
                    mysqli_stmt_close($team_ins_stmt);
                } else {
                    $last_db_err = mysqli_error($conn);
                    $success = false;
                }
            }

            if ($success) {
                $project_title = '';
                $proj_title_res = mysqli_query($conn, "SELECT title FROM internships WHERE id = " . intval($internship_id) . " LIMIT 1");
                if ($proj_title_res) {
                    $proj_title_row = mysqli_fetch_assoc($proj_title_res);
                    $project_title = $proj_title_row['title'] ?? '';
                }

                // Assign new team to selected students
                foreach ($students as $student_id) {
                    $student_id = intval($student_id);
                    // Check if student already has application for this project
                    $app_chk_sql = "SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1";
                    $last_query = $app_chk_sql;
                    $app_chk_stmt = mysqli_prepare($conn, $app_chk_sql);
                    mysqli_stmt_bind_param($app_chk_stmt, "ii", $student_id, $internship_id);
                    if (!_capture_execute($app_chk_stmt)) {
                        $success = false;
                    }
                    $app_chk_res = mysqli_stmt_get_result($app_chk_stmt);
                    
                    if ($app_row = mysqli_fetch_assoc($app_chk_res)) {
                        $update_stmt = _prepare_application_assignment_update_stmt($student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $project_team_id);
                        if ($update_stmt) {
                            if (!_capture_execute($update_stmt)) {
                                $success = false;
                            }
                            mysqli_stmt_close($update_stmt);
                        } else {
                            $success = false;
                        }
                    } else {
                        $insert_stmt = _prepare_application_assignment_insert_stmt($student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $project_team_id);
                        if ($insert_stmt) {
                            if (!_capture_execute($insert_stmt)) {
                                $success = false;
                            }
                            mysqli_stmt_close($insert_stmt);
                        } else {
                            $success = false;
                        }
                    }


                    // Also insert into project_team_members if we have a project_team_id
                    if ($success && !empty($project_team_id)) {
                        $member_sql = "INSERT INTO project_team_members (project_team_id, student_id) VALUES (?, ?)";
                        $last_query = $member_sql;
                        $member_stmt = mysqli_prepare($conn, $member_sql);
                        if ($member_stmt) {
                            mysqli_stmt_bind_param($member_stmt, "ii", $project_team_id, $student_id);
                            if (!_capture_execute($member_stmt)) {
                                // If duplicate entry, ignore; otherwise mark failure
                                if (mysqli_errno($conn) !== 1062) {
                                    $success = false;
                                }
                            }
                            mysqli_stmt_close($member_stmt);
                        } else {
                            $success = false;
                        }
                    }
                    mysqli_stmt_close($app_chk_stmt);
                }
            }

        } elseif ($action === 'edit_team') {
            $old_team_name = trim($_POST['old_team_name']);

            // 1. Clear previous assignments for this team name
            $clear_columns = [
                "team_name = NULL",
                "mentor_id = NULL",
                "team_status = 'Active'",
                "internship_status = 'Pending'"
            ];
            if ($app_has_assigned_project_id) {
                $clear_columns[] = "assigned_project_id = NULL";
            }
            if ($app_has_team_id) {
                $clear_columns[] = "team_id = NULL";
            }
            $clear_sql = "UPDATE internship_applications SET " . implode(', ', $clear_columns) . " WHERE team_name = ?";
            $last_query = $clear_sql;
            $clear_stmt = mysqli_prepare($conn, $clear_sql);
            if ($clear_stmt) {
                mysqli_stmt_bind_param($clear_stmt, "s", $old_team_name);
                if (!_capture_execute($clear_stmt)) {
                    $success = false;
                }
                mysqli_stmt_close($clear_stmt);
            } else {
                $last_db_err = mysqli_error($conn);
                $success = false;
            }

            // Locate or create the corresponding project_teams row, and clear its members
            $project_team_id = null;
            $find_team_sql = "SELECT id FROM project_teams WHERE team_name = ? AND internship_id = ? LIMIT 1";
            $last_query = $find_team_sql;
            $find_team_stmt = mysqli_prepare($conn, $find_team_sql);
            if ($find_team_stmt) {
                mysqli_stmt_bind_param($find_team_stmt, "si", $old_team_name, $internship_id);
                if (_capture_execute($find_team_stmt)) {
                    $find_res = mysqli_stmt_get_result($find_team_stmt);
                    if ($r = mysqli_fetch_assoc($find_res)) {
                        $project_team_id = intval($r['id']);
                        // Update metadata
                        $upd_team_sql = "UPDATE project_teams SET team_name = ?, project_type = ?, project_subtype = ?, mentor_id = ?, status = ? WHERE id = ?";
                        $last_query = $upd_team_sql;
                        $upd_team_stmt = mysqli_prepare($conn, $upd_team_sql);
                        if ($upd_team_stmt) {
                            mysqli_stmt_bind_param($upd_team_stmt, "sssisi", $team_name, $project_type, $project_subtype, $mentor_id, $team_status, $project_team_id);
                            if (!_capture_execute($upd_team_stmt)) {
                                    $success = false;
                                }
                            mysqli_stmt_close($upd_team_stmt);
                        }
                        // Clear existing members
                        mysqli_query($conn, "DELETE FROM project_team_members WHERE project_team_id = " . intval($project_team_id));
                    } else {
                        // Insert a new project_teams row
                        $ins_team_sql = "INSERT INTO project_teams (team_name, project_type, project_subtype, internship_id, mentor_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $last_query = $ins_team_sql;
                        $ins_team_stmt = mysqli_prepare($conn, $ins_team_sql);
                        if ($ins_team_stmt) {
                            mysqli_stmt_bind_param($ins_team_stmt, "sssiisi", $team_name, $project_type, $project_subtype, $internship_id, $mentor_id, $team_status, $_SESSION['user_id']);
                            if (!_capture_execute($ins_team_stmt)) {
                                $success = false;
                            } else {
                                $project_team_id = mysqli_insert_id($conn);
                            }
                            mysqli_stmt_close($ins_team_stmt);
                        } else {
                            $success = false;
                        }
                    }
                } else {
                    $success = false;
                }
                mysqli_stmt_close($find_team_stmt);
            } else {
                $success = false;
            }

            // 2. Re-assign to selected students
            $project_title = '';
            $proj_title_res = mysqli_query($conn, "SELECT title FROM internships WHERE id = " . intval($internship_id) . " LIMIT 1");
            if ($proj_title_res) {
                $proj_title_row = mysqli_fetch_assoc($proj_title_res);
                $project_title = $proj_title_row['title'] ?? '';
            }

            if ($success) {
                foreach ($students as $student_id) {
                    $student_id = intval($student_id);
                    // Check if student has application
                    $app_chk_sql = "SELECT id FROM internship_applications WHERE user_id = ? AND internship_id = ? LIMIT 1";
                    $app_chk_stmt = mysqli_prepare($conn, $app_chk_sql);
                    mysqli_stmt_bind_param($app_chk_stmt, "ii", $student_id, $internship_id);
                    if (!_capture_execute($app_chk_stmt)) {
                        $success = false;
                    }
                    $app_chk_res = mysqli_stmt_get_result($app_chk_stmt);
                    
                    if ($app_row = mysqli_fetch_assoc($app_chk_res)) {
                        $update_stmt = _prepare_application_assignment_update_stmt($student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $project_team_id);
                        if ($update_stmt) {
                            if (!_capture_execute($update_stmt)) {
                                $success = false;
                            }
                            mysqli_stmt_close($update_stmt);
                        } else {
                            $success = false;
                        }
                    } else {
                        $insert_stmt = _prepare_application_assignment_insert_stmt($student_id, $internship_id, $team_name, $mentor_id, $team_status, $project_type, $project_subtype, $project_team_id);
                        if ($insert_stmt) {
                            if (!_capture_execute($insert_stmt)) {
                                $success = false;
                            }
                            mysqli_stmt_close($insert_stmt);
                        } else {
                            $success = false;
                        }
                    }


                    // Also insert into project_team_members for this team (if available)
                    if ($success && !empty($project_team_id)) {
                        $member_sql = "INSERT INTO project_team_members (project_team_id, student_id) VALUES (?, ?)";
                        $member_stmt = mysqli_prepare($conn, $member_sql);
                        if ($member_stmt) {
                            mysqli_stmt_bind_param($member_stmt, "ii", $project_team_id, $student_id);
                            if (!_capture_execute($member_stmt)) {
                                if (mysqli_errno($conn) !== 1062) {
                                    $success = false;
                                }
                            }
                            mysqli_stmt_close($member_stmt);
                        } else {
                            $success = false;
                        }
                    }
                    mysqli_stmt_close($app_chk_stmt);
                }
            }
        } elseif ($action === 'delete_team') {
            $del_team_name = trim($_POST['team_name']);
            
            // Fetch team members before clearing
            $del_mentor_id = 0;
            $del_student_ids = [];
            $del_team_id = null;
            $find_team_res = mysqli_query($conn, "SELECT id, mentor_id FROM project_teams WHERE team_name = '" . mysqli_real_escape_string($conn, $del_team_name) . "' LIMIT 1");
            if ($find_team_res && $row = mysqli_fetch_assoc($find_team_res)) {
                $del_team_id = $row['id'];
                $del_mentor_id = intval($row['mentor_id']);
                $members_res = mysqli_query($conn, "SELECT student_id FROM project_team_members WHERE project_team_id = " . $del_team_id);
                while ($m = mysqli_fetch_assoc($members_res)) {
                    $del_student_ids[] = intval($m['student_id']);
                }
            }

            // Send dissolve notification
            if (!empty($del_team_id)) {
                broadcastAutomatedTeamNotification($conn, $del_team_id, $del_team_name, 'Team Dissolved', "Squad Dissolved: $del_team_name", "Your squad '$del_team_name' has been dissolved. Please wait for further coordinator instructions.", $del_student_ids, $del_mentor_id);
            }

            $clear_columns = [
                "team_name = NULL",
                "mentor_id = NULL",
                "team_status = 'Active'"
            ];
            if ($app_has_assigned_project_id) {
                $clear_columns[] = "assigned_project_id = NULL";
            }
            if ($app_has_team_id) {
                $clear_columns[] = "team_id = NULL";
            }
            $clear_sql = "UPDATE internship_applications SET " . implode(', ', $clear_columns) . " WHERE team_name = ?";
            $last_query = $clear_sql;
            $clear_stmt = mysqli_prepare($conn, $clear_sql);
            if ($clear_stmt) {
                mysqli_stmt_bind_param($clear_stmt, "s", $del_team_name);
                if (!_capture_execute($clear_stmt)) {
                    $success = false;
                }
                mysqli_stmt_close($clear_stmt);
            } else {
                $last_db_err = mysqli_error($conn);
                $success = false;
            }
        }

        if ($success) {
            if ($action === 'create_team' || $action === 'edit_team') {
                if (!empty($project_team_id)) {
                    $event_name = ($action === 'create_team') ? 'Team Created' : 'Team Updated';
                    $subject = "Squad Assignment: $team_name";
                    $msg = "You have been assigned to the squad '$team_name' for the project '$project_title'. Please check your dashboard for details and next steps.";
                    broadcastAutomatedTeamNotification($conn, $project_team_id, $team_name, $event_name, $subject, $msg, $students, $mentor_id);
                }
            }
            mysqli_commit($conn);
            if ($action === 'create_team') {
                $success_msg = "Project team created successfully.";
            } elseif ($action === 'edit_team') {
                $success_msg = "Project team updated successfully.";
            } elseif ($action === 'delete_team') {
                $success_msg = "Project team deleted successfully.";
            } else {
                $success_msg = "Team assignment operation successful!";
            }
        } else {
            // Capture DB error and log details
            $db_err = '';
            if (!empty($last_db_err)) {
                $db_err = $last_db_err;
            } else {
                $db_err = mysqli_error($conn);
            }
            // Log details to file for debugging
            _log_team_error([
                'query' => $last_query,
                'mysql_error' => $db_err,
                'internship_id' => $internship_id ?? null,
                'mentor_id' => $mentor_id ?? null,
                'students' => isset($students) ? $students : null,
            ]);
            mysqli_rollback($conn);
            if (!empty($db_err)) {
                $error_msg = "Database error executing team operations. MySQL error: " . htmlspecialchars($db_err);
            } elseif (!empty($error_msg)) {
                // Preserve earlier non-DB error message (e.g., validation)
                // $error_msg already set
            } else {
                $error_msg = "Database error executing team operations.";
            }
        }
    }
}
}

// Fetch Search Query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_clause = "";
$search_params = [];
$search_types = "";
if (!empty($search)) {
    $search_clause = " AND (a.team_name LIKE ? OR m.full_name LIKE ? OR i.title LIKE ?) ";
    $search_param = "%" . $search . "%";
    $search_params = [$search_param, $search_param, $search_param];
    $search_types = "sss";
}

$coord_id = intval($_SESSION['user_id']);

// Fetch all project teams
$teams_sql = "
    SELECT DISTINCT pt.id, a.team_name, a.mentor_id, a.internship_id, a.team_status, 
                    m.full_name as mentor_name, m.email as mentor_email, m.phone as mentor_phone,
                    i.title as project_title
    FROM internship_applications a
    LEFT JOIN users m ON a.mentor_id = m.id
    LEFT JOIN internships i ON a.internship_id = i.id
    LEFT JOIN project_teams pt ON a.team_name = pt.team_name AND a.internship_id = pt.internship_id
    WHERE a.team_name IS NOT NULL AND a.team_name != '' AND i.coordinator_id = ? " . $search_clause . "
    ORDER BY a.team_name ASC
";
$teams_stmt = mysqli_prepare($conn, $teams_sql);
if (!empty($search)) {
    $bind_types = "i" . $search_types;
    $bind_params = array_merge([$coord_id], $search_params);
    mysqli_stmt_bind_param($teams_stmt, $bind_types, ...$bind_params);
} else {
    mysqli_stmt_bind_param($teams_stmt, "i", $coord_id);
}
mysqli_stmt_execute($teams_stmt);
$teams_res = mysqli_stmt_get_result($teams_stmt);
$teams_list = [];
while ($row = mysqli_fetch_assoc($teams_res)) {
    $teams_list[] = $row;
}
mysqli_stmt_close($teams_stmt);

// Fetch all active students and their applied internships
$students_sql = "
    SELECT u.id, u.full_name, u.email, sp.college_name,
           a.internship_id, a.team_name as assigned_team,
           i.project_subtype as applied_subtype
    FROM users u
    JOIN internship_applications a ON u.id = a.user_id
    JOIN internships i ON a.internship_id = i.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.role = 'student' AND i.coordinator_id = $coord_id
    ORDER BY u.full_name ASC
";
$students_res = mysqli_query($conn, $students_sql);
$students_list = [];
while ($row = mysqli_fetch_assoc($students_res)) {
    $students_list[] = $row;
}

// Fetch all mentors
$mentors_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role='mentor' ORDER BY full_name ASC");
$mentors_list = [];
while ($row = mysqli_fetch_assoc($mentors_res)) {
    $mentors_list[] = $row;
}

// Fetch all active admin-created project types
$project_types_res = mysqli_query($conn, "SELECT pt.id, pt.type_name FROM project_types pt JOIN coordinator_assignments ca ON pt.id = ca.project_type_id WHERE pt.status = 'Active' AND ca.coordinator_id = $coord_id ORDER BY pt.type_name ASC");
$project_types = [];
while ($row = mysqli_fetch_assoc($project_types_res)) {
    $project_types[] = $row;
}

// Fetch all active admin-created project subtypes with their parent type
$project_subtypes_res = mysqli_query($conn, "SELECT s.id, s.project_type_id, s.subtype_name, t.type_name FROM project_subtypes s JOIN project_types t ON s.project_type_id = t.id JOIN coordinator_assignments ca ON t.id = ca.project_type_id WHERE s.status = 'Active' AND t.status = 'Active' AND ca.coordinator_id = $coord_id ORDER BY t.type_name ASC, s.subtype_name ASC");
$project_subtypes = [];
while ($row = mysqli_fetch_assoc($project_subtypes_res)) {
    $project_subtypes[] = $row;
}

// Fetch all internships/projects
// Filter by exact project_type and project_subtype stored in internships table, and by coordinator_id.
$projects_query = "SELECT i.id, i.title, i.project_title, i.task_title, i.project_type, i.project_subtype, i.technology_stack, i.duration, i.start_date, i.end_date, COALESCE(s.mode, '') AS mode FROM internships i LEFT JOIN project_subtypes s ON i.project_subtype COLLATE utf8mb4_general_ci = s.subtype_name COLLATE utf8mb4_general_ci WHERE i.coordinator_id = $coord_id ORDER BY i.project_type ASC, i.project_subtype ASC, i.title ASC";
$projects_res = mysqli_query($conn, $projects_query);
$projects_list = [];
while ($row = mysqli_fetch_assoc($projects_res)) {
    $projects_list[] = $row;
}
$projects_query_debug = $projects_query;

// Fetch assigned project IDs to filter dropdown
$assigned_res = mysqli_query($conn, "SELECT DISTINCT a.internship_id FROM internship_applications a JOIN internships i ON a.internship_id = i.id WHERE a.team_name IS NOT NULL AND a.team_name != '' AND i.coordinator_id = $coord_id");
$assigned_project_ids = [];
while ($row = mysqli_fetch_assoc($assigned_res)) {
    $assigned_project_ids[] = intval($row['internship_id']);
}

// Fetch Message Logs for this coordinator
$msg_sql = "
    SELECT ml.id, ml.receiver_id, ml.subject, ml.message, ml.send_type, ml.status, ml.created_at,
           u.full_name as student_name, u.email as student_email
    FROM message_logs ml
    JOIN users u ON ml.receiver_id = u.id
    WHERE ml.sender_id = $coord_id AND ml.sender_role = 'coordinator' AND ml.receiver_role = 'student'
    ORDER BY ml.created_at DESC
";
$msg_res = mysqli_query($conn, $msg_sql);
$message_logs = [];
if ($msg_res) {
    while ($row = mysqli_fetch_assoc($msg_res)) {
        $message_logs[] = $row;
    }
}

// Fetch Team Message Logs
$team_msg_sql = "
    SELECT ml.id, ml.team_id, ml.recipient_group, ml.subject, ml.message, ml.send_type, ml.status, ml.created_at,
           pt.team_name
    FROM message_logs ml
    LEFT JOIN project_teams pt ON ml.team_id = pt.id
    WHERE ml.sender_id = $coord_id AND ml.sender_role = 'coordinator' AND ml.receiver_role = 'team'
    ORDER BY ml.created_at DESC
";
$team_msg_res = mysqli_query($conn, $team_msg_sql);
$team_message_logs = [];
if ($team_msg_res) {
    while ($row = mysqli_fetch_assoc($team_msg_res)) {
        if (!isset($team_message_logs[$row['team_id']])) {
            $team_message_logs[$row['team_id']] = [];
        }
        $team_message_logs[$row['team_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Team Assignment - Coordinator</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; color: #191c1d; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        aside {
                transition: transform 0.3s ease-in-out;
        }
        main {
                transition: margin-left 0.3s ease-in-out;
                min-width: 0;
                overflow-x: hidden;
        }
        @media (max-width: 767px) {
                aside {
                        transform: translateX(-100%);
                }
                main {
                        margin-left: 0 !important;
                }
                body.sidebar-open aside {
                        transform: translateX(0);
                }
        }
        @media (min-width: 768px) {
                body.sidebar-closed aside {
                        transform: translateX(-100%);
                }
                body.sidebar-closed main {
                        margin-left: 0 !important;
                }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
        <!-- ════════════════ SIDEBAR ════════════════ -->
        <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-white border-r border-gray-200 flex flex-col py-6">
                <div class="px-6 mb-8">
                        <a href="index.html" class="flex items-center gap-2">
                                <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none">
                                        <rect width="32" height="32" rx="8" fill="currentColor"/>
                                        <circle cx="16" cy="16" r="3" fill="white"/>
                                        <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
                                        <circle cx="16" cy="8" r="1.5" fill="white"/>
                                        <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                                        <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
                                        <line x1="17.8" y1="18.4" x2="20" y2="21.5" stroke="white" stroke-width="1.5"/>
                                        <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
                                        <line x1="14.2" y1="18.4" x2="12" y2="21.5" stroke="white" stroke-width="1.5"/>
                                        <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
                                        <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                                        <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
                                </svg>
                                <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
                        </a>
                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-2 ml-0.5">Coordinator Portal</p>
                </div>
                <nav class="flex-1 space-y-0.5 px-3">
                        <a href="coordinator_dashboard.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">dashboard</span> Dashboard
                        </a>
                        <a href="coordinator_internships.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">work</span> Postings
                        </a>
                        <a href="coordinator_candidates.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">group</span> Candidates
                        </a>
                        <a href="coordinator_generate_test.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">quiz</span> Generate Test
                        </a>
                        <a href="coordinator_daily_logs.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">monitoring</span> Daily Logs
                        </a>
                        <a href="coordinator_reports.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">analytics</span> Reports
                        </a>
                        <a href="coordinator_teams.php" class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-3 py-2.5 rounded-r-lg text-sm font-semibold">
                                <span class="material-symbols-outlined text-[20px]">manage_accounts</span> Teams
                        </a>
                </nav>
                <div class="border-t border-gray-200 pt-3 px-3 space-y-0.5">
                        <a href="coordinator_profile.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">account_circle</span> My Profile
                        </a>
                        <a href="coordinator_help_center.php" class="flex items-center gap-3 text-gray-600 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">help</span> Help Center
                        </a>
                        <a href="logout.php" class="flex items-center gap-3 text-red-650 px-3 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                                <span class="material-symbols-outlined text-[20px] text-red-400">logout</span> Logout
                        </a>
                </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-60 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <?php
                $header_uid = $_SESSION['user_id'];
                $header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
                $header_user = mysqli_fetch_assoc($header_res);
                $header_name = $header_user['full_name'] ?? 'Coordinator';
                $header_photo = $header_user['profile_photo'] ?? '';
                ?>
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                        <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">Team Management</h2>
                        </div>
                        
                        <div class="flex items-center gap-6">
                                <!-- Notifications Bell -->
                                <a href="coordinator_notifications.php" class="p-2 text-gray-500 hover:bg-gray-50 transition-colors rounded-full relative">
                                        <span class="material-symbols-outlined">notifications</span>
                                        <?php if ($unread_count > 0): ?>
                                                <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[9px] font-bold"><?php echo $unread_count; ?></span>
                                        <?php endif; ?>
                                </a>

                                <!-- Profile Dropdown Section -->
                                <div class="relative" id="profile-container">
                                        <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                                                <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline-block">
                                                        <?php echo htmlspecialchars($header_name); ?>
                                                </span>
                                                <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-500 transition-colors">
                                                        <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
                                                                <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
                                                        <?php else: ?>
                                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=0D8ABC&color=fff" alt="Profile" class="w-full h-full object-cover">
                                                        <?php endif; ?>
                                                </div>
                                                <span class="material-symbols-outlined text-gray-500 text-[18px] group-hover:text-blue-600 transition-colors">arrow_drop_down</span>
                                        </button>
                                        
                                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                                                <a href="coordinator_profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                        <span class="material-symbols-outlined text-gray-400 text-[20px]">account_circle</span>
                                                        <span>My Profile</span>
                                                </a>
                                                <a href="coordinator_profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                        <span class="material-symbols-outlined text-gray-400 text-[20px]">settings</span>
                                                        <span>Settings</span>
                                                </a>
                                                <hr class="my-1 border-gray-100">
                                                <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                        <span class="material-symbols-outlined text-red-400 text-[20px]">logout</span>
                                                        <span>Logout</span>
                                                </a>
                                        </div>
                                </div>
                        </div>
                </header>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                        const profileBtn = document.getElementById('profile-menu-button');
                        const profileDropdown = document.getElementById('profile-dropdown');
                        
                        if (profileBtn && profileDropdown) {
                                profileBtn.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        profileDropdown.classList.toggle('hidden');
                                });
                                
                                document.addEventListener('click', function(e) {
                                        if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                                                profileDropdown.classList.add('hidden');
                                        }
                                });
                        }
                });
                </script>


                <div class="flex-1 p-8 space-y-6">
                
                <?php if ($success_msg): ?>
                    <div class="p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2 alert-success">
                        <span class="material-symbols-outlined text-green-500">check_circle</span>
                        <span><?php echo $success_msg; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2 alert-danger">
                        <span class="material-symbols-outlined text-red-500">error</span>
                        <span><?php echo $error_msg; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Project Team Assignment</h1>
                        <p class="text-gray-500 text-sm mt-1 font-medium">Create project squads, assign mentors, allocate students, and track squad performance statuses.</p>
                    </div>
                    <button onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-2 cursor-pointer">
                        <span class="material-symbols-outlined text-sm">add</span> Create Project Team
                    </button>
                </div>

                <!-- Search & Filters -->
                <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                    <form method="GET" action="coordinator_teams.php" class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by team name, mentor, or project..." 
                                   class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 pl-10 pr-4 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-xs font-bold transition-all">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="coordinator_teams.php" class="bg-gray-100 hover:bg-gray-200 border border-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold flex items-center justify-center">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Grid of team cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($teams_list)): ?>
                        <div class="col-span-full bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center">
                            <span class="material-symbols-outlined text-5xl text-gray-300 mb-3">groups</span>
                            <h3 class="text-base font-bold text-gray-800">No project teams assigned yet</h3>
                            <p class="text-xs text-gray-500 mt-1">Click the "Create Project Team" button to initialize team allocations.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($teams_list as $team): 
                            $team_name_val = $team['team_name'];
                            $team_status_val = $team['team_status'] ?: 'Active';

                            // Get assigned students for this team
                            $student_stmt = mysqli_prepare($conn, "
                                SELECT u.id, u.full_name, u.email, sp.college_name 
                                FROM internship_applications a
                                JOIN users u ON a.user_id = u.id
                                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                                WHERE a.team_name = ?
                            ");
                            mysqli_stmt_bind_param($student_stmt, "s", $team_name_val);
                            mysqli_stmt_execute($student_stmt);
                            $student_res = mysqli_stmt_get_result($student_stmt);
                            $assigned_students = [];
                            $assigned_ids = [];
                            while ($s_row = mysqli_fetch_assoc($student_res)) {
                                $assigned_students[] = $s_row;
                                $assigned_ids[] = $s_row['id'];
                            }
                            mysqli_stmt_close($student_stmt);

                            // Status color pill
                            $status_color = match($team_status_val) {
                                'Completed' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                'On Hold' => 'bg-amber-50 text-amber-700 border-amber-100',
                                default => 'bg-blue-50 text-blue-700 border-blue-100'
                            };
                        ?>
                            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between hover:shadow-md transition-shadow gap-5">
                                <div class="space-y-4">
                                    <!-- Card header: team name & status -->
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="text-base font-black text-gray-900 flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-blue-600 text-lg">groups</span>
                                                <?php echo htmlspecialchars($team_name_val); ?>
                                            </h3>
                                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($team['project_title'] ?: 'General Project'); ?></p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase border <?php echo $status_color; ?>">
                                            <?php echo htmlspecialchars($team_status_val); ?>
                                        </span>
                                    </div>

                                    <!-- Assigned Mentor Info -->
                                    <div class="bg-slate-50/60 p-3 rounded-lg border border-slate-100 space-y-1">
                                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wide">Mentor Coordinator</div>
                                        <p class="text-xs text-gray-800 font-bold"><?php echo htmlspecialchars($team['mentor_name'] ?: 'No Mentor Assigned'); ?></p>
                                        <?php if (!empty($team['mentor_email'])): ?>
                                            <p class="text-[10px] text-gray-500 font-medium truncate"><?php echo htmlspecialchars($team['mentor_email']); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Assigned Students list -->
                                    <div class="space-y-2">
                                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-wide flex justify-between">
                                            <span>Assigned Squad</span>
                                            <span><?php echo count($assigned_students); ?> Students</span>
                                        </div>
                                        <div class="divide-y divide-gray-100 pr-1">
                                            <?php if (empty($assigned_students)): ?>
                                                <p class="text-xs text-gray-400 italic py-2">No students assigned.</p>
                                            <?php else: ?>
                                                <?php foreach ($assigned_students as $st_user): ?>
                                                    <div class="py-2 flex items-center justify-between text-xs">
                                                        <div>
                                                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($st_user['full_name']); ?></p>
                                                            <p class="text-[10px] text-gray-400 truncate"><?php echo htmlspecialchars($st_user['college_name'] ?: 'College N/A'); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="pt-4 border-t border-gray-100 flex flex-col gap-2 mt-auto">
                                    <button type="button" 
                                            class="w-full bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 text-center py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                            data-team-id="<?php echo htmlspecialchars($team['id'] ?? 0); ?>" 
                                            data-team-name="<?php echo htmlspecialchars($team_name_val); ?>"
                                            onclick="openMessageModal(this.dataset.teamId, this.dataset.teamName)">
                                        <span class="material-symbols-outlined text-sm">notifications_active</span>
                                        Send Notification
                                    </button>
                                    
                                    <div class="flex gap-2">
                                        <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($team), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($assigned_ids), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                class="flex-1 bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 text-center py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                            <span class="material-symbols-outlined text-sm">edit</span>
                                            Edit Squad
                                        </button>
                                        <form method="POST" action="coordinator_teams.php" onsubmit="return confirm('Are you sure you want to delete and dissolve this project team?');" class="flex-1">
                                            <input type="hidden" name="action" value="delete_team">
                                            <input type="hidden" name="team_name" value="<?php echo htmlspecialchars($team_name_val); ?>">
                                            <button type="submit" class="w-full bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-center py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                                <span class="material-symbols-outlined text-sm">delete</span>
                                                Dissolve
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Message Logs Section -->
                <div class="mt-12 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center">
                                <span class="material-symbols-outlined text-xl">history</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Message Logs</h3>
                                <p class="text-xs text-gray-500 font-medium">History of messages sent to students</p>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-xs font-bold text-gray-500 uppercase tracking-wider">
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Student</th>
                                    <th class="px-6 py-4">Subject</th>
                                    <th class="px-6 py-4">Message</th>
                                    <th class="px-6 py-4">Type</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($message_logs)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-gray-500 text-sm">
                                            <div class="flex flex-col items-center justify-center">
                                                <span class="material-symbols-outlined text-4xl text-gray-300 mb-2">inbox</span>
                                                <p>No messages sent yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($message_logs as $log): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 text-xs text-gray-500 whitespace-nowrap">
                                                <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($log['student_name'] ?: 'Unknown'); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['student_email'] ?: ''); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-medium text-gray-800">
                                                <?php echo htmlspecialchars($log['subject']); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($log['message']); ?>">
                                                <?php echo htmlspecialchars($log['message']); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $log['send_type'] === 'both' ? 'bg-purple-100 text-purple-700' : ($log['send_type'] === 'email' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'); ?>">
                                                    <?php echo htmlspecialchars($log['send_type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php 
                                                    $status_color = 'bg-gray-100 text-gray-700';
                                                    if (strtolower($log['status']) === 'sent') $status_color = 'bg-green-100 text-green-700';
                                                    if (stripos($log['status'], 'failed') !== false) $status_color = 'bg-red-100 text-red-700';
                                                ?>
                                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $status_color; ?>">
                                                    <?php echo htmlspecialchars($log['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>

    <!-- Team Assignment Overlay Modal -->
    <div id="team-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">groups</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900" id="modal-title">Create Project Team</h3>
                        <p class="text-xs text-gray-500 font-medium">Allocate students and design squad assignments.</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <form method="POST" action="coordinator_teams.php" id="team-form" class="p-8 space-y-4 max-h-[70vh] overflow-y-auto">
                <input type="hidden" name="action" id="form-action" value="create_team">
                <input type="hidden" name="old_team_name" id="form-old-team-name" value="">

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Team Name</label>
                    <input type="text" name="team_name" id="form-team-name" required placeholder="e.g. Squad Phoenix" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Project Type</label>
                        <select name="project_type" id="form-project-type" onchange="onProjectTypeChange()" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Type...</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Project Subtype</label>
                        <select name="project_subtype" id="form-project-subtype" onchange="onProjectSubtypeChange()" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Subtype...</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Project / Internship</label>
                        <select name="internship_id" id="form-project-id" onchange="updateProjectDetails()" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Project...</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Mentor Assignment</label>
                        <select name="mentor_id" id="form-mentor-id" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                            <option value="">Select Mentor...</option>
                            <?php foreach ($mentors_list as $mentor): ?>
                                <option value="<?php echo $mentor['id']; ?>"><?php echo htmlspecialchars($mentor['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Auto-displayed Project Details -->
                <div id="project-details-container" class="hidden bg-blue-50/50 border border-blue-100 rounded-lg p-4 space-y-3">
                    <div class="flex items-start justify-between border-b border-blue-100/50 pb-2">
                        <div>
                            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider flex items-center gap-1.5 mb-0.5"><span class="material-symbols-outlined text-[14px]">info</span> Project Overview</p>
                            <p id="detail-title" class="font-bold text-gray-900 text-sm"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Timeline</p>
                            <p id="detail-dates" class="font-bold text-blue-700 text-xs mt-0.5"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                        <div><span class="text-gray-500 font-medium">Domain/Type:</span> <span id="detail-type" class="font-bold text-gray-800"></span></div>
                        <div><span class="text-gray-500 font-medium">Subtype:</span> <span id="detail-subtype" class="font-bold text-gray-800"></span></div>
                        <div><span class="text-gray-500 font-medium">Stack:</span> <span id="detail-stack" class="font-bold text-gray-800 truncate block"></span></div>
                        <div><span class="text-gray-500 font-medium">Duration:</span> <span id="detail-duration" class="font-bold text-gray-800"></span></div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Squad Progress Status</label>
                    <select name="team_status" id="form-team-status" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer">
                        <option value="Active">Active / In Progress</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block">Select Students (Check to Assign)</label>
                    <div id="student-checklist-container" class="border border-gray-200 rounded-lg p-3 bg-gray-50 max-h-48 overflow-y-auto space-y-2.5">
                        <p class="text-xs text-gray-400 italic">Please select a project to view applicants.</p>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors cursor-pointer">Save Assignments</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div id="message-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">mail</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Send Message to Student</h3>
                        <p class="text-xs text-gray-500 font-medium">Direct communication</p>
                    </div>
                </div>
                <button onclick="closeMessageModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer focus:outline-none">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <form method="POST" action="coordinator_teams.php" id="message-form" class="p-8 space-y-5">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="team_id" id="msg-team-id" value="">
                
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-lg p-3">
                    <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider mb-1">Squad / Team</p>
                    <p class="text-sm font-bold text-gray-900" id="msg-team-name"></p>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Recipients</label>
                    <select name="recipient_group" id="msg-recipient-group" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                        <option value="">-- Select Recipient Group --</option>
                        <option value="all_students">All Squad Members</option>
                        <option value="mentor">Assigned Mentor</option>
                        <option value="mentor_and_students">Mentor + All Squad Members</option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Subject</label>
                    <input type="text" name="subject" required placeholder="Message Subject" class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Message</label>
                    <textarea name="message" required rows="4" placeholder="Type your message here..." class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none"></textarea>
                </div>

                <div class="space-y-1">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Send Via</label>
                    <select name="send_type" required class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer">
                        <option value="both">Both (In-App & Email)</option>
                        <option value="in-app">In-App Notification Only</option>
                        <option value="email">Email Only</option>
                    </select>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeMessageModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors cursor-pointer">Cancel</button>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shadow-sm transition-colors cursor-pointer flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">send</span> Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Team Message History Modal -->
    <div id="history-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200 flex flex-col max-h-[80vh]">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-xl">history</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Team Message History</h3>
                        <p class="text-xs text-gray-500 font-medium">Past notifications for this squad</p>
                    </div>
                </div>
                <button onclick="closeHistoryModal()" class="text-gray-400 hover:text-gray-600 transition-colors cursor-pointer focus:outline-none">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 bg-gray-50/30">
                <div id="history-modal-content" class="space-y-4">
                    <!-- History items injected here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        const projectsData = <?php echo json_encode($projects_list); ?>;
        const assignedProjectIds = <?php echo json_encode($assigned_project_ids); ?>;
        const studentsData = <?php echo json_encode($students_list); ?>;
        const teamMessageLogs = <?php echo json_encode($team_message_logs); ?>;
        let currentlyAssignedStudentIds = [];

        const modal = document.getElementById('team-modal');
        const modalTitle = document.getElementById('modal-title');
        const formAction = document.getElementById('form-action');
        const formOldTeamName = document.getElementById('form-old-team-name');
        const formTeamName = document.getElementById('form-team-name');
        const formProjectId = document.getElementById('form-project-id');
        const formMentorId = document.getElementById('form-mentor-id');
        const formTeamStatus = document.getElementById('form-team-status');

        const projectTypesData = <?php echo json_encode($project_types); ?>;
        const projectSubtypesData = <?php echo json_encode($project_subtypes); ?>;
        const projectsQueryDebug = <?php echo json_encode($projects_query_debug ?? 'SELECT ...'); ?>;

        function openHistoryModal(teamId) {
            const historyModal = document.getElementById('history-modal');
            const historyContent = document.getElementById('history-modal-content');
            historyContent.innerHTML = ''; // Clear existing
            
            const logs = teamMessageLogs[teamId] || [];
            
            if (logs.length === 0) {
                historyContent.innerHTML = `
                    <div class="text-center py-8">
                        <span class="material-symbols-outlined text-4xl text-gray-300 mb-2 block">history</span>
                        <p class="text-gray-500 font-medium">No messages have been sent to this team yet.</p>
                    </div>
                `;
            } else {
                logs.forEach(log => {
                    const badgeClass = log.send_type === 'both' ? 'bg-purple-100 text-purple-700' : (log.send_type === 'email' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700');
                    const div = document.createElement('div');
                    div.className = 'bg-white rounded-xl border border-gray-200 p-4 shadow-sm';
                    div.innerHTML = `
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="font-bold text-gray-900 mb-1">${log.subject}</h4>
                                <p class="text-xs text-gray-500 font-medium">Sent to: <span class="text-indigo-600">${log.recipient_group || 'Team'}</span></p>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                <span class="text-[10px] text-gray-400 font-medium">${new Date(log.created_at).toLocaleString()}</span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${badgeClass}">${log.send_type}</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-700 whitespace-pre-wrap border border-gray-100">${log.message}</div>
                    `;
                    historyContent.appendChild(div);
                });
            }
            
            historyModal.classList.remove('hidden');
            historyModal.classList.add('flex');
        }

        function closeHistoryModal() {
            const historyModal = document.getElementById('history-modal');
            historyModal.classList.add('hidden');
            historyModal.classList.remove('flex');
        }

        function populateProjectTypeDropdown(selectedType = '') {
            const typeSelect = document.getElementById('form-project-type');
            while (typeSelect.options.length > 1) {
                typeSelect.remove(1);
            }

            projectTypesData.forEach(type => {
                const opt = document.createElement('option');
                opt.value = type.type_name;
                opt.textContent = type.type_name;
                if (type.type_name === selectedType) {
                    opt.selected = true;
                }
                typeSelect.appendChild(opt);
            });
        }

        function populateProjectSubtypeDropdown(typeName, selectedSubtype = '') {
            const subtypeSelect = document.getElementById('form-project-subtype');
            while (subtypeSelect.options.length > 1) {
                subtypeSelect.remove(1);
            }

            if (!typeName) {
                // Reset to default message when no type selected
                return;
            }

            const parentType = projectTypesData.find(t => t.type_name === typeName);
            if (!parentType) return;

            const subtypes = projectSubtypesData.filter(sub => parseInt(sub.project_type_id, 10) === parseInt(parentType.id, 10));
            if (subtypes.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No subtypes available';
                opt.disabled = true;
                subtypeSelect.appendChild(opt);
                return;
            }

            subtypes.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.subtype_name;
                opt.textContent = sub.subtype_name;
                if (sub.subtype_name === selectedSubtype) {
                    opt.selected = true;
                }
                subtypeSelect.appendChild(opt);
            });
        }

        function populateProjectDropdown(typeName, subtypeName, currentInternshipId = null) {
            const projectSelect = document.getElementById('form-project-id');
            while (projectSelect.options.length > 1) {
                projectSelect.remove(1);
            }

            if (!typeName || !subtypeName) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Select Project...';
                projectSelect.appendChild(opt);
                return;
            }

            const normalizedType = typeName.trim().toLowerCase();
            const normalizedSubtype = subtypeName.trim().toLowerCase();
            console.log('Project dropdown debug:', {
                selected_type: typeName,
                selected_subtype: subtypeName,
                internships_row_count: projectsData.length,
                sql_query: projectsQueryDebug
            });

            // Find matching projects using exact stored strings, normalized for whitespace/case
            const matchingProjects = projectsData.filter(proj => {
                const pId = parseInt(proj.id, 10);
                const isAssigned = assignedProjectIds.includes(pId);
                const typeMatch = (proj.project_type || '').trim().toLowerCase() === normalizedType;
                const subtypeMatch = (proj.project_subtype || '').trim().toLowerCase() === normalizedSubtype;

                // Include if not assigned OR if it's the currently selected one (for edit mode)
                return typeMatch && subtypeMatch && (!isAssigned || pId === currentInternshipId);
            });

            if (matchingProjects.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = `No projects found for ${typeName} - ${subtypeName}`;
                opt.disabled = true;
                projectSelect.appendChild(opt);
                return;
            }

            matchingProjects.forEach(proj => {
                const opt = document.createElement('option');
                opt.value = parseInt(proj.id, 10);

                // Build display: "Title - Duration - Mode"
                let displayText = proj.project_title || proj.title || 'Untitled Project';
                if (proj.duration) {
                    displayText += ' - ' + proj.duration;
                }
                if (proj.mode) {
                    displayText += ' - ' + proj.mode;
                }
                if (!proj.mode && proj.task_title && proj.task_title !== proj.project_title) {
                    displayText += ' - ' + proj.task_title;
                }

                opt.textContent = displayText;
                if (parseInt(proj.id, 10) === currentInternshipId) {
                    opt.selected = true;
                }
                projectSelect.appendChild(opt);
            });
        }

        function onProjectTypeChange() {
            const type = document.getElementById('form-project-type').value;
            // Reset subtype and project when type changes
            populateProjectSubtypeDropdown(type);
            populateProjectDropdown('', '');
            updateProjectDetails();
        }

        function onProjectSubtypeChange() {
            const type = document.getElementById('form-project-type').value;
            const subtype = document.getElementById('form-project-subtype').value;
            // Reset project when subtype changes
            populateProjectDropdown(type, subtype);
            updateProjectDetails();
        }

        function renderStudentChecklist(internshipId) {
            const container = document.getElementById('student-checklist-container');
            container.innerHTML = '';
            
            if (isNaN(internshipId) || internshipId === '') {
                container.innerHTML = '<p class="text-xs text-gray-400 italic">Please select a project to view applicants.</p>';
                return;
            }
            
            const selectedProject = projectsData.find(p => parseInt(p.id) === parseInt(internshipId));
            if (!selectedProject) {
                container.innerHTML = '<p class="text-xs text-gray-400 italic">Project not found.</p>';
                return;
            }
            
            // Filter students who applied to THIS specific internship (internship_id match)
            const projectApplicants = studentsData.filter(st => {
                return parseInt(st.internship_id) === parseInt(internshipId);
            });
            
            if (projectApplicants.length === 0) {
                const subtypeName = selectedProject.project_subtype || 'this project';
                container.innerHTML = `<p class="text-xs text-gray-400 italic">No students have applied for ${subtypeName} yet.</p>`;
                return;
            }
            
            projectApplicants.forEach(st => {
                const isChecked = currentlyAssignedStudentIds.includes(parseInt(st.id));
                const checkedAttr = isChecked ? 'checked' : '';
                
                let assignmentSpan = '';
                if (st.assigned_team && st.assigned_team !== '') {
                    assignmentSpan = `<span class="text-indigo-600 font-bold ml-1">(Assigned to: ${st.assigned_team})</span>`;
                }
                
                const html = `
                    <label class="flex items-start gap-2.5 text-xs text-gray-700 font-medium cursor-pointer py-0.5 hover:bg-gray-100/50 rounded transition-all">
                        <input type="checkbox" name="students[]" value="${st.id}" ${checkedAttr} class="student-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 mt-0.5 cursor-pointer">
                        <div class="flex-1">
                            <p class="font-bold text-gray-800">${st.full_name}</p>
                            <p class="text-[10px] text-gray-500 font-normal">
                                ${st.college_name || 'College N/A'}
                                ${assignmentSpan}
                            </p>
                        </div>
                    </label>
                `;
                container.innerHTML += html;
            });
        }

        function openCreateModal() {
            currentlyAssignedStudentIds = [];
            modalTitle.textContent = "Create Project Team";
            formAction.value = "create_team";
            formOldTeamName.value = "";
            formTeamName.value = "";
            formTeamName.readOnly = false;

            populateProjectTypeDropdown('');
            populateProjectSubtypeDropdown('');
            populateProjectDropdown('', '');

            formMentorId.value = "";
            formTeamStatus.value = "Active";

            updateProjectDetails();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function openEditModal(team, assignedStudentIds) {
            currentlyAssignedStudentIds = assignedStudentIds;
            modalTitle.textContent = "Edit Project Team";
            formAction.value = "edit_team";
            formOldTeamName.value = team.team_name;
            formTeamName.value = team.team_name;

            const project = projectsData.find(p => parseInt(p.id) === parseInt(team.internship_id));
            const type = project ? (project.project_type || '') : '';
            const subtype = project ? (project.project_subtype || '') : '';

            populateProjectTypeDropdown(type);
            populateProjectSubtypeDropdown(type, subtype);
            populateProjectDropdown(type, subtype, parseInt(team.internship_id));

            formMentorId.value = team.mentor_id;
            formTeamStatus.value = team.team_status || "Active";

            updateProjectDetails();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function updateProjectDetails() {
            const container = document.getElementById('project-details-container');
            const projectId = parseInt(formProjectId.value);
            
            // Trigger student checklist render
            renderStudentChecklist(projectId);
            
            if (isNaN(projectId)) {
                container.classList.add('hidden');
                return;
            }
            
            const project = projectsData.find(p => parseInt(p.id) === projectId);
            if (project) {
                let displayTitle = project.project_title || project.title || 'Untitled Project';
                if (project.task_title && project.task_title !== project.project_title) {
                    displayTitle += ' - ' + project.task_title;
                }
                document.getElementById('detail-title').textContent = displayTitle;
                
                // Format dates if available
                let dateStr = 'Dates TBD';
                if (project.start_date && project.end_date) {
                    const start = new Date(project.start_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
                    const end = new Date(project.end_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
                    dateStr = `${start} - ${end}`;
                } else if (project.start_date) {
                    dateStr = `Starts: ${new Date(project.start_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'})}`;
                }
                document.getElementById('detail-dates').textContent = dateStr;

                document.getElementById('detail-type').textContent = project.project_type || 'General';
                document.getElementById('detail-subtype').textContent = project.project_subtype || 'N/A';
                document.getElementById('detail-stack').textContent = project.technology_stack || 'Not specified';
                document.getElementById('detail-duration').textContent = project.duration || 'Not specified';
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        const messageModal = document.getElementById('message-modal');
        function openMessageModal(teamId, teamName) {
            messageModal.classList.remove('hidden');
            messageModal.classList.add('flex');
            document.getElementById('msg-team-id').value = teamId;
            document.getElementById('msg-team-name').textContent = teamName || 'Unnamed Squad';
        }

        function closeMessageModal() {
            messageModal.classList.add('hidden');
            messageModal.classList.remove('flex');
            document.getElementById('message-form').reset();
        }

        // Sidebar Toggle Handler
        const toggleBtn = document.getElementById('sidebar-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    document.body.classList.toggle('sidebar-open');
                    document.body.classList.remove('sidebar-closed');
                } else {
                    document.body.classList.toggle('sidebar-closed');
                    document.body.classList.remove('sidebar-open');
                }
            });
        }
    </script>
<script src="js/alerts.js"></script>
</body>
</html>
