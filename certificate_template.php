<?php
session_start();
include_once __DIR__ . '/includes/auth.php';
require_login();
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
require_once __DIR__ . '/includes/cloudinary_config.php';

// Access check: Admin only
$user_role = strtolower(trim($_SESSION['role'] ?? ''));
if ($user_role !== 'admin') {
    die("Unauthorized access. Only Admin can manage Certificate Templates.");
}

$errors = [];
$success_msg = '';

// Default values for restoration
$default_content = "has successfully completed the internship program as a {project_title} at the {company_name}.";
$default_sig_name = "Program Coordinator";
$default_sig_designation = "IMP Platform Director";

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
        $content = trim($_POST['content'] ?? '');
        $sig_name = trim($_POST['signature_name'] ?? '');
        $sig_designation = trim($_POST['signature_designation'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($template_name === '') $errors[] = "Template name is required.";
        if ($content === '') $errors[] = "Certificate content is required.";

        if (empty($errors)) {
            // Fetch existing template if exists to retain logo/seal
            $existing_logo = '';
            $existing_seal = '';
            if ($template_id > 0) {
                $check_q = mysqli_query($conn, "SELECT logo_path, seal_image FROM certificate_templates WHERE id = $template_id");
                $check_row = mysqli_fetch_assoc($check_q);
                $existing_logo = $check_row['logo_path'] ?? '';
                $existing_seal = $check_row['seal_image'] ?? '';
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
                    $filename = 'cert_logo_' . time() . '.' . $ext;
                    $local_path = $upload_dir . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $local_path)) {
                        $logo_path = 'uploads/templates/' . $filename;
                        try {
                            $secure_url = uploadToCloudinary($local_path, 'templates', false);
                            if (!empty($secure_url)) {
                                $logo_path = $secure_url;
                            }
                        } catch (Exception $e) {
                            error_log("Cloudinary upload failed for cert logo: " . $e->getMessage());
                        }
                    } else {
                        $errors[] = "Failed to save logo locally.";
                    }
                }
            }

            // Handle Seal Upload
            $seal_path = $existing_seal;
            if (isset($_FILES['seal_file']) && $_FILES['seal_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['seal_file'];
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "Seal file upload error.";
                } elseif (!in_array($ext, $allowed)) {
                    $errors[] = "Seal must be a JPG, PNG, or WEBP image.";
                } else {
                    $filename = 'cert_seal_' . time() . '.' . $ext;
                    $local_path = $upload_dir . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $local_path)) {
                        $seal_path = 'uploads/templates/' . $filename;
                        try {
                            $secure_url = uploadToCloudinary($local_path, 'templates', false);
                            if (!empty($secure_url)) {
                                $seal_path = $secure_url;
                            }
                        } catch (Exception $e) {
                            error_log("Cloudinary upload failed for cert seal: " . $e->getMessage());
                        }
                    } else {
                        $errors[] = "Failed to save seal locally.";
                    }
                }
            }

            if (empty($errors)) {
                $esc_name = mysqli_real_escape_string($conn, $template_name);
                $esc_content = mysqli_real_escape_string($conn, $content);
                $esc_sig_name = mysqli_real_escape_string($conn, $sig_name);
                $esc_sig_des = mysqli_real_escape_string($conn, $sig_designation);
                $esc_logo = mysqli_real_escape_string($conn, $logo_path);
                $esc_seal = mysqli_real_escape_string($conn, $seal_path);

                if ($is_active === 1) {
                    // Deactivate all others
                    mysqli_query($conn, "UPDATE certificate_templates SET is_active = 0");
                }

                if ($template_id > 0) {
                    // Update
                    $sql = "UPDATE certificate_templates SET 
                            template_name = '$esc_name',
                            content = '$esc_content',
                            signature_name = '$esc_sig_name',
                            signature_designation = '$esc_sig_des',
                            logo_path = '$esc_logo',
                            seal_image = '$esc_seal',
                            is_active = $is_active
                            WHERE id = $template_id";
                } else {
                    // Insert
                    $sql = "INSERT INTO certificate_templates 
                            (template_name, content, signature_name, signature_designation, logo_path, seal_image, is_active)
                            VALUES ('$esc_name', '$esc_content', '$esc_sig_name', '$esc_sig_des', '$esc_logo', '$esc_seal', $is_active)";
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
            mysqli_query($conn, "UPDATE certificate_templates SET is_active = 0");
            if (mysqli_query($conn, "UPDATE certificate_templates SET is_active = 1 WHERE id = $template_id")) {
                $success_msg = "Template activated successfully.";
            } else {
                $errors[] = "Failed to activate template: " . mysqli_error($conn);
            }
        }
    } elseif ($action === 'delete') {
        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            $active_check = mysqli_query($conn, "SELECT is_active FROM certificate_templates WHERE id = $template_id");
            $act_row = mysqli_fetch_assoc($active_check);
            if ($act_row && $act_row['is_active'] == 1) {
                $errors[] = "Cannot delete the active template. Please activate another template first.";
            } else {
                if (mysqli_query($conn, "DELETE FROM certificate_templates WHERE id = $template_id")) {
                    $success_msg = "Template deleted successfully.";
                } else {
                    $errors[] = "Delete failed: " . mysqli_error($conn);
                }
            }
        }
    } elseif ($action === 'restore_default') {
        mysqli_query($conn, "UPDATE certificate_templates SET is_active = 0");
        $esc_content = mysqli_real_escape_string($conn, $default_content);
        $esc_sig_name = mysqli_real_escape_string($conn, $default_sig_name);
        $esc_sig_des = mysqli_real_escape_string($conn, $default_sig_designation);

        $sql = "INSERT INTO certificate_templates 
                (template_name, content, signature_name, signature_designation, logo_path, seal_image, is_active)
                VALUES ('Restored Default Template', '$esc_content', '$esc_sig_name', '$esc_sig_des', '', '', 1)";
        if (mysqli_query($conn, $sql)) {
            $success_msg = "Default template restored and activated successfully.";
        } else {
            $errors[] = "Restore failed: " . mysqli_error($conn);
        }
    }
}

// Fetch all templates
$templates_res = mysqli_query($conn, "SELECT * FROM certificate_templates ORDER BY id DESC");
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
    foreach ($templates as $t) {
        if ($t['is_active'] == 1) {
            $edit_template = $t;
            break;
        }
    }
    if (!$edit_template) {
        $edit_template = $templates[0];
    }
}

if (!$edit_template) {
    $edit_template = [
        'id' => 0,
        'template_name' => 'Default Template',
        'content' => $default_content,
        'signature_name' => $default_sig_name,
        'signature_designation' => $default_sig_designation,
        'logo_path' => '',
        'seal_image' => '',
        'is_active' => 1
    ];
}

// Generate Preview Mock Data
$mock_student_name = "Alex Mercer";
$mock_certificate_id = "IMP-2026-AL-01049";
$mock_project_title = "Advanced AI Integration System";
$mock_project_subtype = "Backend Engine Dev";
$mock_duration = "3 Months";
$mock_mode = "Remote";
$mock_mentor_name = "Dr. Rajesh Kumar";
$mock_team_name = "Alpha-Tech Team";
$mock_start_date = date('M d, Y', strtotime('-90 days'));
$mock_completion_date = date('M d, Y');
$mock_company_name = "Internship Management Platform (IMP)";

$mock_content = $edit_template['content'];

$placeholders = [
    '{student_name}' => $mock_student_name,
    '{certificate_id}' => $mock_certificate_id,
    '{project_title}' => $mock_project_title,
    '{project_subtype}' => $mock_project_subtype,
    '{duration}' => $mock_duration,
    '{mode}' => $mock_mode,
    '{mentor_name}' => $mock_mentor_name,
    '{team_name}' => $mock_team_name,
    '{start_date}' => $mock_start_date,
    '{completion_date}' => $mock_completion_date,
    '{company_name}' => $mock_company_name
];

foreach ($placeholders as $ph => $val) {
    $mock_content = str_replace($ph, $val, $mock_content);
}

page_shell_start('certificate_template', 'Certificate Template', 'Define, modify and preview dynamic completion certificate templates.');
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
        
        <!-- Left Column: Template List & Placeholders -->
        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-slate-800 text-sm uppercase tracking-wider">Templates Library</h2>
                    <a href="certificate_template.php?edit=0" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-0.5">
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
                                </div>
                                <div class="flex items-center gap-1">
                                    <a href="certificate_template.php?edit=<?php echo $tmpl['id']; ?>" class="p-1 hover:bg-slate-100 text-slate-500 rounded" title="Edit">
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
                <p class="text-[11px] text-slate-400">Copy and paste these tags into the certificate text. They replace dynamically during generation:</p>
                <div class="grid grid-cols-2 gap-2 font-mono text-[10px]">
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{student_name}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{certificate_id}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{project_title}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{project_subtype}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{duration}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{mode}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{mentor_name}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{team_name}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{start_date}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{completion_date}</div>
                    <div class="bg-white/10 p-1.5 rounded cursor-pointer hover:bg-white/20 select-all" title="Click to copy text">{company_name}</div>
                </div>
            </div>

            <!-- Restore Defaults Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm text-center">
                <form method="post" onsubmit="return confirm('Restore default completion certificate template?');">
                    <input type="hidden" name="action" value="restore_default">
                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all">
                        <span class="material-symbols-outlined text-base">restore</span> Restore Default Certificate
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Column: Editor & Preview Tabs -->
        <div class="lg:col-span-2 space-y-4">
            
            <!-- Tab Headers -->
            <div class="flex border-b border-slate-200 bg-white p-1.5 rounded-t-2xl">
                <button onclick="switchTab('editor-tab')" id="btn-editor-tab" class="flex-1 py-2.5 text-xs font-bold uppercase tracking-wider text-center rounded-xl transition-all border-b-2 border-blue-600 text-blue-600 bg-blue-50/50">
                    Edit Certificate Template
                </button>
                <button onclick="switchTab('preview-tab')" id="btn-preview-tab" class="flex-1 py-2.5 text-xs font-bold uppercase tracking-wider text-center rounded-xl transition-all text-slate-500 hover:text-slate-800">
                    Landscape Preview
                </button>
            </div>

            <!-- Tab 1: Editor Form -->
            <div id="editor-tab" class="bg-white border border-slate-200 rounded-b-2xl p-6 shadow-sm">
                <form method="post" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="template_id" value="<?php echo intval($edit_template['id']); ?>">
                    
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Template Title Name
                        <input type="text" name="template_name" value="<?php echo htmlspecialchars($edit_template['template_name']); ?>" required class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                    </label>

                    <!-- Branding Logo and Official Seal Stamp -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Logo -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex gap-4 items-center">
                            <div class="w-16 h-16 bg-white border border-slate-200 rounded-xl flex items-center justify-center overflow-hidden shrink-0">
                                <?php if (!empty($edit_template['logo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($edit_template['logo_path']); ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-slate-400 text-3xl">image</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xs font-extrabold text-slate-700 uppercase tracking-wide">Logo Brand</h4>
                                <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp" class="mt-2 text-xs">
                            </div>
                        </div>

                        <!-- Seal -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex gap-4 items-center">
                            <div class="w-16 h-16 bg-white border border-slate-200 rounded-xl flex items-center justify-center overflow-hidden shrink-0">
                                <?php if (!empty($edit_template['seal_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($edit_template['seal_image']); ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-slate-400 text-3xl">verified</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xs font-extrabold text-slate-700 uppercase tracking-wide">Official Seal Stamp</h4>
                                <input type="file" name="seal_file" accept=".png,.jpg,.jpeg,.webp" class="mt-2 text-xs">
                            </div>
                        </div>
                    </div>

                    <!-- Certificate Text Content -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Certificate Certification Text</label>
                            <button type="button" onclick="insertAtCursor('{project_title}')" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold rounded text-slate-600 border border-slate-200">+ Project Title</button>
                        </div>
                        <textarea id="template-content-textarea" name="content" rows="6" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500 font-sans" style="line-height:1.6;"><?php echo htmlspecialchars($edit_template['content']); ?></textarea>
                    </div>

                    <!-- Signature Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Signature / Left Name
                            <input type="text" name="signature_name" value="<?php echo htmlspecialchars($edit_template['signature_name'] ?? ''); ?>" placeholder="e.g. Program Coordinator" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        </label>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Signature / Right Name
                            <input type="text" name="signature_designation" value="<?php echo htmlspecialchars($edit_template['signature_designation'] ?? ''); ?>" placeholder="e.g. Program Director" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-700 focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        </label>
                    </div>

                    <div class="flex items-center gap-3 pt-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-600">
                            <input type="checkbox" name="is_active" value="1" <?php echo ($edit_template['is_active'] == 1) ? 'checked' : ''; ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Set as Active Template
                        </label>
                    </div>

                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-xl shadow-md shadow-blue-500/20 transition-all cursor-pointer">
                        <span class="material-symbols-outlined">save</span> Save Certificate Template
                    </button>
                </form>
            </div>

            <!-- Tab 2: Landscape Certificate Preview -->
            <div id="preview-tab" class="hidden bg-slate-100 border border-slate-200 rounded-b-2xl p-6 shadow-sm min-h-[500px] flex items-center justify-center">
                <!-- Mock PDF Landscape -->
                <div class="bg-white w-full max-w-[700px] shadow-2xl rounded-xl p-10 relative text-slate-800 border-8 border-double border-blue-600 text-center">
                    
                    <!-- Top Logo / Brand -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center text-white font-black text-xl mb-2 overflow-hidden shadow-md">
                            <?php if (!empty($edit_template['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($edit_template['logo_path']); ?>" class="w-full h-full object-contain">
                            <?php else: ?>
                                IMP
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xs font-black text-blue-600 tracking-widest uppercase">Internship Management Platform</h2>
                    </div>

                    <!-- Certificate Title -->
                    <h1 class="text-2xl font-black text-indigo-600 uppercase tracking-widest mb-3" style="font-family:'Playfair Display', serif;">Certificate of Completion</h1>
                    <p class="text-slate-400 text-xs italic">This is to certify that</p>
                    
                    <!-- Candidate Name -->
                    <h2 class="text-3xl font-bold text-slate-900 my-4" style="font-family:'Times New Roman', serif;"><?php echo $mock_student_name; ?></h2>
                    
                    <!-- Dynamic Description Body -->
                    <p class="text-slate-600 text-xs max-w-lg mx-auto leading-relaxed mt-2">
                        <?php echo htmlspecialchars($mock_content); ?>
                    </p>

                    <!-- Details Table Mock -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 my-6 grid grid-cols-4 gap-4 text-left text-[10px] text-slate-600 max-w-xl mx-auto">
                        <div>
                            <span class="text-slate-400 font-bold block uppercase mb-0.5">Project</span>
                            <span class="font-bold text-slate-800 text-[11px] truncate block"><?php echo $mock_project_title; ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block uppercase mb-0.5">Program Dates</span>
                            <span class="font-bold text-slate-800 text-[11px] block"><?php echo $mock_start_date; ?> - <?php echo $mock_completion_date; ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block uppercase mb-0.5">Duration</span>
                            <span class="font-bold text-slate-800 text-[11px] block"><?php echo $mock_duration; ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block uppercase mb-0.5">Assessment</span>
                            <span class="font-bold text-slate-800 text-[11px] block">VERIFIED</span>
                        </div>
                    </div>

                    <!-- Signatures & Seal -->
                    <div class="flex items-end justify-between max-w-xl mx-auto mt-6 text-xs">
                        <div class="text-center w-36">
                            <p class="border-b border-slate-400 pb-1 italic text-slate-600"><?php echo htmlspecialchars($edit_template['signature_name'] ?: 'Program Coordinator'); ?></p>
                            <p class="text-[9px] font-bold text-slate-400 uppercase mt-1">Left Signee</p>
                        </div>
                        
                        <!-- Official Seal Stamp -->
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 rounded-full border-4 border-blue-200 flex items-center justify-center overflow-hidden shrink-0">
                                <?php if (!empty($edit_template['seal_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($edit_template['seal_image']); ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <span class="material-symbols-outlined text-blue-500 text-2xl">verified</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-[8px] font-extrabold text-blue-600 uppercase tracking-widest mt-1">Official Seal</span>
                        </div>

                        <div class="text-center w-36">
                            <p class="border-b border-slate-400 pb-1 italic text-slate-600"><?php echo htmlspecialchars($edit_template['signature_designation'] ?: 'Program Director'); ?></p>
                            <p class="text-[9px] font-bold text-slate-400 uppercase mt-1">Right Signee</p>
                        </div>
                    </div>

                    <!-- Certificate ID -->
                    <p class="text-[9px] text-slate-400 font-mono mt-8">Certificate ID: <?php echo $mock_certificate_id; ?></p>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
    function switchTab(tabId) {
        document.getElementById('editor-tab').classList.add('hidden');
        document.getElementById('preview-tab').classList.add('hidden');
        
        document.getElementById('btn-editor-tab').classList.remove('border-b-2', 'border-blue-600', 'text-blue-600', 'bg-blue-50/50');
        document.getElementById('btn-editor-tab').classList.add('text-slate-500', 'hover:text-slate-800');
        document.getElementById('btn-preview-tab').classList.remove('border-b-2', 'border-blue-600', 'text-blue-600', 'bg-blue-50/50');
        document.getElementById('btn-preview-tab').classList.add('text-slate-500', 'hover:text-slate-800');

        document.getElementById(tabId).classList.remove('hidden');
        
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

<?php page_shell_end(); ?>
