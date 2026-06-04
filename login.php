<?php
ob_start();
session_start();
include 'db.php';
include_once __DIR__ . '/includes/auth.php';

// ── POST: process login ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            if (isset($user['status'])) {
                $status = strtolower($user['status']);
                if ($status === 'inactive') {
                    header("Location: login.php?error=" . urlencode("Your account has been deactivated. Please contact support."));
                    exit();
                } elseif ($status === 'pending_approval') {
                    header("Location: login.php?error=" . urlencode("Your account is pending admin approval. You will receive an email once approved."));
                    exit();
                } elseif ($status === 'rejected') {
                    header("Location: login.php?error=" . urlencode("Your account registration has been rejected by Admin."));
                    exit();
                }
            }
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            // Role-based redirection
            $role = strtolower($user['role']);
            
            if ($role == "student") {
                // Check if student has completed profile
                $check_sql    = "SELECT id FROM student_profiles WHERE user_id = '{$user['id']}'";
                $check_result = mysqli_query($conn, $check_sql);
                if (mysqli_num_rows($check_result) > 0) {
                    header("Location: student_dashboard.php");
                } else {
                    header("Location: student_profile_form.php");
                }
            } elseif ($role == "hr") {
                header("Location: hr_dashboard.php");
            } elseif ($role == "mentor") {
                header("Location: mentor_dashboard.php");
            } elseif ($role == "coordinator") {
                header("Location: coordinator_dashboard.php");
            } elseif ($role == "company") {
                ensure_company_profile($conn, $user['id'], $user['full_name']);
                header("Location: company_dashboard.php");
            } elseif ($role == "admin") {
                header("Location: admin_dashboard.php");
            } else {
                // Default fallback for unknown roles
                header("Location: student_dashboard.php");
            }
            exit();
        } else {
            header("Location: login.php?error=" . urlencode("Invalid email or password"));
            exit();
        }
    } else {
        header("Location: login.php?error=" . urlencode("Invalid email or password"));
        exit();
    }
}

// ── GET: show login page ──
$error_msg   = '';
$success_msg = '';
if (isset($_GET['error']))   $error_msg   = htmlspecialchars(urldecode($_GET['error']));
if (isset($_GET['success'])) $success_msg = htmlspecialchars(urldecode($_GET['success']));
?>
<!DOCTYPE html><html class="light" lang="en"><head>
<meta charset="UTF-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "on-surface-variant": "#434655",
                    "secondary-fixed-dim": "#c0c7d0",
                    "on-primary-container": "#eeefff",
                    "surface-container-low": "#f3f4f5",
                    "primary-fixed": "#dbe1ff",
                    "outline": "#737686",
                    "secondary": "#585f67",
                    "tertiary-container": "#bc4800",
                    "error": "#ba1a1a",
                    "primary-container": "#2563eb",
                    "surface": "#f8f9fa",
                    "on-primary-fixed-variant": "#003ea8",
                    "on-primary": "#ffffff",
                    "on-secondary-container": "#5e656d",
                    "on-background": "#191c1d",
                    "surface-container": "#edeeef",
                    "on-tertiary": "#ffffff",
                    "on-secondary": "#ffffff",
                    "secondary-fixed": "#dce3ec",
                    "on-tertiary-container": "#ffede6",
                    "on-tertiary-fixed-variant": "#7d2d00",
                    "outline-variant": "#c3c6d7",
                    "inverse-primary": "#b4c5ff",
                    "secondary-container": "#dce3ec",
                    "surface-dim": "#d9dadb",
                    "on-surface": "#191c1d",
                    "on-secondary-fixed-variant": "#40484f",
                    "inverse-surface": "#2e3132",
                    "on-error": "#ffffff",
                    "background": "#f8f9fa",
                    "primary": "#004ac6",
                    "tertiary": "#943700",
                    "tertiary-fixed": "#ffdbcd",
                    "surface-variant": "#e1e3e4",
                    "surface-container-highest": "#e1e3e4",
                    "on-tertiary-fixed": "#360f00",
                    "surface-container-lowest": "#ffffff",
                    "error-container": "#ffdad6",
                    "on-primary-fixed": "#00174b",
                    "surface-tint": "#0053db",
                    "primary-fixed-dim": "#b4c5ff",
                    "on-error-container": "#93000a",
                    "surface-bright": "#f8f9fa",
                    "inverse-on-surface": "#f0f1f2",
                    "on-secondary-fixed": "#151c23",
                    "surface-container-high": "#e7e8e9",
                    "tertiary-fixed-dim": "#ffb596"
            },
            "borderRadius": {
                    "DEFAULT": "0.25rem",
                    "lg": "0.5rem",
                    "xl": "0.75rem",
                    "full": "9999px"
            },
            "spacing": {
                    "xl": "32px",
                    "lg": "24px",
                    "container-margin": "40px",
                    "md": "16px",
                    "sm": "8px",
                    "xs": "4px",
                    "gutter": "20px",
                    "unit": "4px"
            },
            "fontFamily": {
                    "body-lg": ["Inter"],
                    "label-md": ["Inter"],
                    "body-md": ["Inter"],
                    "h1": ["Inter"],
                    "label-sm": ["Inter"],
                    "h3": ["Inter"],
                    "h2": ["Inter"]
            },
            "fontSize": {
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "label-md": ["14px", {"lineHeight": "20px", "fontWeight": "500"}],
                    "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "h1": ["30px", {"lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                    "h3": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                    "h2": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}]
            }
          },
        },
      }
    </script>
<style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .auth-background {
            background-image: linear-gradient(135deg, #f8f9fa 0%, #e7e8e9 100%);
        }
        /* Hide browser default password reveal button */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)}
            20%{transform:translateX(-6px)}
            40%{transform:translateX(6px)}
            60%{transform:translateX(-4px)}
            80%{transform:translateX(4px)}
        }
        .animate-shake { animation: shake .4s ease; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-10px); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
        .animate-fade-out {
            animation: fadeOut 0.4s ease-in forwards;
        }
    </style>
</head>
<body class="bg-background text-on-background min-h-screen flex flex-col">
<header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3">
<div class="flex items-center gap-2">
    <a href="index.html" class="flex items-center gap-2 hover:opacity-95 transition-opacity">
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
    </a>
</div>
<div class="flex items-center gap-4">
<span class="text-gray-500 font-label-md text-label-md">Need help?</span>
<span class="material-symbols-outlined text-gray-500 cursor-pointer hover:bg-gray-50 p-2 rounded-full transition-colors" data-icon="help_outline">help_outline</span>
</div>
</header>
<main class="flex-grow flex items-center justify-center p-6 auth-background">
<div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
<div class="lg:col-span-5 flex flex-col justify-center space-y-6">
<div class="space-y-2">
<h1 class="font-h1 text-h1 text-on-background">Bridge the gap between talent and opportunity.</h1>
<p class="font-body-lg text-body-lg text-on-surface-variant">The unified platform for students, universities, and industry leaders to manage the future of internships.</p>
</div>
<div class="grid grid-cols-1 gap-4">
<div class="flex items-center gap-4 p-4 bg-white/50 rounded-lg border border-outline-variant">
<div class="w-10 h-10 rounded-full bg-primary-fixed flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-primary" data-icon="verified">verified</span>
</div>
<div>
<p class="font-label-md text-label-md">Role-Based Access</p>
<p class="font-body-md text-body-md text-on-surface-variant">Tailored dashboards for every user type.</p>
</div>
</div>
<div class="flex items-center gap-4 p-4 bg-white/50 rounded-lg border border-outline-variant">
<div class="w-10 h-10 rounded-full bg-secondary-fixed flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-secondary" data-icon="analytics">analytics</span>
</div>
<div>
<p class="font-label-md text-label-md">Real-time Analytics</p>
<p class="font-body-md text-body-md text-on-surface-variant">Comprehensive tracking for HODs and Coordinators.</p>
</div>
</div>
</div>
<div class="relative w-full h-48 rounded-xl overflow-hidden shadow-lg border border-outline-variant">
<img class="w-full h-full object-cover" data-alt="A professional collaborative office environment where diverse group of young professionals are working together around a sleek modern conference table. The lighting is bright and natural, coming from large floor-to-ceiling windows, highlighting a clean corporate aesthetic with blue and white accents. Everyone looks focused and productive, representing a structured opportunity in a modern business setting with minimalist interior design." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCrRhXhVJkMKrx--fMHL8T7AGJa6NtDjNrY3lnPcQnHwFMSjb37qHtEJX-BaOvGFlcqJMMuI8mIJBwcLyXWmcL25Q6MNpY0jwHEHhB42nUB5VgCfupTgK2w1z1YIJaQ7R97ph-QUIX5wkEerhVTsiOUKjOUI8mMd9y20zyoxUPczneg6c6TpqNFyiYykXNa7MjEfrodqShSI8bMse3eHhcj6o7v1hv9k1TKkxy7W_lZAK0Ukm-6WBHMC8G_DLYGgjZrfPJoMHo71Q">
<div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent flex items-end p-4">
<p class="text-white text-sm font-medium italic">"Empowering the next generation of professionals through seamless coordination."</p>
</div>
</div>
</div>
<div class="lg:col-span-7">
<div class="bg-surface-container-lowest rounded-xl shadow-[0px_1px_3px_rgba(0,0,0,0.1),0px_1px_2px_rgba(0,0,0,0.06)] p-8 md:p-10 border border-outline-variant h-full">
<div class="text-center mb-8">
<h2 class="font-h2 text-h2 text-on-background">Welcome to IMP</h2>
<p class="font-body-md text-body-md text-on-surface-variant mt-2">Access your internship dashboard</p>
</div>
<?php if ($success_msg): ?>
<div id="login-success-container" class="animate-fade-in mb-4 alert-success">
    <div id="login-success-banner" class="flex items-center gap-3 p-3.5 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700 font-medium">
        <span class="material-symbols-outlined text-green-500 text-[20px] flex-shrink-0">check_circle</span>
        <span><?php echo $success_msg; ?></span>
    </div>
</div>
<?php endif; ?>
<form id="login-form" class="space-y-6" action="login.php" method="POST">
<div class="space-y-4">
<div>
<label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Email Address</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg" data-icon="mail">mail</span>
<input id="login-email" name="email" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-outline outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-body-md text-body-md" placeholder="Enter your email" type="email" required>
</div>
</div>
<div>
<div class="flex justify-between mb-1.5">
<label class="block font-label-md text-label-md text-on-surface-variant">Password</label>
<a class="font-label-sm text-label-sm text-primary hover:underline" href="forgot_password.php">Forgot Password?</a>
</div>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg" data-icon="lock">lock</span>
<input id="login-password" name="password" class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-outline outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-body-md text-body-md" placeholder="••••••••" type="password" required>
<span id="toggle-password" class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline text-lg cursor-pointer" data-icon="visibility">visibility</span>
</div>
</div>
</div>
<div class="flex items-center">
<input class="w-4 h-4 rounded border-outline text-primary focus:ring-primary/20" id="remember" type="checkbox">
<label class="ml-2 font-body-md text-body-md text-on-surface-variant" for="remember">Keep me logged in for 30 days</label>
</div>
<?php if ($error_msg): ?>
<div id="login-error-container" class="animate-fade-in alert-danger">
    <div id="login-error-banner" class="flex items-center gap-3 p-3.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium animate-shake">
        <span class="material-symbols-outlined text-red-500 text-[20px] flex-shrink-0">error</span>
        <span><?php echo $error_msg; ?></span>
    </div>
</div>
<?php endif; ?>
<button type="submit" id="login-submit-btn" class="w-full py-3 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2"><span class="btn-text">Sign In</span><span class="material-symbols-outlined text-lg" data-icon="arrow_forward">arrow_forward</span></button><p class="mt-4 text-center font-body-md text-body-md text-on-surface-variant opacity-75">Login for Students, HR, Mentors, and Companies</p>
</form>
<div class="flex items-center justify-center gap-1.5 mt-10 mb-2 text-on-surface-variant opacity-60">
<span class="material-symbols-outlined text-sm" data-icon="lock">lock</span>
<span class="font-label-sm text-label-sm">Your data is securely protected</span>
</div><p class="text-center font-body-md text-body-md text-on-surface-variant mt-2">Don't have an account? <a class="text-primary font-label-md hover:underline" href="registration_page.php">Sign Up</a></p>
</div>
</div>
</div>
</main>
<footer class="w-full py-6 px-6 border-t border-gray-200 bg-white">
<div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
<div class="flex items-center gap-6">
<a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Privacy Policy</a>
<a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Terms of Service</a>
<a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Contact Support</a>
</div>
<p class="font-label-sm text-label-sm text-gray-400">© 2024 InternshipHub. All rights reserved.</p>
</div>
</footer>

<script>
// Password Visibility Toggle
const togglePassword = document.getElementById('toggle-password');
const passwordInput = document.getElementById('login-password');
if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
    });
}

if (document.getElementById('login-error-banner')) {
    document.getElementById('login-email').classList.add('border-red-400', 'bg-red-50/30');
    document.getElementById('login-password').classList.add('border-red-400', 'bg-red-50/30');
    // Clear red on input
    ['login-email','login-password'].forEach(id => {
        const inputEl = document.getElementById(id);
        if (inputEl) {
            inputEl.addEventListener('input', function() {
                this.classList.remove('border-red-400','bg-red-50/30');
            });
        }
    });
}

document.getElementById('login-form').addEventListener('submit', function(e) {
    const btn = document.getElementById('login-submit-btn');
    if (btn) {
        btn.style.pointerEvents = 'none';
        const textSpan = btn.querySelector('.btn-text');
        if (textSpan) {
            textSpan.textContent = 'Please wait...';
        }
        const iconSpan = btn.querySelector('.material-symbols-outlined');
        if (iconSpan) {
            iconSpan.textContent = 'sync';
            iconSpan.classList.add('animate-spin');
        }
    }
});

// Auto-dismiss banners after 2 seconds, fade out, then reload the login page
function handleBannerAutoDismiss(container) {
    if (!container) return;
    setTimeout(() => {
        container.classList.remove('animate-fade-in');
        container.classList.add('animate-fade-out');
        setTimeout(() => {
            container.remove();
            // Reload the login page cleanly (removing query parameters)
            window.location.href = 'login.php';
        }, 400); // Wait for fade-out animation to complete
    }, 2000);
}

const errorContainer = document.getElementById('login-error-container');
const successContainer = document.getElementById('login-success-container');

if (errorContainer) {
    handleBannerAutoDismiss(errorContainer);
}
if (successContainer) {
    handleBannerAutoDismiss(successContainer);
}

// Override window.alert to show a custom non-blocking notification banner
// with the same design, which auto-dismisses and reloads after 2 seconds
function escapeHtml(str) {
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#039;');
}

window.alert = function(message) {
    // Prevent duplicate banners
    const existing = document.querySelectorAll('#login-error-container, #login-success-container, .custom-js-alert-container');
    existing.forEach(el => el.remove());

    const isSuccess = /success|logout|logged out|ok/i.test(message);
    const container = document.createElement('div');
    container.className = 'custom-js-alert-container animate-fade-in mb-4';

    if (isSuccess) {
        container.innerHTML = `
            <div class="flex items-center gap-3 p-3.5 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700 font-medium">
                <span class="material-symbols-outlined text-green-500 text-[20px] flex-shrink-0">check_circle</span>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
    } else {
        container.innerHTML = `
            <div class="flex items-center gap-3 p-3.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium animate-shake">
                <span class="material-symbols-outlined text-red-500 text-[20px] flex-shrink-0">error</span>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
    }

    const form = document.getElementById('login-form');
    if (form) {
        form.parentNode.insertBefore(container, form);
    }

    // Auto dismiss and reload page after 2 seconds
    setTimeout(() => {
        container.classList.remove('animate-fade-in');
        container.classList.add('animate-fade-out');
        setTimeout(() => {
            container.remove();
            window.location.href = 'login.php';
        }, 400);
    }, 2000);
};

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
<script src="js/alerts.js"></script>
</body></html>
