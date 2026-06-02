<?php
$is_cli = php_sapi_name() === 'cli';
if (!$is_cli) {
    session_start();
    include_once __DIR__ . '/includes/auth.php';
    require_module_access('users');
}
include 'db.php';
include_once __DIR__ . '/includes/hr_module_helpers.php';
ensure_module_schema($conn);

function seed_student_logs_helper($conn, $user_id, $posting_id, $application_id, $mentor_id, $student_name) {
    // Delete existing logs
    mysqli_query($conn, "DELETE FROM daily_logs WHERE user_id = $user_id");
    // Clear notifications and activities for clean state
    $escaped_name = mysqli_real_escape_string($conn, $student_name);
    mysqli_query($conn, "DELETE FROM mentor_notifications WHERE mentor_id = $mentor_id AND message LIKE '%$escaped_name%'");
    mysqli_query($conn, "DELETE FROM mentor_activity_logs WHERE student_id = $user_id");

    // We'll vary the logs depending on the index of the student to make the data look organic
    $is_overdue = ($user_id % 3 === 0); // make some students overdue
    
    $log_templates = [
        ['-6 days', 'Completed initial repository setup, configured folder structure, and set up local host definitions.', 4.0, '📈 Steady Progress', 'Approved', 'Nice clean workspace setup.', 4],
        ['-5 days', 'Designed MySQL database schemas for candidates, applications, daily logs, and notifications. Tested indices.', 8.0, '🔥 High Productivity', 'Approved', 'Indexes are well planned.', 5],
        ['-4 days', 'Built application submission REST endpoint with file upload handlers and extension verification.', 6.5, '📈 Steady Progress', 'Reviewed', 'Upload validation looks solid.', 4],
        ['-3 days', 'Developed core Tailwind dashboard layout with responsive mobile navigation drawer and dynamic modals.', 8.0, '🔥 High Productivity', 'Approved', 'UI matches standard guidelines.', 5],
        ['-2 days', 'Drafted email alert dispatchers using PHPMailer. Handled exception loops and delivery logging.', 7.0, '📈 Steady Progress', 'Needs Update', 'Please add email validation patterns in student forms.', 3],
    ];

    foreach ($log_templates as $tmpl) {
        $log_date = date('Y-m-d', strtotime($tmpl[0]));
        $tasks = mysqli_real_escape_string($conn, $tmpl[1]);
        $hours = $tmpl[2];
        $focus = $tmpl[3];
        $status = $tmpl[4];
        $feedback = mysqli_real_escape_string($conn, $tmpl[5]);
        $rating = $tmpl[6];
        $attachment = "NULL";
        if ($tmpl[0] === '-5 days') {
            $attachment = "'uploads/daily_logs/internship_report.pdf'";
        } elseif ($tmpl[0] === '-3 days') {
            $attachment = "'uploads/daily_logs/sprint_notes.pdf'";
        }
        
        mysqli_query($conn, "INSERT INTO daily_logs (user_id, internship_id, application_id, tasks_completed, time_spent, focus_level, issues_faced, next_plan, log_date, status, mentor_feedback, mentor_rating, reviewed_by, reviewed_at, attachment_path) 
            VALUES ($user_id, $posting_id, $application_id, '$tasks', $hours, '$focus', 'None', 'Continue with next milestones.', '$log_date', '$status', '$feedback', $rating, $mentor_id, CURRENT_TIMESTAMP, $attachment)");
            
        // Seed mentor feedback
        $fb_title = "Evaluation for " . date('M d, Y', strtotime($tmpl[0]));
        mysqli_query($conn, "INSERT INTO mentor_feedback (user_id, log_id, feedback_title, given_by, comments, rating, status) 
            VALUES ($user_id, LAST_INSERT_ID(), '$fb_title', '" . ($mentor_id == 5 ? 'Venkatreddy' : 'john smith') . "', '$feedback', $rating, '$status')");
            
        // Seed mentor activity log
        $act_details = "Reviewed log for $student_name on $log_date. Status: $status. Grade: $rating/5.";
        $days_back = intval(str_replace(' days', '', $tmpl[0]));
        mysqli_query($conn, "INSERT INTO mentor_activity_logs (mentor_id, action_type, student_id, log_id, details, created_at) 
            VALUES ($mentor_id, 'review', $user_id, LAST_INSERT_ID(), '$act_details', DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $days_back DAY))");
    }

    if (!$is_overdue) {
        // Seed recent log submissions
        $log_recent = [
            ['-1 day', 'Implemented profile visual edit layout and added profile picture update controller.', 7.5, '🔥 High Productivity', 'Submitted', 'uploads/daily_logs/ui_feedback.png'],
            ['today', 'Configured server-sent events for floating notifications and released PHP session write locks.', 8.0, '🔥 High Productivity', 'Submitted', 'uploads/daily_logs/sprint_notes.pdf']
        ];
        
        foreach ($log_recent as $tmpl) {
            $log_date = date('Y-m-d', strtotime($tmpl[0]));
            $tasks = mysqli_real_escape_string($conn, $tmpl[1]);
            $hours = $tmpl[2];
            $focus = $tmpl[3];
            $status = $tmpl[4];
            $attachment = "'$tmpl[5]'";
            
            mysqli_query($conn, "INSERT INTO daily_logs (user_id, internship_id, application_id, tasks_completed, time_spent, focus_level, issues_faced, next_plan, log_date, status, attachment_path) 
                VALUES ($user_id, $posting_id, $application_id, '$tasks', $hours, '$focus', 'Minor lag on SSE streams', 'Resolve minor lags', '$log_date', '$status', $attachment)");
            
            // Seed a notification to mentor
            $notif_title = "New Log Submitted";
            $notif_msg = "$student_name has submitted a daily activity log for " . date('M d, Y', strtotime($tmpl[0]));
            $days_back = ($tmpl[0] === '-1 day' ? -1 : 0);
            mysqli_query($conn, "INSERT INTO mentor_notifications (mentor_id, title, type, message, is_read, created_at) 
                VALUES ($mentor_id, '$notif_title', 'log_submission', '$notif_msg', 0, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL $days_back DAY))");
        }
    } else {
        // Seed an overdue notification to mentor
        $notif_title = "Log Submission Overdue";
        $notif_msg = "$student_name has not submitted a daily activity log for 3+ days.";
        mysqli_query($conn, "INSERT INTO mentor_notifications (mentor_id, title, type, message, is_read, created_at) 
            VALUES ($mentor_id, '$notif_title', 'reminder', '$notif_msg', 0, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY))");
    }
}


$postings = [
    ['Software Developer Intern', 'Engineering', 'Hyderabad', 4],
    ['Data Analyst Intern', 'Analytics', 'Bengaluru', 3],
    ['UI/UX Design Intern', 'Design', 'Remote', 2],
    ['Cloud Support Trainee', 'Infrastructure', 'Chennai', 3],
    ['HR Operations Intern', 'Human Resources', 'Hyderabad', 2],
    ['QA Automation Intern', 'Quality Assurance', 'Pune', 3],
    ['Business Development Intern', 'Sales', 'Mumbai', 4],
    ['Cybersecurity Intern', 'Security', 'Remote', 2],
];

$posting_ids = [];
foreach ($postings as $posting) {
    [$title, $department, $location, $openings] = $posting;
    $stmt = $conn->prepare("SELECT id FROM job_postings WHERE title = ? LIMIT 1");
    $stmt->bind_param('s', $title);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        $posting_ids[] = (int) $existing['id'];
        continue;
    }
    $type = 'Internship';
    $description = "Demo opening for $title.";
    $requirements = 'Communication, problem solving, and basic domain knowledge.';
    $status = 'Active';
    $deadline = date('Y-m-d', strtotime('+45 days'));
    $stmt = $conn->prepare("INSERT INTO job_postings (title, department, posting_type, location, openings, description, requirements, status, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssissss', $title, $department, $type, $location, $openings, $description, $requirements, $status, $deadline);
    $stmt->execute();
    $posting_ids[] = $stmt->insert_id;
}

$names = [
    'Aarav Sharma', 'Diya Reddy', 'Kabir Mehta', 'Ananya Rao', 'Rohan Nair',
    'Isha Patel', 'Vikram Singh', 'Meera Iyer', 'Aditya Kumar', 'Sneha Das',
    'Rahul Verma', 'Priya Menon', 'Nikhil Jain', 'Kavya S', 'Arjun Thomas',
    'Neha Kulkarni', 'Sanjay Gupta', 'Pooja Shah', 'Kiran B', 'Revathi M',
];
$colleges = ['ABC Institute of Technology', 'City MCA College', 'Hyderabad University', 'National PG College', 'MCA Institute of Science'];
$skills = ['Python, Django, MySQL', 'Java, Spring Boot, SQL', 'HTML, CSS, Figma', 'Excel, Power BI, Python', 'Linux, AWS, Networking', 'Manual Testing, Selenium', 'Communication, CRM, Sales', 'Cybersecurity, SIEM, Linux'];
$statuses = array_merge(
    array_fill(0, 5, 'Applied'),
    array_fill(0, 3, 'Test Completed'),
    array_fill(0, 2, 'HR Round'),
    array_fill(0, 2, 'HOD Approved'),
    array_fill(0, 6, 'Selected'),
    array_fill(0, 2, 'Active Intern')
);
$verification_statuses = ['Pending', 'Verified', 'Rejected'];
$password = password_hash('Demo@123', PASSWORD_DEFAULT);
$created_apps = 0;

foreach ($names as $index => $name) {
    $email = 'demo.student' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '@imp.local';
    $phone = '900000' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
    $role = 'student';

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user_row = $stmt->get_result()->fetch_assoc();
    if ($user_row) {
        $user_id = (int) $user_row['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param('sssss', $name, $email, $password, $role, $phone);
        $stmt->execute();
        $user_id = $stmt->insert_id;
    }

    $college = $colleges[$index % count($colleges)];
    $skill_text = $skills[$index % count($skills)];
    $course = 'MCA';
    $year = (string) ((($index % 2) + 1) . ' Year');
    $resume = '';
    $stmt = $conn->prepare("SELECT id FROM student_profiles WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $profile_row = $stmt->get_result()->fetch_assoc();
    if ($profile_row) {
        $stmt = $conn->prepare("UPDATE student_profiles SET full_name = ?, email = ?, phone = ?, college_name = ?, course = ?, year_of_study = ?, skills = ? WHERE user_id = ?");
        $stmt->bind_param('sssssssi', $name, $email, $phone, $college, $course, $year, $skill_text, $user_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO student_profiles (user_id, full_name, email, phone, college_name, course, year_of_study, skills, resume_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssssss', $user_id, $name, $email, $phone, $college, $course, $year, $skill_text, $resume);
        $stmt->execute();
    }

    $posting_id = $posting_ids[$index % count($posting_ids)];
    $posting_title = $postings[$index % count($postings)][0];
    $status = $statuses[$index % count($statuses)];
    $verification = $verification_statuses[$index % count($verification_statuses)];

    $stmt = $conn->prepare("SELECT id FROM internship_applications WHERE user_id = ? AND job_posting_id = ? LIMIT 1");
    $stmt->bind_param('ii', $user_id, $posting_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        continue;
    }

    $education = 'Pursuing';
    $duration = '3 Months';
    $reason = 'Demo application for HR workflow presentation.';
    $applied_date = date('Y-m-d H:i:s', strtotime('-' . (20 - $index) . ' days'));
    $stmt = $conn->prepare("INSERT INTO internship_applications
        (user_id, internship_id, internship_name, reason_for_applying, relevant_skills, preferred_duration, status, applied_date, education_status, college_name, year_of_study, verification_status, full_name, email, phone, skills, is_deleted, job_posting_id)
        VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");
    $stmt->bind_param('issssssssssssssi', $user_id, $posting_title, $reason, $skill_text, $duration, $status, $applied_date, $education, $college, $year, $verification, $name, $email, $phone, $skill_text, $posting_id);
    $stmt->execute();
    $application_id = $stmt->insert_id;
    $created_apps++;

    if ($status !== 'Applied') {
        $old_status = 'Applied';
        $updated_by_role = 'admin';
        $updated_by_name = 'Demo Seeder';
        $notes = 'Demo workflow history.';
        $stmt = $conn->prepare("INSERT INTO application_status_history (application_id, old_status, new_status, updated_by_role, updated_by_name, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssss', $application_id, $old_status, $status, $updated_by_role, $updated_by_name, $notes);
        $stmt->execute();
    }

    // ── Mentor Assignment & Log Seeding ─────────────────────────────────────
    // Ensure mentors exist
    $mentor_data = [
        [5, 'Venkatreddy', 'venkatreddy@gmail.com', 'mentor'],
        [11, 'john smith', 'john@gmail.com', 'mentor']
    ];
    foreach ($mentor_data as [$m_id, $m_name, $m_email, $m_role]) {
        $chk = mysqli_query($conn, "SELECT id FROM users WHERE id = $m_id LIMIT 1");
        if ($chk && mysqli_num_rows($chk) === 0) {
            $m_pass = password_hash('Demo@123', PASSWORD_DEFAULT);
            mysqli_query($conn, "INSERT INTO users (id, full_name, email, password, role, status) VALUES ($m_id, '$m_name', '$m_email', '$m_pass', '$m_role', 'Active')");
        } else {
            mysqli_query($conn, "UPDATE users SET role = 'mentor', status = 'Active' WHERE id = $m_id");
        }
    }

    if (in_array($status, ['Selected', 'Started', 'Active Intern', 'Internship Started'], true)) {
        $mentor_id = ($index % 2 === 0) ? 5 : 11;
        
        // Deactivate previous
        mysqli_query($conn, "UPDATE mentor_assignments SET status = 'inactive' WHERE student_id = $user_id AND application_id = $application_id");
        
        // Insert active assignment
        mysqli_query($conn, "INSERT INTO mentor_assignments (mentor_id, student_id, application_id, assigned_by, status) VALUES ($mentor_id, $user_id, $application_id, 1, 'active')");
        
        // Update application
        mysqli_query($conn, "UPDATE internship_applications SET mentor_id = $mentor_id WHERE id = $application_id");

        // Seed logs via helper
        seed_student_logs_helper($conn, $user_id, $posting_id, $application_id, $mentor_id, $name);
    }
}

// Post-loop sync: Ensure all existing active applications have mentor assignments and logs
$active_apps_res = mysqli_query($conn, "SELECT id, user_id, job_posting_id, status FROM internship_applications WHERE status IN ('Selected', 'Started', 'Active Intern', 'Internship Started') AND is_deleted = 0");
$loop_idx = 0;
while ($app_row = mysqli_fetch_assoc($active_apps_res)) {
    $app_id = (int)$app_row['id'];
    $st_id = (int)$app_row['user_id'];
    $post_id = (int)$app_row['job_posting_id'];
    
    $mentor_id = ($loop_idx % 2 === 0) ? 5 : 11;
    $loop_idx++;
    
    // Check if there is an active assignment
    $asg_check = mysqli_query($conn, "SELECT id FROM mentor_assignments WHERE student_id = $st_id AND application_id = $app_id AND status = 'active' LIMIT 1");
    if ($asg_check && mysqli_num_rows($asg_check) === 0) {
        // Deactivate previous
        mysqli_query($conn, "UPDATE mentor_assignments SET status = 'inactive' WHERE student_id = $st_id AND application_id = $app_id");
        // Insert active
        mysqli_query($conn, "INSERT INTO mentor_assignments (mentor_id, student_id, application_id, assigned_by, status) VALUES ($mentor_id, $st_id, $app_id, 1, 'active')");
    }
    
    // Update application mentor_id
    mysqli_query($conn, "UPDATE internship_applications SET mentor_id = $mentor_id WHERE id = $app_id");
    
    // Check if daily logs already exist for this student. If not, seed them.
    $log_check = mysqli_query($conn, "SELECT id FROM daily_logs WHERE user_id = $st_id LIMIT 1");
    if ($log_check && mysqli_num_rows($log_check) === 0) {
        $st_name_q = mysqli_query($conn, "SELECT full_name FROM users WHERE id = $st_id LIMIT 1");
        $st_name = ($st_name_q && $st_name_row = mysqli_fetch_assoc($st_name_q)) ? $st_name_row['full_name'] : "Student";
        seed_student_logs_helper($conn, $st_id, $post_id, $app_id, $mentor_id, $st_name);
    }
}


// ── Seed Company Recruiter Users & Profiles ─────────────────────────────────
$companies = [
    [
        'email' => 'demo.company01@imp.local',
        'name' => 'Sarah Jenkins',
        'phone' => '9876540001',
        'company_name' => 'Nexus Technologies',
        'industry' => 'Software & IT',
        'website' => 'https://nexustech.imp.local',
        'size' => '51-200 Employees',
        'plan' => 'Premium'
    ],
    [
        'email' => 'demo.company02@imp.local',
        'name' => 'David Miller',
        'phone' => '9876540002',
        'company_name' => 'Innovate Lab',
        'industry' => 'Electronics & hardware',
        'website' => 'https://innovatelab.imp.local',
        'size' => '11-50 Employees',
        'plan' => 'Basic'
    ]
];

$company_user_ids = [];
foreach ($companies as $comp) {
    // Clear old data for idempotent seeding
    mysqli_query($conn, "DELETE FROM users WHERE email = '{$comp['email']}'");
    
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, phone, status) VALUES (?, ?, ?, 'company', ?, 'Active')");
    $stmt->bind_param("ssss", $comp['name'], $comp['email'], $password, $comp['phone']);
    $stmt->execute();
    $c_uid = $stmt->insert_id;
    $stmt->close();
    $company_user_ids[] = $c_uid;
    
    // Clear profile
    mysqli_query($conn, "DELETE FROM company_profiles WHERE user_id = $c_uid");
    
    $stmt = $conn->prepare("INSERT INTO company_profiles (user_id, company_name, industry_type, website, company_size, plan_selected) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $c_uid, $comp['company_name'], $comp['industry'], $comp['website'], $comp['size'], $comp['plan']);
    $stmt->execute();
    $stmt->close();
}

$c1_uid = $company_user_ids[0];
$c2_uid = $company_user_ids[1];

// Clear old notifications, hiring requests, shortlists, contacts for these companies
mysqli_query($conn, "DELETE FROM company_notifications WHERE company_id IN ($c1_uid, $c2_uid)");
mysqli_query($conn, "DELETE FROM hiring_requests WHERE company_id IN ($c1_uid, $c2_uid)");
mysqli_query($conn, "DELETE FROM company_shortlists WHERE company_id IN ($c1_uid, $c2_uid)");
mysqli_query($conn, "DELETE FROM company_contacts WHERE company_id IN ($c1_uid, $c2_uid)");

// ── Seed Hiring Requests ────────────────────────────────────────────────────
$requests_data = [
    [$c1_uid, 'Node.js Developer Intern', 'Engineering', 2, 'Looking for junior backend developers', 'Node.js, Express, MySQL', 'Approved'],
    [$c1_uid, 'Figma UI/UX Intern', 'Design', 1, 'Looking for creative UI designers', 'Figma, prototyping', 'Pending'],
    [$c2_uid, 'QA Automation Intern', 'Quality Assurance', 2, 'Looking for QA testers', 'Selenium, Python', 'Pending'],
    [$c2_uid, 'React Native Developer', 'Engineering', 1, 'Looking for mobile app devs', 'React Native, Javascript', 'Rejected']
];
foreach ($requests_data as $req) {
    $stmt = $conn->prepare("INSERT INTO hiring_requests (company_id, title, department, openings, description, requirements, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $req[0], $req[1], $req[2], $req[3], $req[4], $req[5], $req[6]);
    $stmt->execute();
    $stmt->close();
}

// ── Seed Recruiter Notifications ────────────────────────────────────────────
$notifications_data = [
    [$c1_uid, 'hiring_approved', 'Hiring Request Approved', 'Your request for a Node.js Developer Intern has been approved by HR.', 0],
    [$c1_uid, 'candidate_applied', 'New Application Received', 'A new candidate has applied for your frontend designer vacancy.', 0],
    [$c1_uid, 'system_update', 'Welcome to IMP', 'Your corporate recruiter dashboard is now active. Explore the talent pool.', 1],
    [$c2_uid, 'hiring_rejected', 'Hiring Request Rejected', 'Your request for a React Native Developer has been declined by HR due to budget constraints.', 0]
];
foreach ($notifications_data as $notif) {
    $stmt = $conn->prepare("INSERT INTO company_notifications (company_id, type, title, message, is_read) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $notif[0], $notif[1], $notif[2], $notif[3], $notif[4]);
    $stmt->execute();
    $stmt->close();
}

// ── Seed Shortlists and Contacts ────────────────────────────────────────────
$student_ids = [];
$q_stu = mysqli_query($conn, "SELECT id, full_name FROM users WHERE role = 'student' LIMIT 3");
if ($q_stu) {
    while ($row = mysqli_fetch_assoc($q_stu)) {
        $student_ids[] = $row;
    }
}

if (count($student_ids) >= 3) {
    $s1_id = $student_ids[0]['id'];
    $s1_name = $student_ids[0]['full_name'];
    $s2_id = $student_ids[1]['id'];
    $s2_name = $student_ids[1]['full_name'];
    $s3_id = $student_ids[2]['id'];
    $s3_name = $student_ids[2]['full_name'];

    mysqli_query($conn, "INSERT INTO company_shortlists (company_id, candidate_id) VALUES ($c1_uid, $s1_id)");
    mysqli_query($conn, "INSERT INTO company_shortlists (company_id, candidate_id) VALUES ($c1_uid, $s2_id)");
    mysqli_query($conn, "INSERT INTO company_shortlists (company_id, candidate_id) VALUES ($c2_uid, $s3_id)");

    mysqli_query($conn, "INSERT INTO company_contacts (company_id, candidate_id, message) VALUES ($c1_uid, $s1_id, 'Hello $s1_name, we reviewed your profile and want to schedule an interview.')");
    
    mysqli_query($conn, "INSERT INTO activity_logs (user_id, user_name, user_role, action_type, details) VALUES ($c1_uid, 'Sarah Jenkins', 'company', 'Shortlist Add', 'Sarah Jenkins shortlisted candidate $s1_name')");
    mysqli_query($conn, "INSERT INTO activity_logs (user_id, user_name, user_role, action_type, details) VALUES ($c1_uid, 'Sarah Jenkins', 'company', 'Shortlist Add', 'Sarah Jenkins shortlisted candidate $s2_name')");
    mysqli_query($conn, "INSERT INTO activity_logs (user_id, user_name, user_role, action_type, details) VALUES ($c1_uid, 'Sarah Jenkins', 'company', 'Candidate Contact', 'Sarah Jenkins contacted candidate $s1_name')");
    mysqli_query($conn, "INSERT INTO activity_logs (user_id, user_name, user_role, action_type, details) VALUES ($c2_uid, 'David Miller', 'company', 'Shortlist Add', 'David Miller shortlisted candidate $s3_name')");
}

$general_activities = [
    ['admin', 'Demo Seeder', 'System Init', 'Database migration schema and seeding data execution.'],
    ['hr', 'HR Manager', 'Status Change', 'Updated candidate application status to Interview Scheduled.'],
    ['coordinator', 'Academic Coord', 'Profile Verification', 'Verified student profile credentials and PAN card details.']
];
foreach ($general_activities as $act) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_name, user_role, action_type, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $act[1], $act[0], $act[2], $act[3]);
    $stmt->execute();
    $stmt->close();
}

sync_candidates_from_applications($conn);

$message = "Demo data ready. Created $created_apps new application(s). Demo student password: Demo@123";
if ($is_cli) {
    echo $message . PHP_EOL;
    exit();
}

set_flash($message);
header('Location: reports.php');
exit();
