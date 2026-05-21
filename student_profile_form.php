<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : "";
$session_email = isset($_SESSION['email']) ? $_SESSION['email'] : "";

// Fetch existing profile data
$profile_sql = "SELECT * FROM student_profiles WHERE user_id = '$user_id' LIMIT 1";
$profile_result = mysqli_query($conn, $profile_sql);
$profile = mysqli_fetch_assoc($profile_result);

// If profile exists, use those values; otherwise use empty defaults
$first_name = ($profile && isset($profile['first_name'])) ? $profile['first_name'] : '';
$last_name = ($profile && isset($profile['last_name'])) ? $profile['last_name'] : '';
$phone = ($profile && isset($profile['phone'])) ? $profile['phone'] : '';
$dob = ($profile && isset($profile['dob'])) ? $profile['dob'] : '';
$gender = ($profile && isset($profile['gender'])) ? $profile['gender'] : '';
$college_name = ($profile && isset($profile['college_name'])) ? $profile['college_name'] : '';
$course = ($profile && isset($profile['course'])) ? $profile['course'] : '';
$skills = ($profile && isset($profile['skills'])) ? $profile['skills'] : '';
$aadhaar_number = ($profile && isset($profile['aadhaar_number'])) ? $profile['aadhaar_number'] : '';
$pan_number = ($profile && isset($profile['pan_number'])) ? $profile['pan_number'] : '';
$resume_file = ($profile && isset($profile['resume_file'])) ? $profile['resume_file'] : '';
$aadhaar_file = ($profile && isset($profile['aadhaar_file'])) ? $profile['aadhaar_file'] : '';
$pan_file = ($profile && isset($profile['pan_file'])) ? $profile['pan_file'] : '';

// Parse year_of_study to determine education status and fields
$year_of_study = ($profile && isset($profile['year_of_study'])) ? $profile['year_of_study'] : '';
$education_status = '';
$year_select = '';
$expected_grad = '';
$graduation_year = '';

if ($year_of_study) {
    if (strpos($year_of_study, 'Passed Out') !== false) {
        $education_status = 'Passed Out';
        // Extract year from "Passed Out (2023)"
        preg_match('/\((\d{4})\)/', $year_of_study, $matches);
        $graduation_year = isset($matches[1]) ? $matches[1] : '';
    } else {
        $education_status = 'Pursuing';
        // Extract year and graduation from "3rd Year (Graduating 2026)"
        if (preg_match('/^(.+?)\s*\(Graduating\s+(\d{4})\)/', $year_of_study, $matches)) {
            $year_select = $matches[1];
            $expected_grad = $matches[2];
        }
    }
}

// If full_name is empty but profile exists, use profile full_name
if (empty($full_name) && $profile && isset($profile['full_name'])) {
    $full_name = $profile['full_name'];
}
if (empty($session_email) && $profile && isset($profile['email'])) {
    $session_email = $profile['email'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding | Complete Your Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@400,0,0,24" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

        /* Education status radio cards */
        .edu-status-card.selected {
            border-color: #2563eb;
            background-color: #eff6ff;
        }
        .edu-status-card.selected .edu-radio-dot {
            border-color: #2563eb;
            background-color: #2563eb;
            box-shadow: inset 0 0 0 3px #fff;
        }
        .edu-status-card:not(.selected) .edu-radio-dot {
            border-color: #94a3b8;
            background-color: transparent;
        }

        /* Smooth reveal for conditional fields */
        #pursuing-fields.visible,
        #passedout-fields.visible {
            max-height: 300px !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            margin-top: 0 !important;
        }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Minimal Navbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-8 h-8 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="32" height="32" rx="8" fill="currentColor"/>
                    <circle cx="16" cy="16" r="3" fill="white"/>
                    <line x1="16" y1="13" x2="16" y2="9" stroke="white" stroke-width="1.5"/>
                    <circle cx="16" cy="8" r="1.5" fill="white"/>
                    <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                    <circle cx="23.5" cy="13.5" r="1.5" fill="white"/>
                    <line x1="17.8" y1="18.4" x2="20.0" y2="21.5" stroke="white" stroke-width="1.5"/>
                    <circle cx="20.7" cy="22.5" r="1.5" fill="white"/>
                    <line x1="14.2" y1="18.4" x2="12.0" y2="21.5" stroke="white" stroke-width="1.5"/>
                    <circle cx="11.3" cy="22.5" r="1.5" fill="white"/>
                    <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="white" stroke-width="1.5"/>
                    <circle cx="8.5" cy="13.5" r="1.5" fill="white"/>
                </svg>
                <span class="text-xl font-bold text-blue-600 tracking-tight">IMP</span>
            </div>
            <a href="login.php" class="text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors">Save & Exit</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6">
        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
            
            <!-- Progress Section -->
            <div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Complete Your Profile</h1>
                <p class="mt-2 text-sm text-slate-500">Complete your profile to start applying for internships and connecting with employers.</p>
            </div>

            <!-- Form -->
            <form action="profile_submit.php" method="POST" enctype="multipart/form-data" class="px-8 py-8 space-y-10">
                
                <!-- 1. Personal Information -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">person</span> Personal Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. Alex" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. Rivera" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name (Auto-filled)</label>
                            <input type="text" name="full_name" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-500 outline-none" value="<?php echo htmlspecialchars($full_name); ?>" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email Address (Auto-filled)</label>
                            <input type="email" name="email" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-500 outline-none" value="<?php echo htmlspecialchars($session_email); ?>" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="1234567890" pattern="\d{10}" minlength="10" maxlength="10" title="Please enter exactly 10 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date of Birth</label>
                            <input type="date" name="dob" value="<?php echo htmlspecialchars($dob); ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 bg-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                            <select name="gender" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 bg-white" required>
                                <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select gender</option>
                                <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                                <option value="Prefer not to say" <?php echo ($gender === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                </section>

                <hr class="border-slate-200">

                <!-- 2. Academic Information -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">school</span> Academic Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">College / University Name</label>
                            <input type="text" name="college_name" value="<?php echo htmlspecialchars($college_name); ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. Stanford University" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Course / Degree</label>
                            <input type="text" name="course" value="<?php echo htmlspecialchars($course); ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. B.S. Computer Science" required>
                        </div>

                        <!-- Education Status -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Education Status <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label id="card-pursuing" class="edu-status-card flex items-center gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all duration-200 hover:border-blue-400 hover:bg-blue-50/40 <?php echo ($education_status === 'Pursuing') ? 'selected' : ''; ?>">
                                    <input type="radio" name="education_status" value="Pursuing" class="hidden" <?php echo ($education_status === 'Pursuing') ? 'checked' : ''; ?> required>
                                    <span class="edu-radio-dot w-4 h-4 rounded-full border-2 border-slate-300 flex-shrink-0 flex items-center justify-center transition-all duration-200"></span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">Currently Pursuing</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Still enrolled in college</p>
                                    </div>
                                </label>
                                <label id="card-passedout" class="edu-status-card flex items-center gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all duration-200 hover:border-blue-400 hover:bg-blue-50/40 <?php echo ($education_status === 'Passed Out') ? 'selected' : ''; ?>">
                                    <input type="radio" name="education_status" value="Passed Out" class="hidden" <?php echo ($education_status === 'Passed Out') ? 'checked' : ''; ?>>
                                    <span class="edu-radio-dot w-4 h-4 rounded-full border-2 border-slate-300 flex-shrink-0 flex items-center justify-center transition-all duration-200"></span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">Passed Out</p>
                                        <p class="text-xs text-slate-500 mt-0.5">Completed graduation</p>
                                    </div>
                                </label>
                            </div>
                            <p id="edu-status-err" class="hidden text-xs text-red-500 mt-1.5">Please select your education status.</p>
                        </div>

                        <!-- Hidden field that carries the final value to PHP -->
                        <input type="hidden" name="year_of_study" id="year_of_study_hidden" value="">

                        <!-- PURSUING fields -->
                        <div id="pursuing-fields" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 overflow-hidden <?php echo ($education_status === 'Pursuing') ? 'visible' : ''; ?>"
                             style="max-height:<?php echo ($education_status === 'Pursuing') ? '300px' : '0'; ?>; opacity:<?php echo ($education_status === 'Pursuing') ? '1' : '0'; ?>; pointer-events:<?php echo ($education_status === 'Pursuing') ? 'auto' : 'none'; ?>; transition: max-height .4s ease, opacity .35s ease, margin .35s ease; margin-top:0;">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Year of Study <span class="text-red-500">*</span></label>
                                <select id="year_of_study_select" name="_year_of_study_pursuing"
                                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 bg-white">
                                    <option value="" disabled <?php echo empty($year_select) ? 'selected' : ''; ?>>Select year</option>
                                    <option value="1st Year" <?php echo ($year_select === '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo ($year_select === '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo ($year_select === '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="Final Year" <?php echo ($year_select === 'Final Year') ? 'selected' : ''; ?>>Final Year</option>
                                </select>
                                <p id="year-err" class="hidden text-xs text-red-500 mt-1">Please select your year of study.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Expected Graduation Year <span class="text-red-500">*</span></label>
                                <select id="expected_grad_year" name="_expected_grad_year"
                                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 bg-white">
                                    <option value="" disabled <?php echo empty($expected_grad) ? 'selected' : ''; ?>>Select year</option>
                                    <?php for ($y = date('Y'); $y <= date('Y') + 6; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($expected_grad == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <p id="exp-grad-err" class="hidden text-xs text-red-500 mt-1">Please select your expected graduation year.</p>
                            </div>
                        </div>

                        <!-- PASSED OUT fields -->
                        <div id="passedout-fields" class="md:col-span-2 overflow-hidden <?php echo ($education_status === 'Passed Out') ? 'visible' : ''; ?>"
                             style="max-height:<?php echo ($education_status === 'Passed Out') ? '300px' : '0'; ?>; opacity:<?php echo ($education_status === 'Passed Out') ? '1' : '0'; ?>; pointer-events:<?php echo ($education_status === 'Passed Out') ? 'auto' : 'none'; ?>; transition: max-height .4s ease, opacity .35s ease, margin .35s ease; margin-top:0;">
                            <div class="max-w-xs">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Graduation Year <span class="text-red-500">*</span></label>
                                <select id="graduation_year_select" name="_graduation_year"
                                        class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 bg-white">
                                    <option value="" disabled <?php echo empty($graduation_year) ? 'selected' : ''; ?>>Select year</option>
                                    <?php for ($y = date('Y'); $y >= 2010; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($graduation_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <p id="grad-year-err" class="hidden text-xs text-red-500 mt-1">Please select your graduation year.</p>
                            </div>
                        </div>

                    </div>
                </section>

                <hr class="border-slate-200">

                <!-- 3. Skills & Resume -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">work</span> Skills & Resume
                    </h2>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Key Skills <span class="text-slate-400 font-normal">(Comma separated)</span></label>
                            <textarea name="skills" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 resize-none" placeholder="e.g. Python, UI/UX Design, Data Analysis, JavaScript" required><?php echo htmlspecialchars($skills); ?></textarea>
                        </div>
                        
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">
                                Resume Upload <?php echo empty($resume_file) ? '' : '<span class="text-slate-400">(Optional - Update)</span>'; ?>
                            </label>
                            <div class="border-2 border-dashed border-blue-300 rounded-2xl p-10 text-center bg-white hover:border-blue-500 transition-all relative">
                                <input
                                    type="file"
                                    name="resume"
                                    id="resume"
                                    accept=".pdf,.doc,.docx"
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                    <?php echo empty($resume_file) ? 'required' : ''; ?>>
                                <label for="resume" class="cursor-pointer flex flex-col items-center" id="resume-label">
                                    <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="h-8 w-8 text-blue-600"
                                             fill="none"
                                             viewBox="0 0 24 24"
                                             stroke="currentColor">
                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  stroke-width="2"
                                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 0115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                    </div>
                                    <p class="text-gray-700 font-medium" id="resume-text">
                                        <?php echo empty($resume_file) ? 'Click to upload or drag and drop' : 'Click to change file'; ?>
                                    </p>
                                    <p class="text-sm text-gray-400 mt-1 <?php echo empty($resume_file) ? '' : 'hidden'; ?>" id="resume-hint">
                                        PDF, DOCX up to 5MB
                                    </p>
                                </label>
                                <div id="resume-preview" class="<?php echo empty($resume_file) ? 'hidden' : ''; ?> mt-4 pt-4 border-t border-slate-100">
                                    <p class="text-sm text-slate-500 mb-1">Selected file:</p>
                                    <a href="<?php echo empty($resume_file) ? '#' : 'uploads/' . htmlspecialchars($resume_file); ?>" id="resume-link" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 hover:underline font-medium relative z-10" onclick="event.stopPropagation();">
                                        <span class="material-symbols-outlined text-[18px]">description</span>
                                        <span id="resume-filename" class="truncate max-w-[200px]"><?php echo empty($resume_file) ? 'filename.pdf' : basename($resume_file); ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <hr class="border-slate-200">

                <!-- 4. Identity Verification -->
                <section>
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600">verified_user</span> Identity Verification
                        </h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Aadhaar Number <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="text" name="aadhaar_number" value="<?php echo htmlspecialchars($aadhaar_number); ?>" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 tracking-widest font-mono placeholder:tracking-normal" placeholder="123456789012" pattern="\d{12}" minlength="12" maxlength="12" title="Please enter exactly 12 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">badge</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Upload Aadhaar Document <?php echo empty($aadhaar_file) ? '<span class="text-red-500">*</span>' : '<span class="text-slate-400">(Optional - Update)</span>'; ?></label>
                                <input type="file" name="aadhaar_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" <?php echo empty($aadhaar_file) ? 'required' : ''; ?>>
                                <?php if (!empty($aadhaar_file)): ?>
                                <p class="text-xs text-green-600 mt-1 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                    Current file: <?php echo basename($aadhaar_file); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-1.5 mt-2 text-slate-500">
                                <span class="material-symbols-outlined text-[14px]">lock</span>
                                <p class="text-xs">Securely stored for verification.</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">PAN Number <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="text" name="pan_number" value="<?php echo htmlspecialchars($pan_number); ?>" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 tracking-widest font-mono placeholder:tracking-normal uppercase" placeholder="ABCDE1234F" pattern="[A-Z]{5}\d{4}[A-Z]{1}" minlength="10" maxlength="10" title="Please enter a valid PAN number (e.g., ABCDE1234F)" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');" required>
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">credit_card</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Upload PAN Card <?php echo empty($pan_file) ? '<span class="text-red-500">*</span>' : '<span class="text-slate-400">(Optional - Update)</span>'; ?></label>
                                <input type="file" name="pan_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" <?php echo empty($pan_file) ? 'required' : ''; ?>>
                                <?php if (!empty($pan_file)): ?>
                                <p class="text-xs text-green-600 mt-1 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                    Current file: <?php echo basename($pan_file); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-1.5 mt-2 text-slate-500">
                                <span class="material-symbols-outlined text-[14px]">lock</span>
                                <p class="text-xs">Required for financial processing.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Actions -->
                <div class="pt-6 border-t border-slate-200 flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
                    <button type="button" class="w-full sm:w-auto px-6 py-2.5 border border-slate-300 text-slate-700 font-medium rounded-lg hover:bg-slate-50 transition-colors">
                        Back
                    </button>
                    <button type="submit" class="w-full sm:w-auto px-8 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm shadow-blue-600/20 hover:shadow-md transition-all">
                        Save & Continue
                    </button>
                </div>
                
                <p class="text-center text-xs text-slate-400 mt-4 max-w-md mx-auto">
                    Your information is securely stored and used only for internship verification purposes. 
                    By continuing, you agree to our <a href="#" class="text-blue-600 hover:underline">Terms of Service</a>.
                </p>

            </form>
        </div>
    </main>

<script>
    // ── Resume upload preview ──
    document.getElementById('resume').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            document.getElementById('resume-text').textContent = 'Click to change file';
            document.getElementById('resume-hint').classList.add('hidden');
            document.getElementById('resume-preview').classList.remove('hidden');
            document.getElementById('resume-filename').textContent = file.name;
            document.getElementById('resume-link').href = URL.createObjectURL(file);
        } else {
            document.getElementById('resume-text').textContent = 'Click to upload or drag and drop';
            document.getElementById('resume-hint').classList.remove('hidden');
            document.getElementById('resume-preview').classList.add('hidden');
            document.getElementById('resume-link').href = '#';
        }
    });

    // ── Education Status conditional logic ──
    const cards         = document.querySelectorAll('.edu-status-card');
    const radios        = document.querySelectorAll('input[name="education_status"]');
    const pursuingBlock = document.getElementById('pursuing-fields');
    const passedBlock   = document.getElementById('passedout-fields');
    const hiddenField   = document.getElementById('year_of_study_hidden');
    const eduErr        = document.getElementById('edu-status-err');

    function showPursuing() {
        pursuingBlock.classList.add('visible');
        passedBlock.classList.remove('visible');
    }
    function showPassedOut() {
        passedBlock.classList.add('visible');
        pursuingBlock.classList.remove('visible');
    }
    function hideAll() {
        pursuingBlock.classList.remove('visible');
        passedBlock.classList.remove('visible');
    }

    cards.forEach(card => {
        card.addEventListener('click', function () {
            // Update radio
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;

            // Update card styles
            cards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');

            // Hide error
            eduErr.classList.add('hidden');

            // Show/hide conditional fields
            if (radio.value === 'Pursuing') {
                showPursuing();
            } else {
                showPassedOut();
            }
        });
    });

    // ── Build year_of_study value before submit ──
    document.querySelector('form').addEventListener('submit', function (e) {
        let valid = true;

        // Education status required
        const checkedRadio = document.querySelector('input[name="education_status"]:checked');
        if (!checkedRadio) {
            eduErr.classList.remove('hidden');
            valid = false;
        } else {
            eduErr.classList.add('hidden');

            if (checkedRadio.value === 'Pursuing') {
                const yearSel    = document.getElementById('year_of_study_select');
                const expGradSel = document.getElementById('expected_grad_year');
                const yearErr    = document.getElementById('year-err');
                const expErr     = document.getElementById('exp-grad-err');

                if (!yearSel.value) {
                    yearErr.classList.remove('hidden');
                    yearSel.classList.add('border-red-400');
                    valid = false;
                } else {
                    yearErr.classList.add('hidden');
                    yearSel.classList.remove('border-red-400');
                }

                if (!expGradSel.value) {
                    expErr.classList.remove('hidden');
                    expGradSel.classList.add('border-red-400');
                    valid = false;
                } else {
                    expErr.classList.add('hidden');
                    expGradSel.classList.remove('border-red-400');
                }

                if (yearSel.value && expGradSel.value) {
                    // Store as "3rd Year (Graduating 2026)" — readable single value
                    hiddenField.value = yearSel.value + ' (Graduating ' + expGradSel.value + ')';
                }

            } else {
                // Passed Out
                const gradSel  = document.getElementById('graduation_year_select');
                const gradErr  = document.getElementById('grad-year-err');

                if (!gradSel.value) {
                    gradErr.classList.remove('hidden');
                    gradSel.classList.add('border-red-400');
                    valid = false;
                } else {
                    gradErr.classList.add('hidden');
                    gradSel.classList.remove('border-red-400');
                    hiddenField.value = 'Passed Out (' + gradSel.value + ')';
                }
            }
        }

        if (!valid) {
            e.preventDefault();
            // Scroll to academic section
            document.querySelector('input[name="education_status"]')
                    .closest('section').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // Live clear red border on change
    ['year_of_study_select','expected_grad_year','graduation_year_select'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', () => el.classList.remove('border-red-400'));
    });
</script>
</body>
</html>