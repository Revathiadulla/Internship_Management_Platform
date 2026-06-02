<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_module_access('candidates');
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);
sync_candidates_from_applications($conn);

$id = (int) ($_GET['id'] ?? 0);
$res = mysqli_query($conn, "SELECT * FROM candidates WHERE id = $id LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
    set_flash('Candidate not found.');
    header('Location: candidates.php');
    exit();
}
$candidate = mysqli_fetch_assoc($res);
$upload_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume_file'])) {
    $file = $_FILES['resume_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors[] = 'Resume upload failed. Please choose a valid file.';
    } else {
        $max_size = 5 * 1024 * 1024;
        $allowed_ext = ['pdf', 'doc', 'docx'];
        $original_name = $file['name'] ?? '';
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            $upload_errors[] = 'Resume must be PDF, DOC, or DOCX.';
        }
        if ((int) $file['size'] > $max_size) {
            $upload_errors[] = 'Resume must be 5MB or smaller.';
        }
        if (empty($upload_errors)) {
            $folder = __DIR__ . '/uploads/resumes';
            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }
            $safe_name = 'resume_candidate_' . $id . '_' . time() . '.' . $ext;
            $target = $folder . '/' . $safe_name;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $relative = 'uploads/resumes/' . $safe_name;
                $stmt = $conn->prepare("UPDATE candidates SET resume_file = ? WHERE id = ?");
                $stmt->bind_param('si', $relative, $id);
                $stmt->execute();
                if (!empty($candidate['latest_application_id'])) {
                    $app_id = (int) $candidate['latest_application_id'];
                    $stmt = $conn->prepare("UPDATE internship_applications SET resume_file = ? WHERE id = ?");
                    $stmt->bind_param('si', $relative, $app_id);
                    $stmt->execute();
                }
                set_flash('Resume uploaded successfully.');
                header('Location: candidate_profile.php?id=' . $id);
                exit();
            }
            $upload_errors[] = 'Unable to save uploaded resume.';
        }
    }
}

$user_id = (int) ($candidate['user_id'] ?? 0);
$apps = mysqli_query($conn, "SELECT a.id, a.status, a.applied_date, COALESCE(i.title, a.internship_name) AS title
    FROM internship_applications a
    LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
    WHERE a.user_id = $user_id
    ORDER BY a.applied_date DESC");
$resume = $candidate['resume_file'] ?: '';
$resume_href = $resume !== '' ? 'resume_serve.php?file=' . urlencode(basename($resume)) : '';

page_shell_start('candidates', $candidate['full_name'], 'Candidate profile, resume, skills, applications, and status history.', '<a href="candidates.php" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Back</a>');
if (!empty($upload_errors)) {
    echo '<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">' . e(implode(' ', $upload_errors)) . '</div>';
}
?>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-bold">Profile</h2>
        <dl class="mt-5 space-y-4 text-sm">
            <div><dt class="font-semibold text-slate-500">Email</dt><dd><?php echo e($candidate['email'] ?: 'Not added'); ?></dd></div>
            <div><dt class="font-semibold text-slate-500">Phone</dt><dd><?php echo e($candidate['phone'] ?: 'Not added'); ?></dd></div>
            <div><dt class="font-semibold text-slate-500">College</dt><dd><?php echo e($candidate['college'] ?: 'Not added'); ?></dd></div>
            <div><dt class="font-semibold text-slate-500">Current status</dt><dd class="mt-1"><?php echo status_badge($candidate['current_status'] ?: 'Applied'); ?></dd></div>
            <div><dt class="font-semibold text-slate-500">Resume</dt><dd><?php echo $resume_href ? '<a class="font-semibold text-blue-700 hover:underline" href="' . e($resume_href) . '" target="_blank">View resume</a>' : 'No resume uploaded'; ?></dd></div>
        </dl>
        <form method="post" enctype="multipart/form-data" class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <label class="block text-sm font-semibold text-slate-700">Upload resume</label>
            <input type="file" name="resume_file" accept=".pdf,.doc,.docx" required class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
            <p class="mt-2 text-xs text-slate-500">PDF, DOC, or DOCX. Maximum 5MB.</p>
            <button class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Upload</button>
        </form>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-6 lg:col-span-2">
        <h2 class="text-lg font-bold">Skills</h2>
        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700"><?php echo e($candidate['skills'] ?: 'No skills added yet.'); ?></p>
    </div>
</div>
<div class="mt-6 rounded-lg border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-bold">Applications</h2>
    <div class="mt-4 divide-y divide-slate-100">
        <?php if ($apps && mysqli_num_rows($apps) > 0): while ($app = mysqli_fetch_assoc($apps)): ?>
            <div class="flex items-center justify-between py-4 text-sm">
                <div><div class="font-semibold"><?php echo e($app['title'] ?: 'Untitled application'); ?></div><div class="text-xs text-slate-500"><?php echo $app['applied_date'] ? e(date('M d, Y', strtotime($app['applied_date']))) : 'Date unavailable'; ?></div></div>
                <div class="flex items-center gap-4"><?php echo status_badge($app['status'] ?: 'Applied'); ?><a class="font-semibold text-blue-700 hover:underline" href="hr_applicant_detail.php?app_id=<?php echo (int) $app['id']; ?>">Details</a></div>
            </div>
        <?php endwhile; else: ?>
            <p class="py-6 text-sm text-slate-500">No applications found for this candidate.</p>
        <?php endif; ?>
    </div>
</div>
<?php page_shell_end(); ?>
