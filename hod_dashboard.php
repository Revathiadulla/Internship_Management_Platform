<?php
session_start();
define('INCLUDE_CHECK', true);
include_once __DIR__ . '/includes/auth.php';
require_role(['hod', 'admin']);
include "db.php";

// Fetch pending approvals for HOD
$query = "SELECT a.id as app_id, a.user_id, a.status, a.applied_date, a.test_score,
                 COALESCE(i.title, a.internship_name) as title,
                 sp.full_name, sp.college_name, sp.resume_file, sp.resume_url,
                 sp.aadhaar_file, sp.pan_file,
                 a.aadhaar_status, a.pan_status, a.hod_status,
                 u_hr.full_name AS hr_verifier
          FROM internship_applications a
          LEFT JOIN internships i ON a.internship_id = i.id AND a.internship_id > 0
          LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
          LEFT JOIN users u ON a.user_id = u.id
          LEFT JOIN users u_hr ON a.aadhaar_verified_by = u_hr.id
          WHERE a.status = 'Forwarded to HOD' AND a.hod_status = 'pending' AND a.is_deleted = 0
          ORDER BY a.applied_date DESC";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HOD Approval Dashboard | IMP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@300,0,0,24" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
  <div class="min-h-screen flex flex-col">
    <!-- Navbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center gap-3">
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
          <span class="text-xl font-bold text-blue-600">IMP</span>
          <span class="text-xs bg-slate-100 text-slate-700 font-semibold px-2.5 py-0.5 rounded-full">HOD Portal</span>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-sm font-medium text-slate-600">Welcome, HOD</span>
          <a href="logout.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs text-red-650 hover:bg-red-50 rounded-lg transition font-bold">
            <span class="material-symbols-outlined text-[16px]">logout</span> Log out
          </a>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Student Internship Applications</h1>
        <p class="text-sm text-slate-500 mt-1">Review and approve internship selection requests forwarded by HR.</p>
      </div>

      <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                <th class="py-4 px-6">Student Name</th>
                <th class="py-4 px-6">Internship Applied</th>
                <th class="py-4 px-6">Applied Date</th>
                <th class="py-4 px-6">Test Score</th>
                <th class="py-4 px-6">Aadhaar Status</th>
                <th class="py-4 px-6">PAN Status</th>
                <th class="py-4 px-6">HR Verified By</th>
                <th class="py-4 px-6 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
              <?php if (mysqli_num_rows($result) === 0): ?>
                <tr>
                  <td colspan="8" class="py-16 text-center">
                    <span class="material-symbols-outlined text-[48px] text-slate-300">inbox</span>
                    <p class="mt-2 text-slate-400 font-medium">No pending applications to review.</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php while ($app = mysqli_fetch_assoc($result)): 
                    $resume = !empty($app['resume_file']) ? trim($app['resume_file']) : '';
                    $resume_url = !empty($app['resume_url']) ? trim($app['resume_url']) : '';
                    $is_remote = false;
                    $view_href = '#';
                    if ($resume_url !== '' && (strpos($resume_url, 'http://') === 0 || strpos($resume_url, 'https://') === 0)) {
                        $view_href = getDocumentViewUrl($resume_url);
                        $is_remote = true;
                    } elseif ($resume !== '') {
                        $view_href = getDocumentViewUrl("resume_serve.php?file=" . urlencode(basename($resume)) . "&mode=view");
                    }
                ?>
                  <tr class="hover:bg-slate-50/50 transition">
                    <td class="py-4 px-6">
                      <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($app['full_name']); ?></div>
                      <div class="text-xs text-slate-400"><?php echo htmlspecialchars($app['college_name']); ?></div>
                    </td>
                    <td class="py-4 px-6">
                      <div class="font-medium text-slate-800"><?php echo htmlspecialchars($app['title']); ?></div>
                    </td>
                    <td class="py-4 px-6 text-slate-500 font-medium">
                      <?php echo date('M d, Y', strtotime($app['applied_date'])); ?>
                    </td>
                    <td class="py-4 px-6 font-semibold text-slate-850">
                      <?php echo isset($app['test_score']) ? $app['test_score'] : 'N/A'; ?>
                    </td>
                    <td class="py-4 px-6">
                      <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase border bg-green-100 text-green-700 border-green-200">
                        <?php echo htmlspecialchars($app['aadhaar_status']); ?>
                      </span>
                    </td>
                    <td class="py-4 px-6">
                      <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase border bg-green-100 text-green-700 border-green-200">
                        <?php echo htmlspecialchars($app['pan_status']); ?>
                      </span>
                    </td>
                    <td class="py-4 px-6 text-slate-500 font-medium">
                      <?php echo htmlspecialchars($app['hr_verifier'] ?: 'HR'); ?>
                    </td>
                    <td class="py-4 px-6">
                      <div class="flex flex-wrap items-center justify-center gap-2">
                        <?php if ($resume !== '' || $resume_url !== ''): ?>
                          <a href="<?php echo htmlspecialchars(getDocumentViewUrl($view_href)); ?>" target="_blank" rel="noopener noreferrer" class="px-2 py-1 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded text-xs font-semibold transition" title="View Resume">
                            Resume
                          </a>
                        <?php endif; ?>
                        <?php if (!empty($app['aadhaar_file'])): 
                          $aadhaar_href = "view_document.php?file=" . urlencode(basename($app['aadhaar_file']));
                          if (strpos($app['aadhaar_file'], 'http://') === 0 || strpos($app['aadhaar_file'], 'https://') === 0) {
                              $aadhaar_href = $app['aadhaar_file'];
                          }
                          $aadhaar_view_href = getDocumentViewUrl($aadhaar_href);
                        ?>
                          <a href="<?php echo htmlspecialchars($aadhaar_view_href); ?>" target="_blank" rel="noopener noreferrer" class="px-2 py-1 text-amber-700 bg-amber-50 hover:bg-amber-100 rounded text-xs font-semibold transition" title="View Aadhaar">
                            Aadhaar
                          </a>
                        <?php endif; ?>
                        <?php if (!empty($app['pan_file'])): 
                          $pan_href = "view_document.php?file=" . urlencode(basename($app['pan_file']));
                          if (strpos($app['pan_file'], 'http://') === 0 || strpos($app['pan_file'], 'https://') === 0) {
                              $pan_href = $app['pan_file'];
                          }
                          $pan_view_href = getDocumentViewUrl($pan_href);
                        ?>
                          <a href="<?php echo htmlspecialchars($pan_view_href); ?>" target="_blank" rel="noopener noreferrer" class="px-2 py-1 text-cyan-700 bg-cyan-50 hover:bg-cyan-100 rounded text-xs font-semibold transition" title="View PAN">
                            PAN
                          </a>
                        <?php endif; ?>
                        
                        <a href="hod_update_application.php?app_id=<?php echo $app['app_id']; ?>&action=approve" class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded transition">
                          Approve
                        </a>
                        <a href="hod_update_application.php?app_id=<?php echo $app['app_id']; ?>&action=reject" class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded transition">
                          Reject
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
