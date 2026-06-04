<?php
ob_start();
session_start();
include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $recruiter_name = trim($_POST['recruiter_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $industry_type = trim($_POST['industry_type'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $company_size = trim($_POST['company_size'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $plan_selected = 'Free';

    if (empty($company_name) || empty($recruiter_name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Phone number must be exactly 10 digits.';
    } else {
        $email_escaped = mysqli_real_escape_string($conn, $email);
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email='$email_escaped'");
        if ($check_email && mysqli_num_rows($check_email) > 0) {
            $error = 'This email is already registered.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'company';
            $status = 'Active';

            mysqli_begin_transaction($conn);
            try {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $recruiter_name, $email, $hashed_password, $role, $phone, $status);
                $stmt->execute();
                $user_id = $stmt->insert_id;

                $stmt_profile = $conn->prepare("INSERT INTO company_profiles (user_id, company_name, industry_type, website, company_size, plan_selected) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_profile->bind_param("isssss", $user_id, $company_name, $industry_type, $website, $company_size, $plan_selected);
                $stmt_profile->execute();

                mysqli_commit($conn);
                $success = true;

                // Send email notification if mail helper functions exist
                if (function_exists('sendEmailNotification')) {
                    $reg_subject = "Welcome to IMP - Company Registration Successful";
                    $reg_message = "Dear $recruiter_name,\n\nYour company account for $company_name has been registered successfully on the Internship Management Platform (IMP).\n\nYou can now log in to access verified talent portfolios and manage hiring requests.";
                    sendEmailNotification($email, $reg_subject, $reg_message, [
                        'event' => 'Company Registration',
                        'registered_name' => $recruiter_name,
                        'registered_email' => $email,
                        'assigned_role' => 'Company',
                        'status' => 'Active',
                        'action_url' => 'http://localhost/IMP/login.php',
                        'action_label' => 'Log In to Portal'
                    ]);
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Error occurred during registration: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Company Registration | InternshipHub</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1d4ed8",
                        surface: "#f8f9fa",
                        "surface-container": "#ffffff",
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #f8f9fa; color: #1e293b; }
        .form-input { 
            width: 100%; 
            padding: 0.75rem 1rem; 
            border-radius: 0.75rem; 
            border: 1px solid #e2e8f0; 
            background-color: #f8fafc;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #1d4ed8;
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.05);
        }
        .plan-card {
            border: 2px solid #f1f5f9;
            transition: all 0.3s;
            cursor: pointer;
        }
        .plan-card:hover {
            border-color: #1d4ed8;
            transform: translateY(-4px);
        }
        .plan-card.active {
            border-color: #1d4ed8;
            background-color: #f0f7ff;
            box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.1);
        }
        .hero-gradient {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans">

    <div class="flex-1 flex flex-col lg:flex-row">
        <!-- Left Side: Hero Section -->
        <div class="lg:w-5/12 hero-gradient p-12 lg:p-20 flex flex-col justify-center relative overflow-hidden">
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-8">
                    <a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
                        <svg class="w-10 h-10 text-blue-600 shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
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
                        <span class="text-2xl font-black text-gray-900 tracking-tight">IMP</span>
                    </a>
                </div>

                <h2 class="text-4xl lg:text-5xl font-black text-gray-900 leading-[1.1] mb-6">
                    Hire certified and <br>
                    <span class="text-blue-600">industry-ready</span> <br>
                    internship talent.
                </h2>

                <p class="text-lg text-gray-600 font-medium mb-10 max-w-md">
                    Skip the screening headache. Access verified portfolios, mentor evaluations, and industry-certified graduates in one place.
                </p>

                <ul class="space-y-4 mb-12">
                    <li class="flex items-center gap-3 text-gray-700 font-semibold">
                        <span class="material-symbols-outlined text-blue-600 font-black">check_circle</span>
                        Access verified talent pool
                    </li>
                    <li class="flex items-center gap-3 text-gray-700 font-semibold">
                        <span class="material-symbols-outlined text-blue-600 font-black">check_circle</span>
                        View mentor evaluations
                    </li>
                    <li class="flex items-center gap-3 text-gray-700 font-semibold">
                        <span class="material-symbols-outlined text-blue-600 font-black">check_circle</span>
                        Shortlist top performers
                    </li>
                    <li class="flex items-center gap-3 text-gray-700 font-semibold">
                        <span class="material-symbols-outlined text-blue-600 font-black">check_circle</span>
                        Contact certified candidates
                    </li>
                </ul>
            </div>

            <!-- Background Decorations -->
            <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-blue-200/40 rounded-full blur-3xl"></div>
            <div class="absolute -top-20 -right-20 w-80 h-80 bg-blue-100/40 rounded-full blur-3xl"></div>
        </div>

        <!-- Right Side: Registration Form -->
        <div class="lg:w-7/12 bg-white p-8 lg:p-20 flex flex-col">
            <div class="max-w-2xl w-full mx-auto">
                <div class="mb-10 text-center lg:text-left">
                    <h3 class="text-3xl font-black text-gray-900 tracking-tight">Company Registration</h3>
                    <p class="text-gray-500 font-medium mt-2">Join the elite network of companies hiring certified talent.</p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 text-sm font-semibold rounded-2xl flex items-center gap-3">
                        <span class="material-symbols-outlined">error</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="company_registration.php" class="space-y-6">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Company Name *</label>
                            <input type="text" name="company_name" class="form-input" placeholder="e.g. Nexus Technologies" required value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">HR / Recruiter Name *</label>
                            <input type="text" name="recruiter_name" class="form-input" placeholder="e.g. Sarah Jenkins" required value="<?php echo htmlspecialchars($_POST['recruiter_name'] ?? ''); ?>">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Company Email *</label>
                            <input type="email" name="email" class="form-input" placeholder="name@company.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Phone Number *</label>
                            <input type="tel" name="phone" class="form-input" placeholder="10-digit number" pattern="[0-9]{10}" maxlength="10" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Industry Type</label>
                            <select name="industry_type" class="form-input appearance-none">
                                <option <?php echo (($_POST['industry_type'] ?? '') === 'Software & IT') ? 'selected' : ''; ?>>Software & IT</option>
                                <option <?php echo (($_POST['industry_type'] ?? '') === 'Finance & Fintech') ? 'selected' : ''; ?>>Finance & Fintech</option>
                                <option <?php echo (($_POST['industry_type'] ?? '') === 'Marketing & Advertising') ? 'selected' : ''; ?>>Marketing & Advertising</option>
                                <option <?php echo (($_POST['industry_type'] ?? '') === 'Healthcare') ? 'selected' : ''; ?>>Healthcare</option>
                                <option <?php echo (($_POST['industry_type'] ?? '') === 'Manufacturing') ? 'selected' : ''; ?>>Manufacturing</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Company Website</label>
                            <input type="url" name="website" class="form-input" placeholder="https://nexus.tech" value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>">
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Company Size</label>
                            <select name="company_size" class="form-input appearance-none">
                                <option <?php echo (($_POST['company_size'] ?? '') === '1-10 Employees') ? 'selected' : ''; ?>>1-10 Employees</option>
                                <option <?php echo (($_POST['company_size'] ?? '') === '11-50 Employees') ? 'selected' : ''; ?>>11-50 Employees</option>
                                <option <?php echo (($_POST['company_size'] ?? '') === '51-200 Employees') ? 'selected' : ''; ?>>51-200 Employees</option>
                                <option <?php echo (($_POST['company_size'] ?? '') === '201-500 Employees') ? 'selected' : ''; ?>>201-500 Employees</option>
                                <option <?php echo (($_POST['company_size'] ?? '') === '500+ Employees') ? 'selected' : ''; ?>>500+ Employees</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Password *</label>
                            <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required>
                            <p class="text-[10px] text-gray-500 font-medium">Minimum 8 characters.</p>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-gray-900 uppercase tracking-widest">Confirm Password *</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-4">
                        <input type="checkbox" required class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <p class="text-xs text-gray-600 font-medium">I agree to the <a href="#" class="text-blue-600 hover:underline">Terms & Conditions</a> and <a href="#" class="text-blue-600 hover:underline">Privacy Policy</a>.</p>
                    </div>

                    <div class="pt-6 border-t border-gray-100 mt-6 flex justify-end">
                        <button type="submit" class="w-full md:w-auto px-10 py-4 bg-gray-900 text-white rounded-2xl font-black text-sm hover:bg-black transition-all shadow-xl hover:scale-[1.02] active:scale-95">
                            Create Company Account
                        </button>
                    </div>

                    <div class="text-center pt-8">
                        <p class="text-sm text-gray-500 font-medium">Already have an account? <a href="login.php" class="text-blue-600 font-bold hover:underline">Login</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Success Overlay Modal -->
    <div id="company-success-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-500 scale-95">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl border border-gray-100 flex flex-col items-center text-center">
            <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-4 shadow-inner animate-bounce">
                <span class="material-symbols-outlined text-3xl">corporate_fare</span>
            </div>
            <h3 class="text-2xl font-black text-gray-900 tracking-tight mb-2">Account created successfully!</h3>
            <p class="text-sm text-gray-600 mb-6">Welcome to IMP! Your company account has been successfully created. Please log in to choose your subscription plan.</p>
            
            <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden mb-4">
                <div class="bg-blue-600 h-full w-full rounded-full animate-[shrink_1.5s_linear_forwards]"></div>
            </div>
            <p class="text-xs text-gray-400 font-medium animate-pulse">Redirecting to login page...</p>
        </div>
    </div>

    <style>
    @keyframes shrink {
        from { width: 100%; }
        to { width: 0%; }
    }
    </style>

    <script>
        <?php if ($success): ?>
            // Show success modal
            const modal = document.getElementById('company-success-modal');
            modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
            modal.classList.add('opacity-100', 'scale-100');
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.2s ease';
                document.body.style.opacity = '0';
                setTimeout(() => {
                    window.location.href = 'login.php?success=' + encodeURIComponent('Company registered! Log in to continue.');
                }, 200);
            }, 1600);
        <?php endif; ?>
    </script>

</body>
</html>
