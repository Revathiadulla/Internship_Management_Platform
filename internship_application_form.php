<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$internship_id = isset($_GET['internship_id']) ? intval($_GET['internship_id']) : 0;

// Fetch internship data from DB
$internship = null;
if ($internship_id > 0) {
    $internship_sql = "SELECT * FROM internships WHERE id = '$internship_id' LIMIT 1";
    $internship_result = mysqli_query($conn, $internship_sql);
    $internship = mysqli_fetch_assoc($internship_result);
}

// Fall back to URL params for static/preset cards
if (!$internship) {
    $name_from_url = isset($_GET['name']) ? trim(strip_tags($_GET['name'])) : '';
    if ($name_from_url === '') {
        header("Location: student_browse_internships.php");
        exit();
    }
    $internship = [
        'id'       => 0,
        'title'    => $name_from_url,
        'duration' => isset($_GET['duration']) ? trim(strip_tags($_GET['duration'])) : 'As per requirement',
        'mode'     => isset($_GET['mode'])     ? trim(strip_tags($_GET['mode']))     : 'Remote',
        'skills'   => isset($_GET['skills'])   ? trim(strip_tags($_GET['skills']))   : '',
        'domain'   => isset($_GET['domain'])   ? trim(strip_tags($_GET['domain']))   : '',
    ];
}
$internship_name = $internship['title'];

// Fetch student profile
$sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$result = mysqli_query($conn, $sql);
$profile = mysqli_fetch_assoc($result);

if (!$profile) {
    header("Location: student_profile_form.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply – <?php echo htmlspecialchars($internship_name); ?> | IMP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@400,0,0,24" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

        /* Stepper */
        .step-dot { transition: all .3s ease; }
        .step-dot.active { background:#2563eb; color:#fff; border-color:#2563eb; }
        .step-dot.done   { background:#16a34a; color:#fff; border-color:#16a34a; }
        .step-line       { transition: background .3s ease; }
        .step-line.done  { background:#16a34a; }

        /* Sections */
        .form-section { display:none; }
        .form-section.active { display:block; animation: slideIn .3s ease; }
        @keyframes slideIn {
            from { opacity:0; transform:translateY(10px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* Fields */
        .field input:focus, .field select:focus, .field textarea:focus {
            outline:none; border-color:#2563eb;
            box-shadow:0 0 0 3px rgba(37,99,235,.12);
        }
        .field input, .field select, .field textarea { transition: border-color .2s, box-shadow .2s; }
        .field.error input, .field.error select, .field.error textarea {
            border-color:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.1);
        }
        .field .err-msg { display:none; color:#ef4444; font-size:11px; margin-top:4px; }
        .field.error .err-msg { display:block; }

        /* Upload zone */
        .upload-zone { border:2px dashed #cbd5e1; transition: border-color .2s, background .2s; cursor:pointer; }
        .upload-zone:hover { border-color:#2563eb; background:#eff6ff; }
        .upload-zone.has-file { border-color:#16a34a; background:#f0fdf4; }

        /* Radio cards */
        .edu-card input[type=radio] { display:none; }
        .edu-card label {
            display:flex; align-items:center; gap:12px;
            padding:14px 18px; border:2px solid #e2e8f0;
            border-radius:12px; cursor:pointer;
            transition: border-color .2s, background .2s;
        }
        .edu-card input[type=radio]:checked + label { border-color:#2563eb; background:#eff6ff; }
        .edu-card .radio-dot {
            width:18px; height:18px; border-radius:50%;
            border:2px solid #94a3b8; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
            transition: border-color .2s;
        }
        .edu-card input[type=radio]:checked + label .radio-dot { border-color:#2563eb; }
        .edu-card input[type=radio]:checked + label .radio-dot::after {
            content:''; width:8px; height:8px; border-radius:50%; background:#2563eb;
        }

        /* PAN mask display */
        .pan-masked { letter-spacing:.15em; font-family:monospace; }

        /* Success overlay */
        #success-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(15,23,42,.65); z-index:999;
            align-items:center; justify-content:center;
        }
        #success-overlay.show { display:flex; }

        /* Read-only info pill */
        .info-pill {
            display:inline-flex; align-items:center; gap:6px;
            background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:10px; padding:6px 12px;
            font-size:12px; font-weight:600; color:#475569;
        }
        .info-pill .material-symbols-outlined { font-size:15px; color:#2563eb; }
    </style>
</head>
<body class="min-h-screen text-slate-800 antialiased">

<!-- ── Navbar ── -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-3xl mx-auto px-6 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-lg shadow-sm">I</div>
            <span class="text-lg font-bold text-slate-800 tracking-tight">IMP</span>
            <span class="hidden sm:inline text-slate-300 mx-2">|</span>
            <span class="hidden sm:inline text-xs font-semibold text-slate-500 uppercase tracking-wider">Internship Application</span>
        </div>
        <a href="student_browse_internships.php" class="flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-red-500 transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Cancel
        </a>
    </div>
</header>

<!-- ── Internship Info Card (Read-Only) ── -->
<div class="bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-600 text-white">
    <div class="max-w-3xl mx-auto px-6 py-7">
        <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-3 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[14px]">lock</span> Selected Internship — Read Only
        </p>
        <div class="flex items-start gap-4">
            <div class="w-13 h-13 w-12 h-12 bg-white/15 rounded-2xl flex items-center justify-center flex-shrink-0 border border-white/20">
                <span class="material-symbols-outlined text-white text-2xl">work</span>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl sm:text-2xl font-black leading-tight mb-3"><?php echo htmlspecialchars($internship_name); ?></h1>
                <div class="flex flex-wrap gap-2">
                    <?php if (!empty($internship['duration'])): ?>
                    <span class="info-pill bg-white/10 border-white/20 text-white">
                        <span class="material-symbols-outlined" style="color:#93c5fd">schedule</span>
                        <?php echo htmlspecialchars($internship['duration']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($internship['mode'])): ?>
                    <span class="info-pill bg-white/10 border-white/20 text-white">
                        <span class="material-symbols-outlined" style="color:#93c5fd">laptop_mac</span>
                        <?php echo htmlspecialchars(ucfirst($internship['mode'])); ?>
                    </span>
                    <?php endif; ?>
                    <?php
                    // Domain: from DB column or URL param
                    $domain_display = '';
                    if (!empty($internship['domain'])) $domain_display = $internship['domain'];
                    elseif (!empty($internship['category'])) $domain_display = $internship['category'];
                    if ($domain_display): ?>
                    <span class="info-pill bg-white/10 border-white/20 text-white">
                        <span class="material-symbols-outlined" style="color:#93c5fd">category</span>
                        <?php echo htmlspecialchars($domain_display); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($internship['skills'])): ?>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <?php foreach (explode(',', $internship['skills']) as $sk): $sk = trim($sk); if ($sk): ?>
                    <span class="px-2.5 py-0.5 bg-white/15 border border-white/20 rounded-lg text-[11px] font-semibold text-blue-100">
                        <?php echo htmlspecialchars($sk); ?>
                    </span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Progress Stepper (3 steps) ── -->
<div class="bg-white border-b border-slate-100 shadow-sm">
    <div class="max-w-3xl mx-auto px-6 py-4">
        <div class="flex items-center justify-between relative">
            <div class="absolute top-4 left-0 right-0 flex px-10">
                <div id="line-1" class="step-line flex-1 h-0.5 bg-slate-200 mx-1"></div>
                <div id="line-2" class="step-line flex-1 h-0.5 bg-slate-200 mx-1"></div>
            </div>
            <div class="flex flex-col items-center z-10">
                <div id="dot-1" class="step-dot active w-8 h-8 rounded-full border-2 border-blue-600 bg-blue-600 text-white flex items-center justify-center text-xs font-bold">1</div>
                <span class="text-[10px] font-semibold text-slate-500 mt-1.5 hidden sm:block">Personal</span>
            </div>
            <div class="flex flex-col items-center z-10">
                <div id="dot-2" class="step-dot w-8 h-8 rounded-full border-2 border-slate-300 bg-white text-slate-400 flex items-center justify-center text-xs font-bold">2</div>
                <span class="text-[10px] font-semibold text-slate-400 mt-1.5 hidden sm:block">Academic</span>
            </div>
            <div class="flex flex-col items-center z-10">
                <div id="dot-3" class="step-dot w-8 h-8 rounded-full border-2 border-slate-300 bg-white text-slate-400 flex items-center justify-center text-xs font-bold">3</div>
                <span class="text-[10px] font-semibold text-slate-400 mt-1.5 hidden sm:block">Verification</span>
            </div>
        </div>
    </div>
</div>

<!-- ── Main Form ── -->
<main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 pb-20">
<form id="app-form" action="internship_application_submit.php" method="POST" enctype="multipart/form-data" novalidate>

    <input type="hidden" name="internship_id"    value="<?php echo (int)$internship['id']; ?>">
    <input type="hidden" name="internship_name"  value="<?php echo htmlspecialchars($internship_name); ?>">
    <input type="hidden" name="profile_id"       value="<?php echo (int)$profile['id']; ?>">
    <input type="hidden" name="education_status" id="hidden-edu-status" value="">
    <!-- Pass internship meta for submit handler -->
    <input type="hidden" name="internship_duration" value="<?php echo htmlspecialchars($internship['duration'] ?? ''); ?>">
    <input type="hidden" name="internship_mode"     value="<?php echo htmlspecialchars($internship['mode'] ?? ''); ?>">

    <!-- ══════════════════════════════════════
         STEP 1 — Personal Information
    ══════════════════════════════════════ -->
    <div id="section-1" class="form-section active space-y-6">

        <!-- Personal Info Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 text-[20px]">person</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800">Personal Information</h2>
                    <p class="text-xs text-slate-500">Pre-filled from your profile — edit if needed</p>
                </div>
                <span class="ml-auto text-[10px] font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full border border-blue-100">Step 1 of 3</span>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">

                <div class="field sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" id="full_name"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                           value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                    <span class="err-msg">Full name is required.</span>
                </div>

                <div class="field">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" id="email"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                           value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                    <span class="err-msg">Enter a valid email address.</span>
                </div>

                <div class="field">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Phone Number <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" id="phone"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                           value="<?php echo htmlspecialchars($profile['phone']); ?>" required>
                    <span class="err-msg">Phone number is required.</span>
                </div>

                <div class="field sm:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Skills <span class="text-red-500">*</span></label>
                    <input type="text" name="skills" id="skills"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                           value="<?php echo htmlspecialchars($profile['skills']); ?>"
                           placeholder="e.g. React.js, Python, Figma, SQL" required>
                    <span class="err-msg">Please list your skills.</span>
                    <p class="text-[11px] text-slate-400 mt-1">Separate skills with commas</p>
                </div>

            </div>
        </div>

        <!-- Education Status Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-100 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-indigo-600 text-[20px]">school</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800">Education Status</h2>
                    <p class="text-xs text-slate-500">Determines your application approval workflow</p>
                </div>
            </div>
            <div class="p-6">
                <p class="text-sm font-semibold text-slate-700 mb-3">Are you currently pursuing or have you passed out? <span class="text-red-500">*</span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="edu-card">
                        <input type="radio" name="edu_status_radio" id="edu-pursuing" value="Pursuing">
                        <label for="edu-pursuing">
                            <div class="radio-dot"></div>
                            <div>
                                <p class="font-semibold text-slate-800 text-sm">Currently Pursuing</p>
                                <p class="text-xs text-slate-500 mt-0.5">Still enrolled in college/university</p>
                            </div>
                        </label>
                    </div>
                    <div class="edu-card">
                        <input type="radio" name="edu_status_radio" id="edu-passedout" value="Passed Out">
                        <label for="edu-passedout">
                            <div class="radio-dot"></div>
                            <div>
                                <p class="font-semibold text-slate-800 text-sm">Passed Out</p>
                                <p class="text-xs text-slate-500 mt-0.5">Completed graduation / alumni</p>
                            </div>
                        </label>
                    </div>
                </div>
                <span id="edu-status-err" class="hidden text-red-500 text-xs mt-2 block">Please select your education status to continue.</span>
                <div id="workflow-badge" class="hidden mt-4 p-3 rounded-xl border text-xs font-medium flex items-start gap-2"></div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" onclick="nextStep(1)"
                    class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm transition-all flex items-center gap-2">
                Next: Academic Info <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         STEP 2 — Academic Information
    ══════════════════════════════════════ -->
    <div id="section-2" class="form-section space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                <div class="w-9 h-9 bg-purple-100 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-purple-600 text-[20px]">menu_book</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800">Academic Information</h2>
                    <p class="text-xs text-slate-500" id="academic-subtitle">Fill in your academic details</p>
                </div>
                <span class="ml-auto text-[10px] font-bold text-purple-600 bg-purple-50 px-2.5 py-1 rounded-full border border-purple-100">Step 2 of 3</span>
            </div>
            <div class="p-6 space-y-5">

                <!-- PURSUING fields -->
                <div id="pursuing-fields">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <div class="field sm:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">College Name <span class="text-red-500">*</span></label>
                            <input type="text" name="college_name" id="college_name"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                                   value="<?php echo htmlspecialchars($profile['college_name']); ?>"
                                   placeholder="e.g. Anna University">
                            <span class="err-msg">College name is required.</span>
                        </div>

                        <div class="field">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Department <span class="text-red-500">*</span></label>
                            <input type="text" name="department" id="department"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                                   value="<?php echo htmlspecialchars($profile['course'] ?? ''); ?>"
                                   placeholder="e.g. Computer Science">
                            <span class="err-msg">Department is required.</span>
                        </div>

                        <div class="field">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Year of Study <span class="text-red-500">*</span></label>
                            <select name="year_of_study" id="year_of_study"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white">
                                <option value="">Select year</option>
                                <option value="1st Year" <?php echo ($profile['year_of_study']=='1st Year')?'selected':''; ?>>1st Year</option>
                                <option value="2nd Year" <?php echo ($profile['year_of_study']=='2nd Year')?'selected':''; ?>>2nd Year</option>
                                <option value="3rd Year" <?php echo ($profile['year_of_study']=='3rd Year')?'selected':''; ?>>3rd Year</option>
                                <option value="4th Year" <?php echo ($profile['year_of_study']=='4th Year')?'selected':''; ?>>4th Year</option>
                                <option value="PG 1st Year">PG 1st Year</option>
                                <option value="PG 2nd Year">PG 2nd Year</option>
                            </select>
                            <span class="err-msg">Please select your year of study.</span>
                        </div>

                        <div class="field">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                HOD Name <span class="text-red-500">*</span>
                                <span class="ml-1 text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Required for approval</span>
                            </label>
                            <input type="text" name="hod_name" id="hod_name"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                                   placeholder="e.g. Dr. Ramesh Kumar">
                            <span class="err-msg">HOD name is required.</span>
                        </div>

                        <div class="field">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                HOD Email ID <span class="text-red-500">*</span>
                                <span class="ml-1 text-[10px] font-bold text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Approval sent here</span>
                            </label>
                            <input type="email" name="hod_email" id="hod_email"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                                   placeholder="hod@college.edu">
                            <span class="err-msg">Valid HOD email is required.</span>
                        </div>

                    </div>
                    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2 text-xs text-amber-800">
                        <span class="material-symbols-outlined text-amber-500 text-[16px] mt-0.5">info</span>
                        <span>Your application goes to <strong>HR Screening first</strong>. After HR approves, an approval request will be sent to your HOD. Internship starts only after HOD approves.</span>
                    </div>
                </div>

                <!-- PASSED OUT fields -->
                <div id="passedout-fields" class="hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

                        <div class="field">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Graduation Year <span class="text-red-500">*</span></label>
                            <select name="graduation_year" id="graduation_year"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white">
                                <option value="">Select year</option>
                                <?php for($y = date('Y'); $y >= 2010; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="err-msg">Please select your graduation year.</span>
                        </div>

                        <div class="field">
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Previous College Name <span class="text-red-500">*</span></label>
                            <input type="text" name="prev_college_name" id="prev_college_name"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white"
                                   value="<?php echo htmlspecialchars($profile['college_name']); ?>"
                                   placeholder="e.g. PSG College of Technology">
                            <span class="err-msg">Previous college name is required.</span>
                        </div>

                    </div>
                    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-xl flex items-start gap-2 text-xs text-green-800">
                        <span class="material-symbols-outlined text-green-500 text-[16px] mt-0.5">check_circle</span>
                        <span>As a passed-out student, your application goes through <strong>HR Screening → HR Approval</strong> and then directly to onboarding. HOD approval is not required.</span>
                    </div>
                </div>

            </div>
        </div>

        <div class="flex justify-between">
            <button type="button" onclick="prevStep(2)"
                    class="px-6 py-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold rounded-xl shadow-sm transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back
            </button>
            <button type="button" onclick="nextStep(2)"
                    class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm transition-all flex items-center gap-2">
                Next: Verification <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════════
         STEP 3 — Verification & Resume
    ══════════════════════════════════════ -->
    <div id="section-3" class="form-section space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                <div class="w-9 h-9 bg-green-100 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600 text-[20px]">verified_user</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800">Verification & Resume</h2>
                    <p class="text-xs text-slate-500">Identity verification and document upload</p>
                </div>
                <span class="ml-auto text-[10px] font-bold text-green-600 bg-green-50 px-2.5 py-1 rounded-full border border-green-100">Step 3 of 3</span>
            </div>
            <div class="p-6 space-y-6">

                <!-- ── Single clean notice ── -->
                <div class="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                    <span class="material-symbols-outlined text-slate-400 text-[22px] flex-shrink-0">shield</span>
                    <p class="text-sm text-slate-600">Verification details are securely fetched from your student profile.</p>
                </div>

                <!-- ── Verification Status Badges ── -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <!-- Aadhaar Badge -->
                    <div class="flex items-center gap-3 p-4 bg-white border border-slate-200 rounded-xl shadow-sm">
                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-green-600 text-[20px]">badge</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-0.5">Aadhaar</p>
                            <?php
                            $aadhaar_val = $profile['aadhaar_number'] ?? '';
                            $aadhaar_ok  = strlen(preg_replace('/\s+/', '', $aadhaar_val)) === 12;
                            ?>
                            <?php if ($aadhaar_ok): ?>
                                <p class="text-sm font-bold text-slate-700 font-mono tracking-widest">
                                    <?php
                                    $d = preg_replace('/\s+/', '', $aadhaar_val);
                                    echo '•••• •••• ' . substr($d, -4);
                                    ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-slate-400 italic">Not provided</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($aadhaar_ok): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-green-50 border border-green-200 rounded-full text-[11px] font-bold text-green-700 flex-shrink-0">
                            <span class="material-symbols-outlined text-[13px]">check_circle</span> Verified
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 border border-amber-200 rounded-full text-[11px] font-bold text-amber-700 flex-shrink-0">
                            <span class="material-symbols-outlined text-[13px]">pending</span> Pending
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- PAN Badge -->
                    <div class="flex items-center gap-3 p-4 bg-white border border-slate-200 rounded-xl shadow-sm">
                        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-green-600 text-[20px]">credit_card</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-0.5">PAN Card</p>
                            <?php
                            $pan_val = strtoupper(trim($profile['pan_number'] ?? ''));
                            $pan_ok  = preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_val);
                            ?>
                            <?php if ($pan_ok): ?>
                                <p class="text-sm font-bold text-slate-700 font-mono tracking-widest">
                                    <?php echo substr($pan_val, 0, 5) . '****' . substr($pan_val, -1); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-slate-400 italic">Not provided</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($pan_ok): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-green-50 border border-green-200 rounded-full text-[11px] font-bold text-green-700 flex-shrink-0">
                            <span class="material-symbols-outlined text-[13px]">check_circle</span> Verified
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 border border-amber-200 rounded-full text-[11px] font-bold text-amber-700 flex-shrink-0">
                            <span class="material-symbols-outlined text-[13px]">pending</span> Pending
                        </span>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Pass Aadhaar & PAN as hidden fields for the submit handler -->
                <input type="hidden" name="aadhaar_number" value="<?php echo htmlspecialchars(preg_replace('/\s+/', '', $profile['aadhaar_number'] ?? '')); ?>">
                <input type="hidden" name="pan_number"     value="<?php echo htmlspecialchars(strtoupper(trim($profile['pan_number'] ?? ''))); ?>">
                <?php if (!empty($profile['pan_file'])): ?>
                <input type="hidden" name="existing_pan"   value="<?php echo htmlspecialchars($profile['pan_file']); ?>">
                <?php endif; ?>

                <hr class="border-slate-100">

                <!-- ── Resume Upload ── -->
                <div>
                    <h3 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400 text-[18px]">description</span>
                        Resume
                    </h3>
                    <div class="field">
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Resume Upload <span class="text-red-500">*</span></label>
                        <div id="upload-zone" class="upload-zone rounded-xl p-6 text-center"
                             onclick="document.getElementById('resume_file').click()">
                            <span class="material-symbols-outlined text-slate-400 text-3xl mb-2 block">upload_file</span>
                            <p class="text-sm font-semibold text-slate-600" id="upload-label">Click to upload your resume</p>
                            <p class="text-xs text-slate-400 mt-1">PDF or DOC/DOCX only · Max 5MB</p>
                        </div>
                        <input type="file" name="resume_file" id="resume_file"
                               accept=".pdf,.doc,.docx" class="hidden">
                        <span class="err-msg" id="resume-err">Please upload your resume (PDF or DOC).</span>
                        <?php if (!empty($profile['resume_file'])): ?>
                        <div class="mt-2 flex items-center gap-2 p-2.5 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-600">
                            <span class="material-symbols-outlined text-red-400 text-[16px]">picture_as_pdf</span>
                            <span class="truncate flex-1">Current: <?php echo htmlspecialchars(basename($profile['resume_file'])); ?></span>
                            <span class="text-slate-400 flex-shrink-0">(upload new to replace)</span>
                        </div>
                        <input type="hidden" name="existing_resume" value="<?php echo htmlspecialchars($profile['resume_file']); ?>">
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="border-slate-100">

                <!-- ── Declaration ── -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="declaration" name="declaration"
                               class="mt-0.5 w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 flex-shrink-0">
                        <span class="text-sm text-slate-600 leading-relaxed">
                            I declare that all information provided in this application is true and correct to the best of my knowledge.
                            I understand that any false information may lead to rejection of my application or termination of internship.
                        </span>
                    </label>
                    <span id="decl-err" class="hidden text-red-500 text-xs mt-2 block">You must accept the declaration to submit.</span>
                </div>

            </div>
        </div>

        <div class="flex justify-between">
            <button type="button" onclick="prevStep(3)"
                    class="px-6 py-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold rounded-xl shadow-sm transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back
            </button>
            <button type="submit" id="submit-btn"
                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm shadow-blue-600/20 transition-all flex items-center gap-2 text-base">
                <span class="material-symbols-outlined text-[20px]">send</span> Submit Application
            </button>
        </div>
    </div>

</form>
</main>

<!-- ── Success Overlay ── -->
<div id="success-overlay">
    <div class="bg-white rounded-2xl shadow-2xl p-10 max-w-sm w-full mx-4 text-center">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5">
            <span class="material-symbols-outlined text-green-500 text-5xl">check_circle</span>
        </div>
        <h2 class="text-2xl font-black text-slate-800 mb-2">Application Submitted!</h2>
        <p class="text-slate-500 text-sm mb-1">Your application has been received successfully.</p>
        <p id="success-status-msg" class="text-xs font-semibold px-3 py-1.5 rounded-full inline-block mt-2 mb-6"></p>
        <div class="w-full bg-slate-100 rounded-full h-1.5 mb-2">
            <div id="redirect-bar" class="bg-blue-600 h-1.5 rounded-full transition-all duration-1000" style="width:0%"></div>
        </div>
        <p class="text-xs text-slate-400">Redirecting to dashboard in <span id="countdown">3</span>s...</p>
    </div>
</div>

<script>
// ── State ──
let currentStep = 1;
let eduStatus   = '';
const TOTAL_STEPS = 3;

// ── Education Status ──
document.querySelectorAll('input[name="edu_status_radio"]').forEach(radio => {
    radio.addEventListener('change', function () {
        eduStatus = this.value;
        document.getElementById('hidden-edu-status').value = eduStatus;
        updateEduFields();
    });
});

function updateEduFields() {
    const pursuing  = document.getElementById('pursuing-fields');
    const passedout = document.getElementById('passedout-fields');
    const badge     = document.getElementById('workflow-badge');
    const subtitle  = document.getElementById('academic-subtitle');

    if (eduStatus === 'Pursuing') {
        pursuing.classList.remove('hidden');
        passedout.classList.add('hidden');
        subtitle.textContent = 'Fill in your current academic details';
        badge.className = 'mt-4 p-3 rounded-xl border text-xs font-medium flex items-start gap-2 bg-amber-50 border-amber-200 text-amber-800';
        badge.innerHTML = '<span class="material-symbols-outlined text-amber-500 text-[16px] mt-0.5">pending_actions</span><span>Workflow: <strong>HR Screening → HOD Approval → Onboarding</strong></span>';
        badge.classList.remove('hidden');
        setRequired(['college_name','department','year_of_study','hod_name','hod_email'], true);
        setRequired(['graduation_year','prev_college_name'], false);
    } else if (eduStatus === 'Passed Out') {
        pursuing.classList.add('hidden');
        passedout.classList.remove('hidden');
        subtitle.textContent = 'Fill in your graduation details';
        badge.className = 'mt-4 p-3 rounded-xl border text-xs font-medium flex items-start gap-2 bg-green-50 border-green-200 text-green-800';
        badge.innerHTML = '<span class="material-symbols-outlined text-green-500 text-[16px] mt-0.5">fast_forward</span><span>Workflow: <strong>HR Screening → HR Approved → Onboarding</strong> (HOD step skipped)</span>';
        badge.classList.remove('hidden');
        setRequired(['graduation_year','prev_college_name'], true);
        setRequired(['college_name','department','year_of_study','hod_name','hod_email'], false);
    }
}

function setRequired(ids, req) {
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) req ? el.setAttribute('required','') : el.removeAttribute('required');
    });
}

// ── Step Navigation ──
function showStep(n) {
    document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
    document.getElementById('section-' + n).classList.add('active');
    currentStep = n;
    updateStepper(n);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateStepper(n) {
    for (let i = 1; i <= TOTAL_STEPS; i++) {
        const dot = document.getElementById('dot-' + i);
        if (i < n) {
            dot.className = 'step-dot done w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold';
            dot.innerHTML = '<span class="material-symbols-outlined text-[16px]">check</span>';
        } else if (i === n) {
            dot.className = 'step-dot active w-8 h-8 rounded-full border-2 border-blue-600 bg-blue-600 text-white flex items-center justify-center text-xs font-bold';
            dot.textContent = i;
        } else {
            dot.className = 'step-dot w-8 h-8 rounded-full border-2 border-slate-300 bg-white text-slate-400 flex items-center justify-center text-xs font-bold';
            dot.textContent = i;
        }
    }
    for (let i = 1; i <= TOTAL_STEPS - 1; i++) {
        const line = document.getElementById('line-' + i);
        if (line) line.classList.toggle('done', i < n);
    }
}

function nextStep(from) {
    if (!validateStep(from)) return;
    showStep(from + 1);
}
function prevStep(from) { showStep(from - 1); }

// ── Validation ──
function validateStep(step) {
    let valid = true;

    if (step === 1) {
        valid = vf('full_name', v => v.trim().length > 0, 'Full name is required.') && valid;
        valid = vf('email',     v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), 'Enter a valid email address.') && valid;
        valid = vf('phone',     v => v.trim().length > 0, 'Phone number is required.') && valid;
        valid = vf('skills',    v => v.trim().length > 0, 'Please list your skills.') && valid;
        if (!eduStatus) {
            document.getElementById('edu-status-err').classList.remove('hidden');
            valid = false;
        } else {
            document.getElementById('edu-status-err').classList.add('hidden');
        }
    }

    if (step === 2) {
        if (eduStatus === 'Pursuing') {
            valid = vf('college_name',  v => v.trim().length > 0, 'College name is required.') && valid;
            valid = vf('department',    v => v.trim().length > 0, 'Department is required.') && valid;
            valid = vf('year_of_study', v => v !== '', 'Please select your year of study.') && valid;
            valid = vf('hod_name',      v => v.trim().length > 0, 'HOD name is required.') && valid;
            valid = vf('hod_email',     v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), 'Valid HOD email is required.') && valid;
        } else if (eduStatus === 'Passed Out') {
            valid = vf('graduation_year',   v => v !== '', 'Please select your graduation year.') && valid;
            valid = vf('prev_college_name', v => v.trim().length > 0, 'Previous college name is required.') && valid;
        }
    }

    if (step === 3) {
        // PAN file size/type if provided
        const panInput = document.getElementById('pan_file');
        if (panInput && panInput.files.length > 0) {
            const pf  = panInput.files[0];
            const ext = pf.name.split('.').pop().toLowerCase();
            const panErr = document.getElementById('pan-file-err');
            if (!['pdf','jpg','jpeg','png'].includes(ext) || pf.size > 2 * 1024 * 1024) {
                document.getElementById('pan-upload-zone').style.borderColor = '#ef4444';
                panErr.textContent = ext && !['pdf','jpg','jpeg','png'].includes(ext)
                    ? 'Only PDF, JPG or PNG allowed.' : 'File must be under 2MB.';
                panErr.style.display = 'block';
                valid = false;
            } else {
                document.getElementById('pan-upload-zone').style.borderColor = '';
                panErr.style.display = 'none';
            }
        }

        // Resume
        const resumeInput   = document.getElementById('resume_file');
        const existingInput = document.querySelector('input[name="existing_resume"]');
        const resumeErr     = document.getElementById('resume-err');
        const uploadZone    = document.getElementById('upload-zone');
        if (resumeInput.files.length === 0 && !existingInput) {
            uploadZone.style.borderColor = '#ef4444';
            resumeErr.style.display = 'block';
            valid = false;
        } else {
            uploadZone.style.borderColor = '';
            resumeErr.style.display = 'none';
        }
        if (resumeInput.files.length > 0) {
            const rf  = resumeInput.files[0];
            const ext = rf.name.split('.').pop().toLowerCase();
            if (!['pdf','doc','docx'].includes(ext)) {
                uploadZone.style.borderColor = '#ef4444';
                resumeErr.textContent = 'Only PDF or DOC/DOCX files are allowed.';
                resumeErr.style.display = 'block';
                valid = false;
            } else if (rf.size > 5 * 1024 * 1024) {
                uploadZone.style.borderColor = '#ef4444';
                resumeErr.textContent = 'File size must be under 5MB.';
                resumeErr.style.display = 'block';
                valid = false;
            }
        }

        // Declaration
        const decl    = document.getElementById('declaration');
        const declErr = document.getElementById('decl-err');
        if (!decl.checked) { declErr.classList.remove('hidden'); valid = false; }
        else                { declErr.classList.add('hidden'); }
    }

    return valid;
}

function vf(id, testFn, errMsg) {
    const el    = document.getElementById(id);
    const field = el ? el.closest('.field') : null;
    if (!el || !field) return true;
    const errSpan = field.querySelector('.err-msg');
    if (testFn(el.value)) {
        field.classList.remove('error');
        return true;
    }
    field.classList.add('error');
    if (errSpan) errSpan.textContent = errMsg;
    return false;
}

// Live clear errors
document.querySelectorAll('.field input, .field select, .field textarea').forEach(el => {
    el.addEventListener('input',  () => el.closest('.field').classList.remove('error'));
    el.addEventListener('change', () => el.closest('.field').classList.remove('error'));
});

// ── Resume upload feedback ──
document.getElementById('resume_file').addEventListener('change', function () {
    const zone  = document.getElementById('upload-zone');
    const label = document.getElementById('upload-label');
    if (this.files.length > 0) {
        label.textContent = '✓ ' + this.files[0].name;
        zone.classList.add('has-file');
        document.getElementById('resume-err').style.display = 'none';
        zone.style.borderColor = '';
    }
});

// ── Form submit → success overlay ──
document.getElementById('app-form').addEventListener('submit', function (e) {
    if (!validateStep(3)) { e.preventDefault(); return; }

    const overlay   = document.getElementById('success-overlay');
    const statusMsg = document.getElementById('success-status-msg');
    overlay.classList.add('show');

    if (eduStatus === 'Pursuing') {
        statusMsg.textContent = 'Status: Sent to HR Screening → HOD Approval after HR';
        statusMsg.className = 'text-xs font-semibold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-full inline-block mt-2 mb-6 border border-amber-200';
    } else {
        statusMsg.textContent = 'Status: Sent to HR Screening';
        statusMsg.className = 'text-xs font-semibold text-blue-700 bg-blue-50 px-3 py-1.5 rounded-full inline-block mt-2 mb-6 border border-blue-200';
    }

    const bar = document.getElementById('redirect-bar');
    const cd  = document.getElementById('countdown');
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = '33%';  cd.textContent = '2'; }, 1000);
    setTimeout(() => { bar.style.width = '66%';  cd.textContent = '1'; }, 2000);
    setTimeout(() => { bar.style.width = '100%'; }, 2800);
});

// Init stepper
updateStepper(1);
</script>
</body>
</html>
