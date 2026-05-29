<?php
ob_start();
session_start();
include 'db.php';
include_once __DIR__ . '/includes/auth.php';

// Enforce login as company
require_role('company');

$company_id = current_user_id();
$recruiter_name = $_SESSION['full_name'] ?? 'Recruiter';
$recruiter_email = $_SESSION['email'] ?? '';

// Fetch company profile details
$company_title = 'Nexus Tech';
$plan_selected = null;
$q_prof = mysqli_query($conn, "SELECT company_name, plan_selected FROM company_profiles WHERE user_id = $company_id LIMIT 1");
if ($q_prof && $row = mysqli_fetch_assoc($q_prof)) {
    $company_title = $row['company_name'];
    $plan_selected = $row['plan_selected'];
}

// Redirect if no plan selected
if (empty($plan_selected)) {
    header("Location: company_subscription.php");
    exit();
}

// ── AJAX Endpoint Handler ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $candidate_id = intval($_POST['candidate_id'] ?? 0);

    if ($candidate_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid candidate ID.']);
        exit();
    }

    // Fetch candidate name for logging
    $cand_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $cand_stmt->bind_param("i", $candidate_id);
    $cand_stmt->execute();
    $cand_row = $cand_stmt->get_result()->fetch_assoc();
    $candidate_name = $cand_row ? $cand_row['full_name'] : 'Candidate';

    if ($action === 'toggle_shortlist') {
        $check = mysqli_query($conn, "SELECT id FROM company_shortlists WHERE company_id = $company_id AND candidate_id = $candidate_id");
        if ($check && mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "DELETE FROM company_shortlists WHERE company_id = $company_id AND candidate_id = $candidate_id");
            log_activity($conn, 'Shortlist Remove', "Company \"$company_title\" removed $candidate_name from shortlist.");
            echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from shortlist.']);
        } else {
            mysqli_query($conn, "INSERT INTO company_shortlists (company_id, candidate_id) VALUES ($company_id, $candidate_id)");
            log_activity($conn, 'Shortlist Add', "Company \"$company_title\" shortlisted $candidate_name.");
            echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to shortlist.']);
        }
        exit();
    }

    if ($action === 'contact_candidate') {
        $message = $_POST['message'] ?? 'We are interested in your profile. Please get in touch.';
        $message_db = mysqli_real_escape_string($conn, $message);
        mysqli_query($conn, "INSERT INTO company_contacts (company_id, candidate_id, message) VALUES ($company_id, $candidate_id, '$message_db') ON DUPLICATE KEY UPDATE message = '$message_db'");
        log_activity($conn, 'Candidate Contact', "Company \"$company_title\" contacted $candidate_name with message: " . $message);
        
        // 1. Insert DB Notification for Candidate
        $notif_title = 'Company Contact Interest';
        $notif_msg = "Company \"$company_title\" is interested in your profile. Message: \"$message\"";
        $stmt_notif = $conn->prepare("INSERT INTO student_notifications (user_id, type, title, message) VALUES (?, 'info', ?, ?)");
        $stmt_notif->bind_param("iss", $candidate_id, $notif_title, $notif_msg);
        $stmt_notif->execute();
        $stmt_notif->close();

        // 2. Trigger Email Notification to Candidate
        include_once __DIR__ . '/includes/mail_helper.php';
        $email_subject = "IMP: Company $company_title is interested in your profile!";
        $email_body = "Dear $candidate_name,\n\nCompany \"$company_title\" has viewed your internship accomplishments and is interested in contacting you for placement opportunities.\n\nMessage from recruiter:\n\"$message\"\n\nPlease log in to your student dashboard to review details and check any further actions.";
        sendEmailNotification($candidate_id, $email_subject, $email_body, [
            'event' => 'Company Contact',
            'company_name' => $company_title,
            'action_url' => 'http://localhost/IMP/student_dashboard.php',
            'action_label' => 'Go to Student Dashboard'
        ]);

        echo json_encode(['success' => true, 'message' => 'Invitation sent successfully.']);
        exit();
    }

    if ($action === 'get_details') {
        // Fetch candidate details
        $stmt = $conn->prepare("
            SELECT c.*, sp.course, sp.year_of_study AS sp_year, a.test_score, a.test_status, a.test_submitted_date, COALESCE(jp.title, a.internship_name) AS project_title, a.reason_for_applying
            FROM candidates c
            LEFT JOIN student_profiles sp ON c.user_id = sp.user_id
            LEFT JOIN internship_applications a ON c.latest_application_id = a.id
            LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
            WHERE c.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $candidate_id);
        $stmt->execute();
        $candidate = $stmt->get_result()->fetch_assoc();

        if (!$candidate) {
            echo json_encode(['success' => false, 'message' => 'Candidate not found.']);
            exit();
        }

        // Enforce Subscription Limits on Profile Views
        $plan_selected = 'Free';
        $q_prof = mysqli_query($conn, "SELECT plan_selected FROM company_profiles WHERE user_id = $company_id LIMIT 1");
        if ($q_prof && $row = mysqli_fetch_assoc($q_prof)) {
            $plan_selected = $row['plan_selected'] ?: 'Free';
        }

        $view_limit = 10;
        if ($plan_selected === 'Basic') {
            $view_limit = 75;
        } elseif ($plan_selected === 'Premium') {
            $view_limit = 999999;
        }

        $cand_user_id = intval($candidate['user_id']);

        // Check if already viewed
        $check_view = $conn->prepare("SELECT id FROM company_views WHERE company_id = ? AND candidate_id = ? LIMIT 1");
        $check_view->bind_param("ii", $company_id, $cand_user_id);
        $check_view->execute();
        $has_viewed = ($check_view->get_result()->num_rows > 0);
        $check_view->close();

        if (!$has_viewed && $plan_selected !== 'Premium') {
            // Count existing viewed candidates
            $views_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM company_views WHERE company_id = ?");
            $views_stmt->bind_param("i", $company_id);
            $views_stmt->execute();
            $views_count = $views_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $views_stmt->close();

            if ($views_count >= $view_limit) {
                if ($plan_selected === 'Free') {
                    $_SESSION['upgrade_message'] = "You have reached the Free Plan limit of 10 profile views. Upgrade your subscription to continue accessing candidate profiles.";
                } else if ($plan_selected === 'Basic') {
                    $_SESSION['upgrade_message'] = "You have reached the Basic Plan limit of 75 profile views. Upgrade to Premium for unlimited access.";
                } else {
                    $_SESSION['upgrade_message'] = "You have reached the limit of profile views allowed on your plan. Please upgrade to continue.";
                }
                echo json_encode([
                    'success' => false,
                    'redirect' => 'company_subscription.php'
                ]);
                exit();
            }
        }

        // Record the view
        $ins_view = $conn->prepare("INSERT IGNORE INTO company_views (company_id, candidate_id) VALUES (?, ?)");
        $ins_view->bind_param("ii", $company_id, $cand_user_id);
        $ins_view->execute();
        $ins_view->close();

        // Fetch candidate's daily logs
        $logs = [];
        $cand_user_id = $candidate['user_id'];
        if ($cand_user_id) {
            $q_logs = mysqli_query($conn, "SELECT tasks_completed, time_spent, focus_level, log_date FROM daily_logs WHERE user_id = $cand_user_id ORDER BY log_date DESC LIMIT 5");
            if ($q_logs) {
                while ($l = mysqli_fetch_assoc($q_logs)) {
                    $logs[] = $l;
                }
            }
        }

        // Fetch candidate's assigned mentor
        $mentor = null;
        if ($cand_user_id) {
            $m_stmt = $conn->prepare("
                SELECT u.full_name AS mentor_name, u.email AS mentor_email, ma.assigned_at
                FROM mentor_assignments ma
                JOIN users u ON u.id = ma.mentor_id
                WHERE ma.student_id = ? AND ma.status = 'active'
                LIMIT 1
            ");
            $m_stmt->bind_param("i", $cand_user_id);
            $m_stmt->execute();
            $mentor = $m_stmt->get_result()->fetch_assoc();
            $m_stmt->close();
        }

        // Fetch candidate's mentor feedback/evaluations
        $mentor_feedback = [];
        if ($cand_user_id) {
            $fb_stmt = $conn->prepare("
                SELECT mf.feedback_title AS title, mf.comments, mf.rating, mf.status, mf.created_at, mf.given_by AS mentor_name
                FROM mentor_feedback mf
                WHERE mf.user_id = ?
                ORDER BY mf.created_at DESC
                LIMIT 5
            ");
            $fb_stmt->bind_param("i", $cand_user_id);
            $fb_stmt->execute();
            $fb_res = $fb_stmt->get_result();
            while ($fb_row = $fb_res->fetch_assoc()) {
                $mentor_feedback[] = $fb_row;
            }
            $fb_stmt->close();
        }

        echo json_encode([
            'success' => true,
            'candidate' => $candidate,
            'logs' => $logs,
            'mentor' => $mentor,
            'mentor_feedback' => $mentor_feedback
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

// ── GET: Filtering & Search ──
$search = trim($_GET['search'] ?? '');
$domain = trim($_GET['domain'] ?? '');
$stack = trim($_GET['stack'] ?? '');
$score = trim($_GET['score'] ?? '');
$certification = trim($_GET['certification'] ?? '');
$filter_shortlist = (trim($_GET['filter'] ?? '') === 'shortlist' || isset($_GET['only_shortlist']));

// Enforce plan restrictions on filtering
$filter_error_msg = '';
if ($plan_selected === 'Free') {
    if (!empty($stack) || ($score !== '' && $score !== 'Score')) {
        $stack = '';
        $score = '';
        $filter_error_msg = "Advanced filtering (Technology Stack, Performance Score) is restricted on the Free plan. Please upgrade your subscription.";
    }
} elseif ($plan_selected === 'Basic') {
    if (!empty($stack)) {
        $stack = '';
        $filter_error_msg = "Technology Stack filtering is a Premium tier feature. Please upgrade your subscription.";
    }
}

$where = ["c.current_status IN ('Test Completed', 'HR Round', 'HOD Approved', 'Selected')"];

if ($search !== '') {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where[] = "(c.full_name LIKE '%$search_safe%' OR c.skills LIKE '%$search_safe%' OR c.college LIKE '%$search_safe%')";
}

if ($domain !== '' && $domain !== 'Domain') {
    $domain_safe = mysqli_real_escape_string($conn, $domain);
    $where[] = "(a.preferred_domain LIKE '%$domain_safe%' OR a.department LIKE '%$domain_safe%' OR jp.department LIKE '%$domain_safe%')";
}

if ($stack !== '' && $stack !== 'Tech Stack') {
    if ($stack === 'MERN') {
        $where[] = "(c.skills LIKE '%React%' OR c.skills LIKE '%Node%' OR c.skills LIKE '%MongoDB%' OR c.skills LIKE '%Express%')";
    } elseif ($stack === 'Python / AI') {
        $where[] = "(c.skills LIKE '%Python%' OR c.skills LIKE '%AI%' OR c.skills LIKE '%ML%' OR c.skills LIKE '%TensorFlow%')";
    } elseif ($stack === 'Flutter') {
        $where[] = "(c.skills LIKE '%Flutter%' OR c.skills LIKE '%Dart%')";
    }
}

if ($score !== '' && $score !== 'Score') {
    if ($score === '90% +') {
        $where[] = "a.test_score >= 90";
    } elseif ($score === '80% +') {
        $where[] = "a.test_score >= 80";
    }
}

if ($certification !== '' && $certification !== 'Certification') {
    if ($certification === 'Certified') {
        $where[] = "c.current_status = 'Selected'";
    } elseif ($certification === 'Verified') {
        $where[] = "c.current_status = 'Test Completed'";
    }
}

if ($filter_shortlist) {
    $where[] = "c.user_id IN (SELECT candidate_id FROM company_shortlists WHERE company_id = $company_id)";
}

$where_sql = implode(' AND ', $where);

// Pagination
$limit = 9;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Count query
$count_query = "
    SELECT COUNT(DISTINCT c.id) AS total 
    FROM candidates c
    LEFT JOIN internship_applications a ON c.latest_application_id = a.id
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    WHERE $where_sql
";
$count_res = mysqli_query($conn, $count_query);
$total_rows = $count_res ? mysqli_fetch_assoc($count_res)['total'] : 0;
$total_pages = ceil($total_rows / $limit);

// Data query
$data_query = "
    SELECT c.*, a.test_score, COALESCE(jp.title, a.internship_name) AS project_title, a.relevant_skills, a.preferred_domain
    FROM candidates c
    LEFT JOIN internship_applications a ON c.latest_application_id = a.id
    LEFT JOIN job_postings jp ON a.job_posting_id = jp.id
    WHERE $where_sql
    ORDER BY a.test_score DESC, c.updated_at DESC
    LIMIT $limit OFFSET $offset
";
$candidates_res = mysqli_query($conn, $data_query);

$candidates = [];
if ($candidates_res) {
    while ($row = mysqli_fetch_assoc($candidates_res)) {
        // Check shortlist status
        $cand_user_id = $row['user_id'];
        $check_s = mysqli_query($conn, "SELECT id FROM company_shortlists WHERE company_id = $company_id AND candidate_id = $cand_user_id LIMIT 1");
        $row['is_shortlisted'] = ($check_s && mysqli_num_rows($check_s) > 0);

        // Check contact status
        $check_c = mysqli_query($conn, "SELECT id FROM company_contacts WHERE company_id = $company_id AND candidate_id = $cand_user_id LIMIT 1");
        $row['is_contacted'] = ($check_c && mysqli_num_rows($check_c) > 0);

        $candidates[] = $row;
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Browse Talent Pool | Company Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "#1d4ed8",
                        secondary: "#64748b",
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
        .sidebar-link { display: flex; items-center: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; transition: all 0.2s; font-size: 0.875rem; font-weight: 500; color: #64748b; }
        .sidebar-link:hover { background-color: #f1f5f9; color: #1d4ed8; }
        .sidebar-link.active { background-color: #1d4ed8; color: #ffffff; box-shadow: 0 4px 6px -1px rgb(29 78 216 / 0.1), 0 2px 4px -2px rgb(29 78 216 / 0.1); }
        .filter-select { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; border-color: #e2e8f0; border-radius: 0.75rem; padding: 0.5rem 2rem 0.5rem 1rem; cursor: pointer; }
    </style>
</head>
<body class="min-h-screen flex font-sans">

    <!-- Sidebar -->
    <aside class="w-64 bg-white border-r border-gray-200 p-6 flex flex-col fixed h-screen z-50">
        <div class="flex flex-col mb-10 px-2">
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
            <p class="text-[10px] text-blue-600 font-bold uppercase tracking-widest mt-2 ml-1">Recruitment Hub</p>
        </div>

        <nav class="flex-1 space-y-1">
            <a href="company_dashboard.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">dashboard</span>
                Hiring Overview
            </a>
            <a href="browse_talent_pool.php" class="sidebar-link active">
                <span class="material-symbols-outlined text-xl">person_search</span>
                Browse Talent Pool
            </a>
            <a href="hiring_requests.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">handshake</span>
                Hiring Requests
            </a>
            <a href="company_subscription.php" class="sidebar-link">
                <span class="material-symbols-outlined text-xl">payments</span>
                My Subscription
            </a>
        </nav>

        <div class="mt-auto pt-6 border-t border-gray-100">
            <a href="logout.php" class="sidebar-link text-red-600 hover:bg-red-50">
                <span class="material-symbols-outlined">logout</span>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="ml-64 flex-1 p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">Browse Talent Pool</h2>
                    <p class="text-gray-500 font-medium mt-1">Discover certified interns ready for industry roles.</p>
                </div>
                <div class="flex items-center gap-3">
                    <?php
                    // Count company views
                    $q_views = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM company_views WHERE company_id = $company_id");
                    $views_row = mysqli_fetch_assoc($q_views);
                    $views_count = intval($views_row['cnt'] ?? 0);

                    $max_views = 10;
                    if ($plan_selected === 'Basic') {
                        $max_views = 75;
                    } elseif ($plan_selected === 'Premium') {
                        $max_views = 'Unlimited';
                    }
                    ?>
                    <span class="bg-blue-50 text-blue-800 border border-blue-200 px-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-[16px] text-blue-600">workspace_premium</span>
                        Subscription: <strong class="uppercase"><?php echo htmlspecialchars($plan_selected); ?></strong> 
                        (<?php echo $views_count; ?>/<?php echo $max_views; ?> views)
                    </span>
                    <button onclick="window.location.href='browse_talent_pool.php?filter=shortlist'" class="bg-white border border-gray-200 text-gray-700 px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 hover:bg-gray-50 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-lg">fact_check</span> View Shortlisted (<?php echo $shortlist_count; ?>)
                    </button>
                </div>
            </div>

            <!-- Search & Filters Form -->
            <form method="GET" action="browse_talent_pool.php" class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-6">
                <?php if ($filter_shortlist): ?>
                    <input type="hidden" name="filter" value="shortlist">
                    <div class="flex items-center justify-between bg-amber-50 border border-amber-100 rounded-xl px-4 py-2 text-xs font-bold text-amber-800">
                        <span>Showing Shortlisted Candidates Only</span>
                        <a href="browse_talent_pool.php" class="text-blue-600 hover:underline">Show All</a>
                    </div>
                <?php endif; ?>

                <div class="relative">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, skill (e.g. React), or technology..." class="w-full pl-12 pr-4 py-3 bg-gray-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/10 transition-all">
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <select name="domain" class="filter-select">
                        <option>Domain</option>
                        <option <?php echo ($domain === 'Web Development') ? 'selected' : ''; ?>>Web Development</option>
                        <option <?php echo ($domain === 'AI / ML') ? 'selected' : ''; ?>>AI / ML</option>
                        <option <?php echo ($domain === 'UI / UX') ? 'selected' : ''; ?>>UI / UX</option>
                    </select>
                    <select name="stack" class="filter-select">
                        <option>Tech Stack</option>
                        <option <?php echo ($stack === 'MERN') ? 'selected' : ''; ?>>MERN</option>
                        <option <?php echo ($stack === 'Python / AI') ? 'selected' : ''; ?>>Python / AI</option>
                        <option <?php echo ($stack === 'Flutter') ? 'selected' : ''; ?>>Flutter</option>
                    </select>
                    <select name="score" class="filter-select">
                        <option>Score</option>
                        <option <?php echo ($score === '90% +') ? 'selected' : ''; ?>>90% +</option>
                        <option <?php echo ($score === '80% +') ? 'selected' : ''; ?>>80% +</option>
                    </select>
                    <select name="certification" class="filter-select">
                        <option>Certification</option>
                        <option <?php echo ($certification === 'Certified') ? 'selected' : ''; ?>>Certified</option>
                        <option <?php echo ($certification === 'Verified') ? 'selected' : ''; ?>>Verified</option>
                    </select>
                    <button type="submit" class="bg-blue-600 text-white py-2 rounded-xl text-[10px] font-bold uppercase tracking-widest hover:bg-blue-700 transition-all shadow-md">Apply Filters</button>
                    <a href="browse_talent_pool.php" class="bg-gray-900 text-white py-2 rounded-xl text-[10px] font-bold uppercase tracking-widest hover:bg-gray-800 transition-all flex items-center justify-center text-center">Clear</a>
                </div>
            </form>

            <?php if (!empty($filter_error_msg)): ?>
                <div class="p-4 bg-orange-50 border border-orange-200 text-orange-850 text-xs font-semibold rounded-2xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-orange-600">warning</span>
                    <span><?php echo htmlspecialchars($filter_error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- Talent Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                
                <?php if (empty($candidates)): ?>
                    <div class="col-span-full bg-white rounded-2xl border border-gray-200 p-16 text-center text-gray-500 shadow-sm">
                        <div class="flex flex-col items-center justify-center py-4 text-center">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 mb-4 animate-pulse">
                                <span class="material-symbols-outlined text-4xl">person_search</span>
                            </div>
                            <h4 class="text-lg font-bold text-slate-800 mb-1">No Candidates Found</h4>
                            <p class="text-sm text-slate-500 max-w-sm mb-6">No candidates matched the specified filters. Try relaxing your parameters or clearing filters.</p>
                            <a href="browse_talent_pool.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-all hover:shadow-md">
                                <span class="material-symbols-outlined text-xs">restart_alt</span> Reset Filters
                            </a>
                        </div>
                    </div>
                <?php else: foreach ($candidates as $cand): ?>
                    <!-- Candidate Card -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden group flex flex-col justify-between">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-6">
                                <div class="flex items-center gap-4">
                                    <span class="grid h-16 w-16 place-items-center rounded-2xl bg-indigo-50 border border-indigo-150 text-indigo-700 font-black text-xl"><?php echo strtoupper(substr($cand['full_name'], 0, 2)); ?></span>
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($cand['full_name']); ?></h4>
                                        <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mt-0.5"><?php echo htmlspecialchars($cand['preferred_domain'] ?: 'Technology'); ?></p>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-3 py-1 rounded-lg border border-gray-100 text-center">
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter leading-none">Score</p>
                                    <p class="text-sm font-black text-gray-900 mt-0.5"><?php echo htmlspecialchars($cand['test_score'] ?? '90'); ?>%</p>
                                </div>
                            </div>

                            <div class="space-y-4 mb-6">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">College</p>
                                    <p class="text-sm font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($cand['college'] ?: 'Not added'); ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Internship Project</p>
                                    <p class="text-sm font-bold text-gray-800 leading-tight truncate"><?php echo htmlspecialchars($cand['project_title'] ?: 'General Internship'); ?></p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Skills Used</p>
                                    <div class="flex flex-wrap gap-2">
                                        <?php 
                                        $skills_arr = explode(',', $cand['skills'] ?? '');
                                        foreach (array_slice($skills_arr, 0, 3) as $skill): if (trim($skill)):
                                        ?>
                                            <span class="bg-gray-50 border border-gray-200 text-gray-600 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 pt-6 border-t border-gray-50">
                                <button onclick="toggleShortlist(this, <?php echo $cand['user_id']; ?>)" class="w-full text-white py-3 rounded-xl text-xs font-bold transition-all shadow-md <?php echo $cand['is_shortlisted'] ? 'bg-amber-600 hover:bg-amber-700' : 'bg-gray-900 hover:bg-gray-800'; ?>">
                                    <?php echo $cand['is_shortlisted'] ? 'Shortlisted' : 'Shortlist Candidate'; ?>
                                </button>
                                <div class="grid grid-cols-2 gap-2">
                                    <button onclick="contactCandidate(this, <?php echo $cand['user_id']; ?>)" class="bg-white border border-gray-200 text-gray-700 py-2.5 rounded-xl text-xs font-bold hover:bg-gray-50 transition-all <?php echo $cand['is_contacted'] ? 'opacity-50 pointer-events-none' : ''; ?>">
                                        <?php echo $cand['is_contacted'] ? 'Contacted' : 'Contact'; ?>
                                    </button>
                                    <button onclick="viewProfileDetails(<?php echo $cand['id']; ?>)" class="bg-blue-50 text-blue-700 py-2.5 rounded-xl text-xs font-bold hover:bg-blue-100 transition-all">View Profile</button>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-3 bg-blue-50/30 flex justify-between items-center text-[10px] font-bold uppercase tracking-widest border-t border-blue-50">
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-blue-600 text-base">verified</span>
                                <span>Certified</span>
                            </div>
                            <span class="text-gray-500">Status: <?php echo htmlspecialchars($cand['current_status']); ?></span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>

            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center gap-4 pt-8">
                    <?php if ($page > 1): ?>
                        <a href="browse_talent_pool.php?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&domain=<?php echo urlencode($domain); ?>&stack=<?php echo urlencode($stack); ?>&score=<?php echo urlencode($score); ?>&certification=<?php echo urlencode($certification); ?><?php echo $filter_shortlist ? '&filter=shortlist' : ''; ?>" class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-all">
                            <span class="material-symbols-outlined">chevron_left</span>
                        </a>
                    <?php endif; ?>
                    <div class="flex gap-2">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="browse_talent_pool.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&domain=<?php echo urlencode($domain); ?>&stack=<?php echo urlencode($stack); ?>&score=<?php echo urlencode($score); ?>&certification=<?php echo urlencode($certification); ?><?php echo $filter_shortlist ? '&filter=shortlist' : ''; ?>" class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm shadow-md <?php echo ($i === $page) ? 'bg-blue-600 text-white' : 'border border-gray-200 text-gray-655 hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php if ($page < $total_pages): ?>
                        <a href="browse_talent_pool.php?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&domain=<?php echo urlencode($domain); ?>&stack=<?php echo urlencode($stack); ?>&score=<?php echo urlencode($score); ?>&certification=<?php echo urlencode($certification); ?><?php echo $filter_shortlist ? '&filter=shortlist' : ''; ?>" class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:bg-gray-50 transition-all">
                            <span class="material-symbols-outlined">chevron_right</span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

        <footer class="max-w-7xl mx-auto mt-16 pt-8 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400 font-medium tracking-tight">© 2026 InternshipHub Enterprise Portal. All candidates are certified via the internal Internship Management Platform.</p>
        </footer>
    </main>

    <!-- Candidate Detailed Evaluation Modal -->
    <div id="details-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300 scale-95">
        <div class="bg-white rounded-3xl p-8 max-w-4xl w-full mx-4 shadow-2xl border border-gray-100 flex flex-col max-h-[85vh] overflow-y-auto">
            <div class="flex justify-between items-start border-b border-gray-100 pb-5 mb-5">
                <div class="flex items-center gap-4">
                    <span id="modal-initial" class="grid h-16 w-16 place-items-center rounded-2xl bg-blue-100 text-blue-700 font-black text-xl">US</span>
                    <div>
                        <h3 class="text-2xl font-black text-gray-900 tracking-tight" id="modal-name">Candidate Name</h3>
                        <p class="text-xs text-blue-600 font-bold uppercase tracking-wider mt-0.5" id="modal-domain">Web Development</p>
                    </div>
                </div>
                <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Left panel: Profile info -->
                <div class="bg-slate-50 border border-slate-100 p-5 rounded-2xl space-y-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Contact Details</p>
                    <div class="text-xs space-y-2">
                        <div>
                            <span class="block font-bold text-gray-900">Email Address</span>
                            <span class="text-gray-600 font-medium" id="modal-email">candidate@email.com</span>
                        </div>
                        <div>
                            <span class="block font-bold text-gray-900">Phone Number</span>
                            <span class="text-gray-600 font-medium" id="modal-phone">1234567890</span>
                        </div>
                        <div>
                            <span class="block font-bold text-gray-900">College / University</span>
                            <span class="text-gray-600 font-medium" id="modal-college">College Name</span>
                        </div>
                        <div>
                            <span class="block font-bold text-gray-900">Degree & Year</span>
                            <span class="text-gray-600 font-medium" id="modal-degree">Degree info</span>
                        </div>
                    </div>
                </div>

                <!-- Middle panel: Evaluations & Scores -->
                <div class="bg-slate-50 border border-slate-100 p-5 rounded-2xl space-y-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Performance Reviews</p>
                    <div class="text-xs space-y-3">
                        <div class="flex justify-between items-center bg-white border border-gray-150 p-2.5 rounded-xl">
                            <span class="font-bold text-gray-900">Assessment Score</span>
                            <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded font-black text-sm" id="modal-score">90%</span>
                        </div>
                        <div class="flex justify-between items-center bg-white border border-gray-150 p-2.5 rounded-xl">
                            <span class="font-bold text-gray-900">Assessment Status</span>
                            <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-black text-[10px] uppercase" id="modal-status">Passed</span>
                        </div>
                        <div>
                            <span class="block font-bold text-gray-900">Technical Skills</span>
                            <p class="text-gray-600 font-medium mt-1 whitespace-pre-wrap leading-relaxed" id="modal-skills">Skills list</p>
                        </div>
                    </div>
                </div>

                <!-- Right panel: Project Completed -->
                <div class="bg-slate-50 border border-slate-100 p-5 rounded-2xl space-y-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Completed Internship Project</p>
                    <div class="text-xs space-y-2">
                        <span class="block font-bold text-gray-900" id="modal-project-title">Project Title</span>
                        <p class="text-gray-600 font-medium leading-relaxed italic" id="modal-project-description">"Project description..."</p>
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Mentor Feedback & Daily logs -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 border-t border-gray-100 pt-6">
                <!-- Mentor Evaluations -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-black text-gray-900 uppercase tracking-wider">Mentor Evaluations</h4>
                        <span id="modal-mentor-info" class="text-xs font-semibold text-slate-500"></span>
                    </div>
                    <div class="space-y-3 max-h-56 overflow-y-auto pr-1" id="modal-mentor-feedback-container">
                        <!-- Mentor feedback populated here -->
                    </div>
                </div>

                <!-- Internship Log Preview -->
                <div class="space-y-4">
                    <h4 class="text-sm font-black text-gray-900 uppercase tracking-wider">Candidate Internship Log Preview</h4>
                    <div class="divide-y divide-gray-100 max-h-56 overflow-y-auto pr-1" id="modal-logs-container">
                        <!-- Logs populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Banner -->
    <div id="toast-banner" class="fixed bottom-5 right-5 z-[999] max-w-sm w-full bg-white border border-green-150 rounded-2xl shadow-2xl p-4 flex gap-3 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
        <div class="w-10 h-10 rounded-xl bg-green-50 text-green-700 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-black text-slate-800">Success</p>
            <p class="text-xs text-slate-500 mt-1 font-semibold leading-relaxed" id="toast-message">Action processed successfully.</p>
        </div>
    </div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast-banner');
            document.getElementById('toast-message').innerText = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            toast.classList.add('translate-y-0', 'opacity-100');
            
            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }

        async function toggleShortlist(btn, candidateId) {
            const formData = new FormData();
            formData.append('action', 'toggle_shortlist');
            formData.append('candidate_id', candidateId);

            try {
                const res = await fetch('browse_talent_pool.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    if (data.action === 'added') {
                        btn.innerText = 'Shortlisted';
                        btn.classList.remove('bg-gray-900', 'hover:bg-gray-800');
                        btn.classList.add('bg-amber-600', 'hover:bg-amber-700');
                    } else {
                        btn.innerText = 'Shortlist Candidate';
                        btn.classList.remove('bg-amber-600', 'hover:bg-amber-700');
                        btn.classList.add('bg-gray-900', 'hover:bg-gray-800');
                    }
                    setTimeout(() => location.reload(), 1000);
                }
            } catch(e) {
                console.error(e);
            }
        }

        async function contactCandidate(btn, candidateId) {
            const message = prompt("Enter invitation message to student:", "We are impressed by your credentials and would love to schedule a interview. Please contact us back!");
            if (message === null) return;

            const formData = new FormData();
            formData.append('action', 'contact_candidate');
            formData.append('candidate_id', candidateId);
            formData.append('message', message);

            try {
                const res = await fetch('browse_talent_pool.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    btn.innerText = 'Contacted';
                    btn.classList.add('opacity-50', 'pointer-events-none');
                    setTimeout(() => location.reload(), 1000);
                }
            } catch(e) {
                console.error(e);
            }
        }

        async function viewProfileDetails(candidateId) {
            const formData = new FormData();
            formData.append('action', 'get_details');
            formData.append('candidate_id', candidateId);

            try {
                const res = await fetch('browse_talent_pool.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (data.success) {
                    const c = data.candidate;
                    
                    document.getElementById('modal-initial').innerText = c.full_name.substring(0, 2).toUpperCase();
                    document.getElementById('modal-name').innerText = c.full_name;
                    document.getElementById('modal-domain').innerText = c.preferred_domain || 'Technology';
                    document.getElementById('modal-email').innerText = c.email || '—';
                    document.getElementById('modal-phone').innerText = c.phone || '—';
                    document.getElementById('modal-college').innerText = c.college || '—';
                    document.getElementById('modal-degree').innerText = (c.course ? c.course : '') + (c.sp_year ? ' (' + c.sp_year + ')' : '');
                    document.getElementById('modal-score').innerText = (c.test_score ? c.test_score : '—') + '%';
                    document.getElementById('modal-status').innerText = c.test_status || '—';
                    document.getElementById('modal-skills').innerText = c.skills || '—';
                    document.getElementById('modal-project-title').innerText = c.project_title || 'General Internship';
                    document.getElementById('modal-project-description').innerText = c.reason_for_applying ? '"' + c.reason_for_applying + '"' : '"Completed internship tasks."';

                    // Helper to escape HTML safely
                    const escapeHtml = (str) => {
                        if (!str) return '';
                        return str.toString()
                            .replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;")
                            .replace(/"/g, "&quot;")
                            .replace(/'/g, "&#039;");
                    };

                    // Populate Mentor details
                    const mentorInfo = document.getElementById('modal-mentor-info');
                    if (data.mentor) {
                        mentorInfo.innerText = `Assigned Mentor: ${data.mentor.mentor_name}`;
                        mentorInfo.title = `Email: ${data.mentor.mentor_email}\nAssigned on: ${new Date(data.mentor.assigned_at).toLocaleDateString()}`;
                    } else {
                        mentorInfo.innerText = 'No Assigned Mentor';
                        mentorInfo.title = '';
                    }

                    // Populate Mentor feedback
                    const fbContainer = document.getElementById('modal-mentor-feedback-container');
                    fbContainer.innerHTML = '';
                    if (!data.mentor_feedback || data.mentor_feedback.length === 0) {
                        fbContainer.innerHTML = '<p class="text-xs text-gray-400 italic py-4">No mentor evaluations recorded yet.</p>';
                    } else {
                        data.mentor_feedback.forEach(f => {
                            const dateStr = new Date(f.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
                            const ratingStars = '★'.repeat(f.rating) + '☆'.repeat(5 - f.rating);
                            
                            const statusColor = f.status === 'Approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-250' : 
                                                f.status === 'Needs Update' ? 'bg-red-50 text-red-750 border-red-200' : 
                                                'bg-blue-50 text-blue-700 border-blue-200';
                            
                            const fDiv = document.createElement('div');
                            fDiv.className = 'p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-1.5';
                            fDiv.innerHTML = `
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="font-extrabold text-slate-800">${escapeHtml(f.mentor_name)}</span>
                                    <span class="text-slate-400 font-semibold">${dateStr}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-amber-500 text-xs font-bold font-mono tracking-tighter" title="Rating: ${f.rating}/5">${ratingStars}</span>
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[8px] font-bold uppercase tracking-wider ${statusColor}">${escapeHtml(f.status || 'Reviewed')}</span>
                                </div>
                                <div class="text-[11px] leading-relaxed">
                                    <p class="font-bold text-slate-700">${escapeHtml(f.title || 'Log Evaluation')}</p>
                                    <p class="text-slate-500 italic mt-0.5">"${escapeHtml(f.comments)}"</p>
                                </div>
                            `;
                            fbContainer.appendChild(fDiv);
                        });
                    }

                    // Populate daily logs
                    const logsContainer = document.getElementById('modal-logs-container');
                    logsContainer.innerHTML = '';
                    if (data.logs.length === 0) {
                        logsContainer.innerHTML = '<p class="text-xs text-gray-400 italic py-4">No daily logs found for this candidate.</p>';
                    } else {
                        data.logs.forEach(l => {
                            const dateStr = new Date(l.log_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
                            const logDiv = document.createElement('div');
                            logDiv.className = 'py-3 flex justify-between items-start gap-4';
                            logDiv.innerHTML = `
                                <div>
                                    <p class="text-xs font-bold text-gray-800 leading-tight">${escapeHtml(l.tasks_completed)}</p>
                                    <p class="text-[10px] text-gray-400 font-medium mt-1">Focus: ${escapeHtml(l.focus_level)}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase">${l.time_spent} hrs</span>
                                    <p class="text-[9px] text-gray-400 mt-1 font-semibold">${dateStr}</p>
                                </div>
                            `;
                            logsContainer.appendChild(logDiv);
                        });
                    }

                    // Open Modal
                    const modal = document.getElementById('details-modal');
                    modal.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
                    modal.classList.add('opacity-100', 'scale-100');
                }
            } catch(e) {
                console.error(e);
            }
        }

        function closeDetailsModal() {
            const modal = document.getElementById('details-modal');
            modal.classList.remove('opacity-100', 'scale-100');
            modal.classList.add('opacity-0', 'pointer-events-none', 'scale-95');
        }
    </script>

</body>
</html>
