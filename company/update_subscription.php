<?php
ob_start();
session_start();
include __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth.php';
include_once __DIR__ . '/../includes/mail_helper.php';

// Enforce login as company
require_role('company');

$company_id = current_user_id();
$recruiter_name = $_SESSION['full_name'] ?? 'Recruiter';
$recruiter_email = $_SESSION['email'] ?? '';

// Fetch company profile details for company_name
$company_title = 'Nexus Tech';
$q_prof = mysqli_query($conn, "SELECT company_name, plan_selected FROM company_profiles WHERE user_id = $company_id LIMIT 1");
if ($q_prof && $row = mysqli_fetch_assoc($q_prof)) {
    $company_title = $row['company_name'];
    $plan_selected = $row['plan_selected'];
}

// Handle plan activation/upgrade request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_plan = trim($_POST['plan'] ?? '');
    
    // Convert to proper casing
    $new_plan = ucfirst(strtolower($new_plan));
    
    if (!in_array($new_plan, ['Free', 'Basic', 'Premium'], true)) {
        $_SESSION['subscription_error'] = 'Invalid subscription plan choice.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $stmt = $conn->prepare("UPDATE company_profiles SET plan_selected = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_plan, $company_id);
            $stmt->execute();
            $stmt->close();

            // Insert notification
            $notif_title = "Subscription Plan Activated";
            $notif_message = "Your account has been successfully switched to the " . strtoupper($new_plan) . " Plan.";
            $stmt_notif = $conn->prepare("INSERT INTO company_notifications (company_id, type, title, message) VALUES (?, 'success', ?, ?)");
            if ($stmt_notif) {
                $stmt_notif->bind_param("iss", $company_id, $notif_title, $notif_message);
                $stmt_notif->execute();
                $stmt_notif->close();
            }

            // Log activity
            log_activity($conn, 'Subscription Change', "Company \"$company_title\" updated plan from \"" . ($plan_selected ?: 'None') . "\" to \"$new_plan\".");

            mysqli_commit($conn);
            
            // Trigger email notification (outside transactional block to avoid SMTP connection blocking)
            if (function_exists('sendEmailNotification')) {
                $email_subject = "IMP Subscription Activated: " . $new_plan . " Plan";
                $email_body = "Dear $recruiter_name,\n\nYour subscription to the $new_plan Plan has been successfully activated for $company_title on the Internship Management Platform (IMP).\n\n" . 
                              ($new_plan === 'Free' ? "You can now view details of up to 10 verified candidates." : 
                              ($new_plan === 'Basic' ? "You can now view details of up to 75 candidates and utilize advanced filters." : 
                              "You now have unlimited candidate access, advanced stack filters, and direct contact options."));
                sendEmailNotification($recruiter_email, $email_subject, $email_body, [
                    'event' => 'Subscription Update',
                    'company_name' => $company_title,
                    'new_plan' => $new_plan,
                    'status' => 'Active',
                    'action_url' => 'http://localhost/IMP/browse_talent_pool.php',
                    'action_label' => 'Explore Talent Pool'
                ]);
            }
            
            $_SESSION['subscription_success'] = "Your subscription has been successfully updated to the $new_plan Plan!";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['subscription_error'] = "Failed to update subscription: " . $e->getMessage();
        }
    }
}

// Redirect back to subscription page
header("Location: subscription.php");
exit();
?>
