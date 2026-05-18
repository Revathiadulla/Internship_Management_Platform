<!DOCTYPE html><html class="light" lang="en"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
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
</head>
<body class="bg-surface text-on-surface font-body-md overflow-x-hidden">
<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col h-full py-6 font-sans text-sm font-medium">
<div class="px-6 mb-8 flex items-center gap-3">
<div class="w-10 h-10 bg-primary-container rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-on-primary-container" data-icon="school">school</span>
</div>
<div>
<h2 class="text-lg font-black tracking-tight text-blue-600 leading-tight">Admin Panel</h2>
<p class="text-[10px] uppercase tracking-widest text-secondary font-bold">Management Console</p>
</div>
</div>
<nav class="flex-1 flex flex-col gap-1">
<div class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 cursor-pointer duration-200 ease-in-out">
<span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
<span class="">Dashboard</span>
</div>
<div class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer duration-200 ease-in-out">
<span class="material-symbols-outlined" data-icon="work">work</span>
<span class="">Postings</span>
</div>
<div class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer duration-200 ease-in-out">
<span class="material-symbols-outlined" data-icon="group">group</span>
<span class="">Candidates</span>
</div>
<div class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer duration-200 ease-in-out">
<span class="material-symbols-outlined" data-icon="account_tree">account_tree</span>
<span class="">Workflows</span>
</div>
<div class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer duration-200 ease-in-out">
<span class="material-symbols-outlined" data-icon="analytics">analytics</span>
<span class="">Reports</span>
</div>
<div class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer duration-200 ease-in-out">
<span class="material-symbols-outlined" data-icon="manage_accounts">manage_accounts</span>
<span class="">Users</span>
</div>
</nav>
<div class="mt-auto border-t border-gray-200 pt-4 flex flex-col gap-1">
<div class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer">
<span class="material-symbols-outlined" data-icon="help">help</span>
<span class="">Help Center</span>
</div>
<div onclick="window.location.href='index.html'" class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 transition-all cursor-pointer">
<span class="material-symbols-outlined" data-icon="logout">logout</span>
<span class="">Logout</span>
</div>
</div>
</aside>
<!-- Main Content Area -->
<main class="pl-60 flex flex-col min-h-screen">
<!-- TopNavBar -->
<header class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3 font-sans antialiased text-sm">
<div class="flex items-center gap-8">
<a href="index.html" class="text-xl font-bold text-blue-600 hover:opacity-80 transition-opacity cursor-pointer block">IMP</a>
<div class="relative w-80">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-lg" data-icon="search">search</span>
<input class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-primary-container focus:border-transparent outline-none transition-all text-body-md" placeholder="Search candidates, skills, or colleges..." type="text">
</div>
</div>
<div class="flex items-center gap-4">
<button class="p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-colors cursor-pointer active:opacity-80">
<span class="material-symbols-outlined" data-icon="notifications">notifications</span>
</button>
<button class="p-2 text-gray-500 hover:bg-gray-50 rounded-full transition-colors cursor-pointer active:opacity-80">
<span class="material-symbols-outlined" data-icon="settings">settings</span>
</button>
<div class="h-8 w-[1px] bg-gray-200 mx-2"></div>
<div class="flex items-center gap-3 cursor-pointer">
<div class="text-right">
<p class="font-semibold text-gray-900 leading-none">Sarah Jenkins</p>
<p class="text-[10px] text-gray-500 mt-1 uppercase font-bold tracking-tight">Talent Acquisition</p>
</div>
<img alt="User profile" class="w-10 h-10 rounded-full object-cover border-2 border-primary-container/20" data-alt="A professional headshot of a middle-aged woman with a warm, confident expression. She is wearing business casual attire in a modern, brightly lit office environment with soft-focus windows in the background. The lighting is crisp and natural, highlighting her professional demeanor in a corporate setting." src="https://lh3.googleusercontent.com/aida-public/AB6AXuD619DZMmD1MqEVPwUIgSV-l4sz2Qbk7xwiLezTaQ74Vg7GIYSQFr8_J1iiSTyFCCHYFf2ZIxnyghrMInkuB0xAIZ6joEqshnsW9sgCAvm1utOMRLouaZ0JTMa3P9KjKAMonXqceqCOLLOyonygSTWkvAt5bsDesUU2y4Ecojr-t2bjM5Y8xTEWg6Yq3uCIDaXVUdserC0gNW0YJfY0bj8svI27UBcltyUuOPRS9c0r3swvLvhCLdzZ0SJh_sp5jm68S1LaxTF0ew">
</div>
</div>
</header>
<!-- Dashboard Body -->
<div class="p-xl space-y-lg max-w-[1600px] mx-auto w-full">
<!-- Summary Stats Section (Bento Style) -->
<div class="grid grid-cols-4 gap-gutter">
<div class="bg-white p-lg rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow">
<div class="flex items-start justify-between">
<div class="p-sm bg-blue-50 text-blue-600 rounded-lg">
<span class="material-symbols-outlined" data-icon="person_add">person_add</span>
</div>
<span class="text-green-600 text-label-sm flex items-center bg-green-50 px-2 py-0.5 rounded-full">
                            +12% <span class="material-symbols-outlined text-[14px] ml-0.5" data-icon="trending_up">trending_up</span>
</span>
</div>
<div class="mt-4">
<h3 class="text-gray-500 font-label-sm uppercase tracking-wider">New Applicants</h3>
<p class="text-h1 text-on-surface">1,284</p>
</div>
</div>
<div class="bg-white p-lg rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow">
<div class="flex items-start justify-between">
<div class="p-sm bg-amber-50 text-amber-600 rounded-lg">
<span class="material-symbols-outlined" data-icon="quiz">quiz</span>
</div>
<span class="text-amber-600 text-label-sm flex items-center bg-amber-50 px-2 py-0.5 rounded-full">
                            Active
                        </span>
</div>
<div class="mt-4">
<h3 class="text-gray-500 font-label-sm uppercase tracking-wider">In Assessment</h3>
<p class="text-h1 text-on-surface">432</p>
</div>
</div>
<div class="bg-white p-lg rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow">
<div class="flex items-start justify-between">
<div class="p-sm bg-purple-50 text-purple-600 rounded-lg">
<span class="material-symbols-outlined" data-icon="forum">forum</span>
</div>
<span class="text-purple-600 text-label-sm flex items-center bg-purple-50 px-2 py-0.5 rounded-full">
                            8 Pending
                        </span>
</div>
<div class="mt-4">
<h3 class="text-gray-500 font-label-sm uppercase tracking-wider">HR Reviews</h3>
<p class="text-h1 text-on-surface">156</p>
</div>
</div>
<div class="bg-white p-lg rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between hover:shadow-md transition-shadow">
<div class="flex items-start justify-between">
<div class="p-sm bg-green-50 text-green-600 rounded-lg">
<span class="material-symbols-outlined" data-icon="verified">verified</span>
</div>
<span class="text-blue-600 text-label-sm flex items-center bg-blue-50 px-2 py-0.5 rounded-full">
                            Q3 Target
                        </span>
</div>
<div class="mt-4">
<h3 class="text-gray-500 font-label-sm uppercase tracking-wider">Total Approved</h3>
<p class="text-h1 text-on-surface">89</p>
</div>
</div>
</div>
<!-- Pipeline Summary & Filter Row -->
<div class="grid grid-cols-12 gap-gutter">
<!-- Filters Sidebar/Panel -->
<div class="col-span-12 lg:col-span-3 space-y-md">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-lg sticky top-24">
<div class="flex items-center justify-between mb-lg">
<h2 class="text-h3 text-on-surface">Filters</h2>
<button class="text-primary font-label-sm hover:underline">Clear all</button>
</div>
<div class="space-y-lg">
<div>
<label class="block text-label-sm text-gray-500 uppercase tracking-widest mb-sm">Candidate Status</label>
<div class="space-y-2">
<label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
<input checked="" class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary" type="checkbox">
<span class="text-body-md">New Applicant</span>
</label>
<label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
<input class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary" type="checkbox">
<span class="text-body-md">In Assessment</span>
</label>
<label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
<input class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary" type="checkbox">
<span class="text-body-md">HR Review</span>
</label>
<label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
<input class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary" type="checkbox">
<span class="text-body-md">Approved</span>
</label>
</div>
</div>
<hr class="border-gray-100">
<div>
<label class="block text-label-sm text-gray-500 uppercase tracking-widest mb-sm">Top Colleges</label>
<select class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 text-body-md focus:ring-2 focus:ring-primary-container outline-none">
<option>All Universities</option>
<option>Stanford University</option>
<option>MIT</option>
<option>Georgia Tech</option>
<option>UT Austin</option>
</select>
</div>
<div>
<label class="block text-label-sm text-gray-500 uppercase tracking-widest mb-sm">Skills Required</label>
<div class="flex flex-wrap gap-2">
<span class="bg-primary-container text-on-primary-container px-3 py-1 rounded-full text-label-sm cursor-pointer">React</span>
<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-label-sm hover:bg-gray-200 cursor-pointer">Python</span>
<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-label-sm hover:bg-gray-200 cursor-pointer">UI/UX</span>
<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-label-sm hover:bg-gray-200 cursor-pointer">Node.js</span>
<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-label-sm hover:bg-gray-200 cursor-pointer">+4 more</span>
</div>
</div>
</div>
</div>
</div>
<!-- Table Content -->
<div class="col-span-12 lg:col-span-9 space-y-md">
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
<div class="px-lg py-md border-b border-gray-100 flex items-center justify-between">
<div class="flex items-center gap-4">
<h2 class="text-h3 font-h3 text-on-surface">Recent Applicants</h2>
<span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-label-sm">24 new today</span>
</div>
<div class="flex items-center gap-2">
<button class="flex items-center gap-2 px-4 py-2 text-label-md text-gray-600 hover:bg-gray-50 rounded-lg transition-all">
<span class="material-symbols-outlined text-[20px]" data-icon="download">download</span>
                                    Export CSV
                                </button>
<button class="flex items-center gap-2 px-4 py-2 text-label-md bg-primary text-on-primary rounded-lg shadow-sm hover:opacity-90 transition-all">
<span class="material-symbols-outlined text-[20px]" data-icon="add">add</span>
                                    Add Manual Entry
                                </button>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead class="bg-gray-50/50">
<tr>
<th class="px-lg py-3 text-label-sm text-gray-500 uppercase">Applicant</th>
<th class="px-lg py-3 text-label-sm text-gray-500 uppercase">Verification</th>
<th class="px-lg py-3 text-label-sm text-gray-500 uppercase">Status</th>
<th class="px-lg py-3 text-label-sm text-gray-500 uppercase">Skill Match</th>
<th class="px-lg py-3 text-label-sm text-gray-500 uppercase text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-100">
<tr class="hover:bg-gray-50/80 transition-colors group">
<td class="px-lg py-4">
<div class="flex items-center gap-4">
<div class="relative">
<img class="w-12 h-12 rounded-lg object-cover" data-alt="A candid profile photo of a young male student smiling brightly, wearing a casual gray hoodie against a minimalist white background. The photo is styled for a corporate hiring platform, emphasizing approachability and professional potential with soft, even studio lighting." src="https://lh3.googleusercontent.com/aida-public/AB6AXuD5rPGoNFYHqoZu5eV69OUCUIsFYGAR389GgBy4pYTAAfhsx5f5k8G48DD2ATwPfBUFVPNWm6cOwIHo2mzZvp2BeZ5ZPUT11JPUy2iX6Qff8lg3jUTpFebjvWHslGyApK9Gs-VEe1Lzd37-htKsh9SW4Fvxvrcn9zUP5djGSzQmj0Mww7BHuyAa87oruWAOMXFE4PaDY8WyFo5WCwvuBgb8TES13p5Gwe4OueAdtIY6rUQW7XMHCCCqNtXpuheqZ8iwhLmoRzAxUQ">
<div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></div>
</div>
<div>
<p class="font-semibold text-gray-900">Marcus Sterling</p>
<p class="text-label-sm text-gray-500">MIT • CS Senior</p>
</div>
</div>
</td>
<td class="px-lg py-4">
<div class="flex items-center gap-1.5 px-2 py-1 bg-green-50 text-green-700 w-fit rounded-full text-label-sm">
<span class="material-symbols-outlined text-[14px]" data-icon="verified" data-weight="fill" style="font-variation-settings: &quot;FILL&quot; 1;">verified</span>
                                                Verified
                                            </div>
</td>
<td class="px-lg py-4">
<div class="px-3 py-1 bg-blue-50 text-blue-700 w-fit rounded-full text-label-sm">
                                                New Applicant
                                            </div>
</td>
<td class="px-lg py-4">
<div class="w-24 bg-gray-100 rounded-full h-2">
<div class="bg-blue-600 h-2 rounded-full w-[92%]"></div>
</div>
<span class="text-[10px] text-gray-400 font-bold mt-1 block">92% MATCH</span>
</td>
<td class="px-lg py-4 text-right">
<div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
<button class="p-2 text-primary bg-primary/10 rounded-lg hover:bg-primary/20 transition-colors" title="Schedule">
<span class="material-symbols-outlined text-[20px]" data-icon="event">event</span>
</button>
<button class="p-2 text-error bg-error/10 rounded-lg hover:bg-error/20 transition-colors" title="Reject">
<span class="material-symbols-outlined text-[20px]" data-icon="close">close</span>
</button>
<button class="p-2 text-green-600 bg-green-50 rounded-lg hover:bg-green-100 transition-colors" title="Approve">
<span class="material-symbols-outlined text-[20px]" data-icon="check">check</span>
</button>
</div>
</td>
</tr>
<tr class="hover:bg-gray-50/80 transition-colors group">
<td class="px-lg py-4">
<div class="flex items-center gap-4">
<div class="relative">
<img class="w-12 h-12 rounded-lg object-cover" data-alt="A portrait of a male professional with spectacles, wearing a crisp light blue shirt in a professional indoor environment. The background is a clean, modern workspace with natural window lighting that creates a bright and reliable aesthetic suitable for a talent management dashboard." src="https://lh3.googleusercontent.com/aida-public/AB6AXuCo5nV7Ozmg9tvhdHnhiPhadufuKN-orDCbgpEsonnYxI4MuP8Y6touUG967NXQpH6WhUnO8iD8kVQ6OrtSb8SSzruG77Ocv-v2EauAoXA9KRo3sr2K4h_vhbyQhk2Ipe3nAH-Q57n60vMP00x9Aq4B2pDXTL58wkXQeC10hJIghmKSnA3v_a1ePNoN032klbMiqwT6iO3JoLBV_80TjIw6lHSzreHio5gzV7ehp1FCtmiMFQBV-63UxF43caQXCbmOF7IRMKud8A">
<div class="absolute -bottom-1 -right-1 w-4 h-4 bg-gray-400 border-2 border-white rounded-full"></div>
</div>
<div>
<p class="font-semibold text-gray-900">Arjun Mehta</p>
<p class="text-label-sm text-gray-500">Stanford • UI Design</p>
</div>
</div>
</td>
<td class="px-lg py-4">
<div class="flex items-center gap-1.5 px-2 py-1 bg-amber-50 text-amber-700 w-fit rounded-full text-label-sm">
<span class="material-symbols-outlined text-[14px]" data-icon="history">history</span>
                                                Pending
                                            </div>
</td>
<td class="px-lg py-4">
<div class="px-3 py-1 bg-amber-50 text-amber-700 w-fit rounded-full text-label-sm">
                                                In Assessment
                                            </div>
</td>
<td class="px-lg py-4">
<div class="w-24 bg-gray-100 rounded-full h-2">
<div class="bg-amber-500 h-2 rounded-full w-[75%]"></div>
</div>
<span class="text-[10px] text-gray-400 font-bold mt-1 block">75% MATCH</span>
</td>
<td class="px-lg py-4 text-right">
<div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
<button class="p-2 text-primary bg-primary/10 rounded-lg hover:bg-primary/20 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="event">event</span>
</button>
<button class="p-2 text-error bg-error/10 rounded-lg hover:bg-error/20 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="close">close</span>
</button>
<button class="p-2 text-green-600 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="check">check</span>
</button>
</div>
</td>
</tr>
<tr class="hover:bg-gray-50/80 transition-colors group">
<td class="px-lg py-4">
<div class="flex items-center gap-4">
<div class="relative">
<img class="w-12 h-12 rounded-lg object-cover" data-alt="A high-quality business portrait of a woman of color smiling confidently, with hair neatly styled and wearing a professional navy blazer. The setting is a minimalist corporate office with high-key lighting, creating a sleek, trustworthy, and results-oriented visual atmosphere for a corporate dashboard." src="https://lh3.googleusercontent.com/aida-public/AB6AXuASTOe8A_enX8ObdfVDMDJKntrfT6F2BdGS5DeVLGnI0FGtUOXZUPlFfF9xiZQXF-ZlpV4hu82KCDTAeKehMijqAbU_hxROIe5dLb5qsV6mvKu_r__kh0kaZhFfH6ZWzkob_Os2Prq3t02gVB6gHHMgI2oWwCrZo9eutF7gGm5BY6eHCHUAk9o4uFyncsvpKC1Y_LMUtBEljKKZPpEultiR4GDt2hvdw-G_24vPIWG-_AtcYRPpCDcsO8isXFVAxYamqwdXgFCkug">
<div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></div>
</div>
<div>
<p class="font-semibold text-gray-900">Elena Rodriguez</p>
<p class="text-label-sm text-gray-500">Georgia Tech • Data Eng.</p>
</div>
</div>
</td>
<td class="px-lg py-4">
<div class="flex items-center gap-1.5 px-2 py-1 bg-green-50 text-green-700 w-fit rounded-full text-label-sm">
<span class="material-symbols-outlined text-[14px]" data-icon="verified" data-weight="fill" style="font-variation-settings: &quot;FILL&quot; 1;">verified</span>
                                                Verified
                                            </div>
</td>
<td class="px-lg py-4">
<div class="px-3 py-1 bg-purple-50 text-purple-700 w-fit rounded-full text-label-sm">
                                                HR Review
                                            </div>
</td>
<td class="px-lg py-4">
<div class="w-24 bg-gray-100 rounded-full h-2">
<div class="bg-blue-600 h-2 rounded-full w-[88%]"></div>
</div>
<span class="text-[10px] text-gray-400 font-bold mt-1 block">88% MATCH</span>
</td>
<td class="px-lg py-4 text-right">
<div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
<button class="p-2 text-primary bg-primary/10 rounded-lg hover:bg-primary/20 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="event">event</span>
</button>
<button class="p-2 text-error bg-error/10 rounded-lg hover:bg-error/20 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="close">close</span>
</button>
<button class="p-2 text-green-600 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="check">check</span>
</button>
</div>
</td>
</tr>
<tr class="hover:bg-gray-50/80 transition-colors group">
<td class="px-lg py-4">
<div class="flex items-center gap-4">
<div class="relative">
<img class="w-12 h-12 rounded-lg object-cover" data-alt="A sophisticated portrait of a young male professional in a modern business-casual outfit. He is looking slightly away from the camera in a light-filled office space. The lighting is soft and airy, fitting the minimal and structured corporate theme of the platform." src="https://lh3.googleusercontent.com/aida-public/AB6AXuATHEnzcwF8tAqKWS4SJtygQf3gXW-Tl4xOadWiFV8M5n-8OTdQ4o7ZrgaTVugrAX6mtUNPd2zm6QIiqW5G_7DyaZHULt0VVn6GAhLb_fpf044GxXTFPxN_1q8KE0HZWqF6hLY1mI4MI5p_EE46ctmmxnFydumu4xdzfDMZjgW5FUlc8dgL5-0qab8EE6mYBb_VucErhbDBIj_93LWIln9deBbyska0M3tCEiwhxmlYrTkleFvdGYqL88mx6lVONtwuXplzwct8TA">
<div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 border-2 border-white rounded-full"></div>
</div>
<div>
<p class="font-semibold text-gray-900">David Chang</p>
<p class="text-label-sm text-gray-500">UT Austin • Marketing</p>
</div>
</div>
</td>
<td class="px-lg py-4">
<div class="flex items-center gap-1.5 px-2 py-1 bg-green-50 text-green-700 w-fit rounded-full text-label-sm">
<span class="material-symbols-outlined text-[14px]" data-icon="verified" data-weight="fill" style="font-variation-settings: &quot;FILL&quot; 1;">verified</span>
                                                Verified
                                            </div>
</td>
<td class="px-lg py-4">
<div class="px-3 py-1 bg-green-50 text-green-700 w-fit rounded-full text-label-sm">
                                                Approved
                                            </div>
</td>
<td class="px-lg py-4">
<div class="w-24 bg-gray-100 rounded-full h-2">
<div class="bg-green-600 h-2 rounded-full w-[95%]"></div>
</div>
<span class="text-[10px] text-gray-400 font-bold mt-1 block">95% MATCH</span>
</td>
<td class="px-lg py-4 text-right">
<div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
<button class="p-2 text-primary bg-primary/10 rounded-lg hover:bg-primary/20 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="event">event</span>
</button>
<button class="p-2 text-error bg-error/10 rounded-lg hover:bg-error/20 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="close">close</span>
</button>
<button class="p-2 text-green-600 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
<span class="material-symbols-outlined text-[20px]" data-icon="check">check</span>
</button>
</div>
</td>
</tr>
</tbody>
</table>
</div>
<div class="px-lg py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
<p class="text-body-md text-gray-500">Showing <span class="font-bold text-on-surface">1-4</span> of <span class="font-bold text-on-surface">1,284</span> candidates</p>
<div class="flex gap-2">
<button class="px-3 py-1 rounded border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 disabled:opacity-50">Previous</button>
<button class="px-3 py-1 rounded bg-primary text-on-primary">1</button>
<button class="px-3 py-1 rounded border border-gray-200 bg-white text-gray-500 hover:bg-gray-100">2</button>
<button class="px-3 py-1 rounded border border-gray-200 bg-white text-gray-500 hover:bg-gray-100">3</button>
<button class="px-3 py-1 rounded border border-gray-200 bg-white text-gray-500 hover:bg-gray-100">Next</button>
</div>
</div>
</div>
</div>
</div>
</div>
</main>
<!-- FAB (Suppressed based on logic, but for dashboard home it is allowed) -->
<button class="fixed bottom-8 right-8 w-14 h-14 bg-primary text-on-primary rounded-full shadow-lg flex items-center justify-center hover:scale-110 active:scale-95 transition-transform z-50">
<span class="material-symbols-outlined" data-icon="chat">chat</span>
</button>


</body></html>