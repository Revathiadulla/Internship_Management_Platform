<?php
ob_start();
session_start();
include "db.php";
include_once __DIR__ . "/includes/auth.php";
include_once __DIR__ . "/includes/mail_helper.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Ensure phone column exists dynamically in users table
    $chk_phone = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'phone'");
    if (!$chk_phone || mysqli_num_rows($chk_phone) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(15) DEFAULT NULL AFTER role");
    }

    $full_name = isset($_POST['full_name']) ? $_POST['full_name'] : (isset($_POST['fullname']) ? $_POST['fullname'] : '');
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    if (empty($phone)) {
        $params = http_build_query(['error' => 'Phone number is required.', 'full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'role' => $role]);
        header("Location: registration_page.php?" . $params);
        exit();
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $params = http_build_query(['error' => 'Phone number must be exactly 10 digits.', 'full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'role' => $role]);
        header("Location: registration_page.php?" . $params);
        exit();
    }

    if ($password !== $confirm_password) {
        $params = http_build_query(['error' => 'Passwords do not match. Please try again.', 'full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'role' => $role]);
        header("Location: registration_page.php?" . $params);
        exit();
    }

    $email_escaped = mysqli_real_escape_string($conn, $email);
    $checkEmail = "SELECT * FROM users WHERE email='$email_escaped'";
    $result = mysqli_query($conn, $checkEmail);

    if (mysqli_num_rows($result) > 0) {
        $params = http_build_query(['error' => 'This email is already registered. Please log in instead.', 'full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'role' => $role]);
        header("Location: registration_page.php?" . $params);
        exit();
    }

    $phone_escaped = mysqli_real_escape_string($conn, $phone);
    $checkPhone = "SELECT * FROM users WHERE phone='$phone_escaped'";
    $resultPhone = mysqli_query($conn, $checkPhone);

    if ($resultPhone && mysqli_num_rows($resultPhone) > 0) {
        $params = http_build_query(['error' => 'This phone number is already registered.', 'full_name' => $full_name, 'email' => $email, 'phone' => $phone, 'role' => $role]);
        header("Location: registration_page.php?" . $params);
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $status = in_array($role, ['hr', 'mentor', 'coordinator']) ? 'pending_approval' : 'approved';

    $sql = "INSERT INTO users (full_name, email, password, role, phone, status)
            VALUES ('" . mysqli_real_escape_string($conn, $full_name) . "', '" . mysqli_real_escape_string($conn, $email) . "', '$hashed_password', '" . mysqli_real_escape_string($conn, $role) . "', '" . mysqli_real_escape_string($conn, $phone) . "', '$status')";

    if (mysqli_query($conn, $sql)) {
        // Automatically log in the user after successful registration only if approved
        $user_id = mysqli_insert_id($conn);
        
        if ($status === 'approved') {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
        }

        if ($role === 'company') {
            ensure_company_profile($conn, $user_id, $full_name);
        }

        // Send registration notification and email to the registrant
        $reg_subject = "Welcome to IMP - Account Registration Successful";
        $reg_message = "Dear $full_name,\n\nWelcome to the Internship Management Platform (IMP)!\n\nYour account has been registered successfully as a " . ucfirst($role) . " with the email address: $email.\n\n" . 
                       ($role !== 'student' ? "Note: Your account is currently pending administrator approval. You will receive another notification once your account has been approved and activated." : "You can now log in to your student dashboard to complete your profile, browse available internships, and track applications.");
        notifyUser($user_id, $role, $email, 'Account Registration Successful', $reg_message, [
            'event' => 'Account Registration',
            'registered_name' => $full_name,
            'assigned_role' => ucfirst($role),
            'status' => ($role !== 'student' ? 'Pending Approval' : 'Active'),
            'action_url' => 'http://localhost/IMP/login.php',
            'action_label' => 'Log In to IMP'
        ], 'registration');

        // Notify admins of the new user registration
        $admin_res = mysqli_query($conn, "SELECT id, email FROM users WHERE LOWER(role) = 'admin'");
        if ($admin_res) {
            $admin_title = "New User Registered";
            $admin_message = "$full_name has registered a new account as " . ucfirst($role) . ".";
            while ($admin_row = mysqli_fetch_assoc($admin_res)) {
                $admin_id = intval($admin_row['id']);
                $admin_email = trim($admin_row['email']);
                notifyUser($admin_id, 'admin', $admin_email, $admin_title, $admin_message, [
                    'event' => 'New Student Registered',
                    'user_name' => $full_name,
                    'user_email' => $email,
                    'assigned_role' => ucfirst($role),
                    'action_url' => 'http://localhost/IMP/admin_users.php',
                    'action_label' => 'Review Users'
                ], 'new_registration');
            }
        }

        if ($role === 'student') {
            $msg = "Account created! Please log in to continue.";
            header("Location: login.php?success=" . urlencode($msg));
            exit();
        } else {
            $msg = "Account created! Please wait for admin approval, then log in.";
            header("Location: login.php?success=" . urlencode($msg));
            exit();
        }
    } else {
        echo 'Error: ' . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Sign Up | InternshipHub</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-error-container": "#93000a",
                        "inverse-surface": "#2e3132",
                        "outline": "#737685",
                        "surface-container": "#edeeef",
                        "surface-container-high": "#e7e8e9",
                        "inverse-on-surface": "#f0f1f2",
                        "surface-tint": "#1b55d0",
                        "surface-container-lowest": "#ffffff",
                        "background": "#f8f9fa",
                        "error-container": "#ffdad6",
                        "on-primary-fixed": "#00174b",
                        "error": "#ba1a1a",
                        "surface-bright": "#f8f9fa",
                        "on-background": "#191c1d",
                        "on-tertiary-fixed-variant": "#7d2d00",
                        "secondary-fixed": "#dce3ed",
                        "on-tertiary": "#ffffff",
                        "on-error": "#ffffff",
                        "outline-variant": "#c3c6d6",
                        "primary-container": "#004ac6",
                        "secondary": "#585f67",
                        "on-secondary": "#ffffff",
                        "on-surface": "#191c1d",
                        "surface-container-highest": "#e1e3e4",
                        "primary-fixed": "#dbe1ff",
                        "on-primary-container": "#b8c8ff",
                        "surface-dim": "#d9dadb",
                        "on-secondary-fixed": "#151c23",
                        "on-surface-variant": "#434654",
                        "on-secondary-container": "#5e656d",
                        "tertiary-container": "#943700",
                        "primary-fixed-dim": "#b4c5ff",
                        "tertiary-fixed-dim": "#ffb596",
                        "tertiary-fixed": "#ffdbcd",
                        "surface-variant": "#e1e3e4",
                        "on-primary": "#ffffff",
                        "inverse-primary": "#b4c5ff",
                        "primary": "#003594",
                        "on-tertiary-container": "#ffba9d",
                        "on-primary-fixed-variant": "#003ea8",
                        "surface-container-low": "#f3f4f5",
                        "on-secondary-fixed-variant": "#40474f",
                        "tertiary": "#6e2700",
                        "on-tertiary-fixed": "#360f00",
                        "secondary-fixed-dim": "#c0c7d0",
                        "secondary-container": "#dce3ed",
                        "surface": "#f8f9fa"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "lg": "24px",
                        "sm": "8px",
                        "xs": "4px",
                        "xl": "32px",
                        "unit": "4px",
                        "container-margin": "40px",
                        "md": "16px",
                        "gutter": "20px"
                    },
                    "fontFamily": {
                        "label-sm": ["Inter"],
                        "label-md": ["Inter"],
                        "h1": ["Inter"],
                        "h2": ["Inter"],
                        "body-md": ["Inter"],
                        "h3": ["Inter"],
                        "body-lg": ["Inter"]
                    },
                    "fontSize": {
                        "label-sm": ["12px", { "lineHeight": "16px", "fontWeight": "600" }],
                        "label-md": ["14px", { "lineHeight": "20px", "fontWeight": "500" }],
                        "h1": ["30px", { "lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "h2": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
                        "body-md": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
                        "h3": ["20px", { "lineHeight": "28px", "fontWeight": "600" }],
                        "body-lg": ["16px", { "lineHeight": "24px", "fontWeight": "400" }]
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
        }

        .role-card-radio:checked+.role-card-content {
            border-color: #003594;
            background-color: #f0f7ff;
            box-shadow: 0 0 0 1px #003594;
        }

        .role-card-radio:checked+.role-card-content .role-icon {
            color: #003594;
        }
    </style>
</head>

<body class="bg-background text-on-background font-body-md min-h-screen flex flex-col md:flex-row">
    <!-- Brand Visual Section -->
    <section
        class="hidden md:flex md:w-5/12 lg:w-1/2 bg-primary relative overflow-hidden flex-col justify-between p-12">
        <div class="relative z-10">
            <div class="mb-12">
                <a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
                    <svg class="w-8 h-8 text-white shrink-0" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="32" height="32" rx="8" fill="currentColor"/>
                        <circle cx="16" cy="16" r="3" fill="#003594"/>
                        <line x1="16" y1="13" x2="16" y2="9" stroke="#003594" stroke-width="1.5"/>
                        <circle cx="16" cy="8" r="1.5" fill="#003594"/>
                        <line x1="18.5" y1="15.1" x2="22.5" y2="13.8" stroke="#003594" stroke-width="1.5"/>
                        <circle cx="23.5" cy="13.5" r="1.5" fill="#003594"/>
                        <line x1="17.8" y1="18.4" x2="20.0" y2="21.5" stroke="#003594" stroke-width="1.5"/>
                        <circle cx="20.7" cy="22.5" r="1.5" fill="#003594"/>
                        <line x1="14.2" y1="18.4" x2="12.0" y2="21.5" stroke="#003594" stroke-width="1.5"/>
                        <circle cx="11.3" cy="22.5" r="1.5" fill="#003594"/>
                        <line x1="13.5" y1="15.1" x2="9.5" y2="13.8" stroke="#003594" stroke-width="1.5"/>
                        <circle cx="8.5" cy="13.5" r="1.5" fill="#003594"/>
                    </svg>
                    <span class="text-2xl font-bold text-white tracking-tight">IMP</span>
                </a>
                <p class="font-body-lg text-body-lg text-primary-fixed-dim mt-2 max-w-md">Bridging the gap between
                    academic ambition and corporate professionalism with structured opportunity.</p>
            </div>
            <div class="space-y-8 mt-24">
                <div class="flex items-start gap-4">
                    <div class="bg-white/10 p-3 rounded-lg backdrop-blur-sm">
                        <span class="material-symbols-outlined text-white" data-icon="work">work</span>
                    </div>
                    <div>
                        <h3 class="font-h3 text-h3 text-white">Curated Opportunities</h3>
                        <p class="font-body-md text-body-md text-primary-fixed-dim">Access exclusive internships from
                            top-tier global companies tailored to your field of study.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="bg-white/10 p-3 rounded-lg backdrop-blur-sm">
                        <span class="material-symbols-outlined text-white"
                            data-icon="compliance">health_and_safety</span>
                    </div>
                    <div>
                        <h3 class="font-h3 text-h3 text-white">Compliance Tracking</h3>
                        <p class="font-body-md text-body-md text-primary-fixed-dim">Integrated tools for university
                            administrators to monitor student progress and regulatory alignment.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="relative z-10 pt-12 border-t border-white/10">
            <p class="font-label-sm text-label-sm text-primary-fixed-dim opacity-70">© 2024 InternshipHub Inc. All
                rights reserved.</p>
        </div>
        <!-- Decorative Background Element -->
        <div class="absolute top-0 right-0 w-full h-full opacity-20 pointer-events-none">
            <img alt="Corporate professional environment" class="w-full h-full object-cover"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuAQL_nLWTZcefxCIRb3sVowG5t3SovDUSdJEZqNp-psmmw8urO7splL_OtErvYuZqK4KhLAyWeZkI7dWLg3__bg1QaQ6651xA9OF6aWZMIzj4iwb8XyJXpuGG465jTnz7jRKuW2E9GcWwFb__33h26cBa1VC2i66RvPR5uZ_G7fA6RWHa_Kt-QgOa0er9BID7U5loMlFpKnGMOG19DxAHbYz9rBYc2fXBb9_kgoOZMsyIzTIu3Npgy8Se7gKQXkk3EzHa2CTlCBeg">
        </div>
    </section>
    <!-- Signup Form Section -->
    <main
        class="w-full md:w-7/12 lg:w-1/2 bg-white flex flex-col items-center p-8 md:p-16 lg:px-24 py-12 overflow-y-auto">
        <div class="w-full max-w-2xl">
            <div class="mb-10 text-center md:text-left">
                <h2 class="font-h2 text-h2 text-on-surface">Create your account</h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-2">Start your journey with the world's
                    most trusted internship ecosystem.</p>
            </div>
            <form id="register-form" class="space-y-6" action="register.php" method="POST">
                <!-- Name & Credentials -->
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant" for="fullname">Full
                            Name</label>
                        <input
                            class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                            id="fullname" name="full_name" placeholder="John Doe" required="" type="text">
                    </div>
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant" for="email">Email
                            Address</label>
                        <input
                            class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                            id="email" name="email" placeholder="name@organization.com" required="" type="email">
                    </div>
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant" for="phone">Phone Number</label>
                        <input
                            class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                            id="phone" name="phone" placeholder="10-digit number" required="" type="tel" pattern="[0-9]{10}" maxlength="10">
                    </div>
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant"
                            for="password">Password</label>
                        <input
                            class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                            id="password" name="password" placeholder="••••••••" required="" type="password">
                        <div class="flex flex-col gap-2 mt-2">
                            <p class="font-label-sm text-label-sm text-outline">Minimum 8 characters, at least 1 special
                                character</p>
                            <div class="flex flex-wrap gap-4 mt-1">
                                <div class="flex items-center gap-1 text-label-sm text-success-600 opacity-60">
                                    <span class="material-symbols-outlined text-[16px] leading-none"
                                        data-icon="check_circle">check_circle</span>
                                    <span class="">8+ characters</span>
                                </div>
                                <div class="flex items-center gap-1 text-label-sm text-outline opacity-60">
                                    <span class="material-symbols-outlined text-[16px] leading-none"
                                        data-icon="circle">circle</span>
                                    <span class="">Special character</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant"
                            for="confirm-password">Confirm Password</label>
                        <input
                            class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                            id="confirm-password" name="confirm_password" placeholder="••••••••" required=""
                            type="password">
                    </div>
                </div>
                <!-- Role Selection -->
                <div class="space-y-4 pt-4">
                    <div class="flex flex-col gap-1">
                        <label
                            class="font-label-md text-label-md text-on-surface-variant block uppercase tracking-wider">Select
                            your role</label>
                        <p class="text-label-sm text-outline">Select your role to continue</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Student (Highlighted by default) -->
                        <label class="cursor-pointer group">
                            <input checked="" class="role-card-radio hidden" name="role" type="radio" value="student">
                            <div
                                class="role-card-content h-full border border-outline-variant rounded-lg p-4 transition-all hover:bg-surface-container-low flex flex-col gap-2 shadow-sm">
                                <span class="material-symbols-outlined role-icon text-secondary"
                                    data-icon="school">school</span>
                                <div>
                                    <span class="font-label-md text-label-md block">Student</span>
                                    <p class="text-[11px] text-on-surface-variant mt-1 leading-tight">Apply and track
                                        internships</p>
                                </div>
                            </div>
                        </label>
                        <!-- HR Manager -->
                        <label class="cursor-pointer group">
                            <input class="role-card-radio hidden" name="role" type="radio" value="hr">
                            <div
                                class="role-card-content h-full border border-outline-variant rounded-lg p-4 transition-all hover:bg-surface-container-low flex flex-col gap-2 shadow-sm">
                                <span class="material-symbols-outlined role-icon text-secondary"
                                    data-icon="badge">badge</span>
                                <div>
                                    <span class="font-label-md text-label-md block">HR</span>
                                    <p class="text-[11px] text-on-surface-variant mt-1 leading-tight">Manage candidates
                                        and hiring process</p>
                                </div>
                            </div>
                        </label>
                        <!-- Coordinator -->
                        <label class="cursor-pointer group">
                            <input class="role-card-radio hidden" name="role" type="radio" value="coordinator">
                            <div
                                class="role-card-content h-full border border-outline-variant rounded-lg p-4 transition-all hover:bg-surface-container-low flex flex-col gap-2 shadow-sm">
                                <span class="material-symbols-outlined role-icon text-secondary"
                                    data-icon="clinical_notes">clinical_notes</span>
                                <div>
                                    <span class="font-label-md text-label-md block">Coordinator</span>
                                    <p class="text-[11px] text-on-surface-variant mt-1 leading-tight">Monitor
                                        internships and workflows</p>
                                </div>
                            </div>
                        </label>
                        <!-- Mentor/Guide -->
                        <label class="cursor-pointer group">
                            <input class="role-card-radio hidden" name="role" type="radio" value="mentor">
                            <div
                                class="role-card-content h-full border border-outline-variant rounded-lg p-4 transition-all hover:bg-surface-container-low flex flex-col gap-2 shadow-sm">
                                <span class="material-symbols-outlined role-icon text-secondary"
                                    data-icon="psychology">psychology</span>
                                <div>
                                    <span class="font-label-md text-label-md block">Mentor/Guide</span>
                                    <p class="text-[11px] text-on-surface-variant mt-1 leading-tight">Review and guide
                                        student progress</p>
                                </div>
                            </div>
                        </label>
                        <!-- Company -->
                        <label class="cursor-pointer group">
                            <input class="role-card-radio hidden" name="role" type="radio" value="company">
                            <div
                                class="role-card-content h-full border border-outline-variant rounded-lg p-4 transition-all hover:bg-surface-container-low flex flex-col gap-2 shadow-sm">
                                <span class="material-symbols-outlined role-icon text-secondary"
                                    data-icon="corporate_fare">corporate_fare</span>
                                <div>
                                    <span class="font-label-md text-label-md block">Company</span>
                                    <p class="text-[11px] text-on-surface-variant mt-1 leading-tight">Hire trained
                                        interns</p>
                                </div>
                            </div>
                        </label>
                    </div>
                    <div class="bg-surface-container-low p-4 rounded-lg space-y-2 mt-4">
                        <div class="flex gap-2 text-label-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[18px]"
                                data-icon="verified_user">verified_user</span>
                            <p class="">HR, Mentor, and Coordinator accounts require admin approval before access.</p>
                        </div>
                        <div class="flex gap-2 text-label-sm text-outline italic">
                            <span class="material-symbols-outlined text-[18px]" data-icon="info">info</span>
                            <p class="">Additional details (resume, identity verification) will be collected after
                                login.</p>
                        </div>
                    </div>
                </div>
                <!-- Action -->
                <div class="pt-6">
                    <button
                        id="register-submit-btn"
                        class="w-full bg-primary text-on-primary py-4 rounded-lg font-label-md text-label-md hover:bg-primary-container hover:shadow-lg transition-all active:scale-[0.98] shadow-md flex items-center justify-center gap-2"
                        type="submit">
                        <span class="btn-text">Create Account</span>
                    </button>
                </div>
            </form>
            <footer class="mt-10 pt-8 border-t border-surface-container-highest">
                <p class="text-center font-body-md text-body-md text-on-surface-variant mb-6">
                    Already have an account?
                    <a class="text-primary font-semibold hover:underline decoration-primary" href="login.php">Log
                        In</a>
                </p>
                <p class="text-center font-label-sm text-label-sm text-outline">
                    By signing up, you agree to InternshipHub's
                    <a class="underline hover:text-on-surface transition-colors" href="#">Terms of Service</a> and
                    <a class="underline hover:text-on-surface transition-colors" href="#">Privacy Policy</a>.
                </p>
            </footer>
        </div>
    </main>

    <!-- Success Overlay Modal -->
    <div id="success-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-500 scale-95">
        <div
            class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl border border-gray-100 flex flex-col items-center text-center">
            <div
                class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-4 shadow-inner animate-bounce">
                <span class="material-symbols-outlined text-3xl" data-icon="check_circle">check_circle</span>
            </div>
            <h3 class="font-h2 text-h2 text-gray-900 mb-2">Account created successfully!</h3>
            <p class="text-sm text-gray-600 mb-6" id="success-role-text">Welcome aboard! Your registration has been
                received.</p>

            <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden mb-4">
                <div class="bg-green-600 h-full w-full rounded-full animate-[shrink_1.5s_linear_forwards]"></div>
            </div>
            <p class="text-xs text-gray-400 font-medium animate-pulse">Redirecting to login page...</p>
        </div>
    </div>

    <style>
        @keyframes shrink {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }
    </style>



    <script>
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length !== 10) {
                this.setCustomValidity('Phone number must be exactly 10 digits.');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    document.getElementById('register-form').addEventListener('submit', function(e) {
        const phoneInput = document.getElementById('phone');
        if (phoneInput && !/^[0-9]{10}$/.test(phoneInput.value)) {
            e.preventDefault();
            phoneInput.setCustomValidity('Phone number must be exactly 10 digits.');
            phoneInput.reportValidity();
            return false;
        }

        const btn = document.getElementById('register-submit-btn');
        if (btn) {
            btn.style.pointerEvents = 'none';
            const textSpan = btn.querySelector('.btn-text');
            if (textSpan) {
                textSpan.textContent = 'Creating account...';
            }
            const spinner = document.createElement('span');
            spinner.className = 'material-symbols-outlined text-lg animate-spin';
            spinner.textContent = 'sync';
            btn.appendChild(spinner);
        }
    });

    function sanitizeEncodingIssues() {
        const replacements = {
            'â€¢': '•',
            'â€“': '—',
            'â€œ': '“',
            'â€\u009d': '”',
            'â€”': '—',
            'â€™': '’',
            'â€': ''
        };

        function walk(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                let text = node.nodeValue;
                let updated = false;
                for (const [key, val] of Object.entries(replacements)) {
                    if (text.includes(key)) {
                        text = text.replaceAll(key, val);
                        updated = true;
                    }
                }
                if (updated) {
                    node.nodeValue = text;
                }
            } else if (node.nodeType === Node.ELEMENT_NODE && node.shadowRoot) {
                walk(node.shadowRoot);
            } else {
                for (let child = node.firstChild; child; child = child.nextSibling) {
                    walk(child);
                }
            }
        }
        
        document.querySelectorAll('input, textarea').forEach(el => {
            ['placeholder', 'value'].forEach(attr => {
                let val = el.getAttribute(attr);
                if (val) {
                    let updated = false;
                    for (const [key, val2] of Object.entries(replacements)) {
                        if (val.includes(key)) {
                            val = val.replaceAll(key, val2);
                            updated = true;
                        }
                    }
                    if (updated) {
                        el.setAttribute(attr, val);
                    }
                }
            });
        });

        walk(document.body);
    }
    document.addEventListener('DOMContentLoaded', sanitizeEncodingIssues);
    </script>
</body>

</html>
