<?php
$reg_error   = '';
$reg_success = '';
if (isset($_GET['error']))   $reg_error   = htmlspecialchars(urldecode($_GET['error']));
if (isset($_GET['success'])) $reg_success = htmlspecialchars(urldecode($_GET['success']));
// Preserve submitted values to re-fill form on error
$old_fullname = isset($_GET['full_name']) ? htmlspecialchars($_GET['full_name']) : '';
$old_email    = isset($_GET['email'])     ? htmlspecialchars($_GET['email'])     : '';
$old_phone    = isset($_GET['phone'])     ? htmlspecialchars($_GET['phone'])     : '';
$old_role     = isset($_GET['role'])      ? htmlspecialchars($_GET['role'])      : 'student';
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8">
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

        @keyframes shake {
            0%,100%{transform:translateX(0)}
            20%{transform:translateX(-6px)}
            40%{transform:translateX(6px)}
            60%{transform:translateX(-4px)}
            80%{transform:translateX(4px)}
        }
        .animate-shake { animation: shake .4s ease; }
        /* Hide browser default password reveal button */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
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
                            id="fullname" name="full_name" placeholder="John Doe" required="" type="text"
                            value="<?php echo $old_fullname; ?>">
                    </div>
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant" for="email">Email
                            Address</label>
                        <input
                            class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                            id="email" name="email" placeholder="name@organization.com" required="" type="email"
                            value="<?php echo $old_email; ?>">
                    </div>
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant" for="phone">Phone Number</label>
                        <input
                            class="w-full px-4 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                            id="phone" name="phone" placeholder="10-digit number" required="" type="tel" pattern="[0-9]{10}" maxlength="10"
                            value="<?php echo $old_phone; ?>">
                    </div>
                    <div class="space-y-2">
                        <label class="font-label-md text-label-md text-on-surface-variant"
                            for="password">Password</label>
                        <div class="relative">
                            <input
                                class="w-full px-4 pr-10 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                                id="password" name="password" placeholder="••••••••" required="" type="password">
                            <span id="toggle-password" class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline text-lg cursor-pointer">visibility</span>
                        </div>
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
                        <div class="relative">
                            <input
                                class="w-full px-4 pr-10 py-3 border border-outline-variant rounded-lg bg-surface-container-lowest focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-outline/50"
                                id="confirm-password" name="confirm_password" placeholder="••••••••" required=""
                                type="password">
                            <span id="toggle-confirm-password" class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline text-lg cursor-pointer">visibility</span>
                        </div>
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
                            <input <?php echo ($old_role === 'student' || $old_role === '') ? 'checked=""' : ''; ?> class="role-card-radio hidden" name="role" type="radio" value="student">
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
                            <input <?php echo ($old_role === 'hr') ? 'checked=""' : ''; ?> class="role-card-radio hidden" name="role" type="radio" value="hr">
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
                            <input <?php echo ($old_role === 'coordinator') ? 'checked=""' : ''; ?> class="role-card-radio hidden" name="role" type="radio" value="coordinator">
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
                            <input <?php echo ($old_role === 'mentor') ? 'checked=""' : ''; ?> class="role-card-radio hidden" name="role" type="radio" value="mentor">
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
                            <input <?php echo ($old_role === 'company') ? 'checked=""' : ''; ?> class="role-card-radio hidden" name="role" type="radio" value="company">
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
                    <?php if ($reg_error): ?>
                    <div class="flex items-center gap-3 p-3.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium animate-shake mb-4">
                        <span class="material-symbols-outlined text-red-500 text-[20px] flex-shrink-0">error</span>
                        <span><?php echo $reg_error; ?></span>
                    </div>
                    <?php endif; ?>
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
                    <a class="text-primary font-semibold hover:underline decoration-primary" href="/IMP/login.php">Log
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
    document.addEventListener('DOMContentLoaded', function() {
        // ── Password Visibility Toggles ─────────────────────────────────────
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const togglePassword = document.getElementById('toggle-password');
        const toggleConfirmPassword = document.getElementById('toggle-confirm-password');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
            });
        }

        if (toggleConfirmPassword && confirmPasswordInput) {
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
            });
        }

        // ── Phone Validation ────────────────────────────────────────────────
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

        // ── Password Strength Validation ────────────────────────────────────
        const passwordRequirementsMessage = 'Password must contain at least:\n• 8 characters\n• One uppercase letter\n• One lowercase letter\n• One number\n• One special character';
        const strongPasswordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                if (this.value && !strongPasswordPattern.test(this.value)) {
                    this.setCustomValidity(passwordRequirementsMessage);
                } else {
                    this.setCustomValidity('');
                }
            });
        }

        // ── Form Submit Handler ─────────────────────────────────────────────
        var registerForm = document.getElementById('register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                if (phoneInput && !/^[0-9]{10}$/.test(phoneInput.value)) {
                    e.preventDefault();
                    phoneInput.setCustomValidity('Phone number must be exactly 10 digits.');
                    phoneInput.reportValidity();
                    return false;
                }

                if (passwordInput && !strongPasswordPattern.test(passwordInput.value)) {
                    e.preventDefault();
                    passwordInput.setCustomValidity(passwordRequirementsMessage);
                    passwordInput.reportValidity();
                    return false;
                }

                if (confirmPasswordInput && passwordInput && confirmPasswordInput.value !== passwordInput.value) {
                    e.preventDefault();
                    confirmPasswordInput.setCustomValidity('Passwords do not match.');
                    confirmPasswordInput.reportValidity();
                    return false;
                }

                var btn = document.getElementById('register-submit-btn');
                if (btn) {
                    btn.style.pointerEvents = 'none';
                    var textSpan = btn.querySelector('.btn-text');
                    if (textSpan) {
                        textSpan.textContent = 'Creating account...';
                    }
                    var spinner = document.createElement('span');
                    spinner.className = 'material-symbols-outlined text-lg animate-spin';
                    spinner.textContent = 'sync';
                    btn.appendChild(spinner);
                }
            });
        }
    });
    </script>
</body>

</html>
