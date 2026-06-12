<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php?error=" . urlencode("Unauthorized access. Admin role required."));
    exit();
}
require_once __DIR__ . '/../includes/db.php';

$success_msg = "";
$error_msg = "";

// ── Ensure Talent Pool columns exist ──
$talent_cols = [
    'in_talent_pool'       => "TINYINT(1) DEFAULT 0",
    'is_featured'          => "TINYINT(1) DEFAULT 0",
    'placement_status'     => "VARCHAR(100) DEFAULT 'Unplaced'",
    'shortlisted_companies'=> "TEXT DEFAULT NULL",
    'performance_score'    => "DECIMAL(5,2) DEFAULT NULL",
    'tech_stack'           => "VARCHAR(255) DEFAULT NULL",
    'skills'               => "TEXT DEFAULT NULL",
    'internship_duration'  => "VARCHAR(50) DEFAULT NULL",
];
foreach ($talent_cols as $col => $def) {
    $chk = mysqli_query($conn, "SHOW COLUMNS FROM internship_applications LIKE '$col'");
    if ($chk && mysqli_num_rows($chk) == 0) {
        mysqli_query($conn, "ALTER TABLE internship_applications ADD COLUMN $col $def");
    }
}

// ── Ensure projects table has needed columns ──
$proj_chk = mysqli_query($conn, "SHOW TABLES LIKE 'projects'");
$projects_exist = ($proj_chk && mysqli_num_rows($proj_chk) > 0);

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $app_id = intval($_POST['app_id'] ?? 0);

    if ($action === 'remove_from_pool' && $app_id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE internship_applications SET in_talent_pool = 0 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $app_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Student removed from Talent Pool.";
        } else {
            $error_msg = "Failed to remove student.";
        }
        mysqli_stmt_close($stmt);
    }

    if ($action === 'toggle_featured' && $app_id > 0) {
        $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_featured FROM internship_applications WHERE id = $app_id"));
        $new_val = ($cur['is_featured'] ?? 0) ? 0 : 1;
        $stmt = mysqli_prepare($conn, "UPDATE internship_applications SET is_featured = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $new_val, $app_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $success_msg = $new_val ? "Marked as featured candidate." : "Removed from featured.";
    }

    if ($action === 'update_placement' && $app_id > 0) {
        $placement_status = mysqli_real_escape_string($conn, trim($_POST['placement_status'] ?? 'Unplaced'));
        $shortlisted = mysqli_real_escape_string($conn, trim($_POST['shortlisted_companies'] ?? ''));
        $stmt = mysqli_prepare($conn, "UPDATE internship_applications SET placement_status = ?, shortlisted_companies = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $placement_status, $shortlisted, $app_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Placement status updated.";
        } else {
            $error_msg = "Failed to update placement.";
        }
        mysqli_stmt_close($stmt);
    }

    if ($action === 'add_to_pool' && $app_id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE internship_applications SET in_talent_pool = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $app_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Student added to Talent Pool.";
        } else {
            $error_msg = "Failed to add student.";
        }
        mysqli_stmt_close($stmt);
    }
}

// ── Filters ──
$search   = trim($_GET['search'] ?? '');
$f_skill  = trim($_GET['skill'] ?? '');
$f_tech   = trim($_GET['tech'] ?? '');
$f_score  = trim($_GET['score'] ?? '');
$f_dur    = trim($_GET['duration'] ?? '');
$f_place  = trim($_GET['placement'] ?? '');
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// ── Build WHERE clause ──
$where_parts = [];

// Always enforce completion filter: student must have successfully completed their internship/project
$where_parts[] = "a.status IN ('Completed','Certificate Issued','Internship Completed','Project Completed','Evaluated')";

if (!$show_all) {
    $where_parts[] = "a.in_talent_pool = 1";
}

if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(u.full_name LIKE '%$s%' OR u.email LIKE '%$s%' OR a.skills LIKE '%$s%' OR a.tech_stack LIKE '%$s%' OR i.title LIKE '%$s%')";
}
if ($f_skill !== '') {
    $sk = mysqli_real_escape_string($conn, $f_skill);
    $where_parts[] = "a.skills LIKE '%$sk%'";
}
if ($f_tech !== '') {
    $tc = mysqli_real_escape_string($conn, $f_tech);
    $where_parts[] = "a.tech_stack LIKE '%$tc%'";
}
if ($f_score !== '') {
    if ($f_score === 'good') {
        $where_parts[] = "a.performance_score >= 70 AND a.performance_score < 80";
    } elseif ($f_score === 'excellent') {
        $where_parts[] = "a.performance_score >= 80 AND a.performance_score < 90";
    } elseif ($f_score === 'outstanding') {
        $where_parts[] = "a.performance_score >= 90";
    }
}
if ($f_dur !== '') {
    $dur = mysqli_real_escape_string($conn, $f_dur);
    $where_parts[] = "a.internship_duration LIKE '%$dur%'";
}
if ($f_place !== '') {
    $pl = mysqli_real_escape_string($conn, $f_place);
    $where_parts[] = "a.placement_status = '$pl'";
}

$where_sql = count($where_parts) > 0 ? "WHERE " . implode(" AND ", $where_parts) : "";

// ── Main talent pool query ──
$talent_sql = "
    SELECT 
        a.id, a.user_id, a.status, a.skills, a.tech_stack, a.performance_score,
        a.internship_duration, a.placement_status, a.shortlisted_companies,
        a.in_talent_pool, a.is_featured, a.resume_file, a.applied_date,
        u.full_name, u.email, u.phone,
        i.title as internship_title,
        COALESCE(a.internship_name, i.title, 'N/A') as intern_name
    FROM internship_applications a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN internships i ON a.internship_id = i.id
    $where_sql
    ORDER BY a.is_featured DESC, a.performance_score DESC, a.id DESC
";

$talent_res = mysqli_query($conn, $talent_sql);
$talent_pool = [];
if ($talent_res) {
    while ($row = mysqli_fetch_assoc($talent_res)) {
        $talent_pool[] = $row;
    }
}

// ── Fetch mentor feedback/ratings per user ──
$mentor_ratings = [];
$mf_res = mysqli_query($conn, "SELECT user_id, AVG(rating) as avg_rating FROM mentor_feedback GROUP BY user_id");
if ($mf_res) {
    while ($mf = mysqli_fetch_assoc($mf_res)) {
        $mentor_ratings[$mf['user_id']] = round($mf['avg_rating'], 1);
    }
}

// ── Stats ──
$total_pool = count($talent_pool);
$featured_count = count(array_filter($talent_pool, fn($r) => $r['is_featured']));
$placed_count = count(array_filter($talent_pool, fn($r) => $r['placement_status'] === 'Placed'));
$shortlisted_count = count(array_filter($talent_pool, fn($r) => $r['placement_status'] === 'Shortlisted'));

// ── Header user info ──
$header_uid = $_SESSION['user_id'];
$header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
$header_user = mysqli_fetch_assoc($header_res);
$header_name = $header_user['full_name'] ?? 'Admin';
$header_photo = $header_user['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talent Pool – IMP Admin</title>
    <meta name="description" content="Manage and track top-performing students in the IMP Talent Pool for placement opportunities.">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <script id="tailwind-config">
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: "#003ea8",
            "primary-hover": "#002a75",
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
      body { background-color: #f8f9fa; color: #191c1d; }
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        vertical-align: middle;
      }
      .talent-card {
        transition: transform 0.18s ease, box-shadow 0.18s ease;
      }
      .talent-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0, 62, 168, 0.10);
      }
      .featured-glow {
        box-shadow: 0 0 0 2px #f59e0b, 0 8px 24px rgba(245,158,11,0.15);
      }
      .star-filled { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
      .badge-placed     { background: #d1fae5; color: #065f46; }
      .badge-shortlisted{ background: #dbeafe; color: #1e40af; }
      .badge-unplaced   { background: #f3f4f6; color: #6b7280; }
      .badge-rejected   { background: #fee2e2; color: #991b1b; }
      .modal-overlay { backdrop-filter: blur(4px); }
      .skill-chip {
        display: inline-flex; align-items: center;
        background: #eff6ff; color: #1d4ed8; border-radius: 9999px;
        padding: 2px 10px; font-size: 11px; font-weight: 600; margin: 2px;
        border: 1px solid #bfdbfe;
      }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

<!-- Top Nav -->
<header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-40">
    <div class="flex items-center gap-8">
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
        <div class="hidden md:flex gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest border-l border-gray-200 pl-6">
            Platform Administration
        </div>
    </div>
    <div class="flex items-center gap-4">
        <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-xl shadow-sm">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
            <span class="font-semibold text-slate-700">System Online</span>
        </div>
        <div class="relative">
            <button onclick="document.getElementById('profile-dropdown').classList.toggle('hidden')" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline">
                    <?php echo htmlspecialchars($header_name); ?> (Admin)
                </span>
                <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-400 transition-colors">
                    <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
                        <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=003ea8&color=fff" alt="Profile" class="w-full h-full object-cover">
                    <?php endif; ?>
                </div>
                <span class="material-symbols-outlined text-gray-400 text-[18px]">arrow_drop_down</span>
            </button>
            <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                <a href="users.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    <span class="material-symbols-outlined text-gray-400 text-[18px]">manage_accounts</span> Manage Users
                </a>
                <hr class="my-1 border-gray-100">
                <a href="../logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                    <span class="material-symbols-outlined text-red-400 text-[18px]">logout</span> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<div class="flex flex-1 overflow-hidden">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto bg-gray-50">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <span class="material-symbols-outlined text-yellow-500 text-3xl" style="font-variation-settings:'FILL' 1,'wght' 600,'GRAD' 0,'opsz' 24">stars</span>
                        Talent Pool
                    </h1>
                    <p class="text-gray-500 text-sm mt-1">Top-performing students eligible for placement opportunities</p>
                </div>
                <div class="flex gap-3 flex-wrap">
                    <a href="?show_all=1" class="<?php echo $show_all ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-700'; ?> px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-medium hover:shadow-md transition-all shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-lg">manage_search</span> View All Completed
                    </a>
                    <a href="talent_pool.php" class="<?php echo !$show_all ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-700'; ?> px-4 py-2 rounded-lg flex items-center gap-2 text-sm font-medium hover:shadow-md transition-all shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-lg">stars</span> Talent Pool Only
                    </a>
                </div>
            </div>

            <!-- Flash Messages -->
            <?php if ($success_msg): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 px-5 py-3 rounded-xl flex items-center gap-2 text-sm font-medium alert-success">
                <span class="material-symbols-outlined text-green-600">check_circle</span>
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-5 py-3 rounded-xl flex items-center gap-2 text-sm font-medium alert-danger">
                <span class="material-symbols-outlined text-red-600">error</span>
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total in Pool</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $total_pool; ?></p>
                    <p class="text-xs text-blue-600 font-medium mt-1">Students</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Featured</p>
                    <p class="text-3xl font-bold text-yellow-500 mt-1"><?php echo $featured_count; ?></p>
                    <p class="text-xs text-yellow-600 font-medium mt-1">Top Candidates</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Placed</p>
                    <p class="text-3xl font-bold text-green-600 mt-1"><?php echo $placed_count; ?></p>
                    <p class="text-xs text-green-600 font-medium mt-1">Successfully Hired</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Shortlisted</p>
                    <p class="text-3xl font-bold text-blue-600 mt-1"><?php echo $shortlisted_count; ?></p>
                    <p class="text-xs text-blue-600 font-medium mt-1">By Companies</p>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <form method="GET" action="talent_pool.php" class="space-y-4" id="filter-form">
                    <?php if ($show_all): ?>
                        <input type="hidden" name="show_all" value="1">
                    <?php endif; ?>
                    <div class="flex flex-col md:flex-row gap-3">
                        <div class="flex-1 relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
                            <input type="text" name="search" id="search-input" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by name, email, skills, technology, internship..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50">
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors flex items-center gap-2 cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">search</span> Search
                        </button>
                        <a href="talent_pool.php<?php echo $show_all ? '?show_all=1' : ''; ?>" class="bg-gray-100 text-gray-700 px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-gray-200 transition-colors flex items-center gap-2 cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">clear</span> Reset
                        </a>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <div class="flex flex-col gap-1 min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Skills</label>
                            <input type="text" name="skill" value="<?php echo htmlspecialchars($f_skill); ?>"
                                placeholder="e.g. Python, Java"
                                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                        </div>
                        <div class="flex flex-col gap-1 min-w-[150px]">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tech Stack</label>
                            <input type="text" name="tech" value="<?php echo htmlspecialchars($f_tech); ?>"
                                placeholder="e.g. React, Django"
                                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                        </div>
                        <div class="flex flex-col gap-1 min-w-[140px]">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Performance Level</label>
                            <select name="score" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                                <option value="">All Levels</option>
                                <option value="good" <?php echo $f_score==='good' ? 'selected':''; ?>>Good (70-79)</option>
                                <option value="excellent" <?php echo $f_score==='excellent' ? 'selected':''; ?>>Excellent (80-89)</option>
                                <option value="outstanding" <?php echo $f_score==='outstanding' ? 'selected':''; ?>>Outstanding (90+)</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 min-w-[140px]">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Duration</label>
                            <select name="duration" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                                <option value="">Any Duration</option>
                                <option value="1 month" <?php echo $f_dur==='1 month' ? 'selected':''; ?>>1 Month</option>
                                <option value="2 months" <?php echo $f_dur==='2 months' ? 'selected':''; ?>>2 Months</option>
                                <option value="3 months" <?php echo $f_dur==='3 months' ? 'selected':''; ?>>3 Months</option>
                                <option value="6 months" <?php echo $f_dur==='6 months' ? 'selected':''; ?>>6 Months</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1 min-w-[140px]">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Placement Status</label>
                            <select name="placement" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                                <option value="">All Statuses</option>
                                <option value="Unplaced"    <?php echo $f_place==='Unplaced'    ? 'selected':''; ?>>Unplaced</option>
                                <option value="Shortlisted" <?php echo $f_place==='Shortlisted' ? 'selected':''; ?>>Shortlisted</option>
                                <option value="Placed"      <?php echo $f_place==='Placed'      ? 'selected':''; ?>>Placed</option>
                                <option value="Rejected"    <?php echo $f_place==='Rejected'    ? 'selected':''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-100 transition-colors flex items-center gap-2 cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]">filter_list</span> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Talent Pool Cards / Table Toggle -->
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-500">
                    Showing <span class="font-semibold text-gray-800"><?php echo count($talent_pool); ?></span> student<?php echo count($talent_pool) !== 1 ? 's' : ''; ?>
                    <?php if ($show_all): ?>
                        <span class="text-blue-600">(all completed internships)</span>
                    <?php else: ?>
                        <span class="text-yellow-600">(talent pool only)</span>
                    <?php endif; ?>
                </p>
                <div class="flex gap-2" id="view-toggle">
                    <button onclick="setView('card')" id="btn-card-view" class="p-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 transition-colors" title="Card view">
                        <span class="material-symbols-outlined text-[18px]">grid_view</span>
                    </button>
                    <button onclick="setView('table')" id="btn-table-view" class="p-2 rounded-lg bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 transition-colors" title="Table view">
                        <span class="material-symbols-outlined text-[18px]">table_rows</span>
                    </button>
                </div>
            </div>

            <?php if (empty($talent_pool)): ?>
            <!-- Empty State -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-16 text-center">
                <span class="material-symbols-outlined text-gray-300 text-7xl block mb-4" style="font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 48">group_off</span>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">
                    <?php echo $show_all ? 'No completed internships found' : 'No students in Talent Pool yet'; ?>
                </h3>
                <p class="text-gray-400 text-sm mb-6">
                    <?php echo $show_all ? 'Students with completed internship status will appear here.' : 'Use "View All Completed" to find and add students to the Talent Pool.'; ?>
                </p>
                <?php if (!$show_all): ?>
                <a href="?show_all=1" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">manage_search</span>
                    Browse Completed Students
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>

            <!-- ═══ CARD VIEW ═══ -->
            <div id="card-view" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <?php foreach ($talent_pool as $student):
                    $uid = $student['user_id'];
                    $app_id = $student['id'];
                    $mentor_rating = $mentor_ratings[$uid] ?? null;
                    $skills_list = array_filter(array_map('trim', explode(',', $student['skills'] ?? '')));
                    $tech_list = array_filter(array_map('trim', explode(',', $student['tech_stack'] ?? '')));
                    $placement = $student['placement_status'] ?? 'Unplaced';
                    $badge_cls = match($placement) {
                        'Placed'      => 'badge-placed',
                        'Shortlisted' => 'badge-shortlisted',
                        'Rejected'    => 'badge-rejected',
                        default       => 'badge-unplaced'
                    };
                    $score = $student['performance_score'];
                    $score_color = $score >= 85 ? 'text-green-600' : ($score >= 70 ? 'text-blue-600' : ($score >= 50 ? 'text-yellow-600' : 'text-gray-500'));
                    $is_featured = $student['is_featured'];
                    $in_pool = $student['in_talent_pool'];

                    // Eligibility status calculation
                    if ($in_pool) {
                        $eligibility_status = "Added to Talent Pool";
                        $eligibility_color = "bg-green-50 text-green-700 border-green-200";
                    } else {
                        $status_lower = strtolower($student['status'] ?? '');
                        $completed_statuses = ['completed', 'certificate issued', 'internship completed', 'project completed', 'evaluated'];
                        $is_comp = in_array($status_lower, $completed_statuses);
                        $score_elig = ($score !== null && $score >= 70);
                        $mentor_eval_val = strtolower($student['mentor_evaluation'] ?? 'approved');
                        $mentor_elig = ($mentor_eval_val === 'approved');
                        $cert_status_val = strtolower($student['certificate_status'] ?? 'completed');
                        $cert_elig = in_array($cert_status_val, ['generated', 'completed']);

                        if ($is_comp && $score_elig && $mentor_elig && $cert_elig) {
                            $eligibility_status = "Eligible";
                            $eligibility_color = "bg-blue-50 text-blue-700 border-blue-200";
                        } else {
                            $eligibility_status = "Not Eligible";
                            $eligibility_color = "bg-red-50 text-red-700 border-red-200";
                        }
                    }
                ?>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm talent-card <?php echo $is_featured ? 'featured-glow border-yellow-300' : ''; ?> relative overflow-hidden">
                    <?php if ($is_featured): ?>
                    <div class="absolute top-3 right-3 bg-yellow-400 text-white text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 shadow">
                        <span class="material-symbols-outlined text-[12px] star-filled">star</span> FEATURED
                    </div>
                    <?php endif; ?>

                    <!-- Card Header -->
                    <div class="p-5 pb-4 border-b border-gray-100">
                        <div class="flex items-start gap-3">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['full_name']); ?>&background=003ea8&color=fff&size=48"
                                alt="<?php echo htmlspecialchars($student['full_name']); ?>"
                                class="w-12 h-12 rounded-full border border-gray-200 shadow-sm shrink-0">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                                <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($student['email']); ?></p>
                                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($placement); ?></span>
                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full border <?php echo $eligibility_color; ?>"><?php echo $eligibility_status; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 space-y-3">
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <p class="text-gray-400 font-medium mb-0.5">Internship</p>
                                <p class="font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($student['intern_name'] ?: 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-400 font-medium mb-0.5">Duration</p>
                                <p class="font-semibold text-gray-700"><?php echo htmlspecialchars($student['internship_duration'] ?: 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-400 font-medium mb-0.5">Performance</p>
                                <p class="font-bold <?php echo $score_color; ?>">
                                    <?php echo $score !== null ? number_format($score, 1) . '%' : 'N/A'; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-400 font-medium mb-0.5">Mentor Rating</p>
                                <p class="font-bold text-gray-700">
                                    <?php if ($mentor_rating !== null): ?>
                                    <span class="flex items-center gap-1">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <span class="material-symbols-outlined text-[14px] <?php echo $s <= round($mentor_rating) ? 'text-yellow-400 star-filled' : 'text-gray-300'; ?>">star</span>
                                        <?php endfor; ?>
                                        <span class="text-xs text-gray-500">(<?php echo $mentor_rating; ?>)</span>
                                    </span>
                                    <?php else: echo 'N/A'; endif; ?>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($skills_list)): ?>
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Skills</p>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach (array_slice($skills_list, 0, 4) as $sk): ?>
                                <span class="skill-chip"><?php echo htmlspecialchars($sk); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($skills_list) > 4): ?>
                                <span class="skill-chip bg-gray-100 text-gray-500 border-gray-200">+<?php echo count($skills_list)-4; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($tech_list)): ?>
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Tech Stack</p>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach (array_slice($tech_list, 0, 4) as $tc): ?>
                                <span class="inline-flex items-center bg-purple-50 text-purple-700 border border-purple-200 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"><?php echo htmlspecialchars($tc); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($tech_list) > 4): ?>
                                <span class="inline-flex items-center bg-gray-100 text-gray-500 rounded-full px-2.5 py-0.5 text-[11px] font-semibold">+<?php echo count($tech_list)-4; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($student['shortlisted_companies'])): ?>
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Shortlisted By</p>
                            <p class="text-xs text-gray-600 font-medium"><?php echo htmlspecialchars($student['shortlisted_companies']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Actions -->
                    <div class="px-5 pb-5 flex flex-wrap gap-2">
                        <!-- Resume Button -->
                        <?php if (!empty($student['resume_file']) && file_exists($student['resume_file'])): ?>
                        <a href="<?php echo htmlspecialchars($student['resume_file']); ?>" target="_blank"
                            class="flex-1 bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-gray-100 transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[15px]">description</span> Resume
                        </a>
                        <?php else: ?>
                        <span class="flex-1 bg-gray-50 border border-gray-200 text-gray-400 px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 cursor-not-allowed">
                            <span class="material-symbols-outlined text-[15px]">description</span> No Resume
                        </span>
                        <?php endif; ?>

                        <!-- Feature Toggle -->
                        <form method="POST" action="talent_pool.php<?php echo $show_all?'?show_all=1':''; ?>" class="flex-1">
                            <input type="hidden" name="action" value="toggle_featured">
                            <input type="hidden" name="app_id" value="<?php echo $app_id; ?>">
                            <button type="submit" class="w-full bg-yellow-50 border border-yellow-200 text-yellow-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-yellow-100 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-outlined text-[15px] <?php echo $is_featured ? 'star-filled text-yellow-500' : ''; ?>">star</span>
                                <?php echo $is_featured ? 'Unfeature' : 'Feature'; ?>
                            </button>
                        </form>

                        <!-- View Profile / Placement -->
                        <button onclick="openPlacementModal(<?php echo $app_id; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>', '<?php echo htmlspecialchars($placement); ?>', '<?php echo htmlspecialchars($student['shortlisted_companies'] ?? ''); ?>')"
                            class="flex-1 bg-blue-50 border border-blue-200 text-blue-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-blue-100 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                            <span class="material-symbols-outlined text-[15px]">business_center</span> Placement
                        </button>

                        <!-- Add/Remove from Pool -->
                        <?php if ($in_pool): ?>
                        <form method="POST" action="talent_pool.php<?php echo $show_all?'?show_all=1':''; ?>" class="flex-1">
                            <input type="hidden" name="action" value="remove_from_pool">
                            <input type="hidden" name="app_id" value="<?php echo $app_id; ?>">
                            <button type="submit" onclick="return confirm('Remove from Talent Pool?')"
                                class="w-full bg-red-50 border border-red-200 text-red-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-red-100 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]">person_remove</span> Remove
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" action="talent_pool.php<?php echo $show_all?'?show_all=1':''; ?>" class="flex-1">
                            <input type="hidden" name="action" value="add_to_pool">
                            <input type="hidden" name="app_id" value="<?php echo $app_id; ?>">
                            <button type="submit"
                                class="w-full bg-green-50 border border-green-200 text-green-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-green-100 transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                                <span class="material-symbols-outlined text-[15px]">person_add</span> Add to Pool
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ═══ TABLE VIEW ═══ -->
            <div id="table-view" class="hidden">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">#</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Student</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Internship</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Skills</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Score</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Mentor Rating</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Duration</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Status</th>
                                    <th class="text-left px-5 py-3.5 font-semibold text-gray-600 text-xs uppercase tracking-wide">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($talent_pool as $idx => $student):
                                    $uid = $student['user_id'];
                                    $app_id = $student['id'];
                                    $mentor_rating = $mentor_ratings[$uid] ?? null;
                                    $placement = $student['placement_status'] ?? 'Unplaced';
                                    $badge_cls = match($placement) {
                                        'Placed'      => 'badge-placed',
                                        'Shortlisted' => 'badge-shortlisted',
                                        'Rejected'    => 'badge-rejected',
                                        default       => 'badge-unplaced'
                                    };
                                    $score = $student['performance_score'];
                                    $score_color = $score >= 85 ? 'text-green-600 font-bold' : ($score >= 70 ? 'text-blue-600 font-semibold' : ($score >= 50 ? 'text-yellow-600' : 'text-gray-500'));
                                    $is_featured = $student['is_featured'];
                                    $in_pool = $student['in_talent_pool'];
                                    $skills_short = implode(', ', array_slice(array_filter(array_map('trim', explode(',', $student['skills'] ?? ''))), 0, 3));

                                    // Eligibility status calculation
                                    if ($in_pool) {
                                        $eligibility_status = "Added to Talent Pool";
                                        $eligibility_color = "bg-green-50 text-green-700 border-green-200";
                                    } else {
                                        $status_lower = strtolower($student['status'] ?? '');
                                        $completed_statuses = ['completed', 'certificate issued', 'internship completed', 'project completed', 'evaluated'];
                                        $is_comp = in_array($status_lower, $completed_statuses);
                                        $score_elig = ($score !== null && $score >= 70);
                                        $mentor_eval_val = strtolower($student['mentor_evaluation'] ?? 'approved');
                                        $mentor_elig = ($mentor_eval_val === 'approved');
                                        $cert_status_val = strtolower($student['certificate_status'] ?? 'completed');
                                        $cert_elig = in_array($cert_status_val, ['generated', 'completed']);

                                        if ($is_comp && $score_elig && $mentor_elig && $cert_elig) {
                                            $eligibility_status = "Eligible";
                                            $eligibility_color = "bg-blue-50 text-blue-700 border-blue-200";
                                        } else {
                                            $eligibility_status = "Not Eligible";
                                            $eligibility_color = "bg-red-50 text-red-700 border-red-200";
                                        }
                                    }
                                ?>
                                <tr class="hover:bg-gray-50 transition-colors <?php echo $is_featured ? 'bg-yellow-50/30' : ''; ?>">
                                    <td class="px-5 py-4 text-gray-400 text-xs"><?php echo $idx + 1; ?></td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['full_name']); ?>&background=003ea8&color=fff&size=36"
                                                class="w-8 h-8 rounded-full border border-gray-200" alt="">
                                            <div>
                                                <p class="font-semibold text-gray-800 text-sm flex items-center gap-1">
                                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                                    <?php if ($is_featured): ?>
                                                    <span class="material-symbols-outlined text-[14px] text-yellow-400 star-filled">star</span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($student['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 text-xs max-w-[140px] truncate"><?php echo htmlspecialchars($student['intern_name'] ?: 'N/A'); ?></td>
                                    <td class="px-5 py-4 text-gray-600 text-xs max-w-[160px] truncate"><?php echo htmlspecialchars($skills_short ?: 'N/A'); ?></td>
                                    <td class="px-5 py-4">
                                        <span class="<?php echo $score_color; ?> text-sm">
                                            <?php echo $score !== null ? number_format($score, 1) . '%' : '—'; ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <?php if ($mentor_rating !== null): ?>
                                        <div class="flex items-center gap-0.5">
                                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <span class="material-symbols-outlined text-[14px] <?php echo $s <= round($mentor_rating) ? 'text-yellow-400 star-filled' : 'text-gray-300'; ?>">star</span>
                                            <?php endfor; ?>
                                            <span class="text-xs text-gray-400 ml-1"><?php echo $mentor_rating; ?></span>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-gray-400 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 text-xs"><?php echo htmlspecialchars($student['internship_duration'] ?: '—'); ?></td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-1.5 min-w-[100px]">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border text-center <?php echo $badge_cls; ?>">
                                                <?php echo htmlspecialchars($placement); ?>
                                            </span>
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border text-center <?php echo $eligibility_color; ?>">
                                                <?php echo $eligibility_status; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <button onclick="openPlacementModal(<?php echo $app_id; ?>, '<?php echo htmlspecialchars($student['full_name']); ?>', '<?php echo htmlspecialchars($placement); ?>', '<?php echo htmlspecialchars($student['shortlisted_companies'] ?? ''); ?>')"
                                                class="text-blue-600 hover:text-blue-800 transition-colors cursor-pointer" title="Manage Placement">
                                                <span class="material-symbols-outlined text-[18px]">business_center</span>
                                            </button>
                                            <form method="POST" action="talent_pool.php<?php echo $show_all?'?show_all=1':''; ?>" class="inline">
                                                <input type="hidden" name="action" value="toggle_featured">
                                                <input type="hidden" name="app_id" value="<?php echo $app_id; ?>">
                                                <button type="submit" class="<?php echo $is_featured ? 'text-yellow-500' : 'text-gray-400 hover:text-yellow-500'; ?> transition-colors cursor-pointer" title="Toggle Featured">
                                                    <span class="material-symbols-outlined text-[18px] <?php echo $is_featured ? 'star-filled' : ''; ?>">star</span>
                                                </button>
                                            </form>
                                            <?php if ($in_pool): ?>
                                            <form method="POST" action="talent_pool.php<?php echo $show_all?'?show_all=1':''; ?>" class="inline">
                                                <input type="hidden" name="action" value="remove_from_pool">
                                                <input type="hidden" name="app_id" value="<?php echo $app_id; ?>">
                                                <button type="submit" onclick="return confirm('Remove from Talent Pool?')" class="text-red-400 hover:text-red-600 transition-colors cursor-pointer" title="Remove from Pool">
                                                    <span class="material-symbols-outlined text-[18px]">person_remove</span>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST" action="talent_pool.php<?php echo $show_all?'?show_all=1':''; ?>" class="inline">
                                                <input type="hidden" name="action" value="add_to_pool">
                                                <input type="hidden" name="app_id" value="<?php echo $app_id; ?>">
                                                <button type="submit" class="text-green-500 hover:text-green-700 transition-colors cursor-pointer" title="Add to Pool">
                                                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </main>
</div>

<!-- ═══ Placement Modal ═══ -->
<div id="placement-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center modal-overlay p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Manage Placement</h2>
                <p id="placement-student-name" class="text-sm text-gray-500 mt-0.5"></p>
            </div>
            <button onclick="closePlacementModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-gray-500">close</span>
            </button>
        </div>
        <form method="POST" action="talent_pool.php<?php echo $show_all?'?show_all=1':''; ?>" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_placement">
            <input type="hidden" name="app_id" id="placement-app-id" value="">

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Placement Status</label>
                <select name="placement_status" id="placement-status-select"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50">
                    <option value="Unplaced">Unplaced</option>
                    <option value="Shortlisted">Shortlisted</option>
                    <option value="Placed">Placed</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Shortlisted Companies</label>
                <textarea name="shortlisted_companies" id="placement-companies" rows="3"
                    placeholder="e.g. Google, Microsoft, Infosys..."
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-50 resize-none"></textarea>
                <p class="text-xs text-gray-400 mt-1">Separate company names with commas</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closePlacementModal()" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-xl text-sm font-semibold hover:bg-gray-200 transition-colors cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors cursor-pointer">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── View Toggle ──
function setView(type) {
    const cardView = document.getElementById('card-view');
    const tableView = document.getElementById('table-view');
    const btnCard = document.getElementById('btn-card-view');
    const btnTable = document.getElementById('btn-table-view');
    if (!cardView || !tableView) return;

    if (type === 'card') {
        cardView.classList.remove('hidden');
        tableView.classList.add('hidden');
        btnCard.className = 'p-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 transition-colors';
        btnTable.className = 'p-2 rounded-lg bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 transition-colors';
        localStorage.setItem('talentPoolView', 'card');
    } else {
        cardView.classList.add('hidden');
        tableView.classList.remove('hidden');
        btnCard.className = 'p-2 rounded-lg bg-gray-100 text-gray-600 text-sm hover:bg-gray-200 transition-colors';
        btnTable.className = 'p-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 transition-colors';
        localStorage.setItem('talentPoolView', 'table');
    }
}

// Restore last view
document.addEventListener('DOMContentLoaded', () => {
    const savedView = localStorage.getItem('talentPoolView') || 'card';
    setView(savedView);

    // Close profile dropdown on outside click
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('profile-dropdown');
        if (dropdown && !e.target.closest('.relative')) {
            dropdown.classList.add('hidden');
        }
    });
});

// ── Placement Modal ──
function openPlacementModal(appId, name, placement, companies) {
    document.getElementById('placement-app-id').value = appId;
    document.getElementById('placement-student-name').textContent = name;
    document.getElementById('placement-status-select').value = placement || 'Unplaced';
    document.getElementById('placement-companies').value = companies || '';
    document.getElementById('placement-modal').classList.remove('hidden');
}

function closePlacementModal() {
    document.getElementById('placement-modal').classList.add('hidden');
}

// Close modal on backdrop click
document.getElementById('placement-modal').addEventListener('click', function(e) {
    if (e.target === this) closePlacementModal();
});

// ── Search live debounce ──
let searchDebounce;
const searchInput = document.getElementById('search-input');
if (searchInput) {
    searchInput.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            document.getElementById('filter-form').submit();
        }, 600);
    });
}
</script>
<script src="js/alerts.js"></script>
</body>
</html>
