<?php
require_once 'db.php';
require_once 'includes/auth.php';
require_once 'ensure_extended_schema.php';
require_once 'includes/mail_helper.php';

require_role(['admin', 'hr', 'coordinator', 'mentor']);

$senderId = current_user_id();
$senderRole = current_user_role();
$errors = [];
$successMessage = '';
$recipientType = $_POST['recipient_type'] ?? 'specific_student';
$subject = trim($_POST['subject'] ?? '');
$messageBody = trim($_POST['message'] ?? '');
$sendNotification = isset($_POST['send_notification']);
$sendEmail = isset($_POST['send_email']);

$returnUrl = match ($senderRole) {
    'admin' => 'admin_dashboard.php',
    'hr' => 'hr_dashboard.php',
    'coordinator' => 'coordinator_dashboard.php',
    'mentor' => 'mentor_dashboard.php',
    default => 'index.php',
};

function fetchUsersByRole(mysqli $conn, string $role): array {
    $roleEsc = mysqli_real_escape_string($conn, strtolower(trim($role)));
    $sql = "SELECT id, full_name, email, role FROM users WHERE role = '$roleEsc' ORDER BY full_name ASC";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    return $rows;
}

function fetchAssignedStudents(mysqli $conn, int $mentorId): array {
    $mentorId = intval($mentorId);
    $sql = "SELECT u.id, u.full_name, u.email, u.role
            FROM mentor_assignments ma
            JOIN users u ON u.id = ma.student_id
            WHERE ma.mentor_id = $mentorId AND ma.status = 'active'
            ORDER BY u.full_name ASC";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    return $rows;
}

$students = fetchUsersByRole($conn, 'student');
$mentors = fetchUsersByRole($conn, 'mentor');
$coordinators = fetchUsersByRole($conn, 'coordinator');
$admins = fetchUsersByRole($conn, 'admin');
$hrs = fetchUsersByRole($conn, 'hr');
$companies = fetchUsersByRole($conn, 'company');

// Fetch all users for single/multiple select lists
$allUsers = [];
$allUsersRes = mysqli_query($conn, "SELECT id, full_name, email, role FROM users ORDER BY full_name ASC");
while ($allUsersRes && $row = mysqli_fetch_assoc($allUsersRes)) {
    $allUsers[] = $row;
}

$assignedStudents = [];
if ($senderRole === 'mentor') {
    $assignedStudents = fetchAssignedStudents($conn, $senderId);
}

$recipientOptions = [];
switch ($senderRole) {
    case 'admin':
        $recipientOptions = [
            'single_user' => 'Single User',
            'multiple_users' => 'Multiple Selected Users',
            'all_students' => 'All Students',
            'all_coordinators' => 'All Coordinators',
            'all_mentors' => 'All Mentors',
            'all_hr' => 'All HR Users',
            'all_companies' => 'All Companies',
            'everyone' => 'Everyone',
        ];
        break;
    case 'hr':
        $recipientOptions = [
            'all_students' => 'All Students',
            'specific_student' => 'Specific Student',
            'all_coordinators' => 'All Coordinators',
            'specific_coordinator' => 'Specific Coordinator',
            'all_admins' => 'All Admins',
            'specific_admin' => 'Specific Admin',
        ];
        break;
    case 'coordinator':
        $recipientOptions = [
            'all_students' => 'All Students',
            'specific_student' => 'Specific Student',
            'all_mentors' => 'All Mentors',
            'specific_mentor' => 'Specific Mentor',
            'all_admins' => 'All Admins',
            'specific_admin' => 'Specific Admin',
        ];
        break;
    case 'mentor':
        $recipientOptions = [
            'assigned_students' => 'Assigned Students',
            'specific_student' => 'Specific Student',
            'all_coordinators' => 'All Coordinators',
            'specific_coordinator' => 'Specific Coordinator',
            'all_admins' => 'All Admins',
            'specific_admin' => 'Specific Admin',
        ];
        break;
}

function resolveRecipientUsers(string $recipientType, int $senderId, string $senderRole, array $students, array $mentors, array $coordinators, array $admins, array $assignedStudents): array {
    switch ($recipientType) {
        case 'all_students':
            return $students;
        case 'all_mentors':
            return $mentors;
        case 'all_coordinators':
            return $coordinators;
        case 'all_admins':
            return $admins;
        case 'assigned_students':
            return $assignedStudents;
        case 'specific_student':
        case 'specific_mentor':
        case 'specific_coordinator':
        case 'specific_admin':
            return [];
        default:
            return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // DEBUG: Log POST values to identify form submission issue
    error_log("Manual Message POST: recipient_type=" . ($_POST['recipient_type'] ?? 'MISSING') . ", recipient_id=" . ($_POST['recipient_id'] ?? 'MISSING') . ", subject=" . substr($subject, 0, 20) . ", message_len=" . strlen($messageBody) . ", send_notification=" . ($sendNotification ? 'YES' : 'NO') . ", send_email=" . ($sendEmail ? 'YES' : 'NO'));
    
    if ($recipientType === '') {
        $errors[] = 'Please select a recipient group.';
    }
    if ($subject === '') {
        $errors[] = 'Please enter a subject.';
    }
    if ($messageBody === '') {
        $errors[] = 'Please enter a message.';
    }
    if (!$sendNotification && !$sendEmail) {
        $errors[] = 'Select at least one delivery method: dashboard notification or email.';
    }

    $targets = [];
    $recipientId = intval(trim($_POST['recipient_id'] ?? '0'));

    switch ($recipientType) {
        case 'single_user':
            if ($recipientId > 0) {
                $check_sql = "SELECT id, full_name, email, role FROM users WHERE id = $recipientId";
                $check_res = mysqli_query($conn, $check_sql);
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    $targets = [mysqli_fetch_assoc($check_res)];
                }
            }
            break;
        case 'multiple_users':
            $recipientIds = $_POST['recipient_ids'] ?? [];
            if (!empty($recipientIds)) {
                $idsSanitized = array_map('intval', $recipientIds);
                $idsList = implode(',', $idsSanitized);
                $check_sql = "SELECT id, full_name, email, role FROM users WHERE id IN ($idsList)";
                $check_res = mysqli_query($conn, $check_sql);
                while ($check_res && $row = mysqli_fetch_assoc($check_res)) {
                    $targets[] = $row;
                }
            }
            break;
        case 'specific_student':
            if ($recipientId > 0) {
                // Validate that student exists in database
                $check_sql = "SELECT id, full_name, email, role FROM users WHERE id = $recipientId AND role = 'student'";
                $check_res = mysqli_query($conn, $check_sql);
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    $targets = [$row = mysqli_fetch_assoc($check_res)];
                } else {
                    error_log("WARNING: Student ID $recipientId not found or role mismatch in database");
                }
            }
            break;
        case 'specific_mentor':
            if ($recipientId > 0) {
                // Validate that mentor exists in database
                $check_sql = "SELECT id, full_name, email, role FROM users WHERE id = $recipientId AND role = 'mentor'";
                $check_res = mysqli_query($conn, $check_sql);
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    $targets = [$row = mysqli_fetch_assoc($check_res)];
                } else {
                    error_log("WARNING: Mentor ID $recipientId not found or role mismatch in database");
                }
            }
            break;
        case 'specific_coordinator':
            if ($recipientId > 0) {
                // Validate that coordinator exists in database
                $check_sql = "SELECT id, full_name, email, role FROM users WHERE id = $recipientId AND role = 'coordinator'";
                $check_res = mysqli_query($conn, $check_sql);
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    $targets = [$row = mysqli_fetch_assoc($check_res)];
                } else {
                    error_log("WARNING: Coordinator ID $recipientId not found or role mismatch in database");
                }
            }
            break;
        case 'specific_admin':
            if ($recipientId > 0) {
                // Validate that admin exists in database
                $check_sql = "SELECT id, full_name, email, role FROM users WHERE id = $recipientId AND role = 'admin'";
                $check_res = mysqli_query($conn, $check_sql);
                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    $targets = [$row = mysqli_fetch_assoc($check_res)];
                } else {
                    error_log("WARNING: Admin ID $recipientId not found or role mismatch in database");
                }
            }
            break;
        case 'assigned_students':
            $targets = $assignedStudents;
            break;
        case 'all_students':
            $targets = $students;
            break;
        case 'all_mentors':
            $targets = $mentors;
            break;
        case 'all_coordinators':
            $targets = $coordinators;
            break;
        case 'all_admins':
            $targets = $admins;
            break;
        case 'all_hr':
            $targets = $hrs;
            break;
        case 'all_companies':
            $targets = $companies;
            break;
        case 'everyone':
            $everyoneRes = mysqli_query($conn, "SELECT id, full_name, email, role FROM users WHERE id != $senderId ORDER BY full_name ASC");
            while ($everyoneRes && $row = mysqli_fetch_assoc($everyoneRes)) {
                $targets[] = $row;
            }
            break;
        default:
            $errors[] = 'Invalid recipient selection.';
            break;
    }

    if (in_array($recipientType, ['specific_student', 'specific_mentor', 'specific_coordinator', 'specific_admin', 'single_user'], true)) {
        if (empty($targets)) {
            if ($recipientId === 0) {
                $errors[] = 'Please select a valid recipient.';
            } else {
                $errors[] = 'The selected recipient could not be found.';
            }
        }
    }
    if ($recipientType === 'multiple_users') {
        if (empty($targets)) {
            $errors[] = 'Please select at least one recipient.';
        }
    }
    if (in_array($recipientType, ['assigned_students'], true) && empty($assignedStudents)) {
        $errors[] = 'You have no assigned students to message.';
    }
    if (empty($targets) && empty($errors)) {
        $errors[] = 'No recipients were resolved for the selected recipient group.';
    }

    if (empty($errors)) {
        $sentCount = 0;
        $failedCount = 0;
        foreach ($targets as $target) {
            $recipientRole = strtolower(trim($target['role'] ?? 'student'));
            $result = sendManualMessage($senderId, $senderRole, intval($target['id']), $recipientRole, $subject, $messageBody, $sendNotification, $sendEmail);
            if ($sendEmail && $result['email_status'] === 'failed') {
                $failedCount++;
            } else {
                $sentCount++;
            }
        }

        $successMessage = "Message delivered to {$sentCount} recipient" . ($sentCount === 1 ? '' : 's') . ".";
        if ($failedCount > 0) {
            $successMessage .= " {$failedCount} delivery attempt" . ($failedCount === 1 ? '' : 's') . " failed.";
        }
    }
}

$historySql = "SELECT m.*, su.full_name AS sender_name, ru.full_name AS recipient_name
               FROM manual_messages m
               LEFT JOIN users su ON su.id = m.sender_id
               LEFT JOIN users ru ON ru.id = m.recipient_id
               ORDER BY m.created_at DESC
               LIMIT 50";
$historyRes = mysqli_query($conn, $historySql);
$historyRows = [];
while ($historyRes && $row = mysqli_fetch_assoc($historyRes)) {
    $historyRows[] = $row;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Manual Message — IMP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="min-h-screen">
        <header class="bg-white shadow-sm border-b border-slate-200">
            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Manual Message Center</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Send a Message</h1>
                        <p class="mt-1 text-sm text-slate-500">Choose recipients, enable notifications and email delivery, and keep history of manual messages.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= e($returnUrl) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <?php if (!empty($errors)): ?>
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <strong class="font-semibold">Please fix the following:</strong>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <details class="mt-3 text-xs text-red-600 cursor-pointer">
                            <summary class="font-mono">Debug Info (click to expand)</summary>
                            <div class="mt-2 whitespace-pre-wrap font-mono text-xs bg-red-100 p-2 rounded">
<?php ob_start(); print_r($_POST); $postDump = ob_get_clean(); ?>
<?= e($postDump) ?>

Selected value parsed as: <?= $recipientId ?>

Form targets resolved: <?= count($targets) ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                    <?= e($successMessage) ?>
                </div>
            <?php endif; ?>

            <div class="grid gap-6 xl:grid-cols-3">
                <section class="xl:col-span-2 rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
                    <div class="space-y-6">
                        <form id="manual_message_form" action="manual_message.php" method="post" class="space-y-6">
                            <div>
                                <label for="recipient_type" class="block text-sm font-semibold text-slate-700">Recipient Group</label>
                                <select id="recipient_type" name="recipient_type" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <?php foreach ($recipientOptions as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $recipientType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>


                            <div id="recipient_selectors" class="space-y-4">
                                <div data-recipient-panel="single_user" class="recipient-panel <?= $recipientType === 'single_user' ? '' : 'hidden' ?>">
                                    <label for="recipient_id_single" class="block text-sm font-semibold text-slate-700">Select Recipient</label>
                                    <select id="recipient_id_single" name="recipient_id" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <option value="">Choose a user</option>
                                        <?php foreach ($allUsers as $u): ?>
                                            <?php if (intval($u['id']) === $senderId) continue; ?>
                                            <option value="<?= e($u['id']) ?>" <?= $recipientType === 'single_user' && intval($_POST['recipient_id'] ?? 0) === intval($u['id']) ? 'selected' : '' ?>><?= e($u['full_name']) ?> (<?= e(ucfirst($u['role'])) ?> — <?= e($u['email']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div data-recipient-panel="multiple_users" class="recipient-panel <?= $recipientType === 'multiple_users' ? '' : 'hidden' ?> space-y-3">
                                    <label class="block text-sm font-semibold text-slate-700">Select Recipients</label>
                                    <div class="relative">
                                        <input type="text" id="user_search" placeholder="Search users by name, email or role..." class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    </div>
                                    <div class="max-h-60 overflow-y-auto border border-slate-200 rounded-2xl p-4 bg-slate-50 space-y-2" id="checkbox_list">
                                        <?php foreach ($allUsers as $u): ?>
                                            <?php if (intval($u['id']) === $senderId) continue; ?>
                                            <label class="flex items-center gap-3 p-3 rounded-2xl bg-white hover:bg-slate-100 hover:shadow-sm cursor-pointer transition-all border border-slate-100 checkbox-item" data-search="<?= e(strtolower($u['full_name'] . ' ' . $u['email'] . ' ' . $u['role'])) ?>">
                                                <input type="checkbox" name="recipient_ids[]" value="<?= e($u['id']) ?>" <?= $recipientType === 'multiple_users' && in_array(intval($u['id']), array_map('intval', $_POST['recipient_ids'] ?? [])) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-semibold text-slate-800"><?= e($u['full_name']) ?></span>
                                                    <span class="text-xs text-slate-500"><?= e($u['email']) ?> • <strong class="uppercase text-[9px] bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded"><?= e($u['role']) ?></strong></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div data-recipient-panel="specific_student" class="recipient-panel <?= $recipientType === 'specific_student' ? '' : 'hidden' ?>">
                                    <label for="recipient_id_student" class="block text-sm font-semibold text-slate-700">Select Student</label>
                                    <?php if (empty($students)): ?>
                                        <p class="rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600">No students available.</p>
                                    <?php else: ?>
                                        <select id="recipient_id_student" name="recipient_id" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                            <option value="">Choose a student</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?= e($student['id']) ?>" <?= $recipientType === 'specific_student' && intval($_POST['recipient_id'] ?? 0) === intval($student['id']) ? 'selected' : '' ?>><?= e($student['full_name'] . ' (' . $student['email'] . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>

                                <div data-recipient-panel="specific_mentor" class="recipient-panel <?= $recipientType === 'specific_mentor' ? '' : 'hidden' ?>">
                                    <label for="recipient_id_mentor" class="block text-sm font-semibold text-slate-700">Select Mentor</label>
                                    <select id="recipient_id_mentor" name="recipient_id" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <option value="">Choose a mentor</option>
                                        <?php foreach ($mentors as $mentor): ?>
                                            <option value="<?= e($mentor['id']) ?>" <?= $recipientType === 'specific_mentor' && intval($_POST['recipient_id'] ?? 0) === intval($mentor['id']) ? 'selected' : '' ?>><?= e($mentor['full_name'] . ' (' . $mentor['email'] . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div data-recipient-panel="specific_coordinator" class="recipient-panel <?= $recipientType === 'specific_coordinator' ? '' : 'hidden' ?>">
                                    <label for="recipient_id_coordinator" class="block text-sm font-semibold text-slate-700">Select Coordinator</label>
                                    <select id="recipient_id_coordinator" name="recipient_id" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <option value="">Choose a coordinator</option>
                                        <?php foreach ($coordinators as $coordinator): ?>
                                            <option value="<?= e($coordinator['id']) ?>" <?= $recipientType === 'specific_coordinator' && intval($_POST['recipient_id'] ?? 0) === intval($coordinator['id']) ? 'selected' : '' ?>><?= e($coordinator['full_name'] . ' (' . $coordinator['email'] . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div data-recipient-panel="specific_admin" class="recipient-panel <?= $recipientType === 'specific_admin' ? '' : 'hidden' ?>">
                                    <label for="recipient_id_admin" class="block text-sm font-semibold text-slate-700">Select Admin</label>
                                    <select id="recipient_id_admin" name="recipient_id" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <option value="">Choose an admin</option>
                                        <?php foreach ($admins as $admin): ?>
                                            <option value="<?= e($admin['id']) ?>" <?= $recipientType === 'specific_admin' && intval($_POST['recipient_id'] ?? 0) === intval($admin['id']) ? 'selected' : '' ?>><?= e($admin['full_name'] . ' (' . $admin['email'] . ')') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div data-recipient-panel="assigned_students" class="recipient-panel <?= $recipientType === 'assigned_students' ? '' : 'hidden' ?>">
                                    <?php if (empty($assignedStudents)): ?>
                                        <p class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">You do not currently have any assigned students.</p>
                                    <?php else: ?>
                                        <p class="text-sm text-slate-600">This will message all students currently assigned to you.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label for="subject" class="block text-sm font-semibold text-slate-700">Subject</label>
                                <input id="subject" name="subject" value="<?= e($subject) ?>" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Message subject" />
                            </div>

                            <div>
                                <label for="message" class="block text-sm font-semibold text-slate-700">Message</label>
                                <textarea id="message" name="message" rows="8" class="mt-2 block w-full rounded-3xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Write your message here..."><?= e($messageBody) ?></textarea>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-800">
                                    <input type="checkbox" name="send_notification" <?= $sendNotification ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                    <span>Send dashboard notification</span>
                                </label>
                                <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-800">
                                    <input type="checkbox" name="send_email" <?= $sendEmail ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                                    <span>Send email message</span>
                                </label>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <button type="submit" id="submit_btn" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700" disabled>Send Message</button>
                                <p class="text-sm text-slate-500">Messages are recorded for administrative history.</p>
                            </div>
                        </form>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
                        <h2 class="text-lg font-semibold text-slate-900">Delivery Notes</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Use dashboard notification for in-app alerts. Use email delivery to reach recipients outside the platform. If both are selected, recipients receive an in-app notification and an email copy.</p>
                    </section>

                    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
                        <h2 class="text-lg font-semibold text-slate-900">Your Role</h2>
                        <p class="mt-3 text-sm text-slate-700">You are sending as <strong class="capitalize"><?= e($senderRole) ?></strong>.</p>
                    </section>

                    <section id="status_section" class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200 hidden">
                        <h2 class="text-lg font-semibold text-slate-900">Form Status</h2>
                        <p id="status_text" class="mt-3 text-sm text-slate-700"></p>
                    </section>
                </aside>
            </div>

            <section class="mt-8 rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Manual Message History</h2>
                        <p class="mt-1 text-sm text-slate-500">Latest 50 manual messages recorded by the platform.</p>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Date</th>
                                <th class="px-4 py-3 font-semibold">Sender</th>
                                <th class="px-4 py-3 font-semibold">Recipient</th>
                                <th class="px-4 py-3 font-semibold">Subject</th>
                                <th class="px-4 py-3 font-semibold">Delivery</th>
                                <th class="px-4 py-3 font-semibold">Email Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <?php if (empty($historyRows)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">No manual messages have been recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historyRows as $row): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-slate-600"><?= e(date('M d, Y H:i', strtotime($row['created_at']))) ?></td>
                                        <td class="px-4 py-3 text-slate-700"><?= e($row['sender_name'] ?: 'System') ?> <span class="text-xs text-slate-500">(<?= e($row['sender_role']) ?>)</span></td>
                                        <td class="px-4 py-3 text-slate-700"><?= e($row['recipient_name'] ?: 'Unknown') ?> <span class="text-xs text-slate-500">(<?= e($row['recipient_role']) ?>)</span></td>
                                        <td class="px-4 py-3 text-slate-700"><?= e($row['subject']) ?></td>
                                        <td class="px-4 py-3 text-slate-700"><?= $row['send_notification'] ? '<span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Notification</span>' : '<span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-500">Email only</span>' ?></td>
                                        <td class="px-4 py-3 text-slate-700"><?= e($row['email_status']) ?><?= $row['email_error'] ? ' / ' . e($row['email_error']) : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        const recipientTypeSelect = document.querySelector('#recipient_type');
        const panels = document.querySelectorAll('.recipient-panel');
        const form = document.querySelector('#manual_message_form');
        const submitBtn = document.querySelector('#submit_btn');
        const statusSection = document.querySelector('#status_section');
        const statusText = document.querySelector('#status_text');
        const subjectInput = document.querySelector('#subject');
        const messageInput = document.querySelector('#message');
        const notificationCheckbox = document.querySelector('input[name="send_notification"]');
        const emailCheckbox = document.querySelector('input[name="send_email"]');

        function updateRecipientPanels() {
            const type = recipientTypeSelect.value;
            panels.forEach(panel => {
                const isActive = panel.dataset.recipientPanel === type;
                panel.classList.toggle('hidden', !isActive);
                
                // For select dropdowns
                const select = panel.querySelector('select');
                if (select) {
                    select.disabled = !isActive;
                }
                
                // For checkboxes in multi-select panel
                const checkboxes = panel.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => {
                    cb.disabled = !isActive;
                });
            });
            validateForm();
        }

        function validateForm() {
            let isValid = true;
            const errors = [];
            
            // Check recipient type and selection
            const type = recipientTypeSelect.value;
            if (!type) {
                isValid = false;
                errors.push("Select a recipient group");
            } else if (['specific_student', 'specific_mentor', 'specific_coordinator', 'specific_admin', 'single_user'].includes(type)) {
                const panel = Array.from(panels).find(p => p.dataset.recipientPanel === type);
                const select = panel ? panel.querySelector('select') : null;
                if (select && !select.value) {
                    isValid = false;
                    errors.push("Select a recipient from the dropdown");
                }
            } else if (type === 'multiple_users') {
                const checked = Array.from(document.querySelectorAll('input[name="recipient_ids[]"]')).filter(cb => cb.checked);
                if (checked.length === 0) {
                    isValid = false;
                    errors.push("Select at least one recipient");
                }
            }
            
            // Check subject
            if (!subjectInput.value.trim()) {
                isValid = false;
                errors.push("Enter a subject");
            }
            
            // Check message
            if (!messageInput.value.trim()) {
                isValid = false;
                errors.push("Enter a message");
            }
            
            // Check at least one delivery method
            if (!notificationCheckbox.checked && !emailCheckbox.checked) {
                isValid = false;
                errors.push("Select dashboard notification or email");
            }
            
            // Update button and status
            submitBtn.disabled = !isValid;
            if (!isValid && errors.length > 0) {
                statusSection.classList.remove('hidden');
                statusText.innerHTML = errors.map(e => '<span style="color: #ea580c;">✓</span> ' + e).join('<br>');
                statusText.style.color = '#d97706';
            } else if (isValid) {
                statusSection.classList.add('hidden');
            }
        }

        if (recipientTypeSelect) {
            recipientTypeSelect.addEventListener('change', updateRecipientPanels);
        }
        
        // Validate on input changes
        document.querySelectorAll('select, #subject, #message').forEach(el => {
            el.addEventListener('change', validateForm);
            el.addEventListener('input', validateForm);
        });
        
        document.querySelectorAll('input[name="send_notification"], input[name="send_email"]').forEach(el => {
            el.addEventListener('change', validateForm);
        });

        // Listen for checkbox selection changes in multi-select list
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'recipient_ids[]') {
                validateForm();
            }
        });
        
        // Prevent form submission if invalid
        form.addEventListener('submit', function(e) {
            if (submitBtn.disabled) {
                e.preventDefault();
            }
        });

        // Real-time search-filtering for multi-select list
        const userSearch = document.getElementById('user_search');
        if (userSearch) {
            userSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const items = document.querySelectorAll('.checkbox-item');
                items.forEach(item => {
                    const searchData = item.getAttribute('data-search') || '';
                    if (searchData.includes(query)) {
                        item.classList.remove('hidden');
                    } else {
                        item.classList.add('hidden');
                    }
                });
            });
        }
        
        // Initial validation
        updateRecipientPanels();
    </script>
</body>
</html>
