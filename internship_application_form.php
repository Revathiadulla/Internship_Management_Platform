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

// ── Check duplicate applications ──
$dup_sql = "SELECT id FROM internship_applications WHERE user_id = '$user_id' AND internship_id = '$internship_id' AND (internship_id > 0 OR internship_name = '" . mysqli_real_escape_string($conn, $internship_name) . "') LIMIT 1";
$dup_result = mysqli_query($conn, $dup_sql);
$has_applied = mysqli_num_rows($dup_result) > 0;

// ── Check profile completeness ──
$is_profile_complete = true;
$missing_fields = [];

if (empty($profile['full_name'])) { $is_profile_complete = false; $missing_fields[] = 'Full Name'; }
if (empty($profile['email'])) { $is_profile_complete = false; $missing_fields[] = 'Email'; }
if (empty($profile['phone'])) { $is_profile_complete = false; $missing_fields[] = 'Phone Number'; }
if (empty($profile['college_name'])) { $is_profile_complete = false; $missing_fields[] = 'College Name'; }
if (empty($profile['course'])) { $is_profile_complete = false; $missing_fields[] = 'Course/Degree'; }
if (empty($profile['year_of_study'])) { $is_profile_complete = false; $missing_fields[] = 'Education Status / Year of Study'; }
if (empty($profile['skills'])) { $is_profile_complete = false; $missing_fields[] = 'Skills'; }
if (empty($profile['resume_file'])) { $is_profile_complete = false; $missing_fields[] = 'Resume'; }
if (empty($profile['aadhaar_number']) || empty($profile['aadhaar_file'])) { $is_profile_complete = false; $missing_fields[] = 'Aadhaar Verification'; }
if (empty($profile['pan_number']) || empty($profile['pan_file'])) { $is_profile_complete = false; $missing_fields[] = 'PAN Verification'; }

// Parse year_of_study for education status
$education_status = '';
$year_select = '';
$expected_grad = '';
$graduation_year = '';
if (!empty($profile['year_of_study'])) {
    if (strpos($profile['year_of_study'], 'Passed Out') !== false) {
        $education_status = 'Passed Out';
        preg_match('/\((\d{4})\)/', $profile['year_of_study'], $matches);
        $graduation_year = isset($matches[1]) ? $matches[1] : '';
    } else {
        $education_status = 'Pursuing';
        if (preg_match('/^(.+?)\s*\(Graduating\s+(\d{4})\)/', $profile['year_of_study'], $matches)) {
            $year_select = $matches[1];
            $expected_grad = $matches[2];
        } else {
            $year_select = $profile['year_of_study'];
        }
        if (empty($profile['hod_name'])) { $is_profile_complete = false; $missing_fields[] = 'HOD Name'; }
        if (empty($profile['hod_phone'])) { $is_profile_complete = false; $missing_fields[] = 'HOD Phone'; }
        if (empty($profile['hod_email'])) { $is_profile_complete = false; $missing_fields[] = 'HOD Email'; }
    }
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
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        
        /* Read-only info pill */
        .info-pill {
            display: inline-flex; align-items: center; gap: 6px;
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 10px; padding: 6px 12px;
            font-size: 12px; font-weight: 600; color: #475569;
        }
        .info-pill .material-symbols-outlined { font-size: 15px; color: #2563eb; }
    </style>
</head>
<body class="min-h-screen text-slate-800 antialiased flex flex-col">

<!-- ── Navbar ── -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-3xl mx-auto px-6 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-lg shadow-sm">I</div>
            <span class="text-lg font-bold text-slate-800 tracking-tight">IMP</span>
            <span class="hidden sm:inline text-slate-300 mx-2">|</span>
            <span class="hidden sm:inline text-xs font-semibold text-slate-500 uppercase tracking-wider">Application Preview</span>
        </div>
        <a href="student_browse_internships.php" class="flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-red-500 transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Internships
        </a>
    </div>
</header>

<!-- ── Internship Info Banner ── -->
<div class="bg-gradient-to-br from-blue-700 via-blue-600 to-indigo-600 text-white">
    <div class="max-w-3xl mx-auto px-6 py-7">
        <p class="text-blue-200 text-[10px] font-bold uppercase tracking-widest mb-3 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[14px]">bolt</span> Easy Apply Workflow
        </p>
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-white/15 rounded-2xl flex items-center justify-center flex-shrink-0 border border-white/20">
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Main Content Area ── -->
<main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 flex-grow w-full">

    <?php if ($has_applied): ?>
        <!-- Duplicate Application State -->
        <div class="bg-white rounded-2xl border border-amber-100 shadow-xl p-8 text-center max-w-md mx-auto my-12">
            <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-5 border border-amber-100">
                <span class="material-symbols-outlined text-4xl">info</span>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">Already Applied</h2>
            <p class="text-slate-500 text-sm mb-6 leading-relaxed">You have already applied for this internship.</p>
            <a href="student_browse_internships.php" class="px-6 py-2.5 bg-slate-800 text-white text-sm font-semibold rounded-xl hover:bg-slate-900 transition-colors shadow-sm inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Browse Other Internships
            </a>
        </div>

    <?php elseif (!$is_profile_complete): ?>
        <!-- Profile Incomplete State -->
        <div class="bg-white rounded-2xl border border-red-100 shadow-xl p-8 max-w-lg mx-auto my-8">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-5 border border-red-100">
                <span class="material-symbols-outlined text-4xl">warning</span>
            </div>
            <h2 class="text-xl font-bold text-slate-800 text-center mb-2">Profile Incomplete</h2>
            <p class="text-slate-500 text-sm text-center mb-6 leading-relaxed">Please complete your profile before applying.</p>
            
            <div class="bg-slate-50 rounded-xl p-4 mb-6 border border-slate-200">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2.5">Missing Required Fields:</p>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($missing_fields as $f): ?>
                    <span class="inline-flex items-center gap-1 text-xs text-red-600 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        <?php echo $f; ?>
                    </span>
                    <?php endvar_dump; // wait, let's write it cleanly without var_dump ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-center gap-3">
                <a href="student_profile_form.php" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">edit</span> Complete Profile
                </a>
                <a href="student_browse_internships.php" class="px-6 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition-all">
                    Cancel
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Profile Preview & Confirm Apply State -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <!-- Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
                <div class="w-9 h-9 bg-blue-100 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 text-[20px]">assignment_ind</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800">Profile Preview Before Apply</h2>
                    <p class="text-xs text-slate-500">Please review your application details below before submitting.</p>
                </div>
            </div>
            
            <!-- Details Grid -->
            <div class="p-6 space-y-6">
                <!-- Personal details -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Personal & Contact Info</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Full Name</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($profile['full_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Email Address</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5 truncate"><?php echo htmlspecialchars($profile['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Phone Number</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($profile['phone']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Academic details -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Academic & Education</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">College Name</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($profile['college_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Course / Department</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($profile['course']); ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Education Status</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($education_status); ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">Year / Graduation Year</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5">
                                <?php 
                                if ($education_status === 'Passed Out') {
                                    echo "Passed Out (" . htmlspecialchars($graduation_year) . ")";
                                } else {
                                    echo htmlspecialchars($year_select) . " (Graduating " . htmlspecialchars($expected_grad) . ")";
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- HOD details if Pursuing -->
                <?php if ($education_status === 'Pursuing'): ?>
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">HOD / Approver Details</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">HOD Name</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($profile['hod_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">HOD Phone Number</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo htmlspecialchars($profile['hod_phone']); ?></p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold text-slate-400 uppercase">HOD Email</p>
                            <p class="text-sm font-bold text-slate-700 mt-0.5 truncate"><?php echo htmlspecialchars($profile['hod_email']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Skills -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">Key Skills</h3>
                    <div class="flex flex-wrap gap-2 bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <?php 
                        $skills_arr = explode(',', $profile['skills']);
                        foreach ($skills_arr as $s): $s = trim($s); if ($s): ?>
                            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-100 rounded-lg text-xs font-semibold">
                                <?php echo htmlspecialchars($s); ?>
                            </span>
                        <?php endif; endforeach; ?>
                    </div>
                </div>

                <!-- Documents Verification -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Documents & Identity Verification</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Resume Status -->
                        <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                            <span class="material-symbols-outlined text-red-500 text-2xl">picture_as_pdf</span>
                            <div class="min-w-0 flex-grow">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Resume File</p>
                                <a href="<?php echo get_resume_view_link($profile); ?>" target="_blank" data-resume-exists="<?php echo check_resume_exists($profile) ? 'true' : 'false'; ?>" class="text-xs font-semibold text-blue-600 hover:underline truncate block">
                                    View Resume
                                </a>
                            </div>
                            <span class="text-green-600 font-bold text-xs">✓ Uploaded</span>
                        </div>

                        <!-- Aadhaar Status -->
                        <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                            <span class="material-symbols-outlined text-green-600 text-2xl">badge</span>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Aadhaar Card</p>
                                <p class="text-xs font-semibold text-slate-700">
                                    <?php 
                                    $a_num = preg_replace('/\s+/', '', $profile['aadhaar_number']);
                                    echo "•••• •••• " . substr($a_num, -4);
                                    ?>
                                </p>
                            </div>
                            <span class="text-green-600 font-bold text-xs ml-auto">✓ Verified</span>
                        </div>

                        <!-- PAN Status -->
                        <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                            <span class="material-symbols-outlined text-indigo-600 text-2xl">credit_card</span>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">PAN Card</p>
                                <p class="text-xs font-semibold text-slate-700">
                                    <?php 
                                    $p_num = strtoupper(trim($profile['pan_number']));
                                    echo substr($p_num, 0, 5) . "****" . substr($p_num, -1);
                                    ?>
                                </p>
                            </div>
                            <span class="text-green-600 font-bold text-xs ml-auto">✓ Verified</span>
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100">

                <!-- Declaration / Agreement Notice -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" id="declaration" name="declaration" checked disabled
                               class="mt-0.5 w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 flex-shrink-0 opacity-70">
                        <span class="text-xs text-slate-500 leading-relaxed">
                            I verify that my profile details are accurate and complete. Submitting this application will automatically create an application record on IMP using this profile data.
                        </span>
                    </label>
                </div>
            </div>

            <!-- Submit Form -->
            <form id="app-form" action="internship_application_submit.php" method="POST">
                <!-- Pass internship metadata -->
                <input type="hidden" name="internship_id"    value="<?php echo (int)$internship['id']; ?>">
                <input type="hidden" name="internship_name"  value="<?php echo htmlspecialchars($internship_name); ?>">
                <input type="hidden" name="profile_id"       value="<?php echo (int)$profile['id']; ?>">
                <input type="hidden" name="education_status" value="<?php echo htmlspecialchars($education_status); ?>">
                
                <!-- Common personal fields -->
                <input type="hidden" name="full_name"        value="<?php echo htmlspecialchars($profile['full_name']); ?>">
                <input type="hidden" name="email"            value="<?php echo htmlspecialchars($profile['email']); ?>">
                <input type="hidden" name="phone"            value="<?php echo htmlspecialchars($profile['phone']); ?>">
                <input type="hidden" name="skills"           value="<?php echo htmlspecialchars($profile['skills']); ?>">
                <input type="hidden" name="aadhaar_number"    value="<?php echo htmlspecialchars($profile['aadhaar_number']); ?>">
                <input type="hidden" name="pan_number"        value="<?php echo htmlspecialchars($profile['pan_number']); ?>">
                <input type="hidden" name="existing_resume"   value="<?php echo htmlspecialchars($profile['resume_file']); ?>">
                <input type="hidden" name="existing_pan"      value="<?php echo htmlspecialchars($profile['pan_file']); ?>">

                <!-- Academic details -->
                <input type="hidden" name="college_name"      value="<?php echo htmlspecialchars($profile['college_name']); ?>">
                <input type="hidden" name="department"        value="<?php echo htmlspecialchars($profile['course']); ?>">
                <input type="hidden" name="year_of_study"     value="<?php echo htmlspecialchars($profile['year_of_study']); ?>">

                <?php if ($education_status === 'Pursuing'): ?>
                    <input type="hidden" name="hod_name"      value="<?php echo htmlspecialchars($profile['hod_name']); ?>">
                    <input type="hidden" name="hod_phone"     value="<?php echo htmlspecialchars($profile['hod_phone']); ?>">
                    <input type="hidden" name="hod_email"     value="<?php echo htmlspecialchars($profile['hod_email']); ?>">
                <?php else: ?>
                    <input type="hidden" name="graduation_year"   value="<?php echo htmlspecialchars($graduation_year); ?>">
                    <input type="hidden" name="prev_college_name" value="<?php echo htmlspecialchars($profile['college_name']); ?>">
                <?php endif; ?>

                <!-- Actions -->
                <div class="px-6 py-5 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-4">
                    <a href="student_profile_form.php" class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">edit</span> Edit Profile
                    </a>
                    
                    <div class="flex items-center gap-3">
                        <a href="student_browse_internships.php" class="text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" id="submit-btn" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-sm shadow-blue-600/20 transition-all flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">bolt</span> Confirm & Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

</main>

<footer class="bg-white border-t border-slate-200 mt-12 py-6 text-center text-xs text-slate-400">
    <div class="max-w-3xl mx-auto px-6">
        IMP Onboarding Platform © 2026. All rights reserved.
    </div>
</footer>

<?php print_resume_not_found_js(); ?>
</body>
</html>
