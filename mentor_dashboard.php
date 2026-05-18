<!DOCTYPE html><html class="light" lang="en"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
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
            "spacing": {
                    "xl": "32px",
                    "lg": "24px",
                    "container-margin": "40px",
                    "md": "16px",
                    "sm": "8px",
                    "xs": "4px",
                    "gutter": "20px",
                    "unit": "4px"
            },
            "fontFamily": {
                    "body-lg": ["Inter"],
                    "label-md": ["Inter"],
                    "body-md": ["Inter"],
                    "h1": ["Inter"],
                    "label-sm": ["Inter"],
                    "h3": ["Inter"],
                    "h2": ["Inter"]
            },
            "fontSize": {
                    "body-lg": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "label-md": ["14px", {"lineHeight": "20px", "fontWeight": "500"}],
                    "body-md": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "h1": ["30px", {"lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                    "h3": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                    "h2": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}]
            }
          },
        },
      }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-background text-on-background" data-mode="connect">
<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6">
<div class="px-6 mb-8 flex items-center gap-3">
<div class="w-10 h-10 bg-primary-container rounded-lg flex items-center justify-center text-white">
<span class="material-symbols-outlined" data-icon="school">school</span>
</div>
<div>
<h1 class="text-lg font-black tracking-tight text-blue-600">Admin Panel</h1>
<p class="text-[10px] text-on-surface-variant font-medium">Management Console</p>
</div>
</div>
<nav class="flex-1 space-y-1">
<a class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 font-sans text-sm font-medium duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                Dashboard
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined" data-icon="work">work</span>
                Postings
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined" data-icon="group">group</span>
                Candidates
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined" data-icon="account_tree">account_tree</span>
                Workflows
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="mentor_daily_logs.html">
<span class="material-symbols-outlined" data-icon="rate_review">rate_review</span>
                Review Daily Logs
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined" data-icon="analytics">analytics</span>
                Reports
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined" data-icon="manage_accounts">manage_accounts</span>
                Users
            </a>
</nav>
<div class="mt-auto border-t border-gray-200 pt-4">
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="#">
<span class="material-symbols-outlined" data-icon="help">help</span>
                Help Center
            </a>
<a class="flex items-center gap-3 text-gray-600 px-4 py-3 font-sans text-sm font-medium hover:bg-gray-100 duration-200 ease-in-out" href="index.html">
<span class="material-symbols-outlined" data-icon="logout">logout</span>
                Logout
            </a>
</div>
</aside>
<main class="ml-60 min-h-screen">
<!-- TopNavBar -->
<header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3">
<div class="flex items-center gap-4">
<a href="index.html" class="text-xl font-bold text-blue-600 font-sans hover:opacity-80 transition-opacity cursor-pointer block">IMP</a>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-sm" data-icon="search">search</span>
<input class="pl-10 pr-4 py-1.5 bg-surface-container-low border-none rounded-lg text-sm w-64 focus:ring-2 focus:ring-primary" placeholder="Search interns or logs..." type="text">
</div>
</div>
<div class="flex items-center gap-4">
<button class="p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-colors cursor-pointer active:opacity-80">
<span class="material-symbols-outlined" data-icon="notifications">notifications</span>
</button>
<button class="p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-colors cursor-pointer active:opacity-80">
<span class="material-symbols-outlined" data-icon="settings">settings</span>
</button>
<div class="h-8 w-8 rounded-full overflow-hidden border border-gray-200 cursor-pointer active:opacity-80">
<img alt="User profile" data-alt="A professional headshot of a corporate mentor in a bright, modern office setting. The lighting is soft and natural, emphasizing a friendly but authoritative demeanor. The background features blurred architectural glass and steel elements typical of a high-end tech firm in light mode." src="https://lh3.googleusercontent.com/aida-public/AB6AXuDH3mrV27J_1bJrhVVbd0ZWHTNHxSwwZtuMBXhoYsUzk1WQUTFep1Clc8Ajggrn92eRa1b8XMISEXvvbeBbXxESFa-cn4N8yMkXaJCB4U5HHdemi525yQaUBEi2wF1paKEUJ1kvtGeQir2UR0636kFnK6Swgz7xicReWvy3-NsmWkvWVD9HD2LMsZFVMJwCKskA_4jh2BYQNME8r0xi58_lMe59TdX4eWD6nPfWI8gNF-hzLlzmRhc-dvINpy8kzDaHaNWdZgDWng" class="">
</div>
</div>
</header>
<div class="p-xl space-y-lg">
<!-- Welcome Section -->
<section class="flex justify-between items-end">
<div>
<h1 class="font-h1 text-h1 text-on-surface">Mentor<span style="letter-spacing: -0.02em;" class="">&nbsp;Dashboard</span></h1>
<p class="font-body-md text-body-md text-on-surface-variant">Welcome back, Sarah. You have 3 pending log reviews and a 1:1 scheduled today.</p>
</div>
<div class="flex gap-sm">
<button class="bg-primary-container text-white px-lg py-sm rounded-lg font-label-md text-label-md shadow-sm hover:brightness-110 transition-all flex items-center gap-2">
<span class="material-symbols-outlined" data-icon="add" style="font-size: 18px;">add</span>
                        New Feedback
                    </button>
</div>
</section>
<!-- Main Bento Grid -->
<div class="grid grid-cols-12 gap-gutter">
<!-- Assigned Interns - High Density Cards -->
<div class="col-span-12 lg:col-span-8 bg-surface-container-lowest rounded-xl shadow-sm p-lg">
<div class="flex justify-between items-center mb-md">
<h3 class="font-h3 text-h3 text-on-surface">Assigned Interns</h3>
<a class="text-primary font-label-sm text-label-sm" href="#">View All</a>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-md">
<!-- Intern Card 1 -->
<div class="border border-outline-variant rounded-lg p-md hover:shadow-md transition-shadow">
<div class="flex items-start justify-between">
<div class="flex gap-md">
<img class="w-12 h-12 rounded-full object-cover" data-alt="A candid, professional portrait of a young male intern smiling confidently. He is wearing a smart-casual blazer against a clean, minimalist white brick wall. The aesthetic is professional, bright, and modern, fitting a high-end corporate internship program." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCA1fvZd920qzy5qvjzoE7xGgavXO_Qv8citNDOw9ktIZeVvZVfGJhkVegrr_dq1qjOULsqaO3R9jHVv0QgC2OEGDyTb2n39IbFDhgeuKU9I1oNVVvScBrmqUlL6PwMMyWHnZynxPLboRe4Lz7WjZ1AMbqfYs9KxTXyNOLNyr-kVLaD6ttTRjDbQGPztWcS5bLPa-xdI5EI-hjBsjIZeucq_szTUHNNuEDQF2Vrt8x_XmkRWh2w_gRv-ReWV8KgXt756jwd68_ydg">
<div>
<h4 class="font-label-md text-label-md text-on-surface">Marcus Chen</h4>
<p class="font-body-md text-body-md text-on-surface-variant">Product Design Intern</p>
</div>
</div>
<span class="bg-green-100 text-green-700 px-xs py-[2px] rounded font-label-sm text-[10px] uppercase tracking-wider">Active</span>
</div>
<div class="mt-md space-y-xs">
<div class="flex justify-between text-[11px] font-label-sm text-on-surface-variant">
<span class="">Weekly Progress</span>
<span class="">85%</span>
</div>
<div class="w-full bg-surface-container h-1.5 rounded-full overflow-hidden">
<div class="bg-primary h-full" style="width: 85%"></div>
</div>
</div>
</div>
<!-- Intern Card 2 -->
<div class="border border-outline-variant rounded-lg p-md hover:shadow-md transition-shadow">
<div class="flex items-start justify-between">
<div class="flex gap-md">
<img class="w-12 h-12 rounded-full object-cover" data-alt="A portrait of a young female intern with a bright, enthusiastic expression. She is in a collaborative office environment with soft focus plants and warm lighting in the background. The visual style is professional and energetic, representing a thriving corporate culture." src="https://lh3.googleusercontent.com/aida-public/AB6AXuAMh-JsdYyAb-doNQ-r1LQNKWXcYqNnghM-204CUvZ1EkUKITwgH-TFnINyoRsKvPPMvQH4gv38O2SS2S64HijEYp2h_s2j0uiNFF5WiBVUFukcPArsmSkgFiPdIqYcuZNVPLcl10Rqk6KXH5Rv7NnSNkoFORVXY6-IghVQRYIrJngSlS5QvPtJ-0jXnrmGVuD-4t-Wq1Tbl5UcIcCO6DOoz2C8qsx2GuchIKWC2Fa7m3IKMm9FGHapfVq3DD3wb7yccrbzereKBA">
<div>
<h4 class="font-label-md text-label-md text-on-surface">Elena Rodriguez</h4>
<p class="font-body-md text-body-md text-on-surface-variant">Software Eng Intern</p>
</div>
</div>
<span class="bg-amber-100 text-amber-700 px-xs py-[2px] rounded font-label-sm text-[10px] uppercase tracking-wider">Pending Log</span>
</div>
<div class="mt-md space-y-xs">
<div class="flex justify-between text-[11px] font-label-sm text-on-surface-variant">
<span class="">Weekly Progress</span>
<span class="">62%</span>
</div>
<div class="w-full bg-surface-container h-1.5 rounded-full overflow-hidden">
<div class="bg-primary h-full" style="width: 62%"></div>
</div>
</div>
</div>
</div>
</div>
<!-- Calendar View - Asymmetric Layout -->
<div class="col-span-12 lg:col-span-4 bg-surface-container-lowest rounded-xl shadow-sm p-lg">
<h3 class="font-h3 text-h3 text-on-surface mb-md">1:1 Schedule</h3>
<div class="space-y-md">
<div class="flex items-center gap-md p-md bg-surface-container-low rounded-lg border-l-4 border-primary">
<div class="text-center min-w-[40px]">
<span class="block font-label-sm text-label-sm text-primary">TODAY</span>
<span class="block font-h2 text-h2 text-on-surface">14</span>
</div>
<div class="flex-1">
<p class="font-label-md text-label-md text-on-surface">Elena Rodriguez</p>
<p class="font-body-md text-body-md text-on-surface-variant">Weekly Sync • 2:00 PM</p>
</div>
<button class="p-2 text-primary hover:bg-primary/5 rounded-full">
<span class="material-symbols-outlined" data-icon="videocam">videocam</span>
</button>
</div>
<div class="flex items-center gap-md p-md border border-outline-variant rounded-lg">
<div class="text-center min-w-[40px]">
<span class="block font-label-sm text-label-sm text-on-surface-variant">WED</span>
<span class="block font-h2 text-h2 text-on-surface-variant">16</span>
</div>
<div class="flex-1">
<p class="font-label-md text-label-md text-on-surface">Marcus Chen</p>
<p class="font-body-md text-body-md text-on-surface-variant">Sprint Review • 10:30 AM</p>
</div>
</div>
</div>
</div>
<!-- Daily Log Review Section - Data Table Style -->
<div class="col-span-12 lg:col-span-7 bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
<div class="p-lg flex justify-between items-center">
<h3 class="font-h3 text-h3 text-on-surface">Recent Logs</h3>
<div class="flex gap-xs">
<button class="p-1.5 bg-surface-container-high rounded text-on-surface-variant">
<span class="material-symbols-outlined text-[18px]" data-icon="filter_list">filter_list</span>
</button>
</div>
</div>
<table class="w-full">
<thead class="bg-surface-container-low">
<tr>
<th class="text-left py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Intern</th>
<th class="text-left py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Date</th>
<th class="text-left py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Status</th>
<th class="text-right py-3 px-lg font-label-sm text-label-sm text-on-surface-variant">Action</th>
</tr>
</thead>
<tbody class="divide-y divide-surface-container">
<tr class="hover:bg-gray-50 transition-colors">
<td class="py-md px-lg">
<div class="flex items-center gap-sm">
<div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">MC</div>
<span class="font-body-md text-body-md text-on-surface">Marcus Chen</span>
</div>
</td>
<td class="py-md px-lg font-body-md text-body-md text-on-surface-variant">Oct 12, 2023</td>
<td class="py-md px-lg">
<span class="inline-flex items-center gap-1.5 py-0.5 px-2 rounded-full bg-blue-50 text-blue-700 text-[11px] font-label-sm">
<span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                        REVIEWED
                                    </span>
</td>
<td class="py-md px-lg text-right">
<button class="text-primary font-label-sm text-label-sm hover:underline">Review</button>
</td>
</tr>
<tr class="hover:bg-gray-50 transition-colors">
<td class="py-md px-lg">
<div class="flex items-center gap-sm">
<div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 font-bold text-xs">ER</div>
<span class="font-body-md text-body-md text-on-surface">Elena Rodriguez</span>
</div>
</td>
<td class="py-md px-lg font-body-md text-body-md text-on-surface-variant">Oct 14, 2023</td>
<td class="py-md px-lg">
<span class="inline-flex items-center gap-1.5 py-0.5 px-2 rounded-full bg-amber-50 text-amber-700 text-[11px] font-label-sm">
<span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                        SUBMITTED
                                    </span>
</td>
<td class="py-md px-lg text-right">
<button class="bg-primary text-white py-1 px-3 rounded text-label-sm shadow-sm hover:brightness-110">Grade</button>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Feedback Submission Form - Minimalist Sidebar-style Card -->
<div class="col-span-12 lg:col-span-5 flex flex-col gap-gutter">

<!-- Mentor Notifications -->
<div class="bg-surface-container-lowest rounded-xl shadow-sm p-lg border border-gray-100">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-h3 text-h3 text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-orange-500">notifications_active</span> Alerts</h3>
        <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-0.5 rounded-full">2 New</span>
    </div>
    <div class="space-y-3">
        <div class="flex items-start gap-3 p-3 bg-red-50 rounded-lg">
            <span class="material-symbols-outlined text-red-500 text-sm mt-0.5">error</span>
            <div><p class="text-xs font-bold text-red-800">Issue/Blocker Reported</p><p class="text-[11px] text-red-600 mt-0.5">Marcus Chen is blocked on API integration.</p></div>
        </div>
        <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-lg">
            <span class="material-symbols-outlined text-blue-500 text-sm mt-0.5">post_add</span>
            <div><p class="text-xs font-bold text-blue-800">New Log Submitted</p><p class="text-[11px] text-blue-600 mt-0.5">Elena Rodriguez submitted her daily log.</p></div>
        </div>
        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
            <span class="material-symbols-outlined text-gray-500 text-sm mt-0.5">person_off</span>
            <div><p class="text-xs font-bold text-gray-700">Student Inactive</p><p class="text-[11px] text-gray-500 mt-0.5">John Doe hasn't submitted a log in 2 days.</p></div>
        </div>
    </div>
</div>

<div class="bg-surface-container-lowest rounded-xl shadow-sm p-lg border border-gray-100">
<div class="flex items-center gap-md mb-md">
<div class="p-2 bg-primary-container/10 text-primary rounded-lg">
<span class="material-symbols-outlined" data-icon="rate_review">rate_review</span>
</div>
<h3 class="font-h3 text-h3 text-on-surface">Quick Feedback</h3>
</div>
<form class="space-y-md">
<div>
<label class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Select Intern</label>
<select class="w-full rounded-lg border-outline-variant focus:ring-primary focus:border-primary text-body-md">
<option>Marcus Chen</option>
<option>Elena Rodriguez</option>
</select>
</div>
<div>
<label class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Log Status</label>
<div class="flex gap-2">
<button class="flex-1 py-2 px-1 border border-outline-variant rounded-lg text-center hover:bg-surface-container-low transition-colors" type="button">
<span class="material-symbols-outlined block text-on-surface-variant mb-1 text-sm" data-icon="warning">warning</span>
<span class="text-[9px] font-bold text-gray-600 uppercase tracking-tight">Needs Improvement</span>
</button>
<button class="flex-1 py-2 px-1 border border-primary bg-primary/5 rounded-lg text-center" type="button">
<span class="material-symbols-outlined block text-primary mb-1 text-sm" data-icon="check_circle">check_circle</span>
<span class="text-[9px] font-bold text-primary uppercase tracking-tight">Reviewed</span>
</button>
<button class="flex-1 py-2 px-1 border border-outline-variant rounded-lg text-center hover:bg-surface-container-low transition-colors" type="button">
<span class="material-symbols-outlined block text-on-surface-variant mb-1 text-sm" data-icon="star">star</span>
<span class="text-[9px] font-bold text-gray-600 uppercase tracking-tight">Excellent Progress</span>
</button>
</div>
</div>
<div>
<label class="block font-label-sm text-label-sm text-on-surface-variant mb-xs">Comments</label>
<textarea class="w-full rounded-lg border-outline-variant focus:ring-primary focus:border-primary text-body-md p-md" placeholder="Share specific insights..." rows="3"></textarea>
</div>
<div class="flex items-center gap-2 mt-4 bg-green-50 p-3 rounded-lg border border-green-100">
    <input type="checkbox" id="approve_weekly" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500 cursor-pointer">
    <label for="approve_weekly" class="text-xs font-bold text-green-800 cursor-pointer">Approve Weekly Progress</label>
</div>
<button class="w-full bg-primary-container text-white py-md mt-4 rounded-lg font-label-md text-label-md shadow-sm hover:brightness-110 transition-all" type="submit">Submit Evaluation</button>
</form>
</div>
</div>
</div>
</div>
</main>




</body></html>