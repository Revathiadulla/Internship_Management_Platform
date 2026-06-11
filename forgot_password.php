<?php
ob_start();
session_start();
include 'db.php';
include_once __DIR__ . '/includes/mail_helper.php';
include_once __DIR__ . '/includes/sms_helper.php';
include_once __DIR__ . '/password_validation.php';

// Ensure password_resets.code column can store SHA-256 hash (64 chars)
$chk_code = mysqli_query($conn, "SHOW COLUMNS FROM password_resets LIKE 'code'");
if ($chk_code && $col = mysqli_fetch_assoc($chk_code)) {
    if (stripos($col['Type'], 'varchar') !== false) {
        preg_match('/varchar\((\d+)\)/i', $col['Type'], $matches);
        $len = isset($matches[1]) ? intval($matches[1]) : 0;
        if ($len > 0 && $len < 64) {
            mysqli_query($conn, "ALTER TABLE password_resets MODIFY COLUMN code VARCHAR(255) NOT NULL");
        }
    }
}

// Helper function to mask email
function maskEmail($email) {
    $parts = explode("@", $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $masked_name = str_repeat('*', $len);
    } else {
        $masked_name = substr($name, 0, 1) . str_repeat('*', $len - 2) . substr($name, -1);
    }
    return $masked_name . "@" . $domain;
}

// Helper function to mask phone number
function maskPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    $len = strlen($phone);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', $len - 4) . substr($phone, -4);
}

// Reset password reset state if requested
if (isset($_GET['action']) && $_GET['action'] === 'restart') {
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_phone']);
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_method']);
    unset($_SESSION['reset_verified']);
    unset($_SESSION['reset_otp']);
    unset($_SESSION['reset_otp_hash']);
    unset($_SESSION['reset_otp_expires']);
    session_write_close();
    header("Location: forgot_password.php");
    exit();
}

// Initialize step
if (!isset($_SESSION['reset_step'])) {
    $_SESSION['reset_step'] = 1;
}

// If someone tries to skip steps manually
if ($_SESSION['reset_step'] > 1 && !isset($_SESSION['reset_email'])) {
    $_SESSION['reset_step'] = 1;
}
if ($_SESSION['reset_step'] === 2) {
    $_SESSION['reset_step'] = 1;
}
if ($_SESSION['reset_step'] === 4 && (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true)) {
    $_SESSION['reset_step'] = 3;
}

$error_msg = '';
$success_msg = '';
$info_msg = '';

// Handle POST actions based on step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1_lookup') {
        $identity = mysqli_real_escape_string($conn, trim($_POST['identity'] ?? ''));
        if (empty($identity)) {
            $error_msg = "Please enter your email address or phone number.";
        } else {
            // First check users table by email or phone
            $sql = "SELECT id, email, full_name, role, phone FROM users 
                    WHERE email = '$identity' OR phone = '$identity' LIMIT 1";
            $result = mysqli_query($conn, $sql);
            
            $user = null;
            if ($result && mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
            } else {
                // If not found, check if it's a phone number and look up in student_profiles
                $student_sql = "SELECT user_id FROM student_profiles WHERE phone = '$identity' LIMIT 1";
                $student_res = mysqli_query($conn, $student_sql);
                if ($student_res && mysqli_num_rows($student_res) > 0) {
                    $student_row = mysqli_fetch_assoc($student_res);
                    $u_id = intval($student_row['user_id']);
                    $user_sql = "SELECT id, email, full_name, role, phone FROM users WHERE id = $u_id LIMIT 1";
                    $user_res = mysqli_query($conn, $user_sql);
                    if ($user_res && mysqli_num_rows($user_res) > 0) {
                        $user = mysqli_fetch_assoc($user_res);
                    }
                }
            }

            if ($user) {
                $_SESSION['reset_email'] = $user['email'];
                $_SESSION['reset_role'] = $user['role'];
                
                $full_name = trim($user['full_name'] ?? '');
                
                // Retrieve phone and name from student profile if student
                $phone = $user['phone'];
                if (strtolower($user['role']) === 'student') {
                    $student_sql = "SELECT phone, full_name FROM student_profiles WHERE user_id = " . intval($user['id']) . " LIMIT 1";
                    $student_res = mysqli_query($conn, $student_sql);
                    if ($student_res && $student_row = mysqli_fetch_assoc($student_res)) {
                        if (empty($phone)) {
                            $phone = $student_row['phone'];
                        }
                        if (empty($full_name)) {
                            $full_name = trim($student_row['full_name'] ?? '');
                        }
                    }
                }
                
                $_SESSION['reset_name'] = !empty($full_name) ? $full_name : 'User';
                $_SESSION['reset_phone'] = $phone;
                $_SESSION['reset_method'] = 'email';
                
                // Generate 6-digit OTP code
                $code = sprintf("%06d", mt_rand(100000, 999999));
                $email = $_SESSION['reset_email'];
                $hashed_code = hash('sha256', $code);
                
                // Clear any existing codes for this email to prevent spam/confusion
                $clear_sql = "DELETE FROM password_resets WHERE email = '" . mysqli_real_escape_string($conn, $email) . "'";
                mysqli_query($conn, $clear_sql);
                
                // Store expiry in PHP time format (10 minutes)
                $php_expires_at = date('Y-m-d H:i:s', time() + 10 * 60);
                
                // Insert new OTP (store the hashed code)
                $insert_sql = "INSERT INTO password_resets (email, code, send_method, expires_at) 
                               VALUES ('" . mysqli_real_escape_string($conn, $email) . "', '$hashed_code', 'email', '$php_expires_at')";
                
                if (mysqli_query($conn, $insert_sql)) {
                    $_SESSION['reset_otp'] = (string)$code;
                    $_SESSION['reset_otp_hash'] = $hashed_code;
                    $_SESSION['reset_otp_expires'] = time() + (10 * 60);
 
                    // Send OTP code via email
                    $subject = "IMP Password Recovery Verification Code";
                    $body = "Hello " . htmlspecialchars($_SESSION['reset_name']) . ",\n\n" .
                            "You requested to recover your password. Please use the following 6-digit verification code:\n\n" .
                            "**" . $code . "**\n\n" .
                            "This verification code will expire in 10 minutes.\n\n" .
                            "If you did not request a password recovery, please ignore this email.";
                    
                    $email_sent = sendEmailNotification($email, $subject, $body, [
                        'event' => 'Password Recovery OTP',
                        'email_address' => $email,
                        'delivery_method' => 'Email Notification',
                        'code_validity' => '10 Minutes',
                        'action_url' => 'http://localhost/IMP/forgot_password.php',
                        'action_label' => 'Enter Reset Code',
                        'recipient_name' => $_SESSION['reset_name']
                    ]);
                    
                    $_SESSION['reset_step'] = 3;
                    session_write_close();
                    if ($email_sent) {
                        header("Location: forgot_password.php?info=" . urlencode("Verification code sent to your email."));
                    } else {
                        header("Location: forgot_password.php?info=" . urlencode("Failed to send verification email. Checking log fallback for the verification code."));
                    }
                    exit();
                } else {
                    $error_msg = "Database error. Failed to generate verification code: " . mysqli_error($conn);
                }
            } else {
                $error_msg = "No account found with this email address or phone number.";
            }
        }
    } 
    
    elseif ($action === 'step2_select_method') {
        $method = $_POST['method'] ?? '';
        if ($method !== 'email' && $method !== 'phone') {
            $error_msg = "Please select a recovery method.";
        } else {
            // Check if phone was selected but doesn't exist
            if ($method === 'phone' && empty($_SESSION['reset_phone'])) {
                $error_msg = "No phone number is registered for this account.";
            } else {
                $_SESSION['reset_method'] = $method;
                
                // Generate 6-digit OTP code
                $code = sprintf("%06d", mt_rand(100000, 999999));
                $email = $_SESSION['reset_email'];
                $hashed_code = hash('sha256', $code);
                
                // Clear any existing codes for this email to prevent spam/confusion
                $clear_sql = "DELETE FROM password_resets WHERE email = '" . mysqli_real_escape_string($conn, $email) . "'";
                mysqli_query($conn, $clear_sql);
                
                // Store expiry in PHP time format (10 minutes)
                $php_expires_at = date('Y-m-d H:i:s', time() + 10 * 60);
                
                // Insert new OTP (store the hashed code)
                $insert_sql = "INSERT INTO password_resets (email, code, send_method, expires_at) 
                               VALUES ('" . mysqli_real_escape_string($conn, $email) . "', '$hashed_code', '$method', '$php_expires_at')";
                
                if (mysqli_query($conn, $insert_sql)) {
                    $_SESSION['reset_otp'] = (string)$code;
                    $_SESSION['reset_otp_hash'] = $hashed_code;
                    $_SESSION['reset_otp_expires'] = time() + (10 * 60);
 
                    if ($method === 'email') {
                        // Send OTP code via email
                        $subject = "IMP Password Recovery Verification Code";
                        $body = "Hello " . htmlspecialchars($_SESSION['reset_name']) . ",\n\n" .
                                "You requested to recover your password. Please use the following 6-digit verification code:\n\n" .
                                "**" . $code . "**\n\n" .
                                "This verification code will expire in 10 minutes.\n\n" .
                                "If you did not request a password recovery, please ignore this email.";
                        
                        $email_sent = sendEmailNotification($email, $subject, $body, [
                            'event' => 'Password Recovery OTP',
                            'email_address' => $email,
                            'delivery_method' => 'Email Notification',
                            'code_validity' => '10 Minutes',
                            'action_url' => 'http://localhost/IMP/forgot_password.php',
                            'action_label' => 'Enter Reset Code',
                            'recipient_name' => $_SESSION['reset_name']
                        ]);
                        
                        $_SESSION['reset_step'] = 3;
                        session_write_close();
                        if ($email_sent) {
                            header("Location: forgot_password.php?info=" . urlencode("Verification code has been sent to your email."));
                        } else {
                            header("Location: forgot_password.php?info=" . urlencode("Failed to send verification email. Checking log fallback for the verification code."));
                        }
                        exit();
                    } else {
                        // Send OTP code via SMS gateway
                        $sms_msg = "Hello " . $_SESSION['reset_name'] . ", your IMP verification code is: $code. Valid for 10 mins.";
                        $sms_sent = sendSMS($_SESSION['reset_phone'], $sms_msg);
                        
                        $_SESSION['reset_step'] = 3;
                        session_write_close();
                        if ($sms_sent) {
                            header("Location: forgot_password.php?info=" . urlencode("Verification code has been sent to your phone."));
                        } else {
                            header("Location: forgot_password.php?info=" . urlencode("Failed to send SMS. Checking log fallback for the verification code."));
                        }
                        exit();
                    }
                } else {
                    $error_msg = "Database error. Failed to generate verification code: " . mysqli_error($conn);
                }
            }
        }
    } 
    
    elseif ($action === 'step3_verify_otp') {
        // Retrieve entered code strictly as string and remove whitespace
        $code = isset($_POST['code']) ? trim((string)$_POST['code']) : '';
        $code = preg_replace('/\s+/', '', $code);
        $email = $_SESSION['reset_email'] ?? '';
        
        if (empty($code)) {
            $error_msg = "Please enter the verification code.";
        } else {
            $user_code_hash = hash('sha256', $code);
            $esc_email = mysqli_real_escape_string($conn, $email);
            
            // Check if OTP matches and is not expired in database (using timezone-independent php comparison)
            $sql = "SELECT code, expires_at FROM password_resets 
                    WHERE email = '$esc_email' 
                    ORDER BY created_at DESC LIMIT 1";
            $res = mysqli_query($conn, $sql);
            
            $db_valid = false;
            if ($res && $row = mysqli_fetch_assoc($res)) {
                $db_code_hash = $row['code'];
                if (hash_equals($db_code_hash, $user_code_hash) && time() < strtotime($row['expires_at'])) {
                    $db_valid = true;
                }
            }
            
            // Check if OTP matches and is not expired in session
            $session_otp = $_SESSION['reset_otp'] ?? '';
            $session_expires = $_SESSION['reset_otp_expires'] ?? 0;
            $session_valid = (
                is_string($session_otp) && 
                trim($session_otp) !== '' && 
                trim($session_otp) === $code && 
                time() < $session_expires
            );
            
            if ($db_valid && $session_valid) {
                $_SESSION['reset_verified'] = true;
                $_SESSION['reset_step'] = 4;
                session_write_close();
                header("Location: forgot_password.php");
                exit();
            } else {
                $error_msg = "Invalid or expired verification code. Please check and try again.";
            }
        }
    } 
    
    elseif ($action === 'step4_reset_password') {
        // Enforce strict check to prevent step bypass/exploit
        if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true || empty($_SESSION['reset_email'])) {
            $_SESSION['reset_step'] = 3;
            session_write_close();
            header("Location: forgot_password.php");
            exit();
        }

        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $email = $_SESSION['reset_email'];
        
        $password_validation = validate_password_strength($password);
        if (!$password_validation['is_valid']) {
            $error_msg = implode(' ', $password_validation['errors']);
        } elseif ($password !== $confirm_password) {
            $error_msg = "Passwords do not match.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $esc_email = mysqli_real_escape_string($conn, $email);
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            // Update password in users table
            $update_sql = "UPDATE users SET password = '$hashed_password' WHERE email = '$esc_email'";
            $upd_res = mysqli_query($conn, $update_sql);
            
            // Delete OTP record
            $delete_sql = "DELETE FROM password_resets WHERE email = '$esc_email'";
            $del_res = mysqli_query($conn, $delete_sql);
            
            if ($upd_res && $del_res) {
                mysqli_commit($conn);
                
                // Clear reset session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_phone']);
                unset($_SESSION['reset_name']);
                unset($_SESSION['reset_role']);
                unset($_SESSION['reset_step']);
                unset($_SESSION['reset_method']);
                unset($_SESSION['reset_verified']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_otp_hash']);
                unset($_SESSION['reset_otp_expires']);
                
                session_write_close();
                header("Location: login.php?success=" . urlencode("Password reset successful! You can now log in with your new password."));
                exit();
            } else {
                mysqli_rollback($conn);
                $error_msg = "Failed to update password. Please try again.";
            }
        }
    }
}

// Fetch helper alerts from query string
if (isset($_GET['info'])) {
    $info_msg = htmlspecialchars(urldecode($_GET['info']));
}

$step = $_SESSION['reset_step'];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Recover Password | IMP</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet">
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
            "fontFamily": {
                "body-lg": ["Inter"],
                "label-md": ["Inter"],
                "body-md": ["Inter"],
                "h1": ["Inter"],
                "label-sm": ["Inter"],
                "h3": ["Inter"],
                "h2": ["Inter"]
            }
          }
        }
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
        @keyframes shake {
            0%,100%{transform:translateX(0)}
            20%{transform:translateX(-6px)}
            40%{transform:translateX(6px)}
            60%{transform:translateX(-4px)}
            80%{transform:translateX(4px)}
        }
        .animate-shake { animation: shake .4s ease; }
    </style>
</head>
<body class="bg-background text-on-background min-h-screen flex flex-col">
    <!-- Header -->
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
            <a href="login.php" class="text-blue-600 hover:text-blue-800 font-label-md text-label-md flex items-center gap-1 transition-colors">
                <span class="material-symbols-outlined text-lg">arrow_back</span> Back to Login
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-grow flex items-center justify-center p-6 auth-background">
        <div class="w-full max-w-xl">
            <div class="bg-surface-container-lowest rounded-xl shadow-[0px_1px_3px_rgba(0,0,0,0.1),0px_1px_2px_rgba(0,0,0,0.06)] p-8 md:p-10 border border-outline-variant">
                
                <!-- Step Indicator Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">
                        <span>Password Recovery</span>
                        <span>Step <?php echo $step; ?> of 4</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden flex">
                        <div class="h-full bg-blue-600 rounded-full transition-all duration-300" style="width: <?php echo ($step * 25); ?>%;"></div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($error_msg): ?>
                    <div class="flex items-center gap-3 p-3.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium mb-5 animate-shake alert-danger">
                        <span class="material-symbols-outlined text-red-500 text-[20px] flex-shrink-0">error</span>
                        <span><?php echo $error_msg; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($info_msg): ?>
                    <div class="flex items-center gap-3 p-3.5 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 font-medium mb-5 alert-info">
                        <span class="material-symbols-outlined text-blue-500 text-[20px] flex-shrink-0">info</span>
                        <span><?php echo $info_msg; ?></span>
                    </div>
                <?php endif; ?>

                <!-- STEP 1: Account Lookup -->
                <?php if ($step === 1): ?>
                    <div class="text-center mb-6">
                        <h2 class="font-h2 text-h2 text-on-background">Forgot Password?</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-2">Enter your email address or phone number to recover your account.</p>
                    </div>

                    <form action="forgot_password.php" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="step1_lookup">
                        <div>
                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Email Address or Phone Number</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">person</span>
                                <input name="identity" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-outline outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-body-md text-body-md" placeholder="Enter your email or phone number" type="text" value="<?php echo htmlspecialchars($_POST['identity'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="w-full py-3 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                            <span>Find Account</span>
                            <span class="material-symbols-outlined text-lg">search</span>
                        </button>
                    </form>


                <!-- STEP 2: Choose Recovery Option -->
                <?php elseif ($step === 2): ?>
                    <div class="text-center mb-6">
                        <h2 class="font-h2 text-h2 text-on-background">Verification Code</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-2">Choose how you want to receive your security code.</p>
                    </div>

                    <form action="forgot_password.php" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="step2_select_method">
                        
                        <div class="space-y-4">
                            <!-- Email Card -->
                            <label class="relative flex items-start p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50/50 transition-colors">
                                <div class="flex items-center h-5">
                                    <input name="method" type="radio" value="email" checked class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500">
                                </div>
                                <div class="ml-3 flex items-center gap-3">
                                    <span class="material-symbols-outlined p-2.5 bg-blue-50 text-blue-600 rounded-full shrink-0">mail</span>
                                    <div>
                                        <p class="font-label-md text-label-md text-slate-800">Verify by Email</p>
                                        <p class="font-body-md text-body-md text-slate-500"><?php echo maskEmail($_SESSION['reset_email']); ?></p>
                                    </div>
                                </div>
                            </label>

                            <!-- Phone Card -->
                            <?php if (!empty($_SESSION['reset_phone'])): ?>
                                <label class="relative flex items-start p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50/50 transition-colors">
                                    <div class="flex items-center h-5">
                                        <input name="method" type="radio" value="phone" class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3 flex items-center gap-3">
                                        <span class="material-symbols-outlined p-2.5 bg-green-50 text-green-600 rounded-full shrink-0">call</span>
                                        <div>
                                            <p class="font-label-md text-label-md text-slate-800">Verify by Phone (SMS)</p>
                                            <p class="font-body-md text-body-md text-slate-500"><?php echo maskPhone($_SESSION['reset_phone']); ?></p>
                                        </div>
                                    </div>
                                </label>
                            <?php else: ?>
                                <div class="relative flex items-start p-4 border border-slate-100 bg-slate-50/50 rounded-xl opacity-60">
                                    <div class="flex items-center h-5">
                                        <input name="method" type="radio" disabled class="h-4 w-4 border-slate-200 text-slate-400">
                                    </div>
                                    <div class="ml-3 flex items-center gap-3">
                                        <span class="material-symbols-outlined p-2.5 bg-slate-100 text-slate-400 rounded-full shrink-0">call</span>
                                        <div>
                                            <p class="font-label-md text-label-md text-slate-400">Verify by Phone (SMS)</p>
                                            <p class="font-body-md text-body-md text-slate-400 italic">No phone number registered.</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <a href="forgot_password.php?action=restart" class="w-1/3 py-3 border border-slate-200 hover:bg-slate-50 text-slate-600 font-label-md text-label-md rounded-lg transition-colors text-center">
                                Back
                            </a>
                            <button type="submit" class="w-2/3 py-3 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                                <span>Send Security Code</span>
                                <span class="material-symbols-outlined text-lg">send</span>
                            </button>
                        </div>
                    </form>


                <!-- STEP 3: Verification Code Input -->
                <?php elseif ($step === 3): ?>
                    <div class="text-center mb-6">
                        <h2 class="font-h2 text-h2 text-on-background">Enter Security Code</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-2">
                            We sent a 6-digit code to your 
                            <strong><?php echo $_SESSION['reset_method'] === 'email' ? 'email address' : 'phone number'; ?></strong>:
                            <br>
                            <span class="text-slate-700 font-semibold mt-1 inline-block">
                                <?php echo $_SESSION['reset_method'] === 'email' ? maskEmail($_SESSION['reset_email']) : maskPhone($_SESSION['reset_phone']); ?>
                            </span>
                        </p>
                    </div>


                    <form action="forgot_password.php" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="step3_verify_otp">
                        <div>
                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">6-Digit Code</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">sms_failed</span>
                                <input name="code" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-outline outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all tracking-widest text-center font-bold text-lg" placeholder="000000" type="text" pattern="\d{6}" maxlength="6" required autocomplete="off">
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4 pt-2">
                            <a href="forgot_password.php?action=restart" class="w-1/3 py-3 border border-slate-200 hover:bg-slate-50 text-slate-600 font-label-md text-label-md rounded-lg transition-colors text-center">
                                Cancel
                            </a>
                            <button type="submit" class="w-2/3 py-3 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                                <span>Verify Code</span>
                                <span class="material-symbols-outlined text-lg">verified_user</span>
                            </button>
                        </div>
                    </form>


                <!-- STEP 4: Reset Password -->
                <?php elseif ($step === 4): ?>
                    <div class="text-center mb-6">
                        <h2 class="font-h2 text-h2 text-on-background">Create New Password</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-2">Set a strong, new password for your account.</p>
                    </div>

                    <form action="forgot_password.php" method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="step4_reset_password">
                        
                        <div>
                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">New Password</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">lock</span>
                                <input id="new-password" name="password" class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-outline outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-body-md text-body-md" placeholder="••••••••" type="password" required minlength="6">
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline text-lg cursor-pointer" id="toggle-pwd-1">visibility</span>
                            </div>
                        </div>

                        <div>
                            <label class="block font-label-md text-label-md text-on-surface-variant mb-1.5">Confirm New Password</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">lock</span>
                                <input id="confirm-password" name="confirm_password" class="w-full pl-10 pr-10 py-2.5 rounded-lg border border-outline outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-body-md text-body-md" placeholder="••••••••" type="password" required>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline text-lg cursor-pointer" id="toggle-pwd-2">visibility</span>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-colors flex items-center justify-center gap-2">
                            <span>Reset Password</span>
                            <span class="material-symbols-outlined text-lg">check_circle</span>
                        </button>
                    </form>
                <?php endif; ?>

            </div>
            
            <p class="text-center font-body-md text-body-md text-on-surface-variant mt-8">
                Remember your password? <a class="text-primary font-label-md hover:underline" href="login.php">Sign In</a>
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full py-6 px-6 border-t border-gray-200 bg-white">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-6">
                <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Privacy Policy</a>
                <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Terms of Service</a>
                <a class="font-label-sm text-label-sm text-on-surface-variant hover:text-primary transition-colors" href="#">Contact Support</a>
            </div>
            <p class="font-label-sm text-label-sm text-gray-400">© 2026 InternshipHub. All rights reserved.</p>
        </div>
    </footer>

    <!-- Password visibility toggle script -->
    <script>
        function setupVisibilityToggle(inputId, toggleId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(toggleId);
            if (input && toggle) {
                toggle.addEventListener('click', function() {
                    if (input.type === 'password') {
                        input.type = 'text';
                        toggle.textContent = 'visibility_off';
                    } else {
                        input.type = 'password';
                        toggle.textContent = 'visibility';
                    }
                });
            }
        }
        setupVisibilityToggle('new-password', 'toggle-pwd-1');
        setupVisibilityToggle('confirm-password', 'toggle-pwd-2');

        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const resetPasswordForm = document.querySelector('form[action="forgot_password.php"]');
        if (resetPasswordForm && newPasswordInput && confirmPasswordInput) {
            resetPasswordForm.addEventListener('submit', function (e) {
                const requirements = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;
                if (!requirements.test(newPasswordInput.value)) {
                    e.preventDefault();
                    newPasswordInput.setCustomValidity('Password must contain at least 8 characters, one uppercase letter, one lowercase letter, one number, and one special character.');
                    newPasswordInput.reportValidity();
                    return false;
                }
                if (newPasswordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    confirmPasswordInput.setCustomValidity('Passwords do not match.');
                    confirmPasswordInput.reportValidity();
                    return false;
                }
                newPasswordInput.setCustomValidity('');
                confirmPasswordInput.setCustomValidity('');
            });
        }
    </script>
<script src="js/alerts.js"></script>
</body>
</html>
