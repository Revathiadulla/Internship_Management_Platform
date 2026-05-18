<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : "";
$session_email = isset($_SESSION['email']) ? $_SESSION['email'] : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding | Complete Your Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@400,0,0,24" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Minimal Navbar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white font-bold text-xl shadow-sm">I</div>
                <span class="text-xl font-bold text-slate-800 tracking-tight">IMP</span>
            </div>
            <a href="login.php" class="text-sm font-medium text-slate-500 hover:text-slate-800 transition-colors">Save & Exit</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6">
        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
            
            <!-- Progress Section -->
            <div class="px-8 pt-8 pb-6 border-b border-slate-100 bg-slate-50/50">
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-800">Complete Your Profile</h1>
                <p class="mt-2 text-sm text-slate-500">Complete your profile to start applying for internships and connecting with employers.</p>
            </div>

            <!-- Form -->
            <form action="application_submit.php" method="POST" enctype="multipart/form-data" class="px-8 py-8 space-y-10">
                
                <!-- 1. Personal Information -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">person</span> Personal Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                            <input type="text" name="first_name" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. Alex" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. Rivera" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name (Auto-filled)</label>
                            <input type="text" name="full_name" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-500 outline-none" value="<?php echo htmlspecialchars($full_name); ?>" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email Address (Auto-filled)</label>
                            <input type="email" name="email" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-slate-500 outline-none" value="<?php echo htmlspecialchars($session_email); ?>" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="+1 (555) 000-0000" required>
                        </div>
                    </div>
                </section>

                <hr class="border-slate-200">

                <!-- 2. Academic Information -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">school</span> Academic Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">College / University Name</label>
                            <input type="text" name="college_name" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. Stanford University" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Course / Degree</label>
                            <input type="text" name="course" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800" placeholder="e.g. B.S. Computer Science" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Year of Study</label>
                            <select name="year_of_study" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 bg-white" required>
                                <option value="" disabled selected>Select year</option>
                                <option value="2026">2026</option>
                                <option value="2027">2027</option>
                                <option value="2028">2028</option>
                                <option value="2029">2029</option>
                                <option value="2030">2030</option>
                                <option value="passed_out">Passed Out</option>
                            </select>
                        </div>
                    </div>
                </section>

                <hr class="border-slate-200">

                <!-- 3. Skills & Resume -->
                <section>
                    <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">work</span> Skills & Resume
                    </h2>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Key Skills <span class="text-slate-400 font-normal">(Comma separated)</span></label>
                            <textarea name="skills" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 resize-none" placeholder="e.g. Python, UI/UX Design, Data Analysis, JavaScript" required></textarea>
                        </div>
                        
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">
                                Resume Upload
                            </label>
                            <div class="border-2 border-dashed border-blue-300 rounded-2xl p-10 text-center bg-white hover:border-blue-500 transition-all relative">
                                <input
                                    type="file"
                                    name="resume"
                                    id="resume"
                                    accept=".pdf,.doc,.docx"
                                    class="hidden"
                                    required>
                                <label for="resume" class="cursor-pointer flex flex-col items-center" id="resume-label">
                                    <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="h-8 w-8 text-blue-600"
                                             fill="none"
                                             viewBox="0 0 24 24"
                                             stroke="currentColor">
                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  stroke-width="2"
                                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 0115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                    </div>
                                    <p class="text-gray-700 font-medium" id="resume-text">
                                        Click to upload or drag and drop
                                    </p>
                                    <p class="text-sm text-gray-400 mt-1" id="resume-hint">
                                        PDF, DOCX up to 5MB
                                    </p>
                                </label>
                                <div id="resume-preview" class="hidden mt-4 pt-4 border-t border-slate-100">
                                    <p class="text-sm text-slate-500 mb-1">Selected file:</p>
                                    <a href="#" id="resume-link" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 hover:underline font-medium relative z-10" onclick="event.stopPropagation();">
                                        <span class="material-symbols-outlined text-[18px]">description</span>
                                        <span id="resume-filename" class="truncate max-w-[200px]">filename.pdf</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <hr class="border-slate-200">

                <!-- 4. Identity Verification -->
                <section>
                    <div class="flex items-start justify-between mb-4">
                        <h2 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-600">verified_user</span> Identity Verification
                        </h2>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Aadhaar Number <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" name="aadhaar_number" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors outline-none text-slate-800 tracking-widest font-mono placeholder:tracking-normal" placeholder="123456789012" pattern="\d{12}" minlength="12" maxlength="12" title="Please enter exactly 12 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">badge</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-2 text-slate-500">
                            <span class="material-symbols-outlined text-[14px]">lock</span>
                            <p class="text-xs">Your Aadhaar number is encrypted and securely stored for official verification only.</p>
                        </div>
                    </div>
                </section>

                <!-- Actions -->
                <div class="pt-6 border-t border-slate-200 flex flex-col-reverse sm:flex-row items-center justify-between gap-4">
                    <button type="button" class="w-full sm:w-auto px-6 py-2.5 border border-slate-300 text-slate-700 font-medium rounded-lg hover:bg-slate-50 transition-colors">
                        Back
                    </button>
                    <button type="submit" class="w-full sm:w-auto px-8 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 shadow-sm shadow-blue-600/20 hover:shadow-md transition-all">
                        Save & Continue
                    </button>
                </div>
                
                <p class="text-center text-xs text-slate-400 mt-4 max-w-md mx-auto">
                    Your information is securely stored and used only for internship verification purposes. 
                    By continuing, you agree to our <a href="#" class="text-blue-600 hover:underline">Terms of Service</a>.
                </p>

            </form>
        </div>
    </main>

<script>
    document.getElementById('resume').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            document.getElementById('resume-text').textContent = 'Click to change file';
            document.getElementById('resume-hint').classList.add('hidden');
            document.getElementById('resume-preview').classList.remove('hidden');
            document.getElementById('resume-filename').textContent = file.name;
            const fileURL = URL.createObjectURL(file);
            document.getElementById('resume-link').href = fileURL;
        } else {
            document.getElementById('resume-text').textContent = 'Click to upload or drag and drop';
            document.getElementById('resume-hint').classList.remove('hidden');
            document.getElementById('resume-preview').classList.add('hidden');
            document.getElementById('resume-link').href = '#';
        }
    });
</script>
</body>
</html>