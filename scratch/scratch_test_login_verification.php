<?php
/**
 * Programmatic login validation test.
 * Simulates login processing logic from login.php.
 */

include 'db.php';

echo "=== Running Login Logic Tests ===\n\n";

// Helper function to simulate core login validation from login.php
function simulate_login($conn, $email, $password) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // 2. Check password hashing
        $pass_ok = false;
        $need_rehash = false;
        if (!empty($user['password'])) {
            $info = password_get_info($user['password']);
            $is_hashed = (strpos($user['password'], '$2y$') === 0 || strpos($user['password'], '$2a$') === 0 || strpos($user['password'], '$2b$') === 0 || (isset($info['algo']) && $info['algo'] !== 0 && $info['algo'] !== null));
            if ($is_hashed) {
                if (password_verify($password, $user['password'])) {
                    $pass_ok = true;
                }
            } else {
                if ($password === $user['password']) {
                    $pass_ok = true;
                    $need_rehash = true;
                }
            }
        }

        if ($pass_ok) {
            $user_role_lc   = strtolower(trim($user['role'] ?? ''));
            $user_status    = strtolower(trim($user['status'] ?? ''));
            $user_is_active = isset($user['is_active']) ? intval($user['is_active']) : 1;

            // ── Pending Approval Check ──
            $approval_roles = ['hr', 'coordinator', 'mentor', 'company'];
            if (in_array($user_role_lc, $approval_roles, true)) {
                if ($user_status === 'pending_approval' || $user_is_active === 0) {
                    return ["status" => "blocked", "message" => "Your account is pending admin approval. You will receive an email once approved."];
                }
                if ($user_status === 'rejected') {
                    return ["status" => "blocked", "message" => "Your registration was not approved. Please contact the administrator."];
                }
            }

            // ── General Status Block & Inactive Check ──
            $is_active_flag = true;
            if (isset($user['is_active']) && (intval($user['is_active']) === 0 || $user['is_active'] === false)) {
                $is_active_flag = false;
            }
            if (isset($user['status'])) {
                $status_check = strtolower(trim($user['status']));
                $inactive_statuses = ['inactive', 'deactivated', 'disabled', 'blocked'];
                if (in_array($status_check, $inactive_statuses, true)) {
                    $is_active_flag = false;
                }
            }
            if (isset($user['account_status'])) {
                $acc_status_check = strtolower(trim($user['account_status']));
                $inactive_statuses = ['inactive', 'deactivated', 'disabled', 'blocked'];
                if (in_array($acc_status_check, $inactive_statuses, true)) {
                    $is_active_flag = false;
                }
            }

            if (!$is_active_flag) {
                return ["status" => "inactive", "message" => "Your account is inactive. Please contact admin."];
            }

            $role = strtolower(trim($user['role'] ?? ''));
            return [
                "status" => "success",
                "message" => "Logged in successfully",
                "session" => [
                    "user_id" => $user['id'],
                    "email" => $user['email'],
                    "role" => $role,
                    "name" => $user['full_name']
                ]
            ];
        } else {
            return ["status" => "failed", "message" => "Invalid email or password"];
        }
    } else {
        return ["status" => "failed", "message" => "Invalid email or password"];
    }
}

// 1. Setup test users
$pass_hashed = password_hash('bcrypt_password', PASSWORD_DEFAULT);

// Clean previous runs
mysqli_query($conn, "DELETE FROM users WHERE email LIKE 'test_%@example.com'");

// Create test cases
mysqli_query($conn, "INSERT INTO users (full_name, email, password, role, status, is_active, approval_status) VALUES ('Test Active Student', 'test_active_student@example.com', '$pass_hashed', 'student', 'approved', 1, 'approved')");
mysqli_query($conn, "INSERT INTO users (full_name, email, password, role, status, is_active, approval_status) VALUES ('Test Inactive Status Student', 'test_inactive_student@example.com', '$pass_hashed', 'student', 'inactive', 1, 'approved')");
mysqli_query($conn, "INSERT INTO users (full_name, email, password, role, status, is_active, approval_status) VALUES ('Test Inactive Bool Student', 'test_inactive_bool_student@example.com', '$pass_hashed', 'student', 'approved', 0, 'approved')");
mysqli_query($conn, "INSERT INTO users (full_name, email, password, role, status, is_active, approval_status) VALUES ('Test Plain Student', 'test_plain_student@example.com', 'plain_password', 'student', 'approved', 1, 'approved')");

// Run tests
$tests = [
    [
        "desc" => "Test case 1: Successful login for active student with hashed password",
        "email" => "test_active_student@example.com",
        "password" => "bcrypt_password",
        "expected_status" => "success"
    ],
    [
        "desc" => "Test case 2: Failed login for active student with wrong password",
        "email" => "test_active_student@example.com",
        "password" => "wrong_password",
        "expected_status" => "failed"
    ],
    [
        "desc" => "Test case 3: Blocked login for inactive status student",
        "email" => "test_inactive_student@example.com",
        "password" => "bcrypt_password",
        "expected_status" => "inactive",
        "expected_msg" => "Your account is inactive. Please contact admin."
    ],
    [
        "desc" => "Test case 4: Blocked login for is_active = 0 student",
        "email" => "test_inactive_bool_student@example.com",
        "password" => "bcrypt_password",
        "expected_status" => "inactive",
        "expected_msg" => "Your account is inactive. Please contact admin."
    ],
    [
        "desc" => "Test case 5: Successful login for plain-text password student",
        "email" => "test_plain_student@example.com",
        "password" => "plain_password",
        "expected_status" => "success"
    ]
];

$all_passed = true;
foreach ($tests as $t) {
    echo "Running: " . $t['desc'] . "\n";
    $res = simulate_login($conn, $t['email'], $t['password']);
    echo "  Result Status: " . $res['status'] . "\n";
    echo "  Result Message: " . $res['message'] . "\n";
    
    $ok = ($res['status'] === $t['expected_status']);
    if (isset($t['expected_msg'])) {
        $ok = $ok && ($res['message'] === $t['expected_msg']);
    }
    
    if ($ok) {
        echo "  [PASSED]\n";
    } else {
        echo "  [FAILED]\n";
        $all_passed = false;
    }
    echo "\n";
}

// Clean up
mysqli_query($conn, "DELETE FROM users WHERE email LIKE 'test_%@example.com'");

if ($all_passed) {
    echo "=== ALL TESTS PASSED SUCCESSFULLY ===\n";
    exit(0);
} else {
    echo "=== SOME TESTS FAILED ===\n";
    exit(1);
}
