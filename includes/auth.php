<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user_role(): ?string {
    return isset($_SESSION['role']) ? strtolower($_SESSION['role']) : null;
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function has_role($allowed_roles): bool {
    $allowed = array_map('strtolower', (array) $allowed_roles);
    $role = current_user_role();
    return $role !== null && in_array($role, $allowed, true);
}

function role_default_modules(?string $role): array {
    switch (strtolower((string) $role)) {
        case 'admin':
            return ['dashboard', 'applications', 'candidates', 'workflows', 'reports', 'users', 'hiring_requests', 'student_logs'];
        case 'hr':
            return ['dashboard', 'applications', 'candidates', 'workflows', 'reports', 'hiring_requests', 'student_logs'];
        case 'recruiter':
            return ['dashboard', 'postings', 'applications', 'candidates'];
        case 'mentor':
            return ['mentor_dashboard'];
        default:
            return [];
    }
}

function current_user_permissions(): array {
    if (empty($_SESSION['permissions'])) {
        return [];
    }
    $raw = is_array($_SESSION['permissions'])
        ? $_SESSION['permissions']
        : preg_split('/[\s,]+/', (string) $_SESSION['permissions']);
    return array_values(array_filter(array_map(fn($item) => strtolower(trim($item)), $raw)));
}

function can_access_module(string $module): bool {
    $module = strtolower($module);
    $role = current_user_role();
    if ($role === 'admin') {
        return true;
    }
    $allowed = role_default_modules($role);
    $custom = current_user_permissions();
    return in_array($module, array_unique(array_merge($allowed, $custom)), true);
}

function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        header("Location: {$redirect}?error=" . urlencode("Please log in to continue."));
        exit();
    }
}

function require_role($allowed_roles, string $redirect = 'login.php', string $message = 'Unauthorized access.'): void {
    require_login($redirect);
    if (!has_role($allowed_roles)) {
        header("Location: {$redirect}?error=" . urlencode($message));
        exit();
    }
}

function require_hr_or_admin(string $redirect = 'login.php'): void {
    require_role(['hr', 'admin'], $redirect, 'Unauthorized access. HR or Admin role required.');
}

function require_module_access(string $module, string $redirect = 'login.php'): void {
    require_login($redirect);
    if (!can_access_module($module)) {
        header("Location: {$redirect}?error=" . urlencode("Unauthorized access. You do not have permission for {$module}."));
        exit();
    }
}

function require_ajax_role($allowed_roles): void {
    if (!is_logged_in() || !has_role($allowed_roles)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit();
    }
}

function require_ajax_module_access(string $module): void {
    if (!is_logged_in() || !can_access_module($module)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit();
    }
}

function log_activity(mysqli $conn, string $action_type, string $details): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $user_name = $_SESSION['full_name'] ?? 'System/Guest';
    $user_role = $_SESSION['role'] ?? 'guest';
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, user_role, action_type, details) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $user_name, $user_role, $action_type, $details);
        return $stmt->execute();
    }
    return false;
}

function check_rate_limit(string $action, int $limit_requests = 15, int $time_window_seconds = 60): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $now = time();
    $key = "rate_limit_" . $action;
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    // Filter timestamps inside window
    $_SESSION[$key] = array_filter($_SESSION[$key], function($ts) use ($now, $time_window_seconds) {
        return $ts > ($now - $time_window_seconds);
    });
    if (count($_SESSION[$key]) >= $limit_requests) {
        return false;
    }
    $_SESSION[$key][] = $now;
    return true;
}

if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrf_token_field')) {
    function csrf_token_field(): string {
        $token = generate_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}



