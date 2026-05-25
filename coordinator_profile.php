<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'coordinator') {
    header("Location: login.php");
    exit();
}
include "db.php";

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

$section = isset($_GET['section']) ? trim($_GET['section']) : 'profile';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($full_name)) {
            $error_msg = "Full Name cannot be empty.";
        } else {
            // Check for photo upload
            $profile_photo_path = null;
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_photo']['tmp_name'];
                $file_name = $_FILES['profile_photo']['name'];
                $file_size = $_FILES['profile_photo']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_ext, $allowed_exts)) {
                    $error_msg = "Invalid file type. Allowed formats: " . implode(', ', $allowed_exts);
                } elseif ($file_size > 2 * 1024 * 1024) { // 2MB limit
                    $error_msg = "File size exceeds the 2MB limit.";
                } else {
                    $upload_dir = 'uploads/avatars/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    // Generate safe unique name
                    $new_file_name = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        $profile_photo_path = $dest_path;
                        
                        // Delete old profile photo file if it exists
                        $old_photo_query = mysqli_query($conn, "SELECT profile_photo FROM users WHERE id = $user_id");
                        $old_photo_row = mysqli_fetch_assoc($old_photo_query);
                        if ($old_photo_row && !empty($old_photo_row['profile_photo']) && file_exists($old_photo_row['profile_photo'])) {
                            @unlink($old_photo_row['profile_photo']);
                        }
                    } else {
                        $error_msg = "Failed to save uploaded photo.";
                    }
                }
            }
            
            if (empty($error_msg)) {
                if ($profile_photo_path) {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, phone = ?, profile_photo = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssi", $full_name, $phone, $profile_photo_path, $user_id);
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "ssi", $full_name, $phone, $user_id);
                }
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = "Profile updated successfully!";
                    $_SESSION['user_name'] = $full_name;
                } else {
                    $error_msg = "Error updating profile in database.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_msg = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_msg = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_msg = "New password must be at least 6 characters long.";
        } else {
            $pwd_query = mysqli_query($conn, "SELECT password FROM users WHERE id = $user_id");
            $pwd_row = mysqli_fetch_assoc($pwd_query);
            
            if ($pwd_row && password_verify($current_password, $pwd_row['password'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $new_hash, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = "Password updated successfully!";
                } else {
                    $error_msg = "Failed to update password in database.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_msg = "Incorrect current password.";
            }
        }
    }
}

// Fetch current user details
$user_res = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user_data = mysqli_fetch_assoc($user_res);
$full_name = $user_data['full_name'] ?? '';
$email = $user_data['email'] ?? '';
$phone = $user_data['phone'] ?? '';
$profile_photo = $user_data['profile_photo'] ?? '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>Coordinator Profile - IMP</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
        <style>
                 body { font-family: 'Inter', sans-serif; }
                 .material-symbols-outlined {
                         font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                         vertical-align: middle;
                 }
                 aside {
                         transition: transform 0.3s ease-in-out;
                 }
                 main {
                         transition: margin-left 0.3s ease-in-out;
                         min-width: 0;
                         overflow-x: hidden;
                 }
                 @media (max-width: 767px) {
                         aside {
                                 transform: translateX(-100%);
                         }
                         main {
                                 margin-left: 0 !important;
                         }
                         body.sidebar-open aside {
                                 transform: translateX(0);
                         }
                 }
                 @media (min-width: 768px) {
                         body.sidebar-closed aside {
                                 transform: translateX(-100%);
                         }
                         body.sidebar-closed main {
                                 margin-left: 0 !important;
                         }
                 }
        </style>
</head>
<body class="bg-gray-100 text-gray-800">
        <!-- SideNavBar -->
        <aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 font-sans text-sm font-medium">
                <div class="px-6 mb-8">
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
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 ml-1">Coordinator Portal</p>
                </div>
                <nav class="flex-1 space-y-1">
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_dashboard.php">
                                <span class="material-symbols-outlined">dashboard</span>
                                <span>Dashboard</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_internships.php">
                                <span class="material-symbols-outlined">work</span>
                                <span>Postings</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_candidates.php">
                                <span class="material-symbols-outlined">group</span>
                                <span>Candidates</span>
                        </a>

                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_daily_logs.php">
                                <span class="material-symbols-outlined">monitoring</span>
                                <span>Daily Logs Monitoring</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_reports.php">
                                <span class="material-symbols-outlined">analytics</span>
                                <span>Reports</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_teams.php">
                                <span class="material-symbols-outlined">manage_accounts</span>
                                <span>Team Management</span>
                        </a>
                </nav>
                <div class="mt-auto border-t border-gray-200 pt-4">
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="#">
                                <span class="material-symbols-outlined">help</span>
                                <span>Help Center</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="logout.php">
                                <span class="material-symbols-outlined">logout</span>
                                <span>Logout</span>
                        </a>
                </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-60 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <?php
                $header_uid = $_SESSION['user_id'];
                $header_res = mysqli_query($conn, "SELECT full_name, profile_photo FROM users WHERE id = $header_uid");
                $header_user = mysqli_fetch_assoc($header_res);
                $header_name = $header_user['full_name'] ?? 'Coordinator';
                $header_photo = $header_user['profile_photo'] ?? '';
                ?>
                <header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-8 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <button id="sidebar-toggle" class="p-1 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none cursor-pointer">
                                        <span class="material-symbols-outlined text-gray-600 text-2xl">menu</span>
                                </button>
                                <h2 class="text-lg font-bold text-gray-800">My Profile</h2>
                        </div>
                        
                        <!-- Profile Dropdown Section -->
                        <div class="relative" id="profile-container">
                                <button id="profile-menu-button" class="flex items-center gap-2 focus:outline-none cursor-pointer group">
                                        <span class="text-sm font-semibold text-gray-700 group-hover:text-blue-600 transition-colors hidden sm:inline-block">
                                                <?php echo htmlspecialchars($header_name); ?>
                                        </span>
                                        <div class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 shadow-sm group-hover:border-blue-500 transition-colors">
                                                <?php if (!empty($header_photo) && file_exists($header_photo)): ?>
                                                        <img src="<?php echo htmlspecialchars($header_photo); ?>" alt="Profile" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($header_name); ?>&background=0D8ABC&color=fff" alt="Profile" class="w-full h-full object-cover">
                                                <?php endif; ?>
                                        </div>
                                        <span class="material-symbols-outlined text-gray-500 text-[18px] group-hover:text-blue-600 transition-colors">arrow_drop_down</span>
                                </button>
                                
                                <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2 z-50">
                                        <a href="coordinator_profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                <span class="material-symbols-outlined text-gray-400 text-[20px]">account_circle</span>
                                                <span>My Profile</span>
                                        </a>
                                        <a href="coordinator_profile.php?section=settings" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                <span class="material-symbols-outlined text-gray-400 text-[20px]">settings</span>
                                                <span>Settings</span>
                                        </a>
                                        <hr class="my-1 border-gray-100">
                                        <a href="logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                <span class="material-symbols-outlined text-red-400 text-[20px]">logout</span>
                                                <span>Logout</span>
                                        </a>
                                </div>
                        </div>
                </header>

                <!-- Profile Dashboard Content -->
                <div class="flex-1 p-8 space-y-6 max-w-4xl mx-auto w-full">
                        <?php if ($success_msg): ?>
                            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200 flex items-center gap-2">
                                <span class="material-symbols-outlined text-green-500">check_circle</span>
                                <span><?php echo $success_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_msg): ?>
                            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200 flex items-center gap-2">
                                <span class="material-symbols-outlined text-red-500">error</span>
                                <span><?php echo $error_msg; ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <!-- Banner & Avatar Section -->
                                <div class="h-32 bg-gradient-to-r from-blue-500 to-indigo-600 relative"></div>
                                <div class="px-8 pb-6 relative flex flex-col sm:flex-row items-center sm:items-end gap-4 -mt-16 mb-4">
                                        <div class="w-32 h-32 rounded-full border-4 border-white bg-white overflow-hidden shadow-md">
                                                <?php if (!empty($profile_photo) && file_exists($profile_photo)): ?>
                                                        <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Avatar" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=0D8ABC&color=fff" alt="Avatar" class="w-full h-full object-cover">
                                                <?php endif; ?>
                                        </div>
                                        <div class="text-center sm:text-left pb-2 flex-1">
                                                <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($full_name); ?></h1>
                                                <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Coordinator</p>
                                        </div>
                                </div>

                                <!-- Navigation Tabs -->
                                <div class="border-b border-gray-200 px-8 flex gap-6">
                                        <a href="coordinator_profile.php?section=profile" class="py-4 border-b-2 font-semibold text-sm transition-colors cursor-pointer <?php echo $section === 'profile' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                                                Account Details
                                        </a>
                                        <a href="coordinator_profile.php?section=settings" class="py-4 border-b-2 font-semibold text-sm transition-colors cursor-pointer <?php echo $section === 'settings' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                                                Security Settings
                                        </a>
                                </div>

                                <!-- Tab Content -->
                                <div class="p-8">
                                        <?php if ($section === 'profile'): ?>
                                                <form action="coordinator_profile.php?section=profile" method="POST" enctype="multipart/form-data" class="space-y-6">
                                                        <input type="hidden" name="update_profile" value="1">
                                                        
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                                <div>
                                                                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">Full Name</label>
                                                                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required
                                                                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                                                </div>
                                                                
                                                                <div>
                                                                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">Email Address</label>
                                                                        <input type="email" value="<?php echo htmlspecialchars($email); ?>" disabled
                                                                               class="w-full bg-gray-100 border border-gray-200 text-gray-500 rounded-lg px-4 py-2 text-sm cursor-not-allowed">
                                                                        <p class="text-xs text-gray-400 mt-1">Email address cannot be changed.</p>
                                                                </div>

                                                                <div>
                                                                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">Phone Number</label>
                                                                        <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>"
                                                                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                                                </div>

                                                                <div>
                                                                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">Profile Photo</label>
                                                                        <input type="file" name="profile_photo" accept="image/*"
                                                                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all">
                                                                        <p class="text-xs text-gray-400 mt-1">Max size: 2MB. Formats: JPG, PNG, GIF, WEBP.</p>
                                                                </div>
                                                        </div>

                                                        <div class="flex justify-end pt-4 border-t border-gray-100">
                                                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-6 py-2.5 rounded-lg shadow-sm transition-colors cursor-pointer">
                                                                        Save Changes
                                                                </button>
                                                        </div>
                                                </form>
                                        <?php elseif ($section === 'settings'): ?>
                                                <form action="coordinator_profile.php?section=settings" method="POST" class="space-y-6 max-w-md">
                                                        <input type="hidden" name="change_password" value="1">
                                                        
                                                        <div class="space-y-4">
                                                                <div>
                                                                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">Current Password</label>
                                                                        <input type="password" name="current_password" required
                                                                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                                                </div>
                                                                
                                                                <div>
                                                                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">New Password</label>
                                                                        <input type="password" name="new_password" required
                                                                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                                                </div>

                                                                <div>
                                                                        <label class="block text-xs font-bold uppercase text-gray-500 tracking-wider mb-2">Confirm New Password</label>
                                                                        <input type="password" name="confirm_password" required
                                                                               class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                                                </div>
                                                        </div>

                                                        <div class="flex justify-end pt-4 border-t border-gray-100">
                                                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm px-6 py-2.5 rounded-lg shadow-sm transition-colors cursor-pointer">
                                                                        Update Password
                                                                </button>
                                                        </div>
                                                </form>
                                        <?php endif; ?>
                                </div>
                        </div>
                </div>
        </main>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
                // Sidebar Toggle
                const toggleBtn = document.getElementById('sidebar-toggle');
                if (toggleBtn) {
                        toggleBtn.addEventListener('click', () => {
                                if (window.innerWidth < 768) {
                                        document.body.classList.toggle('sidebar-open');
                                        document.body.classList.remove('sidebar-closed');
                                } else {
                                        document.body.classList.toggle('sidebar-closed');
                                        document.body.classList.remove('sidebar-open');
                                }
                        });
                }

                // Profile menu dropdown toggle
                const profileBtn = document.getElementById('profile-menu-button');
                const profileDropdown = document.getElementById('profile-dropdown');
                
                if (profileBtn && profileDropdown) {
                        profileBtn.addEventListener('click', function(e) {
                                e.stopPropagation();
                                profileDropdown.classList.toggle('hidden');
                        });
                        
                        document.addEventListener('click', function(e) {
                                if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                                        profileDropdown.classList.add('hidden');
                                }
                        });
                }
        });
        </script>
</body>
</html>
