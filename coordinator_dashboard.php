<!DOCTYPE html>

<html class="light" lang="en">

<head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
                rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
                rel="stylesheet" />
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
                                                "body-lg": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                                                "label-md": ["14px", { "lineHeight": "20px", "fontWeight": "500" }],
                                                "body-md": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
                                                "h1": ["30px", { "lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                                                "label-sm": ["12px", { "lineHeight": "16px", "fontWeight": "600" }],
                                                "h3": ["20px", { "lineHeight": "28px", "fontWeight": "600" }],
                                                "h2": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600" }]
                                        }
                                },
                        },
                }
        </script>
        <style>
                .material-symbols-outlined {
                        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                        vertical-align: middle;
                }

                body {
                        font-family: 'Inter', sans-serif;
                }

                .bento-grid {
                        display: grid;
                        grid-template-columns: repeat(12, 1fr);
                        gap: 20px;
                }
        </style>
</head>

<body class="bg-background text-on-surface">
        <!-- SideNavBar -->
        <aside
                class="fixed left-0 top-0 h-screen w-60 z-50 bg-gray-50 border-r border-gray-200 flex flex-col py-6 font-sans text-sm font-medium">
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
                        <a class="flex items-center gap-3 bg-blue-50 text-blue-700 border-l-4 border-blue-600 px-4 py-3 duration-200 ease-in-out"
                                href="#">
                                <span class="material-symbols-outlined">dashboard</span>
                                <span>Dashboard</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="#">
                                <span class="material-symbols-outlined">work</span>
                                <span>Postings</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="#">
                                <span class="material-symbols-outlined">group</span>
                                <span>Candidates</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="#">
                                <span class="material-symbols-outlined">account_tree</span>
                                <span>Workflows</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="coordinator_daily_logs.html">
                                <span class="material-symbols-outlined">monitoring</span>
                                <span>Daily Logs Monitoring</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="#">
                                <span class="material-symbols-outlined">analytics</span>
                                <span>Reports</span>
                        </a>
                        <a class="flex items-center gap-3 text-gray-600 px-4 py-3 hover:bg-gray-100 duration-200 ease-in-out"
                                href="#">
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
                                href="index.html">
                                <span class="material-symbols-outlined">logout</span>
                                <span>Logout</span>
                        </a>
                </div>
        </aside>
        <!-- Main Content Area -->
        <main class="ml-60 flex flex-col min-h-screen">
                <!-- TopNavBar -->
                <header
                        class="w-full sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm flex items-center justify-between px-6 py-3 font-sans antialiased text-sm">
                        <div class="flex items-center gap-4">
                                <span class="text-xl font-bold text-blue-600">InternshipHub</span>
                                <div class="relative ml-4">
                                        <span
                                                class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                                        <input class="pl-10 pr-4 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary-container text-sm w-64"
                                                placeholder="Search interns or projects..." type="text" />
                                </div>
                        </div>
                        <div class="flex items-center gap-4">
                                <button
                                        class="p-2 hover:bg-gray-50 transition-colors cursor-pointer active:opacity-80 rounded-full">
                                        <span class="material-symbols-outlined text-gray-500">notifications</span>
                                </button>
                                <button
                                        class="p-2 hover:bg-gray-50 transition-colors cursor-pointer active:opacity-80 rounded-full">
                                        <span class="material-symbols-outlined text-gray-500">settings</span>
                                </button>
                                <div
                                        class="w-8 h-8 rounded-full bg-gray-200 overflow-hidden ml-2 border border-outline-variant">
                                        <img alt="User profile"
                                                data-alt="A professional headshot of a corporate coordinator in a brightly lit, modern office environment. The individual is smiling confidently, wearing business-casual attire. The background is softly blurred, showing hints of a glass-walled conference room and contemporary office furniture, maintaining a high-key, professional light-mode aesthetic with clean white and soft gray tones."
                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuArm-ek8_z6yNJrNGqrDL4ImXVmp5Dj2yo2qX-kO_uYcztlv3110NG1T8HwBiv1LAWTbijoGBuJcUDAdOg8rJ16xPPHfG3Z7I5xeb4NIYj7Mw0jLg3R0206NwSREEK4hddt-jU6NSFh_RlKwB1Ak3FGOVTK5eD7DKjYWgaXbejs2602llPcsUPlP0ZmN-A2L31M6eGRGeZF-eYsv4anZsVIg8uZE863u3rKer3UjXimU0cBqaFXbbYw0Kt6j7dkbPK1QY4NSw4xng" />
                                </div>
                        </div>
                </header>
                <!-- Dashboard Content -->
                <div class="p-container-margin space-y-lg">
                        <!-- Page Title & Bulk Actions -->
                        <div class="flex justify-between items-end">
                                <div>
                                        <h2 class="font-h1 text-h1 text-on-background">Coordinator Dashboard</h2>
                                        <p class="font-body-md text-body-md text-secondary">Cohort Summer 2024 • 124
                                                Active Interns</p>
                                </div>
                                <div class="flex gap-sm">
                                        <button
                                                class="flex items-center gap-2 bg-secondary-container text-on-secondary-container px-4 py-2 rounded-lg font-label-md text-label-md hover:bg-gray-200 transition-all">
                                                <span class="material-symbols-outlined">mail</span>
                                                Bulk Notification
                                        </button>
                                        <button
                                                class="flex items-center gap-2 bg-primary-container text-on-primary px-4 py-2 rounded-lg font-label-md text-label-md hover:opacity-90 transition-all shadow-sm">
                                                <span class="material-symbols-outlined">add</span>
                                                New Internship
                                        </button>
                                </div>
                        </div>
                        <!-- Bento Grid Overview -->
                        <div class="bento-grid">
                                <!-- Intern Cohort Status Card -->
                                <div
                                        class="col-span-8 bg-surface-container-lowest p-lg rounded-xl shadow-sm border border-outline-variant">
                                        <div class="flex justify-between items-start mb-lg">
                                                <h3 class="font-h3 text-h3">Global Intern Overview</h3>
                                                <div class="flex gap-2">
                                                        <span
                                                                class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-label-sm font-label-sm">92%
                                                                On Track</span>
                                                        <span
                                                                class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-label-sm font-label-sm">8
                                                                Flagged</span>
                                                </div>
                                        </div>
                                        <div class="space-y-4">
                                                <div class="grid grid-cols-4 gap-4">
                                                        <div
                                                                class="p-md bg-surface-container-low rounded-lg border-l-4 border-primary shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                <p
                                                                        class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                                                                        Total Logs</p>
                                                                <p class="font-h2 text-h2 mt-1 text-primary">1,432</p>
                                                        </div>
                                                        <div
                                                                class="p-md bg-surface-container-low rounded-lg border-l-4 border-red-500 shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                <p
                                                                        class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                                                                        Missing Logs</p>
                                                                <p class="font-h2 text-h2 mt-1 text-red-600">18</p>
                                                        </div>
                                                        <div
                                                                class="p-md bg-surface-container-low rounded-lg border-l-4 border-green-500 shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                <p
                                                                        class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                                                                        Completion %</p>
                                                                <p class="font-h2 text-h2 mt-1 text-green-700">82%</p>
                                                        </div>
                                                        <div
                                                                class="p-md bg-surface-container-low rounded-lg border-l-4 border-purple-500 shadow-[inset_0px_2px_4px_rgba(0,0,0,0.02)]">
                                                                <p
                                                                        class="font-label-sm text-label-sm text-secondary uppercase tracking-wider">
                                                                        Avg Productivity</p>
                                                                <p class="font-h2 text-h2 mt-1 text-purple-700">4.2<span
                                                                                class="text-xs text-gray-500 font-normal">/5</span>
                                                                </p>
                                                        </div>
                                                </div>
                                                <!-- Mini Timeline -->
                                                <div class="pt-lg">
                                                        <p
                                                                class="font-label-sm text-label-sm text-secondary mb-4 uppercase tracking-wider">
                                                                Program Timeline</p>
                                                        <div
                                                                class="relative h-12 w-full bg-gray-100 rounded-full overflow-hidden flex items-center">
                                                                <div
                                                                        class="h-full bg-primary-container w-[65%] flex items-center justify-end pr-4 text-white text-[10px] font-bold">
                                                                        WEEK 8 / 12</div>
                                                                <div class="absolute inset-0 flex justify-between px-4">
                                                                        <div
                                                                                class="h-full border-r border-gray-300 w-px">
                                                                        </div>
                                                                        <div
                                                                                class="h-full border-r border-gray-300 w-px">
                                                                        </div>
                                                                        <div
                                                                                class="h-full border-r border-gray-300 w-px">
                                                                        </div>
                                                                        <div
                                                                                class="h-full border-r border-gray-300 w-px">
                                                                        </div>
                                                                </div>
                                                        </div>
                                                        <div
                                                                class="flex justify-between mt-2 font-label-sm text-label-sm text-secondary">
                                                                <span>Onboarding</span>
                                                                <span>Mid-term</span>
                                                                <span>Final Review</span>
                                                                <span>Offboarding</span>
                                                        </div>
                                                </div>
                                        </div>
                                </div>
                                <!-- Assignment Stats Card -->
                                <div
                                        class="col-span-4 bg-primary-container text-on-primary-container p-lg rounded-xl shadow-sm relative overflow-hidden">
                                        <div class="relative z-10">
                                                <h3 class="font-h3 text-h3 mb-md">Project Filling</h3>
                                                <div class="space-y-lg">
                                                        <div>
                                                                <div
                                                                        class="flex justify-between font-label-md text-label-md mb-2">
                                                                        <span>Assigned Interns</span>
                                                                        <span>88/112</span>
                                                                </div>
                                                                <div
                                                                        class="w-full bg-white/20 h-2 rounded-full overflow-hidden">
                                                                        <div class="bg-white h-full w-[78%]"></div>
                                                                </div>
                                                        </div>
                                                        <div>
                                                                <div
                                                                        class="flex justify-between font-label-md text-label-md mb-2">
                                                                        <span>Open Projects</span>
                                                                        <span>24</span>
                                                                </div>
                                                                <div
                                                                        class="w-full bg-white/20 h-2 rounded-full overflow-hidden">
                                                                        <div class="bg-white h-full w-[22%]"></div>
                                                                </div>
                                                        </div>
                                                        <button
                                                                class="w-full py-3 bg-white text-primary-container rounded-lg font-label-md text-label-md font-bold mt-4">
                                                                Review Assignments
                                                        </button>
                                                </div>
                                        </div>
                                        <div class="absolute -right-12 -bottom-12 opacity-10">
                                                <span class="material-symbols-outlined text-[200px]"
                                                        style="font-variation-settings: 'FILL' 1;">account_tree</span>
                                        </div>
                                </div>
                                <!-- Assignment Panel -->
                                <div
                                        class="col-span-12 bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant overflow-hidden">
                                        <div
                                                class="px-lg py-md border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                                <h3
                                                        class="font-label-md text-label-md text-on-background uppercase tracking-widest">
                                                        Project Assignment Pipeline</h3>
                                                <div class="flex gap-4">
                                                        <select
                                                                class="bg-white border-outline-variant rounded-lg text-label-sm font-label-sm px-3 py-1">
                                                                <option>All Departments</option>
                                                                <option>Engineering</option>
                                                                <option>Product</option>
                                                        </select>
                                                        <button
                                                                class="text-primary font-label-sm text-label-sm hover:underline">View
                                                                All Projects</button>
                                                </div>
                                        </div>
                                        <div class="p-lg grid grid-cols-3 gap-lg">
                                                <!-- Project Card 1 -->
                                                <div
                                                        class="border border-outline-variant rounded-lg p-md flex flex-col justify-between hover:shadow-md transition-all">
                                                        <div>
                                                                <div class="flex justify-between items-start mb-2">
                                                                        <span
                                                                                class="text-label-sm font-label-sm px-2 py-0.5 bg-blue-50 text-blue-600 rounded">Engineering</span>
                                                                        <span
                                                                                class="material-symbols-outlined text-gray-400">more_vert</span>
                                                                </div>
                                                                <h4
                                                                        class="font-label-md text-label-md font-bold text-on-surface">
                                                                        API Gateway Modernization</h4>
                                                                <p
                                                                        class="text-body-md font-body-md text-secondary mt-1">
                                                                        Refactoring legacy endpoints to GraphQL.</p>
                                                        </div>
                                                        <div class="mt-lg flex justify-between items-center">
                                                                <div class="flex -space-x-2">
                                                                        <img alt="Intern"
                                                                                class="w-8 h-8 rounded-full border-2 border-white"
                                                                                data-alt="A smiling young woman of Asian descent, representing a university intern, in a clean and modern studio setting with a soft lavender background. She looks professional and enthusiastic, wearing a simple navy blazer over a white shirt. The lighting is bright and even, highlighting her friendly expression and professional demeanor."
                                                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuBC0vHpwIC8Tiv4jbsNYtvs4UA0sYH62ScY2ULfLsiycS5qHxxqfpHUHRLdNQ4MEXtLI9F-j1NlrmjOsiy1i1WsjDjDKvNJYZkgv6J3vNFzQ13fJpl3l31tpprvXNmv3p3R5AT84m2PO-vVCg2npCHXla3RV63yTgJS0cU5l9T43ocN46NNKmw0lL8F-EeYdDzdzrtlaOfSy1THSYe-XdVUVoB8-fxGPwrCWwgWZdYK93DBKuLIKduUPsX13Idx1n2Rq9K8sAOJgQ" />
                                                                        <img alt="Intern"
                                                                                class="w-8 h-8 rounded-full border-2 border-white"
                                                                                data-alt="A portrait of a young male intern with a warm and professional smile, set against a blurred modern architectural backdrop. He has a clean-cut appearance, wearing a collared shirt. the color palette is dominated by airy blues and crisp whites, evoking a sense of corporate professionalism and academic ambition."
                                                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuAln-sQprbtl-bcilV8neh23wK9JC4SifRA42oS226Vh5hasVXVMhHp_nNU3S6c4rcf90mZqucwhYbPwQuAUgkdkgycafBZ1tve9c_JtWXEuEAayUy-vNheHz8TzS4yTNzSdVQZAn1F_LpHs3Vi5AGonvg4MVi8f0Py8eocNJSJAXQ3Q7RJ3sck3Z_HEbYLTAEfFG6V6652LsxF4y_xic2jiCd5accUgYpClxJvP1pG58RB8giP3YcKJE4WkpthIC2tdFZrnZ0gXg" />
                                                                        <div
                                                                                class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                                                                +1</div>
                                                                </div>
                                                                <span
                                                                        class="text-label-sm text-green-600 font-label-sm">3/4
                                                                        Assigned</span>
                                                        </div>
                                                </div>
                                                <!-- Project Card 2 -->
                                                <div
                                                        class="border border-outline-variant rounded-lg p-md flex flex-col justify-between hover:shadow-md transition-all">
                                                        <div>
                                                                <div class="flex justify-between items-start mb-2">
                                                                        <span
                                                                                class="text-label-sm font-label-sm px-2 py-0.5 bg-purple-50 text-purple-600 rounded">Design</span>
                                                                        <span
                                                                                class="material-symbols-outlined text-gray-400">more_vert</span>
                                                                </div>
                                                                <h4
                                                                        class="font-label-md text-label-md font-bold text-on-surface">
                                                                        Mobile Design System</h4>
                                                                <p
                                                                        class="text-body-md font-body-md text-secondary mt-1">
                                                                        Building a unified library for iOS/Android.</p>
                                                        </div>
                                                        <div class="mt-lg flex justify-between items-center">
                                                                <div class="flex -space-x-2">
                                                                        <div
                                                                                class="w-8 h-8 rounded-full border-2 border-white bg-secondary-container flex items-center justify-center">
                                                                                <span
                                                                                        class="material-symbols-outlined text-sm text-secondary">add</span>
                                                                        </div>
                                                                </div>
                                                                <span
                                                                        class="text-label-sm text-amber-600 font-label-sm font-bold italic">Unassigned</span>
                                                        </div>
                                                </div>
                                                <!-- Project Card 3 -->
                                                <div
                                                        class="border border-outline-variant rounded-lg p-md flex flex-col justify-between hover:shadow-md transition-all">
                                                        <div>
                                                                <div class="flex justify-between items-start mb-2">
                                                                        <span
                                                                                class="text-label-sm font-label-sm px-2 py-0.5 bg-green-50 text-green-600 rounded">Marketing</span>
                                                                        <span
                                                                                class="material-symbols-outlined text-gray-400">more_vert</span>
                                                                </div>
                                                                <h4
                                                                        class="font-label-md text-label-md font-bold text-on-surface">
                                                                        User Growth Campaign</h4>
                                                                <p
                                                                        class="text-body-md font-body-md text-secondary mt-1">
                                                                        SEO optimization for the new landing pages.</p>
                                                        </div>
                                                        <div class="mt-lg flex justify-between items-center">
                                                                <div class="flex -space-x-2">
                                                                        <img alt="Intern"
                                                                                class="w-8 h-8 rounded-full border-2 border-white"
                                                                                data-alt="A confident young professional woman standing in a high-tech lobby with glass walls. She is wearing a modern corporate outfit, and her expression is one of focus and readiness. The scene is bathed in natural daylight, creating a bright and professional atmosphere that fits a premium recruitment platform aesthetic."
                                                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuDkA0PfTlQo7gnUd_tjN4B7VJBWkwZBUKJgKYgN3_xZF6xzFNfrmpOlWxBrWX8DSc8l4gW9PXEIikMUV-Iy3sQo0OEslaEQm62prUslYsJk9yzLiWWDuCOI72IQq9NwgZrr21hv-ZHm0b_v6dAkNTg7OYS29iqoaOg9lhQcULKj_BYD5Xa58Gwf_R0J3oR1il6WEs10UXq3tG3uRsrb5mrwY7OQghGjifW88ThnqtkqEzxvETmxKEeiVMe3ft2KkSq-E5Jn0gRIBw" />
                                                                </div>
                                                                <span
                                                                        class="text-label-sm text-secondary font-label-sm">1/1
                                                                        Assigned</span>
                                                        </div>
                                                </div>
                                        </div>
                                </div>
                                <!-- Recent Activity Table -->
                                <div
                                        class="col-span-12 bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant overflow-hidden">
                                        <div
                                                class="px-lg py-md border-b border-gray-100 flex justify-between items-center">
                                                <h3
                                                        class="font-label-md text-label-md text-on-background uppercase tracking-widest">
                                                        Internship Monitoring & Logs</h3>
                                                <div class="flex gap-2">
                                                        <button class="p-1 hover:bg-gray-100 rounded"><span
                                                                        class="material-symbols-outlined text-gray-400">filter_list</span></button>
                                                        <button class="p-1 hover:bg-gray-100 rounded"><span
                                                                        class="material-symbols-outlined text-gray-400">download</span></button>
                                                </div>
                                        </div>
                                        <table class="w-full text-left">
                                                <thead>
                                                        <tr class="bg-gray-50/50 border-b border-gray-100">
                                                                <th
                                                                        class="px-lg py-3 font-label-sm text-label-sm text-secondary">
                                                                        INTERN NAME</th>
                                                                <th
                                                                        class="px-lg py-3 font-label-sm text-label-sm text-secondary">
                                                                        PHASE</th>
                                                                <th
                                                                        class="px-lg py-3 font-label-sm text-label-sm text-secondary">
                                                                        ACTIVITY STATUS</th>
                                                                <th
                                                                        class="px-lg py-3 font-label-sm text-label-sm text-secondary">
                                                                        PROGRESS</th>
                                                                <th
                                                                        class="px-lg py-3 font-label-sm text-label-sm text-secondary text-right">
                                                                        ACTION</th>
                                                        </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                        <tr class="hover:bg-gray-50 transition-colors">
                                                                <td class="px-lg py-4">
                                                                        <div class="flex items-center gap-3">
                                                                                <div
                                                                                        class="w-10 h-10 rounded-full bg-primary-fixed overflow-hidden">
                                                                                        <img alt="Intern"
                                                                                                data-alt="A portrait of a young man with a creative and modern appearance, wearing a stylish denim jacket. He is set against a vibrant but professional studio background with soft geometric patterns. The lighting is high-quality and directional, emphasizing his approachable and innovative personality, consistent with a modern corporate design system."
                                                                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCs3Wft3NDKqC-KoEdzHD3O08iAkIef4hLPhGPd1WQrqA0yLac_mbeynAwzVvzAm5Q5t2j7qmDP5B3lUFR--N_kXJkSGsJaPDYlVM1PHfl5SXG62smsxFsaDWuI-NN5PhyWyodjDdequJT3yQ0H_S537XwmpiAsvGlJcj0yE33Z1t6KATIjG-WBFMZD-PBRXJPV6eegS9B6hD1oU5WfbJcYRcPjfx_2Zo0AEina-zis07QfvpLcij3x0jLERlvyc1YopEIccyElGA" />
                                                                                </div>
                                                                                <div>
                                                                                        <p
                                                                                                class="font-label-md text-label-md font-bold">
                                                                                                Alex Rivera</p>
                                                                                        <p
                                                                                                class="text-[11px] text-secondary">
                                                                                                Engineering • Stanford
                                                                                                Univ.</p>
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                                <td class="px-lg py-4 font-body-md text-body-md">Mentor
                                                                        Evaluation</td>
                                                                <td class="px-lg py-4">
                                                                        <span
                                                                                class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[11px] font-bold flex items-center gap-1 w-max"><span
                                                                                        class="w-1.5 h-1.5 rounded-full bg-green-600 inline-block"></span>Active</span>
                                                                </td>
                                                                <td class="px-lg py-4">
                                                                        <div
                                                                                class="w-32 bg-gray-100 h-1.5 rounded-full">
                                                                                <div
                                                                                        class="bg-primary h-full w-[75%] rounded-full">
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                                <td class="px-lg py-4 text-right">
                                                                        <button
                                                                                class="text-primary font-label-sm text-label-sm font-bold">Details</button>
                                                                </td>
                                                        </tr>
                                                        <tr class="hover:bg-gray-50 transition-colors">
                                                                <td class="px-lg py-4">
                                                                        <div class="flex items-center gap-3">
                                                                                <div
                                                                                        class="w-10 h-10 rounded-full bg-tertiary-fixed overflow-hidden">
                                                                                        <img alt="Intern"
                                                                                                data-alt="A professional headshot of a young woman with a focused and intelligent expression. She is in a minimalist office setting with large windows that let in soft, diffused light. The aesthetic is clean and modern, using a palette of white and cool grays, highlighting her as a dedicated intern candidate."
                                                                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCf8PCOZwoHctpxUjjV0i0QsN2gm0XFsDmmFpLM_4bXCJXvSeIVNxdvE_b3aZWmU1n2MaB_ZTkjGin0uQh-PeZ8OPKcbtwY1yM3r35BJeuVlea9XN7qhzhzvacCTDz0Mq3R2_MQTaywnj8rN001vmYMteqHxeJRJpOCHJ1jtMlSSujax8Q7SpRAFusKWU1TzwSnwon8IdVMCBDICifYxa3asOzFqaRCgj47Y5qOHCAAPwdvrko0RVRLmZphULdECwJ7Px-nWykOMw" />
                                                                                </div>
                                                                                <div>
                                                                                        <p
                                                                                                class="font-label-md text-label-md font-bold">
                                                                                                Sarah Chen</p>
                                                                                        <p
                                                                                                class="text-[11px] text-secondary">
                                                                                                Product • MIT</p>
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                                <td class="px-lg py-4 font-body-md text-body-md">Daily
                                                                        Logs</td>
                                                                <td class="px-lg py-4">
                                                                        <span
                                                                                class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[11px] font-bold flex items-center gap-1 w-max"><span
                                                                                        class="w-1.5 h-1.5 rounded-full bg-amber-600 inline-block"></span>Delayed</span>
                                                                </td>
                                                                <td class="px-lg py-4">
                                                                        <div
                                                                                class="w-32 bg-gray-100 h-1.5 rounded-full">
                                                                                <div
                                                                                        class="bg-amber-500 h-full w-[40%] rounded-full">
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                                <td class="px-lg py-4 text-right">
                                                                        <button
                                                                                class="text-primary font-label-sm text-label-sm font-bold">Details</button>
                                                                </td>
                                                        </tr>
                                                        <tr class="hover:bg-gray-50 transition-colors">
                                                                <td class="px-lg py-4">
                                                                        <div class="flex items-center gap-3">
                                                                                <div
                                                                                        class="w-10 h-10 rounded-full bg-secondary-fixed overflow-hidden">
                                                                                        <img alt="Intern"
                                                                                                data-alt="A portrait of a young male intern with a confident and professional smile, set against a blurred modern architectural backdrop. He has a clean-cut appearance, wearing a collared shirt. the color palette is dominated by airy blues and crisp whites, evoking a sense of corporate professionalism and academic ambition."
                                                                                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuDEd5lSYOxxvAl89E-zu6dpaNPAMkWW60q32sj_O9IE7M_FZHT5AooVie7VFvqVoi7X012OyCzvbX7FmlFCPOkaps5UO5SR7b6uKfTPPqjJTh319LvNyxVbIbn2j9ZJ9DKlEuc5r6gHQb1GpTZ83CGdlLAEhbQznBPawevSrBy5vHSJS6cpBI5wY1oVRD-Ugd8GFZ4gQ08jRcJo2xnoJrv6B7mHREAXxSAlHNZSIiJ1b3mYaL4-C1k0-mPZP7N4RHrA1ZxKdgSskQ" />
                                                                                </div>
                                                                                <div>
                                                                                        <p
                                                                                                class="font-label-md text-label-md font-bold">
                                                                                                Marcus Johnson</p>
                                                                                        <p
                                                                                                class="text-[11px] text-secondary">
                                                                                                Design • RISD</p>
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                                <td class="px-lg py-4 font-body-md text-body-md">Project
                                                                        Assignment</td>
                                                                <td class="px-lg py-4">
                                                                        <span
                                                                                class="px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[11px] font-bold flex items-center gap-1 w-max"><span
                                                                                        class="w-1.5 h-1.5 rounded-full bg-red-600 inline-block"></span>Inactive</span>
                                                                </td>
                                                                <td class="px-lg py-4">
                                                                        <div
                                                                                class="w-32 bg-gray-100 h-1.5 rounded-full">
                                                                                <div
                                                                                        class="bg-gray-300 h-full w-[5%] rounded-full">
                                                                                </div>
                                                                        </div>
                                                                </td>
                                                                <td class="px-lg py-4 text-right">
                                                                        <button
                                                                                class="text-primary font-label-sm text-label-sm font-bold">Details</button>
                                                                </td>
                                                        </tr>
                                                </tbody>
                                        </table>
                                        <div
                                                class="px-lg py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
                                                <p class="text-label-sm text-secondary">Showing 1-10 of 124 interns</p>
                                                <div class="flex gap-2">
                                                        <button class="px-3 py-1 border border-outline-variant rounded bg-white text-label-sm disabled:opacity-50"
                                                                disabled="">Previous</button>
                                                        <button
                                                                class="px-3 py-1 border border-outline-variant rounded bg-white text-label-sm">Next</button>
                                                </div>
                                        </div>
                                </div>
                        </div>
                </div>
        </main>
</body>

</html>