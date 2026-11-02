<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Attendance Register')</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=Noto+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        paper: '#F7F4EC',
                        forest: '#1E3D32',
                        'forest-deep': '#14291F',
                        gold: '#E8C56A',
                        ink: '#2B3A67',
                        'ink-soft': '#6B7594',
                        present: '#4C7A5D',
                        absent: '#B33A3A',
                    },
                    fontFamily: {
                        display: ['"Special Elite"', 'monospace'],
                        sans: ['"Noto Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Noto Sans', sans-serif; overflow-x: hidden; }
        button, a, input, select { -webkit-tap-highlight-color: transparent; }
        button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible {
            outline: 3px solid rgba(232, 197, 106, 0.75); outline-offset: 2px;
        }
        .nav-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            padding: 7px 12px; border-radius: 8px; font-size: 13px; font-weight: 600;
            background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.18);
            color: white; text-decoration: none; white-space: nowrap; transition: all 0.15s ease;
        }
        .nav-btn:hover { background: rgba(232,197,106,0.30); border-color: rgba(232,197,106,0.55); }
        .nav-btn-danger { background: rgba(179,58,58,0.20); border-color: rgba(179,58,58,0.40); }
        .nav-btn-danger:hover { background: rgba(179,58,58,0.45); }
        @media (max-width: 640px) {
            .nav-btn { padding: 6px 10px; font-size: 11px; gap: 4px; }
            .nav-btn svg { width: 14px; height: 14px; }
        }
        @media (max-width: 450px) {
            body { font-size: 14px; }
            main { padding-left: 12px; padding-right: 12px; }
            .nav-btn { padding: 6px 8px; font-size: 10px; gap: 3px; }
            .nav-btn svg { width: 12px; height: 12px; }
        }
    
        /* Shared Attendance UI System — Forest, Gold & Paper */
        :root {
            --ui-forest:#1E3D32; --ui-forest-deep:#14291F; --ui-gold:#E8C56A;
            --ui-paper:#F7F4EC; --ui-ink:#26382F; --ui-muted:#718078;
            --ui-border:#E2E8E3; --ui-success:#347653; --ui-success-bg:#EAF4ED;
            --ui-warning:#A86B12; --ui-warning-bg:#FFF5DF; --ui-danger:#B33A3A;
            --ui-danger-bg:#FCEEEE; --ui-info:#315F83; --ui-info-bg:#EAF2F8;
            --ui-radius:12px; --ui-shadow:0 3px 12px rgba(30,61,50,.07);
        }
        html{scroll-behavior:smooth}
        body{color:var(--ui-ink);background:var(--ui-paper);font-family:'Noto Sans',sans-serif;-webkit-font-smoothing:antialiased}
        main{flex:1 0 auto}
        button,input,select,textarea{font:inherit}
        button,a,input,select,textarea{-webkit-tap-highlight-color:transparent}
        button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid rgba(232,197,106,.72);outline-offset:2px}
        button:not(.nav-btn),input[type="submit"],input[type="button"],a[role="button"]{min-height:40px;border-radius:9px;font-weight:600;letter-spacing:.005em;transition:background-color .16s ease,border-color .16s ease,color .16s ease,box-shadow .16s ease,transform .16s ease}
        button:not(.nav-btn):not([class*="bg-"]),input[type="submit"]:not([class*="bg-"]),input[type="button"]:not([class*="bg-"]){padding:9px 15px;color:#fff;background:var(--ui-forest);border:1px solid var(--ui-forest)}
        button:not(.nav-btn):hover,input[type="submit"]:hover,input[type="button"]:hover,a[role="button"]:hover{box-shadow:0 3px 9px rgba(30,61,50,.14)}
        button:disabled,input:disabled,select:disabled,textarea:disabled{cursor:not-allowed;opacity:.58}
        input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="hidden"]),select,textarea{min-height:40px;border:1px solid #D6DFD8;border-radius:9px;background-color:#fff;color:var(--ui-ink);padding:9px 11px;transition:border-color .16s ease,box-shadow .16s ease}
        textarea{min-height:92px}
        input::placeholder,textarea::placeholder{color:#98A39C}
        input:focus,select:focus,textarea:focus{border-color:var(--ui-forest);box-shadow:0 0 0 3px rgba(30,61,50,.10);outline:none}
        input[type="checkbox"],input[type="radio"]{accent-color:var(--ui-forest);width:16px;height:16px}
        table{border-collapse:separate;border-spacing:0}
        table thead th{background:#EEF2ED;color:#344B3E;font-weight:700;font-size:12px;letter-spacing:.025em}
        table tbody tr{transition:background-color .14s ease}
        table tbody tr:hover{background-color:#F6F8F5}
        table th,table td{border-color:var(--ui-border)}
        hr{border-color:var(--ui-border)}
        .btn-primary{background:var(--ui-forest)!important;border-color:var(--ui-forest)!important;color:#fff!important}
        .btn-primary:hover{background:var(--ui-forest-deep)!important}
        .btn-secondary{background:#EEF2ED!important;border:1px solid #DCE5DD!important;color:var(--ui-forest)!important}
        .btn-success{background:var(--ui-success)!important;border-color:var(--ui-success)!important;color:#fff!important}
        .btn-warning{background:#D99A2B!important;border-color:#D99A2B!important;color:#fff!important}
        .btn-danger{background:var(--ui-danger)!important;border-color:var(--ui-danger)!important;color:#fff!important}
        .status-success{color:var(--ui-success);background:var(--ui-success-bg)}
        .status-warning{color:var(--ui-warning);background:var(--ui-warning-bg)}
        .status-danger{color:var(--ui-danger);background:var(--ui-danger-bg)}
        .status-info{color:var(--ui-info);background:var(--ui-info-bg)}
        .developer-credit{flex-shrink:0;background:var(--ui-forest)!important;border-top:1px solid rgba(232,197,106,.35);color:#fff!important}
        @media(max-width:640px){button:not(.nav-btn),input[type="submit"],input[type="button"],a[role="button"]{min-height:38px}table{font-size:12px}table th,table td{padding:8px 9px}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;transition-duration:.01ms!important}}

    </style>

    @stack('styles')
</head>

<body class="bg-paper min-h-screen text-ink flex flex-col">

    <header class="bg-forest text-white shadow-md">
        <div class="max-w-7xl mx-auto px-3 sm:px-5">
            <div class="flex items-center justify-between gap-2 sm:gap-4 min-h-[64px] py-2">

                <div class="flex-shrink-0 flex flex-col justify-center">
                    <div class="font-display text-[8px] sm:text-[9px] md:text-[10px] tracking-[0.15em] uppercase text-gold mb-0.5">
                        Attendance Register
                    </div>
                    <h1 class="text-xs sm:text-sm md:text-base font-bold whitespace-nowrap leading-none">
                        @yield('header_title')
                    </h1>
                    @yield('header_meta')
                </div>

                <div class="flex-1 min-w-0 flex items-center justify-center gap-2 px-1 sm:px-2">
                    <img src="/images/logo.jpeg" alt="School Logo" class="h-8 sm:h-10 w-8 sm:w-10 rounded-full flex-shrink-0 object-cover">
                    <div class="font-extrabold text-[13px] sm:text-base md:text-lg lg:text-xl tracking-wider drop-shadow-md truncate">
                        {{ config('school.full_name') }}
                    </div>
                </div>

                <nav class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0">
                    @hasSection('header_links')
                        @yield('header_links')
                    @else
                        <a href="{{ route('dashboard') }}" class="nav-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5 sm:w-4 sm:h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
                            </svg>
                            <span>Dashboard</span>
                        </a>
                        <a href="{{ route('change-password.show') }}" class="nav-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5 sm:w-4 sm:h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <span>Password</span>
                        </a>
                        <form method="post" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="nav-btn nav-btn-danger">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="w-3.5 h-3.5 sm:w-4 sm:h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                <span>Log Out</span>
                            </button>
                        </form>
                    @endif
                </nav>

            </div>
        </div>
    </header>

    <main class="max-w-7xl w-full flex-1 mx-auto px-4 sm:px-6 pt-4 pb-2 sm:pt-5">
        @yield('content')
    </main>

    <footer class="developer-credit mt-auto flex items-center justify-center gap-1 px-3 py-2 text-[10px] sm:text-[11px]">
        Developed by <a href="https://github.com/MMunna5" target="_blank" rel="noopener noreferrer" class="font-semibold text-gold hover:underline">Munna</a>
    </footer>

</body>
</html>
