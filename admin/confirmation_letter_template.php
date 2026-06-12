<?php
session_start();
include_once __DIR__ . '/../includes/auth.php';
require_login();
include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/hr_module_helpers.php';
require_once __DIR__ . '/../includes/cloudinary_config.php';
require_once __DIR__ . '/template_layout.php';

// Access check: Admin and HR only
$user_role = strtolower(trim($_SESSION['role'] ?? ''));
if ($user_role !== 'hr' && $user_role !== 'admin') {
    die("Unauthorized access. Only HR and Admin roles can manage templates.");
}

$errors = [];
$success_msg = '';

// Default values for restoration
$default_subject = "Congratulations! You have been selected for the internship";
$default_content = "Dear {student_name},\n\nWe are pleased to inform you that your application for the internship position \"{project_title}\" has been successful. You have been officially selected for this role.\n\nPlease note: Project allocation, team formation, and mentor assignment will be communicated separately by the Coordinator. You do not need to take any action regarding these assignments until further notice.\n\nCongratulations on your selection!";
$default_sig_name = "HR Team";
$default_sig_designation = "IMP Platform";

// Ensure directories exist
$upload_dir = __DIR__ . '/uploads/templates';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $template_id = intval($_POST['template_id'] ?? 0);
        $template_name = trim($_POST['template_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $sig_name = trim($_POST['signature_name'] ?? '');
        $sig_designation = trim($_POST['signature_designation'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($template_name === '') $errors[] = "Template name is required.";
        if ($subject === '') $errors[] = "Subject is required.";
        if ($content === '') $errors[] = "Content body is required.";

        if (empty($errors)) {
            // Fetch existing template if exists to retain logo
            $existing_logo = '';
            if ($template_id > 0) {
                $check_q = mysqli_query($conn, "SELECT logo_path FROM confirmation_letter_templates WHERE id = $template_id");
                $check_row = mysqli_fetch_assoc($check_q);
                $existing_logo = $check_row['logo_path'] ?? '';
            }

            // Handle Logo Upload
            $logo_path = $existing_logo;
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['logo_file'];
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "Logo file upload error.";
                } elseif (!in_array($ext, $allowed)) {
                    $errors[] = "Logo must be a JPG, PNG, or WEBP image.";
                } else {
                    $filename = 'logo_' . time() . '.' . $ext;
                    $local_path = $upload_dir . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $local_path)) {
                        $logo_path = 'uploads/templates/' . $filename;
                        // Attempt Cloudinary upload
                        try {
                            $secure_url = uploadToCloudinary($local_path, 'templates', false);
                            if (!empty($secure_url)) {
                                $logo_path = $secure_url;
                            }
                        } catch (Exception $e) {
                            error_log("Cloudinary upload failed for logo: " . $e->getMessage());
                        }
                    } else {
                        $errors[] = "Failed to save logo locally.";
                    }
                }
            }

            if (empty($errors)) {
                $esc_name = mysqli_real_escape_string($conn, $template_name);
                $esc_subject = mysqli_real_escape_string($conn, $subject);
                $esc_content = mysqli_real_escape_string($conn, $content);
                $esc_sig_name = mysqli_real_escape_string($conn, $sig_name);
                $esc_sig_des = mysqli_real_escape_string($conn, $sig_designation);
                $esc_logo = mysqli_real_escape_string($conn, $logo_path);

                if ($is_active === 1) {
                    // Deactivate all others
                    mysqli_query($conn, "UPDATE confirmation_letter_templates SET is_active = 0");
                }

                if ($template_id > 0) {
                    // Update
                    $sql = "UPDATE confirmation_letter_templates SET 
                            template_name = '$esc_name',
                            subject = '$esc_subject',
                            content = '$esc_content',
                            signature_name = '$esc_sig_name',
                            signature_designation = '$esc_sig_des',
                            logo_path = '$esc_logo',
                            is_active = $is_active
                            WHERE id = $template_id";
                } else {
                    // Insert
                    $sql = "INSERT INTO confirmation_letter_templates 
                            (template_name, subject, content, signature_name, signature_designation, logo_path, is_active)
                            VALUES ('$esc_name', '$esc_subject', '$esc_content', '$esc_sig_name', '$esc_sig_des', '$esc_logo', $is_active)";
                }

                if (mysqli_query($conn, $sql)) {
                    $success_msg = "Template saved successfully.";
                    if ($template_id === 0) {
                        $template_id = mysqli_insert_id($conn);
                    }
                } else {
                    $errors[] = "Database save error: " . mysqli_error($conn);
                }
            }
        }
    } elseif ($action === 'activate') {
        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            mysqli_query($conn, "UPDATE confirmation_letter_templates SET is_active = 0");
            if (mysqli_query($conn, "UPDATE confirmation_letter_templates SET is_active = 1 WHERE id = $template_id")) {
                $success_msg = "Template activated successfully.";
            } else {
                $errors[] = "Failed to activate template: " . mysqli_error($conn);
            }
        }
    } elseif ($action === 'delete') {
        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            // Verify it is not the active one, or allow deletion with fallback
            $active_check = mysqli_query($conn, "SELECT is_active FROM confirmation_letter_templates WHERE id = $template_id");
            $act_row = mysqli_fetch_assoc($active_check);
            if ($act_row && $act_row['is_active'] == 1) {
                $errors[] = "Cannot delete the active template. Please activate another template first.";
            } else {
                if (mysqli_query($conn, "DELETE FROM confirmation_letter_templates WHERE id = $template_id")) {
                    $success_msg = "Template deleted successfully.";
                } else {
                    $errors[] = "Delete failed: " . mysqli_error($conn);
                }
            }
        }
    } elseif ($action === 'restore_default') {
        mysqli_query($conn, "UPDATE confirmation_letter_templates SET is_active = 0");
        $esc_subject = mysqli_real_escape_string($conn, $default_subject);
        $esc_content = mysqli_real_escape_string($conn, $default_content);
        $esc_sig_name = mysqli_real_escape_string($conn, $default_sig_name);
        $esc_sig_des = mysqli_real_escape_string($conn, $default_sig_designation);

        $sql = "INSERT INTO confirmation_letter_templates 
                (template_name, subject, content, signature_name, signature_designation, logo_path, is_active)
                VALUES ('Restored Default Template', '$esc_subject', '$esc_content', '$esc_sig_name', '$esc_sig_des', '', 1)";
        if (mysqli_query($conn, $sql)) {
            $success_msg = "Default template restored and activated successfully.";
        } else {
            $errors[] = "Restore failed: " . mysqli_error($conn);
        }
    }
}

// Fetch all templates
$templates_res = mysqli_query($conn, "SELECT * FROM confirmation_letter_templates ORDER BY id DESC");
$templates = [];
while ($t_row = mysqli_fetch_assoc($templates_res)) {
    $templates[] = $t_row;
}

// Determine active template or template being edited
$edit_template = null;
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
if ($edit_id > 0) {
    foreach ($templates as $t) {
        if (intval($t['id']) === $edit_id) {
            $edit_template = $t;
            break;
        }
    }
}

// Fallback to active template or seed values
if (!$edit_template && !empty($templates)) {
    // Look for active template
    foreach ($templates as $t) {
        if ($t['is_active'] == 1) {
            $edit_template = $t;
            break;
        }
    }
    // If no active, grab first one
    if (!$edit_template) {
        $edit_template = $templates[0];
    }
}

// If completely empty, make a mock object
if (!$edit_template) {
    $edit_template = [
        'id' => 0,
        'template_name' => 'Default Template',
        'subject' => $default_subject,
        'content' => $default_content,
        'signature_name' => $default_sig_name,
        'signature_designation' => $default_sig_designation,
        'logo_path' => '',
        'is_active' => 1
    ];
}

// Generate Live Preview Mock Data
$mock_student_name = "Alex Mercer";
$mock_application_id = "IMP-1049";
$mock_project_title = "Advanced AI Integration System";
$mock_project_subtype = "Backend Engine Dev";
$mock_duration = "6 Months";
$mock_mode = "Hybrid (Office & Remote)";
$mock_mentor_name = "Dr. Rajesh Kumar";
$mock_team_name = "Alpha-Tech Project Team";
$mock_company_name = "IMP Core Labs";
$mock_joining_date = date('F d, Y', strtotime('+7 days'));
$mock_selection_date = date('F d, Y');

$mock_subject = $edit_template['subject'];
$mock_content = $edit_template['content'];

$placeholders = [
    '{student_name}' => $mock_student_name,
    '{application_id}' => $mock_application_id,
    '{project_title}' => $mock_project_title,
    '{project_subtype}' => $mock_project_subtype,
    '{duration}' => $mock_duration,
    '{mode}' => $mock_mode,
    '{mentor_name}' => $mock_mentor_name,
    '{team_name}' => $mock_team_name,
    '{company_name}' => $mock_company_name,
    '{joining_date}' => $mock_joining_date,
    '{selection_date}' => $mock_selection_date
];

foreach ($placeholders as $ph => $val) {
    $mock_subject = str_replace($ph, $val, $mock_subject);
    $mock_content = str_replace($ph, $val, $mock_content);
}

admin_template_header('Confirmation Letter Template');
?>

<div class="max-w-6xl mx-auto space-y-6">
    <?php if (!empty($errors)): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">error</span>
            <span><?php echo implode(' ', $errors); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success_msg !== ''): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700 flex items-center gap-2">
            <span class="material-symbols-outlined text-lg">check_circle</span>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Template List -->
        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-slate-800 text-sm uppercase tracking-wider">Templates Library</h2>
                    <a href="confirmation_letter_template.php?edit=0" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-sm">add</span> Add New
                    </a>
                </div>
                <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto pr-1">
                    <?php if (empty($templates)): ?>
                        <p class="text-xs text-slate-400 font-semibold py-4 text-center">No custom templates available.</p>
                    <?php else: ?>
                        <?php foreach ($templates as $tmpl): ?>
                            <div class="py-3 flex items-center justify-between gap-3 <?php echo intval($tmpl['id']) === intval($edit_template['id']) ? 'bg-slate-50 px-2 rounded-xl border border-slate-100' : ''; ?>">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-xs text-slate-800 truncate"><?php echo htmlspecialchars($tmpl['template_name']); ?></p>
                                        <?php if ($tmpl['is_active'] == 1): ?>
                                            <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider">Active</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-[10px] text-slate-400 truncate mt-0.5"><?php echo htmlspecialchars($tmpl['subject']); ?></p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <a href="confirmation_letter_template.php?edit=<?php echo $tmpl['id']; ?>" class="p-1 hover:bg-slate-100 text-slate-500 rounded" title="Edit">
                                        <span class="material-symbols-outlined text-base">edit</span>
                                    </a>
                                    <?php if ($tmpl['is_active'] != 1): ?>
                                        <form method="post" class="inline" onsubmit="return confirm('Activate this template?');">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="template_id" value="<?php echo $tmpl['id']; ?>">
                                            <button type="submit" class="p-1 hover:bg-emerald-50 text-emerald-600 rounded" title="Activate">
                                                <span class="material-symbols-outlined text-base">check_circle</span>
                                            </button>
                                        </form>
                                        <form method="post" class="inline" onsubmit="return confirm('Delete this template?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="template_id" value="<?php echo $tmpl['id']; ?>">
                                            <button type="submit" class="p-1 hover:bg-red-50 text-red-500 rounded" title="Delete">
                                                <span class="material-symbols-outlined text-base">delete</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Placeholders Guide Card -->
            <div class="bg-slate-900 rounded-2xl p-5 text-white shadow-sm space-y-3">
                <h3 class="font-bold text-xs uppercase tracking-wider text-slate-300">Placeholder Dictionary</h3>
                <p class="text-[11px] text-slate-400">Copy and paste these tags into your subject or body. They replace dynamically during generation:</p>
                <div class="grid grid-cols-2 gap-2 font-mono text-[10px]">
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{student_name}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{application_id}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{project_title}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{project_subtype}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{duration}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{mode}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{mentor_name}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{team_name}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{company_name}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{joining_date}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{selection_date}</div>
                </div>
            </div>

            <!-- Restore Defaults Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm text-center">
                <form method="post" onsubmit="return confirm('Are you sure you want to restore the system default template? This will activate a new default template.');">
                    <input type="hidden" name="action" value="restore_default">
                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all">
                        <span class="material-symbols-outlined text-base">restore</span> Restore Default Template
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: Editor & Preview Tabs (Col Span 2) -->
        <div class="lg:col-span-2 space-y-4">
            
            <!-- Tab Headers -->
            <div class="flex border-b border-slate-200 bg-white p-1.5 rounded-t-2xl">
                <button onclick="switchTab('editor-tab')" id="btn-editor-tab" class="flex-1 py-2.5 text-xs font-bold uppercase tracking-wider text-center rounded-xl transition-all border-b-2 border-blue-600 text-blue-600 bg-blue-50/50">
                    Edit Template
                </button>
                <button onclick="switchTab('preview-tab')" id="btn-preview-tab" class="flex-1 py-2.5 text-xs font-bold uppercase tracking-wider text-center rounded-xl transition-all text-slate-500 hover:text-slate-800">
                    Live Preview
                </button>
            </div>

            <!-- Tab 1: Editor Content -->
            <div id="editor-tab" class="bg-white border border-slate-200 rounded-b-2xl p-6 shadow-sm">
                <form method="post" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="template_id" value="<?php echo intval($edit_template['id']); ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Template Name
                            <input type="text" name="template_name" value="<?php echo htmlspecialchars($edit_template['template_name']); ?>" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        </label>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Email Subject
                            <input type="text" name="subject" value="<?php echo htmlspecialchars($edit_template['subject']); ?>" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        </label>
                    </div>

                    <!-- Custom Logo Upload -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex flex-col md:flex-row gap-4 items-center">
                        <div class="w-16 h-16 bg-white border border-slate-200 rounded-xl flex items-center justify-center overflow-hidden shrink-0">
                            <?php if (!empty($edit_template['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($edit_template['logo_path']); ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-slate-400 text-3xl">image</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-xs font-extrabold text-slate-700 uppercase tracking-wide">Company Logo</h4>
                            <p class="text-[10px] text-slate-400 mt-0.5">Upload a transparent PNG/JPG logo to render on the letterhead.</p>
                            <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp" class="mt-2 text-xs">
                        </div>
                    </div>

                    <!-- Editor Body -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Letter Content Body</label>
                            <div class="flex gap-2">
                                <button type="button" onclick="insertAtCursor('{student_name}')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold rounded text-slate-600 border border-slate-200">+ Student Name</button>
                                <button type="button" onclick="insertAtCursor('{project_title}')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold rounded text-slate-600 border border-slate-200">+ Project Title</button>
                            </div>
                        </div>
                        <textarea id="template-content-textarea" name="content" rows="10" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500 font-sans" style="line-height:1.6;"><?php echo htmlspecialchars($edit_template['content']); ?></textarea>
                    </div>

                    <!-- Signatures -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Signee Name
                            <input type="text" name="signature_name" value="<?php echo htmlspecialchars($edit_template['signature_name'] ?? ''); ?>" placeholder="e.g. HR Team / Program Director" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        </label>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Signee Designation
                            <input type="text" name="signature_designation" value="<?php echo htmlspecialchars($edit_template['signature_designation'] ?? ''); ?>" placeholder="e.g. IMP Platform" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        </label>
                    </div>

                    <div class="flex items-center gap-3 pt-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-600">
                            <input type="checkbox" name="is_active" value="1" <?php echo ($edit_template['is_active'] == 1) ? 'checked' : ''; ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Set as Active Template
                        </label>
                    </div>

                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-md shadow-blue-500/20 transition-all cursor-pointer">
                        <span class="material-symbols-outlined">save</span> Save Template Changes
                    </button>
                </form>
            </div>

            <!-- Tab 2: Live Preview -->
            <div id="preview-tab" class="hidden bg-slate-100 border border-slate-200 rounded-b-2xl p-6 shadow-sm min-h-[500px] flex items-center justify-center">
                <!-- Mock PDF Letterhead -->
                <div class="bg-white w-full max-w-[650px] shadow-lg border border-slate-200 rounded-xl p-10 font-sans space-y-6 relative text-slate-800">
                    
                    <!-- Decorative watermarks -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50/50 rounded-full translate-x-12 -translate-y-12 select-none"></div>

                    <!-- Header -->
                    <div class="flex justify-between items-center border-b border-slate-200 pb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white font-extrabold text-xl shrink-0">
                                <?php if (!empty($edit_template['logo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($edit_template['logo_path']); ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    IMP
                                <?php endif; ?>
                            </div>
                            <div>
                                <h1 class="text-base font-black text-blue-600 tracking-tight">IMP</h1>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Internship Management Platform</p>
                            </div>
                        </div>
                        <div class="text-right text-[11px] text-slate-400">
                            <p class="font-bold text-slate-500">Ref: <?php echo $mock_application_id; ?></p>
                            <p class="mt-0.5"><?php echo $mock_selection_date; ?></p>
                        </div>
                    </div>

                    <!-- Meta Data -->
                    <div class="space-y-1.5 text-xs border-b border-slate-100 pb-4">
                        <p><span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Student:</span> <span class="font-bold text-slate-800"><?php echo $mock_student_name; ?></span></p>
                        <p><span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Project Allocation:</span> <span class="font-bold text-slate-800"><?php echo $mock_project_title; ?> ({project_subtype})</span></p>
                    </div>

                    <!-- Body -->
                    <div class="text-xs leading-relaxed text-slate-700 whitespace-pre-line space-y-4 font-sans">
                        <?php echo htmlspecialchars($mock_content); ?>
                    </div>

                    <!-- Signature block -->
                    <div class="pt-6 border-t border-slate-100 flex justify-between items-end">
                        <div class="space-y-1">
                            <p class="text-[11px] text-slate-400 italic">Sincerely,</p>
                            <p class="font-black text-slate-800 text-xs mt-3"><?php echo htmlspecialchars($edit_template['signature_name'] ?: 'HR Team'); ?></p>
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide"><?php echo htmlspecialchars($edit_template['signature_designation'] ?: 'IMP Platform'); ?></p>
                        </div>
                        <div class="text-right text-[9px] text-slate-400 font-mono">
                            System Verified Selection Letter
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
    function switchTab(tabId) {
        // Hide both tabs
        document.getElementById('editor-tab').classList.add('hidden');
        document.getElementById('preview-tab').classList.add('hidden');
        
        // Remove active button styles
        document.getElementById('btn-editor-tab').classList.remove('border-b-2', 'border-blue-600', 'text-blue-600', 'bg-blue-50/50');
        document.getElementById('btn-editor-tab').classList.add('text-slate-500', 'hover:text-slate-800');
        document.getElementById('btn-preview-tab').classList.remove('border-b-2', 'border-blue-600', 'text-blue-600', 'bg-blue-50/50');
        document.getElementById('btn-preview-tab').classList.add('text-slate-500', 'hover:text-slate-800');

        // Show active tab
        document.getElementById(tabId).classList.remove('hidden');
        
        // Style active button
        const activeBtn = document.getElementById('btn-' + tabId);
        activeBtn.classList.add('border-b-2', 'border-blue-600', 'text-blue-600', 'bg-blue-50/50');
        activeBtn.classList.remove('text-slate-500', 'hover:text-slate-800');
    }

    function insertAtCursor(placeholder) {
        const textarea = document.getElementById('template-content-textarea');
        const startPos = textarea.selectionStart;
        const endPos = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, startPos) + placeholder + text.substring(endPos, text.length);
        textarea.focus();
        textarea.selectionStart = startPos + placeholder.length;
        textarea.selectionEnd = startPos + placeholder.length;
    }
</script>

<?php admin_template_footer(); ?>
